<?php
   /**
    * Checkbox Filter Template
    *
    * @var string $filter_key The key for this filter (e.g., 'category', 'tag').
    * @var string $filter_label The display label for this filter.
    * @var array $options Array of options (terms/items) for the filter. Each item is an object/array with 'id', 'name', 'count'.
    * @var array $current_selection Array of currently selected values for this filter.
    * @var string $name_attribute The HTML name attribute for the input fields.
    */
   if ( empty( $options ) ) return;

   // Ensure $current_selection is an array
   $current_selection = is_array($current_selection) ? $current_selection : array();
   ?>
   <div class="apf-filter-group apf-filter-group-<?php echo esc_attr( $filter_key ); ?> apf-filter-group-checkbox widget woocommerce widget_layered_nav_filters">
       <h4 class="widget-title apf-filter-title"><?php echo esc_html( $filter_label ); ?></h4>
       <ul class="apf-filter-list apf-filter-list-checkbox woocommerce-widget-layered-nav-list">
           <?php foreach ( $options as $option ) : ?>
               <?php
               // Adapt for both term objects and custom arrays (like stock status)
               $option_id = '';
               $option_name = '';
               $option_count = '';

               if (is_object($option) && isset($option->term_id)) { // WordPress Term object
                   $option_id = $option->term_id;
                   $option_name = $option->name;
                   $option_count = $option->count;
               } elseif (is_array($option) && isset($option['value'])) { // Custom array (e.g. for stock)
                   $option_id = $option['value'];
                   $option_name = $option['name'];
                   $option_count = isset($option['count']) ? $option['count'] : ''; // Count might not always be relevant for custom
               } else {
                   continue; // Skip if option format is not recognized
               }

               $input_id = 'apf-' . esc_attr( $filter_key ) . '-' . esc_attr( $option_id );
               // Ensure $current_selection values are strings for comparison, as form values are strings.
               $checked = in_array( (string) $option_id, array_map('strval', $current_selection), true );
               ?>
               <li class="woocommerce-widget-layered-nav-list__item wc-layered-nav-term <?php if($checked) echo 'woocommerce-widget-layered-nav-list__item--chosen chosen'; ?>">
                   <input type="checkbox"
                          id="<?php echo esc_attr( $input_id ); ?>"
                          name="<?php echo esc_attr( $name_attribute ); ?>[]"
                          value="<?php echo esc_attr( $option_id ); ?>"
                          <?php checked( $checked ); ?>
                          data-filter-key="<?php echo esc_attr( $filter_key ); ?>"
                          class="apf-filter-checkbox"/>
                   <label for="<?php echo esc_attr( $input_id ); ?>">
                       <?php echo esc_html( $option_name ); ?>
                       <?php if ( $option_count !== '' && $filter_key !== 'stock_status' ) : // Don't show count for stock status by default ?>
                           <span class="apf-count count">(<?php echo esc_html( $option_count ); ?>)</span>
                       <?php endif; ?>
                   </label>
               </li>
           <?php endforeach; ?>
       </ul>
   </div>
