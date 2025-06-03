<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WooCommerce_Addon_Product_Filter_Admin {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add admin menu page.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Product Filter Settings', 'woocommerce-addon-product-filter' ),
            __( 'Product Filter', 'woocommerce-addon-product-filter' ),
            'manage_woocommerce',
            'wc-addon-product-filter-settings',
            array( $this, 'settings_page_html' )
        );
    }

    /**
     * Render settings page HTML.
     */
    public function settings_page_html() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'woocommerce_addon_product_filter_settings_group' );
                do_settings_sections( 'wc-addon-product-filter-settings' );
                submit_button( __( 'Save Settings', 'woocommerce-addon-product-filter' ) );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting(
            'woocommerce_addon_product_filter_settings_group',
            'woocommerce_addon_product_filter_enable',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            )
        );

        add_settings_section(
            'woocommerce_addon_product_filter_general_section',
            __( 'General Settings', 'woocommerce-addon-product-filter' ),
            null, // No callback needed for the section description
            'wc-addon-product-filter-settings'
        );

        add_settings_field(
            'woocommerce_addon_product_filter_enable_field',
            __( 'Enable Product Filter', 'woocommerce-addon-product-filter' ),
            array( $this, 'render_enable_checkbox_field' ),
            'wc-addon-product-filter-settings',
            'woocommerce_addon_product_filter_general_section'
        );
    }

    /**
     * Render the enable checkbox field.
     */
    public function render_enable_checkbox_field() {
        $option = get_option( 'woocommerce_addon_product_filter_enable' );
        ?>
        <label for="woocommerce_addon_product_filter_enable">
            <input type="checkbox"
                   name="woocommerce_addon_product_filter_enable"
                   id="woocommerce_addon_product_filter_enable"
                   value="1"
                   <?php checked( $option, 1 ); ?> />
            <?php esc_html_e( 'Enable Product Filter', 'woocommerce-addon-product-filter' ); ?>
        </label>
        <?php
    }
}
