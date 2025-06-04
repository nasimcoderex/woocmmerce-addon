<?php
    /**
     * Handles AJAX requests for Advanced Product Filters.
     *
     * @package Advanced_Product_Filters
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly.
    }

    /**
     * APF_Ajax_Handler Class.
     */
    class APF_Ajax_Handler {

        /**
         * The single instance of the class.
         * @var APF_Ajax_Handler
         */
        protected static $_instance = null;

        private $active_filters = array();
        private $current_page = 1;

        /**
         * Main APF_Ajax_Handler Instance.
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
            // Register AJAX actions
            add_action( 'wp_ajax_apf_filter_products', array( $this, 'filter_products_callback' ) );
            add_action( 'wp_ajax_nopriv_apf_filter_products', array( $this, 'filter_products_callback' ) );
        }

        /**
         * Callback for the apf_filter_products AJAX action.
         * Handles product filtering and returns updated product list and pagination.
         */
        public function filter_products_callback() {
            check_ajax_referer( 'apf_filter_nonce', 'nonce' );

            $raw_filters = isset( $_POST['filters'] ) ? json_decode( stripslashes( $_POST['filters'] ), true ) : array();

            $sanitized_filters = array();
            if (!empty($raw_filters) && is_array($raw_filters)) {
                // Sanitize Category filters
                if (isset($raw_filters['category']) && !empty($raw_filters['category'])) {
                    $category_filters = array_values(array_filter(array_map('absint', (array)$raw_filters['category']), function($id){ return $id > 0; }));
                    if (!empty($category_filters)) $sanitized_filters['category'] = $category_filters;
                }
                // Sanitize Tag filters
                if (isset($raw_filters['tag']) && !empty($raw_filters['tag'])) {
                    $tag_filters = array_values(array_filter(array_map('absint', (array)$raw_filters['tag']), function($id){ return $id > 0; }));
                    if (!empty($tag_filters)) $sanitized_filters['tag'] = $tag_filters;
                }
                // Sanitize Attribute filters (pa_*)
                foreach ($raw_filters as $key => $value) {
                    if (strpos($key, 'pa_') === 0 && !empty($value)) {
                        $s_key = sanitize_key($key);
                        $attribute_values = array_values(array_filter(array_map('absint', (array)$value), function($id){ return $id > 0; }));
                        if (!empty($attribute_values)) $sanitized_filters[$s_key] = $attribute_values;
                    }
                }
                // Sanitize Stock Status
                if (isset($raw_filters['stock_status']) && !empty($raw_filters['stock_status'])) {
                    $allowed_stock_statuses = array('instock', 'outofstock');
                    $stock_status_filters = array_values(array_filter(array_map('sanitize_key', (array)$raw_filters['stock_status']), function($status) use ($allowed_stock_statuses) {
                        return in_array($status, $allowed_stock_statuses, true);
                    }));
                    if (!empty($stock_status_filters)) $sanitized_filters['stock_status'] = $stock_status_filters;
                }
                // Sanitize Price Range
                if (isset($raw_filters['price_range']) && is_array($raw_filters['price_range'])) {
                    $min_p = isset($raw_filters['price_range']['min']) ? floatval($raw_filters['price_range']['min']) : null;
                    $max_p = isset($raw_filters['price_range']['max']) ? floatval($raw_filters['price_range']['max']) : null;

                    if ($min_p !== null || $max_p !== null) {
                        // Get WooCommerce min/max price limits to ensure submitted values are not out of global bounds
                        // This is a simplified way; a more robust way might involve querying actual min/max of all products.
                        $wc_min_price = 0;
                        // $wc_max_price = a very large number or dynamically queried max product price.
                        // For now, just basic validation.

                        $current_min_p = ($min_p === null) ? $wc_min_price : $min_p;
                        $current_max_p = ($max_p === null) ? null : $max_p; // null can mean no upper limit for WC query

                        if ($current_min_p < 0) $current_min_p = 0;
                        if ($current_max_p !== null && $current_max_p < $current_min_p) $current_max_p = $current_min_p;

                        $sanitized_filters['price_range']['min'] = $current_min_p;
                        if ($current_max_p !== null) { // Only set max_price if it's not "no upper limit"
                           $sanitized_filters['price_range']['max'] = $current_max_p;
                        }
                    }
                }
                // Sanitize Rating
                if (isset($raw_filters['rating']) && !empty($raw_filters['rating'])) {
                     $rating_filters = array_values(array_filter(array_map('absint', (array)$raw_filters['rating']), function($r) { return $r > 0 && $r <= 5; }));
                     if (!empty($rating_filters)) $sanitized_filters['rating'] = $rating_filters; // Store as array
                }
            }
            $this->active_filters = $sanitized_filters;
            $this->current_page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
            if ($this->current_page < 1) $this->current_page = 1;

            add_action( 'woocommerce_product_query', array( $this, 'modify_wc_query_action' ) );

            // Set 'paged' for the main query, WC will use it
            set_query_var( 'paged', $this->current_page );

            // Get the standard WooCommerce query args to ensure consistency
            // This is important if themes or other plugins modify the main shop query.
            // However, for a direct AJAX call, we might construct args more directly.
            // For now, let's stick to a new WP_Query and use wc_set_loop_prop.

            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => wc_get_loop_prop( 'posts_per_page', apply_filters( 'loop_shop_per_page', get_option( 'posts_per_page' ) ) ),
                'paged'          => $this->current_page,
                // 'wc_query'       => 'product_query', // This is used by WC to identify the main query, not strictly needed for new WP_Query here
                                                   // but our hook 'woocommerce_product_query' implies we are modifying a WC main query.
                                                   // For AJAX, it's cleaner to build the query and then display results.
            );

            $products_query = new WP_Query( $args );

            // Set WooCommerce loop properties for the new query
            // These are important for woocommerce_pagination() and woocommerce_result_count()
            wc_set_loop_prop( 'current_page', $this->current_page );
            wc_set_loop_prop( 'is_paginated', $products_query->max_num_pages > 1 );
            wc_set_loop_prop( 'page_template', get_page_template_slug() );
            wc_set_loop_prop( 'per_page', $products_query->get( 'posts_per_page' ) );
            wc_set_loop_prop( 'total', $products_query->found_posts );
            wc_set_loop_prop( 'total_pages', $products_query->max_num_pages );

            // For functions like woocommerce_result_count() to work with a custom query,
            // we might need to temporarily set the global $wp_query.
            global $wp_query;
            $original_wp_query = $wp_query; // Backup original query
            $wp_query = $products_query; // Set global $wp_query to our custom query

            ob_start();
            if ( $products_query->have_posts() ) {
                woocommerce_product_loop_start();
                while ( $products_query->have_posts() ) {
                    $products_query->the_post();
                    wc_get_template_part( 'content', 'product' );
                }
                woocommerce_product_loop_end();
            } else {
                wc_no_products_found();
            }
            $products_html = ob_get_clean();

            ob_start();
            woocommerce_result_count();
            $result_count_html = ob_get_clean();

            ob_start();
            woocommerce_pagination();
            $pagination_html = ob_get_clean();

            $wp_query = $original_wp_query; // Restore original query
            wp_reset_postdata();

            remove_action( 'woocommerce_product_query', array( $this, 'modify_wc_query_action' ) );

            wp_send_json_success( array(
                'products_html'     => $products_html,
                'pagination_html'   => $pagination_html,
                'result_count_html' => $result_count_html,
            ) );
        }

        public function modify_wc_query_action( $q ) {
            // Ensure we are modifying the main query if this hook is used more broadly,
            // but for this AJAX context, $q is our new WP_Query object's query vars.
            // if ( ! $q->is_main_query() ) return; // Not strictly needed here as we pass $q from new WP_Query

            $tax_query = $q->get('tax_query');
            if (!is_array($tax_query)) $tax_query = array();

            $meta_query = $q->get('meta_query');
            if (!is_array($meta_query)) $meta_query = array();


            if ( ! empty( $this->active_filters['category'] ) ) {
                $tax_query[] = array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $this->active_filters['category'],
                    'operator' => 'IN',
                );
            }

            if ( ! empty( $this->active_filters['tag'] ) ) {
                $tax_query[] = array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'term_id',
                    'terms'    => $this->active_filters['tag'],
                    'operator' => 'IN',
                );
            }

            foreach ( $this->active_filters as $key => $values ) {
                if ( strpos( $key, 'pa_' ) === 0 && !empty($values) ) {
                    $tax_query[] = array(
                        'taxonomy' => $key,
                        'field'    => 'term_id',
                        'terms'    => $values,
                        'operator' => 'IN', // TODO: Make operator configurable (AND/OR) per attribute in admin
                    );
                }
            }

            if ( ! empty( $this->active_filters['stock_status'] ) ) {
                // Assuming single stock status selection or that WC handles array of stock statuses.
                // If multiple selected, 'compare' should be 'IN'. Here, we assume one or all.
                // For simplicity, if 'outofstock' is selected, we show only outofstock.
                // If 'instock' is selected, we show only instock.
                // If both, it's like no filter unless handled by specific logic.
                // WC typically handles 'instock' by default if 'hide_out_of_stock_items' is set.
                // This explicit filter is for user choice.
                $meta_query[] = array(
                    'key'     => '_stock_status',
                    'value'   => $this->active_filters['stock_status'], // This should be an array if multiple are allowed from JS
                    'compare' => is_array($this->active_filters['stock_status']) ? 'IN' : '=',
                );
            }

            if ( isset( $this->active_filters['price_range'] ) ) {
                // WooCommerce's price filter hook 'woocommerce_product_query_price_filter' handles this
                // by looking at _min_price and _max_price query vars.
                $price_meta_query = array(
                    'key' => '_price',
                    'type' => 'DECIMAL(10,2)', // Ensure numeric comparison
                    'compare' => 'BETWEEN',
                    'value' => array( $this->active_filters['price_range']['min'], $this->active_filters['price_range']['max'] )
                );
                 // Check if a price meta query already exists to avoid conflicts (e.g. from WC itself)
                $price_key_exists = false;
                foreach($meta_query as $mq_item){
                    if(isset($mq_item['key']) && $mq_item['key'] === '_price'){
                        $price_key_exists = true;
                        break;
                    }
                }
                if(!$price_key_exists){
                    $meta_query[] = $price_meta_query;
                } else {
                    // If price filter is already set by WC (e.g. via shortcode attributes),
                    // we might need to decide whether to override or merge.
                    // For now, we assume direct control via our AJAX.
                    // To ensure our filter takes precedence if WC also adds one based on URL:
                    $q->set('min_price', $this->active_filters['price_range']['min']);
                    $q->set('max_price', $this->active_filters['price_range']['max']);
                }
            }

            if ( ! empty( $this->active_filters['rating'] ) ) {
                // Assuming rating filter means "at least X stars".
                // If multiple ratings selected (e.g. "3 stars & up" AND "4 stars & up"), take the lowest.
                $min_rating = min( $this->active_filters['rating'] );
                $meta_query[] = array(
                    'key'     => '_wc_average_rating', // Stored as meta
                    'value'   => $min_rating,
                    'compare' => '>=',
                    'type'    => 'DECIMAL(3,2)',
                );
                 // WooCommerce also adds its own rating filter if `rating_filter` is in tax_query.
                 // To avoid conflict, we use meta_query directly.
            }

            // Set 'relation' for tax_query if multiple taxonomies are queried
            if (count(array_filter(array_keys($tax_query), 'is_numeric')) > 1 && !isset($tax_query['relation'])) {
                $tax_query['relation'] = 'AND'; // TODO: Make this configurable in admin (AND/OR for different taxonomies)
            }

            // Set 'relation' for meta_query if multiple meta conditions are added
            if (count(array_filter(array_keys($meta_query), 'is_numeric')) > 1 && !isset($meta_query['relation'])) {
                $meta_query['relation'] = 'AND';
            }

            if (!empty($tax_query) && count($tax_query) > (isset($tax_query['relation']) ? 1:0) ) {
                 $q->set('tax_query', $tax_query);
            } else {
                 $q->set('tax_query', array()); // Clear if only relation is set
            }

            if (!empty($meta_query) && count($meta_query) > (isset($meta_query['relation']) ? 1:0) ) {
                $q->set('meta_query', $meta_query);
            } else {
                $q->set('meta_query', array()); // Clear if only relation is set
            }
        }

    }
