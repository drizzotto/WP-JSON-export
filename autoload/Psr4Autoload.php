<?php

namespace Posts_Jsoner\autoload;

class Psr4Autoload
{
    public static function getNamespaces(string $root = '')
    {
        return [
            'Posts_Jsoner' => $root . DIRECTORY_SEPARATOR,
            'Posts_Jsoner\autoload' => $root . DIRECTORY_SEPARATOR . 'autoload',
            'Posts_Jsoner\Data' => $root . DIRECTORY_SEPARATOR . 'Data',
            'Posts_Jsoner\Storage' => $root . DIRECTORY_SEPARATOR . 'Storage',
            'Posts_Jsoner\admin' => $root . DIRECTORY_SEPARATOR . 'admin',
        ];
    }
}