<?php
namespace Posts_Jsoner\Data;

use Posts_Jsoner\Storage\FileSystem;

trait MapperCommon
{
    public final function hasWildCard(string $field): bool
    {
        return strpos($field,'*') > 0;
    }

    /**
     * @param string $source
     * @param object $post
     * @param object $customs
     * @return array
     */
    public static function wildCardToArray(string $source, object $post, object $customs): array
    {
        $parts = explode('.', $source);
        return (array)${$parts[0]};
    }

    /**
     * @param string $str
     * @return string
     */
    public final function cleanupStr(string $str): string
    {
        return preg_replace("#[^a-zA-Z0-9 \-_]#", "", $str);
    }

    /**
     * @param string $source
     * @return array
     */
    public final function getParts(string $source): array
    {
        return explode('.', $source) ?? [];
    }

    /**
     * @param string $postType
     * @param string $mapper
     * @param string $format
     * @return array
     */
    public function getTemplate(string $postType, string $mapper = 'default', string $format = 'json'): array
    {
        $name = $mapper . DIRECTORY_SEPARATOR . $postType;
        $pconfig = get_option('jsoner_config_root', JSONER_CONFIG_ROOT);
        if (!file_exists( $pconfig . DIRECTORY_SEPARATOR . $name .sprintf('.%s', $format))) {
            $name = $mapper . DIRECTORY_SEPARATOR . "default";
        }

        return FileSystem::loadConfig($name, $format);
    }
}