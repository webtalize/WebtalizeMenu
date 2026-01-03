<?php
/**
 * Plugin Name: Webtalize Menu
 * Plugin URI:  na
 * Description: Displays Webtalize Menu items organized by category.
 * Version:     1.0.3
 * Author:      Webtalize
 * Author URI:  https://webtalize.com/
 * License:     n/a
 * License URI: 
 * Text Domain: webtalize-menu
 * Domain Path: /languages
 * built initially wiht Gemini AI
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define( 'WTM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WTM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once WTM_PLUGIN_DIR . 'post-types.php';
require_once WTM_PLUGIN_DIR . 'frontend.php';
require_once WTM_PLUGIN_DIR . 'admin.php';
