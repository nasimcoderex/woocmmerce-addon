<?php
    /**
     * Handles the frontend display of product filters.
     *
     * @package Advanced_Product_Filters
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly.
    }

    /**
     * APF_Frontend_Filters Class.
     */
    class APF_Frontend_Filters {

        /**
         * The single instance of the class.
         * @var APF_Frontend_Filters
         */
        protected static $_instance = null;

        private $options = array();

        /**
         * Main APF_Frontend_Filters Instance.
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
            $this->options = get_option( 'apf_options', array() );

            if ( ! empty( $this->options['enable_filters'] ) && $this->options['enable_filters'] ) {
                $this->init_hooks();
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
            }
        }

        /**
         * Initialize hooks based on display settings.
         */
        private function init_hooks() {
            $display_position = isset( $this->options['filter_display_position'] ) ? $this->options['filter_display_position'] : 'sidebar';

            switch ( $display_position ) {
                case 'above_grid':
                    add_action( 'woocommerce_before_shop_loop', array( $this, 'render_filters_container' ), 25 ); // Default is 30 for result count/order
                    break;
                case 'sidebar':
                    // For now, let's use a common hook. A widget would be better long-term.
                    add_action( 'woocommerce_sidebar', array( $this, 'render_filters_container' ), 10 );
                    break;
                // 'shortcode' case will be handled by the shortcode registration itself.
            }
        }

        /**
         * Enqueue frontend scripts and styles.
         */
        public function enqueue_assets() {
            if ( is_shop() || is_product_category() || is_product_tag() ) { // Only on relevant pages
                wp_enqueue_style(
                    'apf-frontend-styles',
                    APF_PLUGIN_URL . 'assets/css/apf-frontend-styles.css',
                    array(),
                    APF_VERSION
                );

                // Determine if jQuery UI Slider is needed
                $scripts_dependencies = array('jquery');
                $filter_configs = isset($this->options['filter_configs']) ? $this->options['filter_configs'] : array();
                if (isset($this->options['filter_types']['price_range']) && $this->options['filter_types']['price_range']) {
                    if(isset($filter_configs['price_range']['display_type']) && $filter_configs['price_range']['display_type'] === 'slider') {
                        $scripts_dependencies[] = 'jquery-ui-slider';
                    }
                }


                wp_enqueue_script(
                    'apf-frontend-scripts',
                    APF_PLUGIN_URL . 'assets/js/apf-frontend-scripts.js',
                    $scripts_dependencies,
                    APF_VERSION,
                    true
                );

                // Localize script with data like ajaxurl, nonce, initial filter values etc.
                // This will be expanded in the AJAX step.
                wp_localize_script( 'apf-frontend-scripts', 'apf_vars', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'apf_filter_nonce' )
                    // Add other vars as needed, e.g., current selections, labels
                ) );
            }
        }

        /**
         * Main function to render the filters container and individual filters.
         */
        public function render_filters_container() {
            if ( ! ( is_shop() || is_product_category() || is_product_tag() ) ) {
                return; // Only show on main shop/archive pages
            }

            $enabled_filters_from_options = isset( $this->options['filter_types'] ) ? array_filter( (array) $this->options['filter_types'] ) : array();
            $filter_order = isset( $this->options['filter_order'] ) && is_array( $this->options['filter_order'] ) ? $this->options['filter_order'] : array_keys( $enabled_filters_from_options );

            // Ensure filter_order only contains enabled filters and respects their order
            $ordered_enabled_filters = array();
            foreach($filter_order as $filter_key) {
                if(isset($enabled_filters_from_options[$filter_key])) {
                    $ordered_enabled_filters[$filter_key] = $enabled_filters_from_options[$filter_key]; // value is 1 (enabled)
                }
            }
            // Add any enabled filters that might not be in the order array (e.g. newly enabled and not yet saved in order)
            foreach($enabled_filters_from_options as $key => $value){
                if(!isset($ordered_enabled_filters[$key])){
                    $ordered_enabled_filters[$key] = $value;
                }
            }


            if ( empty( $ordered_enabled_filters ) ) {
                return;
            }

            echo '<div id="apf-filters-container" class="apf-filters-container widget woocommerce widget_layered_nav">'; // Added widget classes for basic theme compat
            echo '<h3 class="widget-title apf-filters-title">' . esc_html__( 'Filter Products', 'advanced-product-filters' ) . '</h3>'; // Added widget-title class
            echo '<form id="apf-filter-form" class="apf-filter-form">';

            foreach ( $ordered_enabled_filters as $filter_key => $is_enabled ) {
                // This $is_enabled check is somewhat redundant here because $ordered_enabled_filters should only contain enabled ones.
                // However, keeping it for safety doesn't hurt.
                if ( ! $is_enabled ) continue;

                $filter_config = isset( $this->options['filter_configs'][ $filter_key ] ) ? $this->options['filter_configs'][ $filter_key ] : array();
                $display_type = isset( $filter_config['display_type'] ) ? $filter_config['display_type'] : 'checkbox'; // Default display type

                // Placeholder for calling specific render methods
                $render_method_name = 'render_filter_' . $filter_key;
                // $display_type is handled within specific render methods now
                // $display_type = isset( $filter_config['display_type'] ) ? $filter_config['display_type'] : 'checkbox';

                if ( $filter_key === 'attribute' ) {
                    // The render_filter_attribute method will handle its own display_type fetching from $filter_config
                    $this->render_filter_attribute( $filter_config );
                } else {
                    $render_method_name = 'render_filter_' . $filter_key;
                    if ( method_exists( $this, $render_method_name ) ) {
                        // Pass its specific config (which includes display_type)
                        $this->$render_method_name( $filter_config );
                    } else {
                        // Fallback for non-attribute filters not yet implemented
                        echo '<div class="apf-filter-group apf-filter-group-' . esc_attr( $filter_key ) . ' widget woocommerce widget_layered_nav_filters">';
                        echo '<h4 class="widget-title apf-filter-title">' . esc_html( $this->get_filter_label( $filter_key ) ) . '</h4>';
                        echo '<p>Render logic for ' . esc_html( $filter_key ) . ' coming soon.</p>';
                        echo '</div>';
                    }
                }
            }

            echo '<div class="apf-filter-actions">';
            // echo '<button type="submit" id="apf-apply-filters">' . esc_html__( 'Apply', 'advanced-product-filters' ) . '</button>'; // For non-AJAX or initial
            echo '<button type="button" id="apf-clear-filters" style="display:none;">' . esc_html__( 'Clear All', 'advanced-product-filters' ) . '</button>';
            echo '</div>';

            echo '</form>';
            echo '</div>';
        }

        // Helper to get filter labels (can be expanded)
        private function get_filter_label( $filter_key ) {
            // Consider using the same source as admin settings for consistency if possible, or make this more robust.
            // For now, this is fine for initial setup.
            $admin_settings = class_exists('APF_Admin_Settings') ? APF_Admin_Settings::instance() : null;
            if ($admin_settings && method_exists($admin_settings, 'get_all_filter_types_labels')) {
                 $labels = $admin_settings->get_all_filter_types_labels();
                 if(isset($labels[$filter_key])) {
                     return $labels[$filter_key];
                 }
            }

            // Fallback labels if admin class/method not available or key not found
            $fallback_labels = array(
                'category'     => __( 'Product Categories', 'advanced-product-filters' ),
                'tag'          => __( 'Product Tags', 'advanced-product-filters' ),
                'attribute'    => __( 'Attributes', 'advanced-product-filters' ),
                'price_range'  => __( 'Price Range', 'advanced-product-filters' ),
                'rating'       => __( 'Average Rating', 'advanced-product-filters' ),
                'stock_status' => __( 'Stock Status', 'advanced-product-filters' ),
            );
            return isset( $fallback_labels[ $filter_key ] ) ? $fallback_labels[ $filter_key ] : ucfirst( str_replace( '_', ' ', $filter_key ) );
        }

        // --- Individual Filter Rendering Methods (to be implemented) ---

        // Example: public function render_filter_price_range($display_type, $config) { /* ... */ }

        /**
         * Load a template part.
         *
         * @param string $template_name Name of the template file (without .php).
         * @param array  $data          Data to pass to the template.
         */
        private function load_template( $template_name, $data = array() ) {
            $template_path = APF_PLUGIN_DIR . 'templates/filters/' . $template_name . '.php';

            // Add filter hook for theme overrides
            $template_path = apply_filters('apf_load_template_path', $template_path, $template_name, $data);

            if ( file_exists( $template_path ) ) {
                extract( $data ); // Make $data keys available as variables in the template
                include $template_path;
            } else {
                // Fallback or error message if template not found
                echo '<p>Error: Filter template <code>' . esc_html( $template_name ) . '.php</code> not found at <code>' . esc_html($template_path) . '</code>.</p>';
            }
        }

        /**
         * Get current selections from URL or other sources.
         * This is a placeholder and will be more robust with AJAX and URL handling.
         * For now, it checks basic $_GET parameters.
         */
        private function get_current_selections( $filter_key, $is_multiple = false ) {
            // This needs to align with how AJAX will update selections.
            // For initial load, $_GET might be used if filters are applied via URL.
            // For checkbox (multiple), the name is filter_category[], for dropdown (single) it's filter_category
            $param_name = 'filter_' . $filter_key;
            if ($is_multiple) {
                 // For checkboxes, param name in GET might not have [] depending on submission.
                $value = isset( $_GET[ $param_name ] ) && is_array($_GET[ $param_name ]) ? array_map( 'sanitize_text_field', $_GET[ $param_name ] ) : array();
                return $value;
            } else {
                // For single value like dropdown or specific params like min_price
                if ($filter_key === 'price_range') { // Special handling for price range
                    $min_val = isset($_GET['min_price']) ? sanitize_text_field($_GET['min_price']) : null;
                    $max_val = isset($_GET['max_price']) ? sanitize_text_field($_GET['max_price']) : null;
                    if ($min_val !== null || $max_val !== null) {
                        return ['min' => $min_val, 'max' => $max_val];
                    }
                    return ['min' => null, 'max' => null]; // Ensure array structure for price
                }
                $value = isset( $_GET[ $param_name ] ) ? sanitize_text_field( $_GET[ $param_name ] ) : '';
                return $value;
            }
        }

        /**
         * Render Product Category Filter.
         */
        public function render_filter_category( $config ) {
            $display_type = $config['display_type'] ?? 'checkbox';
            $terms = get_terms( array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
            ) );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                return;
            }

            $filter_key = 'category';
            $current_selections = $this->get_current_selections( $filter_key, $display_type === 'checkbox' );

            $data = array(
                'filter_key'        => $filter_key,
                'filter_label'      => $this->get_filter_label( $filter_key ),
                'options'           => $terms,
                'current_selection' => $current_selections, // Array for checkbox, string for dropdown
                'name_attribute'    => 'filter_category', // Base name, template adds [] if needed
            );

            if ( $display_type === 'dropdown' ) {
                $this->load_template( 'dropdown-filter', $data );
            } else { // Default to checkbox
                $this->load_template( 'checkbox-filter', $data );
            }
        }

        /**
         * Render Price Range Filter.
         */
        public function render_filter_price_range( $config ) {
            // display_type is implicitly 'slider' for price_range as per admin settings, but good to have $config
            global $wpdb;

            // Sub-query to get product IDs that are visible (tax_query, meta_query might be needed from main query)
            // This is a simplified version. For accuracy, it should reflect current product visibility context.
            $product_ids_query = "SELECT DISTINCT ID FROM {$wpdb->posts} p
                                  JOIN {$wpdb->wc_product_meta_lookup} wc_meta ON p.ID = wc_meta.product_id
                                  WHERE p.post_type = 'product' AND p.post_status = 'publish'";
                                  // Add more conditions based on current query if possible (e.g., category)

            // Using WooCommerce's price lookup table is more efficient
            $sql = "
                SELECT MIN( CAST( price_meta.min_price AS DECIMAL(10,2) ) ) as min_price,
                       MAX( CAST( price_meta.max_price AS DECIMAL(10,2) ) ) as max_price
                FROM {$wpdb->wc_product_meta_lookup} AS price_meta
                WHERE price_meta.product_id IN ({$product_ids_query}) AND (price_meta.min_price > 0 OR price_meta.max_price > 0)
            ";
            $min_max_prices = $wpdb->get_row($sql);

            $min_limit = floor(floatval($min_max_prices->min_price ?? 0));
            $max_limit = ceil(floatval($min_max_prices->max_price ?? 1000)); // Default max if none found or all free

            if ($min_limit === $max_limit) { // Don't show slider if all products have the same price or no price data
                if (apply_filters('apf_hide_price_slider_if_min_equals_max', true)) {
                    return;
                }
            }

            $current_selections = $this->get_current_selections( 'price_range' ); // Expects ['min' => val, 'max' => val]
            $current_min = isset( $current_selections['min'] ) && is_numeric($current_selections['min']) ? floatval( $current_selections['min'] ) : $min_limit;
            $current_max = isset( $current_selections['max'] ) && is_numeric($current_selections['max']) ? floatval( $current_selections['max'] ) : $max_limit;

            // Ensure current values are within limits
            $current_min = max( $min_limit, min( $current_min, $max_limit ) );
            $current_max = min( $max_limit, max( $current_max, $min_limit ) );
            if ($current_min > $current_max) { // Swap if min is greater than max
                list($current_min, $current_max) = array($current_max, $current_min);
            }


            $data = array(
                'filter_key'        => 'price_range',
                'filter_label'      => $this->get_filter_label( 'price_range' ),
                'min_price_limit'   => $min_limit,
                'max_price_limit'   => $max_limit,
                'current_min_value' => $current_min,
                'current_max_value' => $current_max,
            );

            $this->load_template( 'price-range-slider', $data );
        }

        /**
         * Render Rating Filter.
         */
        public function render_filter_rating( $config ) {
            $display_type = $config['display_type'] ?? 'checkbox';
            $rating_options = array();
            // Creating "X stars & up" style options
            for ( $i = 5; $i >= 1; $i-- ) {
                // Check if there are products with this rating or higher.
                // This count logic can be expensive. Consider if it's essential for initial display.
                // $count = $this->get_products_rated_or_higher_count($i);
                // if (!apply_filters('apf_show_empty_ratings', false) && $count === 0) {
                //    continue;
                // }

                $rating_options[] = array(
                    'value' => $i, // The value submitted will be the minimum rating
                    'name'  => sprintf( esc_html__( '%s star &amp; up', 'advanced-product-filters' ), number_format_i18n( $i ) ),
                    // 'count' => $count, // Optional count
                );
            }
             // Alternative: individual stars
            // for ( $i = 5; $i >= 1; $i-- ) {
            //     $rating_options[] = array( 'value' => $i, 'name' => sprintf( _n( '%s star', '%s stars', $i, 'advanced-product-filters' ), number_format_i18n( $i ) ) );
            // }


            if ( empty( $rating_options ) ) {
                return;
            }

            $filter_key = 'rating';
            // Rating is usually a "select one or more" if checkboxes, or "select one" if dropdown.
            // "X stars & up" implies that selecting "3 stars & up" includes 4 and 5.
            // However, typical checkbox behavior is additive for distinct options.
            // If we treat "3 stars & up" as a single choice, then name should not be an array for checkboxes.
            // For simplicity with current checkbox template, we'll use array name, but it means user can select "3 stars & up" AND "4 stars & up".
            // This might need a custom template or JS logic if only one "X & up" can be chosen.
            $current_selections = $this->get_current_selections( $filter_key, $display_type === 'checkbox' );


            $data = array(
                'filter_key'        => $filter_key,
                'filter_label'      => $this->get_filter_label( $filter_key ),
                'options'           => $rating_options,
                'current_selection' => $current_selections,
                'name_attribute'    => 'filter_rating', // Becomes filter_rating[] in checkbox template
            );

            if ( $display_type === 'checkbox' ) {
                $this->load_template( 'checkbox-filter', $data );
            } else { // Assuming 'dropdown' is the other option specified in admin for ratings
                 // Although dropdown for "X & up" is less common, handle if configured
                $this->load_template( 'dropdown-filter', $data );
            }
        }

        /**
         * Render Attribute Filters.
         * This method iterates over available product attributes and renders a filter for each.
         */
        private function render_filter_attribute( $attribute_general_config ) {
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            if ( empty( $attribute_taxonomies ) ) {
                return;
            }

            $display_type = $attribute_general_config['display_type'] ?? 'checkbox'; // General display type for all attributes

            foreach ( $attribute_taxonomies as $taxonomy_item ) {
                $taxonomy_name = wc_attribute_taxonomy_name( $taxonomy_item->attribute_name ); // e.g., pa_color

                // TODO: Add a check here if a specific attribute is enabled for filtering via admin settings, once that exists.
                // For now, assume all public attributes with terms are shown.
                // if ( ! $this->is_attribute_enabled_for_filtering( $taxonomy_name ) ) continue;


                $terms = get_terms( array(
                    'taxonomy'   => $taxonomy_name,
                    'hide_empty' => true,
                ) );

                if ( is_wp_error( $terms ) || empty( $terms ) ) {
                    continue; // Skip if no terms or error
                }

                $filter_key = $taxonomy_name; // e.g. pa_color
                $filter_label = wc_attribute_label( $taxonomy_name ); // e.g. Color
                $current_selections = $this->get_current_selections( $filter_key, $display_type === 'checkbox' );
                $name_attribute = 'filter_' . $filter_key; // e.g. filter_pa_color, template adds []

                $data = array(
                    'filter_key'        => $filter_key,
                    'filter_label'      => $filter_label,
                    'options'           => $terms,
                    'current_selection' => $current_selections,
                    'name_attribute'    => $name_attribute,
                );

                if ( $display_type === 'dropdown' ) {
                    $this->load_template( 'dropdown-filter', $data );
                } else { // Default to checkbox
                    $this->load_template( 'checkbox-filter', $data );
                }
            }
        }

        /**
         * Render Product Tag Filter.
         */
        public function render_filter_tag( $config ) {
            $display_type = $config['display_type'] ?? 'checkbox';
            $terms = get_terms( array(
                'taxonomy'   => 'product_tag',
                'hide_empty' => true,
            ) );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                return;
            }

            $filter_key = 'tag';
            $current_selections = $this->get_current_selections( $filter_key, $display_type === 'checkbox' );

            $data = array(
                'filter_key'        => $filter_key,
                'filter_label'      => $this->get_filter_label( $filter_key ),
                'options'           => $terms,
                'current_selection' => $current_selections,
                'name_attribute'    => 'filter_tag',
            );

            if ( $display_type === 'dropdown' ) {
                $this->load_template( 'dropdown-filter', $data );
            } else {
                $this->load_template( 'checkbox-filter', $data );
            }
        }

        /**
         * Render Stock Status Filter.
         */
        public function render_filter_stock_status( $config ) {
            $display_type = $config['display_type'] ?? 'checkbox';
            $stock_statuses = array(
                array( 'value' => 'instock', 'name' => __( 'In Stock', 'advanced-product-filters' ) /*, 'count' => $this->get_stock_count('instock') */ ),
                array( 'value' => 'outofstock', 'name' => __( 'Out of Stock', 'advanced-product-filters' ) /*, 'count' => $this->get_stock_count('outofstock') */ ),
            );
            // Note: Getting accurate counts for stock status efficiently can be complex and might require custom queries.
            // For now, counts are omitted from stock status.

            $filter_key = 'stock_status';
            $current_selections = $this->get_current_selections( $filter_key, $display_type === 'checkbox' );

            $data = array(
                'filter_key'        => $filter_key,
                'filter_label'      => $this->get_filter_label( $filter_key ),
                'options'           => $stock_statuses,
                'current_selection' => $current_selections,
                'name_attribute'    => 'filter_stock_status',
            );

            if ( $display_type === 'dropdown' ) {
                $this->load_template( 'dropdown-filter', $data );
            } else {
                $this->load_template( 'checkbox-filter', $data );
            }
        }

    }
