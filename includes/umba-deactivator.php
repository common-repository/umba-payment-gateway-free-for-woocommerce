<?php

/**
 * Fired during plugin deactivation
 *
 * @since      1.0.0
 * @package    umba-payment-gateway-free-for-woocommerce
 * @subpackage umba-payment-gateway-free-for-woocommerce/includes
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class UMBA_Deactivator { 

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.1
	 */
	public static function deactivate() {
		// Delete the option
		delete_option( 'Activated_WooCommerce_Umba' );
	}

}