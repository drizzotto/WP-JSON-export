<?php
!defined('ABSPATH') && exit;
$blog_id = get_current_blog_id();
$isDefaultSite = ($blog_id === 1);

$sites = Post_Jsoner_Admin::getSites();
$categories = $this->getCategories();

$authors = get_users([ 'role__in' => [ 'editor', 'author' ]]);
$statuses = get_post_stati();
$defaultStatuses = [
    'publish',
    'private',
    'draft',
    'trash',
    'auto-draft',
    'future',
    'pending',
];
foreach ($statuses as $key => $status) {
    if (!in_array($status, $defaultStatuses)) {
        unset($statuses[$key]);
    }
}

if (false === $isDefaultSite) {
    foreach ($sites as $site) {
        if ($site->blog_id == $blog_id) {
            $sites = [];
            $sites[] = $site;
            break;
        }
    }
}


?>
<?php if (is_array($sites)): //multisite ?>
    <section id="export-site">
        <p>
            <em>
                Export a single blog.
            </em>
        </p>
        <form id="jsoner-site-export-form">
            <table>
                <tr>
                    <td><label for="site">Select Site:</label></td>
                    <td>
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

                        <?php else: //single site  ?>
                            <input type="hidden" id="site-id" value="<?php echo $site->blog_id; ?>">
                            <input id="site" type="text" value="<?php echo trim($site->path, '/'); ?>"
                                   data-id="<?php echo $site->blog_id; ?>" disabled/>
                        <?php endif;
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><label for="authors">Select Author:</label></td>
                    <td>
                        <input type="text" id="author" list="authors" disabled>
                        <input type="hidden" id="author-id" list="authors">
                        <datalist id="authors">
                            <?php foreach ($authors as $author): ?>
                                <option label="<?php echo $author->display_name; ?>"
                                        value="<?php echo $author->display_name; ?>"
                                        data-id="<?php echo $author->ID; ?>"></option>
                            <?php endforeach;
                            ?>
                        </datalist>
                    </td>
                </tr>
                <tr>
                    <td><label for="status">Select Post status:</label></td>
                    <td>
                        <input type="text" id="status" list="statuses" disabled>
                        <input type="hidden" id="status-id" list="statuses">
                        <datalist id="statuses">
                            <?php foreach ($statuses as $status): ?>
                                <option label="<?php echo $status; ?>"
                                        value="<?php echo $status; ?>"
                                        data-id="<?php echo $status; ?>"></option>
                            <?php endforeach;
                            ?>
                        </datalist>
                    </td>
                </tr>
                <tr>
                    <td><label for="site">Select Category:</label></td>
                    <td>
                        <input type="text" id="category" list="categories" disabled>
                        <input type="hidden" id="category-id" list="categories">
                        <datalist id="categories">
                            <?php foreach ($categories as $category): ?>
                                <option label="<?php echo $category->name; ?>"
                                        value="<?php echo $category->name; ?>"
                                        data-id="<?php echo $category->cat_ID; ?>"></option>
                            <?php endforeach;
                            ?>
                        </datalist>
                    </td>
                </tr>
                <tr>
                    <td><label for="category">Select data-range:</label></td>
                    <td><input type="text" name="datefilter" id="datefilter" value="" disabled /></td>
                </tr>
            </table>
            <button type="submit" class="btn" id="btn-export-site" disabled>Export</button>
        </form>
    </section>
<?php endif;
?>

<?php if ($isDefaultSite): // bulk export ?>
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
