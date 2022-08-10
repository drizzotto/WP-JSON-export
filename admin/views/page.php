<?php
!defined('ABSPATH') && exit;

//Get the active tab from the $_GET param
$default_tab = null;
$tab = isset($_GET['tab']) ? $_GET['tab'] : $default_tab;
?>
<div class="wrap">
    <h1>Posts JSONer - Administration page</h1>
    <main class="jsoner">
        <nav class="nav-tab-wrapper">
            <a href="?page=post-jsoner" class="nav-tab <?php echo empty($tab) ? 'nav-tab-active' : ''; ?>">Settings</a>
            <a href="?page=post-jsoner&tab=export"
               class="nav-tab <?php echo ($tab == 'export') ? 'nav-tab-active' : ''; ?>">Export</a>
        </nav>
        <div class="wait">
            <div class="mask"></div>
            <div class="progressbar-container">
                <div id="progressbar"></div>
            </div>
        </div>

        <div class="tab-content">
            <?php
            if ($tab == 'export'):
                include_once plugin_dir_path(dirname(__FILE__)) . 'views/partials/post-jsoner-admin-export.php';
            else:
                include_once plugin_dir_path(dirname(__FILE__)) . 'views/partials/post-jsoner-admin-settings.php';
            endif;
            ?>
        </div>

    </main>
</div>