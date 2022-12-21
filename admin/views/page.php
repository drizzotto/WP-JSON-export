<?php
!defined('ABSPATH') && exit;

//Get the active tab from the $_GET param
$default_tab = 'export';

$tab = get_current_blog_id() !== 1 ? $default_tab : $_GET['tab'] ?? $default_tab;
?>
<div class="wrap">
    <h1>Posts JSONer - Administration page</h1>
    <main class="jsoner">
        <nav class="nav-tab-wrapper">
            <?php if (get_current_blog_id() === 1) { ?>
                <a href="?page=post-jsoner&tab=settings" class="nav-tab <?php echo ($tab == 'settings') ? 'nav-tab-active' : ''; ?>">Settings</a>
            <?php }
 ?>
            <a href="?page=post-jsoner&tab=export"
               class="nav-tab <?php echo ($tab == 'export') ? 'nav-tab-active' : ''; ?>">Export</a>
        </nav>
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
