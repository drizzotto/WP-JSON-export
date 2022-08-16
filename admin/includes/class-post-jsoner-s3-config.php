<?php

class Post_Jsoner_S3_Config
{
    private static $prefix = 'post_jsoner_s3_';

    /**
     * @param $env
     * @return string
     */
    public static function getAccessKey($env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_KEY';
        return constant($const);
    }

    /**
     * @param $env
     * @return string
     */
    public static function getSecretKey($env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_SECRET';
        return constant($const);
    }

    /**
     * @param $env
     * @return string
     */
    public static function getRegion($env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_REGION';
        return constant($const);
    }

    /**
     * @return array
     */
    private static function getSettings(): array
    {
        $opt = get_option('post_jsoner_s3_settings', '[]');
        $out = json_decode($opt, 1);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $out = [];
        }
        return $out;
    }

    /**
     * @param $env
     * @return string
     */
    public static function getBucketValue($env): string
    {
        $settings = self::getSettings();
        $name = self::$prefix . strtolower($env) . '_bucket';
        return (empty($settings) || !array_key_exists($name, $settings))
            ? ''
            : $settings[$name];
    }

    /**
     * @param $env
     * @return string
     */
    public static function getPathValue($env): string
    {
        $settings = self::getSettings();
        $name = self::$prefix . strtolower($env) . '_path';
        return (empty($settings) || !array_key_exists($name, $settings))
            ? ''
            : $settings[$name];
    }

    /**
     * @param $env
     * @return string
     */
    public static function isEnabled($env): string
    {
        $settings = self::getSettings();
        $name = self::$prefix . strtolower($env) . '_enabled';
        return  (empty($settings) || !array_key_exists($name, $settings))
            ? ''
            : $settings[$name];
    }

    /**
     * @param $env
     * @return array[]
     */
    public static function getOptionSection($env): array
    {
        $_env = strtolower($env);
        return [
            [
                'type' => 'input',
                'subtype' => 'text',
                'id' => 'post_jsoner_s3_'.$_env.'_bucket',
                'name' => 'post_jsoner_s3_'.$_env.'_bucket',
                'class' => 'bucket',
                'label' => 'Bucket',
                'required' => 'false',
                'get_options_list' => '',
                'value' => Post_Jsoner_S3_Config::getBucketValue($env),
                'value_type' => 'normal',
                'wp_data' => 'option',
                'is_s3' => '1',
            ],
            [
                'type' => 'input',
                'subtype' => 'text',
                'id' => 'post_jsoner_s3_'.$_env.'_path',
                'name' => 'post_jsoner_s3_'.$_env.'_path',
                'class' => 'path',
                'label' => 'Path',
                'required' => 'false',
                'get_options_list' => '',
                'value' => Post_Jsoner_S3_Config::getPathValue($env),
                'value_type' => 'normal',
                'wp_data' => 'option',
                'is_s3' => '1',
            ],
            [
                'type' => 'input',
                'subtype' => 'checkbox',
                'id' => 'post_jsoner_s3_'.$_env.'_enabled',
                'name' => 'post_jsoner_s3_'.$_env.'_enabled',
                'class' => 'enabled',
                'label' => 'Is Enabled',
                'required' => 'false',
                'get_options_list' => '',
                'value' => Post_Jsoner_S3_Config::isEnabled($env),
                'value_type' => 'normal',
                'wp_data' => 'option',
                'is_s3' => '1',
            ]
        ];
    }
}