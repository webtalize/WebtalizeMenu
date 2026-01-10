/* global inlineEditTax, ajaxurl */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // We are using the WP inline edit taxonomy functionality
        if (typeof inlineEditTax === 'undefined') {
            return;
        }

        var wpInlineEdit = inlineEditTax.edit;

        inlineEditTax.edit = function (id) {
            // Call the original function
            wpInlineEdit.apply(this, arguments);

            // Get the term ID
            var termId = 0;
            if (typeof id === 'object') {
                termId = parseInt(id.toString().replace('tag-', ''), 10);
            }

            if (termId > 0) {
                // Get the table row
                var row = $('#tag-' + termId);

                // Get the sort order value from the column
                var sortOrder = $('.wtm-order-data', row).data('val');

                // Populate the new input field in the quick edit form
                $('.inline-edit-row :input[name="wtm_category_order"]').val(sortOrder);
            }
        };
    });
}(jQuery));
