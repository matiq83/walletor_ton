<?php

/*
  Plugin Name: TON Integration
  Description: TON Integration
  Version: 1.0.5
  Author: Muhammad Atiq
 */

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo "Hi there!  I'm just a plugin, not much I can do when called directly.";
    exit;
}

//ini_set('display_errors', 1);ini_set('display_startup_errors', 1);error_reporting(E_ALL);
//Global define variables
define('WALLETOR_TON_PLUGIN_NAME', 'TON');
define('WALLETOR_TON_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WALLETOR_TON_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WALLETOR_TON_SLUG', plugin_basename(__DIR__));
define('WALLETOR_TON_SITE_BASE_URL', rtrim(get_bloginfo('url'), "/") . "/");
define('WALLETOR_TON_LANG_DIR', WALLETOR_TON_PLUGIN_PATH . 'language/');
define('WALLETOR_TON_VIEWS_DIR', WALLETOR_TON_PLUGIN_PATH . 'views/');
define('WALLETOR_TON_ASSETS_DIR_URL', WALLETOR_TON_PLUGIN_URL . 'assets/');
define('WALLETOR_TON_ASSETS_DIR_PATH', WALLETOR_TON_PLUGIN_PATH . 'assets/');
define('WALLETOR_TON_SETTINGS_KEY', '_walletor_ton_options');
define('WALLETOR_TON_TEXT_DOMAIN', 'walletor_ton');
define('WALLETOR_TON_UPDATE_URL', 'http://portfolio.itfledge.com/wp0822/wp-content/plugins/');

//Plugin update checker
require WALLETOR_TON_PLUGIN_PATH . 'update/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
                WALLETOR_TON_UPDATE_URL . WALLETOR_TON_SLUG . '.json',
                __FILE__,
                WALLETOR_TON_SLUG
);

//Load the classes
require_once WALLETOR_TON_PLUGIN_PATH . '/inc/helpers/autoloader.php';

//Get main class instance
$main = WALLETOR_TON\Inc\Main::get_instance();

//Plugin activation hook
register_activation_hook(__FILE__, [$main, 'walletor_ton_install']);

//Plugin deactivation hook
register_deactivation_hook(__FILE__, [$main, 'walletor_ton_uninstall']);
