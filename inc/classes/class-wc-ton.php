<?php

/*
 * Load WooCommerce TON Payment gateway functions
 *
 * @package WALLETOR_TON
 */

namespace WALLETOR_TON\Inc;

use WALLETOR_TON\Inc\Traits\Singleton;
use WC_Payment_Gateway;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

class WC_Ton extends WC_Payment_Gateway {

    use Singleton;

    //Construct function
    protected function __construct() {

        $this->id = 'wc_ton';
        $this->icon = WALLETOR_TON_ASSETS_DIR_URL . 'images/tonkeeper_pay.jpg'; // URL of the icon that will be displayed on checkout
        $this->has_fields = false; // If you need custom fields on checkout
        $this->method_title = 'Pay with TON';
        $this->method_description = 'Pay using TON payment gateway.';

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user settings
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        //load class hooks
        $this->setup_hooks();
    }

    /*
     * Function to load action and filter hooks
     */

    protected function setup_hooks() {

        //actions and filters
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_blocks_loaded', [$this, 'gateway_block_support']);
        add_action('woocommerce_product_options_pricing', [$this, 'woo_product_ton_pricing']);
        add_action('woocommerce_admin_process_product_object', [$this, 'woo_admin_process_product'], 10, 1);
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', [$this, 'woo_order_query'], 10, 2);
    }

    /*
     * Function to show the price in TON field on product edit page
     */

    public function woo_product_ton_pricing() {

        global $post;
        $product = wc_get_product($post->ID);
        woocommerce_wp_text_input(array(
            'id' => 'ton_price',
            'class' => 'wc_input_price short',
            'label' => __('Price in TON', 'woocommerce'),
            'value' => $this->get_price_in_ton($product)
        ));
    }

    /*
     * Function to save the price in TON
     *
     * @param $product WooCommerce product object
     */

    public function woo_admin_process_product($product) {

        $ton_rpice = filter_input(INPUT_POST, 'ton_price');

        if (isset($ton_rpice)) {
            $product->update_meta_data('ton_price', sanitize_text_field($ton_rpice));
        }
    }

    /*
     * Function to get the product price in TON
     *
     * @param $product WooCommerce product object
     */

    public function get_price_in_ton($product) {

        $ton_price = 0;

        if (empty($product)) {
            return $ton_price;
        }

        $price = $product->get_price();

        if (empty($price)) {
            return $ton_price;
        }

        if (empty($ton_price)) {
            $rate = get_option('_ton_currency_rate');
            if (isset($rate) && !empty($rate) && $rate > 0) {
                $ton_unit_price = 1 / $rate;
                $ton_price = round($ton_unit_price * $price, 4);
            }
        }

        return $ton_price;
    }

    /*
     * Filter hook function to create an invoice
     *
     * @param $order WooCommerce order object
     *
     * @return $result Array of result
     */

    public function create_ton_invoice($order) {

        $items = $order->get_items();
        $total_ton_price = 0;
        $empty_ton_prices = [];
        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            $ton_price = $this->get_price_in_ton($product); //get_post_meta($values['data']->get_id() , 'ton_price', true);
            if (!empty($ton_price)) {
                $total_ton_price = $total_ton_price + ($ton_price * $item->get_quantity());
            } else {
                $empty_ton_prices[] = $item_id . ' ~ ' . $product->get_title();
            }
        }

        $error_message = "";

        if ($empty_ton_prices) {
            $rate = get_option('_ton_currency_rate');
            $error_message = 'Some products do not have a TON price.... Total Price = ' . $total_ton_price . ' .... Products those have not prices = ' . implode("| ", $empty_ton_prices) . ' .... Rate = ' . $rate;
        } else {
            $total_ton_price = $total_ton_price * 1000000000; //TON API accepts minimum 0.001 amount
            $invoice = $this->make_api_call(
                    'https://tonconsole.com/api/v1/services/invoices/invoice',
                    'POST',
                    ['amount' => (string) $total_ton_price, 'life_time' => 1800, 'description' => '']
            );

            if (isset($invoice->error)) {
                $error_message = $invoice->error;
            }
        }

        if (!empty($error_message)) {
            $result = ['status' => false, 'message' => $error_message];
        } else {
            $result = ['status' => true, 'invoice' => $invoice];
        }

        return $result;
    }

    /*
     * Function to make API calls to tonconsole.com
     *
     * @param $url String URL of the API end point
     * @param $method String API call method, either GET or POST
     * @param $data Array of data that we may need to send to API
     * @param $transient WooCommerce cart hash, so that we can get data from WP transient
     *
     * @return $result API call result, json decoded object
     */

    public function make_api_call($url, $method = 'GET', $data = [], $transient = '', $send_token = true) {

        if (!empty($transient)) {
            if (!empty($ton_cart_data = get_transient("__TON_" . $transient))) {
                return $ton_cart_data;
            }
        }

        $headers = ['Content-Type: application/json'];

        if ($send_token) {
            $api_token = $this->get_option('api_token');
            if (empty($api_token)) {
                return false;
            }
            $headers[] = 'Authorization: Bearer ' . $api_token;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $result = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($result);

        if (!empty($transient) && !isset($result->error)) {
            $expiration = 1800;
            if (isset($data['life_time'])) {
                $expiration = $data['life_time'];
            }
            set_transient("__TON_" . $transient, $result, $expiration);
        }

        return $result;
    }

    /*
     * Filter function to add meta query in wc_get_orders
     */

    public function woo_order_query($query, $query_vars) {

        if (!empty($query_vars['_ton_invoice'])) {
            $query['meta_query'][] = array(
                'key' => '_ton_invoice',
                'value' => esc_attr($query_vars['_ton_invoice']),
                'compare' => '!='
            );
        }

        return $query;
    }

    /*
     * Function to update all orders statuses
     */

    public function update_orders_status() {

        $args = array(
            'limit' => 3,
            'status' => 'on-hold',
            '_ton_invoice' => ''
        );

        $orders = wc_get_orders($args);

        // NOT empty
        if (!empty($orders)) {
            foreach ($orders as $order) {
                $invoice = get_post_meta($order->get_id(), '_ton_invoice', true);
                $invoice_data = $this->make_api_call('https://tonconsole.com/api/v1/services/invoices/' . $invoice, 'GET');
                if (isset($invoice_data->status)) {
                    if ($invoice_data->status == 'paid') {
                        $order->update_status('completed', __('Paid', 'woocommerce'));
                    } else if ($invoice_data->status != 'pending') {
                        delete_post_meta($order->get_id(), '_gate_invoice');
                    }
                }
            }
        }
    }

    /*
     * Function to save the Currency Rates
     */

    public function save_currency_rates() {

        $currency = get_woocommerce_currency();

        $rates = $this->make_api_call('https://tonapi.io/v2/rates?tokens=ton&currencies=' . $currency,
                'GET',
                [],
                "",
                false
        );

        if (isset($rates->rates)) {
            update_option('_ton_currency_rate', $rates->rates->TON->prices->$currency);
        }
    }

    /*
     * Function to add gateway block support
     */

    public function gateway_block_support() {

        // registering the class we have just included
        add_action('woocommerce_blocks_payment_method_type_registration', [$this, 'register_woo_payment_blocks'], 10, 1);
    }

    /*
     * Function to register the payment block into Woo Registry
     */

    public function register_woo_payment_blocks(PaymentMethodRegistry $payment_method_registry) {

        $payment_method_registry->register(WC_Ton_Block::get_instance());
    }

    /*
     * Function to Initialize form fields
     */

    public function init_form_fields() {

        $rate = get_option('_ton_currency_rate');
        $currency = get_woocommerce_currency();

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable TON Payment Gateway', 'woocommerce'),
                'default' => 'no'
            ],
            'api_token' => [
                'title' => __('API Token', 'woocommerce'),
                'type' => 'text',
                'description' => __('TON API token that you can get from Tonconsole', 'woocommerce'),
                'default' => '',
                'desc_tip' => true,
            ],
            'title' => [
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay with TON', 'woocommerce'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce') . '<br><br>1 TON = ' . round($rate, 4) . ' ' . $currency . '<br><br><b>' . __('IPN callback webhook URL: ', 'woocommerce') . '</b>' . WALLETOR_TON_SITE_BASE_URL . 'wc-api/' . $this->id . '/',
                'default' => __('Pay using TON payment gateway', 'woocommerce'),
            ],
        ];
    }

    /*
     * Function to Process the payment
     *
     * @param $order_id WooCommerce order id
     */

    public function process_payment($order_id) {

        $order = wc_get_order($order_id);
        $invoice = $this->create_ton_invoice($order);

        if (!$invoice['status']) {
            // Return error
            return array(
                'result' => 'failure',
                'messages' => $invoice['message'],
            );
        }

        $redirect = $invoice['invoice']->payment_link;
        update_post_meta($order_id, '_ton_invoice', $invoice['invoice']->id);

        // Mark as on-hold (we're awaiting the payment)
        $order->update_status('on-hold', __('Awaiting TON payment', 'woocommerce'));

        // Reduce stock levels
        //wc_reduce_stock_levels($order_id);
        //$order->payment_complete();
        // Remove cart
        WC()->cart->empty_cart();

        // Return thank you page redirect
        return array(
            'result' => 'success',
            'redirect' => !empty($redirect) ? $redirect : $this->get_return_url($order),
        );
    }
}
