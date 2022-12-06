<?php

class Post_Jsoner_S3_Config
{
    private static string $prefix = 'post_jsoner_s3_';

    public static function getAccessKey(string $env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_KEY';
        try {
            return constant($const);
        } catch (Throwable $exception) {
            error_log("getAccessKey Error {$const}\n" . $exception->getMessage());
        }
        return "";
    }

    /**
     * @param string $env
     * @return string
     */
    public static function getSecretKey(string $env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_SECRET';
        try {
            return constant($const);
        } catch (Throwable $exception) {
            error_log("getSecretKey Error {$const}\n" . $exception->getMessage());
        }
        return "";
    }

    /**
     * @param string $env
     * @return string
     */
    public static function getRegion(string $env): string
    {
        $const = 'S3_UPLOADS_' . strtoupper($env) . '_REGION';
        try {
            return constant($const);
        } catch (Throwable $exception) {
            error_log("getRegion Error {$const}\n" . $exception->getMessage());
        }
        return "";
    }

    /**
     * @return mixed[]
     */
    private static function getSettings(): array
    {
        $opt = \Post_Jsoner_Admin::getGlobalOption('post_jsoner_s3_settings', '[]');
        $out = json_decode($opt, 1);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return $out;
    }

    /**
     * @param string $env
     * @return string
     */
    public static function getBucketValue(string $env): string
    {
        $settings = self::getSettings();
        $name = self::$prefix . strtolower($env) . '_bucket';
        return (empty($settings) || !array_key_exists($name, $settings))
            ? ''
            : $settings[$name];
    }

    /**
     * @param string $env
     * @return string
     */
    public static function getPathValue(string $env): string
    {
        $settings = self::getSettings();
        $name = self::$prefix . strtolower($env) . '_path';
        return (empty($settings) || !array_key_exists($name, $settings))
            ? ''
            : $settings[$name];
    }

    /**
     * @param string $env
     * @return string
     */
    public static function isEnabled(string $env = 'qa'): string
    {
        $settings = self::getSettings();
        if (empty($env)) {
            $env = 'qa';
        }

        $name = self::$prefix . strtolower($env) . '_enabled';
        return  (empty($settings) || !array_key_exists($name, $settings))
            ? ''
            : $settings[$name];
    }

    /**
     * @param string $env
     * @return array[]
     */
    public static function getOptionSection(string $env): array
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
                'value' => Post_Jsoner_S3_Config::getBucketValue($_env),
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
                'value' => Post_Jsoner_S3_Config::getPathValue($_env),
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
                'value' => Post_Jsoner_S3_Config::isEnabled($_env),
                'value_type' => 'normal',
                'wp_data' => 'option',
                'is_s3' => '1',
            ]
        ];
    }
}
