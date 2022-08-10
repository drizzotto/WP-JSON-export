<?php

namespace Posts_Jsoner\Data;

interface iMapper
{
    public function getTemplate(string $postType, string $mapper = 'default', string $format = 'json'): array;
    public function map(object $post, array $template, array $customs = [], int $iteration = 0): array;
    public function getValue(object $post, string $source, object $customs): string;
    public function reformatCustoms(int $post_id, string $postType = 'post'): array;
}