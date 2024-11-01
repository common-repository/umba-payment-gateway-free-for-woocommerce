<?php

/**
 * Define the internationalization functionality.
 * 
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    umba-payment-gateway-free-for-woocommerce
 * @subpackage umba-payment-gateway-free-for-woocommerce/includes
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class UMBA_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'woocommerce-umba',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}
}