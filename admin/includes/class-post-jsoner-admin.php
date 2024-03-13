<?php

use Posts_Jsoner\Admin\Administrator;

class Post_Jsoner_Admin
{
    private Post_Jsoner_Settings_Fields $settings_Fields;

    private array $exportTypes = ['post' => ['value' => 'post', 'enabled' => true], 'page' => ['value' => 'page', 'enabled' => true], 'categories' => ['value' => 'categories', 'enabled' => true]];

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct(private string $plugin_name, private string $version)
    {
        add_action('admin_menu', function () {
            return $this->addPluginAdminMenu();
        }, 9);
        add_action('admin_init', function (): void {
            $this->registerAndBuildFields();
        });
        $this->settings_Fields = new Post_Jsoner_Settings_Fields();
    }

    public function addPluginAdminMenu(): void
    {
        add_management_page(Administrator::TITLE, Administrator::SUB_TITLE, 'administrator', $this->plugin_name, function () {
            return $this->displayPluginAdminDashboard();
        });
    }

    public function displayPluginAdminDashboard(): void
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'views/page.php';
    }

    public function registerAndBuildFields(): void
    {
        $this->loadTypes();
        /**
         * First, we add_settings_section. This is necessary since all future settings must belong to one.
         * Second, add_settings_field
         * Third, register_setting
         */
        add_settings_section(// ID used to identify this section and with which to register options
            'post_jsoner_general_section', // Title to be displayed on the administration page
            '', // Callback used to render the description of the section
            function () {
                return $this->post_jsoner_display_general_account();
            }, // Page on which to add this section of options
            'post_jsoner_general_settings');

        $fields = $this->settings_Fields->getFields($this->exportTypes);
        $this->settings_Fields->registerSettings($fields);
    }

    public function loadTypes(): void
    {
        global $wp_post_types;
        $built_in = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_navigation', 'wp_global_styles', 'wp_template_part', 'wp_template',];
        $exclude = array_merge(['acf-field', 'acf-field-group'], $built_in);
        $types = array_keys($wp_post_types);

        foreach ($types as $type) {
            if (in_array($type, $this->exportTypes)) {
                continue;
            }
            if (in_array($type, $exclude)) {
                continue;
            }
            $this->exportTypes[$type] = ['value' => $type, 'enabled' => false];
        }

    }

    public function post_jsoner_display_general_account(): void
    {
        echo '<p>These settings apply to all Post JSONer functionality.<br><small><i>PHP constants have precedence over options</i></small></p>';
    }

    public static function getActiveSiteEnvironment(): string
    {
        return get_option('wp_site_env', WP_SITE_ENV);
    }

    public static function getGlobalOption($option_name, $default = false)
    {
        global $wpdb;
        global $table_prefix;
        $prefix = (is_multisite()) ? str_replace(get_current_blog_id() . '_', '', $table_prefix) : $table_prefix;


        $table = $prefix . 'options';
        $query = "SELECT option_value FROM " . $table . " WHERE option_name = %s LIMIT 1";
        $row = $wpdb->get_row($wpdb->prepare($query, $option_name));
        if (is_object($row)) {
            return $row->option_value;
        }

        return $default;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles(): void
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in post_jsoner_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The post_jsoner_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */
        wp_enqueue_style($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/css/post-jsoner-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts(): void
    {
        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in post_jsoner_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The post_jsoner_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/js/post-jsoner-admin.js', array('jquery'), $this->version, false);
        wp_enqueue_script("moment", plugin_dir_url(dirname(__FILE__)) . 'assets/js/moment.min.js', array('jquery'), $this->version, false);
        wp_enqueue_script("daterangepicker", plugin_dir_url(dirname(__FILE__)) . 'assets/js/daterangepicker.min.js', array('jquery', 'jquery-ui-core', 'moment' ), $this->version, false);
    }

    public function displayPluginAdminSettings(): void
    {
        if (isset($_GET['tab'])) {
        }

        if (isset($_GET['error_message'])) {
            add_action('admin_notices', function ($error_message) {
                return $this->postJsonerSettingsMessages($error_message);
            });
            do_action('admin_notices', $_GET['error_message']);
        }

        require_once 'partials/' . $this->plugin_name . '-admin-settings-display.php';
    }

    public function postJsonerSettingsMessages($error_message): void
    {
        if ($error_message === '1') {
            $message = __('There was an error adding this setting. Please try again.  If this persists, shoot us an email.', 'my-text-domain');
            $err_code = esc_attr('post_jsoner_example_setting');
            $setting_field = 'post_jsoner_example_setting';
        }

        $type = 'error';
        add_settings_error($setting_field, $err_code, $message, $type);
    }

    public function getCountSites(): int
    {
        $sites = (array)Post_Jsoner_Admin::getSites();
        return count($sites) ?? 0;
    }

    public static function getSites()
    {
        if (function_exists('get_sites')) {
            return get_sites(['public' => 1, 'archived' => 0, 'path__not_in' => ['/', 'uk'], 'orderby' => 'path',]);
        }

        return 1;
    }

    public function getCategories(): array
    {
        // get_categories args
        $args = array('hide_empty' => true);
        $categories = [];
        if (is_multisite()) {
            // All sites
            $sites = Post_Jsoner_Admin::getSites();
            // Current Site
            $current = get_current_site();

            foreach ($sites as $blog) {
                // switch to the blog
                switch_to_blog($blog->blog_id);

                $categories = get_categories($args);
            }
            // return to the current site
            switch_to_blog($current->id);
        } else {
            $categories = get_categories($args);
        }
        return $categories;
    }
}
