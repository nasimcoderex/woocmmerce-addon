<?php
/**
 * Plugin Name:       Advanced Product Filters for WooCommerce
 * Plugin URI:        https://example.com/plugins/advanced-product-filters/
 * Description:       Adds advanced product filtering capabilities to WooCommerce shop pages.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advanced-product-filters
 * Domain Path:       /languages
 *
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * WC requires at least: 3.0
 * WC tested up to: latest
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define constants
define( 'APF_VERSION', '1.0.0' );
define( 'APF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
final class Advanced_Product_Filters {

	/**
	 * The single instance of the class.
	 *
	 * @var Advanced_Product_Filters
	 */
	protected static $_instance = null;

	/**
	 * Main Advanced_Product_Filters Instance.
	 *
	 * Ensures only one instance of Advanced_Product_Filters is loaded or can be loaded.
	 *
	 * @static
	 * @return Advanced_Product_Filters - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->setup_hooks();
		$this->includes();
		$this->init();
	}

	/**
	 * Hook into actions and filters.
	 */
	private function setup_hooks() {
		// Add hooks here
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	public function includes() {
		// Include files here
		require_once APF_PLUGIN_DIR . 'includes/class-apf-admin-settings.php';
		require_once APF_PLUGIN_DIR . 'includes/class-apf-frontend-filters.php';
		require_once APF_PLUGIN_DIR . 'includes/class-apf-ajax-handler.php';
		// require_once APF_PLUGIN_DIR . 'includes/apf-helper-functions.php';
	}

	/**
	 * Init plugin features.
	 */
	public function init() {
		// Initialize classes here
		if ( is_admin() ) {
		    APF_Admin_Settings::instance();
		}
		APF_Frontend_Filters::instance();
		APF_Ajax_Handler::instance();
	}

	/**
	 * Action to take once all plugins are loaded.
	 * Check for WooCommerce.
	 */
	public function on_plugins_loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_not_active_notice' ) );
			return;
		}
		// WooCommerce is active, continue with plugin initialization.
	}

	/**
	 * Display a notice if WooCommerce is not active.
	 */
	public function woocommerce_not_active_notice() {
		?>
		<div class="error">
			<p><?php _e( 'Advanced Product Filters for WooCommerce requires WooCommerce to be installed and active.', 'advanced-product-filters' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	private function __wakeup() {}
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_advanced_product_filters() {
	return Advanced_Product_Filters::instance();
}
// Get the plugin running.
run_advanced_product_filters();

?>
