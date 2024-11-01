<?php

/**
 * The actual WP functions and hooks.
 * 
 * @since      1.0.0
 * @package    umba-payment-gateway-free-for-woocommerce
 * @subpackage umba-payment-gateway-free-for-woocommerce/includes
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class UMBA_Hooks {

    private static                              $debug          = UMBA_debug;
    
    /**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.5
	 *
	 * @param      string $plugin_name The name of the plugin.
	 * @param      string $version The version of this plugin.
	 */
	public function __construct() {
        // Nothing to construct
    }

    /**
	 * This will add the gateway to WooCommerce
	 */
    public function UMBA_add_gateway_class( $methods ) {
        $methods[] = 'WC_Gateway_Umba';
        return $methods;
    }

    /**
	 * This will remove the Gateway if the customer do not have enough credit
	 */
    public function UMBA_filter_payment_gateways($available_gateways){
        global $woocommerce;
        $umba                                                   = new WC_Gateway_Umba();
        $total_in_cart                                          = WC()->cart ? floatval( preg_replace( '#[^\d.]#', '', WC()->cart->get_total() ) ): 0;
        $signature                                              = '';
        if(isset($_POST['post_data']) && !empty($_POST['post_data'])){

            // Remove the session first
            WC()->session->set('WC_UMBA_hide', null);
            WC()->session->set('WC_UMBA_hide_reason', null);

            $post_data           = wp_parse_args($_POST['post_data']);
            $phone               = esc_attr($post_data['billing_phone']);
            $data = array('msisdn' => $phone, 'merchant_id' => $umba->vid);
            if ($umba->integration_type == "direct_umba"){
                switch($umba->environment) {
                        case "stage":
                            $signature  = hash_hmac ( "sha256" , json_encode($data)  , $umba->sec_key);
                            $limit_url  = UMBA_gateway_api_staging;
                            break;
                        case "production":
                            $signature  = hash_hmac ( "sha256" , json_encode($data)  , $umba->sec_key);
                            $limit_url  = UMBA_gateway_api;
                            break;
                        default:
                            echo __("Unsupported environment",'woocommerce-umba');
                            exit();
                }
            }elseif ($umba->integration_type == "ipay"){
                $signature  = hash_hmac( "sha256" , json_encode($data) , $umba->api_key);
                $limit_url  = UMBA_gateway_api;
            }
        }
        if($signature){
            $umba_arg = array(
                                'headers'     => array('Content-Type' => 'application/json','Accept' => 'application/json', 
                                'API-Key' => $umba->api_key,'API-Sign' => $signature),
                                'method'      => 'POST',
                                'body' => json_encode($data),
                            );
        
            $request                    = wp_remote_post($limit_url,$umba_arg);
            $body                       = wp_remote_retrieve_body( $request );
            $data                       = json_decode( $body );
            $unset_umba                 = true;
            $available_gateways_keys    = array_keys($available_gateways);
            $amount                     = 0;
            $reason                     = !empty($data->reason) ? sanitize_text_field($data->reason) : __("You have not been approved for a loan with Umba at this time. Please choose another payment option!", "woocommerce-umba");
            
            // First check the amount
            if($data && property_exists($data, 'amount' )){
                $amount = $data->amount;
            }
            // If we have an amount and the total > amount we need to disable it
            if ($amount>0 && $total_in_cart < $amount){
                $unset_umba = false;
            }
            // If we need to unset umba
            if($unset_umba == true){
                //Disabling or enabling payment gateways
                if($available_gateways_keys){
                    foreach ($available_gateways_keys as $available_gateway_key) {
                        if ($available_gateway_key == 'umba') {
                            unset($available_gateways['umba']);
                        }
                    }
                }
            }
            // If no gateways are active, remove the "place order" button
            if (empty($available_gateways)) {
                add_filter('woocommerce_order_button_html', '__return_empty_string');
            }
            // Use cookies over session
            if($unset_umba == true){
                WC()->session->set( 'WC_UMBA_hide', 1 );
                WC()->session->set( 'WC_UMBA_hide_reason', $reason );
            }
        }
        return $available_gateways;
    }

    /**
	 * This will make the phone number required
	 */
    public function UMBA_required_phone_field( $fields) {
        $fields['billing']['billing_phone'] = array(
                                                'label' => __('Phone', 'woocommerce'), 
                                                'class' => array('umba-check'), 
                                                'required' => true
                                            );
        return $fields;
    }

    /**
	 * This will add some settings for WooCommerce
	 */
    public function UMBA_settings( $links ) {
        $settings_link  = '<a href="'.admin_url( 'admin.php?page=wc-settings&tab=checkout&section=umba' ).'">'.__('Settings','woocommerce-umba').'</a>';
        $ipay_docs      = '<a href="'.UMBA_dev_docs.'" title="'.__('Docs','woocommerce-umba').'">'.__('Docs','woocommerce-umba').'</a>';
        array_push( $links, $settings_link );
        array_push( $links, $ipay_docs );
        return $links;
    }

    /**
	 * Check if WooCommerce is active or not
	 */
    public function UMBA_check_woocommerce(){
        if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {
            $message = sprintf(__('Umba requires WooCommerce to be installed and active. You can download <a href="%s" target="_blank">WooCommerce</a> here.','woocommerce-umba'), 'https://woocommerce.com/');
            new UMBA_Message($message, 'error', false);
        }
    }

    /**
	 * Refresh checkout
	 */
    public function UMBA_add_update_cart(){

        // Only on checkout page
        if( ! is_checkout() ) return;
        ?>
        <script type="text/javascript">
            jQuery('.umba-check').on('change', function(){
                jQuery(document.body).trigger("update_checkout");
                // Do ajax query to check
                setTimeout(function(){
                    jQuery.ajax({
                        type : "post",
                        dataType : "json",
                        url : woocommerce_params.ajax_url,
                        data : { action: "umba_hide" },
                        success: function(response) {
                            if(response.hide == 1){
                                jQuery('.woocommerce-info.umba-message').html('').html(response.reason);
                                jQuery('body').addClass('umba_hide');
                            }else{
                                jQuery('body').removeClass('umba_hide');
                            }
                        }
                    });
                }, 1000);
            });
        </script>
        <?php
    }

    /**
	 * Assets needed for the checkout page
	 */
	public function UMBA_enqueue_assets() {
		$minify_enabled = (UMBA_MINIFY == true) ? 'min.' : '';
        $minify_version = (UMBA_MINIFY == true) ? UMBA_CSS_JS_VERSION : time();
        wp_enqueue_style( 'umba-checkout-css', UMBA_ASSETS_DIR . sprintf('css/umba-checkout.%scss', $minify_enabled), '', $minify_version, 'all' );
    }

    /**
	 * Check is UMBA is hidden or not
	 */
    public function UMBA_hide(){
        session_start();
        $hide       = false;
        $reason     = '';
        if(null !== WC()->session->get('WC_UMBA_hide')){
            $hide       = sanitize_text_field(WC()->session->get('WC_UMBA_hide'));
        }
        if(null !== WC()->session->get('WC_UMBA_hide_reason')){
            $reason     = sanitize_text_field(WC()->session->get('WC_UMBA_hide_reason'));
        }
        echo json_encode(array('hide' => (int) $hide, 'reason' => $reason));
        exit;
    }

    /**
	 * Add a message to tell the customer UMBA is not available
	 */
    public function UMBA_message(){
        ?>
        <div class="woocommerce-info umba-message" style="display:none;"></div>
        <style>body.umba_hide .woocommerce-info.umba-message{ display: block !important; }</style>
        <script type="text/javascript">
            jQuery(document).ready(function() {
                if(jQuery('.payment_method_umba').length == 0){
                    jQuery('body').addClass('umba_hide');
                }
            });
        </script>
    <?php
    }

    /**
	 * Add some logic for the UMBA settings page in the backend
	 */
    public function UMBA_admin_footer(){
        global $pagenow;
        if($pagenow == 'admin.php' && isset($_GET['tab']) && isset($_GET['section']) && $_GET['tab'] == 'checkout' && $_GET['section'] == 'umba'){
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function(){
                change_Umba();
                if(jQuery('#woocommerce_umba_integration_type').length > 0){
                    jQuery('#woocommerce_umba_integration_type').trigger('change');
                }
                function change_Umba(){
                    jQuery('select[name="woocommerce_umba_integration_type"]').on('change', function(){
                        if(jQuery(this).val() == 'direct_umba'){
                            jQuery('#woocommerce_umba_sec_key').parents('tr').show();
                            jQuery('#woocommerce_umba_api_key').parents('tr').show();
                            jQuery('#woocommerce_umba_environment').parents('tr').show();
                            // Hide others
                            jQuery('#woocommerce_umba_vid').parents('tr').show();
                            jQuery('#woocommerce_umba_hsh').parents('tr').hide();
                            jQuery('#woocommerce_umba_live').parents('tr').hide();
                        }else{
                            jQuery('#woocommerce_umba_sec_key').parents('tr').hide();
                            jQuery('#woocommerce_umba_api_key').parents('tr').show();
                            jQuery('#woocommerce_umba_environment').parents('tr').hide();
                            // Hide others
                            jQuery('#woocommerce_umba_vid').parents('tr').show();
                            jQuery('#woocommerce_umba_hsh').parents('tr').show();
                            jQuery('#woocommerce_umba_live').parents('tr').show();
                        }
                    });
                }
            });
        </script>
    <?php
        }
    }

    /**
     * This will add an message on the thank you page
     */
    public function UMBA_checkout_page($order_id, $hide = false, $prepend = false, $prepend_to = ''){
        $order                      = wc_get_order($order_id);
        $gateways                   = WC()->payment_gateways->get_available_payment_gateways();
        $installed_gateways         = array_keys($gateways);
        $allowed                    = UMBA_gateway_key;

        if(in_array($allowed, $installed_gateways) && $allowed == $order->get_payment_method() && $order->get_status() != 'completed'){
    ?>
        <?php
            if($order->get_status() != 'completed' && $order->get_status() != 'failed'){
        ?>
        <div class="umba" style="<?php echo $hide == true ? 'display: none;' : ''; ?>">
            <div class="payment-initiate" style="margin-bottom: 25px;">
                <div class="load"><div class="lds-dual-ring"></div></div>
                <p class="processing">
                    <?php echo __('An SMS has been sent to your phone number with a link to complete checkout.','woocommerce-umba'); ?>
                    <br/>
                    <strong>
                        <?php echo __('Keep this window open and complete the checkout process.','woocommerce-umba'); ?>
                    </strong>
                </p>
                <p class="error" style="display:none;"><?php echo __('Oops! Something went wrong. Please contact the administrator by email or try again.','woocommerce-umba'); ?></p>
            </div>
        </div>
        <style>.woocommerce-order-details, .woocommerce-customer-details{ display: none; }</style>
        <script>
            window.onload   =  function(){
                window.onbeforeunload = function(){
                    return true;
                };
            };
            jQuery( document ).ready(function(){
				
				<?php if($prepend == true && !empty($prepend_to)){ ?>
                    var umba_holder = jQuery('.umba');
                    var prepend_to  = '<?php echo $prepend_to; ?>';
                    jQuery('.'+prepend_to).prepend(umba_holder);
                    jQuery('.umba').show();
                <?php } ?>
				
                setTimeout(function(){
                    var data = {
                        'action': 'umba_check_order_status',
                        'order_id': '<?php echo $order->get_id(); ?>'
                    };
                    // We can also pass the url value separately from ajaxurl for front end AJAX implementations
                    jQuery.post(woocommerce_params.ajax_url, data, function(response) {
                        if(response.status && response.status == 'completed'){
                            // Redirect
                            window.onbeforeunload = null;
                            jQuery('.umba .payment-initiate').hide();
                            jQuery('.woocommerce-order-details, .woocommerce-customer-details').show();
                            // If custom redirect
                            document.location.href = response.redirect;
                        }else{
                            if(response.status == 'failed'){
                                window.onbeforeunload = null;
                                document.location.href = response.redirect;
                            }else{
                                setTimeout(function(){
                                    window.onbeforeunload = null;                                    
                                    jQuery('body').addClass('umba-auto-reload');
                                    window.location.reload();
                                }, <?php echo UMBA_checkout_timeout; ?>);
                            }
                        }
                    }, 'json');
                }, <?php echo UMBA_checkout_timeout; ?>);
            });
        </script>
        <?php } ?>
        <?php if(isset($_GET['umba_error_message']) && !empty($_GET['umba_error_message']) && $order->get_status() == 'failed'){ ?>
            <span class="woocommerce-thankyou-order-failed-umba" style="display: none;">
                <?php wc_print_notice(__('Reason:','woocommerce-umba') . ' ' . sanitize_text_field(stripslashes($_GET['umba_error_message'])), 'error'); ?>
            </span>
            <script>
                jQuery( document ).ready(function(){
                    jQuery('.woocommerce-thankyou-order-failed').html(jQuery('.woocommerce-thankyou-order-failed-umba'));
                    jQuery('.woocommerce-thankyou-order-failed-umba').fadeIn();
                });
            </script>
        <?php } ?>
    <?php
        }
    }

    /**
     * This will check the order status and checks if the user has done the SMS verification
     */
    public function UMBA_check_order_status(){
        $order_id                    = esc_attr($_POST['order_id']);
        $order_status                = 'pending';
        $error                       = false;
        $message                     = __('Still processing!','woocommerce-umba');
        $order                       = $order_id ? wc_get_order($order_id) : 0;
        $redirect                    = str_replace('/'. $order_id . '/', '/', wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ))) . sprintf('?key=%s&order=%s', $order->order_key , $order_id);
        if($order_id && $order){
            $error                   = get_post_meta($order_id, 'umba_error', true) == true ? true : false;
            $umba_status_message     = get_post_meta($order_id, 'umba_status', true);
            $order_status            = $order->get_status();
            // Check status and if there is some error from UMBA
            if($order_status != 'completed' && $error == true){
                $message = $umba_status_message;
            }
            if($order_status == 'failed'){
                $redirect = $redirect . '&umba_error_message=' . sanitize_text_field($umba_status_message);
            }
        }else{
            $error                   = true;
            $message                 = __('No {order_id} supplied with the call','woocommerce-umba');
        }
        echo json_encode(array('status' => $order_status, 'error' => $error, 'message' => $message, 'redirect' => $redirect));
        exit;
    }
	
	/**
	 * Refresh checkout when pending payment
	 */
    public function UMBA_pending_payment(){
		if(isset($_GET['key']) && !empty($_GET['key'])){
			$order_id   = wc_get_order_id_by_order_key(esc_attr(strip_tags($_GET['key'])));
			$order      = wc_get_order($order_id);
			if($order->get_payment_method() == UMBA_gateway_key){
				$this->UMBA_checkout_page($order_id, true, true, 'woocommerce-notices-wrapper');
            }
		}
    }

    /**
     * This will return if the current currency is equal to the global currency setting of the plugin
     */
    public function UMBA_activate_hooks(){
        $currency = apply_filters( 'woocommerce_currency', get_option( 'woocommerce_currency' ) );
        if(in_array($currency, unserialize(UMBA_allowed_currencies))){
            add_option('umba_currency_check', true);
        }else{
            delete_option('umba_currency_check');
        }
    }

    // This will write to a log file if needed
    // @important! Never enable this on a live environment
    public function UMBA_write_to_log($log){
        $upload         = wp_upload_dir();
        $upload_dir     = $upload['basedir'];
        $upload_dir     = $upload_dir . '/umba-logging';
        if (! is_dir($upload_dir)) {
            mkdir( $upload_dir, 0777 );
        }
        $filename 	= $upload_dir . '/umba-log.txt';
        file_put_contents($filename, $log . PHP_EOL, FILE_APPEND);
    }

}