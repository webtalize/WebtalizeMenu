(function($) {
    'use strict';

    // Wait for jQuery to be ready
    $(document).ready(function() {
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
                
                // Handle featured image
                var row = $('#post-' + post_id);
                var imageData = row.find('.wtm-featured-image-data');
                var imageId = imageData.attr('data-id') || '';
                var imageUrl = imageData.attr('data-url') || '';
                
                var $imagePreview = edit_row.find('.wtm-quick-edit-image-preview img');
                var $imageInput = edit_row.find('.wtm_featured_image_id');
                var $removeBtn = edit_row.find('.wtm-remove-featured-image-btn');
                
                if (imageId && imageUrl) {
                    $imageInput.val(imageId);
                    $imagePreview.attr('src', imageUrl).show();
                    $removeBtn.show();
                } else {
                    $imageInput.val('');
                    $imagePreview.hide();
                    $removeBtn.hide();
                }
            }, 50); // A small delay is needed to ensure the row is ready.
        });
        
        // Featured image upload handler
        // Use event delegation and create a new media uploader for each click
        $(document).on('click', '.wtm-upload-featured-image-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Upload button clicked'); // Debug
            
            // Check if wp.media is available
            if (typeof wp === 'undefined') {
                console.error('wp is undefined');
                alert('WordPress media library is not available. Please refresh the page.');
                return;
            }
            
            if (typeof wp.media === 'undefined') {
                console.error('wp.media is undefined');
                alert('Media library is not available. Please refresh the page.');
                return;
            }
            
            var $button = $(this);
            var $wrapper = $button.closest('.wtm-quick-edit-image-wrapper');
            
            if ($wrapper.length === 0) {
                console.error('Could not find image wrapper');
                return;
            }
            
            var $preview = $wrapper.find('.wtm-quick-edit-image-preview img');
            var $input = $wrapper.find('.wtm_featured_image_id');
            var $removeBtn = $wrapper.find('.wtm-remove-featured-image-btn');
            
            console.log('Creating media uploader'); // Debug
            
            // Create a new media uploader instance for each click
            var mediaUploader = wp.media({
                title: 'Choose Featured Image',
                button: {
                    text: 'Choose Image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            // Handle image selection
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                var imageUrl = attachment.url;
                
                // Try to get a smaller size if available
                if (attachment.sizes && attachment.sizes.thumbnail) {
                    imageUrl = attachment.sizes.thumbnail.url;
                } else if (attachment.sizes && attachment.sizes.medium) {
                    imageUrl = attachment.sizes.medium.url;
                }
                
                $input.val(attachment.id);
                $preview.attr('src', imageUrl).show();
                $removeBtn.show();
                
                console.log('Image selected:', attachment.id); // Debug
            });
            
            // Open the media uploader
            console.log('Opening media uploader'); // Debug
            mediaUploader.open();
        });
        
        // Featured image remove handler
        $(document).on('click', '.wtm-remove-featured-image-btn', function(e) {
            e.preventDefault();
            var $button = $(this);
            var $wrapper = $button.closest('.wtm-quick-edit-image-wrapper');
            var $preview = $wrapper.find('.wtm-quick-edit-image-preview img');
            var $input = $wrapper.find('.wtm_featured_image_id');
            
            $input.val('');
            $preview.hide();
            $button.hide();
        });
    });
})(jQuery);