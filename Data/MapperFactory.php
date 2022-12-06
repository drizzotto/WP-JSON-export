<?php
namespace Posts_Jsoner\Data;

class MapperFactory
{

    public static function getMapper(string $mapperType): iMapper
    {
        return new MapperRegistry::$registry[$mapperType];
    }
}