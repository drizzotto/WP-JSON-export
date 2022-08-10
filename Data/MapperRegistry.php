<?php

namespace Posts_Jsoner\Data;

class MapperRegistry
{
    public static array $registry = [
        'default' => '\\Posts_Jsoner\\Data\\DefaultMapper',
        'mywu' => '\\Posts_Jsoner\\Data\\MywuMapper'
    ];

    public static function getMappers(): array
    {
        return array_keys(self::$registry);
    }
}