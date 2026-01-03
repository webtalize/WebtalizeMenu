<?php
// Meta boxes for price and description
// 
function wtm_add_price_meta_boxes() {
    add_meta_box('wtm_price_meta_box', __('Price', 'webtalize-menu'), 'wtm_price_meta_box_callback', 'menu_item', 'normal', 'default');
}
add_action('add_meta_boxes', 'wtm_add_price_meta_boxes');

function wtm_add_description_meta_boxes() {
    add_meta_box('wtm_description_meta_box', __('Short Description', 'webtalize-menu'), 'wtm_description_meta_box_callback', 'menu_item', 'normal', 'default'); // Below the editor
}
add_action('add_meta_boxes', 'wtm_add_description_meta_boxes');

function wtm_price_meta_box_callback($post) {
    wp_nonce_field('wtm_meta_box_nonce', 'wtm_meta_box_nonce');
    $price = get_post_meta($post->ID, 'wtm_price', true);
    echo '<label for="wtm_price">' . esc_html__('Price:', 'webtalize-menu') . '</label>';
    echo '<input type="text" id="wtm_price" name="wtm_price" value="' . esc_attr($price) . '" size="10" />';
}

function wtm_description_meta_box_callback($post) {
    $description = get_post_meta($post->ID, 'wtm_description', true);
    wp_nonce_field('wtm_meta_box_nonce', 'wtm_meta_box_nonce');
    echo '<label for="wtm_description">' . esc_html__('Description:', 'webtalize-menu') . '</label>';
    echo '<textarea id="wtm_description" name="wtm_description" rows="4" cols="50">' . esc_textarea($description) . '</textarea>'; // Use esc_textarea
}


function wtm_save_meta_boxes_data($post_id) {
    if (!isset($_POST['wtm_meta_box_nonce']) || !wp_verify_nonce($_POST['wtm_meta_box_nonce'], 'wtm_meta_box_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

        if (isset($_POST['post_type']) && 'menu_item' != $_POST['post_type']) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['wtm_price'])) {
        $price = sanitize_text_field($_POST['wtm_price']);
        if (!empty($price) && !is_numeric($price)) {
            $price = '';
        }
        update_post_meta($post_id, 'wtm_price', $price);
    } else {
        delete_post_meta($post_id, 'wtm_price');
    }

    if (isset($_POST['wtm_description'])) {
        $description = sanitize_textarea_field($_POST['wtm_description']); // Sanitize textarea
        update_post_meta($post_id, 'wtm_description', $description);
    } else {
        delete_post_meta($post_id, 'wtm_description');
    }
}
add_action('save_post', 'wtm_save_meta_boxes_data');

// Add columns to the Menu Items list table
add_filter('manage_menu_item_posts_columns', 'wtm_add_columns_to_menu_items_table');
function wtm_add_columns_to_menu_items_table($columns) {
    $new_columns = array();
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title; // Copy existing columns first
        if ($key == 'date') { // Insert new columns before date
            $new_columns['wtm_price'] = __('Price', 'webtalize-menu');
            $new_columns['wtm_description'] = __('Description', 'webtalize-menu');
            $new_columns['menu_order'] = __('Order', 'webtalize-menu');
        }
    }
    return $new_columns; // Now correctly returns the full modified array
}

// Display data in custom columns
add_action('manage_menu_item_posts_custom_column', 'wtm_display_custom_columns', 10, 2);
function wtm_display_custom_columns($column, $post_id) {
    switch ($column) {
        case 'wtm_price':
            $price = get_post_meta($post_id, 'wtm_price', true);
            if (!empty($price)) {
                echo esc_html('$' . $price);
            }
            break;
        case 'wtm_description':
            $description = get_post_meta($post_id, 'wtm_description', true);
            if (!empty($description)) {
                echo esc_html($description);
            }
            break;
        case 'menu_order':
            $p = get_post($post_id);
            echo $p->menu_order;
            break;
    }
}

// Make columns sortable
add_filter( 'manage_edit-menu_item_sortable_columns', 'wtm_sortable_columns' );
function wtm_sortable_columns( $columns ) {
    $columns['menu_order'] = 'menu_order';
    return $columns;
}



// Add fields to Quick Edit
// Add fields to Quick Edit and Nonce
add_action('quick_edit_custom_box', 'wtm_add_quick_edit_fields', 10, 2);
add_action( 'bulk_edit_custom_box', 'wtm_add_quick_edit_fields', 10, 2 );
function wtm_add_quick_edit_fields($column_name, $post_type) {
    if ($post_type != 'menu_item') return;

    static $printNonce = TRUE;
    if ( $printNonce ) {
        $printNonce = FALSE;
        wp_nonce_field( 'wtm_quick_edit_nonce', 'wtm_quick_edit_nonce' );
    }

    switch ($column_name) {
        case 'wtm_price':
            ?>
            <fieldset class="inline-edit-col-right">
                <div class="inline-edit-col">
                    <span class="title"><?php _e('Price', 'webtalize-menu'); ?></span>
                    <input type="text" name="wtm_price" class="wtm_price_input"/>
                </div>
            </fieldset>
            <?php
            break;
        case 'wtm_description':
            ?>
            <fieldset class="inline-edit-col-left">
                <div class="inline-edit-col">
                    <span class="title"><?php _e('Description', 'webtalize-menu'); ?></span>
                    <textarea cols="22" rows="1" name="wtm_description" class="wtm_description_input"></textarea>
                </div>
            </fieldset>
            <?php
            break;
    }
} 


// Save Quick Edit data
add_action('save_post', 'wtm_save_quick_edit_data');
function wtm_save_quick_edit_data($post_id) {
    // Check if doing quick edit
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['post_type']) || 'menu_item' != $_POST['post_type']) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (!isset($_POST['wtm_quick_edit_nonce']) || !wp_verify_nonce($_POST['wtm_quick_edit_nonce'], 'wtm_quick_edit_nonce')) {
        return;
    }

    if (isset($_POST['wtm_price'])) {
        $price = sanitize_text_field($_POST['wtm_price']);
        if (!empty($price) && !is_numeric($price)) {
            $price = '';
        }
        update_post_meta($post_id, 'wtm_price', $price);
    }

    if (isset($_POST['wtm_description'])) {
        $description = sanitize_textarea_field($_POST['wtm_description']);
        update_post_meta($post_id, 'wtm_description', $description);
    }
}









// CSV Import & Bulk Add admin pages
add_action('admin_menu','wtm_add_import_pages');
function wtm_add_import_pages() {
    add_submenu_page('edit.php?post_type=menu_item','CSV Import','CSV Import','edit_posts','wtm-csv-import','wtm_csv_import_page');
    add_submenu_page('edit.php?post_type=menu_item','Bulk Add Items','Bulk Add Items','edit_posts','wtm-bulk-add','wtm_bulk_add_page');
}

// Fallback Tools menu entries in case CPT submenu is not visible to some roles/sites
add_action('admin_menu','wtm_add_tools_pages');
function wtm_add_tools_pages() {
    add_management_page('Webtalize Menu: CSV Import','Webtalize Menu: CSV Import','manage_options','wtm-csv-import-tools','wtm_csv_import_page');
    add_management_page('Webtalize Menu: Bulk Add Items','Webtalize Menu: Bulk Add Items','manage_options','wtm-bulk-add-tools','wtm_bulk_add_page');
}

// Top-level menu for quick access
add_action('admin_menu','wtm_add_top_level_menu');
function wtm_add_top_level_menu() {
    add_menu_page('Webtalize Menu','Webtalize Menu','manage_options','wtm-main','wtm_main_page','dashicons-food',25.5);
    // add quick links as submenus
    add_submenu_page('wtm-main','All Menu Items','All Menu Items','edit_posts','edit.php?post_type=menu_item');
    // add Menu Categories link to manage taxonomy terms
    add_submenu_page('wtm-main','Menu Categories','Menu Categories','manage_options','edit-tags.php?taxonomy=menu_category&post_type=menu_item');
    add_submenu_page('wtm-main','CSV Import','CSV Import','edit_posts','wtm-csv-import','wtm_csv_import_page');
    add_submenu_page('wtm-main','Reorder Items','Reorder Items','edit_posts','wtm-reorder','wtm_reorder_page');
    add_submenu_page('wtm-main','Bulk Add Items','Bulk Add Items','edit_posts','wtm-bulk-add','wtm_bulk_add_page');
}

function wtm_main_page() {
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Webtalize Menu', 'webtalize-menu') . '</h1>';
    echo '<p>' . esc_html__('Quick links:', 'webtalize-menu') . '</p>';
    echo '<ul>';
    echo '<li><a href="' . esc_url(admin_url('edit.php?post_type=menu_item')) . '">' . esc_html__('All Menu Items', 'webtalize-menu') . '</a></li>';
    echo '<li><a href="' . esc_url(admin_url('edit.php?post_type=menu_item&page=wtm-csv-import')) . '">' . esc_html__('CSV Import', 'webtalize-menu') . '</a></li>';
    echo '<li><a href="' . esc_url(admin_url('edit.php?post_type=menu_item&page=wtm-bulk-add')) . '">' . esc_html__('Bulk Add Items', 'webtalize-menu') . '</a></li>';
    echo '</ul>';
    echo '</div>';
}

add_action('admin_enqueue_scripts','wtm_enqueue_admin_scripts');
function wtm_enqueue_admin_scripts($hook) {
    global $pagenow;

    // Enqueue Quick Edit script on the menu items list table
    if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'menu_item') {
        wp_enqueue_script('wtm-quick-edit', WTM_PLUGIN_URL . 'js/wtm-quick-edit.js', array('jquery', 'wp-data'), '1.0.1', true);
    }
    
    // allow CPT pages, Tools fallback pages and top-level menu page
    if (false === strpos($hook,'wtm-csv-import') && false === strpos($hook,'wtm-bulk-add') && false === strpos($hook,'wtm-main') && false === strpos($hook,'wtm-reorder')) return;
    if (false !== strpos($hook,'wtm-csv-import')) {
        wp_enqueue_script('wtm-csv-import', WTM_PLUGIN_URL.'js/wtm-csv-import.js', array('jquery'), '1.0.0', true);
        wp_localize_script('wtm-csv-import','wtmCSV', array('ajax_url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('wtm_ajax_nonce')));
    }
    if (false !== strpos($hook,'wtm-bulk-add')) {
        wp_enqueue_script('wtm-bulk-add-ui', WTM_PLUGIN_URL.'js/wtm-bulk-add-ui.js', array('jquery'), '1.0.0', true);
        wp_localize_script('wtm-bulk-add-ui','wtmBulkUI', array('ajax_url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('wtm_ajax_nonce')));
    }
    if (false !== strpos($hook,'wtm-reorder')) {
        wp_enqueue_script('jquery-ui-sortable');
    }
    wp_enqueue_style('wtm-admin', WTM_PLUGIN_URL.'css/wtm-admin.css', array(), '1.0.0');
}

function wtm_csv_import_page() {
    // show any notices
    if (isset($_GET['wtm_bulk_added'])) {
        $count = intval($_GET['wtm_bulk_added']);
        echo '<div class="notice notice-success"><p>'.esc_html($count).' menu items added.</p></div>';
    }
    if (isset($_GET['wtm_bulk_errors'])) {
        $errors = sanitize_text_field(wp_unslash($_GET['wtm_bulk_errors']));
        echo '<div class="notice notice-error"><p>'.esc_html($errors).'</p></div>';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Content Import', 'webtalize-menu'); ?></h1>
        <p><?php esc_html_e('Paste your menu items below. The importer will attempt to automatically detect the "name", "price", "description", and "category" columns. If you provide a header row (e.g., "name,price,category"), it will be used for mapping.', 'webtalize-menu'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wtm_csv_import_nonce','wtm_csv_import_nonce'); ?>
            <input type="hidden" name="action" value="wtm_csv_import" />
            <textarea name="wtm_bulk_text" id="wtm_bulk_text" rows="12" cols="80" placeholder="My Awesome Burger,15.99,Main Courses,A delicious burger.
Pizza,22.50,Main Courses"></textarea>
            <p>
                <label>
                    <input type="checkbox" name="wtm_auto_detect" value="1" />
                    <?php esc_html_e('Auto-detect format and columns. Check this if your data is not a standard CSV or has no header.', 'webtalize-menu'); ?>
                </label>
            </p>
            <p>
                <label for="wtm_new_category"><?php esc_html_e('Create category on the fly:', 'webtalize-menu'); ?></label>
                <input type="text" id="wtm_new_category" />
                <button type="button" class="button" id="wtm_create_category"><?php esc_html_e('Add Category', 'webtalize-menu'); ?></button>
            </p>
            <p>
                <input type="submit" class="button button-primary" value="<?php esc_attr_e('Import Items', 'webtalize-menu'); ?>" />
            </p>
        </form>

        <h2><?php esc_html_e('Preview', 'webtalize-menu'); ?></h2>
        <div id="wtm_bulk_preview"></div>
    </div>
    <?php
}

add_action('admin_post_wtm_csv_import','wtm_handle_csv_import');
function wtm_handle_csv_import() {
    if (!current_user_can('edit_posts')) wp_die('Insufficient permissions');
    if (!isset($_POST['wtm_csv_import_nonce']) || !wp_verify_nonce($_POST['wtm_csv_import_nonce'],'wtm_csv_import_nonce')) wp_die('Security check failed');

    $parsed = [];
    if (isset($_POST['wtm_bulk_json'])) {
        $parsed = json_decode(wp_unslash($_POST['wtm_bulk_json']), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $parsed = []; // or set an error
        }
    } else {
        $text = isset($_POST['wtm_bulk_text']) ? wp_unslash($_POST['wtm_bulk_text']) : '';
        if (isset($_POST['wtm_auto_detect'])) {
            $parsed = wtm_intelligent_parse_bulk_text($text);
        } else {
            $parsed = wtm_parse_bulk_text($text);
        }
    }

    $created = 0;
    $errors = array();
    foreach ($parsed as $i => $item) {
        $name = isset($item['name']) ? trim(sanitize_text_field($item['name'])) : '';
        $description = isset($item['description']) ? trim(sanitize_textarea_field($item['description'])) : '';
        $price = isset($item['price']) ? sanitize_text_field($item['price']) : '';
        $category = isset($item['category']) ? sanitize_text_field($item['category']) : '';

        if (empty($name)) {
            $errors[] = "Row ".($i+1)." missing name";
            continue;
        }

        // Ensure category
        $term_id = 0;
        if (!empty($category)) {
            $term = term_exists($category, 'menu_category');
            if ($term === 0 || $term === null) {
                $t = wp_insert_term($category, 'menu_category');
                if (is_wp_error($t)) {
                    $errors[] = "Row ".($i+1)." category error: ".$t->get_error_message();
                } else {
                    $term_id = $t['term_id'];
                }
            } else {
                $term_id = is_array($term) ? $term['term_id'] : $term;
            }
        }

        // Create post
        $postarr = array(
            'post_title' => $name,
            'post_content' => $description,
            'post_status' => 'publish',
            'post_type' => 'menu_item',
        );
        $post_id = wp_insert_post($postarr);
        if (is_wp_error($post_id) || !$post_id) {
            $errors[] = "Row ".($i+1)." could not create post";
            continue;
        }
        if (!empty($price)) update_post_meta($post_id, 'wtm_price', $price);
        if (!empty($description)) update_post_meta($post_id, 'wtm_description', $description);
        if ($term_id) wp_set_post_terms($post_id, array(intval($term_id)), 'menu_category', false);
        $created++;
    }

    $redirect = add_query_arg('wtm_bulk_added', $created, admin_url('edit.php?post_type=menu_item&page=wtm-csv-import'));
    if (!empty($errors)) {
        $err = implode('; ', $errors);
        $redirect = add_query_arg('wtm_bulk_errors', urlencode($err), $redirect);
    }
    wp_redirect($redirect);
    exit;
}

function wtm_bulk_add_page() {
    // Bulk Add UI: table-style entry
    if (isset($_GET['wtm_bulk_added'])) {
        $count = intval($_GET['wtm_bulk_added']);
        echo '<div class="notice notice-success"><p>'.esc_html($count).' menu items added.</p></div>';
    }
    if (isset($_GET['wtm_bulk_errors'])) {
        $errors = sanitize_text_field(wp_unslash($_GET['wtm_bulk_errors']));
        echo '<div class="notice notice-error"><p>'.esc_html($errors).'</p></div>';
    }
    $categories = get_terms(array('taxonomy'=>'menu_category','hide_empty'=>false));
    $category_names = array();
    foreach($categories as $cat) { $category_names[] = $cat->name; }
    // Build name and description dictionaries from existing menu_item posts
    $name_dict = array();
    $desc_dict = array();
    $posts = get_posts(array('post_type'=>'menu_item','posts_per_page'=>-1,'post_status'=>'any'));
    foreach($posts as $p) {
        if (!empty($p->post_title)) $name_dict[] = $p->post_title;
        if (!empty($p->post_content)) {
            $text = wp_strip_all_tags($p->post_content);
            $text = trim(preg_replace('/\s+/',' ', $text));
            if ($text) $desc_dict[] = mb_substr($text,0,200);
        }
    }
    // unique
    $category_names = array_values(array_unique($category_names));
    $name_dict = array_values(array_unique($name_dict));
    $desc_dict = array_values(array_unique($desc_dict));
    // Output JS dictionaries
    echo '<script>window.wtmCategoryDict = '.json_encode($category_names).';window.wtmNameDict = '.json_encode($name_dict).';window.wtmDescDict = '.json_encode($desc_dict).';</script>';
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Bulk Add Menu Items', 'webtalize-menu'); ?></h1>
        <p><?php esc_html_e('Add multiple menu items at once. Use the table below. You can add/remove rows as needed.','webtalize-menu'); ?></p>
        <form id="wtm_bulk_add_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="wtm_bulk_add_import" />
        <?php wp_nonce_field('wtm_bulk_add_ui_nonce','wtm_bulk_add_ui_nonce'); ?>
        <table id="wtm_bulk_add_table" class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name','webtalize-menu'); ?></th>
                    <th><?php esc_html_e('Description','webtalize-menu'); ?></th>
                    <th><?php esc_html_e('Price','webtalize-menu'); ?></th>
                    <th><?php esc_html_e('Category','webtalize-menu'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $restore_key = 'wtm_bulk_rows_' . get_current_user_id();
                $restore_rows = get_transient($restore_key);
                $restore_errors = get_transient('wtm_bulk_row_errors_' . get_current_user_id());
                if ($restore_rows && is_array($restore_rows)) {
                    // remove after reading so it's one-time
                    delete_transient($restore_key);
                    if ($restore_errors && is_array($restore_errors)) delete_transient('wtm_bulk_row_errors_' . get_current_user_id());
                    $i = 0;
                    foreach ($restore_rows as $rr) {
                        $rname = isset($rr['name']) ? $rr['name'] : '';
                        $rdesc = isset($rr['description']) ? $rr['description'] : '';
                        $rprice = isset($rr['price']) ? $rr['price'] : '';
                        $rcat = isset($rr['category']) ? $rr['category'] : '';
                        ?>
                        <tr>
                            <td><input type="text" name="rows[<?php echo $i; ?>][name]" class="wtm-bulk-name" style="width:100%" value="<?php echo esc_attr($rname); ?>" /></td>
                            <td><textarea name="rows[<?php echo $i; ?>][description]" class="wtm-bulk-description" rows="2" style="width:100%"><?php echo esc_textarea($rdesc); ?></textarea></td>
                            <td><input type="text" name="rows[<?php echo $i; ?>][price]" class="wtm-bulk-price" style="width:100%" value="<?php echo esc_attr($rprice); ?>" /></td>
                            <td>
                                <select name="rows[<?php echo $i; ?>][category]" class="wtm-bulk-category" style="width:100%">
                                    <option value=""><?php esc_html_e('Select category','webtalize-menu'); ?></option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat->name); ?>" <?php selected($rcat, $cat->name); ?>><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="rows[<?php echo $i; ?>][new_category]" class="wtm-bulk-new-category" placeholder="<?php esc_attr_e('Or add new','webtalize-menu'); ?>" style="width:100%;margin-top:2px;" value="<?php echo esc_attr($rcat); ?>" />
                            </td>
                            <td><button type="button" class="button wtm-bulk-remove-row" tabindex="-1" aria-label="Remove row">&times;</button></td>
                        <?php if (isset($restore_errors[$i])): ?>
                            <td class="wtm-row-msg" style="color:#b94a48"><?php echo esc_html($restore_errors[$i]); ?></td>
                        <?php endif; ?>
                        </tr>
                        <?php
                        $i++;
                    }
                } else {
                    // default single empty row
                    ?>
                    <tr>
                        <td><input type="text" name="rows[0][name]" class="wtm-bulk-name" style="width:100%" /></td>
                        <td><textarea name="rows[0][description]" class="wtm-bulk-description" rows="2" style="width:100%"></textarea></td>
                        <td><input type="text" name="rows[0][price]" class="wtm-bulk-price" style="width:100%" /></td>
                        <td>
                            <select name="rows[0][category]" class="wtm-bulk-category" style="width:100%">
                                <option value=""><?php esc_html_e('Select category','webtalize-menu'); ?></option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->name); ?>"><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="rows[0][new_category]" class="wtm-bulk-new-category" placeholder="<?php esc_attr_e('Or add new','webtalize-menu'); ?>" style="width:100%;margin-top:2px;" />
                        </td>
                        <td><button type="button" class="button wtm-bulk-remove-row" tabindex="-1" aria-label="Remove row">&times;</button></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button" id="wtm_bulk_add_row"><?php esc_html_e('Add Row','webtalize-menu'); ?></button>
            <button type="submit" class="button button-primary" id="wtm_bulk_import_selected"><?php esc_html_e('Import All', 'webtalize-menu'); ?></button>
        </p>
        </form>
        <script type="text/javascript">
        (function($){
            $(document).ready(function(){
                $('#wtm_bulk_add_row').on('click', function(){
                    var $last = $('#wtm_bulk_add_table tbody tr:last');
                    var $new = $last.clone();
                    $new.find('input').val('');
                    $new.find('textarea').val('');
                    $new.find('select').val('');
                    $new.find('.wtm-name-suggest, .wtm-desc-suggest, .wtm-cat-suggest').remove();
                    $('#wtm_bulk_add_table tbody').append($new);
                    $new.find('.wtm-bulk-name').focus();
                    // reindex names
                    $('#wtm_bulk_add_table tbody tr').each(function(i){
                        $(this).find('input[name], textarea[name], select[name]').each(function(){
                            var name = $(this).attr('name');
                            if(!name) return;
                            var newName = name.replace(/rows\[\d+\]/,'rows['+i+']');
                            $(this).attr('name', newName);
                        });
                    });
                });
                $(document).on('click','.wtm-bulk-remove-row', function(){
                    if ($('#wtm_bulk_add_table tbody tr').length <= 1) { alert('<?php echo esc_js(__('At least one row required','webtalize-menu')); ?>'); return; }
                    $(this).closest('tr').remove();
                    $('#wtm_bulk_add_table tbody tr').each(function(i){
                        $(this).find('input[name], textarea[name], select[name]').each(function(){
                            var name = $(this).attr('name');
                            if(!name) return;
                            var newName = name.replace(/rows\[\d+\]/,'rows['+i+']');
                            $(this).attr('name', newName);
                        });
                    });
                });
            });
        })(jQuery);
        </script>
        <div id="wtm_bulk_ui_preview"></div>
    </div>
    <?php
}

add_action('admin_post_wtm_bulk_add_import','wtm_handle_bulk_add_ui');
function wtm_handle_bulk_add_ui() {
    if (!current_user_can('edit_posts')) wp_die('Insufficient permissions');
    if (!isset($_POST['wtm_bulk_add_ui_nonce']) || !wp_verify_nonce($_POST['wtm_bulk_add_ui_nonce'],'wtm_bulk_add_ui_nonce')) wp_die('Security check failed');

    $rows = isset($_POST['rows']) && is_array($_POST['rows']) ? $_POST['rows'] : array();
    // If any price > 100 and user hasn't confirmed, block and ask for confirmation
    foreach ($rows as $i => $r) {
        $price_check = isset($r['price']) ? preg_replace('/[^0-9.]/','',$r['price']) : '';
        if ($price_check !== '' && floatval($price_check) > 100 && empty($_POST['confirm_high_price'])) {
            // store user's submitted rows so they are not lost when asking for confirmation
            $restore_key = 'wtm_bulk_rows_' . get_current_user_id();
            $store_rows = array();
            foreach ($rows as $ri => $rr) {
                $store_rows[$ri] = array(
                    'name' => isset($rr['name']) ? sanitize_text_field($rr['name']) : '',
                    'description' => isset($rr['description']) ? sanitize_textarea_field($rr['description']) : '',
                    'price' => isset($rr['price']) ? sanitize_text_field($rr['price']) : '',
                    'category' => isset($rr['category']) ? sanitize_text_field($rr['category']) : '',
                );
            }
            set_transient($restore_key, $store_rows, 300);
            $redirect = add_query_arg(array('wtm_bulk_errors' => urlencode('High price detected. Please confirm to proceed.'), 'wtm_bulk_restore' => 1), admin_url('edit.php?post_type=menu_item&page=wtm-bulk-add'));
            wp_redirect($redirect); exit;
        }
    }
    $created = 0; $errors = array();
    foreach ($rows as $i => $r) {
        $name = isset($r['name']) ? trim(sanitize_text_field($r['name'])) : '';
        $description = isset($r['description']) ? trim(sanitize_textarea_field($r['description'])) : '';
        $price = isset($r['price']) ? sanitize_text_field($r['price']) : '';
        $category = isset($r['category']) ? sanitize_text_field($r['category']) : '';
        $new_category = isset($r['new_category']) ? trim(sanitize_text_field($r['new_category'])) : '';

        if (empty($name) && empty($description) && empty($price) && empty($category) && empty($new_category)) {
            continue;
        }
        
        if (!empty($new_category)) {
            $category = $new_category;
        }

        if (empty($name)) { $errors[] = "Row ".($i+1)." missing name"; continue; }
        // enforce unique name: skip/flag if a menu_item with same title exists
        $existing = get_page_by_title($name, OBJECT, 'menu_item');
        if ($existing && $existing->ID) { $errors[] = "Row ".($i+1)." duplicate name: '{$name}' exists"; continue; }
        $term_id = 0;
        if (!empty($category)) {
            $term = term_exists($category, 'menu_category');
            if ($term === 0 || $term === null) {
                $t = wp_insert_term($category, 'menu_category');
                if (is_wp_error($t)) { $errors[] = "Row ".($i+1)." category error: ".$t->get_error_message(); }
                else { $term_id = $t['term_id']; }
            } else { $term_id = is_array($term) ? $term['term_id'] : $term; }
        }
        $postarr = array('post_title'=>$name,'post_content'=>$description,'post_status'=>'publish','post_type'=>'menu_item');
        $post_id = wp_insert_post($postarr);
        if (is_wp_error($post_id) || !$post_id) { $errors[] = "Row ".($i+1)." could not create post"; continue; }
        if (!empty($price)) update_post_meta($post_id,'wtm_price',$price);
        if (!empty($description)) update_post_meta($post_id,'wtm_description',$description);
        if ($term_id) wp_set_post_terms($post_id, array(intval($term_id)), 'menu_category', false);
        $created++;
    }
    $redirect = add_query_arg('wtm_bulk_added',$created, admin_url('edit.php?post_type=menu_item&page=wtm-bulk-add'));
    if (!empty($errors)) {
        // store rows so user can correct them without retyping
        $restore_key = 'wtm_bulk_rows_' . get_current_user_id();
        $store_rows = array();
        foreach ($rows as $ri => $rr) {
            $store_rows[$ri] = array(
                'name' => isset($rr['name']) ? sanitize_text_field($rr['name']) : '',
                'description' => isset($rr['description']) ? sanitize_textarea_field($rr['description']) : '',
                'price' => isset($rr['price']) ? sanitize_text_field($rr['price']) : '',
                'category' => isset($rr['category']) ? sanitize_text_field($rr['category']) : '',
            );
        }
        set_transient($restore_key, $store_rows, 300);
        // map errors to row indices so the UI can show inline messages
        $row_errors = array();
        foreach ($errors as $err) {
            if (preg_match('/Row\s+(\d+)/i', $err, $m)) {
                $idx = intval($m[1]) - 1;
                $row_errors[$idx] = $err;
            }
        }
        if (!empty($row_errors)) set_transient('wtm_bulk_row_errors_' . get_current_user_id(), $row_errors, 300);
        $err = implode('; ',$errors);
        $redirect = add_query_arg('wtm_bulk_errors', urlencode($err), $redirect);
        $redirect = add_query_arg('wtm_bulk_restore', 1, $redirect);
    }
    wp_redirect($redirect); exit;
}

// AJAX create category
add_action('wp_ajax_wtm_create_category','wtm_create_category_ajax');
function wtm_create_category_ajax() {
    check_ajax_referer('wtm_ajax_nonce','nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error('Insufficient permissions');
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    if (empty($name)) wp_send_json_error('Empty name');
    $term = term_exists($name, 'menu_category');
    if ($term === 0 || $term === null) {
        $t = wp_insert_term($name, 'menu_category');
        if (is_wp_error($t)) wp_send_json_error($t->get_error_message());
        wp_send_json_success(array('term_id'=>$t['term_id'],'name'=>$name));
    } else {
        $term_id = is_array($term) ? $term['term_id'] : $term;
        wp_send_json_success(array('term_id'=>$term_id,'name'=>$name));
    }
}

// Reorder Page
function wtm_reorder_page() {
    $terms = get_terms(array('taxonomy'=>'menu_category','hide_empty'=>false));
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Reorder Menu Items', 'webtalize-menu'); ?></h1>
        <p><?php esc_html_e('Drag and drop items to reorder them within their categories. Changes are saved automatically.', 'webtalize-menu'); ?></p>
        <div id="wtm-reorder-message" style="display:none;margin-bottom:15px;padding:10px;background:#fff;border-left:4px solid #46b450;"></div>
        
        <?php if (empty($terms) || is_wp_error($terms)): ?>
            <p><?php esc_html_e('No categories found.', 'webtalize-menu'); ?></p>
        <?php else: ?>
            <div class="wtm-reorder-container">
                <?php foreach ($terms as $term): ?>
                    <div class="wtm-reorder-section" style="margin-bottom:30px;background:#fff;padding:15px;border:1px solid #ccd0d4;max-width:600px;">
                        <h3 style="margin-top:0;border-bottom:1px solid #eee;padding-bottom:10px;"><?php echo esc_html($term->name); ?></h3>
                        <?php
                        $items = get_posts(array(
                            'post_type' => 'menu_item',
                            'posts_per_page' => -1,
                            'tax_query' => array(array('taxonomy'=>'menu_category','field'=>'term_id','terms'=>$term->term_id)),
                            'orderby' => 'menu_order',
                            'order' => 'ASC'
                        ));
                        ?>
                        <ul class="wtm-sortable-list" data-term-id="<?php echo esc_attr($term->term_id); ?>" style="margin:0;">
                            <?php if ($items): foreach($items as $item): ?>
                                <li id="item_<?php echo $item->ID; ?>" style="cursor:move;padding:10px;background:#f9f9f9;border:1px solid #ddd;margin-bottom:5px;display:flex;justify-content:space-between;">
                                    <strong><?php echo esc_html($item->post_title); ?></strong>
                                    <span style="color:#999;"><?php echo esc_html(get_post_meta($item->ID, 'wtm_price', true)); ?></span>
                                </li>
                            <?php endforeach; else: ?>
                                <li style="color:#999;font-style:italic;"><?php esc_html_e('No items in this category', 'webtalize-menu'); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script type="text/javascript">
    jQuery(document).ready(function($){
        $('.wtm-sortable-list').sortable({
            update: function(event, ui) {
                var $list = $(this);
                var term_id = $list.data('term-id');
                var order = $list.sortable('toArray'); // returns array of "item_ID"
                
                // Feedback
                $('#wtm-reorder-message').hide();
                $list.css('opacity', '0.6');

                $.post(ajaxurl, {
                    action: 'wtm_save_reorder',
                    nonce: '<?php echo wp_create_nonce("wtm_reorder_nonce"); ?>',
                    term_id: term_id,
                    order: order
                }, function(response) {
                    $list.css('opacity', '1');
                    if(response.success) {
                        $('#wtm-reorder-message').text('<?php esc_html_e("Order saved!", "webtalize-menu"); ?>').fadeIn().delay(2000).fadeOut();
                    } else {
                        alert('Error saving order');
                    }
                });
            }
        });
    });
    </script>
    <?php
}

// Save Reorder AJAX
add_action('wp_ajax_wtm_save_reorder', 'wtm_save_reorder_ajax');
function wtm_save_reorder_ajax() {
    check_ajax_referer('wtm_reorder_nonce', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error();

    $order = isset($_POST['order']) ? $_POST['order'] : array();
    if (!is_array($order)) wp_send_json_error();

    foreach ($order as $index => $item_str) {
        // $item_str is "item_123"
        $post_id = intval(str_replace('item_', '', $item_str));
        if ($post_id > 0) {
            wp_update_post(array(
                'ID' => $post_id,
                'menu_order' => $index
            ));
        }
    }
    wp_send_json_success();
}

// AJAX handler for CSV Preview
add_action('wp_ajax_wtm_preview_csv', 'wtm_preview_csv_ajax');
function wtm_preview_csv_ajax() {
    check_ajax_referer('wtm_ajax_nonce', 'nonce');
    if (!current_user_can('edit_posts')) wp_send_json_error();

    $text = isset($_POST['text']) ? wp_unslash($_POST['text']) : '';
    $auto_detect = isset($_POST['auto_detect']) && $_POST['auto_detect'] === 'true';

    $debug = [];
    if ($auto_detect) {
        $items = wtm_intelligent_parse_bulk_text($text, $debug);
    } else {
        $items = wtm_parse_bulk_text($text, $debug);
    }
    
    wp_send_json_success(['items' => $items, 'debug' => $debug]);
}

// Helper to parse bulk text without headers
function wtm_intelligent_parse_bulk_text($text, &$debug = []) {
    $debug[] = 'Starting intelligent parse. Text length: ' . strlen($text);
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines, function($line) { return !empty($line); });

    if (count($lines) < 1) {
        $debug[] = 'No lines found after split/trim.';
        return [];
    }

    // 1. Detect Delimiter (weighted by lines containing numbers)
    $delimiters = [',' => 0, '|' => 0, "\t" => 0, ';' => 0];
    foreach ($lines as $line) {
        // Remove content inside parentheses to avoid false positives for delimiters (e.g. "Item (desc, with, commas)")
        $clean_line = preg_replace('/\([^)]+\)/', '', $line);

        // Give more weight to lines that look like data (contain numbers)
        $weight = preg_match('/\d/', $line) ? 2 : 1;
        $weight = preg_match('/\d/', $clean_line) ? 2 : 1;
        foreach ($delimiters as $delim => &$count) {
            $count += substr_count($line, $delim) * $weight;
            $count += substr_count($clean_line, $delim) * $weight;
        }
    }
    arsort($delimiters);
    $delimiter = key($delimiters);
    if ($delimiters[$delimiter] == 0) $delimiter = ',';
    $debug[] = 'Detected delimiter: ' . $delimiter . ' (score: ' . $delimiters[$delimiter] . ')';
    $delimiter_score = $delimiters[$delimiter];
    if ($delimiter_score == 0) $delimiter = null;
    $debug[] = 'Detected delimiter: ' . ($delimiter ? $delimiter : 'None') . ' (score: ' . $delimiter_score . ')';

    // 2. Parse lines and identify Category Headers vs Data Rows
    $data_rows = [];
    $current_category = '';

    foreach($lines as $line) {
        // Check for "Text: Price" pattern (strong signal)
        // e.g. "Item Name: $10.00" or "Item Name : 10.00"
        // We ignore commas in the name part if this matches.
        $override_split = false;
        if (preg_match('/^(.+?)\s*[:]\s*([\$€£¥]?\s*\d+(?:\.\d{1,2})?)\s*$/', $line, $m)) {
             $parts = [$m[1], $m[2]];
             $override_split = true;
        }

        // Handle delimiters inside parentheses (e.g. "Item (desc, with, commas)")
        // We temporarily replace them with a placeholder token
        $temp_line = $line;
        if (!$override_split && $delimiter && strpos($line, '(') !== false) {
            $temp_line = preg_replace_callback('/\([^)]+\)/', function($m) use ($delimiter) {
                return str_replace($delimiter, '{{WTM_DELIM}}', $m[0]);
            }, $line);
        }


        if ($override_split) {
            // $parts is already set
        } elseif ($delimiter) {
            $parts = str_getcsv($temp_line, $delimiter);
        } else {
            $parts = [$temp_line];
        }

        // Restore delimiters
        if (!$override_split && $delimiter && strpos($line, '(') !== false) {
            $parts = array_map(function($p) use ($delimiter) {
                return str_replace('{{WTM_DELIM}}', $delimiter, $p);
            }, $parts);
        }

        $parts = array_map('trim', $parts);
        // Remove empty trailing columns
        while(count($parts) > 0 && end($parts) === '') {
            array_pop($parts);
        }
        if (empty($parts)) continue;

        // Heuristic: If single column and NOT a price/number, treat as Category Header
        if (count($parts) === 1 && !preg_match('/\d/', $parts[0])) {
            $current_category = rtrim($parts[0], ':');
            // Clean up category name (remove markdown-like symbols #, *, -, _)
            $current_category = trim($parts[0], " \t\n\r\0\x0B:#*-_");
            $debug[] = 'Found category header: ' . $current_category;
        } else {
            $data_rows[] = [
                'parts' => $parts,
                'category' => $current_category
            ];
        }
    }

    $debug[] = 'Data rows found: ' . count($data_rows);
    if (empty($data_rows)) return [];
    
    // 3. Analyze columns based on Data Rows only
    $max_cols = 0;
    foreach ($data_rows as $row) {
        $max_cols = max($max_cols, count($row['parts']));
    }
    $debug[] = 'Max columns detected: ' . $max_cols;

    $col_stats = [];
    for ($i = 0; $i < $max_cols; $i++) {
        $column_values = [];
        foreach($data_rows as $row) {
            if (isset($row['parts'][$i])) $column_values[] = $row['parts'][$i];
        }

        if (empty($column_values)) {
            $col_stats[$i] = ['price_score'=>0, 'avg_length'=>0];
            continue;
        }

        $price_matches = 0;
        $total_length = 0;
        foreach($column_values as $val) {
            // Looser price regex: allows currency symbols, optional decimals, and ensures it's short
            if (preg_match('/^[\$€£¥]?\s*\d{1,5}(\.\d{1,2})?\s*$/', $val) || (preg_match('/\d/', $val) && strlen($val) < 10)) {
                $price_matches++;
            }
            $total_length += strlen($val);
        }

        $col_stats[$i] = [
            'price_score' => $price_matches / count($column_values),
            'avg_length' => $total_length / count($column_values)
        ];
    }
    $debug[] = 'Column stats: ' . json_encode($col_stats);

    // 4. Map columns
    $mapping = ['name' => -1, 'price' => -1, 'description' => -1];
    $assigned_cols = [];
    
    // A. Find Price (Highest price score)
    $best_price_score = -1;
    $price_col = -1;
    foreach($col_stats as $i => $stats) {
        if ($stats['price_score'] > $best_price_score) {
            $best_price_score = $stats['price_score'];
            $price_col = $i;
        }
    }
    if ($price_col != -1 && $best_price_score > 0.3) {
        $mapping['price'] = $price_col;
        $assigned_cols[] = $price_col;
    }

    // B. Find Name and Description among remaining
    $remaining = [];
    for ($i = 0; $i < $max_cols; $i++) {
        if (!in_array($i, $assigned_cols)) {
            $remaining[] = $i;
        }
    }

    if (!empty($remaining)) {
        // Sort by length: Shorter is Name, Longer is Description
        usort($remaining, function($a, $b) use ($col_stats) {
            return $col_stats[$a]['avg_length'] <=> $col_stats[$b]['avg_length'];
        });
        
        $mapping['name'] = $remaining[0];
        if (count($remaining) > 1) {
            $mapping['description'] = $remaining[count($remaining)-1];
        }
    }
    $debug[] = 'Final Mapping: ' . json_encode($mapping);

    // 5. Build items array
    $items = [];
    foreach ($data_rows as $row) {
        $p = $row['parts'];
        $item = [
            'name' => ($mapping['name'] !== -1 && isset($p[$mapping['name']])) ? $p[$mapping['name']] : '',
            'price' => ($mapping['price'] !== -1 && isset($p[$mapping['price']])) ? $p[$mapping['price']] : '',
            'description' => ($mapping['description'] !== -1 && isset($p[$mapping['description']])) ? $p[$mapping['description']] : '',
            'category' => $row['category']
        ];
        
        // Fallback: If Name is empty but Description is not, use Description as Name
        if (empty($item['name']) && !empty($item['description'])) {
            $item['name'] = $item['description'];
            $item['description'] = '';
        }

        // Intelligent Price Extraction if missing
        if (empty($item['price'])) {
            // Check Name for price (e.g. "Burger $10")
            if (preg_match('/([\$€£¥]\s*\d+(?:\.\d{1,2})?)/', $item['name'], $m)) {
                $item['price'] = $m[1];
                $item['name'] = preg_replace('/'.preg_quote($m[1], '/').'/', '', $item['name'], 1);
            } elseif (preg_match('/([\$€£¥]\s*\d+(?:\.\d{1,2})?)/', $item['description'], $m)) {
                // Check Description for price
                $item['price'] = $m[1];
                $item['description'] = preg_replace('/'.preg_quote($m[1], '/').'/', '', $item['description'], 1);
            }
        }

        // Extract Description from Name (text in brackets)
        // e.g. "Item Name (Description)" -> Name: "Item Name", Desc: "Description"
        // But preserve numeric quantities e.g. "Dumplings (10)"
        $item['name'] = preg_replace_callback('/\s*\(([^)]+)\)/', function($m) use (&$item) {
            $content = $m[1];
            // If content is numeric (e.g. "10", " 5 "), keep it in name (quantity)
            if (preg_match('/^[\d\.\s]+$/', $content)) return $m[0];

            // Move to description
            $desc_part = trim($content);
            if (!empty($item['description'])) $item['description'] .= ' ' . $desc_part;
            else $item['description'] = $desc_part;

            return ''; // Remove from name
        }, $item['name']);

        // Cleanup Name: remove leading bullets (*, -) but preserve numbers (e.g. "1. Item")
        $item['name'] = preg_replace('/^[\s\*\-]+/', '', $item['name']);
        $item['name'] = trim($item['name'], " \t\n\r\0\x0B:");

        // Clean price
        $item['price'] = preg_replace('/[^\d.]/', '', $item['price']);

        if (!empty($item['name']) || !empty($item['price'])) {
            $items[] = $item;
        }
    }

    return $items;
}

// Helper to parse bulk text (Fixing missing function)
function wtm_parse_bulk_text($text, &$debug = []) {
    $debug[] = 'Starting standard parse.';
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $items = array();

    // Clean up lines
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines, function($line) { return !empty($line); });

    if (count($lines) < 2) {
        // We need at least a header and one data row
        $debug[] = 'Not enough lines (need header + data). Count: ' . count($lines);
        return [];
    }

    $header_line = array_shift($lines);

    // Detect delimiter from header
    if (strpos($header_line, '|') !== false) {
        $delimiter = '|';
    } elseif (strpos($header_line, "\t") !== false) {
        $delimiter = "\t";
    } else {
        $delimiter = ',';
    }
    $debug[] = 'Detected delimiter: ' . $delimiter;
    
    $header = str_getcsv($header_line, $delimiter);
    $header = array_map('trim', $header);
    $header = array_map('strtolower', $header); // Normalize headers to lowercase

    // Map synonyms to standard keys
    $map = [];
    foreach ($header as $i => $col) {
        if (in_array($col, ['name', 'title', 'item', 'dish', 'product'])) $map[$i] = 'name';
        elseif (in_array($col, ['price', 'cost', 'amount', 'value'])) $map[$i] = 'price';
        elseif (in_array($col, ['description', 'desc', 'details', 'info'])) $map[$i] = 'description';
        elseif (in_array($col, ['category', 'cat', 'group', 'type'])) $map[$i] = 'category';
        else $map[$i] = $col; // Keep original if unknown
    }
    $debug[] = 'Header map: ' . json_encode($map);

    foreach ($lines as $line) {
        $parts = str_getcsv($line, $delimiter);
        $row = [];
        foreach ($map as $index => $key) {
            if (isset($parts[$index])) {
                 $row[$key] = trim($parts[$index]);
            }
        }
        // Ensure standard keys exist for JS
        $row = array_merge(['name'=>'','price'=>'','description'=>'','category'=>''], $row);
        
        if (!empty($row['name']) || !empty($row['price'])) {
            $items[] = $row;
        }
    }
    return $items;
}