<?php

/*
 * Bootstraps the plugin, this class will load all other classes
 *
 * @package WALLETOR_TON
 */

namespace WALLETOR_TON\Inc;

use WALLETOR_TON\Inc\Traits\Singleton;

class Main {

    use Singleton;

    //Construct function
    protected function __construct() {

        //load class
        $this->setup_hooks();

        //Load cron
        Cron::get_instance();

        //Load WooCommerce Payment Gateway Classes
        add_action('plugins_loaded', [$this, 'init_woo_gateway_classes']);
        add_filter('woocommerce_payment_gateways', [$this, 'add_woo_gateways'], 100, 1);
    }

    /*
     * Function to load action and filter hooks
     */

    protected function setup_hooks() {

        //actions and filters
        add_action('init', [$this, 'load_textdomain']);
    }

    /*
     * Function to load WooCommerce Payment Gateway Classes
     */

    public function init_woo_gateway_classes() {

        //Load the TON class
        WC_Ton::get_instance();
    }

    /*
     * Function to tell the WooCommerce that TON gateway class exists
     */

    public function add_woo_gateways($gateways) {

        $gateways[] = WC_Ton::get_instance(); //Add class

        return $gateways;
    }

    /**
     * Load plugin textdomain, i.e language directory
     */
    public function load_textdomain() {

        load_plugin_textdomain(WALLETOR_TON_TEXT_DOMAIN, false, WALLETOR_TON_LANG_DIR);
    }

    /*
     * Function that executes once the plugin is activated
     */

    public function walletor_ton_install() {

        //Run code once when plugin activated

        if (!wp_next_scheduled(WALLETOR_TON_TEXT_DOMAIN . '_cron_event')) {

            wp_schedule_event(time(), WALLETOR_TON_TEXT_DOMAIN . '_cron_interval', WALLETOR_TON_TEXT_DOMAIN . '_cron_event');
        }
    }

    /*
     * Function that executes once the plugin is deactivated
     */

    public function walletor_ton_uninstall() {

        //Run code once when plugin deactivated
        wp_clear_scheduled_hook(WALLETOR_TON_TEXT_DOMAIN . '_cron_event');
    }
}
