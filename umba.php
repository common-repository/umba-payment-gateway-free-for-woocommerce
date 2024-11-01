<?php
/**
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              umba.com
 * @since             1.5
 * @package           Umba Payment Gateway Free for WooCommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Umba Payment Gateway Free for WooCommerce
 * Plugin URI:        https://umba.com
 * Description:       Umba is payment gateway for WooCommerce allowing you to receive payments via Umba Loan.
 * Version:           1.5
 * Author:            Umba
 * Author URI:        https://umba.com
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       woocommerce-umba
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Include plugin actication
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

// The plugins folder path
define('UMBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
define('UMBA_ASSETS_DIR', plugins_url('assets/', __FILE__));
define('UMBA_base', plugin_basename( __FILE__ ));
define('UMBA_MINIFY', true); // set to false if you want to change javascript/css
define('UMBA_CSS_JS_VERSION', '1.0');
define('UMBA_debug', false);
define('UMBA_ipn_key','https://www.ipayafrica.com/ipn?');
define('UMBA_supported_currencies', serialize(array('KES')));
define('UMBA_url_key', 'https://payments.ipayafrica.com/v3/ke');
define('UMBA_gateway_api', 'https://www.mkopokaka.com/api/v1/order/limit');
define('UMBA_gateway_api_staging', 'https://stage.mkopokaka.com/api/v1/order/limit');
define('UMBA_gateway_order_api','https://www.mkopokaka.com/api/v1/order/');
define('UMBA_gateway_order_api_staging','https://stage.mkopokaka.com/api/v1/order/');
define('UMBA_countries', serialize(array('ke' => 'Kenya')));
define('UMBA_allowed_currencies', serialize(array('KES')));
define('UMBA_dev_docs', 'https://dev.ipayafrica.com/');
define('UMBA_hash_key', '4uqUXsg99g^S9#jZ');
define('UMBA_checkout_timeout', '30000');
define('UMBA_gateway_key', 'umba');
// Description and instructions
define('UMBA_gateway_description', __('Payment method description that the customer will see on your checkout.','woocommerce-umba'));
define('UMBA_gateway_description_2', __('You can pay in four installments of %s KES over four weeks','woocommerce-umba'));
define('UMBA_gateway_instruction', __('Instructions that will be added to the thank you page and emails.','woocommerce-umba'));
define('UMBA_zero_percent',serialize(array('omaar','tbspink')));

// Get current plugin version
$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
$plugin_version = $plugin_data['Version'];
define('UMBA_current_version', $plugin_version );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/umba-init.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/umba-activator.php
 */
function UMBA_activate() {
	ob_start();
	require_once plugin_dir_path( __FILE__ ) . 'includes/umba-activator.php';
	UMBA_Activator::activate();
	ob_end_clean();
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/umba-deactivator.php
 */
function UMBA_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/umba-deactivator.php';
	UMBA_Deactivator::deactivate();
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.1
 */
function UMBA_run() {
	$plugin = new UMBA_init();
	$plugin->run();
}
register_activation_hook( __FILE__, 'UMBA_activate');
register_deactivation_hook( __FILE__, 'UMBA_deactivate' );
UMBA_run();