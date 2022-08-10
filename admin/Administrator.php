<?php

namespace Posts_Jsoner\admin;

use Posts_Jsoner\Data\BulkExport;

class Administrator
{
    const TITLE = "Posts JSONer Page";
    const SUB_TITLE = "Posts JSONer";

    private string $plugin_name = "post-jsoner";
    private string $version = "1.0.0";
    private object $loader;

    private object $plugin_admin;


    public function __construct()
    {
        $this->load_dependencies();
        $this->set_constants();
        $this->define_admin_hooks();
        $this->registerEndpoints();
    }

    private function load_dependencies()
    {
        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/class-post-jsoner-loader.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/class-post-jsoner-constants.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/class-post-jsoner-admin.php';

        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/class-post-jsoner-settings-fields.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/includes/class-post-jsoner-s3-config.php';

        \Post_Jsoner_Constants::setConstants();

        $this->loader = new \Post_Jsoner_Loader();
        $this->plugin_admin = new \Post_Jsoner_Admin( $this->get_plugin_name(), $this->get_version() );
    }

    private function set_constants()
    {
        $plugin_constants = new \Post_Jsoner_Constants( $this->get_plugin_name(), $this->get_version() );
        $plugin_constants->setConstants();
    }

    private function define_admin_hooks()
    {
        $this->loader->add_action( 'admin_enqueue_scripts', $this->plugin_admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $this->plugin_admin, 'enqueue_scripts' );
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    // LEGACY

    public function registerEndpoints(): void
    {
        // Register endpoints to handle form submissions
        add_action('wp_ajax_jsoner_bulk', [$this, 'jsonerBulkExport']);
        add_action('wp_ajax_jsoner_site', [$this, 'jsonerSiteExport']);
    }

    public function jsonerBulkExport()
    {
        $offset = $_POST['offset'] ?? 0;
        $step = $_POST['step'] ?? 5;

        $next = (($offset/$step) > floor($this->plugin_admin->getCountSites()/$step))
            ? -1
            : ($offset + $step);

        $response = [
            'errors' => null,
            'success' => false,
            'step' => $step,
            'next' => $next,
            'processed' => 0,
            'is_multisite' => is_multisite(),
        ];

        if (!is_admin()) {
            $this->responseError($response, "Admin user is required");
        }

        $errors = [];
        if (is_multisite()) {
            $args = [
                'public' => 1,
                'path__not_in' => ['uk'],
                'orderby' => 'path',
                'number' => $step,
                'offset' => $offset,
            ];
            $sites = get_sites($args);
            $count = 0;
            foreach ($sites as $item) {
                $path = trim($item->path, '/');
                if (empty($path)) {
                    $path = 'default';
                }
                if (($item->blog_id !==0) && ($item->public==0  || $path=='uk')) { // skip not public sites
                    continue;
                }
                if (!BulkExport::exportSite($path, $item->blog_id)) {
                    error_log("Site {$path} was not exporter\n",3, '/tmp/wp-errors.log');
                    $errors[] = $path;
                }
                $count++;
            }
            $response['processed'] = $count;
        } else {
            if (!BulkExport::exportSite('default', 0)) {
                error_log("Site default was not exported\n",3, '/tmp/wp-errors.log');
                $errors[] = 'default';
            }
            $response['processed'] = 1;
        }

        if (!empty($errors)) {
            $errSites = join(' - ',$errors);
            $this->responseError($response, "There was an error exporting the following sites: {$errSites}");
        }

        $response['success'] = true;

        $this->responseSuccess($response);
    }

    public function jsonerSiteExport()
    {
        $response = [
            'errors' => null,
            'success' => false,
        ];

        if (!is_admin()) {
            $this->responseError($response, "Admin user is required");
        }
        if (empty($_POST['site']) || empty($_POST['site_id'])) {
            $this->responseError($response, "You must select a site to be exported");
        }
        $path = $_POST['site'];
        $blogId = $_POST['site_id'];
        if (!BulkExport::exportSite($path, $blogId)) {
            $this->responseError($response, "There was an error exporting {$path}");
        }

        $response['success'] = true;
        $this->responseSuccess($response);
    }

    private function responseSuccess(array $response): void
    {
        header( "Content-Type: application/json" );
        echo json_encode($response);

        //Don't forget to always exit in the ajax function.
        exit();
    }

    private function responseError(array $response, string $error): void
    {
        $response['errors'] = $error;
        header( "Content-Type: application/json" );
        echo json_encode($response);

        //Don't forget to always exit in the ajax function.
        exit();
    }
}