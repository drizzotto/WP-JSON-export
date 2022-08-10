<?php
namespace Posts_Jsoner\Data;

class MapperFactory
{

    /**
     * @param string $mapperType
     *
     * @return iMapper
     */
    public static function getMapper(string $mapperType): iMapper
    {
        return new MapperRegistry::$registry[$mapperType];
    }
}