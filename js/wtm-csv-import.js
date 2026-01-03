jQuery(document).ready(function($){
    var $ta = $('#wtm_bulk_text');
    var $preview = $('#wtm_bulk_preview');
    var $autoDetect = $('input[name="wtm_auto_detect"]');
    var timer;
    var parsedItems = []; // Store the parsed items

    // Setup log area
    $preview.html('<div id="wtm_import_log" style="border:1px solid #ccc; background:#f9f9f9; padding:10px; max-height:300px; overflow:auto; margin-bottom:10px; font-family:monospace; font-size:11px; display:none;"><strong>Activity Log:</strong><br/></div><div id="wtm_import_results"></div>');
    var $log = $('#wtm_import_log');
    var $results = $('#wtm_import_results');

    function log(msg) {
        $log.show();
        var time = new Date().toLocaleTimeString();
        $log.append('<div><span style="color:#888">[' + time + ']</span> ' + msg + '</div>');
        $log.scrollTop($log[0].scrollHeight);
    }

    function fetchPreview(){
        var text = $ta.val();
        log('Processing input (Length: ' + text.length + ')');
        if(!$.trim(text)){
            $results.html('<em>No items parsed yet</em>');
            log('Input empty.');
            parsedItems = [];
            updateHiddenInput();
            return;
        }
        $results.css('opacity', '0.5');
        
        var isAuto = $autoDetect.is(':checked');
        log('Sending AJAX request. Auto-detect: ' + isAuto);

        var data = {
            action: 'wtm_preview_csv',
            nonce: wtmCSV.nonce,
            text: text,
            auto_detect: isAuto ? 'true' : 'false'
        };

        $.post(wtmCSV.ajax_url, data, function(response){
            $results.css('opacity', '1');
            if(response.success){
                log('AJAX Success.');
                
                parsedItems = response.data.items || [];
                var debug = response.data.debug || [];

                if(debug.length > 0) {
                    log('<strong>Server Debug:</strong><br/>' + debug.join('<br/>'));
                }

                renderItems();
                log('Rendered ' + parsedItems.length + ' items.');
            } else {
                $results.html('<em>Error parsing data</em>');
                log('AJAX Error: ' + (response.data || 'Unknown error'));
                parsedItems = [];
            }
            updateHiddenInput();
        }).fail(function() {
            $results.css('opacity', '1');
            $results.html('<em>Server communication error</em>');
            log('AJAX Request Failed.');
            parsedItems = [];
            updateHiddenInput();
        });
    }

    function renderItems(){
        if(!parsedItems || !parsedItems.length){ 
            $results.html('<em>No items parsed yet</em>'); 
            return; 
        }
        var html = '<table class="wtm-bulk-table"><thead><tr><th>Name</th><th>Description</th><th>Price</th><th>Category</th><th>Actions</th></tr></thead><tbody>';
        $.each(parsedItems,function(i,it){
            var priceDisplay = it.price ? '$' + it.price : '';
            html += '<tr data-index="'+i+'">' +
                '<td>'+escapeHtml(it.name||'')+'</td>' +
                '<td>'+escapeHtml(it.description||'')+'</td>' +
                '<td>'+escapeHtml(priceDisplay)+'</td>' +
                '<td>'+escapeHtml(it.category||'')+'</td>' +
                '<td><button type="button" class="button wtm-edit-row">Edit</button></td>' +
                '</tr>';
        });
        html += '</tbody></table>';
        $results.html(html);
    }

    function renderEditForm(index) {
        var item = parsedItems[index];
        var $row = $results.find('tr[data-index="'+index+'"]');
        var formHtml = '<td colspan="5">' +
            '<div style="padding:10px; background:#f5f5f5;">' +
            '<p><label>Name: <input type="text" class="wtm-edit-name" value="'+escapeHtml(item.name||'')+'" style="width:100%"></label></p>' +
            '<p><label>Description: <textarea class="wtm-edit-description" style="width:100%">'+escapeHtml(item.description||'')+'</textarea></label></p>' +
            '<p><label>Price: <input type="text" class="wtm-edit-price" value="'+escapeHtml(item.price||'')+'"></label></p>' +
            '<p><label>Category: <input type="text" class="wtm-edit-category" value="'+escapeHtml(item.category||'')+'"></label></p>' +
            '<p><button type="button" class="button button-primary wtm-save-row">Save</button> ' +
            '<button type="button" class="button wtm-cancel-edit">Cancel</button></p>' +
            '</div>' +
            '</td>';
        $row.html(formHtml);
    }
    
    function updateHiddenInput() {
        var $form = $ta.closest('form');
        var $hiddenInput = $form.find('input[name="wtm_bulk_json"]');
        if ($hiddenInput.length === 0) {
            $form.append('<input type="hidden" name="wtm_bulk_json" />');
            $hiddenInput = $form.find('input[name="wtm_bulk_json"]');
        }
        $hiddenInput.val(JSON.stringify(parsedItems));
    }

    function escapeHtml(s){ return $('<div/>').text(s).html(); }

    $results.on('click', '.wtm-edit-row', function() {
        var index = $(this).closest('tr').data('index');
        renderEditForm(index);
    });

    $results.on('click', '.wtm-cancel-edit', function() {
        renderItems();
    });

    $results.on('click', '.wtm-save-row', function() {
        var $container = $(this).closest('td');
        var index = $container.closest('tr').data('index');
        
        var name = $container.find('.wtm-edit-name').val();
        var description = $container.find('.wtm-edit-description').val();
        var price = $container.find('.wtm-edit-price').val();
        var category = $container.find('.wtm-edit-category').val();

        parsedItems[index] = {
            name: name,
            description: description,
            price: price,
            category: category
        };

        log('Row ' + (index+1) + ' updated.');
        updateHiddenInput();
        renderItems();
    });

    $ta.on('input', function(){
        clearTimeout(timer);
        timer = setTimeout(fetchPreview, 500); // Debounce
    });

    $autoDetect.on('change', function() {
        log('Auto-detect checkbox changed: ' + ($(this).is(':checked') ? 'Checked' : 'Unchecked'));
        fetchPreview();
    });

    if($ta.val()) fetchPreview();

    $('#wtm_create_category').on('click', function(){
        var name = $('#wtm_new_category').val();
        if(!name) return alert('Please enter a category name');
        log('Creating category: ' + name);
        var ajax = (typeof wtmCSV !== 'undefined' ? wtmCSV : (typeof wtmBulk !== 'undefined' ? wtmBulk : {}));
        $.post(ajax.ajax_url || ajaxurl, { action:'wtm_create_category', name:name, nonce:ajax.nonce || '' }, function(resp){
            if(resp && resp.success){
                alert('Category "'+resp.data.name+'" created (id:'+resp.data.term_id+')');
                log('Category created: ' + resp.data.name);
                $('#wtm_new_category').val('');
            } else {
                alert('Error: '+(resp && resp.data ? resp.data : 'unknown'));
                log('Category creation error: ' + (resp && resp.data ? resp.data : 'unknown'));
            }
        });
    });
});