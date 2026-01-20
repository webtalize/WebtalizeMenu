(function($, wp) {
    'use strict';

    $(function() {
        // The "Quick Edit" link is actually a button, so we can listen for clicks on it.
        $(document).on('click', 'button.editinline', function() {
            // Get the post ID from the table row.
            var post_id = $(this).closest('tr').attr('id').replace('post-', '');

            // The inline edit row is available, but the data is not yet populated.
            // We need to wait for the next tick of the event loop.
            setTimeout(function() {
                // Get the post object from the WordPress data store.
                var post = wp.data.select('core').getEntityRecord('postType', 'menu_item', post_id);
                var edit_row = $('#edit-' + post_id);
                
                // Get price and description from data store
                var price = '';
                var description = '';
                if (post && post.meta) {
                    price = post.meta.wtm_price || '';
                    description = post.meta.wtm_description || '';
                }
                
                edit_row.find('.wtm_price_input').val(price);
                edit_row.find('.wtm_description_input').val(description);
                
                // Handle dietary labels - try multiple sources
                var dietary_labels = [];
                
                // First, try to get from data store
                if (post && post.meta && post.meta.wtm_dietary_labels) {
                    var store_labels = post.meta.wtm_dietary_labels;
                    if (typeof store_labels === 'string') {
                        try {
                            dietary_labels = JSON.parse(store_labels);
                        } catch (e) {
                            dietary_labels = [];
                        }
                    } else if (Array.isArray(store_labels)) {
                        dietary_labels = store_labels;
                    }
                }
                
                // If not found in data store, try to get from the dietary labels column (more reliable)
                if (dietary_labels.length === 0 || !Array.isArray(dietary_labels)) {
                    var row = $('#post-' + post_id);
                    var data_span = row.find('.wtm-dietary-labels-data');
                    if (data_span.length > 0) {
                        var row_data = data_span.attr('data-labels');
                        if (row_data) {
                            try {
                                dietary_labels = JSON.parse(row_data);
                            } catch (e) {
                                dietary_labels = [];
                            }
                        }
                    }
                }
                
                // Uncheck all first
                edit_row.find('.wtm_dietary_labels_input').prop('checked', false);
                
                // Check the ones that are set
                if (Array.isArray(dietary_labels) && dietary_labels.length > 0) {
                    dietary_labels.forEach(function(label) {
                        edit_row.find('.wtm_dietary_labels_input[data-label="' + label + '"]').prop('checked', true);
                    });
                }
            }, 50); // A small delay is needed to ensure the row is ready.
        });
    });
})(jQuery, wp);