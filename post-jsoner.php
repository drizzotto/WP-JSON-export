<?php
/*
Plugin Name: Post Jsoner
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates ?
Description: Export content into JSON files based on post types, blog and languages.
Version: 1.1
Author: Leo Daidone, leo.daidone@gmail.com
License: MIT
*/

/* Make sure plugin remains secure if called directly */
if (!defined('ABSPATH'))
{
    if (!headers_sent())
    {
        header('HTTP/1.1 403 Forbidden');
    }

    die('ERROR: This plugin requires WordPress and will not function if called directly.');
}

if (!is_admin())
{
    return false;
}

define('PLUGIN_DIR', dirname(__FILE__));
foreach (glob(dirname(__FILE__) . '/autoload/*.php') as $file)
{
    if (!in_array($file, ['.', '..']))
    {
        require_once $file;
    }
}

if (!defined('DEBUG_FILE'))
{
    define('DEBUG_FILE', '/tmp/wp-error.log');
}

use Posts_Jsoner\admin\Administrator;
use Posts_Jsoner\autoload\Autoloader;

$loader = new Autoloader(PLUGIN_DIR);

$admin = new Administrator();
$admin->run();
