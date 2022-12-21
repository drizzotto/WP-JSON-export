<?php


class Post_Jsoner_Constants
{
    public static function setConstants(): void
    {
        $pdir = dirname(dirname(__DIR__));
        $jsoner_config_root = get_option('jsoner_config_root', $pdir . DIRECTORY_SEPARATOR . 'config');
        if (!defined('JSONER_CONFIG_ROOT')) {
            define('JSONER_CONFIG_ROOT', $jsoner_config_root);
        }

        $jsoner_export_path = get_option('jsoner_export_path', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'JSONER');
        if (!defined('JSONER_EXPORT_PATH')) {
            define('JSONER_EXPORT_PATH', $jsoner_export_path);
        }

        $wp_site_env = get_option('wp_site_env', 'stage');
        if (!defined('WP_SITE_ENV')) {
            define('WP_SITE_ENV', $wp_site_env);
        }

        $s3_wp_bucket = get_option('s3_wp_bucket', '');
        if (!defined('S3_WP_BUCKET')) {
            define('S3_WP_BUCKET', $s3_wp_bucket);
        }

        $s3ConnOK = \Posts_Jsoner\Storage\S3Wrapper::checkConnection();
        $jsoner_s3_enabled = ($s3ConnOK)
            ? get_option('jsoner_s3_enabled', false)
            : false;
        if (!defined('JSONER_S3_ENABLED')) {
            define('JSONER_S3_ENABLED', $jsoner_s3_enabled);
        }

        $jsoner_mapper = get_option('post_jsoner_mapper', 'default');
        if (!defined('JSONER_MAPPER')) {
            define('JSONER_MAPPER', $jsoner_mapper);
        }
    }
}