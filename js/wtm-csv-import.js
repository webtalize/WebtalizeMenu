jQuery(document).ready(function($){
    var $ta = $('#wtm_bulk_text');
    var $preview = $('#wtm_bulk_preview');
    var $autoDetect = $('input[name="wtm_auto_detect"]');
    var timer;

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
                
                var items = response.data.items || [];
                var debug = response.data.debug || [];

                if(debug.length > 0) {
                    log('<strong>Server Debug:</strong><br/>' + debug.join('<br/>'));
                }

                renderItems(items);
                log('Rendered ' + items.length + ' items.');
            } else {
                $results.html('<em>Error parsing data</em>');
                log('AJAX Error: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            $results.css('opacity', '1');
            $results.html('<em>Server communication error</em>');
            log('AJAX Request Failed.');
        });
    }

    function renderItems(items){
        if(!items || !items.length){ $results.html('<em>No items parsed yet</em>'); return; }
        var html = '<table class="wtm-bulk-table"><thead><tr><th>Name</th><th>Description</th><th>Price</th><th>Category</th></tr></thead><tbody>';
        $.each(items,function(i,it){
            var priceDisplay = it.price ? '$' + it.price : '';
            html += '<tr><td>'+escapeHtml(it.name||'')+'</td><td>'+escapeHtml(it.description||'')+'</td><td>'+escapeHtml(priceDisplay)+'</td><td>'+escapeHtml(it.category||'')+'</td></tr>';
        });
        html += '</tbody></table>';
        $results.html(html);
    }

    function escapeHtml(s){ return $('<div/>').text(s).html(); }

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