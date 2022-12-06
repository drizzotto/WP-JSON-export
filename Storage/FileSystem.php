<?php

namespace Posts_Jsoner\Storage;

class FileSystem
{
    /**
     * @param string $country
     * @param string $lang
     * @param string $type
     *
     * @return array
     */
    public function loadFromJson(string $country, string $lang, string $type = 'posts'): array
    {
        try {
            $filename = $this->getOrCreateFilename($country, $lang, $type);
            return json_decode($this->load($filename), 1) ?? [];
        } catch (\Exception $exception) {
            error_log($exception->getMessage(),3,DEBUG_FILE);
            return [];
        }
    }

    /**
     * @param string $country
     * @param string $lang
     * @param array $data
     * @param string $type
     *
     * @return bool
     */
    public function saveToJson(string $country, string $lang, array $data, string $type = 'post'): bool
    {
        try {
            $filename = $this->getOrCreateFilename($country, $lang, $type);
            $out = $this->save($filename, json_encode($data));
        } catch (\Exception $exception) {
            error_log($exception->getMessage(),3,DEBUG_FILE);
            $out = false;
        }

        return $out;
    }

    /**
     * @param string $name
     * @param string $format
     *
     * @return array
     */
    public static function loadConfig(string $name, string $format='json'): array
    {
        $data = self::load(JSONER_CONFIG_ROOT . DIRECTORY_SEPARATOR . $name . '.' . $format);
        return (array)json_decode($data,1)
            ?? [];
    }

    /**
     * @param string $country
     * @param string $lang
     * @param string $type
     *
     * @return string
     * @throws \Exception
     */
    private function getOrCreateFilename(string $country, string $lang, string $type): string
    {
        $path = JSONER_EXPORT_PATH . DIRECTORY_SEPARATOR . $country . DIRECTORY_SEPARATOR. $lang;

        if (!file_exists($path)) {
            $ok = mkdir($path, 0777, true);
            if (!$ok) {
                throw new \Exception("Unable to create path");
            }
        }

        $filename = $path . DIRECTORY_SEPARATOR . $type . '.json';
        if (!file_exists($filename)) {
            touch($filename);
        }

        return $filename;
    }

    /**
     * @param string $filename - the full path to the file
     * @return string|bool
     */
    private static function load(string $filename): string|bool
    {
        return (file_exists($filename))
            ? file_get_contents($filename)
            : "";
    }

    /**
     * @param string $filename - the full path to the file
     * @param string $content
     *
     * @return bool
     */
    private static function save(string $filename, string $content): bool
    {
        return (file_put_contents($filename, $content) > 0);
    }
}
