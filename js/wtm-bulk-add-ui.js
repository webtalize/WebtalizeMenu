jQuery(document).ready(function($){
        // Dictionaries from PHP
        var categoryDict = window.wtmCategoryDict || [];
        var nameDict = window.wtmNameDict || [];
        var descDict = window.wtmDescDict || [];

        // lower-cased name dictionary for quick existence checks
        var nameDictLower = (nameDict || []).map(function(n){ return (n||'').toLowerCase(); });

        // Suggestion helper
        function levenshtein(a,b){
            if(a===b) return 0;
            if(a.length===0) return b.length;
            if(b.length===0) return a.length;
            var matrix = [];
            for(var i=0;i<=b.length;i++){ matrix[i]=[i]; }
            for(var j=0;j<=a.length;j++){ matrix[0][j]=j; }
            for(i=1;i<=b.length;i++){
                for(j=1;j<=a.length;j++){
                    if(b.charAt(i-1)===a.charAt(j-1)) matrix[i][j]=matrix[i-1][j-1];
                    else matrix[i][j]=Math.min(matrix[i-1][j-1]+1, Math.min(matrix[i][j-1]+1, matrix[i-1][j]+1));
                }
            }
            return matrix[b.length][a.length];
        }

        function showSuggestions($input, list, cls){
            var val = ($input.val()||'').toLowerCase();
            $input.next('.'+cls).remove();
            if(!val || val.length===0) return;
            // substring matches first
            var matches = list.filter(function(item){ return item.toLowerCase().indexOf(val) !== -1; });
            var usedFuzzy = false;
            // if no substring matches, try fuzzy matching (small edit distance)
            if(matches.length===0){
                usedFuzzy = true;
                var fuzzy = list.map(function(item){ return {item:item,dist:levenshtein(item.toLowerCase(), val)}; });
                fuzzy.sort(function(a,b){ return a.dist - b.dist; });
                // keep top 5 with reasonable distance
                matches = fuzzy.filter(function(x){ return x.dist <= Math.max(2, Math.floor(x.item.length * 0.25)); }).slice(0,5).map(function(x){ return x.item; });
            }
            if(matches.length){
                var $list = $('<ul class="'+cls+'" style="position:absolute;z-index:1000;background:#fff;border:1px solid #ccc;max-height:120px;overflow:auto;margin:0;padding:2px 0;list-style:none;width:90%"></ul>');
                if(usedFuzzy){
                    $list.append('<li class="wtm-suggest-header" style="padding:4px 8px;font-weight:600;color:#2a7ae2">Did you mean?</li>');
                }
                matches.forEach(function(it){ $list.append('<li style="padding:2px 8px;cursor:pointer">'+it+'</li>'); });
                $input.after($list);
            }
        }

        // Name suggestions for single-line name inputs
        $(document).on('input', '.wtm-bulk-name', function(){ showSuggestions($(this), nameDict, 'wtm-name-suggest'); validateNameExists($(this)); });
        // validate immediately whether a name exists in DB and block submit if so
        function validateNameExists($input){
            var val = $.trim($input.val() || '');
            // remove previous inline message
            $input.next('.wtm-exists-msg').remove();
            $input.removeClass('wtm-name-exists');
            if (!val) { updateSubmitState(); return; }
            var lower = val.toLowerCase();
            if (nameDictLower.indexOf(lower) !== -1) {
                $input.addClass('wtm-name-exists');
                $input.after('<div class="wtm-exists-msg" style="color:#b94a48;margin-top:4px;font-size:90%">Name already exists</div>');
            }
            // re-evaluate inline duplicates and submit state
            checkInlineDuplicates();
        }
        // detect duplicates within the form and show/hide inline duplicate messages
        function checkInlineDuplicates(){
            // remove existing duplicate messages
            $('#wtm_bulk_add_table tbody tr').each(function(){
                $(this).find('.wtm-row-msg').filter(function(){ return $(this).text().toLowerCase().indexOf('duplicate') !== -1; }).remove();
            });
            var map = {};
            $('#wtm_bulk_add_table tbody tr').each(function(){
                var $tr = $(this);
                var name = $.trim($tr.find('.wtm-bulk-name').val() || '');
                if (!name) return;
                var lower = name.toLowerCase();
                map[lower] = map[lower] || [];
                map[lower].push($tr);
            });
            for(var k in map){
                if(map[k].length > 1){
                    map[k].forEach(function($t){
                        $t.append('<td class="wtm-row-msg" style="color:#b94a48">Duplicate name</td>');
                    });
                }
            }
            updateSubmitState();
        }
        function updateSubmitState(){
            var hasExists = $('.wtm-bulk-name.wtm-name-exists').length > 0;
            var hasInlineDup = $('.wtm-row-msg').filter(function(){ return $(this).text().toLowerCase().indexOf('duplicate') !== -1; }).length > 0;
            if (hasExists || hasInlineDup) $('#wtm_bulk_import_selected').prop('disabled', true).attr('aria-disabled','true');
            else $('#wtm_bulk_import_selected').prop('disabled', false).removeAttr('aria-disabled');
        }
        $(document).on('mousedown', '.wtm-name-suggest li', function(e){
            var $li=$(this);
            if($li.hasClass('wtm-suggest-header')) return;
            var $input=$li.closest('td').find('.wtm-bulk-name');
            $input.val($li.text());
            $li.parent().remove();
            validateNameExists($input);
            e.preventDefault();
        });
        $(document).on('blur', '.wtm-bulk-name', function(){
            validateNameExists($(this));
            setTimeout(()=>{$(this).next('.wtm-name-suggest').remove();},200);
        });

        // Description suggestions for textarea (show shorter matches)
        $(document).on('input', '.wtm-bulk-description', function(){ showSuggestions($(this), descDict, 'wtm-desc-suggest'); });
        $(document).on('mousedown', '.wtm-desc-suggest li', function(e){
            var $li=$(this);
            if($li.hasClass('wtm-suggest-header')) return;
            var $ta=$li.closest('td').find('.wtm-bulk-description');
            $ta.val($li.text());
            $li.parent().remove();
            e.preventDefault();
        });
        $(document).on('blur', '.wtm-bulk-description', function(){ setTimeout(()=>{$(this).next('.wtm-desc-suggest').remove();},200); });

        // Category suggestions (existing behavior)
        $(document).on('input', '.wtm-bulk-category', function(){ showSuggestions($(this), categoryDict, 'wtm-cat-suggest'); });
        // also attach suggestions to the per-row "add new category" input
        $(document).on('input', '.wtm-bulk-new-category', function(){ showSuggestions($(this), categoryDict, 'wtm-cat-suggest'); });
        // clicking a suggestion should fill either the select or the new-category input in the same cell
        $(document).on('mousedown', '.wtm-cat-suggest li', function(e){
            var $li=$(this);
            if($li.hasClass('wtm-suggest-header')) return;
            var $td = $li.closest('td');
            var $newInput = $td.find('.wtm-bulk-new-category');
            if($newInput.length){
                $newInput.val($li.text());
            } else {
                var $input=$td.find('.wtm-bulk-category');
                $input.val($li.text());
            }
            $li.parent().remove();
            e.preventDefault();
        });
        $(document).on('blur', '.wtm-bulk-category, .wtm-bulk-new-category', function(){ setTimeout(()=>{$(this).next('.wtm-cat-suggest').remove();},200); });

        // Validate rows on submit: inline messages for name/price/category; keep high-price confirm
        $(document).on('submit', '#wtm_bulk_add_form', function(e) {
            // if user entered new category in the "add new" input, copy into select so server receives it
            $('#wtm_bulk_add_table tbody tr').each(function(){
                var $tr = $(this);
                // trim name and description inputs to remove leading/trailing spaces
                $tr.find('.wtm-bulk-name').val(function(i,v){ return $.trim(v || ''); });
                $tr.find('.wtm-bulk-description').val(function(i,v){ return $.trim(v || ''); });
                var newCat = $.trim($tr.find('.wtm-bulk-new-category').val());
                var $sel = $tr.find('.wtm-bulk-category');
                if (newCat !== ''){
                    if ($sel.find('option[value="'+newCat+'"]').length === 0) {
                        $sel.append('<option value="'+escapeHtml(newCat)+'">'+escapeHtml(newCat)+'</option>');
                    }
                    $sel.val(newCat);
                }
            });

            // ensure rows are properly indexed (in case of dynamic add/remove)
            function reindexRows(){
                $('#wtm_bulk_add_table tbody tr').each(function(i){
                    $(this).find('input[name], textarea[name], select[name]').each(function(){
                        var name = $(this).attr('name');
                        if(!name) return;
                        var newName = name.replace(/rows\[\d+\]/,'rows['+i+']');
                        $(this).attr('name', newName);
                    });
                });
            }
            reindexRows();

            // clear previous row messages
            $('#wtm_bulk_add_table tbody tr').find('.wtm-row-msg').remove();

            var invalidPrice = false; var priceRow = -1; var missingName = false; var duplicateName = false; var dupRow = -1;
            var seenNames = {};
            $(this).find('tbody tr').each(function(i){
                var $tr = $(this);
                var name = $.trim($tr.find('.wtm-bulk-name').val());
                var description = $.trim($tr.find('.wtm-bulk-description').val());
                var price = $.trim($tr.find('.wtm-bulk-price').val());
                var category = $.trim($tr.find('.wtm-bulk-category').val());
                var new_category = $.trim($tr.find('.wtm-bulk-new-category').val());

                // If the row is entirely blank, skip validation for it
                if (name === '' && description === '' && price === '' && category === '' && new_category === '') {
                    return; // Continue to next row
                }

                if (!name) { missingName = true; $tr.append('<td class="wtm-row-msg" style="color:#b94a48">Name required</td>'); }
                else {
                    // check duplicate within form
                    var lower = name.toLowerCase();
                    if (seenNames[lower] !== undefined) { duplicateName = true; dupRow = i+1; $tr.append('<td class="wtm-row-msg" style="color:#b94a48">Duplicate name</td>'); }
                    seenNames[lower] = i;
                    // check against existing names from server dictionary
                    if (typeof nameDict !== 'undefined' && nameDict.indexOf && nameDict.map) {
                        // case-sensitive array from server; check case-insensitively
                        for(var ni=0; ni<nameDict.length; ni++){ if(nameDict[ni].toLowerCase() === lower){ duplicateName = true; dupRow = i+1; $tr.append('<td class="wtm-row-msg" style="color:#b94a48">Duplicate name</td>'); break; } }
                    }
                }
                var price = $.trim($tr.find('.wtm-bulk-price').val());
                if (price) {
                    // allow formats like 9.99 or 9 or .99
                    if (!/^[0-9]*\.?[0-9]+$/.test(price)) { invalidPrice = true; priceRow = i+1; $tr.append('<td class="wtm-row-msg" style="color:#b94a48">Invalid price</td>'); return false; }
                }
            });
            if (missingName) { alert('One or more rows missing Name. Please fill them in.'); e.preventDefault(); return false; }
            if (invalidPrice) { alert('Invalid price format on row '+priceRow+'. Use numeric decimal format like 9.99'); e.preventDefault(); return false; }
            if (duplicateName) { alert('Duplicate name detected on row '+dupRow+'. Names must be unique.'); e.preventDefault(); return false; }
            // check for any price over 100 and require explicit confirm
            var highPrice = false;
            $(this).find('tbody tr').each(function(i){
                var price = $.trim($(this).find('.wtm-bulk-price').val());
                if (price && /^[0-9]*\.?[0-9]+$/.test(price) && parseFloat(price) > 100) highPrice = true;
            });
            if (highPrice) {
                if (!confirm('One or more items have price over 100. Are you sure you want to import these?')) { e.preventDefault(); return false; }
                // append a hidden confirmation input so server knows user confirmed
                if (!$(this).find('input[name="confirm_high_price"]').length) $(this).append('<input type="hidden" name="confirm_high_price" value="1" />');
            }
            // allow submit otherwise
        });
    var $paste = $('#wtm_bulk_paste');
    var $controls = $('#wtm_bulk_ui_controls');
    var $preview = $('#wtm_bulk_ui_preview');

    // persistent state
    var rows = [];
    var mapping = {};
    var undoStack = [];

    function splitLine(line){
        if (line.indexOf('|') !== -1) return $.map(line.split('|'), $.trim);
        if (line.indexOf('\t') !== -1) return $.map(line.split('\t'), $.trim);
        if (line.indexOf(',') !== -1) return $.map(line.split(','), $.trim);
        // fallback split on 2+ spaces
        return $.map(line.split(/\s{2,}/), $.trim);
    }

    function parseText(text){
        var lines = text.split(/\r?\n/);
        var rows = [];
        $.each(lines,function(i,l){
            if(!$.trim(l)) return;
            rows.push(splitLine(l));
        });
        return rows;
    }

    function renderMapping(cols){
        var html = '<div class="wtm-mapping">';
        html += '<p>' + (cols.length) + ' columns detected. Map columns to fields:</p>';
        html += '<div class="wtm-map-row">';
        for (var i=0;i<cols.length;i++){
            html += '<div class="wtm-map-col">Column '+(i+1)+':<br/>' +
                '<select class="wtm-map-select" data-col="'+i+'">' +
                '<option value="name">Name</option>' +
                '<option value="description">Description</option>' +
                '<option value="price">Price</option>' +
                '<option value="category">Category</option>' +
                '<option value="ignore">Ignore</option>' +
                '</select>' +
                '</div>';
        }
        html += '</div></div>';
        $controls.html(html);
    }

    function renderPreview(){
        if(!rows.length){ $preview.html('<em>No rows parsed</em>'); return; }
        var html = '<table class="wtm-bulk-table"><thead><tr><th></th><th>Name</th><th>Description</th><th>Price</th><th>Category</th></tr></thead><tbody>';
        $.each(rows,function(i,r){
            html += '<tr data-row="'+i+'">';
            html += '<td><input type="checkbox" class="wtm-row-select" checked/></td>';
            var values = {name:'',description:'',price:'',category:''};
            for(var c=0;c< r.length;c++){
                var map = mapping && mapping[c] ? mapping[c] : (c==0?'name':(c==1?'description':(c==2?'price':'category')));
                values[map] = r[c];
            }
            html += '<td contenteditable="true" class="wtm-cell wtm-cell-name">'+escapeHtml(values.name)+'</td>';
            html += '<td contenteditable="true" class="wtm-cell wtm-cell-desc">'+escapeHtml(values.description)+'</td>';
            html += '<td contenteditable="true" class="wtm-cell wtm-cell-price">'+escapeHtml(values.price)+'</td>';
            html += '<td contenteditable="true" class="wtm-cell wtm-cell-cat">'+escapeHtml(values.category)+'</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        $preview.html(html);
    }

    function escapeHtml(s){ return $('<div/>').text(s).html(); }

    $('#wtm_bulk_parse').on('click', function(){
        rows = parseText($paste.val());
        var cols = 0; $.each(rows,function(i,r){ cols = Math.max(cols, r.length); });
        renderMapping(Array(cols));
        // default mapping
        mapping = {};
        for(var i=0;i<cols;i++) mapping[i] = (i==0?'name':(i==1?'description':(i==2?'price':'category')));
        renderPreview();
        // attach mapping change handler (also supported via delegated handler below)
        $controls.find('.wtm-map-select').on('change', function(){
            mapping = {};
            $controls.find('.wtm-map-select').each(function(){ mapping[parseInt($(this).data('col'))] = $(this).val(); });
            renderPreview();
        });

    // delegated handler so re-rendered mapping selects still update mapping
    $controls.on('change', '.wtm-map-select', function(){
        mapping = {};
        $controls.find('.wtm-map-select').each(function(){ mapping[parseInt($(this).data('col'))] = $(this).val(); });
        renderPreview();
    });

    // Add / remove row handlers
    $('#wtm_bulk_add_row').on('click', function(){
        var $last = $('#wtm_bulk_add_table tbody tr:last');
        var $new = $last.clone();
        // clear values
        $new.find('input').val('');
        $new.find('textarea').val('');
        $new.find('select').val('');
        // remove any suggestion lists
        $new.find('.wtm-name-suggest, .wtm-desc-suggest, .wtm-cat-suggest').remove();
        $('#wtm_bulk_add_table tbody').append($new);
        // reindex names
        $('#wtm_bulk_add_table tbody tr').each(function(i){
            $(this).find('input[name], textarea[name], select[name]').each(function(){
                var name = $(this).attr('name');
                if(!name) return;
                var newName = name.replace(/rows\[\d+\]/,'rows['+i+']');
                $(this).attr('name', newName);
            });
        });
        // re-evaluate duplicates/state after adding
        setTimeout(function(){ checkInlineDuplicates(); }, 50);
        // focus the Name field of the newly added row so user can type immediately
        setTimeout(function(){ $('#wtm_bulk_add_table tbody tr:last .wtm-bulk-name').focus().select(); }, 80);
    });

    $(document).on('click', '.wtm-bulk-remove-row', function(){
        if ($('#wtm_bulk_add_table tbody tr').length <= 1) { alert('At least one row required'); return; }
        $(this).closest('tr').remove();
        // reindex
        $('#wtm_bulk_add_table tbody tr').each(function(i){
            $(this).find('input[name], textarea[name], select[name]').each(function(){
                var name = $(this).attr('name');
                if(!name) return;
                var newName = name.replace(/rows\[\d+\]/,'rows['+i+']');
                $(this).attr('name', newName);
            });
        });
        // re-evaluate duplicates/state after removing
        setTimeout(function(){ checkInlineDuplicates(); }, 50);
    });

    // When user presses Tab on the category field of the last row, automatically add a new row
    // This prevents tabbing into the delete button and streamlines rapid data entry.
    $(document).on('keydown', '.wtm-bulk-category, .wtm-bulk-new-category', function(e){
        // Tab key only, ignore Shift+Tab
        if (e.which !== 9 || e.shiftKey) return;
        var $input = $(this);
        var $tr = $input.closest('tr');
        var $tbody = $('#wtm_bulk_add_table tbody');
        if ($tr.is($tbody.find('tr').last())) {
            e.preventDefault();
            // trigger the Add Row button which clones and appends a new row
            $('#wtm_bulk_add_row').trigger('click');
            // focus the Name field of the newly added row
            setTimeout(function(){ $tbody.find('tr:last .wtm-bulk-name').focus(); }, 50);
        }
    });
    });

    // Paste into column helper functions
    function getColIndexForField(field){
        for(var c in mapping){ if(mapping[c] === field) return parseInt(c); }
        return null;
    }

    function ensureColExists(c){
        for(var i=0;i<rows.length;i++){ while(rows[i].length <= c) rows[i].push(''); }
    }

    // Paste into column action
    $('#wtm_paste_into_col').on('click', function(){
        var target = $('#wtm_paste_target').val();
        var behavior = $('#wtm_paste_behavior').val();
        var delim = $('#wtm_paste_delim').val();
        var text = $('#wtm_paste_column_text').val();
        if(!text) return alert('No paste data provided');

        var lines = [];
        if(delim === 'comma') lines = text.split(/\s*,\s*/);
        else if(delim === 'tab') lines = text.split(/\t+/);
        else lines = text.split(/\r?\n/);
        lines = $.map(lines,function(l){ return $.trim(l); });
        lines = $.grep(lines, function(v){ return v !== ''; });

        // store undo
        undoStack.push($.extend(true, [], rows));
        // find or create col index
        var col = getColIndexForField(target);
        if(col === null){
            // create as new column at end
            col = 0; for(var i in mapping) col = Math.max(col, parseInt(i)); col = col+1;
            mapping[col] = target;
            // ensure existing rows have this column
            ensureColExists(col);
            // re-render mapping UI to include new column
            var cols = 0; $.each(rows,function(i,r){ cols = Math.max(cols, r.length); });
            renderMapping(Array(cols));
            // set newly created mapping select value
            $controls.find('.wtm-map-select[data-col="'+col+'"]').val(target);
        }

        // apply lines according to behavior
        if(behavior === 'overwrite'){
            for(var i=0;i<lines.length;i++){
                if(i < rows.length){ ensureColExists(col); rows[i][col] = lines[i]; }
                else { var newRow = []; for(var j=0;j<=col;j++) newRow.push(''); newRow[col] = lines[i]; rows.push(newRow); }
            }
        } else if(behavior === 'fill'){
            for(var i=0;i<lines.length;i++){
                if(i < rows.length){ ensureColExists(col); if(!rows[i][col] || rows[i][col] === '') rows[i][col] = lines[i]; }
                else { var newRow = []; for(var j=0;j<=col;j++) newRow.push(''); newRow[col] = lines[i]; rows.push(newRow); }
            }
        } else { // append
            for(var i=0;i<lines.length;i++){
                var newRow = []; for(var j=0;j<=col;j++) newRow.push(''); newRow[col] = lines[i]; rows.push(newRow);
            }
        }

        renderPreview();
    });

    // undo last paste
    $('#wtm_paste_undo').on('click', function(){
        if(!undoStack.length) return alert('Nothing to undo');
        rows = undoStack.pop();
        renderPreview();
    });

    // Import selected rows
    $('#wtm_bulk_import_selected').on('click', function(){
        // If the table-style form exists, let the normal form submit handler handle this button
        if ($('#wtm_bulk_add_form').length) return;
        var rows = [];
        $preview.find('tbody tr').each(function(){
            var $tr = $(this);
            if (!$tr.find('.wtm-row-select').is(':checked')) return;
            var name = $tr.find('.wtm-cell-name').text().trim();
            var description = $tr.find('.wtm-cell-desc').text().trim();
            var price = $tr.find('.wtm-cell-price').text().trim();
            var category = $tr.find('.wtm-cell-cat').text().trim();
            rows.push({name:name,description:description,price:price,category:category});
        });
        if (!rows.length) return alert('No rows selected to import');
        // build form and submit
        var $form = $('<form method="post" action="'+ajaxurl.replace('admin-ajax.php','admin-post.php')+'" />');
        $form.append('<input type="hidden" name="action" value="wtm_bulk_add_import" />');
        $form.append('<input type="hidden" name="wtm_bulk_add_ui_nonce" value="'+$('input[name="wtm_bulk_add_ui_nonce"]').val()+'" />');
        $.each(rows,function(i,r){
            $form.append('<input type="hidden" name="rows['+i+'][name]" value="'+escapeHtml(r.name)+'" />');
            $form.append('<input type="hidden" name="rows['+i+'][description]" value="'+escapeHtml(r.description)+'" />');
            $form.append('<input type="hidden" name="rows['+i+'][price]" value="'+escapeHtml(r.price)+'" />');
            $form.append('<input type="hidden" name="rows['+i+'][category]" value="'+escapeHtml(r.category)+'" />');
        });
        $('body').append($form);
        $form.submit();
    });

    // Reuse category create button if present
    $(document).on('click','#wtm_create_category', function(){
        var name = $('#wtm_new_category').val();
        if(!name) return alert('Please enter a category name');
        var ajaxObj = (typeof wtmBulkUI !== 'undefined' ? wtmBulkUI : (typeof wtmBulk !== 'undefined' ? wtmBulk : (typeof wtmCSV !== 'undefined' ? wtmCSV : {})));
        $.post(ajaxObj.ajax_url || ajaxurl, { action:'wtm_create_category', name:name, nonce: ajaxObj.nonce || '' }, function(resp){
            if(resp && resp.success){ alert('Category "'+resp.data.name+'" created (id:'+resp.data.term_id+')'); $('#wtm_new_category').val(''); }
            else alert('Error: '+(resp && resp.data ? resp.data : 'unknown'));
        });
    });
    // initial validation pass for restored rows (if any) to set button state
    setTimeout(function(){
        $('.wtm-bulk-name').each(function(){ validateNameExists($(this)); });
        checkInlineDuplicates();
    }, 60);
});