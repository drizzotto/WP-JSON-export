<?php

namespace Posts_Jsoner\Data;

use Exception;
use Post_Jsoner_Admin;
use Post_Jsoner_S3_Config;
use Posts_Jsoner\Storage\FileSystem;
use Posts_Jsoner\Storage\S3Wrapper;
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
        $langs = self::getLangs($blogId);
        $categoryOpt = get_option('categories', '{"value":"categories","enabled":false}');
        $categoryType = json_decode($categoryOpt, true);

        foreach ($langs as $lang) {
            $_lang = $lang['code'] ?? '';

            if ($categoryType['enabled'] === true) {
                self::getCategories($filesystem, $siteName, $_lang, $categoryType['value']);
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
                    error_log("\n---\nBulkExport::exportSite: S3 upload Exception: " . $exception->getTraceAsString() . "\n---\n", 3, DEBUG_FILE);
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
            error_log("BulkExport::getS3 error: " . $exception->getMessage(), 3, DEBUG_FILE);
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
            $result = self::getActiveLanguages();
        }
        return empty($result) ? ['default' => ['code' => 'default']] : $result;
    }

    /**
     * Retrieves and saves categories to a JSON file.
     *
     * @param FileSystem $filesystem The filesystem helper object
     * @param string $country The country for which categories are retrieved
     * @param string $lang (optional) The language for which categories are retrieved
     * @param string $filename (optional) The filename for the JSON file
     * @return void
     */
    private static function getCategories(FileSystem $filesystem, string $country, string $lang = '', string $filename = 'categories'): void
    {
        if (!empty($lang)) {
            global $sitepress;
            if (!empty($sitepress)) {
                $sitepress->switch_lang($lang);
            }
        }

        $categories = array_filter(get_categories(['suppress_filters' => false]), function ($cat) {
            return !str_contains(strtolower($cat->slug), "uncategorized");
        });
        $categories = array_map(function ($cat) {
            return ["id" => $cat->term_id, "name" => $cat->name, "slug" => $cat->slug, "description" => $cat->category_description, "count" => $cat->category_count, "order" => $cat->term_order];

        }, $categories);

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
                $elements = self::getPosts($post_type->name, $_lang, $blogId,  $author, $status, $category, $dateRange);
                $value = $type['value'] ?? $post_type->name;
                // Set the current language to default if necessary
                if ($_lang === 'default') {
                    $currentLang = Post_Jsoner_Admin::getGlobalOption($prefix . 'default_language', 'en');
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
    private static function getPosts(string $type, string $lang = '', int $blogId = 1,  string $author = "", string $status = "", string $category = "", string $dateRange = ""): array
    {
        global $wpdb;

        // extra check
        $blogId = ($blogId == 0) ? 1 : $blogId;
        try {
            $query_args = [];
            $lang = empty($lang) ? apply_filters('wpml_current_language', null) : $lang;
            $current = get_current_blog_id();
            self::toggleDefaultSite($blogId);
            list($query, $query_args) = self::buildQuery($type, $query_args, $author, $category, $dateRange, $status);
            self::toggleDefaultSite($current);
            $post_ids = $wpdb->get_col($wpdb->prepare($query, ...$query_args));
            if (is_plugin_active('sitepress-multilingual-cms/sitepress.php')) {
                $post_ids = array_filter($post_ids, function ($value) use ($lang, $type) {
                    global $wpdb;
                    $query = "SELECT language_code 
                    FROM {$wpdb->prefix}icl_translations
                    WHERE element_id=%d
                    AND element_type=%s
                    LIMIT 1";

                    $language_for_element_prepared = $wpdb->prepare($query, [$value, "post_" . $type]);
                    $language = $wpdb->get_var($language_for_element_prepared);

                    return ("default" === $lang) || ($language === $lang);
                });
            }
            $current = get_current_blog_id();
            self::toggleDefaultSite($blogId);
            $posts = array_map(fn($pid) => get_post($pid), $post_ids);
            self::toggleDefaultSite($current);
            if (empty($posts)) {
                return [];
            }

            switch_to_blog(1);
            $mapperName = get_option('post_jsoner_mapper', 'default');
            switch_to_blog($blogId);
            $mapper = \Posts_Jsoner\Data\MapperFactory::getMapper($mapperName);
            $template = $mapper->getTemplate($type, $mapperName);
            $result = array_map(function ($post) use ($mapper, $template) {
                $normalizedCustom = $mapper->reformatCustoms($post->ID);
                return $mapper->map((object)$post, $template, $normalizedCustom);
            }, $posts);
        } catch (Exception $e) {
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
     * @param array $query_args
     * @param string $author
     * @param string $category
     * @param string $dateRange
     * @param string $status
     * @return array
     */
    private static function buildQuery(string $type, array $query_args, string $author, string $category, string $dateRange, string $status): array
    {
        global $wpdb;
        $query = "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status NOT IN ('archived')";
        $query_args[] = $type;
        if ($type != "page") {
            if (!empty($author)) {
                $query_args[] = $author;
                $query .= " AND post_author=%s";
            }

            if (!empty($category)) {
                $query_args[] = $category;
                $query .= " AND post_category=%s";
            }

            if (!empty($dateRange)) {
                $_dateRange = explode(' - ', $dateRange);
                $query_args[] = date('Y-m-d H:i:s', strtotime(trim($_dateRange[0]) . ' 00:00:00'));
                $query_args[] = date('Y-m-d H:i:s', strtotime(trim($_dateRange[1]) . ' 23:59:59'));
                $query .= " AND post_modified BETWEEN %s AND %s";
            }
        }
        if (!empty($status)) {
            $query_args[] = $status;
            $query .= " AND post_status=%s";
        } else {
            $query .= " AND post_status IN ('publish', 'private')";
        }
        return  [$query, $query_args];
    }

    /**
     * @return array|object|\stdClass[]|null
     */
    private static function getActiveLanguages()
    {
        global $wpdb;
        $res_query
            = "
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

        $res_query_prepared = $wpdb->prepare( $res_query, 'en', 'en' );
        $res                = $wpdb->get_results( $res_query_prepared, ARRAY_A );
        return $res;
    }
}
