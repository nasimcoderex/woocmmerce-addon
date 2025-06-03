<?php
/**
 * Plugin Name:       WooCommerce Addon Product Filter
 * Plugin URI:
 * Description:       Adds product filtering functionality to WooCommerce.
 * Version:           0.1.0
 * Author:            Your Name/Company
 * Author URI:
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-addon-product-filter
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Activation hook.
 */
function woocommerce_addon_product_filter_activate() {
    // Activation code here.
}
register_activation_hook( __FILE__, 'woocommerce_addon_product_filter_activate' );

/**
 * Deactivation hook.
 */
function woocommerce_addon_product_filter_deactivate() {
    // Deactivation code here.
}
register_deactivation_hook( __FILE__, 'woocommerce_addon_product_filter_deactivate' );

if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'admin/class-woocommerce-addon-product-filter-admin.php';
    new WooCommerce_Addon_Product_Filter_Admin();
}

// Public facing functionality
require_once plugin_dir_path( __FILE__ ) . 'public/class-woocommerce-addon-product-filter-public.php';
new WooCommerce_Addon_Product_Filter_Public();
