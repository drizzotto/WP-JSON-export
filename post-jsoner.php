<?php

/*
Plugin Name: Post Jsoner
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates ?
Description: Export content into JSON files based on post types, blog and languages.
Version: 1.0
Author: Leo Daidone, leo.daidone@gmail.com
License: MIT
*/

/* Make sure plugin remains secure if called directly */
if (!defined('ABSPATH')) {
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
    }
    die('ERROR: This plugin requires WordPress and will not function if called directly.');
}

if (!is_admin()) {
    return false;
}

define('PLUGIN_DIR', dirname(__FILE__));
foreach (glob(dirname(__FILE__) . '/autoload/*.php') as $file) {
    if (!in_array($file, ['.', '..'])) {
        require_once $file;
    }
}

use \Posts_Jsoner\autoload\Autoloader;

$loader = new Autoloader(PLUGIN_DIR);

use \Posts_Jsoner\Data\Jsoner;
use \Posts_Jsoner\admin\Administrator;

$admin = new Administrator();
$admin->run();
// Add filters and actions
add_action('save_post', 'save_post_callback', 10, 2);
function save_post_callback($post_id, $post)
{
    global $sitepress;
    $country = 'default';
    $lang = 'en';
    if (!empty($sitepress)) { // is WPML enabled
        $country = trim(get_blog_details(get_current_blog_id())->path, '/');
        $lang = $sitepress->get_element_language_details($post_id)->language_code;
    }

    if (!empty($post_id)) {
        $jsoner = new Jsoner($country, $lang, $post_id);
        $jsoner->updateNode($post);
    }
}