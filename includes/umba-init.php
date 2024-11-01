<?php

/**
 * 
 * Init dependecies
 * 
 * @since      1.0.0
 * @package    umba-payment-gateway-free-for-woocommerce
 * @subpackage umba-payment-gateway-free-for-woocommerce/includes
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class UMBA_init {

    /**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'umba-payment-gateway-free-for-woocommerce';
		$this->version     = '1.0.0';
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->set_locale();
	}

	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/umba-loader.php';

		/**
		 * The class responsible to show admin messages
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/umba-messages.php';

		/**
		 * The class responsible for WooCommerce payment gateway
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/umba-wc-gateway.php';

		/**
		 * The class responsible for WooCommerce hooks
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/umba-hooks.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/umba-i18n.php';

		$this->loader 	= new UMBA_Loader();

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	public function define_admin_hooks() {
		$wphooks		= new UMBA_Hooks();
		// Check if WooCommerce is installed and active or show a message
		$this->loader->add_action('init', $wphooks, 'UMBA_check_woocommerce' );
		$this->loader->add_action( 'woocommerce_init', $wphooks, 'UMBA_activate_hooks' );
		
		if(get_option('umba_currency_check') && in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )){
			// Register payment gateway.
			$this->loader->add_filter('woocommerce_payment_gateways', $wphooks, 'UMBA_add_gateway_class');
			// Filter for checkout page.
			$this->loader->add_filter('woocommerce_available_payment_gateways', $wphooks, "UMBA_filter_payment_gateways");
			// Make the phone required.
			$this->loader->add_filter('woocommerce_checkout_fields', $wphooks, 'UMBA_required_phone_field', 10);
			// This will load the links under plugins.php
			$this->loader->add_filter('plugin_action_links_' . UMBA_base, $wphooks, 'UMBA_settings');
			// This will load the gateway
			add_action('plugins_loaded', 'UMBA_init_gateway');
			// Refresh the checkout gateways when the phone is changed
			$this->loader->add_action( 'wp_footer', $wphooks, 'UMBA_add_update_cart' );
			// Add a message before gateways on checkout page for UMBA
			$this->loader->add_action('woocommerce_review_order_before_payment', $wphooks, 'UMBA_message', 10, 0 );
			// Ajax call to show message when UMBA is not available
			$this->loader->add_action('wp_ajax_umba_hide',  $wphooks, 'UMBA_hide');
			$this->loader->add_action('wp_ajax_nopriv_umba_hide', $wphooks, 'UMBA_hide');
			// Add some logic for the admin settings gateway
			$this->loader->add_action('admin_footer', $wphooks, 'UMBA_admin_footer');		
			// Add our assets for the checkout page
			$this->loader->add_action( 'wp_enqueue_scripts', $wphooks, 'UMBA_enqueue_assets' );
			// Ajax calls for checkout page
			$this->loader->add_action('wp_ajax_umba_check_order_status',  $wphooks, 'UMBA_check_order_status');
			$this->loader->add_action('wp_ajax_nopriv_umba_check_order_status', $wphooks, 'UMBA_check_order_status');	
			// This is for pending payments
			$this->loader->add_action( 'wp_footer', $wphooks, 'UMBA_pending_payment', 99 );
		}

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Bucket_Auth_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new UMBA_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}


}