<?php
/*
 * Load WooCommerce TON Payment gateway block functions
 * 
 * @package WALLETOR_TON
 */

namespace WALLETOR_TON\Inc;

use WALLETOR_TON\Inc\Traits\Singleton;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Ton_Block extends AbstractPaymentMethodType{
    
    use Singleton;
    
    private $gateway;
	
    protected $name = 'wc_ton'; // payment gateway id
    
    /*
     * Function for the class initialization
     */
    public function initialize() {
        
        // get payment gateway settings
        $this->settings = get_option( "woocommerce_{$this->name}_settings", [] );
        $this->gateway  = WC_Ton::get_instance();
    }
    
    /*
     * Function to check if gateway is active
     */
    public function is_active() {
        
        return $this->gateway->is_available();
    }
    
    /*
     * Function to load the JS file
     */
    public function get_payment_method_script_handles() {

        wp_register_script(
            'wc-ton-blocks-integration',
            WALLETOR_TON_ASSETS_DIR_URL.'javascript/ton_block.js',
                [
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                ],
            null, // or time() or filemtime( ... ) to skip caching
            true
        );

        return [ 'wc-ton-blocks-integration' ];
    }
    
    /*
     * Function to show the payment method data on block
     */
    public function get_payment_method_data() {
        return [
                'title'         => $this->get_setting( 'title' ),
                'description'   => $this->get_setting( 'description' ),    
                'supports'      => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
                'icon'          => $this->get_setting( 'icon' )
            ];
    }
}