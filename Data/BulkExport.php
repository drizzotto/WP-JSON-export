<?php

namespace Posts_Jsoner\Data;

use \Posts_Jsoner\Storage\FileSystem;
use \Posts_Jsoner\Storage\S3Wrapper;

class BulkExport
{


    public static function exportSite(string $siteName, int $blogId): bool
    {
        set_time_limit(0);

        $filesystem = new FileSystem();
        $s3 = self::getS3();
        $langs = self::getLangs($blogId);
        foreach ($langs as $lang) {
            $_lang = array_key_exists('code', $lang)
                ? $lang['code']
                : '';

            self::getCategries($filesystem, $siteName, $_lang);

            self::saveElement($_lang, $filesystem, $siteName);
        }

        if ((!empty($s3) && S3Wrapper::checkConnection())) {
            if (\Post_Jsoner_S3_Config::isEnabled(WP_SITE_ENV)) {
                $source = JSONER_EXPORT_PATH . DIRECTORY_SEPARATOR . $siteName;
                if (file_exists($source)) {
                    $target = $siteName;
                    try {
                        $s3->uploadDirectory($source, $target);
                    } catch (\Exception $e) {
                        error_log($e->getMessage(), 3, '/tmp/wp-errors.log');
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param FileSystem $filesystem
     * @param string $country
     * @param string $lang
     */
    private static function getCategries(
        FileSystem $filesystem,
        string     $country,
        string     $lang = ''
    ): void
    {
//        \error_log("here: " . $country . " - " . $lang);

        if (!empty($lang)) {
            global $sitepress;
            if (!empty($sitepress)) {
                $sitepress->switch_lang($lang);
            }
        }
        $categories = [];
        $category = @get_categories(['suppress_filters' => false]);
        foreach ($category as $cat) {
            if (strpos(strtolower($cat->slug), "uncategorized") !== false) {
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
            $filesystem->saveToJson($country, $lang, $categories, 'categories');
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
        $result = [];
        $args = [
            'post_type' => $type,
            'suppress_filters' => false,
            'posts_per_page' => -1,
            'post_status' => ['publish', 'private'],
        ];

        $posts = get_posts($args);
        $filteredPosts = $posts;

        if (!empty($lang)) {
            global $sitepress;
            if (!empty($sitepress)) {
                foreach ($posts as $post) {
                    $trid = $sitepress->get_element_trid($post->ID);
                    $translation = $sitepress->get_element_translations($trid);
                    if (empty($translation) && !(is_array($translation) && array_key_exists($lang,
                                $translation) && is_object($translation[$lang]) && property_exists($translation[$lang],
                                'element_id'))) {
                        continue;
                    }
                    $filteredPosts[] = get_post($translation[$lang]->element_id);
                }
            }
        }

        $posts = array_filter($filteredPosts);

        if (!empty($posts)) {
            error_log("getPosts->" . count($posts) . " - " . JSONER_MAPPER . "\n\n", 3, '/tmp/wp-errors.log');
            $mapper = MapperFactory::getMapper(JSONER_MAPPER);
            $template = $mapper->getTemplate($type, JSONER_MAPPER);
            foreach ($posts as $post) {
                $normalizedCustom = $mapper->reformatCustoms($post->ID);
                $result[] = $mapper->map((object)$post, $template, $normalizedCustom);
            }
        }

        return $result;
    }

    /**
     * @return object
     */
    private static function getS3(): object
    {
        try {
            $s3wrapper = new S3Wrapper(WP_SITE_ENV);
        } catch (\Exception $e) {
            \error_log("BulkExport::getS3 error: ".$e->getMessage(),3,'/tmp/wp-errors.log');
            $s3wrapper = (object)null;
        }
        return $s3wrapper;
    }

    /**
     * @param int $blogId
     * @return string[]
     */
    private static function getLangs(int $blogId): array
    {
        $result = ['code' => ''];
        global $sitepress;
        if (!empty($sitepress)) {
            switch_to_blog($blogId);
            $result = @$sitepress->get_active_languages(1);
        } else if (is_plugin_active(plugin_dir_path(__DIR__) . '../polylang/polylang.php')) {
            $_langs = pll_languages_list(['fields' => 'name']);
            foreach ($_langs as $lang) {
                $result[] = ['code' => $lang];
            }
        }
        return $result;
    }

    /**
     * @param $_lang
     * @param FileSystem $filesystem
     * @param string $siteName
     * @return void
     */
    private static function saveElement($_lang, FileSystem $filesystem, string $siteName): void
    {
        $builtin_types = [
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block',
        ];
        foreach (get_post_types('', 'names') as $post_type) {
            if (in_array($post_type, $builtin_types)) {
                continue;
            }
            $elements = self::getPosts($post_type, $_lang);
//            \error_log("saveElement::posts: ".var_export($elements,1)."\n\n--------\n\n",3,'/tmp/wp-errors.log');
            if (!empty($elements)) {
                $filesystem->saveToJson($siteName, $_lang, $elements, $post_type);
            }
        }
    }
}
