jQuery(document).ready(function($) {
    if (typeof apf_vars !== 'undefined') {
        console.log('APF: Frontend scripts loaded. AJAX URL: ' + apf_vars.ajax_url + ', Nonce: ' + apf_vars.nonce);
        // Add currency symbol to apf_vars if not already there, for price display
        apf_vars.currency_symbol = apf_vars.currency_symbol || '$';
    } else {
        console.error('APF: apf_vars are not defined. AJAX functionality will fail.');
        // Mock apf_vars for environments where it might be missing but we want script to run partially
        // window.apf_vars = { ajax_url: '', nonce: '', currency_symbol: '$' };
        // return; // Consider returning if critical apf_vars are missing
    }

    var $filterForm = $('#apf-filter-form');
    var $clearFiltersButton = $('#apf-clear-filters');
    var $priceSliderElement = $('.apf-price-slider-range'); // Renamed for clarity

    // Define a wrapper for products to apply loading overlay.
    var $productsWrapper = $('ul.products').first().parent(); // Try parent of the first ul.products
    if (!$productsWrapper.length || $productsWrapper.is('body') || $productsWrapper.is('#main')) { // Avoid overly broad wrappers
        $productsWrapper = $('ul.products').first(); // Fallback to ul.products itself
    }
    if ($productsWrapper.length === 0) {
        console.warn('APF: Products wrapper (ul.products or its direct parent) not found for loading state. Loading overlay might not work as expected.');
    }


    // --- Price Range Slider ---
    if ($priceSliderElement.length > 0 && typeof $.fn.slider === 'function') {
        var $minPriceInput = $('#apf_min_price');
        var $maxPriceInput = $('#apf_max_price');
        // Correctly select the .from and .to spans relative to the slider instance
        var $priceDisplayWrapper = $priceSliderElement.closest('.price_slider_wrapper').find('.apf-price-slider-amount.price_label');
        var $priceLabelFrom = $priceDisplayWrapper.find('span.from');
        var $priceLabelTo = $priceDisplayWrapper.find('span.to');

        var minLimit = parseFloat($priceSliderElement.data('min'));
        var maxLimit = parseFloat($priceSliderElement.data('max'));
        var currentMin = parseFloat($minPriceInput.val());
        var currentMax = parseFloat($maxPriceInput.val());

        currentMin = isNaN(currentMin) || currentMin < minLimit || currentMin > maxLimit ? minLimit : currentMin;
        currentMax = isNaN(currentMax) || currentMax > maxLimit || currentMax < minLimit ? maxLimit : currentMax;
        if (currentMin > currentMax) {
            [currentMin, currentMax] = [currentMax, currentMin];
        }

        $priceSliderElement.slider({
            range: true,
            min: minLimit,
            max: maxLimit,
            values: [currentMin, currentMax],
            create: function() {
                $minPriceInput.val(currentMin);
                $maxPriceInput.val(currentMax);
                if($priceLabelFrom.length) $priceLabelFrom.text(apf_vars.currency_symbol + currentMin.toFixed(2));
                if($priceLabelTo.length) $priceLabelTo.text(apf_vars.currency_symbol + currentMax.toFixed(2));
            },
            slide: function(event, ui) {
                $minPriceInput.val(ui.values[0]);
                $maxPriceInput.val(ui.values[1]);
                if($priceLabelFrom.length) $priceLabelFrom.text(apf_vars.currency_symbol + ui.values[0].toFixed(2));
                if($priceLabelTo.length) $priceLabelTo.text(apf_vars.currency_symbol + ui.values[1].toFixed(2));
            },
            // stop event is handled by the separate binding below
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

        if ($priceSliderElement.length) {
            var absMin = parseFloat($priceSliderElement.data('min'));
            var absMax = parseFloat($priceSliderElement.data('max'));
            var currentMinVal = parseFloat($('#apf_min_price').val()); // Ensure it's a number
            var currentMaxVal = parseFloat($('#apf_max_price').val()); // Ensure it's a number

            // Only include price_range if it's actively filtered (different from absolute min/max)
            if (currentMinVal > absMin || currentMaxVal < absMax) {
                active_filters['price_range'] = {
                    min: currentMinVal,
                    max: currentMaxVal
                };
                 console.log('APF: Price filter active', active_filters['price_range']);
            } else {
                 console.log('APF: Price filter inactive, values at default.');
            }
        }
        console.log('APF: Collected Filters:', JSON.parse(JSON.stringify(active_filters)));
        return active_filters;
    }

    function perform_ajax_filter(filters, page) {
        page = page || 1;
        console.log('APF: Sending AJAX request with filters:', JSON.parse(JSON.stringify(filters)), 'Page:', page);

        if (typeof apf_vars === 'undefined' || !apf_vars.ajax_url || !apf_vars.nonce) {
            console.error('APF: AJAX variables (ajax_url or nonce) are missing.');
            return;
        }

        var $productsTargetForLoading = $productsWrapper.length ? $productsWrapper : $('ul.products').first();
        if($productsTargetForLoading.length > 0) {
            $productsTargetForLoading.addClass('apf-loading');
            if ($productsTargetForLoading.find('.apf-loader-overlay').length === 0) {
                 $productsTargetForLoading.append('<div class="apf-loader-overlay"><div class="apf-loader"></div></div>');
            }
        } else {
            console.warn('APF: Target for loading overlay not found.');
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
                console.log('APF: AJAX Response:', response);
                if (response && response.success) {
                    // More specific targets, ideally within a plugin-controlled wrapper
                    var $shopWrapper = $('#apf-shop-content-wrapper'); // Assume this wrapper exists or use a broader one
                    if (!$shopWrapper.length) {
                        // Fallback to updating individual standard WC elements if the wrapper is not there
                        // This makes it less atomic but more theme-resilient if the wrapper isn't used.
                        var $productsTarget = $('ul.products').first();
                        var $paginationTarget = $('.woocommerce-pagination').first();
                        var $resultCountTarget = $('.woocommerce-result-count').first();

                        if ($productsTarget.length && typeof response.data.products_html !== 'undefined') {
                            $productsTarget.html(response.data.products_html);
                        } else {
                            console.warn('APF: Products target (ul.products) not found or no products_html in response.');
                        }

                        if ($paginationTarget.length) {
                            if (typeof response.data.pagination_html !== 'undefined' && response.data.pagination_html.trim() !== "") {
                               $paginationTarget.html(response.data.pagination_html);
                            } else {
                               $paginationTarget.empty();
                            }
                        } else {
                            console.warn('APF: Pagination target (.woocommerce-pagination) not found.');
                        }

                        if($resultCountTarget.length) {
                            if(typeof response.data.result_count_html !== 'undefined' && response.data.result_count_html.trim() !== "") {
                                $resultCountTarget.html(response.data.result_count_html);
                            } else {
                                 $resultCountTarget.empty();
                            }
                        } else {
                            console.warn('APF: Result count target (.woocommerce-result-count) not found.');
                        }
                    } else {
                         // If wrapper exists, replace its content (assuming PHP returns a combined HTML block for this wrapper)
                         // This would require PHP to change to send e.g. response.data.shop_content_html
                         // For now, stick to individual element updates.
                    }

                    console.log('APF: AJAX success - DOM updated.');
                    // TODO: Potentially update browser URL using history.pushState
                } else {
                    var errorMessage = response && response.data && response.data.message ? response.data.message : 'Unknown error from server.';
                    console.error('APF: AJAX Error (response.success=false):', errorMessage);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('APF: AJAX Request Failed. Status:', textStatus, 'Error:', errorThrown, 'ResponseText:', jqXHR.responseText);
            },
            complete: function() {
                if($productsTargetForLoading.length > 0) {
                    $productsTargetForLoading.removeClass('apf-loading').find('.apf-loader-overlay').remove();
                }
                $(document.body).trigger('init_tooltips');
                console.log('APF: AJAX request complete.');
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
        console.log('APF: Filter changed (checkbox/select)', $(this).data('filter-key'));
        handle_filter_change(1);
    });

    if ($priceSliderElement.length > 0 && typeof $.fn.slider === 'function') {
        $priceSliderElement.on('slidestop', function(event, ui) {
            console.log('APF: Filter changed (price slider)');
            handle_filter_change(1);
        });
    }

    $(document).on('click', '.woocommerce-pagination a.page-numbers', function(e) {
        e.preventDefault();
        var pageUrl = $(this).attr('href');
        var pageNum = 1;

        var matches = pageUrl.match(/\/page\/(\d+)/); // For /page/X/ structure
        if (matches && matches[1]) {
            pageNum = parseInt(matches[1]);
        } else {
            var urlParams = new URLSearchParams(pageUrl.split('?')[1] || ''); // For ?paged=X or ?product-page=X
            if (urlParams.has('paged')) {
                pageNum = parseInt(urlParams.get('paged'));
            } else if (urlParams.has('product-page')) { // Some themes might use this, esp. with shortcodes
                pageNum = parseInt(urlParams.get('product-page'));
            } else { // Try to find any param that looks like a page number as a last resort
                 urlParams.forEach(function(value, key) {
                    if (key.toLowerCase().includes('page')) {
                        var num = parseInt(value);
                        if (!isNaN(num) && num > 0) {
                            pageNum = num;
                            return;
                        }
                    }
                 });
            }
        }

        if(!isNaN(pageNum) && pageNum > 0){
            console.log('APF: Pagination link clicked. Page:', pageNum);
            handle_filter_change(pageNum);
            $('html, body').animate({ scrollTop: $productsWrapper.length ? $productsWrapper.offset().top - 50 : 0 }, 500); // Scroll to top of products
        } else {
            console.warn('APF: Could not determine page number from pagination link:', pageUrl);
        }
    });

    $clearFiltersButton.on('click', function(e) {
        e.preventDefault();
        console.log('APF: Clear All clicked');

        $filterForm.find('input.apf-filter-checkbox').prop('checked', false);
        $filterForm.find('select.apf-filter-select').val('');

        if ($priceSliderElement.length > 0 && typeof $.fn.slider === 'function') {
            var minLimit = parseFloat($priceSliderElement.data('min'));
            var maxLimit = parseFloat($priceSliderElement.data('max'));
            var $minPriceInput = $('#apf_min_price');
            var $maxPriceInput = $('#apf_max_price');
            var $priceDisplayWrapper = $priceSliderElement.closest('.price_slider_wrapper').find('.apf-price-slider-amount.price_label');
            var $priceLabelFrom = $priceDisplayWrapper.find('span.from');
            var $priceLabelTo = $priceDisplayWrapper.find('span.to');

            $priceSliderElement.slider('values', [minLimit, maxLimit]);
            $minPriceInput.val(minLimit);
            $maxPriceInput.val(maxLimit);
            if($priceLabelFrom.length) $priceLabelFrom.text(apf_vars.currency_symbol + minLimit.toFixed(2));
            if($priceLabelTo.length) $priceLabelTo.text(apf_vars.currency_symbol + maxLimit.toFixed(2));
        }

        handle_filter_change(1);
    });

    function manage_clear_button_visibility() {
        // Call collect_active_filters without its internal logging for this check, or make logging conditional
        var current_filters = collect_active_filters(); // This will log
        var is_any_filter_active = false;
        for (var key in current_filters) {
            if (current_filters.hasOwnProperty(key)) {
                if (key === 'price_range') {
                    // Price range object exists only if it's active
                    is_any_filter_active = true;
                    break;
                } else if (Array.isArray(current_filters[key]) && current_filters[key].length > 0) {
                    is_any_filter_active = true;
                    break;
                } else if (!Array.isArray(current_filters[key]) && current_filters[key]) {
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
         console.log('APF: Clear button visibility updated. Visible:', is_any_filter_active);
    }

    if ($filterForm.length > 0) {
        manage_clear_button_visibility();
    }
});
