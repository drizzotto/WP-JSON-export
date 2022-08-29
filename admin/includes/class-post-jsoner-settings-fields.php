<?php

use Posts_Jsoner\Data\MapperRegistry;

class Post_Jsoner_Settings_Fields
{
    private $fields_def = [
        'post_jsoner_config_root' => [
            'title' => 'Config Root path',
            'args' => [
                'type' => 'input',
                'subtype' => 'text',
                'id' => 'post_jsoner_config_root',
                'name' => 'post_jsoner_config_root',
                'required' => 'true',
                'get_options_list' => '',
                'value' => JSONER_CONFIG_ROOT,
                'value_type' => 'normal',
                'wp_data' => 'option'
            ]
        ],
        'post_jsoner_export_path' => [
            'title' => 'Export path',
            'args' => [
                'type' => 'input',
                'subtype' => 'text',
                'id' => 'post_jsoner_export_path',
                'name' => 'post_jsoner_export_path',
                'required' => 'true',
                'get_options_list' => '',
                'value' => JSONER_EXPORT_PATH,
                'value_type' => 'normal',
                'wp_data' => 'option'
            ]
        ],
        'post_jsoner_mapper' => [
            'title' => 'Mapper',
            'args' => [
                'type' => 'select',
                'id' => 'post_jsoner_mapper',
                'name' => 'post_jsoner_mapper',
                'required' => 'true',
                'get_options_list' => '',
                'value' => JSONER_MAPPER,
                'value_type' => 'normal',
                'wp_data' => 'option'
            ]
        ],
        'wp_site_env' => [
            'title' => 'Current Site Environment',
            'args' => [
                'type' => 'input',
                'subtype' => 'text',
                'id' => 'wp_site_env',
                'name' => 'wp_site_env',
                'required' => 'false',
                'get_options_list' => '',
                'value' => WP_SITE_ENV,
                'value_type' => 'normal',
                'wp_data' => 'option'
            ]
        ],
        // S3 Accordion
        'post_jsoner_s3_settings' => [
            'title' => 'S3 Settings',
            'args' => [
                'type' => 'group',
                'subtype' => 'accordion',
                'id' => 'post_jsoner_s3_settings',
                'name' => 'post_jsoner_s3_settings',
                'required' => 'false',
                'get_options_list' => '',
                'value' => '',
                'value_type' => 'serialized',
                'wp_data' => 'option',
                'sections' => []
            ]
        ],
    ];

    public function __construct()
    {
        $msg = $this->isS3Enabled()
            ? ''
            : 'This option needs S3_UPLOADS_KEY and S3_UPLOADS_SECRET set to be enabled.';
        $this->fields_def['post_jsoner_s3_bucket']['args']['tooltip'] = $msg;
        $this->fields_def['post_jsoner_s3_enabled']['args']['tooltip'] = $msg;

        foreach (['QA', 'UAT', 'PROD'] as $env) {
            $this->fields_def['post_jsoner_s3_settings']['args']['sections'][$env] = Post_Jsoner_S3_Config::getOptionSection($env);
        }

    }

    public function isS3Enabled($env = 'stage'): bool
    {
        return (strtoupper($env) === strtoupper(Post_Jsoner_Admin::getActiveSiteEnvironment()));
    }

    public function getFields($exportTypes): array
    {
        $result = [];
        $this->appendExportTypes($exportTypes);
        foreach ($this->fields_def as $key => $definitions) {
            if (array_key_exists('is_s3', $definitions['args']) && (!$this->isS3Enabled())) {
                $definitions['args']['disabled'] = 1;
            }
            add_settings_field(
                $key,
                $definitions['title'] ?? "",
                [$this, 'post_jsoner_render_settings_field'],
                'post_jsoner_general_settings',
                'post_jsoner_general_section',
                $definitions['args']
            );
            $result[] = $key;
        }

        return $result;
    }

    private function appendExportTypes($types): void
    {
        if (!empty($types)) {
            foreach ($types as $type => $item) {
                $id = 'post_jsoner_' . strtolower($type);
                $initial = (array_key_exists($type, $item)) ? $item[$type] : ['value' => $type, 'enabled' => false];
                $option = json_decode(get_option($id, json_encode($initial)), 1);

                $title = 'Export filename for [' . ucfirst($type) . ']';
                $this->fields_def[$id] = [
                    'title' => $title,
                    'args' => [
                        'type' => 'checked-text',
                        'subtype' => 'text',
                        'id' => $id,
                        'name' => $id,
                        'class' => 'checked-text',
                        'required' => 'true',
                        'get_options_list' => '',
                        'value' => $option['value'] ?? $type,
                        'default' => $type,
                        'value_type' => 'normal',
                        'wp_data' => 'option'
                    ]
                ];
            }
        }
    }

    public function resgiterSettings(array $fields): void
    {
        foreach ($fields as $field) {
            register_setting(
                'post_jsoner_general_settings',
                $field
            );
        }
    }

    public function post_jsoner_render_settings_field($args)
    {
        /* EXAMPLE INPUT
                            'type'      => 'input',
                            'subtype'   => '',
                            'id'    => $this->plugin_name.'_example_setting',
                            'name'      => $this->plugin_name.'_example_setting',
                            'required' => 'required="required"',
                            'get_option_list' => "",
                                'value_type' = serialized OR normal,
        'wp_data'=>(option or post_meta),
        'post_id' =>
        */
        if (array_key_exists('wp_data', $args)) {
            if ($args['wp_data'] == 'option') {
                $wp_data_value = get_option($args['name']);
                if (empty($wp_data_value)) {
                    $wp_data_value = $args['value'];
                }
            } elseif ($args['wp_data'] == 'post_meta') {
                $wp_data_value = get_post_meta($args['post_id'], $args['name'], true);
            }
        }

        $tooltip = (!empty($args['tooltip']))
            ? '<div class="help-tip"><p>' . $args['tooltip'] . '</p></div>'
            : '';

        if (array_key_exists('type', $args)) {
            switch ($args['type']) {
                case 'input':
                    $value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
                    $this->renderInput($args, $tooltip, $value);
                    break;
                case 'select':
                    $options = MapperRegistry::getMappers();
                    $value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;
                    $this->renderSelect($args['name'], $value, $options);
                    break;
                case 'group':
                    $this->renderGroup($args);
                    break;
                case 'checked-text':
                    $this->renderCheckedText($args);
                    break;
                default:
                    # code...
                    break;
            }
        }
    }

    /**
     * @param $args
     * @param string $tooltip
     * @param $value
     * @return void
     */
    private function renderInput($args, string $tooltip, $value): void
    {
        $class = (isset($args['class'])) ? ' class="' . $args['class'] . '" ' : '';
        if ($args['subtype'] != 'checkbox') {
            $prependStart = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">' . $args['prepend_value'] . '</span>' : '';
            $prependEnd = (isset($args['prepend_value'])) ? $tooltip . '</div>' : $tooltip . '';
            $step = (isset($args['step'])) ? 'step="' . $args['step'] . '"' : '';
            $min = (isset($args['min'])) ? 'min="' . $args['min'] . '"' : '';
            $max = (isset($args['max'])) ? 'max="' . $args['max'] . '"' : '';
            if (isset($args['disabled'])) {
                // hide the actual input bc if it was just a disabled input the info saved in the database would be wrong - bc it would pass empty values and wipe the actual information
                echo $prependStart . '<input ' . $class . ' type="' . $args['subtype'] . '" id="' . $args['id'] . '_disabled" ' . $step . ' ' . $max . ' ' . $min . ' name="' . $args['name'] . '_disabled" size="40" disabled value="' . esc_attr($value) . '" /><input type="hidden" id="' . $args['id'] . '" ' . $step . ' ' . $max . ' ' . $min . ' name="' . $args['name'] . '" size="40" value="' . esc_attr($value) . '" />' . $prependEnd;
            } else {
                echo $prependStart . '<input ' . $class . ' type="' . $args['subtype'] . '" id="' . $args['id'] . '" "' . $args['required'] . '" ' . $step . ' ' . $max . ' ' . $min . ' name="' . $args['name'] . '" size="40" value="' . esc_attr($value) . '" />' . $prependEnd;
            }
        } else {
            $checked = ($value) ? 'checked' : '';
            echo empty($args['disabled'])
                ? '<input ' . $class . ' type="' . $args['subtype'] . '" id="' . $args['id'] . '" "' . $args['required'] . '" name="' . $args['name'] . '" size="40" value="1" ' . $checked . '/>' . $tooltip
                : '<input ' . $class . ' type="' . $args['subtype'] . '" id="' . $args['id'] . '" "' . $args['required'] . '" name="' . $args['name'] . '" size="40" value="1" ' . $checked . ' disabled/>' . $tooltip;
        }
    }

    /**
     * @param $name
     * @param $value
     * @param array $options
     * @return void
     */
    private function renderSelect($name, $value, array $options): void
    {
        echo '<select name="' . $name . '" value="' . $value . '">';
        foreach ($options as $option) {
            echo ($option == $value)
                ? '<option value="' . $option . '" selected>' . ucfirst($option) . '</option>'
                : '<option value="' . $option . '">' . ucfirst($option) . '</option>';
        }
        echo '</select>';
    }

    private function renderGroup($args): void
    {
        $this->fields_def['post_jsoner_s3_settings']['args']['value'] = get_option('post_jsoner_s3_settings', "{}");
        $fieldVal = json_decode($this->fields_def['post_jsoner_s3_settings']['args']['value'], 1);
        echo '<input type="hidden" id="' . $args['id'] . '" name="' . $args['name'] . '" value="' . esc_attr($this->fields_def['post_jsoner_s3_settings']['args']['value']) . '" />';
        echo '<div id="accordion">';
        foreach ($args['sections'] as $key => $section) {
            $activeClass = $this->isS3Enabled($key) ? 'active' : '';
            echo '<h3 class="' . $activeClass . '">' . $key . '</h3>';
            echo '<div>';
            foreach ($section as $field) {
                $value = array_key_exists($field['id'], $fieldVal) ? $fieldVal[$field['id']] : '';
                echo '<div class="row">';
                echo '<label for="' . $field['id'] . '" style="line-height: 2.5">' . $field['label'] . '</label>';
                echo '<div>';
                $this->renderInput($field, '', $value);
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private function renderCheckedText($args): void
    {
        $this->fields_def[$args['id']]['args']['value'] = get_option($args['id'], "{}");
        $value = $this->fields_def[$args['id']]['args']['value'];
        $option = json_decode($value, 1);

        $checked = (array_key_exists('enabled', $option) && (true === (bool)$option['enabled']))
            ? 'checked=checked'
            : '';
        $inputValue = (array_key_exists('value', $option))
            ? $option['value']
            : $args['default'];

        echo '<div>';
        echo '<input type="hidden" id="' . $args['id'] . '" name="' . $args['name'] . '" value="' . esc_attr($value) . '" />';
        echo '<input type="hidden" id="' . $args['id'] . '_debug" name="' . $args['name'] . '_debug" value="' . var_export($option, 1) . '" />';
        $args['id'] = $args['id'] . '_input';
        $args['name'] = $args['name'] . '_input';
        $this->renderInput($args, '', $inputValue);
        echo '<input type="checkbox" ' . $checked . ' class="checked-text" id="' .
            $args['id'] . '_check" name="' . $args['id'] . '_check" style="margin: 5px !important;" />';
        echo '</div>';
    }
}