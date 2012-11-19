<?php
/*
Plugin Name: Grapplingligan
Plugin URI: http://angrycreative.se
Description: Main driver for grapplingligan.se providing the custom functions needed.
Author: Mattias Stahre
Version: 0.1
Author URI: http://angrycreative.se
*/
define('SGL_PLUGIN_PATH', dirname(__FILE__));
define('SGL_PLUGIN_PHP', __FILE__);

// Hack to solve a problem with a nonexistant function.
include(ABSPATH . "/wp-admin/includes/file.php");

if(!function_exists('dbgx_trace_var')) {
    function dbgx_trace_var() {}
}
require('helpers/request_handler.php');
require('views/query_helpers.php');
require('views/views.php');


// Load all hooks.
require('helpers/functions.php');
require('helpers/database.php');
require('data/data.php');
require('core/actions.php');
require('core/cron.php');
require('admin/filters.php');
require('admin/actions.php');
require('api/api.php');

