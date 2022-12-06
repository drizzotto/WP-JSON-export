<?php
namespace Posts_Jsoner\Data;


class DefaultMapper implements iMapper
{
    use MapperCommon;

    /**
     * @param object $post
     * @param array $template
     * @param array $customs
     * @param int $iteration
     * @return array
     */
    public function map(object $post, array $template, array $customs = [], int $iteration = 0): array
    {
        $result = [];

        foreach ($template as $key => $source) {
            if (!is_array($source) && $this->hasWildCard($source)) {
                $result = array_merge($result, $this->wildCardToArray($source, $post, (object)$customs));
            } else {
                $result[$key] = is_array($source)
                    ? $this->map($post, $source, $customs, $iteration++)
                    : $this->getValue($post, $source, (object)$customs);

                // attempt to remove special characters from Index fields to prevent filter/search issues
                if (in_array($key, ['ID', 'name', 'slug'])) {
                    $result[$key] = $this->cleanupStr($result[$key]);
                }
            }
        }

        return $result;
    }

    /**
     * @param object $post
     * @param string $source
     * @param object $customs
     * @return string
     */
    public function getValue(object $post, string $source, object $customs): string
    {
        $parts = $this->$this->getParts($source);
        if (empty($parts)) {
            return $source;
        }
        if (!in_array($parts[0], ['post', 'customs'])) {
            return $source;
        }

        $countParts = count($parts);

        $output = (array)${$parts[0]} ?? [];
        for ($index = 1; $index < $countParts; ++$index) {
            if (is_array($output)) {
                if (!array_key_exists($parts[$index], $output)) {
                    $output = "";
                    break;
                }

                $output = $output[$parts[$index]];
            }
        }

        return is_array($output)
            ? (string)array_shift($output)
            : (string)$output;
    }

    /**
     * @param int $post_id
     * @param string $postType
     * @return array
     */
    public function reformatCustoms(int $post_id, string $postType = 'post'): array
    {
        $customs = (array)get_post_custom($post_id);
        $customFields = [];
        foreach ($customs as $key => $val) {
            if (!str_starts_with($key, "_") && !empty($val)) {
                if (is_array($val) && count($val) == 1) {
                    $val = array_shift($val);
                }

                $customFields[$key] = $val;
            }
        }

        if (array_key_exists("image", $customFields)) {
            $image = wp_get_attachment_image_src($customFields['image'], 'full');
            $customFields['image'] = [
                "href" => $image[0] ?? '',
                "height" => $image[1] ?? '',
                "width" => $image[2] ?? ''
            ];
        }

        if (array_key_exists("brand_logo", $customFields)) {
            $image = wp_get_attachment_image_src($customFields['brand_logo'], 'full');
            $customFields['logo'] = [
                "href" => $image[0] ?? '',
                "height" => $image[1] ?? '',
                "width" => $image[2] ?? ''
            ];
        }

        $customFields['canonical_id'] = $post_id;
        if (function_exists('icl_object_id')) {
            $customFields['canonical_id'] = icl_object_id($post_id, $postType, false, ICL_LANGUAGE_CODE);
        }

        return $customFields;
    }

}
