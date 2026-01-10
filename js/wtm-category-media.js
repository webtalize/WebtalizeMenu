jQuery(document).ready(function($){
    var mediaUploader;
    
    // Image upload functionality
    $(document).on('click', '.wtm-upload-image-btn', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $wrapper = $button.closest('.term-image-wrap');
        
        if (mediaUploader) {
            mediaUploader.off('select');
            setupSelectHandler($wrapper);
            mediaUploader.open();
            return;
        }
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Category Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false
        });
        
        setupSelectHandler($wrapper);
        mediaUploader.open();
    });
    
    function setupSelectHandler($wrapper) {
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            var imageUrl = attachment.url || attachment.sizes.thumbnail.url || attachment.sizes.medium.url;
            $wrapper.find('.wtm-category-image-id').val(attachment.id);
            
            // Different preview sizes for Quick Edit vs regular edit
            var isQuickEdit = $wrapper.closest('.inline-edit-row').length > 0;
            var maxWidth = isQuickEdit ? '100px' : '150px';
            $wrapper.find('.wtm-category-image-preview').html('<img src="' + imageUrl + '" style="max-width:' + maxWidth + ';height:auto;display:block;" />');
            $wrapper.find('.wtm-remove-image-btn').show();
        });
    }
    
    $(document).on('click', '.wtm-remove-image-btn', function(e){
        e.preventDefault();
        var $wrapper = $(this).closest('.term-image-wrap');
        $wrapper.find('.wtm-category-image-id').val('');
        
        // Show placeholder text in Quick Edit, empty in regular edit
        var isQuickEdit = $wrapper.closest('.inline-edit-row').length > 0;
        if (isQuickEdit) {
            $wrapper.find('.wtm-category-image-preview').html('<span style="color:#999;font-style:italic;">No image selected</span>');
        } else {
            $wrapper.find('.wtm-category-image-preview').html('');
        }
        $(this).hide();
    });

    // --- Quick Edit Handler ---
    // Hook into WordPress's inlineEditTax.edit function (most reliable approach)
    if (typeof inlineEditTax !== 'undefined') {
        var originalInlineEdit = inlineEditTax.edit;
        
        inlineEditTax.edit = function(id) {
            // Call original function first
            originalInlineEdit.apply(this, arguments);
            
            // Extract term ID from the id parameter
            var termId = 0;
            if (typeof id === 'object') {
                termId = parseInt(id.toString().replace('tag-', ''), 10);
            } else if (typeof id === 'string') {
                termId = parseInt(id.replace('tag-', ''), 10);
            } else {
                termId = parseInt(id, 10);
            }
            
            if (termId > 0) {
                // Use a longer timeout to ensure WordPress has created the edit row
                setTimeout(function() {
                    addQuickEditFields(termId);
                }, 150);
            }
        };
    }
    
    // Fallback: Also listen for button clicks (in case inlineEditTax isn't available)
    $(document.body).on('click', '#the-list button.editinline, #the-list .editinline', function() {
        var $row = $(this).closest('tr');
        var tag_id = $row.attr('id');
        if (tag_id) {
            tag_id = tag_id.replace('tag-', '');
            setTimeout(function() {
                addQuickEditFields(tag_id);
            }, 150);
        }
    });
    
    function addQuickEditFields(tag_id) {
        var $editRow = $('#edit-' + tag_id);
        var $row = $('#tag-' + tag_id);
        
        if ($editRow.length === 0 || $row.length === 0) {
            return;
        }
        
        // Get data from table row
        var imageId = $row.find('.wtm-image-data').data('id') || '';
        var imageUrl = $row.find('.wtm-image-data').data('url') || '';
        var orderVal = $row.find('.wtm-order-data').data('val');
        if (typeof orderVal === 'undefined' || orderVal === '' || orderVal === null) {
            orderVal = 0;
        }
        
        // Check if Sort Order field already exists
        if ($editRow.find('input[name="wtm_category_order"]').length === 0) {
            // Find where to insert - look for the slug field or name field
            var $targetFieldset = $editRow.find('input[name="slug"]').closest('fieldset');
            if ($targetFieldset.length === 0) {
                $targetFieldset = $editRow.find('input[name="name"]').closest('fieldset');
            }
            
            var sortOrderHtml = '<fieldset class="inline-edit-col-right">' +
                '<div class="inline-edit-col">' +
                    '<label>' +
                        '<span class="title">Sort Order</span>' +
                        '<span class="input-text-wrap">' +
                            '<input type="number" name="wtm_category_order" class="wtm-category-order" value="">' +
                        '</span>' +
                    '</label>' +
                '</div>' +
            '</fieldset>';
            
            if ($targetFieldset.length > 0) {
                $targetFieldset.after(sortOrderHtml);
            } else {
                // Last resort: insert before submit button
                $editRow.find('p.submit, p.inline-edit-save').first().before(sortOrderHtml);
            }
        }
        
        // Populate Sort Order field
        $editRow.find('input[name="wtm_category_order"]').val(orderVal);
        
        // Always add Image field if it doesn't exist
        if ($editRow.find('.wtm-quick-edit-image-fields').length === 0) {
            var imageHtml = '<fieldset class="inline-edit-col-left term-image-wrap wtm-quick-edit-image-fields" style="clear:both; margin-top:10px; border-top: 1px solid #ddd; padding-top: 10px; display: block !important;">' +
                '<div class="inline-edit-col">' +
                    '<label class="inline-edit-group">' +
                        '<span class="title" style="font-weight: 600;">Category Image</span>' +
                        '<span class="input-text-wrap">' +
                            '<div class="wtm-category-image-preview" style="margin-bottom: 8px; min-height: 60px; padding: 5px; border: 1px solid #ddd; background: #f9f9f9; display: block;"></div>' +
                            '<input type="hidden" name="wtm_category_image_id" class="wtm-category-image-id" value="">' +
                            '<button type="button" class="button button-primary wtm-upload-image-btn" style="margin-right: 5px;">Select Image</button>' +
                            '<button type="button" class="button wtm-remove-image-btn" style="display:none;">Remove Image</button>' +
                        '</span>' +
                    '</label>' +
                '</div>' +
            '</fieldset>';
            
            // Insert after the Sort Order fieldset
            var $sortOrderFieldset = $editRow.find('input[name="wtm_category_order"]').closest('fieldset');
            if ($sortOrderFieldset.length > 0) {
                $sortOrderFieldset.after(imageHtml);
            } else {
                // Fallback: Try to find submit button or last fieldset
                var $submitContainer = $editRow.find('p.submit, p.inline-edit-save, .inline-edit-save, .submit');
                if ($submitContainer.length > 0) {
                    $submitContainer.first().before(imageHtml);
                } else {
                    // Try to find the last fieldset and add after it
                    var $lastFieldset = $editRow.find('fieldset').last();
                    if ($lastFieldset.length > 0) {
                        $lastFieldset.after(imageHtml);
                    } else {
                        // Last resort: append to the edit row
                        $editRow.append(imageHtml);
                    }
                }
            }
        }
        
        // Populate Image field
        $editRow.find('.wtm-category-image-id').val(imageId);
        
        if (imageUrl) {
            $editRow.find('.wtm-category-image-preview').html('<img src="' + imageUrl + '" style="max-width:100px;height:auto;display:block;" />');
            $editRow.find('.wtm-remove-image-btn').show();
        } else {
            $editRow.find('.wtm-category-image-preview').html('<span style="color:#999;font-style:italic;">No image selected</span>');
            $editRow.find('.wtm-remove-image-btn').hide();
        }
    }
});
