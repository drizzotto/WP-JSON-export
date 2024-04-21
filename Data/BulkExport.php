<?php

namespace Posts_Jsoner\Data;

use Exception;
use Post_Jsoner_Admin;
use Post_Jsoner_S3_Config;
use Posts_Jsoner\Storage\FileSystem;
use Posts_Jsoner\Storage\S3Wrapper;
use stdClass;
use function error_log;

class BulkExport
{
    /**
     * Export a site with the given site name and blog ID.
     *
     * @param string $siteName The name of the site to export.
     * @param int $blogId The ID of the blog to export.
     * @param string $author (optional) The author of the exported content.
     * @param string $status (optional) The status of the exported content.
     * @param string $category (optional) The category of the exported content.
     * @param string $dateRange (optional) The date range of the exported content.
     * @return bool
     * @throws Exception
     */
    public static function exportSite(string $siteName, int $blogId, string $author = "", string $status = "", string $category = "", string $dateRange = ""): bool
    {
        set_time_limit(0);
        $env = Post_Jsoner_Admin::getActiveSiteEnvironment() ?? 'qa';
        $filesystem = new FileSystem();
        $s3 = self::getS3($env);

        switch_to_blog($blogId);
        $langs = self::getLangs($blogId);
        $categoryOpt = get_option('categories', '{"value":"categories","enabled":false}');
        $categoryType = json_decode($categoryOpt, true);

        foreach ($langs as $lang) {
            $_lang = $lang['code'] ?? '';

            if ($categoryType['enabled'] === true) {
                self::getCategories($filesystem, $siteName, $blogId, $_lang, $categoryType['value']);
            }

            self::saveElement($filesystem, $siteName, $blogId, $_lang, $author, $status, $category, $dateRange);
        }

        if (!empty($s3) && S3Wrapper::checkConnection() && Post_Jsoner_S3_Config::isEnabled($env)) {
            $source = JSONER_EXPORT_PATH . DIRECTORY_SEPARATOR . $siteName;
            if (file_exists($source)) {
                $target = $siteName;
                try {
                    $s3->uploadDirectory($source, $target);
                } catch (Exception $exception) {
                    self::plog("BulkExport::exportSite: S3 upload Exception: "  . $exception->getTraceAsString());
                }
            }
        }

        return true;
    }

    /**
     * Retrieve an S3 object based on the environment.
     *
     * @param string $env The environment to retrieve the S3 object for.
     * @return object The retrieved S3 object
     * @throws Exception Description of the exception thrown
     */
    private static function getS3(string $env): object
    {
        try {
            $s3wrapper = new S3Wrapper($env);
        } catch (Exception $exception) {
            self::plog("BulkExport::getS3 error: " . $exception->getMessage());
            $s3wrapper = (object)null;
        }

        return $s3wrapper;
    }

    /**
     * Retrieves the active languages for a given blog.
     *
     * @param int $blogId The ID of the blog to retrieve languages for.
     * @return array The list of active languages, with 'default' as fallback.
     */
    private static function getLangs(int $blogId): array
    {
        $result = [];
        if (is_plugin_active("sitepress-multilingual-cms/sitepress.php")) {
            switch_to_blog($blogId);
            global $sitepress;
            return $sitepress->get_active_languages(true);
        }
        return empty($result) ? ['default' => ['code' => 'default']] : $result;
    }

    /**
     * Retrieves and saves categories to a JSON file.
     *
     * @param FileSystem $filesystem The filesystem helper object
     * @param string $country The country for which categories are retrieved
     * @param int $blogId
     * @param string $lang (optional) The language for which categories are retrieved
     * @param string $filename (optional) The filename for the JSON file
     * @return void
     */
    private static function getCategories(FileSystem $filesystem, string $country, int $blogId, string $lang = '', string $filename = 'categories'): void
    {
        // extra check
        $blogId = ($blogId == 0) ? 1 : $blogId;
        $lang = empty($lang) ? apply_filters('wpml_current_language', null) : $lang;
        switch_to_blog($blogId);
        $args = ['suppress_filters' => false, 'hide_empty' => false, 'fields' => 'ids',];
        $uncategorized_id = get_cat_ID('Uncategorized');
        $category_ids = array_filter(get_categories($args), function ($cat) use ($uncategorized_id) {
            return $cat->term_id !== $uncategorized_id;
        });
        if (!empty($category_ids)) {
            $categories = array_map(function ($cat) use ($lang) {
                $trid = apply_filters('wpml_element_trid', NULL, $cat, "tax_category");
                $translations = apply_filters('wpml_get_element_translations', NULL, $trid, "tax_category");
                if (array_key_exists($lang, $translations) && !empty($translations[$lang])) {
                    $eid = $translations[$lang]->element_id;
                    return get_category_to_edit($eid);
                }
                return 0;
            }, $category_ids);
            $categories = array_map(function ($cat) {
                if (!empty($cat) && !is_null($cat)) {
                    return ["id" => $cat->term_id, "name" => $cat->name, "slug" => $cat->slug, "description" => $cat->category_description, "count" => $cat->category_count, "order" => $cat->term_order];
                }
            }, $categories);
            $categories = array_filter($categories);
            $categories = array_unique($categories, SORT_REGULAR);
        }

        if (!empty($categories)) {
            $filesystem->saveToJson($country, $lang, $categories, $filename);
        }
    }

    /**
     * Save elements for a site with the given site name, blog ID, language, and optional parameters.
     *
     * @param FileSystem $filesystem The filesystem helper object.
     * @param string $siteName The name of the site to save elements for.
     * @param int $blogId The ID of the blog to save elements for.
     * @param string $_lang The language to save elements in.
     * @param string $author (optional) The author of the saved elements.
     * @param string $status (optional) The status of the saved elements.
     * @param string $category (optional) The category of the saved elements.
     * @param string $dateRange (optional) The date range of the saved elements.
     * @return void
     * @throws Exception
     */
    private static function saveElement(FileSystem $filesystem, string $siteName, int $blogId, string $_lang, string $author = "", string $status = "", string $category = "", string $dateRange = ""): void
    {
        // Get the global post types array
        global $wp_post_types;
        // Define the built-in types to exclude
        $builtin_types = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_navigation', 'wp_global_styles', 'wp_template_part', 'wp_template',];
        // Set the option prefix and additional types to exclude
        $prefix = 'post_jsoner_';
        $exclude = array_merge(['acf-field', 'acf-field-group'], $builtin_types);

        foreach ($wp_post_types as $post_type) {
            // Skip excluded post types
            if (in_array($post_type->name, $exclude)) {
                continue;
            }

            // Get the post type option and decode the value
            $opt = $prefix . $post_type->name;
            $obj = Post_Jsoner_Admin::getGlobalOption($opt);
            $type = json_decode($obj, true);

            // Set the current language and default value
            $currentLang = $_lang;

            // Check if the type is enabled and get the posts if so
            if (!empty($type) && (true === $type['enabled'])) {
                $elements = self::getPosts($post_type->name, $_lang, $blogId, $author, $status, $category, $dateRange);
                $value = $type['value'] ?? $post_type->name;
                // Set the current language to default if necessary
                if ($_lang === 'default') {
                    $currentLang = Post_Jsoner_Admin::getGlobalOption($prefix . 'default_language', 'en');
                }
                // re-index array of elements to prevent json_encode convert to objects
                if (!empty($elements)) {
                    $newKeys = range(0, count($elements) - 1);
                    $values = array_values($elements);
                    $elements = array_combine($newKeys, $values);
                }
                // Save the elements to JSON
                $filesystem->saveToJson($siteName, $currentLang, !empty($elements) ? $elements : [], $value);
            }
        }
    }

    /**
     * Retrieves posts of a specific type and language.
     *
     * @param string $type The type of the post.
     * @param string $lang The language of the post.
     * @return array The posts of the specified type and language.
     * @throws Exception
     */
    private static function getPosts(string $type, string $lang = '', int $blogId = 1, string $author = "", string $status = "", string $category = "", string $dateRange = ""): array
    {
        // extra check
        $blogId = ($blogId == 0) ? 1 : $blogId;
        try {
            $lang = empty($lang) ? apply_filters('wpml_current_language', null) : $lang;
            self::toggleDefaultSite($blogId);
            $query_args = self::buildQuery($type, $author, $category, $dateRange, $status);

            $post_ids = get_posts($query_args);
            $posts = [];
            if (!empty($post_ids)) {
                $posts = array_map(function ($post) use ($type, $lang) {
                    $trid = apply_filters('wpml_element_trid', NULL, $post, "post_{$type}");
                    $translations = apply_filters('wpml_get_element_translations', NULL, $trid, "post_{$type}");
                    if (array_key_exists($lang, $translations) && !empty($translations[$lang])) {
                        $eid = $translations[$lang]->element_id;
                        return get_post($eid);
                    }
                    return 0;
                }, $post_ids);
                $posts = array_filter($posts);
            }

            switch_to_blog(1);
            $mapperName = get_option('post_jsoner_mapper', 'default');
            switch_to_blog($blogId);
            $mapper = MapperFactory::getMapper($mapperName);
            $template = $mapper->getTemplate($type, $mapperName);

            $result = array_map(function ($post) use ($mapper, $template) {
                $normalizedCustom = $mapper->reformatCustoms($post->ID);
                return $mapper->map((object)$post, $template, $normalizedCustom);
            }, $posts);
            if (!empty($result)) {
                $result = array_unique($result, SORT_REGULAR);
            }
        } catch (Exception $e) {
            self::plog("BulkExport::getPost:Exception " . var_export($e->getTraceAsString(),1));
            throw new Exception($e->getMessage());
        }

        return $result;
    }

    /**
     * Toggle the default site ID.
     *
     * @param int $siteId The site ID to toggle (default is 1).
     * @return int The updated site ID.
     */
    private static function toggleDefaultSite(int $siteId = 1): int
    {
        $bid = ($siteId === 1) ? get_current_blog_id() : $siteId;
        if (is_multisite() && $siteId !== 1) {
            switch_to_blog($siteId);
        }
        return $bid;
    }

    /**
     * @param string $type
     * @param string $author
     * @param string $category
     * @param string $dateRange
     * @param string $status
     * @return array
     */
    private static function buildQuery(string $type, string $author, string $category, string $dateRange, string $status): array
    {
        // exclude archived
        $eArgs = ['fields' => 'ids', 'post_status' => 'archived',];
        $excluded = get_posts($eArgs);
        // main query
        $args = ['fields' => 'ids', 'post_type' => $type, 'post__not_in' => $excluded,];

        if (!empty($author)) {
            $args['post_author'] = $author;
        }

        if (!empty($category)) {
            $args['category'] = $category;
        }

        if (!empty($dateRange)) {
            $_dateRange = explode(' - ', $dateRange);
            $from = date('F jS, Y', strtotime($_dateRange[0]));
            $to = date('F jS, Y', strtotime($_dateRange[1]));
            $args['date_query'] = ['after' => $from, 'before' => $to, 'inclusive' => true,];
        }

        if (!empty($status)) {
            $args['post_status'] = [$status];
        } else {
            $args['post_status'] = ['publish', 'private'];
        }
        return $args;
    }

    /**
     * Pretty Log
     *
     * @param string $msg
     * @return void
     */
    public static function plog(string $msg): void
    {
        $date = "[" . date('Y-m-d H:i:s') . "] ";
        error_log("\n {$date} {$msg} \n\n", 3, DEBUG_FILE);
    }

    /**
     * @return array|object|stdClass[]|null
     */
    private static function getActiveLanguages()
    {
        global $wpdb;
        $res_query = "
            SELECT
              l.code,
              l.id,
              english_name,
              nt.name AS native_name,
              major,
              active,
              default_locale,
              encode_url,
              tag,
              lt.name AS display_name
			FROM {$wpdb->prefix}icl_languages l
			JOIN {$wpdb->prefix}icl_languages_translations nt
			  ON ( nt.language_code = l.code AND nt.display_language_code = l.code )
            LEFT OUTER JOIN {$wpdb->prefix}icl_languages_translations lt ON l.code=lt.language_code
			WHERE l.active = 1 AND 
			  ( lt.display_language_code = %s
			  OR (lt.display_language_code = 'en'
			    AND NOT EXISTS ( SELECT *
			          FROM {$wpdb->prefix}icl_languages_translations ls
			          WHERE ls.language_code = l.code
			            AND ls.display_language_code = %s ) ) )
            GROUP BY l.code, lt.name";

        $res_query_prepared = $wpdb->prepare($res_query, 'en', 'en');
        $res = $wpdb->get_results($res_query_prepared, ARRAY_A);
        return $res;
    }
}
