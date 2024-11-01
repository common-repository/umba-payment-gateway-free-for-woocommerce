<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.1
 * @package    umba-payment-gateway-free-for-woocommerce
 * @subpackage umba-payment-gateway-free-for-woocommerce/includes
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function UMBA_init_gateway(){
    
    if( !class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_Umba extends WC_Payment_Gateway {

        /**
         * Our constructor
         */
        public function __construct(){
            $this->id                 = 'umba';
            $this->icon               = UMBA_ASSETS_DIR . '/images/UmbaLoanPay_Button.png'; 
            $this->has_fields         = false;
            $this->method_title       = __( 'Umba LoanPay - financing option', 'woocommerce-umba' );
            $this->method_description = __( 'Allow customers to conveniently pay with Umba payment gateway.', 'woocommerce-umba' );
            $this->callback_url       = $this->umba_callback();

            $this->init_form_fields();
            $this->init_settings();
            $this->title              = $this->get_option( 'title' );
            $this->description        = UMBA_gateway_description;
            $this->instructions       = UMBA_gateway_instruction;
            $this->mer                = $this->get_option( 'mer' );
            $this->vid                = $this->get_option( 'vid' );
            $this->merchant_country   = $this->get_option( 'merchant_country' );
            $this->hsh                = $this->get_option( 'hsh' );
            $this->live               = $this->get_option( 'live' );
            $this->mkoporahisi        = $this->get_option( 'mkoporahisi' );
            $this->sec_key            = $this->get_option( 'sec_key' );
            $this->api_key            = $this->get_option( 'api_key' );
            $this->integration_type   = $this->get_option( 'integration_type' );
            $this->environment        = $this->get_option( 'environment' );
            $this->interest_rate      = $this->interest_rate();
            $total                    = WC()->cart ? floatval( preg_replace( '#[^\d.]#', '', WC()->cart->get_total() ) ): 0;

            
            if(WC()->cart && ceil($total) > 0){	
                $amount= ceil(($total*$this->interest_rate)/4); 
                $this->description = sprintf(UMBA_gateway_description_2, $amount);
            }else{
                if(isset($_GET['key'])){
                    $order_id   = wc_get_order_id_by_order_key(esc_attr(strip_tags($_GET['key'])));
                    $order      = wc_get_order($order_id);
                    $amount= ceil(($order->get_total()*$this->interest_rate)/4); 
                    $this->description = sprintf(UMBA_gateway_description_2, $amount);
                }
            }
            add_action('init', array($this, 'callback_handler'));
            add_action('woocommerce_api_'.strtolower( get_class( $this ) ), array( $this, 'callback_handler' ) );
            if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options' ) );
            }else {
                add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
            }
            add_action( 'woocommerce_receipt_umba', array( $this, 'receipt_page' ) );
        }

        /**
         * Add our form fields to WooCommerce
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'title' => array(
                    'title'       => __( 'Title', 'woocommerce-umba' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-umba' ),
                    'default'     => __( 'Umba LoanPay - financing option', 'woocommerce-umba' ),
                    'desc_tip'    => true,
                ),
                'integration_type' => array(
                    'title'       => __( 'Integration type', 'woocommerce-umba' ),
                    'type'        => 'select',
                    'description' => __( 'Type of integration (direct with Umba or iPay)', 'woocommerce-umba' ),
                    'default'     => __( 'Select integration', 'woocommerce-umba' ),
                    'options' => array(
                            'direct_umba' => 'Umba (direct)',
                            'ipay' => 'iPay',
                        ),
                    'desc_tip'    => true,
                ),
                'environment' => array(
                    'title'       => __( 'Environment', 'woocommerce-umba' ),
                    'type'        => 'select',
                    'description' => __( 'Umba environment', 'woocommerce-umba' ),
                    'default'     => __( 'Select environment', 'woocommerce-umba' ),
                    'options' => array(
                            'stage' => 'Umba Stage',
                            'production' => 'Umba Production',
                        ),
                    'desc_tip'    => true,
                ),
                'merchant_country' => array(
                    'title'       => __( 'Merchant Country', 'woocommerce-umba' ),
                    'type'        => 'select',
                    'description' => __( 'The location of Umba you assigned up to as a merchant.', 'woocommerce-umba' ),
                    'default'     => __( 'Select Country', 'woocommerce-umba' ),
                    'options'     => unserialize(UMBA_countries),
                    'desc_tip'    => true,
                    ),
                'mer' => array(
                    'title'       => __( 'Merchant Name', 'woocommerce-umba' ),
                    'description' => __( 'Company name', 'woocommerce-umba' ),
                    'type'        => 'text',
                    'default'     => __( 'Company Name', 'woocommerce-umba'),
                    'desc_tip'    => false,
                ),
                'vid' => array(
                    'title'       => __( 'Vendor ID', 'woocommerce-umba' ),
                    'type'        => 'text',
                    'description' => __( 'Direct integration assigned by Umba,iPay integration assigned by iPay. SET IN LOWER CASE.', 'woocommerce-umba' ),
                    'default'     => __( 'demo', 'woocommerce-umba' ),
                    'desc_tip'    => false,
                ),
                'hsh' => array(
                    'title'       => __( 'Security Key', 'woocommerce-umba'),
                    'type'        => 'password',
                    'description' => __( 'Security key assigned by iPay', 'woocommerce-umba' ),
                    'default'     => __( 'demo', 'woocommerce-umba' ),
                    'desc_tip'    => false,
                ),
                'live' => array(
                    'title'     => __( 'Live/Demo', 'woocommerce-umba' ),
                    'type'      => 'checkbox',
                    'label'     => __( 'Make Umba LoanPay live', 'woocommerce-umba' ),
                    'default'   => 'no',
                ),
                'sec_key' => array(
                    'title'       => __( 'Umba Security Key', 'woocommerce-umba'),
                    'type'        => 'password',
                    'description' => __( 'Security key assigned by Umba', 'woocommerce-umba' ),
                    'default'     => __( 'demo', 'woocommerce-umba' ),
                    'desc_tip'    => false,
                ),
                'api_key' => array(
                    'title'       => __( 'Umba Api Key', 'woocommerce'),
                    'type'        => 'password',
                    'description' => __( 'Api key assigned by Umba', 'woocommerce' ),
                    'default'     => __( 'demo', 'woocommerce' ),
                    'desc_tip'    => false,
                ),
            );
        }

        /**
         * Our admin options for WooCommerce
         */
        public function admin_options(){
            echo '<h3>' . __('Umba Payments Gateway','woocommerce-umba') . '</h3>';
            echo '<p>' . __('Allow customers to conveniently pay with Umba Loan.','woocommerce-umba') . '</p>';
            echo '<table class="form-table">';
                $this->generate_settings_html();
            echo '</table>';
        }

        /**
         * Redirects Umba
         */
        public function receipt_page( $order_id ) {
            if ($this->integration_type == "ipay") {
                echo $this->redirect_umba( $order_id );
            }
        }

        /**
         * Redirects Umba
         */
        public function redirect_umba( $order_id ) {
            global $woocommerce;

            $order = wc_get_order( $order_id );

            $mkoporahisi   = ($this->mkoporahisi == 'yes')? 1 : 0;

            if ( $this->live == 'no' ) {
                $live   = 0;
            }
            else{
                $live   = 1;
            }

            $mer            = $this->mer;
            $tel            = $order->get_billing_phone();
            $tel            = str_replace("-", "", $tel);
            $tel            = str_replace( array(' ', '<', '>', '&', '{', '}', '*', "+", '!', '@', '#', "$", '%', '^', '&'), "", $tel );
            $eml            = $order->get_billing_email();
            $live           = $live;
            $vid            = $this->vid;
            $oid            = $order->get_id();
            $inv            = $oid;
            $p1             = '';
            $p2             = '';
            $p3             = '';
            $p4             = '';
            $mkoporahisi    = $mkoporahisi;
            $eml            = $order->get_billing_email();

            $supported_currencies = unserialize(UMBA_supported_currencies);

            $curr = "";

            if(in_array(get_woocommerce_currency(), $supported_currencies)){
                $curr       = get_woocommerce_currency();
            } else {
                echo __('Unsupported currency','woocommerce-umba');
                exit();
            }

            $ttl        = $order->get_total();
            if(in_array($ttl,$supported_currencies) && $curr != "USD") {
                $ttl    = ceil($ttl);
            }

            $tel        = $tel;
            $crl        = '0';
            $cst        = '1';
            $callbk     = $this->callback_url;
            $cbk        = $callbk;
            $hsh        = $this->hsh;

            $datastring = $live.$oid.$inv.$ttl.$tel.$eml.$vid.$curr.$p1.$p2.$p3.$p4.$cbk.$cst.$crl;
            $hash_string= hash_hmac('sha1', $datastring,$hsh);
            $hash       = $hash_string;

            $url_ke = UMBA_url_key;
            $ipayUrl = "";
            switch($this->merchant_country) {
                case "ke":
                    $ipayUrl = $url_ke;
                    break;
                default:
                    echo __('Unsupported merchant country','woocommerce-umba');
                    exit();
                    break;
            }

            $url        = $ipayUrl."?live=".$live."&oid=".$oid."&inv=".$inv."&ttl=".$ttl."&tel=".$tel."&eml=".$eml."&vid=".$vid."&curr=".$curr."&p1=".$p1."&p2=".$p2."&p3=".$p3."&p4=".$p4."&autopay=0"."&mpesa=0"."&airtel=0"."&equity=0"."&creditcard=0"."&debitcard=0"."&pesalink=0"."&mkoporahisi=".$mkoporahisi."&cbk=".$cbk."&cst=".$cst."&crl=".$crl."&hsh=".$hash;
            header("location: $url");
            exit();
        }

        /**
         * Returns link to the callback class
         * Refer to WC-API for more information on using classes as callbacks
         */
        public function umba_callback(){
            return WC()->api_request_url('WC_Gateway_Umba');
        }

        public function interest_rate(){
            if (in_array($this->vid , unserialize(UMBA_zero_percent))){
                return 1;
            }
            else{
                return 1.11;
            }
        }
    
        /**
         * This function gets the callback values posted by iPay to the callback url
         * It updates order status and order notes
         */
        public function callback_handler(){
            if ($this->integration_type == "direct_umba") {

                $headers                =   $this->get_request_headers();
                // Get the body
                $body = file_get_contents('php://input');
                $data                   =   (object) json_decode($body, true);
                $received_order_key     =   $data->ext_id;
                $received_order_id      =   wc_get_order_id_by_order_key($received_order_key);
                $status                 =   $data->status;
                $error_reason           =   $data->error_reason;
				if (array_key_exists('reference', $data)) {
                        	$reference = $data->reference;
						}
						else{
                        	$reference = "";		
						} 
                $order                  =   new WC_Order ( $received_order_id );
                // Hash the body and verify
                $api_sign               =   hash_hmac( "sha256", $body, $this->sec_key);
                            
                if($order && isset($headers['ApiKey']) && $headers['ApiSign'] == $api_sign){
                    switch($status){
                        case "successful":
                            $order->update_status( 'completed', __('The order was SUCCESSFULLY processed by Umba.<br>', 'woocommerce-umba' ));
                            $order->reduce_order_stock();
                            $reason = __('The order was SUCCESSFULLY processed by Umba.','woocommerce-umba');
                            // Save the umba status as metadata
                            update_post_meta($received_order_id, 'umba_error', 0);
                            update_post_meta($received_order_id, 'umba_status', $reason);
                            break;
                        case "payment_reference";
                            update_post_meta($received_order_id, 'umba_payment_reference', $reference);
                            break;
                        case "failed":
                            // The text for the note
                            $note = $error_reason;
                            $order->add_order_note( 'UMBA - ' . $note );
                            // Save the error message as metadata
                            $order->update_status( 'failed', __('The order was not processed by Umba.<br>', 'woocommerce-umba' ));
                            update_post_meta($received_order_id, 'umba_error', 1);
                            update_post_meta($received_order_id, 'umba_error_type', 'failed');
                            update_post_meta($received_order_id, 'umba_status', $error_reason);
                            break;
                        default:
                            // Save the error message as metadata
                            update_post_meta($received_order_id, 'umba_error', 1);
                            update_post_meta($received_order_id, 'umba_status', __('Unsupported order status','woocommerce-umba'));
                            break;
                    }
                }
            }else {
                global $woocommerce;
                $ipn_ke = UMBA_ipn_key;
                $ipn_base = "";
                switch ($this->merchant_country) {
                    case 'ke':
                        $ipn_base = $ipn_ke;
                        break;
                    default:
                        echo __('Unknown country of operation','woocommerce-admin');
                        exit();
                        break;
                }

                $val            = $this->vid;
                $val1           = sanitize_text_field($_GET['id']);
                $val2           = sanitize_text_field($_GET['ivm']);
                $val3           = sanitize_text_field($_GET['qwh']);
                $val4           = sanitize_text_field($_GET['afd']);
                $val5           = sanitize_text_field($_GET['poi']);
                $val6           = sanitize_text_field($_GET['uyt']);
                $val7           = sanitize_text_field($_GET['ifd']);
                $ipnurl         = $ipn_base."vendor=".$val."&id=".$val1."&ivm=".$val2."&qwh=".$val3."&afd=".$val4."&poi=".$val5."&uyt=".$val6."&ifd=".$val7;
                $fp             = fopen($ipnurl, "rb");
                $status         = stream_get_contents($fp, -1, -1);
                fclose($fp);
                $this->notifications($status,$val1);
            }
        }
    
        /**
         * This will give back a notification based on the status
         * @param $status, $order_id
         */
        public function notifications($status,$order_id) {
            $order = new WC_Order ( $order_id );
            //Failed
            if($status == "fe2707etr5s4wq" )
            {
                $order->update_status('failed', __('The attempted payment FAILED - iPay.<br>', 'woocommerce-umba' ));
                wp_die( sprintf(__('iPay payment failed. Check out the email sent to you from iPay for the reason of failure of order %s.','woocommerce-umba'), $order_id) );
            }
            // Successful
            else if($status == "aei7p7yrx4ae34" ) 
            {
                $order->update_status( 'completed', __('The order was SUCCESSFULLY processed by iPay.<br>', 'woocmmerce-umba' ));
                $order->reduce_order_stock();
                wp_redirect( $this->get_return_url( $order ) );
            }
            // Pending
            else if($status == "bdi6p2yy76etrs")
            { 
                $order->update_status( 'pending', __('The transaction is PENDING. Tell customer to try again -iPAY', 'woocmmerce-umba' ));
                wp_die(__('The iPay payment is pending. Please try again in 5 minutes or contact the the owner of the site for assistance.', 'woocmmerce-umba'));
            }
            // Used code
            else if($status == "cr5i3pgy9867e1" )
            {
                $order->update_status( 'payment-used', __( 'The input payment code has already been USED. Please contact customer - iPay.<br>', 'woocmmerce-umba') );
                wp_die(__('The iPay payment has already been used. Contact the owner of the site for further assistance.','woocmmerce-umba'));
            }
            // Less
            else if($status == "dtfi4p7yty45wq")
            {
                $order->update_status( 'on-hold', __( 'Amount paid was LESS than the required - iPay.<br>', 'woocmmerce-umba') );
                wp_die(__('The iPay payment received is less than the transaction amount expected. Contact the Merchant for assistance.','woocmmerce-umba'));
            }
            // More
            else if($status == "eq3i7p5yt7645e"){
                $order->update_status( 'overpaid', __('The order was overpaid but SUCCESSFULLY processed by iPay.<br>', 'woocmmerce-umba' ));
                $order->reduce_order_stock();
                wp_redirect( $this->get_return_url( $order ) );
            }
            die;
        }
    
        /**
         * Process the payment field and redirect to checkout/pay page.
         *
         * @param $order_id
         * @return array
         */
        public function process_payment( $order_id ){
            if ($this->integration_type == "direct_umba") {
                $error = $this->create_umba_order( $order_id );
                // If the status was failed we want to to another call so we need to set it back to pending
                $order         = $order_id ? wc_get_order($order_id) : 0;
                $order_status  = $order ? $order->get_status() : '';
                if($order_status == 'failed'){
                    $order->update_status( 'pending', __('The order for Umba is processed again.<br>', 'woocommerce-umba' ));
                }
                // success returned when creating POS loan, redirect to Umba app
                if ($error == "") {
                    $order = wc_get_order( $order_id );
                    return array(
                        'result' => 'success',
                        'redirect' => add_query_arg('order', $order->get_id(),
                                        add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)))
                    );
                    // error returned when creating POS loan, do not redirect from Checkout page
                }else{
                    wc_add_notice( __($error, 'woocommerce'), 'error' );
                }
            } else {
                $order = new WC_Order( $order_id );
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $order->get_id(),
                        add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)))
                );
            }
        }

        /**
         * Create the UMBA order
         *
         * @param $order_id
         * @return array
         */
        public function create_umba_order( $order_id ) {
                $create_order_url = "";
                switch($this->environment) {
                case "stage":
                    $create_order_url = UMBA_gateway_order_api_staging;
                    break;
                case "production":
                    $create_order_url = UMBA_gateway_order_api;
                    break;
                default:
                    echo __("Unsupported currency",'woocommerce-umba');
                    exit();
                    break;
                }
                $supported_currencies = unserialize(UMBA_supported_currencies);
                $currency = "";
                if(in_array(get_woocommerce_currency(), $supported_currencies)){
                    $currency = get_woocommerce_currency();
                } else {
                    echo __("Unsupported currency",'woocommerce-umba');
                    exit();
                }
                $vendor_id = $this->vid;
                $amount = WC()->cart ? floatval( preg_replace( '#[^\d.]#', '', WC()->cart->get_total() ) ): 0;
                $msisdn = WC()->customer->get_billing_phone();
                $email =  WC()->customer->get_billing_email();
                
                if(wp_is_mobile()) { 
                    $user_agent = "mobile";
                } else {
                    $user_agent = "desktop";
                }

                $order              = new WC_Order ( $order_id );
                $redirect_url       = $this->get_return_url( $order );
                $ext_id             = $order->get_order_key();
                
                $data = array('email' => $email, 'msisdn' => $msisdn, 'ext_id' => $ext_id, 'order_id' => strval($order_id), 'vendor_id' => $vendor_id,
                            'amount' => strval(intval($amount)), 'currency' => $currency, 'user_agent' => $user_agent, 'redirect_url' => $redirect_url);
                
                $api_sign=hash_hmac ( "sha256" , json_encode($data) , $this->sec_key);  
                    
                $umba_arg = array(
                            'headers'     => array('Content-Type' => 'application/json','Accept' => 'application/json', 
                            'API-Key' => $this->api_key,'API-Sign' => $api_sign),
                            'method'      => 'POST',
                            'body' => json_encode($data),
                        );
                $request = wp_remote_post($create_order_url,$umba_arg);
                $body = wp_remote_retrieve_body( $request );
                $response_code = wp_remote_retrieve_response_code($request);
                $data = json_decode( $body );

                switch($response_code){
                    case 200:
                    case 201:
                        $error = "";
                        break;
                    default:
						if (array_key_exists('loan', $data)) {
                        	$error = $data->loan[0];
						}
						else{
                        	$error = $data->error[0];		
						}					
                        break;
                }
                return $error;
        }

        /**
         * This will get the headers
         *
         * @param $order_id
         * @return array
         */
        public function get_request_headers(){
            // Get the headers
            $headers = array();
            foreach($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headers[str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))))] = $value;
                }
            }
            return $headers;
        }

    }
}