<?php

class Post_Jsoner_S3_Config
{
    private static $prefix = 'post_jsoner_s3_';

    public static function getAccessKey($env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_KEY';
        if (defined(constant($const))) {
            return constant($const);
        }
        return "";
    }

    public static function getSecretKey($env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_SECRET';
        if (defined(constant($const))) {
            return constant($const);
        }
        return "";
    }

    public static function getRegion($env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_REGION';
        if (defined(constant($const))) {
            return constant($const);
        }
        return "";
    }

    public static function getBucketValue($env): string
    {
        $name = self::$prefix . $env . '_bucket';
        return get_option($name, '');
    }

    public static function getPathValue($env): string
    {
        $name = self::$prefix . $env . '_path';
        return get_option($name, '');
    }

    public static function isEnabled($env): string
    {
        $name = self::$prefix . $env . '_enabled';
        return get_option($name, '');
    }

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