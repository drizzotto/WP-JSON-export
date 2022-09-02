<?php

class Post_Jsoner_Admin
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private string $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private string $version;

    private \Post_Jsoner_Settings_Fields $settings_Fields;

    private array $exportTypes = [
        'post' => [
            'value' => 'post',
            'enabled' => true
        ],
        'page' => [
            'value' => 'page',
            'enabled' => true
        ],
        'categories' => [
            'value' => 'categories',
            'enabled' => true
        ]
    ];

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct(string $plugin_name, string $version )
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action('admin_menu', array( $this, 'addPluginAdminMenu' ), 9);
        add_action('admin_init', array( $this, 'registerAndBuildFields' ));
        $this->settings_Fields = new Post_Jsoner_Settings_Fields();
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
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
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( dirname(__FILE__) ) . 'assets/css/post-jsoner-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
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

        wp_enqueue_script( $this->plugin_name, plugin_dir_url( dirname(__FILE__) ) . 'assets/js/post-jsoner-admin.js', array( 'jquery' ), $this->version, false );
    }

    public function addPluginAdminMenu() {
        add_management_page(
            \Posts_Jsoner\Admin\Administrator::TITLE,
            \Posts_Jsoner\Admin\Administrator::SUB_TITLE,
            'administrator',
            $this->plugin_name,
            array($this, 'displayPluginAdminDashboard')
        );
    }

    public function displayPluginAdminDashboard() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) .'views/page.php';
    }

    public function displayPluginAdminSettings() {
        // set this var to be used in the settings-display view
        isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';
        if(isset($_GET['error_message'])){
            add_action('admin_notices', array($this,'postJsonerSettingsMessages'));
            do_action( 'admin_notices', $_GET['error_message'] );
        }
        require_once 'partials/'.$this->plugin_name.'-admin-settings-display.php';
    }

    public function postJsonerSettingsMessages($error_message){
        if ($error_message === '1') {
            $message = __('There was an error adding this setting. Please try again.  If this persists, shoot us an email.', 'my-text-domain');
            $err_code = esc_attr('post_jsoner_example_setting');
            $setting_field = 'post_jsoner_example_setting';
        }
        $type = 'error';
        add_settings_error(
            $setting_field,
            $err_code,
            $message,
            $type
        );
    }

    private function loadTypes(): void
    {
        $types = get_post_types(['_builtin' => false, 'post_type__not_in' => ['acf-field', 'acf-field-group']],'names');
        foreach ($types as $type) {
            if (!in_array($type,$this->exportTypes)) {
                $this->exportTypes[] = [
                    $type => [
                        'value' => $type,
                        'enabled' => false
                    ]
                ];
            }
        }

    }

    public static function getActiveSiteEnvironment(): string
    {
        return get_option('wp_site_env', WP_SITE_ENV);
    }

    public static function getGlobalOption($option_name, $default = false) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM wp_options WHERE option_name = %s LIMIT 1", $option_name));
        if (is_object($row)) {
            return $row->option_value;
        }
        return $default;
    }

    public function registerAndBuildFields(): void
    {
        $this->loadTypes();
        /**
         * First, we add_settings_section. This is necessary since all future settings must belong to one.
         * Second, add_settings_field
         * Third, register_setting
         */
        add_settings_section(
        // ID used to identify this section and with which to register options
            'post_jsoner_general_section',
            // Title to be displayed on the administration page
            '',
            // Callback used to render the description of the section
            array( $this, 'post_jsoner_display_general_account' ),
            // Page on which to add this section of options
            'post_jsoner_general_settings'
        );

        $fields = $this->settings_Fields->getFields($this->exportTypes);
        $this->settings_Fields->resgiterSettings($fields);
    }

    public function post_jsoner_display_general_account() {
        echo '<p>These settings apply to all Post JSONer functionality.<br><small><i>PHP constants have precedence over options</i></small></p>';
    }

    public function getCountSites(): int
    {
        $sites = (array)$this->getSites();
        return count($sites) ?? 0;
    }

    public function getSites()
    {
        if (function_exists('get_sites')) {
            return \get_sites([
                'public' => 1,
                'path__not_in' => ['/','uk'],
                'orderby' => 'path',
            ]);
        }
        return 1;
    }
}