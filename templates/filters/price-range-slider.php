<?php
/**
 * Price Range Slider Template
 *
 * @var string $filter_key
 * @var string $filter_label
 * @var float $min_price_limit
 * @var float $max_price_limit
 * @var float $current_min_value
 * @var float $current_max_value
 * @var string $currency_symbol (Optional, could be passed from PHP)
 */

$currency_symbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$';
?>
<div class="apf-filter-group apf-filter-group-price-range widget woocommerce widget_price_filter">
    <h4 class="widget-title apf-filter-title"><?php echo esc_html( $filter_label ); ?></h4>
    <div class="price_slider_wrapper">
        <div class="apf-price-slider-range price_slider"
             data-min="<?php echo esc_attr( $min_price_limit ); ?>"
             data-max="<?php echo esc_attr( $max_price_limit ); ?>"
             data-current-min="<?php echo esc_attr( $current_min_value ); ?>"
             data-current-max="<?php echo esc_attr( $current_max_value ); ?>">
        </div>
        <div class="apf-price-slider-amount price_label">
            <?php // The text will be constructed by JS, but inputs store the values ?>
            <span class="from"></span> &mdash; <span class="to"></span>
            <input type="hidden" id="apf_min_price" name="min_price"
                   value="<?php echo esc_attr( $current_min_value ); ?>"
                   data-min="<?php echo esc_attr( $min_price_limit ); ?>" />
            <input type="hidden" id="apf_max_price" name="max_price"
                   value="<?php echo esc_attr( $current_max_value ); ?>"
                   data-max="<?php echo esc_attr( $max_price_limit ); ?>" />
            <?php /* The button below is WooCommerce's default, we might not need it if AJAX is on slide stop */ ?>
            <button type="submit" class="button apf-price-filter-button" style="display:none;"><?php esc_html_e('Filter', 'woocommerce'); ?></button>
             <div class="clear"></div>
        </div>
    </div>
</div>
