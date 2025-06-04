<?php
/**
 * Admin Settings for Advanced Product Filters.
 *
 * @package Advanced_Product_Filters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * APF_Admin_Settings Class.
 */
class APF_Admin_Settings {

	/**
	 * The single instance of the class.
	 * @var APF_Admin_Settings
	 */
	protected static $_instance = null;

	/**
	 * Option group.
	 * @var string
	 */
	private $option_group = 'apf_settings';

	/**
	 * Option name.
	 * @var string
	 */
	private $option_name = 'apf_options';


	/**
	 * Main APF_Admin_Settings Instance.
	 * Ensures only one instance of APF_Admin_Settings is loaded or can be loaded.
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        // Only load on our admin page
        // The hook_suffix for a submenu page is 'woocommerce_page_apf-settings' (parent_slug_page_menu_slug)
        if ( 'woocommerce_page_apf-settings' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'apf-admin-styles',
            APF_PLUGIN_URL . 'assets/css/apf-admin-styles.css',
            array(),
            APF_VERSION
        );

        wp_enqueue_script(
            'apf-admin-scripts',
            APF_PLUGIN_URL . 'assets/js/apf-admin-scripts.js',
            array( 'jquery', 'jquery-ui-sortable' ), // Added jquery-ui-sortable
            APF_VERSION,
            true // Load in footer
        );
    }

	/**
	 * Add admin menu item.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce', // Parent slug
			__( 'Advanced Product Filters', 'advanced-product-filters' ), // Page title
			__( 'Product Filters', 'advanced-product-filters' ), // Menu title
			'manage_woocommerce', // Capability
			'apf-settings', // Menu slug
			array( $this, 'settings_page_html' ) // Function to display the page
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			$this->option_group, // Option group
			$this->option_name,  // Option name
			array( $this, 'sanitize_settings' ) // Sanitize callback
		);

		// General Settings Section
		add_settings_section(
			'apf_general_section', // ID
			__( 'General Settings', 'advanced-product-filters' ), // Title
			null, // Callback
			$this->option_group // Page
		);

		add_settings_field(
			'enable_filters', // ID
			__( 'Enable Product Filters', 'advanced-product-filters' ), // Title
			array( $this, 'render_enable_filters_field' ), // Callback
			$this->option_group, // Page
			'apf_general_section' // Section
		);

		// Filter Configuration Section
        add_settings_section(
            'apf_filter_config_section', // ID
            __( 'Configure Filters', 'advanced-product-filters' ), // Title
            array( $this, 'filter_config_section_callback' ), // Callback
            $this->option_group // Page
        );

        add_settings_field(
            'filter_types', // ID
            __( 'Enable Filter Types', 'advanced-product-filters' ), // Title
            array( $this, 'render_filter_types_field' ), // Callback
            $this->option_group, // Page
            'apf_filter_config_section' // Section
        );

        // Display Settings Section
        add_settings_section(
            'apf_display_settings_section', // ID
            __( 'Display Settings', 'advanced-product-filters' ), // Title
            null, // Callback
            $this->option_group // Page
        );

        add_settings_field(
            'filter_display_position', // ID
            __( 'Filter Display Position', 'advanced-product-filters' ), // Title
            array( $this, 'render_filter_display_position_field' ), // Callback
            $this->option_group, // Page
            'apf_display_settings_section' // Section
        );

        // Filter Order Section
        add_settings_section(
            'apf_filter_order_section', // ID
            __( 'Filter Order', 'advanced-product-filters' ), // Title
            array( $this, 'filter_order_section_callback' ), // Callback
            $this->option_group // Page
        );

        add_settings_field(
            'filter_order', // ID
            __( 'Drag and Drop to Reorder Filters', 'advanced-product-filters' ), // Title
            array( $this, 'render_filter_order_field' ), // Callback
            $this->option_group, // Page
            'apf_filter_order_section' // Section
        );

	}

	/**
	 * Render the HTML for the settings page.
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
				settings_fields( $this->option_group );
				do_settings_sections( $this->option_group );
				submit_button( __( 'Save Settings', 'advanced-product-filters' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize settings.
	 * @param array $input Settings input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized_input = array();

		if ( isset( $input['enable_filters'] ) ) {
			$sanitized_input['enable_filters'] = absint( $input['enable_filters'] );
		} else {
			$sanitized_input['enable_filters'] = 0;
		}

        $allowed_filter_keys = array('category', 'tag', 'attribute', 'price_range', 'rating', 'stock_status');

        // Sanitize filter_types (which ones are enabled)
        $sanitized_input['filter_types'] = array();
        if ( isset( $input['filter_types'] ) && is_array( $input['filter_types'] ) ) {
            foreach ( $input['filter_types'] as $key => $value ) {
                if ( in_array( $key, $allowed_filter_keys, true ) ) {
                    $sanitized_input['filter_types'][ sanitize_key( $key ) ] = absint( $value );
                }
            }
        }

        // Sanitize filter_configs (display types for each enabled filter)
        $sanitized_input['filter_configs'] = array();
        if ( isset( $input['filter_configs'] ) && is_array( $input['filter_configs'] ) ) {
            foreach ( $input['filter_configs'] as $filter_key => $config ) {
                if ( !in_array( $filter_key, $allowed_filter_keys, true ) ) {
                    continue;
                }

                $sanitized_config = array();
                if ( isset( $config['display_type'] ) ) {
                    $display_type = sanitize_text_field( $config['display_type'] );
                    // Validate display_type based on filter_key
                    $valid_display_types = array();
                    switch ( $filter_key ) {
                        case 'category':
                        case 'tag':
                        case 'attribute':
                        case 'stock_status':
                            $valid_display_types = array( 'checkbox', 'dropdown' );
                            break;
                        case 'price_range':
                            $valid_display_types = array( 'slider' );
                            break;
                        case 'rating':
                            $valid_display_types = array( 'checkbox' ); // 'dropdown' could be added
                            break;
                    }

                    if ( in_array( $display_type, $valid_display_types, true ) ) {
                        $sanitized_config['display_type'] = $display_type;
                    } else if ( !empty($valid_display_types) ) {
                        $sanitized_config['display_type'] = $valid_display_types[0]; // Default to first valid if invalid submitted
                    }
                }
                 // If filter_key is price_range, force display_type to slider
                if ($filter_key === 'price_range') {
                    $sanitized_config['display_type'] = 'slider';
                }


                if (!empty($sanitized_config)) {
                    $sanitized_input['filter_configs'][ sanitize_key( $filter_key ) ] = $sanitized_config;
                }
            }
        }

        // Sanitize display position
        if ( isset( $input['filter_display_position'] ) ) {
            $allowed_positions = array( 'sidebar', 'above_grid', 'shortcode' );
            if ( in_array( $input['filter_display_position'], $allowed_positions, true ) ) {
                $sanitized_input['filter_display_position'] = sanitize_text_field( $input['filter_display_position'] );
            } else {
                $sanitized_input['filter_display_position'] = 'sidebar'; // Default
            }
        } else {
            $sanitized_input['filter_display_position'] = 'sidebar'; // Default
        }

        // Sanitize filter_order
        if ( isset( $input['filter_order'] ) && is_array( $input['filter_order'] ) ) {
            $all_defined_filter_keys = array_keys( $this->get_all_filter_types_labels() );
            $sanitized_filter_order = array();

            // Determine currently enabled filters based on the already sanitized 'filter_types'
            $enabled_filter_keys_from_sanitized_types = array();
            if (isset($sanitized_input['filter_types']) && is_array($sanitized_input['filter_types'])) {
                 $enabled_filter_keys_from_sanitized_types = array_keys(array_filter($sanitized_input['filter_types']));
            }

            foreach ( $input['filter_order'] as $filter_key ) {
                $filter_key = sanitize_key( $filter_key );
                // A filter must be defined in our system AND enabled in the current settings submission to be part of the order
                if ( in_array( $filter_key, $all_defined_filter_keys, true ) &&
                     in_array( $filter_key, $enabled_filter_keys_from_sanitized_types, true ) ) {
                    $sanitized_filter_order[] = $filter_key;
                }
            }
            // Ensure all enabled filters are in the order list. If any are missing (e.g. newly enabled), add them to the end.
            foreach($enabled_filter_keys_from_sanitized_types as $enabled_key) {
                if (!in_array($enabled_key, $sanitized_filter_order, true)) {
                    $sanitized_filter_order[] = $enabled_key;
                }
            }
            $sanitized_input['filter_order'] = array_unique( $sanitized_filter_order );
        } else {
            // If not set, try to build from enabled filters if available
            if (isset($sanitized_input['filter_types']) && is_array($sanitized_input['filter_types'])) {
                $sanitized_input['filter_order'] = array_keys(array_filter($sanitized_input['filter_types']));
            } else {
                $sanitized_input['filter_order'] = array();
            }
        }


		// Add more sanitization rules for other fields as they are added
		return $sanitized_input;
	}

	/**
	 * Render enable_filters field.
	 */
	public function render_enable_filters_field() {
		$options = get_option( $this->option_name, array() );
		$value = isset( $options['enable_filters'] ) ? $options['enable_filters'] : 0;
		?>
		<label for="enable_filters">
			<input type="checkbox" id="enable_filters" name="<?php echo esc_attr( $this->option_name ); ?>[enable_filters]" value="1" <?php checked( $value, 1 ); ?> />
			<?php esc_html_e( 'Activate or deactivate product filters on the shop page.', 'advanced-product-filters' ); ?>
		</label>
		<?php
	}

	/**
     * Callback for the filter configuration section.
     */
    public function filter_config_section_callback() {
        echo '<p>' . esc_html__( 'Choose which filters to enable and configure their display types.', 'advanced-product-filters' ) . '</p>';
    }

    /**
     * Render filter_types field.
     */
    public function render_filter_types_field() {
        $options = get_option( $this->option_name, array() );
        $current_values = isset( $options['filter_types'] ) ? (array) $options['filter_types'] : array();

        $filter_types = array(
            'category'     => __( 'Product Categories', 'advanced-product-filters' ),
            'tag'          => __( 'Product Tags', 'advanced-product-filters' ),
            'attribute'    => __( 'Attributes (e.g., Color, Size)', 'advanced-product-filters' ),
            'price_range'  => __( 'Price Range', 'advanced-product-filters' ),
            'rating'       => __( 'Ratings', 'advanced-product-filters' ),
            'stock_status' => __( 'Stock Status', 'advanced-product-filters' ),
        );

        foreach ( $filter_types as $key => $label ) {
            $is_filter_enabled = isset( $current_values[ $key ] ) && $current_values[ $key ] == 1;
            ?>
            <div class="filter-type-item">
                <label for="filter_type_<?php echo esc_attr( $key ); ?>">
                    <input type="checkbox"
                           id="filter_type_<?php echo esc_attr( $key ); ?>"
                           name="<?php echo esc_attr( $this->option_name ); ?>[filter_types][<?php echo esc_attr( $key ); ?>]"
                           value="1" <?php checked( $is_filter_enabled, true ); ?>
                           data-filter-key="<?php echo esc_attr( $key ); ?>"
                           class="apf-filter-type-checkbox" />
                    <strong><?php echo esc_html( $label ); ?></strong>
                </label>

                <?php
                // Display options section
                $display_options_id = 'display_options_' . esc_attr( $key );
                // Get current config for this specific filter, not the general $options which is for all settings.
                // $options might be from get_option( $this->option_name, array() );
                // $current_filter_specific_config = isset($options['filter_configs'][$key]) ? $options['filter_configs'][$key] : array();
                // It's better to pull from $options which should contain the saved values including 'filter_configs'
                $filter_configs_from_db = isset( $options['filter_configs'] ) ? $options['filter_configs'] : array();
                $current_config_for_filter = isset( $filter_configs_from_db[ $key ] ) ? $filter_configs_from_db[ $key ] : array();
                $current_display_type = isset( $current_config_for_filter['display_type'] ) ? $current_config_for_filter['display_type'] : '';

                echo '<div class="filter-display-options" id="' . esc_attr( $display_options_id ) . '" style="display: ' . ( $is_filter_enabled ? 'block' : 'none' ) . '; margin-left: 20px; padding-top: 5px;">';
                echo '<em>' . esc_html__( 'Display as:', 'advanced-product-filters' ) . '</em><br/>';

                $possible_display_types = array();
                $default_display_type = '';

                switch ( $key ) {
                    case 'category':
                    case 'tag':
                    case 'attribute': // General attribute setting
                    case 'stock_status':
                        $possible_display_types = array(
                            'checkbox' => __( 'Checkboxes', 'advanced-product-filters' ),
                            'dropdown' => __( 'Dropdown', 'advanced-product-filters' ),
                        );
                        $default_display_type = 'checkbox';
                        break;
                    case 'price_range':
                        $possible_display_types = array(
                            'slider' => __( 'Range Slider', 'advanced-product-filters' ),
                        );
                        $default_display_type = 'slider'; // This is also forced in sanitize
                        break;
                    case 'rating':
                        $possible_display_types = array(
                            'checkbox' => __( 'Checkboxes (e.g., 4+ stars)', 'advanced-product-filters' ),
                            // 'dropdown' => __( 'Dropdown', 'advanced-product-filters' ), // Maybe later
                        );
                        $default_display_type = 'checkbox';
                        break;
                }

                if (empty($current_display_type) && !empty($default_display_type)) {
                    $current_display_type = $default_display_type;
                }


                foreach ( $possible_display_types as $type_key => $type_label ) {
                    $radio_id = 'display_type_' . esc_attr( $key ) . '_' . esc_attr( $type_key );
                    $is_readonly = ( $key === 'price_range' && $type_key === 'slider' ); // Price range slider is typically the only option

                    echo '<label for="' . esc_attr( $radio_id ) . '" style="margin-right: 10px;">';
                    echo '<input type="radio" id="' . esc_attr( $radio_id ) . '"
                           name="' . esc_attr( $this->option_name ) . '[filter_configs][' . esc_attr( $key ) . '][display_type]"
                           value="' . esc_attr( $type_key ) . '" ' . checked( $current_display_type, $type_key, false ) . '
                           ' . ( $is_readonly ? 'onclick="return false;"' : '' ) . '/>'; // Use onclick for readonly on radio
                    echo esc_html( $type_label );
                    echo '</label> ';
                }
                echo '</div>'; // end .filter-display-options
                ?>
            </div><hr style="margin-top:10px; margin-bottom:10px; border-top: 1px solid #eee; border-bottom: none;"/>
            <?php
        }
    }

    /**
     * Callback for the filter order section.
     */
    public function filter_order_section_callback() {
        echo '<p>' . esc_html__( 'Arrange the order in which enabled filters will appear on the frontend. Only enabled filters are shown here and can be ordered.', 'advanced-product-filters' ) . '</p>';
    }

    /**
     * Render filter_order field.
     */
    public function render_filter_order_field() {
        $options = get_option( $this->option_name, array() );
        $enabled_filters_map = isset( $options['filter_types'] ) ? array_filter( $options['filter_types'] ) : array(); // array_filter to get only enabled (value=1)
        $enabled_filter_keys = array_keys($enabled_filters_map);

        $saved_order = isset( $options['filter_order'] ) && is_array( $options['filter_order'] ) ? $options['filter_order'] : array();

        // Ensure saved_order only contains currently enabled filters, maintaining their relative order.
        $actual_order = array_intersect( $saved_order, $enabled_filter_keys );

        // Add any newly enabled filters (not yet in saved_order) to the end of the list.
        $newly_enabled_filters = array_diff( $enabled_filter_keys, $actual_order );
        foreach ( $newly_enabled_filters as $filter_key ) {
            $actual_order[] = $filter_key;
        }
        // If actual_order is empty and there are enabled filters (e.g. first time, or after disabling all then re-enabling), populate with enabled filters.
        if (empty($actual_order) && !empty($enabled_filter_keys)) {
            $actual_order = $enabled_filter_keys;
        }


        $all_filter_labels = $this->get_all_filter_types_labels();

        echo '<ul id="apf-sortable-filters" class="apf-sortable-list">';
        if ( ! empty( $actual_order ) ) {
            foreach ( $actual_order as $filter_key ) {
                // Double check if the filter key from actual_order is indeed in our defined labels and was considered enabled.
                if ( isset( $all_filter_labels[ $filter_key ] ) && in_array( $filter_key, $enabled_filter_keys, true) ) {
                    echo '<li class="ui-state-default">';
                    echo '<span class="dashicons dashicons-move"></span> ';
                    echo esc_html( $all_filter_labels[ $filter_key ] );
                    echo '<input type="hidden" name="' . esc_attr( $this->option_name ) . '[filter_order][]" value="' . esc_attr( $filter_key ) . '" />';
                    echo '</li>';
                }
            }
        } else {
            echo '<li>' . esc_html__( 'No filters enabled or configured for ordering. Enable filters in the "Configure Filters" section above, save, and then reorder them here.', 'advanced-product-filters') . '</li>';
        }
        echo '</ul>';
        echo '<p class="description">' . esc_html__( 'Note: If you enable or disable filters in the "Configure Filters" section, please save the settings. The list for ordering will update on the next page load reflecting these changes.', 'advanced-product-filters' ) . '</p>';
    }

    /**
     * Render filter_display_position field.
     */
    public function render_filter_display_position_field() {
        $options = get_option( $this->option_name, array() );
        $current_value = isset( $options['filter_display_position'] ) ? $options['filter_display_position'] : 'sidebar';

        $positions = array(
            'sidebar'    => __( 'Sidebar (default WooCommerce sidebar area)', 'advanced-product-filters' ),
            'above_grid' => __( 'Above product grid', 'advanced-product-filters' ),
            'shortcode'  => __( 'Shortcode for custom placement', 'advanced-product-filters' ),
        );

        foreach ( $positions as $key => $label ) {
            ?>
            <label for="display_position_<?php echo esc_attr( $key ); ?>">
                <input type="radio"
                       id="display_position_<?php echo esc_attr( $key ); ?>"
                       name="<?php echo esc_attr( $this->option_name ); ?>[filter_display_position]"
                       value="<?php echo esc_attr( $key ); ?>" <?php checked( $current_value, $key ); ?> />
                <?php echo esc_html( $label ); ?>
            </label><br/>
            <?php
        }
    }

    /**
     * Helper method to get all defined filter type keys and their labels.
     * @return array
     */
    private function get_all_filter_types_labels() {
        return array(
            'category'     => __( 'Product Categories', 'advanced-product-filters' ),
            'tag'          => __( 'Product Tags', 'advanced-product-filters' ),
            'attribute'    => __( 'Attributes', 'advanced-product-filters' ), // Keep generic for now
            'price_range'  => __( 'Price Range', 'advanced-product-filters' ),
            'rating'       => __( 'Ratings', 'advanced-product-filters' ),
            'stock_status' => __( 'Stock Status', 'advanced-product-filters' ),
        );
    }

	// More field rendering methods will be added here...
}
