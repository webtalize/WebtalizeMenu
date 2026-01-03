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
                if (post && post.meta) {
                    var price = post.meta.wtm_price || '';
                    var description = post.meta.wtm_description || '';

                    // Find the input fields in the Quick Edit row and set their values.
                    var edit_row = $('#edit-' + post_id);
                    edit_row.find('.wtm_price_input').val(price);
                    edit_row.find('.wtm_description_input').val(description);
                }
            }, 50); // A small delay is needed to ensure the row is ready.
        });
    });
})(jQuery, wp);