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
            error_log("APF AJAX: filter_products_callback triggered.");
            error_log("APF AJAX: POST data: " . print_r($_POST, true));

            check_ajax_referer( 'apf_filter_nonce', 'nonce' );
            error_log("APF AJAX: Nonce check passed.");

            $raw_filters_json = isset( $_POST['filters'] ) ? stripslashes( $_POST['filters'] ) : '{}';
            $raw_filters = json_decode( $raw_filters_json, true );
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("APF AJAX: JSON decode error for filters: " . json_last_error_msg());
                wp_send_json_error(array('message' => 'Invalid filter data format.'));
                return;
            }

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
                        $wc_min_price = 0;
                        // Default min to 0, max to a very high number if not set by user.
                        // JS logic should ideally send values only if slider is touched.
                        $current_min_p = ($min_p === null || $min_p === '') ? 0 : floatval($min_p);
                        $current_max_p = ($max_p === null || $max_p === '') ? PHP_INT_MAX : floatval($max_p);

                        if ($current_min_p < 0) $current_min_p = 0;
                        // Ensure max is not less than min, unless max is PHP_INT_MAX (no upper limit)
                        if ($current_max_p < $current_min_p && $current_max_p !== PHP_INT_MAX) {
                             $current_max_p = $current_min_p;
                        }

                        $sanitized_filters['price_range']['min'] = $current_min_p;
                        $sanitized_filters['price_range']['max'] = $current_max_p; // Always set max, even if PHP_INT_MAX
                    }
                }
                // Sanitize Rating
                if (isset($raw_filters['rating']) && !empty($raw_filters['rating'])) {
                     $rating_filters = array_values(array_filter(array_map('absint', (array)$raw_filters['rating']), function($r) { return $r > 0 && $r <= 5; }));
                     if (!empty($rating_filters)) $sanitized_filters['rating'] = $rating_filters;
                }
            }
            $this->active_filters = $sanitized_filters;
            $this->current_page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
            if ($this->current_page < 1) $this->current_page = 1;

            error_log("APF AJAX: Sanitized Filters: " . print_r($this->active_filters, true));
            error_log("APF AJAX: Current Page: " . $this->current_page);

            add_action( 'woocommerce_product_query', array( $this, 'modify_wc_query_action' ) );

            set_query_var( 'paged', $this->current_page );

            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => wc_get_loop_prop( 'posts_per_page', apply_filters( 'loop_shop_per_page', get_option( 'posts_per_page' ) ) ),
                'paged'          => $this->current_page,
            );

            $products_query = new WP_Query( $args );

            error_log("APF AJAX: Final WP_Query args after modify_wc_query_action hook (from \$products_query->query_vars): " . print_r($products_query->query_vars, true));


            wc_set_loop_prop( 'current_page', $this->current_page );
            wc_set_loop_prop( 'is_paginated', $products_query->max_num_pages > 1 );
            wc_set_loop_prop( 'page_template', get_page_template_slug() );
            wc_set_loop_prop( 'per_page', $products_query->get( 'posts_per_page' ) );
            wc_set_loop_prop( 'total', $products_query->found_posts );
            wc_set_loop_prop( 'total_pages', $products_query->max_num_pages );

            global $wp_query;
            $original_wp_query = $wp_query;
            $wp_query = $products_query;

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
            // error_log("APF AJAX: Products HTML length: " . strlen($products_html)); // To check if HTML is generated

            ob_start();
            woocommerce_result_count();
            $result_count_html = ob_get_clean();

            ob_start();
            woocommerce_pagination();
            $pagination_html = ob_get_clean();

            $wp_query = $original_wp_query;
            wp_reset_postdata();

            remove_action( 'woocommerce_product_query', array( $this, 'modify_wc_query_action' ) );

            wp_send_json_success( array(
                'products_html'     => $products_html,
                'pagination_html'   => $pagination_html,
                'result_count_html' => $result_count_html,
                // 'debug_filters' => $this->active_filters // Optionally send back for JS console
            ) );
        }

        public function modify_wc_query_action( $q ) {
            error_log("APF AJAX: modify_wc_query_action called. Initial query vars: " . print_r($q->query_vars, true));
            error_log("APF AJAX: Active filters for query modification: " . print_r($this->active_filters, true));

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
                        'operator' => 'IN',
                    );
                }
            }

            if ( ! empty( $this->active_filters['stock_status'] ) ) {
                $meta_query[] = array(
                    'key'     => '_stock_status',
                    'value'   => (array) $this->active_filters['stock_status'], // Ensure it's an array
                    'compare' => 'IN', // Always use IN for consistency
                );
            }

            // Price Range Filter: Rely on WooCommerce's internal price filter hooks by setting query vars
            if ( isset( $this->active_filters['price_range'] ) ) {
                if (isset($this->active_filters['price_range']['min'])) {
                    $q->set( 'min_price', $this->active_filters['price_range']['min'] );
                }
                if (isset($this->active_filters['price_range']['max']) && $this->active_filters['price_range']['max'] < PHP_INT_MAX) {
                    // Only set max_price if it's not our placeholder for "no upper limit"
                    $q->set( 'max_price', $this->active_filters['price_range']['max'] );
                }
                 // Ensure WooCommerce knows a price filter is active if either min or max is meaningfully set.
                if ( (isset($this->active_filters['price_range']['min']) && $this->active_filters['price_range']['min'] > 0) ||
                     (isset($this->active_filters['price_range']['max']) && $this->active_filters['price_range']['max'] < PHP_INT_MAX) ) {
                    $q->set('price_filter', true);
                }
            }

            if ( ! empty( $this->active_filters['rating'] ) ) {
                $min_rating = min( $this->active_filters['rating'] );
                $meta_query[] = array(
                    'key'     => '_wc_average_rating',
                    'value'   => $min_rating,
                    'compare' => '>=',
                    'type'    => 'DECIMAL(3,2)',
                );
            }

            $numeric_tax_queries = array_filter(array_keys($tax_query), 'is_numeric');
            if (count($numeric_tax_queries) > 1 && !isset($tax_query['relation'])) {
                $tax_query['relation'] = 'AND';
            }

            $numeric_meta_queries = array_filter(array_keys($meta_query), 'is_numeric');
            if (count($numeric_meta_queries) > 1 && !isset($meta_query['relation'])) {
                $meta_query['relation'] = 'AND';
            }

            if (!empty($numeric_tax_queries) ) { // Only set if there are actual tax queries
                 $q->set('tax_query', $tax_query);
            } else {
                 $q->set('tax_query', array());
            }

            if (!empty($numeric_meta_queries) ) { // Only set if there are actual meta queries
                $q->set('meta_query', $meta_query);
            } else {
                $q->set('meta_query', array());
            }
            error_log("APF AJAX: Modified query vars: " . print_r($q->query_vars, true));
        }

    }
