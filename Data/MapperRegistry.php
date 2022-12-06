<?php

namespace Posts_Jsoner\Data;

class MapperRegistry
{
    public static array $registry = [
        'default' => '\\' . \Posts_Jsoner\Data\DefaultMapper::class,
        'mywu' => '\\' . \Posts_Jsoner\Data\MywuMapper::class
    ];

    /**
     * @return int[]|string[]
     */
    public static function getMappers(): array
    {
        return array_keys(self::$registry);
    }
}