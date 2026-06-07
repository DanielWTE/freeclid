<?php
/**
 * Plugin Name: FreeCLID
 * Description: Free, local-first offline conversion tracking for WordPress forms and Google Ads CSV imports.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: WGST
 * Author URI: https://wgst.at
 * License: GPLv2 or later
 * Text Domain: freeclid
 * Update URI: false
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FREECLID_VERSION', '1.0.0');
define('FREECLID_FILE', __FILE__);
define('FREECLID_PATH', plugin_dir_path(__FILE__));
define('FREECLID_URL', plugin_dir_url(__FILE__));

require_once FREECLID_PATH . 'includes/helpers.php';
require_once FREECLID_PATH . 'includes/class-freeclid-db.php';
require_once FREECLID_PATH . 'includes/class-freeclid-capture.php';
require_once FREECLID_PATH . 'includes/class-freeclid-feed.php';
require_once FREECLID_PATH . 'includes/class-freeclid-settings.php';

function freeclid_activate(): void
{
    Freeclid_Settings::add_default_options();
    Freeclid_DB::create_table();
    Freeclid_Feed::add_rewrite_rule();
    flush_rewrite_rules();
}

function freeclid_deactivate(): void
{
    flush_rewrite_rules();
}

function freeclid_uninstall(): void
{
    Freeclid_DB::uninstall();
}

function freeclid_bootstrap(): void
{
    Freeclid_Capture::init();
    Freeclid_Feed::init();

    if (is_admin()) {
        Freeclid_Settings::init();
    }
}

register_activation_hook(__FILE__, 'freeclid_activate');
register_deactivation_hook(__FILE__, 'freeclid_deactivate');
register_uninstall_hook(__FILE__, 'freeclid_uninstall');

add_action('plugins_loaded', 'freeclid_bootstrap');
