<?php
   /**
    * Dropdown Filter Template
    *
    * @var string $filter_key The key for this filter.
    * @var string $filter_label The display label for this filter.
    * @var array $options Array of options (terms/items) for the filter.
    * @var string $current_selection Currently selected value for this filter.
    * @var string $name_attribute The HTML name attribute for the select field.
    */
   if ( empty( $options ) ) return;

   // Ensure $current_selection is a string
    $current_selection = (string) $current_selection;
   ?>
   <div class="apf-filter-group apf-filter-group-<?php echo esc_attr( $filter_key ); ?> apf-filter-group-dropdown widget woocommerce widget_layered_nav_filters">
       <h4 class="widget-title apf-filter-title"><?php echo esc_html( $filter_label ); ?></h4>
       <select name="<?php echo esc_attr( $name_attribute ); ?>"
               id="apf-<?php echo esc_attr( $filter_key ); ?>-select"
               class="apf-filter-select"
               data-filter-key="<?php echo esc_attr( $filter_key ); ?>">
           <option value=""><?php printf( esc_html__( 'Any %s', 'advanced-product-filters' ), esc_html( strtolower($filter_label) ) ); ?></option>
           <?php foreach ( $options as $option ) : ?>
               <?php
               // Adapt for both term objects and custom arrays
               $option_id = '';
               $option_name = '';
               $option_count = '';

               if (is_object($option) && isset($option->term_id)) { // WordPress Term object
                   $option_id = $option->term_id;
                   $option_name = $option->name;
                   $option_count = $option->count;
               } elseif (is_array($option) && isset($option['value'])) { // Custom array
                   $option_id = $option['value'];
                   $option_name = $option['name'];
                   $option_count = isset($option['count']) ? $option['count'] : '';
               } else {
                   continue; // Skip if option format is not recognized
               }

               $selected = ( (string) $option_id === $current_selection );
               ?>
               <option value="<?php echo esc_attr( $option_id ); ?>" <?php selected( $selected ); ?>>
                   <?php echo esc_html( $option_name ); ?>
                   <?php if ( $option_count !== '' && $filter_key !== 'stock_status' ) : ?>
                       (<?php echo esc_html( $option_count ); ?>)
                   <?php endif; ?>
               </option>
           <?php endforeach; ?>
       </select>
   </div>
