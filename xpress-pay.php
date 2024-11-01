<?php
/**
* Plugin Name: XpressPay Payment Gateway
* Plugin URI: https://github.com/muyiwer/XpressPayPlugin
* Description: XpressPay Woocommerce Plugin allows you to accept payment on your Woocommerce store.
* Author: Xpress Payments Solutions Limited
* Author URI: https://www.xpresspayments.com/
* Version: 1.0.0
* License: GPL-2.0+
* License URI: http://www.gnu.org/licenses/gpl-2.0.txt
**/

if(! defined ("ABSPATH")){
    die;
}

add_action('plugins_loaded', 'wc_xpresspay_init');

function wc_xpresspay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_Xpress_Pay extends WC_Payment_Gateway
    {
            public function __construct()
            {
                global $woocommerce;

                $this->id = 'xpresspay';
                $this->icon = apply_filters('woocommerce_xpresspay_icon', plugins_url('assets/images/xpresspay-payment-options.png', __FILE__));
                $this->method_title = __('XpressPay', 'woocommerce');
                $this->method_description = __('XpressPay redirects customers to XpressPay to enter their payment informations', 'woocommerce');
              
                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                // Define user set variables
                // $this->xpresspay_title = $this->settings['xpresspay_title'];
                $this->title              = $this->get_option( 'xpresspay_title' );
                $this->description        = $this->get_option( 'xpresspay_description' );               
                $this->xpresspay_publickey = $this->settings['xpresspay_publickey'];
                $this->xpresspay_mode = $this->settings['xpresspay_mode'];
                $this->xpresspay_enabled = $this->settings['xpresspay_enabled'];
                $this->xpresspay_payment_page = $this->settings['xpresspay_payment_page'];
                $this->xpresspay_url = null;
                $this->xpresspayVerify_url = null;

                if ( ! function_exists( 'write_log' ) ) {
                /**
                * Write log to log file
                *
                * @param string|array|object $log
                */
                function write_log( $log ) {
                    if ( true === WP_DEBUG ) {
                        if ( is_array( $log ) || is_object( $log ) ) {
                                error_log( print_r( $log, true ) );
                        }
                        else if(is_bool($log)) {
                            error_log( ($log == true)? 'true': 'false' );
                        }
                        else {
                            error_log( $log );
                        }
                    }
                }
            }

            // Hooks           
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                &$this,
                'process_admin_options'
            ));
           
            add_action('admin_notices', array( $this, 'admin_notices'));

            // Payment listener/API hook.WC_Gateway_Xpress_Pay
            add_action( 'woocommerce_api_wc_gateway_xpress_pay', array( $this, 'verify_xpresspay_transaction' ) );

            //Filters
            add_filter('woocommerce_currencies', array(
            $this,
            'add_ngn_currency'
            ));
            add_filter('woocommerce_currency_symbol', array(
            $this,
            'add_ngn_currency_symbol'
            ), 10, 2);           

        }


        function add_ngn_currency($currencies)
        {
            $currencies['NGN'] = __('Nigerian Naira (NGN)', 'woocommerce');
            return $currencies;
        }

        function add_ngn_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'NGN':
                $currency_symbol = '₦';
                break;
            }
            return $currency_symbol;
        }

        function is_valid_for_use()
        {
            $return = true;

            if (!in_array(get_option('woocommerce_currency'), array(
                'NGN'
                ))) {
                    $return = false;
            }   
            return $return;
        }

        function add_query_vars_filter( $vars ){
            $vars[] = "transId";
            return $vars;
        }       

        function admin_options()
        {
            // echo '<h3>' . __('XpressPay Payment Gateway', 'woocommerce') . '</h3>';
            echo '<h3>' . esc_html(__('XpressPay Payment Gateway', 'woocommerce')) . '</h3>';
            echo '<p>' . __('<br><img src="' . esc_url(plugins_url('assets/images/xpresslogo.png', __FILE__)) . '" >', 'woocommerce') . '</p>';
            echo '<table class="form-table">';

            if ($this->is_valid_for_use()) {
                $this->generate_settings_html();
            } else {
                echo '<div class="inline error"><p><strong>' . esc_html( __('Gateway Disabled', 'woocommerce')). '</strong>: ' . esc_html(__('XpressPay does not support your store currency.', 'woocommerce')) . '</p></div>';
            }
            echo '</table>';
        }

        function init_form_fields()
        {
            $this->form_fields = array(
                'xpresspay_title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the payment method title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Debit/Credit Cards', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'xpresspay_description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the payment method description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Make payment using your debit and credit cards', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'xpresspay_enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'), //Enable xpresspay as a payment option on the checkout page.
                    'label' => __('Enable XpressPay', 'woocommerce'),
                    'description' => __('Enable XpressPay as a payment option on the checkout page.', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woocommerce'),
                    'default' => 'no'
                ),
                'xpresspay_publickey' => array(
                    'title' => __('Public Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => 'Login to xpresspayments.com to get your public key',
                    'desc_tip' => true
                ),
                'xpresspay_payment_page' => array(
                    'title' => __('Payment Option', 'woocommerce' ),
                    'type' => 'select',
                    'description' => __('Redirect will redirect the customer to XpressPay to make payment.', 'woocommerce' ),
                    'desc_tip' => true,
                    'options' => array(
                    'redirect' => __( 'Redirect', 'woocommerce' ),
                    ),
                ),
                'xpresspay_mode' => array(
                    'title' => __('Environment', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Select Test or Live modes.', 'woothemes'),
                    'desc_tip' => true,
                    'placeholder' => '',
                    'options' => array(
                    'test' => "Test",
                    'live' => "Live"
                    )
                )
            );
        }

        function payment_fields()
        {
            // Description of payment method from settings
            if ($this->description) {
            ?>
            <p><?php
            echo esc_html($this->description);
            ?></p>

            <?php
            }
            ?>

            <?php
        }

        function admin_notices() {

            if ( $this->xpresspay_enabled == 'no' ) {
                return;
            }

           // Check required fields.
            if (!($this->xpresspay_publicKey && $this->xpresspay_mode)) {
                echo '<div class="inline error"><p><strong>' . esc_html(__('Gateway Disabled', 'woocommerce')) . '</strong>: ' . esc_html(__('Please enter your public key.', 'woocommerce')) . '</p></div>';
                return;
            }
        }

        function process_payment($order_id){
            if ('redirect' === $this->xpresspay_payment_page) {
                return $this->process_redirect_payment($order_id);
            }
            else{
                wc_add_notice( __('cannot process payment', 'woocommerce'), 'error' );
            }
        }       

        function process_redirect_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $email = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
            $amount = $order->get_total();
            $txnref = $order_id . '_' . time();
            $currency = method_exists( $order, 'get_currency' ) ? $order->get_currency() : $order->order_currency;
            $callback_url = WC()->api_request_url('WC_Gateway_Xpress_Pay');
            $callback_urlWithId = add_query_arg('transId', $txnref, $callback_url);
            
            $xpresspay_params = array(
                'email' => $email,
                'amount' => $amount,
                'transactionId' => $txnref,
                'currency' => $currency,
                'callbackUrl' => $callback_urlWithId,
            ); 

            if('live' === $this->xpresspay_mode){
                $this->xpresspay_url = 'https://myxpresspay.com:6004/api/Payments/Initialize/';
            }else {
                $this->xpresspay_url = 'https://pgsandbox.xpresspayments.com:8090/api/Payments/Initialize/';
            }                                  

            $headers = array(
                'Authorization' => 'Bearer ' . $this->xpresspay_publickey,
                'Content-Type' => 'application/json',
            );

            $args = array(
                'headers' => $headers,
                'timeout' => 60,
                'body' => json_encode($xpresspay_params),
            );            
            
            $response = wp_remote_post($this->xpresspay_url, $args);

            if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
                $xpresspay_response = json_decode(wp_remote_retrieve_body($response));
                if ($xpresspay_response->responseCode === '00') {
                    return array(
                        'result' => 'success',
                        'redirect' => $xpresspay_response->data->paymentUrl,
                    );
                }else{
                    wc_add_notice( __('payment failed from xpresspay', 'woocommerce'), 'error' );
                    return;
                }
            } else {
                wc_add_notice( __('Unable to process payment try again', 'woocommerce'), 'error' );
                return;
            }
        }

        function verify_xpresspay_transaction() {  
            $current_url = home_url( add_query_arg( null, null ));
            $transId = explode( '=', $current_url );
            $transref = $transId[1];

            // write_log('json ' .$current_url);
            write_log('transId ' .$transref);

            if('live' === $this->xpresspay_mode){
                $this->xpresspayVerify_url = 'https://myxpresspay.com:6004/api/Payments/VerifyPayment';
            }else {
                $this->xpresspayVerify_url = 'https://pgsandbox.xpresspayments.com:8090/api/Payments/VerifyPayment';
            } 
                
            $xpresspay_verifyparams = array(                
                'transactionId' => $transref
            ); 

            $verify_response = wp_remote_post($this->xpresspayVerify_url, array(
                'method' => 'POST',
                'timeout' => 60,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(
                   'Content-Type' => 'application/json',
                   'Authorization' => 'Bearer ' . $this->xpresspay_publickey,
                  ),
               'body' => json_encode($xpresspay_verifyparams),
               'cookies' => array()
                )
            );
            
            write_log('response from verify payment ' . wp_remote_retrieve_response_code($verify_response));

            if (!is_wp_error($verify_response) && 200 === wp_remote_retrieve_response_code($verify_response)) {
                $xpresspay_verify_response = json_decode(wp_remote_retrieve_body($verify_response));

                if ($xpresspay_verify_response->responseCode === '00' && true == $xpresspay_verify_response->data->isSuccessful ) {
                    write_log('verify payment successful');
                    $order_details = explode( '_', $xpresspay_verify_response->data->transactionId );
					$order_id      = (int) $order_details[0];
					$order         = wc_get_order( $order_id );

                    if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
						wp_redirect( $this->get_return_url( $order ) );
						exit;
					}
                                       
                    $order->payment_complete( $transref );
                    $order->add_order_note( sprintf( __( 'Payment successful (Transaction Reference: %s)', 'woocommerce' ), $transref ) );                   
                    $order->update_status( 'completed' );                    
                                      
                }else{

                    $order_details = explode( '_', $transref );
					$order_id = (int) $order_details[0];
					$order = wc_get_order( $order_id );
					$order->update_status( 'failed', __( 'Payment was declined by XpressPay.', 'woocommerce' ) );
                }
            } 

            wp_redirect( $this->get_return_url( $order ) );
			exit;
        }        
    }

    function woocommerce_add_xpresspay_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Xpress_Pay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_xpresspay_gateway');
}

?>