<?php

namespace Posts_Jsoner\Data;

use \Posts_Jsoner\Storage\FileSystem;
use \Posts_Jsoner\Storage\S3Wrapper;

class BulkExport
{

    /**
     * @param string $siteName
     * @param int $blogId
     * @return bool
     */
    public static function exportSite(string $siteName, int $blogId): bool
    {
        set_time_limit(0);
        $env = \Post_Jsoner_Admin::getActiveSiteEnvironment() ?? 'qa';
        $filesystem = new FileSystem();
        $s3 = self::getS3($env);
        $langs = self::getLangs($blogId);
        $categoryOpt = get_option('categories','{"value":"categories","enabled":false}');
        $categoryType = json_decode($categoryOpt, 1);
        foreach ($langs as $lang) {
            $_lang = array_key_exists('code', $lang)
                ? $lang['code']
                : '';

            if ($categoryType['enabled'] === true) {
                self::getCategries($filesystem, $siteName, $_lang, $categoryType['value']);
            }

            self::saveElement($filesystem, $siteName, $_lang);
        }

        if (!empty($s3) && S3Wrapper::checkConnection() && \Post_Jsoner_S3_Config::isEnabled($env)) {
            $source = JSONER_EXPORT_PATH . DIRECTORY_SEPARATOR . $siteName;
            if (file_exists($source)) {
                $target = $siteName;
                try {
                    $s3->uploadDirectory($source, $target);
                } catch (\Exception $exception) {
                    error_log("\n---\nBulkExport::exportSite: S3 upload Exception: ".$exception->getTraceAsString()."\n---\n", 3, DEBUG_FILE);
                }
            }
        }

        return true;
    }

    /**
     * @param FileSystem $filesystem
     * @param string $country
     * @param string $lang
     * @param string $filename
     * @return void
     */
    private static function getCategries(
        FileSystem $filesystem,
        string     $country,
        string     $lang = '',
        string     $filename = 'categories'
    ): void
    {
        if (!empty($lang)) {
            global $sitepress;
            if (!empty($sitepress)) {
                $sitepress->switch_lang($lang);
            }
        }

        $categories = [];
        $category = @get_categories(['suppress_filters' => false]);
        foreach ($category as $cat) {
            if (str_contains(strtolower($cat->slug), "uncategorized")) {
                continue;
            }

            $categories[$cat->slug] = [
                "id" => $cat->term_id,
                "name" => $cat->name,
                "slug" => $cat->slug,
                "description" => $cat->category_description,
                "count" => $cat->category_count,
                "order" => $cat->term_order
            ];

        }

        if (!empty($categories)) {
            $filesystem->saveToJson($country, $lang, $categories, $filename);
        }
    }

    /**
     * @param string $type
     * @param string $lang
     *
     * @return array
     */
    private static function getPosts(string $type, string $lang = ''): array
    {
        global $wpdb;

        if (empty($lang)) {
            $lang = apply_filters( 'wpml_current_language', null );
        }

        $result = [];
        wp_reset_query();
        $post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type=%s AND post_status IN ('publish', 'private')", $type));
        $post_ids = array_filter($post_ids, function($value) use ($lang) {
            $language_details = apply_filters( 'wpml_post_language_details', null, $value );
            if (("default" === $lang) || ($language_details['language_code']==$lang)) {
                return $value;
            }
        },ARRAY_FILTER_USE_BOTH);

        $posts = array_map(fn($pid) => get_post($pid), $post_ids);

        if (!empty($posts)) {
            $sid = self::toggleDefaultSite();
            $mapperName = get_option('post_jsoner_mapper', 'default');
            self::toggleDefaultSite($sid);
            $mapper = MapperFactory::getMapper($mapperName);
            $template = $mapper->getTemplate($type, $mapperName);
            foreach ($posts as $post) {
                $normalizedCustom = $mapper->reformatCustoms($post->ID);
                $result[] = $mapper->map((object)$post, $template, $normalizedCustom);
            }
        }

        return $result;
    }

    private static function toggleDefaultSite(int $siteId=1): int
    {
        $bid = ($siteId==1) ? get_current_blog_id() : $siteId;
        if (is_multisite()) {
            switch_to_blog($siteId);
        }
        return $bid;
    }

    /**
     * @param string $env
     * @return object
     */
    private static function getS3(string $env): object
    {
        try {
            $s3wrapper = new S3Wrapper($env);
        } catch (\Exception $exception) {
            \error_log("BulkExport::getS3 error: ".$exception->getMessage(),3,DEBUG_FILE);
            $s3wrapper = (object)null;
        }

        return $s3wrapper;
    }

    /**
     * @param int $blogId
     * @return array|array[]
     */
    private static function getLangs(int $blogId): array
    {
        $result = [];
        global $sitepress;
        if (!empty($sitepress)) {
            switch_to_blog($blogId);
            $result = @$sitepress->get_active_languages(1);
        }

        if (empty($result)) {
            return ['default' => ['code' => 'default']];
        }

        return $result;
    }

    /**
     * @param FileSystem $filesystem
     * @param string $siteName
     * @param string $_lang
     * @return void
     */
    private static function saveElement(FileSystem $filesystem, string $siteName, string $_lang): void
    {
        global $wp_post_types;
        $builtin_types = [
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block',
            'wp_navigation',
            'wp_global_styles',
            'wp_template_part',
            'wp_template',
        ];
        $prefix = 'post_jsoner_';
        $exclude = array_merge(['acf-field', 'acf-field-group'], $builtin_types);

        foreach ($wp_post_types as $post_type) {
            if (in_array($post_type->name, $exclude)) {
                continue;
            }

            $opt = $prefix.$post_type->name;
            $obj = \Post_Jsoner_Admin::getGlobalOption($opt);
            $type = json_decode($obj,1);
            $currentLang = $_lang;
            if (!empty($type) && (true === $type['enabled'])) {
                $elements = self::getPosts($post_type->name, $_lang);
                if (!empty($elements)) {
                    $value = $type['value'] ?? $post_type->name;
                    if ($_lang == "default") {
                        $currentLang = \Post_Jsoner_Admin::getGlobalOption($prefix . "default_language", "en");
                    }
                    $filesystem->saveToJson($siteName, $currentLang, $elements, $value);
                }
            }
        }
    }
}
