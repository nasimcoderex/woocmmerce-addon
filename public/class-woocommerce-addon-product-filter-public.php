<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WooCommerce_Addon_Product_Filter_Public {

    private $plugin_version = '0.1.0'; // Define plugin version

    /**
     * Constructor.
     */
    public function __construct() {
        if ( get_option( 'woocommerce_addon_product_filter_enable' ) ) {
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
            add_action( 'woocommerce_before_shop_loop', array( $this, 'display_product_filters' ) );
            add_action( 'woocommerce_product_query', array( $this, 'filter_products_by_category' ) );
        }
    }

    /**
     * Enqueue public-facing stylesheets.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'woocommerce-addon-product-filter-public-style',
            plugin_dir_url( __FILE__ ) . 'css/woocommerce-addon-product-filter-public.css',
            array(),
            $this->plugin_version
        );
    }

    /**
     * Display product category filters on the shop page.
     */
    public function display_product_filters() {
        if ( ! get_option( 'woocommerce_addon_product_filter_enable' ) ) {
            return;
        }

        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
        ) );

        if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
            $shop_page_url = get_permalink( wc_get_page_id( 'shop' ) );
            $current_filter = isset( $_GET['filter_product_cat'] ) ? sanitize_text_field( $_GET['filter_product_cat'] ) : '';

            echo '<div class="product-filters-wrapper">'; // Main wrapper for all filter UI
            echo '<h3>' . esc_html__( 'Filter by Category', 'woocommerce-addon-product-filter' ) . '</h3>';
            echo '<div class="product-filters-container">'; // Container for the filter items themselves
            echo '<ul class="product-category-filters">';
            foreach ( $categories as $category ) {
                $filter_url = add_query_arg( 'filter_product_cat', $category->slug, $shop_page_url );
                $active_class = ( $current_filter === $category->slug ) ? 'active-filter' : '';
                echo '<li class="' . esc_attr( $active_class ) . '"><a href="' . esc_url( $filter_url ) . '">' . esc_html( $category->name ) . '</a></li>';
            }
            echo '</ul>';
            echo '</div>'; // End .product-filters-container

            if ( ! empty( $current_filter ) ) {
                echo '<a href="' . esc_url( $shop_page_url ) . '" class="clear-filter-link">' . esc_html__( 'Clear Filter', 'woocommerce-addon-product-filter' ) . '</a>';
            }
            echo '</div>'; // End .product-filters-wrapper
        }
    }

    /**
     * Filter products by category based on the query parameter.
     *
     * @param WP_Query $q The main WooCommerce product query.
     */
    public function filter_products_by_category( $q ) {
        if ( ! get_option( 'woocommerce_addon_product_filter_enable' ) ) {
            return;
        }

        if ( isset( $_GET['filter_product_cat'] ) && ! empty( $_GET['filter_product_cat'] ) ) {
            $category_slug = sanitize_text_field( $_GET['filter_product_cat'] );

            $tax_query = (array) $q->get( 'tax_query' );
            // Ensure we don't duplicate the tax query if already set by another part of WC or a plugin.
            // This basic check might need to be more robust in a complex setup.
            $category_filter_exists = false;
            foreach ($tax_query as $query_part) {
                if (isset($query_part['taxonomy']) && $query_part['taxonomy'] === 'product_cat' && isset($query_part['field']) && $query_part['field'] === 'slug' && isset($query_part['terms']) && $query_part['terms'] === $category_slug) {
                    $category_filter_exists = true;
                    break;
                }
            }

            if (!$category_filter_exists) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category_slug,
                );
                $q->set( 'tax_query', $tax_query );
            }
        }
    }
}
