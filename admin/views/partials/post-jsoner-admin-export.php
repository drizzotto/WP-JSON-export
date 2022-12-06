<?php
!defined('ABSPATH') && exit;

$isDefaultSite = (get_current_blog_id() === 1);

$sites = $this->getSites();

if (false === $isDefaultSite) {
    foreach ($sites as $site) {
        if ($site->blog_id == get_current_blog_id()) {
            $sites = [];
            $sites[] = $site;
            break;
        }
    }
}
?>
<?php if (is_array($sites)): ?>
    <section id="export-site">
        <p>
            <em>
                Export a single blog.
            </em>
        </p>
        <form id="jsoner-site-export-form">
            <label for="site">Select Site:</label>
            <?php if ($isDefaultSite): ?>
            <input type="text" id="site" list="sites">
            <input type="hidden" id="site-id" list="sites">
                <datalist id="sites">
                    <?php foreach ($sites as $site): ?>
                        <option label="<?php echo trim($site->path, '/'); ?>"
                                value="<?php echo trim($site->path, '/'); ?>"
                                data-id="<?php echo $site->blog_id; ?>"></option>
                    <?php endforeach;
 ?>
                </datalist>
<?php else: ?>
                <input type="hidden" id="site-id" value="<?php echo $site->blog_id; ?>">
                <input id="site" type="text" value="<?php echo trim($site->path, '/'); ?>"
                       data-id="<?php echo $site->blog_id; ?>" disabled/>
            <?php endif;
 ?>
            <button type="submit" class="btn">Export</button>
        </form>
    </section>
<?php endif;
 ?>

<?php if ($isDefaultSite): ?>
    <section id="export-bulk">
        <p>
            <em>
                Full export. This option will export all publish / active active blogs.
            </em>
        </p>
        <form id="jsoner-bulk-export-form">
            <input type="hidden" value="0" id="offset" name="offset">
            <button type="submit" class="btn">Full Export</button>
        </form>
    </section>
<?php endif;
 ?>
<div class="clearfix"></div>

<p style="background-color: rgba(0,0,0,0.1); padding: 5px; font-style: italic;">
    Export data for <strong><?php echo Post_Jsoner_Admin::getActiveSiteEnvironment() ?></strong> Environment
</p>

<div class="wait mask">
    <div class="progress-gauge"></div>
</div>

<div class="toast-container"></div>
