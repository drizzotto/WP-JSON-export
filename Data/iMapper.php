<?php

namespace Posts_Jsoner\Data;

interface iMapper
{
    /**
     * @param string $postType
     * @param string $mapper
     * @param string $format
     * @return array
     */
    public function getTemplate(string $postType, string $mapper = 'default', string $format = 'json'): array;

    /**
     * @param object $post
     * @param array $template
     * @param array $customs
     * @param int $iteration
     * @return array
     */
    public function map(object $post, array $template, array $customs = [], int $iteration = 0): array;

    /**
     * @param object $post
     * @param string $source
     * @param object $customs
     * @return string
     */
    public function getValue(object $post, string $source, object $customs): string;

    /**
     * @param int $post_id
     * @param string $postType
     * @return array
     */
    public function reformatCustoms(int $post_id, string $postType = 'post'): array;
}
