<?php

namespace Posts_Jsoner\Data;

class MywuMapper implements iMapper
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

            if ($this->hasImage($key) && is_string($result[$key])) {
                $result[$key] = $this->remapImage($result[$key]);
            }
        }

        return $result;
    }

    /**
     * @param object $post
     * @param string $source
     * @param object $customs
     *
     * @return string
     */
    public function getValue(object $post, string $source, object $customs): string
    {
        $parts = explode('.', $source) ?? [];
        if (empty($parts)) {
            return $source;
        }
        if (!in_array($parts[0], ['post', 'customs'])) {
            return $source;
        }

        $countParts = count($parts);

        $output = (array)${$parts[0]} ?? [];
        for ($index = 0; $index < $countParts; ++$index) {
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

    private function hasImage(string $key): bool
    {
        return (str_contains(strtolower($key), 'image'));
    }

    /**
     * @param string $id
     * @return string[]
     */
    private function remapImage(string $id): array
    {
        $result = [
            "href" => "",
            "height" => "",
            "width" => ""
        ];
        $image = wp_get_attachment_image_src($id, 'full');
        if (!empty($image) && is_array($image)) {
            $result['href'] = $image[0];
            $result['height'] = $image[1];
            $result['width'] = $image[2];
        }

        return $result;
    }

    /**
     * @param int $post_id
     * @param string $postType
     *
     * @return array
     */
    public function reformatCustoms(int $post_id, string $postType = 'post'): array
    {
        $customs = (array)@get_post_custom($post_id);

        $customFields = [];
        foreach ($customs as $key => $val) {
            if (!str_starts_with($key, "_") && !empty($val)) {
                if (is_array($val) && count($val) == 1) {
                    $val = array_shift($val);
                }

                $customFields[$key] = $val;
            }
        }

        $ptype = null;
        if (array_key_exists("reward_details", $customFields)) {
            $ptype = $customFields['reward_details'];
        }

        $customFields['reward_details'] = [];

        foreach ($customFields as $key => $val) {
            if (substr($key, 0, 17) == "reward_details_0_") {
                $customFields['reward_details'][substr($key, 17)] = $val;
                unset($customFields[$key]);
            }
        }

        if (!empty($ptype) && is_string($ptype)) {
            $_type = unserialize($ptype) ?? '';
            if (is_array($_type)) {
                $customFields['reward_details']['type'] = array_pop($_type);
            }
        }

        if (array_key_exists("image", $customFields)) {
            $image = wp_get_attachment_image_src($customFields['image'], 'full');
            if (!empty($image)) {
                $customFields['image'] = [
                    "href" => $image[0],
                    "height" => $image[1],
                    "width" => $image[2]
                ];
            }
        }

        if (array_key_exists("brand_logo", $customFields)) {
            $image = wp_get_attachment_image_src($customFields['brand_logo'], 'full');
            if (!empty($image)) {
                $customFields['logo'] = [
                    "href" => $image[0],
                    "height" => $image[1],
                    "width" => $image[2]
                ];
            }
        }

        if (array_key_exists("fulfillment_instructions", $customFields['reward_details'])) {
            $y = $customFields['reward_details']["fulfillment_instructions"];
            $customFields['reward_details']["fulfillment_instructions"] = [];
            for ($x = 0; $x < $y; ++$x) {
                $key = "fulfillment_instructions_" . $x . "_instruction_item";
                if (array_key_exists($key, $customFields['reward_details'])) {
                    $customFields['reward_details']["fulfillment_instructions"][] = $customFields['reward_details'][$key];
                    unset($customFields['reward_details'][$key]);
                }
            }
        }


        $my_current_lang = apply_filters( 'wpml_current_language', NULL );
        $customFields['canonical_id'] = apply_filters('wpml_object_id', $post_id, $postType, false, $my_current_lang);

        $customFields['category'] = [
            'id' => '',
            'name' => '',
            'slug' => '',
            'description' => '',
            'count' => '',
            'order' => ''
        ];
        $category = @get_the_category($post_id);
        if (!empty($category)) {
            $category = array_shift($category);
            if (is_object($category)) {
                $customFields['category']['id'] = $category->term_id ?? '';
                $customFields['category']['name'] = $category->name ?? '';
                $customFields['category']['slug'] = $category->slug ?? '';
                $customFields['category']['description'] = $category->description ?? '';
                $customFields['category']['count'] = $category->category_count ?? '';
                $customFields['category']['order'] = $category->term_order ?? '';
            }
        }

        return $customFields;
    }
}
