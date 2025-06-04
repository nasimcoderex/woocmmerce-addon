jQuery(document).ready(function($) {
    // Handle visibility of display type options
    $('input.apf-filter-type-checkbox').on('change', function() { // Changed selector for more specificity
        var filterKey = $(this).data('filter-key'); // Use data attribute
        var isChecked = $(this).is(':checked');
        var $displayOptionsDiv = $('#display_options_' . filterKey);

        if (isChecked) {
            $displayOptionsDiv.slideDown();
        } else {
            $displayOptionsDiv.slideUp();
        }
    });

    // Trigger change on page load to set initial states
    $('input.apf-filter-type-checkbox').trigger('change');

    // Initialize jQuery UI Sortable on the filter order list
    $('#apf-sortable-filters').sortable({
        placeholder: 'ui-state-highlight', // Class for the placeholder
        axis: 'y', // Only allow vertical dragging
        cursor: 'move', // Cursor style while dragging
        opacity: 0.7, // Opacity of the helper while dragging
        update: function(event, ui) {
            // This function is called when the sorting is stopped and the DOM has been changed.
            // No specific AJAX action needed here for saving,
            // as the hidden input fields' order is updated in the DOM,
            // and they will be submitted with the form.
        }
    });

    // Optional: Prevent text selection within the sortable items while dragging
    // This can improve the user experience by preventing accidental text highlighting.
    // $('#apf-sortable-filters').disableSelection(); // jQuery UI specific, ensure it's loaded if used
    // For broader compatibility, can be done with user-select CSS if preferred, but this is common with sortable.
    // Check if disableSelection is available from jQuery UI, if not, it might cause an error.
    // It's part of jQuery UI core, so should be fine if jquery-ui-sortable is loaded.
    if (typeof $.fn.disableSelection === 'function') {
        $('#apf-sortable-filters').disableSelection();
    }

});
