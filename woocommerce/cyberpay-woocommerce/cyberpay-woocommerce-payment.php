<?php
/*
	Plugin Name: Cyberpay WooCommerce Payment 
	Plugin URI:  https://cyberpay.net.ng
	Description: Cyberpay WooCommerce Payment Plugin allows you to accept payment on your WooCommerce store via Visa Cards, Mastercards, Verve Cards and eTranzact.
	Version: 1.6.5
	Author: May Davison Tech
	Author URI: http://www.mdt.com.ng
	License: GPL-2.0+
 	License URI: http://www.gnu.org/licenses/gpl-2.0.txt

*/
if (!defined('ABSPATH'))
    exit;

add_action('plugins_loaded', 'woocommerce_cyberpay_init', 0);

function woocommerce_cyberpay_init()
{

    if (!class_exists('WC_Payment_Gateway')) return;

    /**
     * Gateway class
     */
    class WC_Cyberpay_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            global $woocommerce;

            //define and set generic variables
            $this->id = 'cyberpay_gateway';
            $this->icon = apply_filters('woocommerce_cyberpay_icon', plugins_url('assets/cyberpay.jpeg', __FILE__));
            $this->has_fields = false;
            $this->baseurl = 'https://payment-api.cyberpay.ng/api/v1/';
            $this->notify_url = WC()->api_request_url('WC_Cyberpay_Gateway');
            $this->method_title = 'Cyberpay Payment Plugin';
            $this->method_description = 'Cyberpay Payment Plugin allows you to receive Mastercard and Visa Card Payments On your WooCommerce Powered Site.';


            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();


            // Define user set variables
            $this->Key = $this->get_option('Key');

            //Actions
            add_action('woocommerce_receipt_cyberpay_gateway', array($this, 'receipt_page'));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_cyberpay_gateway', array($this, 'check_cyberpay_response'));

            // Check if the gateway can be used
            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        public function is_valid_for_use()
        {

            if (!in_array(get_woocommerce_currency(), array('NGN'))) {
                $this->msg = 'Cyberpay doesn\'t support your store currency, set it to Nigerian Naira &#8358; <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc_settings&tab=general">here</a>';
                return false;
            }

            return true;
        }

        /**
         * Admin Panel Options
         **/
        public function admin_options()
        {
            echo '<h3>Cyberpay Payment Plugin</h3>';
            echo '<p>Cyberpay Payment Plugin allows you to accept payment through various channels such as Mastercard and Visa cards.</p>';


            if ($this->is_valid_for_use()) {

                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            } else { ?>
                <div class="inline error"><p><strong>Cyberpay Payment Plugin
                            Disabled</strong>: <?php echo $this->msg ?></p></div>

            <?php }
        }


        /**
         * Initialise Gateway Settings Form Fields
         **/
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'type' => 'checkbox',
                    'label' => 'Enable Cyberpay Payment Plugin',
                    'description' => 'Enable or disable the gateway.',
                    'desc_tip' => true,
                    'default' => 'yes'
                ),
                'Key' => array(
                    'title' => 'Intergation Key',
                    'type' => 'text',
                    'label' => 'Enter Cyberpay Integration Key',
                    'description' => 'Register on the platform(https://merchant.cyberpay.ng) to get Intergration Key',
                    'desc_tip' => true,
                    'default' => ''
                ),
            );
        }

        /**
         * Get Cyberpay Args for passing to Cyberpay
         **/
        function get_cyberpay_args($order)
        {
            global $woocommerce;

            $order_data = $order->get_data();
            $order_id = $order->get_id();


            $ceamt = $order->get_total();
            $cekey = $this->Key;
            $cememo = "Payment from " . get_bloginfo('name');
            $cenurl = home_url('/').'wc-api/WC_Cyberpay_Gateway';


            session_start();
            $_SESSION['order_id'] = $order_id;

            //Register Transaction
            $split = array('WalletId' => "teargstd",
                'Amount' => intval($ceamt * 100),
                'ShouldDeductFrom' => True);
                
            $fields = array('Currency' => "NGN",
                'MerchantRef' => rand(00000,99989) .'_'. $order_id,
                'Amount' => intval($ceamt * 100),
                'Description' => $cememo,
                'CustomerName' => $order_data['billing']['first_name'],
                'CustomerEmail' => $order_data['billing']['email'],
                'CustomerMobile' => $order_data['billing']['phone'],
                'IntegrationKey' => $cekey,
                'ReturnUrl' => $cenurl,
                'SharedSplits' => array($split));

            $payload = json_encode($fields);

            $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://payment-api.cyberpay.ng/api/v1/payments",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => $payload,
              CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
              ),
            ));
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
                return "cURL Error #:" . $err;
            } else {
                $responseObject = json_decode($response);
              
                if ($responseObject->code == "PY00") {
                    $redirect_url = $responseObject->data->redirectUrl;
                    $error = false;
                    $error_detail = "";
                } else {
                    $error = true;
                    $error_detail = 'Register transaction error: ' . $response;
                };

                $cyberpay_args = array(
                    'cp_error' => $error,
                    'cp_errorDetail' => $error_detail,
                    'cp_redirectUrl' => $redirect_url,
                );
    
                $cyberpay_args = apply_filters('woocommerce_cyberpay_args', $cyberpay_args);
    
                return $cyberpay_args;
            }

        }

        /**
         * Generate the Cyberpay Payment button link
         **/
        function generate_cyberpay_form($order_id)
        {
            
                global $woocommerce;

                $order = wc_get_order($order_id);
    
                $cyberpay_args = $this->get_cyberpay_args($order);
    
                $cyberpay_args_object = json_decode(json_encode((object)$cyberpay_args));


            if ($cyperpay_args_object->cp_error) {
                return $cyberpay_args_object->cp_errorDetail;
            } else {
                //return $cyberpay_args_object->cp_redirectUrl;
                return '<a class="button-alt" href="' . esc_url($cyberpay_args_object->cp_redirectUrl) . '"> '.__('Proceed to Payment', 'woocommerce'). '</a>
					<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woocommerce') . '</a>';
            }

        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        }

        /**
         * Output for the order received page.
         **/
        function receipt_page($order_id)
        {
            echo '<p></br>' . __('Thank you for your order, please click the button below to make payment.', 'woocommerce') . '</p>';
            echo $this->generate_cyberpay_form($order_id);
        }


        /**
         * Verify Transaction
         **/

        function check_cyberpay_response(){

            function getStatus($reference)
            {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                CURLOPT_URL => "https://payment-api.cyberpay.ng/api/v1/payments/". $reference,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_POSTFIELDS => "",
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json"
                  ),
                ));

                $response = curl_exec($curl);
                $err = curl_error($curl);

                curl_close($curl);

                if ($err) {
                    echo "cURL Error #:" . $err;
                    exit;
                } else {
                    $responseObject = json_decode($response);
                    return $responseObject;
                }

            }
                session_start();
                $order_id 		= (int) $_SESSION['order_id'];
                $response       = getStatus($_GET['ref']);
                //echo $response->code;
                //exit;

				// after payment hook
                do_action('cyberpay_after_payment', $_POST, $response );
                $order = wc_get_order($order_id);

				if($response->code == "PY00") {
                    $message = $response->data->message;
                    $message_type = 'success';
                    $order->update_status( 'processing', 'Payment received, your order is currently being processed.' );
                    $order->add_order_note('Payment Via Cyberpay ');
                    $order->reduce_order_stock();
					wc_empty_cart();
				} else {
	            	$message = 	'Payment Failed<br/> Reason: '. $response->data->message .'<br />Transaction Reference: '.$_REQUEST['ref'];
					$message_type = 'error';
                   	$order->add_order_note( $message, 1 );
                  	$order->add_order_note( $message );
                    $order->update_status( 'failed', '' );
				}


                if (function_exists('wc_add_notice')) {
                    wc_add_notice($message, $message_type);
                } else // WC < 2.1
                {
                    $woocommerce->add_error($message);
                    $woocommerce->set_messages();
                }
    
                $redirect_url = get_permalink(wc_get_page_id('myaccount'));
    
                wp_safe_redirect($redirect_url);
                exit;
            
        }

    }


    function _cyberpay_message()
    {
        if (is_order_received_page()) {
            $order_id = absint(get_query_var('order-received'));

            $cyberpay_message = get_post_meta($order_id, '', false);
            $message = $cyberpay_message['message'];
            $message_type = $cyberpay_message['message_type'];

            delete_post_meta($order_id, '__cyberpay_message');

            if (!empty($cyberpay_message)) {
                wc_add_notice($message, $message_type);
            }
        }
    }

    add_action('wp', '_cyberpay_message');


    /**
     * Add cyberpay Gateway to WC
     **/
    function woocommerce_add_cyberpay_gateway($methods)
    {
        $methods[] = 'WC_Cyberpay_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_cyberpay_gateway');


    /**
     * only add the naira currency and symbol if WC versions is less than 2.1
     */
    if (version_compare(WOOCOMMERCE_VERSION, "2.1") <= 0) {

        /**
         * Add NGN as a currency in WC
         **/
        add_filter('woocommerce_currencies', '_add_my_currency');

        if (!function_exists('_add_my_currency')) {
            function _add_my_currency($currencies)
            {
                $currencies['NGN'] = __('Naira', 'woocommerce');
                return $currencies;
            }
        }

        /**
         * Enable the naira currency symbol in WC
         **/
        add_filter('woocommerce_currency_symbol', '_add_my_currency_symbol', 10, 2);

        if (!function_exists('_add_my_currency_symbol')) {
            function _add_my_currency_symbol($currency_symbol, $currency)
            {
                switch ($currency) {
                    case 'NGN':
                        $currency_symbol = '&#8358; ';
                        break;
                }
                return $currency_symbol;
            }
        }
    }


    /**
     * Add Settings link to the plugin entry in the plugins menu for WC below 2.1
     **/
    if (version_compare(WOOCOMMERCE_VERSION, "2.1") <= 0) {

        add_filter('plugin_action_links', '_cyberpay_plugin_action_links', 10, 2);

        function _cyberpay_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Cyberpay_Gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }
    } /**
     * Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
     **/
    else {
        add_filter('plugin_action_links', '_cyberpay_plugin_action_links', 10, 2);

        function _cyberpay_plugin_action_links($links, $file)
        {
            static $this_plugin;

            if (!$this_plugin) {
                $this_plugin = plugin_basename(__FILE__);
            }

            if ($file == $this_plugin) {
                $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_cyberpay_gateway">Settings</a>';
                array_unshift($links, $settings_link);
            }
            return $links;
        }
    }
}