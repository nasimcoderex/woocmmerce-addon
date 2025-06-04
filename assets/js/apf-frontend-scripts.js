jQuery(document).ready(function($) {
    if (typeof apf_vars !== 'undefined') {
        console.log('Advanced Product Filters frontend scripts loaded. AJAX URL: ' + apf_vars.ajax_url + ', Nonce: ' + apf_vars.nonce);
    } else {
        console.log('Advanced Product Filters frontend scripts loaded, but apf_vars are not defined.');
        // return; // Might be critical if apf_vars are needed immediately
    }

    var $filterForm = $('#apf-filter-form');
    var $clearFiltersButton = $('#apf-clear-filters');
    var $priceSlider = $('.apf-price-slider-range');
    // Define a wrapper for products to apply loading overlay. Could be ul.products parent or ul.products itself.
    // It's often better to have a dedicated wrapper if other elements besides ul.products are part of the AJAX update zone.
    var $productsWrapper = $('ul.products').closest('.woocommerce-products-wrapper') || $('ul.products');
    if ($productsWrapper.length === 0) { // Fallback if specific wrapper not found
        $productsWrapper = $('ul.products');
    }


    // --- Price Range Slider ---
    if ($priceSlider.length > 0 && typeof $.fn.slider === 'function') {
        var $minPriceInput = $('#apf_min_price');
        var $maxPriceInput = $('#apf_max_price');
        var $priceLabelFrom = $priceSlider.closest('.price_slider_wrapper').find('.price_label .from');
        var $priceLabelTo = $priceSlider.closest('.price_slider_wrapper').find('.price_label .to');
        var currencySymbol = typeof apf_vars !== 'undefined' && apf_vars.currency_symbol ? apf_vars.currency_symbol : '$';

        var minLimit = parseFloat($priceSlider.data('min'));
        var maxLimit = parseFloat($priceSlider.data('max'));
        var currentMin = parseFloat($minPriceInput.val());
        var currentMax = parseFloat($maxPriceInput.val());

        currentMin = isNaN(currentMin) || currentMin < minLimit || currentMin > maxLimit ? minLimit : currentMin;
        currentMax = isNaN(currentMax) || currentMax > maxLimit || currentMax < minLimit ? maxLimit : currentMax;
        if (currentMin > currentMax) {
            [currentMin, currentMax] = [currentMax, currentMin];
        }

        $priceSlider.slider({
            range: true,
            min: minLimit,
            max: maxLimit,
            values: [currentMin, currentMax],
            create: function() {
                $minPriceInput.val(currentMin);
                $maxPriceInput.val(currentMax);
                if($priceLabelFrom.length) $priceLabelFrom.text(currencySymbol + currentMin);
                if($priceLabelTo.length) $priceLabelTo.text(currencySymbol + currentMax);
            },
            slide: function(event, ui) {
                $minPriceInput.val(ui.values[0]);
                $maxPriceInput.val(ui.values[1]);
                if($priceLabelFrom.length) $priceLabelFrom.text(currencySymbol + ui.values[0]);
                if($priceLabelTo.length) $priceLabelTo.text(currencySymbol + ui.values[1]);
            },
            stop: function(event, ui) {
                handle_filter_change();
            }
        });
    }


    function collect_active_filters() {
        var active_filters = {};

        $filterForm.find('input.apf-filter-checkbox:checked').each(function() {
            var $this = $(this);
            var filterKey = $this.data('filter-key');
            if (!active_filters[filterKey]) {
                active_filters[filterKey] = [];
            }
            active_filters[filterKey].push($this.val());
        });

        $filterForm.find('select.apf-filter-select').each(function() {
            var $this = $(this);
            var filterKey = $this.data('filter-key');
            var value = $this.val();
            if (value) {
                active_filters[filterKey] = value;
            }
        });

        if ($priceSlider.length > 0) {
            var minVal = parseFloat($('#apf_min_price').val());
            var maxVal = parseFloat($('#apf_max_price').val());
            var minLimitSlider = parseFloat($priceSlider.data('min'));
            var maxLimitSlider = parseFloat($priceSlider.data('max'));

            if (minVal !== minLimitSlider || maxVal !== maxLimitSlider) {
                active_filters.price_range = {
                    min: minVal,
                    max: maxVal
                };
            }
        }
        return active_filters;
    }

    function perform_ajax_filter(filters, page) {
        page = page || 1; // Default to page 1 if not provided

        // Use the defined $productsWrapper for loading state
        if($productsWrapper.length === 0) {
            console.warn('APF: Products wrapper not found for loading state.');
            // Fallback to ul.products if no better wrapper.
            $productsWrapper = $('ul.products');
        }
        $productsWrapper.addClass('apf-loading');
        if ($productsWrapper.find('.apf-loader-overlay').length === 0) {
             $productsWrapper.append('<div class="apf-loader-overlay"><div class="apf-loader"></div></div>');
        }


        $.ajax({
            url: apf_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'apf_filter_products',
                nonce: apf_vars.nonce,
                filters: JSON.stringify(filters),
                page: page
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Replace content: products, pagination, result count
                    // Ensure the selectors target the correct elements on your page.
                    // These might need to be within a specific container that is updated.
                    var $productsTarget = $('ul.products'); // Standard WC selector
                    var $paginationTarget = $('.woocommerce-pagination');
                    var $resultCountTarget = $('.woocommerce-result-count');

                    if ($productsTarget.length) {
                        $productsTarget.html(response.data.products_html);
                    }
                    if ($paginationTarget.length && response.data.pagination_html) {
                        $paginationTarget.html(response.data.pagination_html);
                    } else if ($paginationTarget.length) {
                        $paginationTarget.empty();
                    }
                    if($resultCountTarget.length && response.data.result_count_html) {
                        $resultCountTarget.html(response.data.result_count_html);
                    }

                    console.log('AJAX success:', response.data.message);
                    // TODO: Potentially update browser URL using history.pushState (advanced)
                } else {
                    console.error('AJAX Error:', response.data.message || 'Unknown error from server.');
                    // Optionally show an error to the user on the page
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Request Failed:', textStatus, errorThrown);
            },
            complete: function() {
                $productsWrapper.removeClass('apf-loading').find('.apf-loader-overlay').remove();
                $(document.body).trigger('wc_fragment_refresh');
                $(document.body).trigger('init_tooltips');
            }
        });
    }

    function handle_filter_change(page) {
        var active_filters = collect_active_filters();
        manage_clear_button_visibility();
        perform_ajax_filter(active_filters, page);
    }

    // Event Handlers
    $filterForm.on('change', 'input.apf-filter-checkbox, select.apf-filter-select', function() {
        // No timeout needed here, perform_ajax_filter will handle it
        handle_filter_change(1); // Reset to page 1 on filter change
    });
    // Price slider changes are handled by its 'stop' event which calls handle_filter_change (and should also reset to page 1).
    // Need to ensure the price slider's stop event also passes page 1.
    if ($priceSlider.length > 0 && typeof $.fn.slider === 'function') {
        $priceSlider.on('slidestop', function(event, ui) {
            handle_filter_change(1); // Ensure page 1 on price change
        });
    }

    // AJAX Pagination
    // Delegate click event for pagination links if they are inside a container that gets replaced
    $(document).on('click', '.woocommerce-pagination a.page-numbers', function(e) {
        e.preventDefault();
        var pageUrl = $(this).attr('href');
        var pageNum = 1;

        // Try to extract page number from URL
        var matches = pageUrl.match(/\/page\/(\d+)/);
        if (matches && matches[1]) {
            pageNum = parseInt(matches[1]);
        } else { // Fallback for ?paged= or other structures if needed
            var urlParams = new URLSearchParams(pageUrl.split('?')[1] || '');
            if (urlParams.has('paged')) {
                pageNum = parseInt(urlParams.get('paged'));
            }
        }

        if(!isNaN(pageNum) && pageNum > 0){
            handle_filter_change(pageNum);
        }
    });


    // --- Clear All Filters ---
    $clearFiltersButton.on('click', function(e) {
        e.preventDefault();

        $filterForm.find('input.apf-filter-checkbox').prop('checked', false);
        $filterForm.find('select.apf-filter-select').val('');

        if ($priceSlider.length > 0 && typeof $.fn.slider === 'function') {
            var minLimit = parseFloat($priceSlider.data('min'));
            var maxLimit = parseFloat($priceSlider.data('max'));
            var $minPriceInput = $('#apf_min_price'); // Define here as it might not be in global scope of this func
            var $maxPriceInput = $('#apf_max_price');
            var $priceLabelFrom = $priceSlider.closest('.price_slider_wrapper').find('.price_label .from');
            var $priceLabelTo = $priceSlider.closest('.price_slider_wrapper').find('.price_label .to');
            var currencySymbol = typeof apf_vars !== 'undefined' && apf_vars.currency_symbol ? apf_vars.currency_symbol : '$';


            $priceSlider.slider('values', [minLimit, maxLimit]);
            $minPriceInput.val(minLimit);
            $maxPriceInput.val(maxLimit);
             if($priceLabelFrom.length) $priceLabelFrom.text(currencySymbol + minLimit);
             if($priceLabelTo.length) $priceLabelTo.text(currencySymbol + maxLimit);
        }

        console.log('Clear All clicked');
        handle_filter_change(1); // Reset to page 1
    });

    function manage_clear_button_visibility() {
        var active_filters = collect_active_filters();
        var is_any_filter_active = false;
        for (var key in active_filters) {
            if (active_filters.hasOwnProperty(key)) {
                if (key === 'price_range') {
                    if ($priceSlider.length > 0 && (active_filters[key].min !== parseFloat($priceSlider.data('min')) ||
                        active_filters[key].max !== parseFloat($priceSlider.data('max')))) {
                        is_any_filter_active = true;
                        break;
                    }
                } else if (Array.isArray(active_filters[key]) && active_filters[key].length > 0) {
                    is_any_filter_active = true;
                    break;
                } else if (!Array.isArray(active_filters[key]) && active_filters[key]) {
                    is_any_filter_active = true;
                    break;
                }
            }
        }

        if (is_any_filter_active) {
            $clearFiltersButton.show();
        } else {
            $clearFiltersButton.hide();
        }
    }

    // Initial setup
    manage_clear_button_visibility();

});
