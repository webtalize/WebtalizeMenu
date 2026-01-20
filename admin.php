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

function wtm_add_dietary_labels_meta_boxes() {
    add_meta_box('wtm_dietary_labels_meta_box', __('Dietary & Allergen Labels', 'webtalize-menu'), 'wtm_dietary_labels_meta_box_callback', 'menu_item', 'normal', 'default');
}
add_action('add_meta_boxes', 'wtm_add_dietary_labels_meta_boxes');

function wtm_price_meta_box_callback($post) {
    wp_nonce_field('wtm_meta_box_nonce', 'wtm_meta_box_nonce');
    $price = get_post_meta($post->ID, 'wtm_price', true);
    echo '<label for="wtm_price">' . esc_html__('Price:', 'webtalize-menu') . '</label>';
    echo '<input type="text" id="wtm_price" name="wtm_price" value="' . esc_attr($price) . '" size="10" />';
}

function wtm_description_meta_box_callback($post) {
    $description = get_post_meta($post->ID, 'wtm_description', true);
    echo '<label for="wtm_description">' . esc_html__('Description:', 'webtalize-menu') . '</label>';
    echo '<textarea id="wtm_description" name="wtm_description" rows="4" cols="50">' . esc_textarea($description) . '</textarea>'; // Use esc_textarea
}

function wtm_dietary_labels_meta_box_callback($post) {
    $dietary_labels = get_post_meta($post->ID, 'wtm_dietary_labels', true);
    if (!is_array($dietary_labels)) {
        $dietary_labels = array();
    }
    
    // Define all dietary options
    $all_options = array(
        'vegan' => 'Vegan',
        'gluten_free' => 'Gluten Free',
        'vegetarian' => 'Vegetarian',
        'spicy_1' => 'Spicy',
        'spicy_2' => 'Very Spicy',
        'spicy_3' => 'Very Very Spicy',
        'can_be_vegetarian' => 'Can be made Vegetarian',
        'can_be_gluten_free' => 'Can be made Gluten free',
        'contains_peanuts' => 'Contains peanuts',
    );
    
    // Popular options (quick access)
    $popular_options = array('vegan', 'gluten_free', 'spicy_1');
    
    // Other options (in sub-menu)
    $other_options = array_diff_key($all_options, array_flip($popular_options));
    
    echo '<div class="wtm-dietary-labels-wrapper">';
    
    // Popular options section
    echo '<div class="wtm-dietary-popular">';
    echo '<h4 style="margin-top:0;">' . esc_html__('Popular Options', 'webtalize-menu') . '</h4>';
    echo '<div class="wtm-dietary-checkboxes" style="display:flex;flex-wrap:wrap;gap:15px;margin-bottom:15px;">';
    foreach ($popular_options as $key) {
        $checked = in_array($key, $dietary_labels) ? 'checked="checked"' : '';
        $label = $all_options[$key];
        echo '<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">';
        echo '<input type="checkbox" name="wtm_dietary_labels[]" value="' . esc_attr($key) . '" ' . $checked . ' />';
        echo '<span>' . esc_html($label) . '</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</div>';
    
    // Other options section (collapsible)
    echo '<div class="wtm-dietary-other">';
    echo '<details style="margin-top:10px;">';
    echo '<summary style="cursor:pointer;font-weight:600;padding:8px;background:#f5f5f5;border-radius:4px;">' . esc_html__('More Options', 'webtalize-menu') . '</summary>';
    echo '<div class="wtm-dietary-checkboxes" style="display:flex;flex-wrap:wrap;gap:15px;margin-top:15px;padding:10px;">';
    foreach ($other_options as $key => $label) {
        $checked = in_array($key, $dietary_labels) ? 'checked="checked"' : '';
        echo '<label style="display:flex;align-items:center;gap:5px;cursor:pointer;">';
        echo '<input type="checkbox" name="wtm_dietary_labels[]" value="' . esc_attr($key) . '" ' . $checked . ' />';
        echo '<span>' . esc_html($label) . '</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</details>';
    echo '</div>';
    
    echo '</div>';
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
        if (!is_numeric($price)) {
            $price = '';
        }
        if ($price !== '') {
            update_post_meta($post_id, 'wtm_price', $price);
        } else {
            delete_post_meta($post_id, 'wtm_price');
        }
    }

    if (isset($_POST['wtm_description'])) {
        $description = sanitize_textarea_field($_POST['wtm_description']); // Sanitize textarea
        if ($description !== '') {
            update_post_meta($post_id, 'wtm_description', $description);
        } else {
            delete_post_meta($post_id, 'wtm_description');
        }
    }

    // Save dietary labels
    if (isset($_POST['wtm_dietary_labels']) && is_array($_POST['wtm_dietary_labels'])) {
        $dietary_labels = array_map('sanitize_text_field', $_POST['wtm_dietary_labels']);
        // Validate against allowed options
        $allowed_options = array('vegan', 'gluten_free', 'vegetarian', 'spicy_1', 'spicy_2', 'spicy_3', 'can_be_vegetarian', 'can_be_gluten_free', 'contains_peanuts');
        $dietary_labels = array_intersect($dietary_labels, $allowed_options);
        $dietary_labels = array_values($dietary_labels); // Re-index array
        
        if (!empty($dietary_labels)) {
            update_post_meta($post_id, 'wtm_dietary_labels', $dietary_labels);
        } else {
            delete_post_meta($post_id, 'wtm_dietary_labels');
        }
    } else {
        // If no labels are checked, remove the meta
        delete_post_meta($post_id, 'wtm_dietary_labels');
    }
    
    // Update menu change timestamp when menu item is saved
    if (function_exists('wtm_update_menu_change_timestamp')) {
        wtm_update_menu_change_timestamp();
    }
}
add_action('save_post', 'wtm_save_meta_boxes_data');

// Add columns to the Menu Items list table
add_filter('manage_menu_item_posts_columns', 'wtm_add_columns_to_menu_items_table');
function wtm_add_columns_to_menu_items_table($columns) {
    $new_columns = array();
    foreach ($columns as $key => $title) {
        if ($key == 'date') { // Insert new columns before date
            $new_columns['wtm_price'] = __('Price', 'webtalize-menu');
            $new_columns['wtm_description'] = __('Description', 'webtalize-menu');
            $new_columns['wtm_dietary_labels'] = __('Dietary Labels', 'webtalize-menu');
            $new_columns['menu_order'] = __('Order', 'webtalize-menu');
        }
        $new_columns[$key] = $title;
    }
    return $new_columns;
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
        case 'wtm_dietary_labels':
            $dietary_labels = get_post_meta($post_id, 'wtm_dietary_labels', true);
            if (!empty($dietary_labels) && is_array($dietary_labels)) {
                $label_names = array(
                    'vegan' => 'Vegan',
                    'gluten_free' => 'GF',
                    'vegetarian' => 'Veg',
                    'spicy_1' => '🌶️1',
                    'spicy_2' => '🌶️2',
                    'spicy_3' => '🌶️3',
                    'can_be_vegetarian' => 'Can Veg',
                    'can_be_gluten_free' => 'Can GF',
                    'contains_peanuts' => '🥜',
                );
                $display_labels = array();
                foreach ($dietary_labels as $label_key) {
                    if (isset($label_names[$label_key])) {
                        $display_labels[] = $label_names[$label_key];
                    }
                }
                if (!empty($display_labels)) {
                    // Add hidden data attribute for quick edit JavaScript
                    echo '<span class="wtm-dietary-labels-data" data-labels="' . esc_attr(json_encode($dietary_labels)) . '" style="display:none;"></span>';
                    echo '<span style="font-size:0.9em;">' . esc_html(implode(', ', $display_labels)) . '</span>';
                }
            } else {
                // Even if empty, add the data attribute so JavaScript knows it's empty
                echo '<span class="wtm-dietary-labels-data" data-labels="[]" style="display:none;"></span>';
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

// Add category filter dropdown to admin list
add_action('restrict_manage_posts', 'wtm_add_category_filter');
function wtm_add_category_filter() {
    global $typenow;
    
    // Only show on menu_item post type
    if ($typenow != 'menu_item') {
        return;
    }
    
    // Get selected category from URL
    $selected = isset($_GET['menu_category_filter']) ? $_GET['menu_category_filter'] : '';
    
    // Get all menu categories
    $categories = get_terms(array(
        'taxonomy' => 'menu_category',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    
    if (!empty($categories) && !is_wp_error($categories)) {
        echo '<select name="menu_category_filter" id="menu_category_filter">';
        echo '<option value="">' . esc_html__('All Categories', 'webtalize-menu') . '</option>';
        
        foreach ($categories as $category) {
            $selected_attr = ($selected == $category->slug) ? ' selected="selected"' : '';
            echo '<option value="' . esc_attr($category->slug) . '"' . $selected_attr . '>';
            echo esc_html($category->name);
            // Show count in parentheses
            $count = $category->count;
            if ($count > 0) {
                echo ' (' . $count . ')';
            }
            echo '</option>';
        }
        
        echo '</select>';
    }
}

// Filter posts by category when category filter is selected
add_action('parse_query', 'wtm_filter_posts_by_category');
function wtm_filter_posts_by_category($query) {
    global $pagenow, $typenow;
    
    // Only apply on admin list page for menu_item post type
    if (!is_admin() || $pagenow != 'edit.php' || $typenow != 'menu_item') {
        return;
    }
    
    // Check if category filter is set
    if (isset($_GET['menu_category_filter']) && $_GET['menu_category_filter'] != '') {
        $category_slug = sanitize_text_field($_GET['menu_category_filter']);
        
        // Get the term ID from slug
        $term = get_term_by('slug', $category_slug, 'menu_category');
        
        if ($term && !is_wp_error($term)) {
            // Set tax query to filter by category
            $query->set('tax_query', array(
                array(
                    'taxonomy' => 'menu_category',
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ),
            ));
        }
    }
}

// Sort menu items by category order first, then by item name
// Use priority 20 to run after WordPress sets default query vars
add_action('pre_get_posts', 'wtm_menu_items_sort_by_category_order', 20);
function wtm_menu_items_sort_by_category_order($query) {
    // Only apply to menu_item post type in admin
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    // Check if we're on the menu items list page
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'menu_item' || $screen->id !== 'edit-menu_item') {
        return;
    }
    
    // Get the orderby from URL parameter
    $get_orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
    
    // Don't override if user is manually sorting by a column (including date, title, etc.)
    // Only apply custom sorting when there's no explicit orderby in the URL
    if (!empty($get_orderby) && $get_orderby !== 'wtm_category_order') {
        // Allow manual sorting if user clicks on a sortable column (except our custom one)
        return;
    }
    
    // When there's no explicit orderby in URL (default view), always apply custom sorting
    // This ensures all items are shown, sorted by category order then name
    // Set orderby to trigger our custom sorting
    $query->set('orderby', 'wtm_category_order');
    if (!$query->get('order')) {
        $query->set('order', 'ASC');
    }
}

// Modify SQL clauses to join with termmeta and sort by category order
add_filter('posts_clauses', 'wtm_menu_items_orderby_category_clauses', 999, 2);
function wtm_menu_items_orderby_category_clauses($clauses, $query) {
    // Only apply to menu_item post type in admin
    if (!is_admin() || !$query->is_main_query()) {
        return $clauses;
    }
    
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'menu_item' || $screen->id !== 'edit-menu_item') {
        return $clauses;
    }
    
    // Check if we're using our custom orderby
    $orderby = $query->get('orderby');
    $manual_orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
    
    // If user is manually sorting by a specific column (not our custom one), don't override
    if (!empty($manual_orderby) && $manual_orderby !== 'wtm_category_order') {
        return $clauses;
    }
    
    // Apply our custom sort if orderby is 'wtm_category_order' (set by pre_get_posts)
    // Only apply when explicitly set to avoid interfering with WordPress's default query on first load
    // The pre_get_posts hook will set this when there's no manual orderby
    if ($orderby === 'wtm_category_order') {
        global $wpdb;
        
        // Ensure the query is properly set up - check that post_type is set
        $post_type = $query->get('post_type');
        if (empty($post_type) || (is_array($post_type) && !in_array('menu_item', $post_type)) || (!is_array($post_type) && $post_type !== 'menu_item')) {
            return $clauses;
        }
        
        // Get order direction
        $order = 'ASC';
        if ($query->get('order')) {
            $order = strtoupper($query->get('order'));
        } elseif (isset($_GET['order']) && !empty($_GET['order'])) {
            $order = strtoupper(sanitize_text_field($_GET['order']));
        }
        if ($order !== 'ASC' && $order !== 'DESC') {
            $order = 'ASC';
        }
        
        // Use a subquery to get the minimum category order for each post
        // This handles posts with multiple categories by using the category with the lowest order value
        // IMPORTANT: The subquery only includes posts that have at least one category
        // Posts without categories won't be in the subquery result (will be NULL after LEFT JOIN)
        $subquery = "
            SELECT tr.object_id, 
                   MIN(CAST(COALESCE(NULLIF(TRIM(tm.meta_value), ''), '999999') AS SIGNED)) AS category_order
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'menu_category'
            LEFT JOIN {$wpdb->termmeta} tm ON tt.term_id = tm.term_id AND tm.meta_key = 'wtm_category_order'
            GROUP BY tr.object_id
        ";
        
        // Check if we already have the join
        $has_category_order_join = strpos($clauses['join'], 'wtm_category_order_meta') !== false;
        
        if (!$has_category_order_join) {
            // LEFT JOIN ensures ALL posts are included, even those without categories
            // Posts without categories will have NULL category_order
            $clauses['join'] .= " LEFT JOIN ({$subquery}) AS wtm_category_order_meta ON {$wpdb->posts}.ID = wtm_category_order_meta.object_id";
        }
        
        // CRITICAL: Ensure posts without categories (NULL category_order) are not filtered out
        // Check and remove any WHERE clauses that exclude NULL values
        if (!empty($clauses['where'])) {
            // Remove WHERE conditions that filter out NULL category_order
            $clauses['where'] = preg_replace('/\s*AND\s*\(\s*wtm_category_order_meta\.category_order\s+IS\s+NOT\s+NULL\s*\)/i', '', $clauses['where']);
            $clauses['where'] = preg_replace('/\s*AND\s*wtm_category_order_meta\.category_order\s+IS\s+NOT\s+NULL/i', '', $clauses['where']);
        }
        
        // Modify ORDER BY clause
        // Sort by category order first (NULL/0/999999 values go last), then by post title
        // CRITICAL: Use LPAD to pad numbers with leading zeros for proper string-based numeric sorting
        // This ensures: 07 < 08 < 09 < 10 < 11 < 15 (string comparison matches numeric)
        // Without padding: "15" < "7" (incorrect string comparison)
        // IMPORTANT: WordPress adds "ORDER BY" automatically, so we only provide the ordering expressions
        $clauses['orderby'] = "
            CASE 
                WHEN wtm_category_order_meta.category_order IS NULL 
                     OR CAST(COALESCE(wtm_category_order_meta.category_order, 0) AS SIGNED) = 0 
                     OR CAST(COALESCE(wtm_category_order_meta.category_order, 0) AS SIGNED) = 999999
                THEN 1 
                ELSE 0 
            END ASC,
            LPAD(CAST(COALESCE(wtm_category_order_meta.category_order, 999999) AS SIGNED), 10, '0') {$order},
            {$wpdb->posts}.post_title {$order}";
        
        // Debug: Output SQL to console with full query details
        static $debug_sql_output = false;
        if (!$debug_sql_output) {
            add_action('admin_footer', function() use ($clauses, $subquery, $query) {
                echo '<script type="text/javascript">';
                echo 'console.log("=== WTM Menu Items Sorting Debug ===");';
                echo 'console.log("GET orderby: ' . (isset($_GET['orderby']) ? esc_js($_GET['orderby']) : 'not set') . '");';
                echo 'console.log("Query orderby: ' . esc_js($query->get('orderby')) . '");';
                echo 'console.log("Query post_type: ' . esc_js(is_array($query->get('post_type')) ? implode(', ', $query->get('post_type')) : $query->get('post_type')) . '");';
                echo 'console.log("Query post_status: ' . esc_js(is_array($query->get('post_status')) ? implode(', ', $query->get('post_status')) : $query->get('post_status')) . '");';
                echo 'console.log("Query posts_per_page: ' . esc_js($query->get('posts_per_page')) . '");';
                echo 'console.log("WHERE clause: ' . esc_js(substr($clauses['where'], 0, 500)) . '");';
                echo 'console.log("JOIN clause: ' . esc_js(substr($clauses['join'], -300)) . '");';
                echo 'console.log("ORDER BY: ' . esc_js($clauses['orderby']) . '");';
                echo 'console.log("Subquery: ' . esc_js($subquery) . '");';
                echo '</script>';
            }, 999);
            $debug_sql_output = true;
        }
    }
    
    return $clauses;
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
        case 'wtm_dietary_labels':
            // Define all dietary options
            $all_options = array(
                'vegan' => 'Vegan',
                'gluten_free' => 'Gluten Free',
                'vegetarian' => 'Vegetarian',
                'spicy_1' => 'Spicy',
                'spicy_2' => 'Very Spicy',
                'spicy_3' => 'Very Very Spicy',
                'can_be_vegetarian' => 'Can be made Vegetarian',
                'can_be_gluten_free' => 'Can be made Gluten free',
                'contains_peanuts' => 'Contains peanuts',
            );
            
            // Popular options
            $popular_options = array('vegan', 'gluten_free', 'spicy_1');
            $other_options = array_diff_key($all_options, array_flip($popular_options));
            ?>
            <fieldset class="inline-edit-col-right wtm-dietary-quick-edit">
                <div class="inline-edit-col">
                    <span class="title"><?php _e('Dietary Labels', 'webtalize-menu'); ?></span>
                    <!-- Hidden field to track that this field was in the form -->
                    <input type="hidden" name="wtm_dietary_labels_present" value="1" />
                    <div class="wtm-quick-edit-dietary-wrapper" style="max-height:200px;overflow-y:auto;border:1px solid #ddd;padding:8px;background:#f9f9f9;border-radius:4px;">
                        <div style="margin-bottom:8px;">
                            <strong style="font-size:11px;color:#666;"><?php _e('Popular:', 'webtalize-menu'); ?></strong>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;">
                                <?php foreach ($popular_options as $key): ?>
                                    <label style="display:flex;align-items:center;gap:4px;font-size:11px;cursor:pointer;">
                                        <input type="checkbox" name="wtm_dietary_labels[]" value="<?php echo esc_attr($key); ?>" class="wtm_dietary_labels_input" data-label="<?php echo esc_attr($key); ?>" />
                                        <span><?php echo esc_html($all_options[$key]); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <details style="margin-top:8px;">
                            <summary style="cursor:pointer;font-size:11px;font-weight:600;color:#666;margin-bottom:4px;"><?php _e('More Options', 'webtalize-menu'); ?></summary>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;padding-top:4px;border-top:1px solid #ddd;">
                                <?php foreach ($other_options as $key => $label): ?>
                                    <label style="display:flex;align-items:center;gap:4px;font-size:11px;cursor:pointer;">
                                        <input type="checkbox" name="wtm_dietary_labels[]" value="<?php echo esc_attr($key); ?>" class="wtm_dietary_labels_input" data-label="<?php echo esc_attr($key); ?>" />
                                        <span><?php echo esc_html($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    </div>
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
        if (!is_numeric($price)) {
            $price = '';
        }
        if ($price !== '') {
            update_post_meta($post_id, 'wtm_price', $price);
        } else {
            delete_post_meta($post_id, 'wtm_price');
        }
    }

    if (isset($_POST['wtm_description'])) {
        $description = sanitize_textarea_field($_POST['wtm_description']);
        if ($description !== '') {
            update_post_meta($post_id, 'wtm_description', $description);
        } else {
            delete_post_meta($post_id, 'wtm_description');
        }
    }

    // Save dietary labels from quick edit
    // Check if we're in quick edit mode by looking for the inline-save action
    $is_quick_edit = isset($_POST['action']) && $_POST['action'] === 'inline-save';
    
    if ($is_quick_edit && isset($_POST['wtm_dietary_labels_present'])) {
        // The dietary labels field was in the quick edit form
        // If checkboxes are unchecked, they won't be in POST, so we need to check if any were sent
        if (isset($_POST['wtm_dietary_labels']) && is_array($_POST['wtm_dietary_labels'])) {
            $dietary_labels = $_POST['wtm_dietary_labels'];
        } else {
            // No checkboxes were checked, so set to empty array
            $dietary_labels = array();
        }
        
        // Validate against allowed options
        $allowed_options = array('vegan', 'gluten_free', 'vegetarian', 'spicy_1', 'spicy_2', 'spicy_3', 'can_be_vegetarian', 'can_be_gluten_free', 'contains_peanuts');
        $dietary_labels = array_map('sanitize_text_field', $dietary_labels);
        $dietary_labels = array_intersect($dietary_labels, $allowed_options);
        $dietary_labels = array_values($dietary_labels); // Re-index array
        
        if (!empty($dietary_labels)) {
            update_post_meta($post_id, 'wtm_dietary_labels', $dietary_labels);
        } else {
            // All checkboxes were unchecked, so clear the meta
            delete_post_meta($post_id, 'wtm_dietary_labels');
        }
    }
    // If wtm_dietary_labels_present is not in POST, the field wasn't in the form, so don't change existing value
    
    // Update menu change timestamp when menu item is saved via quick edit
    if (function_exists('wtm_update_menu_change_timestamp')) {
        wtm_update_menu_change_timestamp();
    }
}









// Track menu changes when posts are deleted or trashed
add_action('before_delete_post', 'wtm_track_menu_change_on_delete');
add_action('wp_trash_post', 'wtm_track_menu_change_on_delete');
function wtm_track_menu_change_on_delete($post_id) {
    $post = get_post($post_id);
    if ($post && $post->post_type === 'menu_item') {
        if (function_exists('wtm_update_menu_change_timestamp')) {
            wtm_update_menu_change_timestamp();
        }
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
    // Add New Menu Item - use callback to redirect
    add_submenu_page('wtm-main','Add New Menu Item','Add New Menu Item','edit_posts','wtm-add-new-menu-item','wtm_redirect_to_new_menu_item');
    // add Menu Categories link to manage taxonomy terms
    add_submenu_page('wtm-main','Menu Categories','Menu Categories','manage_options','edit-tags.php?taxonomy=menu_category&post_type=menu_item');
    add_submenu_page('wtm-main','CSV Import','CSV Import','edit_posts','wtm-csv-import','wtm_csv_import_page');
    add_submenu_page('wtm-main','Reorder Items','Reorder Items','edit_posts','wtm-reorder','wtm_reorder_page');
    add_submenu_page('wtm-main','Bulk Add Items','Bulk Add Items','edit_posts','wtm-bulk-add','wtm_bulk_add_page');
    add_submenu_page('wtm-main','Opening Hours','Opening Hours','manage_options','wtm-opening-hours','wtm_opening_hours_page');
    add_submenu_page('wtm-main','Settings','Settings','manage_options','wtm-settings','wtm_settings_page');
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

// Redirect callback for "Add New Menu Item" submenu
function wtm_redirect_to_new_menu_item() {
    wp_redirect(admin_url('post-new.php?post_type=menu_item'));
    exit;
}

// Settings page for menu display options
function wtm_settings_page() {
    // Handle form submission
    if (isset($_POST['wtm_save_settings']) && check_admin_referer('wtm_settings_nonce')) {
        $three_column = isset($_POST['wtm_three_column']) ? '1' : '0';
        update_option('wtm_three_column_layout', $three_column);
        echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'webtalize-menu') . '</p></div>';
    }
    
    // Get current setting
    $three_column_enabled = get_option('wtm_three_column_layout', '0');
    
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Webtalize Menu Settings', 'webtalize-menu') . '</h1>';
    echo '<form method="post" action="">';
    wp_nonce_field('wtm_settings_nonce');
    
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">' . esc_html__('Menu Display Layout', 'webtalize-menu') . '</th>';
    echo '<td>';
    echo '<label>';
    echo '<input type="checkbox" name="wtm_three_column" value="1" ' . checked($three_column_enabled, '1', false) . ' />';
    echo ' ' . esc_html__('Enable 3-column layout for menu items', 'webtalize-menu');
    echo '</label>';
    echo '<p class="description">' . esc_html__('When enabled, menu items will display in 3 columns on wide screens, 2 columns on tablets, and 1 column on mobile devices. When disabled, items will display in a single column.', 'webtalize-menu') . '</p>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    
    submit_button(esc_html__('Save Settings', 'webtalize-menu'), 'primary', 'wtm_save_settings');
    echo '</form>';
    echo '</div>';
}

// Opening Hours settings
function wtm_get_default_opening_hours() {
    return array(
        'monday'    => array('closed' => false, 'open' => '09:00', 'close' => '21:00', 'break_start' => '', 'break_end' => ''),
        'tuesday'   => array('closed' => false, 'open' => '09:00', 'close' => '21:00', 'break_start' => '', 'break_end' => ''),
        'wednesday' => array('closed' => false, 'open' => '09:00', 'close' => '21:00', 'break_start' => '', 'break_end' => ''),
        'thursday'  => array('closed' => false, 'open' => '09:00', 'close' => '21:00', 'break_start' => '', 'break_end' => ''),
        'friday'    => array('closed' => false, 'open' => '09:00', 'close' => '22:00', 'break_start' => '', 'break_end' => ''),
        'saturday'  => array('closed' => false, 'open' => '09:00', 'close' => '22:00', 'break_start' => '', 'break_end' => ''),
        'sunday'    => array('closed' => false, 'open' => '09:00', 'close' => '20:00', 'break_start' => '', 'break_end' => ''),
    );
}

function wtm_sanitize_time($value) {
    $value = sanitize_text_field($value);
    if ($value === '') {
        return '';
    }
    // Accept HH:MM in 24-hour format
    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return '';
    }
    return $value;
}

function wtm_normalize_datetime($value) {
    $value = sanitize_text_field($value);
    if ($value === '') {
        return '';
    }
    $value = str_replace('T', ' ', $value);
    $dt = date_create_from_format('Y-m-d H:i', $value, wp_timezone());
    if (!$dt) {
        return '';
    }
    return $dt->format('Y-m-d H:i');
}

function wtm_cleanup_special_hours() {
    $specials = get_option('wtm_special_hours', array());
    if (empty($specials) || !is_array($specials)) {
        return;
    }
    $now = current_time('timestamp');
    $one_week = 7 * DAY_IN_SECONDS;
    $filtered = array();
    foreach ($specials as $special) {
        $end = isset($special['end']) ? $special['end'] : '';
        if ($end === '') {
            // Keep entries without end date
            $filtered[] = $special;
            continue;
        }
        $end_dt = date_create_from_format('Y-m-d H:i', $end, wp_timezone());
        if (!$end_dt) {
            $filtered[] = $special;
            continue;
        }
        $end_ts = $end_dt->getTimestamp();
        if (($end_ts + $one_week) >= $now) {
            $filtered[] = $special;
        }
    }
    if (count($filtered) !== count($specials)) {
        update_option('wtm_special_hours', $filtered);
    }
}
add_action('init', 'wtm_cleanup_special_hours');

function wtm_opening_hours_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $days = array(
        'monday'    => __('Monday', 'webtalize-menu'),
        'tuesday'   => __('Tuesday', 'webtalize-menu'),
        'wednesday' => __('Wednesday', 'webtalize-menu'),
        'thursday'  => __('Thursday', 'webtalize-menu'),
        'friday'    => __('Friday', 'webtalize-menu'),
        'saturday'  => __('Saturday', 'webtalize-menu'),
        'sunday'    => __('Sunday', 'webtalize-menu'),
    );

    if (isset($_POST['wtm_opening_hours_save']) && check_admin_referer('wtm_opening_hours_nonce')) {
        $default_hours = wtm_get_default_opening_hours();
        $posted_hours = isset($_POST['wtm_hours']) && is_array($_POST['wtm_hours']) ? $_POST['wtm_hours'] : array();
        $clean_hours = array();

        foreach ($days as $day_key => $label) {
            $row = isset($posted_hours[$day_key]) ? $posted_hours[$day_key] : array();
            $closed = !empty($row['closed']);
            $open = wtm_sanitize_time(isset($row['open']) ? $row['open'] : '');
            $close = wtm_sanitize_time(isset($row['close']) ? $row['close'] : '');
            $break_start = wtm_sanitize_time(isset($row['break_start']) ? $row['break_start'] : '');
            $break_end = wtm_sanitize_time(isset($row['break_end']) ? $row['break_end'] : '');

            if ($closed) {
                $open = '';
                $close = '';
                $break_start = '';
                $break_end = '';
            }

            $clean_hours[$day_key] = array(
                'closed' => $closed,
                'open' => $open,
                'close' => $close,
                'break_start' => $break_start,
                'break_end' => $break_end,
            );

            if (!isset($default_hours[$day_key])) {
                $default_hours[$day_key] = $clean_hours[$day_key];
            }
        }

        update_option('wtm_opening_hours', $clean_hours);

        $note = isset($_POST['wtm_opening_hours_note']) ? sanitize_textarea_field($_POST['wtm_opening_hours_note']) : '';
        update_option('wtm_opening_hours_note', $note);

        $phone1 = isset($_POST['wtm_opening_hours_phone1']) ? sanitize_text_field($_POST['wtm_opening_hours_phone1']) : '';
        update_option('wtm_opening_hours_phone1', $phone1);
        
        $phone2 = isset($_POST['wtm_opening_hours_phone2']) ? sanitize_text_field($_POST['wtm_opening_hours_phone2']) : '';
        update_option('wtm_opening_hours_phone2', $phone2);
        
        // Keep backward compatibility - if phone1 exists, also save to old option name
        if (!empty($phone1)) {
            update_option('wtm_opening_hours_phone', $phone1);
        }
        
        // Save address fields for Schema.org
        $address = array(
            'street' => isset($_POST['wtm_address_street']) ? sanitize_text_field($_POST['wtm_address_street']) : '',
            'city' => isset($_POST['wtm_address_city']) ? sanitize_text_field($_POST['wtm_address_city']) : '',
            'state' => isset($_POST['wtm_address_state']) ? sanitize_text_field($_POST['wtm_address_state']) : '',
            'postal_code' => isset($_POST['wtm_address_postal_code']) ? sanitize_text_field($_POST['wtm_address_postal_code']) : '',
            'country' => isset($_POST['wtm_address_country']) ? sanitize_text_field($_POST['wtm_address_country']) : '',
        );
        update_option('wtm_restaurant_address', $address);

        // Special hours
        $specials_in = isset($_POST['wtm_specials']) && is_array($_POST['wtm_specials']) ? $_POST['wtm_specials'] : array();
        $specials_out = array();
        foreach ($specials_in as $special) {
            if (!empty($special['remove'])) {
                continue;
            }
            $type = isset($special['type']) && $special['type'] === 'closed' ? 'closed' : 'open';
            $label = sanitize_text_field(isset($special['label']) ? $special['label'] : '');
            $start = wtm_normalize_datetime(isset($special['start']) ? $special['start'] : '');
            $end = wtm_normalize_datetime(isset($special['end']) ? $special['end'] : '');
            $open = wtm_sanitize_time(isset($special['open']) ? $special['open'] : '');
            $close = wtm_sanitize_time(isset($special['close']) ? $special['close'] : '');
            $break_start = wtm_sanitize_time(isset($special['break_start']) ? $special['break_start'] : '');
            $break_end = wtm_sanitize_time(isset($special['break_end']) ? $special['break_end'] : '');

            if ($start === '' || $end === '') {
                continue;
            }

            if ($type === 'closed') {
                $open = '';
                $close = '';
                $break_start = '';
                $break_end = '';
            }

            $specials_out[] = array(
                'id' => isset($special['id']) ? sanitize_text_field($special['id']) : uniqid('wtm_special_', true),
                'type' => $type,
                'label' => $label,
                'start' => $start,
                'end' => $end,
                'open' => $open,
                'close' => $close,
                'break_start' => $break_start,
                'break_end' => $break_end,
            );
        }

        update_option('wtm_special_hours', $specials_out);
        wtm_cleanup_special_hours();

        echo '<div class="notice notice-success"><p>' . esc_html__('Opening hours saved.', 'webtalize-menu') . '</p></div>';
    }

    $hours = get_option('wtm_opening_hours', wtm_get_default_opening_hours());
    $specials = get_option('wtm_special_hours', array());
    $note = get_option('wtm_opening_hours_note', '');
    $phone1 = get_option('wtm_opening_hours_phone1', '');
    $phone2 = get_option('wtm_opening_hours_phone2', '');
    
    // Backward compatibility - if phone1 is empty but old phone exists, use it
    if (empty($phone1)) {
        $phone1 = get_option('wtm_opening_hours_phone', '');
    }
    $address = get_option('wtm_restaurant_address', array(
        'street' => '',
        'city' => 'Calgary',
        'state' => 'Alberta',
        'postal_code' => '',
        'country' => 'Canada',
    ));

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Opening Hours', 'webtalize-menu') . '</h1>';
    echo '<p>' . esc_html__('Use the shortcode [wtm_opening_hours] with attributes to control display. Examples:', 'webtalize-menu') . '</p>';
    echo '<ul>';
    echo '<li><code>[wtm_opening_hours]</code> - ' . esc_html__('Weekly table with specials.', 'webtalize-menu') . '</li>';
    echo '<li><code>[wtm_opening_hours show="today"]</code> - ' . esc_html__('Today only.', 'webtalize-menu') . '</li>';
    echo '<li><code>[wtm_opening_hours layout="compact"]</code> - ' . esc_html__('Weekly compact list.', 'webtalize-menu') . '</li>';
    echo '<li><code>[wtm_opening_hours specials="no"]</code> - ' . esc_html__('Hide specials.', 'webtalize-menu') . '</li>';
    echo '</ul>';
    echo '<form method="post" action="">';
    wp_nonce_field('wtm_opening_hours_nonce');

    echo '<h2>' . esc_html__('Weekly Hours', 'webtalize-menu') . '</h2>';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Day', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Closed', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Open', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Close', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Break Start', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Break End', 'webtalize-menu') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($days as $day_key => $label) {
        $row = isset($hours[$day_key]) ? $hours[$day_key] : array();
        $closed = !empty($row['closed']);
        $open = isset($row['open']) ? $row['open'] : '';
        $close = isset($row['close']) ? $row['close'] : '';
        $break_start = isset($row['break_start']) ? $row['break_start'] : '';
        $break_end = isset($row['break_end']) ? $row['break_end'] : '';

        echo '<tr>';
        echo '<td>' . esc_html($label) . '</td>';
        echo '<td><input type="checkbox" name="wtm_hours[' . esc_attr($day_key) . '][closed]" value="1" ' . checked($closed, true, false) . ' /></td>';
        echo '<td><input type="time" name="wtm_hours[' . esc_attr($day_key) . '][open]" value="' . esc_attr($open) . '" /></td>';
        echo '<td><input type="time" name="wtm_hours[' . esc_attr($day_key) . '][close]" value="' . esc_attr($close) . '" /></td>';
        echo '<td><input type="time" name="wtm_hours[' . esc_attr($day_key) . '][break_start]" value="' . esc_attr($break_start) . '" /></td>';
        echo '<td><input type="time" name="wtm_hours[' . esc_attr($day_key) . '][break_end]" value="' . esc_attr($break_end) . '" /></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<h3 style="margin-top:20px;">' . esc_html__('Optional Note', 'webtalize-menu') . '</h3>';
    echo '<p>' . esc_html__('Displayed below the opening hours (e.g., "Holidays: Hours vary; open New Year\'s Day").', 'webtalize-menu') . '</p>';
    echo '<textarea name="wtm_opening_hours_note" rows="3" style="width:100%;">' . esc_textarea($note) . '</textarea>';

    echo '<h3 style="margin-top:20px;">' . esc_html__('Phone Numbers', 'webtalize-menu') . '</h3>';
    echo '<p>' . esc_html__('Displayed below today\'s hours when using [wtm_opening_hours show="today"]. Also used for Schema.org structured data.', 'webtalize-menu') . '</p>';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="wtm_opening_hours_phone1">' . esc_html__('Phone 1', 'webtalize-menu') . '</label></th>';
    echo '<td><input type="text" id="wtm_opening_hours_phone1" name="wtm_opening_hours_phone1" value="' . esc_attr($phone1) . '" style="width:100%;max-width:400px;" placeholder="' . esc_attr__('(555) 123-4567', 'webtalize-menu') . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="wtm_opening_hours_phone2">' . esc_html__('Phone 2', 'webtalize-menu') . '</label></th>';
    echo '<td><input type="text" id="wtm_opening_hours_phone2" name="wtm_opening_hours_phone2" value="' . esc_attr($phone2) . '" style="width:100%;max-width:400px;" placeholder="' . esc_attr__('(555) 123-4568', 'webtalize-menu') . '" /></td>';
    echo '</tr>';
    echo '</table>';
    
    echo '<h3 style="margin-top:20px;">' . esc_html__('Restaurant Address (for Schema.org SEO)', 'webtalize-menu') . '</h3>';
    echo '<p>' . esc_html__('Used for Google Rich Results and SEO. All fields are optional.', 'webtalize-menu') . '</p>';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row"><label for="wtm_address_street">' . esc_html__('Street Address', 'webtalize-menu') . '</label></th>';
    echo '<td><input type="text" id="wtm_address_street" name="wtm_address_street" value="' . esc_attr($address['street']) . '" style="width:100%;max-width:500px;" placeholder="' . esc_attr__('123 Main Street', 'webtalize-menu') . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="wtm_address_city">' . esc_html__('City', 'webtalize-menu') . '</label></th>';
    echo '<td><input type="text" id="wtm_address_city" name="wtm_address_city" value="' . esc_attr($address['city']) . '" style="width:100%;max-width:300px;" placeholder="' . esc_attr__('Calgary', 'webtalize-menu') . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="wtm_address_state">' . esc_html__('State/Province', 'webtalize-menu') . '</label></th>';
    echo '<td><input type="text" id="wtm_address_state" name="wtm_address_state" value="' . esc_attr($address['state']) . '" style="width:100%;max-width:200px;" placeholder="' . esc_attr__('Alberta', 'webtalize-menu') . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="wtm_address_postal_code">' . esc_html__('Postal Code', 'webtalize-menu') . '</label></th>';
    echo '<td><input type="text" id="wtm_address_postal_code" name="wtm_address_postal_code" value="' . esc_attr($address['postal_code']) . '" style="width:100%;max-width:150px;" placeholder="' . esc_attr__('T2P 1J4', 'webtalize-menu') . '" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th scope="row"><label for="wtm_address_country">' . esc_html__('Country', 'webtalize-menu') . '</label></th>';
    echo '<td><input type="text" id="wtm_address_country" name="wtm_address_country" value="' . esc_attr($address['country']) . '" style="width:100%;max-width:200px;" placeholder="' . esc_attr__('Canada', 'webtalize-menu') . '" /></td>';
    echo '</tr>';
    echo '</table>';

    echo '<h2 style="margin-top:25px;">' . esc_html__('Special Hours / Closures', 'webtalize-menu') . '</h2>';
    echo '<p>' . esc_html__('Use special hours for holidays, events, or temporary closures. Entries are removed one week after their end time.', 'webtalize-menu') . '</p>';

    echo '<table class="widefat fixed striped" id="wtm-special-hours-table">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__('Type', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Label', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Start', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('End', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Open', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Close', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Break Start', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Break End', 'webtalize-menu') . '</th>';
    echo '<th>' . esc_html__('Remove', 'webtalize-menu') . '</th>';
    echo '</tr></thead><tbody>';

    if (!empty($specials)) {
        foreach ($specials as $index => $special) {
            $type = isset($special['type']) ? $special['type'] : 'open';
            $label = isset($special['label']) ? $special['label'] : '';
            $start = isset($special['start']) ? $special['start'] : '';
            $end = isset($special['end']) ? $special['end'] : '';
            $open = isset($special['open']) ? $special['open'] : '';
            $close = isset($special['close']) ? $special['close'] : '';
            $break_start = isset($special['break_start']) ? $special['break_start'] : '';
            $break_end = isset($special['break_end']) ? $special['break_end'] : '';

            $start_val = $start ? str_replace(' ', 'T', $start) : '';
            $end_val = $end ? str_replace(' ', 'T', $end) : '';

            echo '<tr class="wtm-special-row">';
            echo '<td>';
            echo '<select name="wtm_specials[' . esc_attr($index) . '][type]">';
            echo '<option value="open"' . selected($type, 'open', false) . '>' . esc_html__('Special Open', 'webtalize-menu') . '</option>';
            echo '<option value="closed"' . selected($type, 'closed', false) . '>' . esc_html__('Closed', 'webtalize-menu') . '</option>';
            echo '</select>';
            echo '</td>';
            echo '<td><input type="text" name="wtm_specials[' . esc_attr($index) . '][label]" value="' . esc_attr($label) . '" placeholder="' . esc_attr__('Holiday / Event', 'webtalize-menu') . '" /></td>';
            echo '<td><input type="datetime-local" name="wtm_specials[' . esc_attr($index) . '][start]" value="' . esc_attr($start_val) . '" /></td>';
            echo '<td><input type="datetime-local" name="wtm_specials[' . esc_attr($index) . '][end]" value="' . esc_attr($end_val) . '" /></td>';
            echo '<td><input type="time" name="wtm_specials[' . esc_attr($index) . '][open]" value="' . esc_attr($open) . '" /></td>';
            echo '<td><input type="time" name="wtm_specials[' . esc_attr($index) . '][close]" value="' . esc_attr($close) . '" /></td>';
            echo '<td><input type="time" name="wtm_specials[' . esc_attr($index) . '][break_start]" value="' . esc_attr($break_start) . '" /></td>';
            echo '<td><input type="time" name="wtm_specials[' . esc_attr($index) . '][break_end]" value="' . esc_attr($break_end) . '" /></td>';
            echo '<td><input type="checkbox" name="wtm_specials[' . esc_attr($index) . '][remove]" value="1" /></td>';
            echo '<input type="hidden" name="wtm_specials[' . esc_attr($index) . '][id]" value="' . esc_attr(isset($special['id']) ? $special['id'] : '') . '" />';
            echo '</tr>';
        }
    }

    // Blank row template
    echo '<tr class="wtm-special-row wtm-special-template" style="display:none;">';
    echo '<td>';
    echo '<select name="wtm_specials[__INDEX__][type]">';
    echo '<option value="open">' . esc_html__('Special Open', 'webtalize-menu') . '</option>';
    echo '<option value="closed">' . esc_html__('Closed', 'webtalize-menu') . '</option>';
    echo '</select>';
    echo '</td>';
    echo '<td><input type="text" name="wtm_specials[__INDEX__][label]" placeholder="' . esc_attr__('Holiday / Event', 'webtalize-menu') . '" /></td>';
    echo '<td><input type="datetime-local" name="wtm_specials[__INDEX__][start]" value="" /></td>';
    echo '<td><input type="datetime-local" name="wtm_specials[__INDEX__][end]" value="" /></td>';
    echo '<td><input type="time" name="wtm_specials[__INDEX__][open]" value="" /></td>';
    echo '<td><input type="time" name="wtm_specials[__INDEX__][close]" value="" /></td>';
    echo '<td><input type="time" name="wtm_specials[__INDEX__][break_start]" value="" /></td>';
    echo '<td><input type="time" name="wtm_specials[__INDEX__][break_end]" value="" /></td>';
    echo '<td><input type="checkbox" name="wtm_specials[__INDEX__][remove]" value="1" /></td>';
    echo '<input type="hidden" name="wtm_specials[__INDEX__][id]" value="" />';
    echo '</tr>';

    echo '</tbody></table>';
    echo '<p><button type="button" class="button" id="wtm-add-special-row">' . esc_html__('Add Special Entry', 'webtalize-menu') . '</button></p>';

    submit_button(esc_html__('Save Opening Hours', 'webtalize-menu'), 'primary', 'wtm_opening_hours_save');
    echo '</form>';

    echo '<script type="text/javascript">';
    echo '(function($){';
    echo '$(document).ready(function(){';
    echo 'var $table = $("#wtm-special-hours-table");';
    echo 'var index = $table.find("tbody tr.wtm-special-row").not(".wtm-special-template").length;';
    echo '$("#wtm-add-special-row").on("click", function(){';
    echo 'var $tpl = $table.find(".wtm-special-template").clone();';
    echo 'var html = $tpl.prop("outerHTML").replace(/__INDEX__/g, index);';
    echo '$(html).removeClass("wtm-special-template").show().appendTo($table.find("tbody"));';
    echo 'index++;';
    echo '});';
    echo '});';
    echo '})(jQuery);';
    echo '</script>';

    echo '</div>';
}

add_action('admin_enqueue_scripts','wtm_enqueue_admin_scripts');
function wtm_enqueue_admin_scripts($hook) {
    global $pagenow;

    // Enqueue Quick Edit script on the menu items list table
    if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'menu_item') {
        wp_enqueue_script('wtm-quick-edit', WTM_PLUGIN_URL . 'js/wtm-quick-edit.js', array('jquery', 'wp-data', 'wp-i18n', 'wp-hooks'), '1.0.1', true);
    }
    
    // Enqueue scripts for Menu Category management (Image Upload)
    if (($hook === 'term.php' || $hook === 'edit-tags.php') && isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'menu_category') {
        wp_enqueue_media();
        wp_enqueue_script('wtm-category-media', WTM_PLUGIN_URL . 'js/wtm-category-media.js', array('jquery', 'inline-edit-tax'), '1.1.0', true);
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
    if (isset($_POST['wtm_bulk_json']) && !empty($_POST['wtm_bulk_json'])) {
        $parsed = json_decode(wp_unslash($_POST['wtm_bulk_json']), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle JSON error, maybe redirect back with an error message
            wp_redirect(add_query_arg('wtm_bulk_errors', urlencode('Invalid data format.'), admin_url('edit.php?post_type=menu_item&page=wtm-csv-import')));
            exit;
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

// Category Management: Add Image and Sort Order fields

// Add fields to "Add New Category" form
add_action('menu_category_add_form_fields', 'wtm_menu_category_add_form_fields');
function wtm_menu_category_add_form_fields($taxonomy) {
    ?>
    <div class="form-field term-image-wrap">
        <label><?php _e('Category Image', 'webtalize-menu'); ?></label>
        <div id="wtm-category-image-preview" class="wtm-category-image-preview" style="margin-bottom:10px;"></div>
        <input type="hidden" name="wtm_category_image_id" id="wtm_category_image_id" class="wtm-category-image-id" value="">
        <button type="button" class="button wtm-upload-image-btn"><?php _e('Upload/Add Image', 'webtalize-menu'); ?></button>
        <button type="button" class="button wtm-remove-image-btn" style="display:none;"><?php _e('Remove Image', 'webtalize-menu'); ?></button>
    </div>
    <div class="form-field term-order-wrap">
        <label for="wtm_category_order"><?php _e('Sort Order', 'webtalize-menu'); ?></label>
        <input type="number" name="wtm_category_order" id="wtm_category_order" value="0">
        <p><?php _e('Numeric value for sorting categories.', 'webtalize-menu'); ?></p>
    </div>
    <?php
}

// Add fields to "Edit Category" form
add_action('menu_category_edit_form_fields', 'wtm_menu_category_edit_form_fields', 10, 2);
function wtm_menu_category_edit_form_fields($term, $taxonomy) {
    $image_id = get_term_meta($term->term_id, 'wtm_category_image_id', true);
    $order = get_term_meta($term->term_id, 'wtm_category_order', true);
    if ($order === '') $order = 0;
    $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
    
    if (is_ssl() && !empty($image_url)) {
        $image_url = set_url_scheme($image_url, 'https');
    }
    
    ?>
    <tr class="form-field term-image-wrap">
        <th scope="row"><label><?php _e('Category Image', 'webtalize-menu'); ?></label></th>
        <td>
            <div id="wtm-category-image-preview" class="wtm-category-image-preview" style="margin-bottom:10px;">
                <?php if ($image_url) echo '<img src="' . esc_url($image_url) . '" style="max-width:150px;height:auto;" />'; ?>
            </div>
            <input type="hidden" name="wtm_category_image_id" id="wtm_category_image_id" class="wtm-category-image-id" value="<?php echo esc_attr($image_id); ?>">
            <button type="button" class="button wtm-upload-image-btn"><?php _e('Upload/Add Image', 'webtalize-menu'); ?></button>
            <button type="button" class="button wtm-remove-image-btn" <?php echo $image_id ? '' : 'style="display:none;"'; ?>><?php _e('Remove Image', 'webtalize-menu'); ?></button>
        </td>
    </tr>
    <tr class="form-field term-order-wrap">
        <th scope="row"><label for="wtm_category_order"><?php _e('Sort Order', 'webtalize-menu'); ?></label></th>
        <td>
            <input type="number" name="wtm_category_order" id="wtm_category_order" value="<?php echo esc_attr($order); ?>">
            <p class="description"><?php _e('Numeric value for sorting categories.', 'webtalize-menu'); ?></p>
        </td>
    </tr>
    <?php
}

// Save Category Meta
add_action('created_menu_category', 'wtm_save_menu_category_meta');
add_action('edited_menu_category', 'wtm_save_menu_category_meta');
function wtm_save_menu_category_meta($term_id) {
    if (isset($_POST['wtm_category_image_id'])) {
        $image_id = sanitize_text_field($_POST['wtm_category_image_id']);
        if (!empty($image_id)) {
            update_term_meta($term_id, 'wtm_category_image_id', $image_id);
        } else {
            // Delete meta if image ID is empty (removed image)
            delete_term_meta($term_id, 'wtm_category_image_id');
        }
    }
    if (isset($_POST['wtm_category_order'])) {
        update_term_meta($term_id, 'wtm_category_order', intval($_POST['wtm_category_order']));
    }
}

// Add Columns to Category List
add_filter('manage_edit-menu_category_columns', 'wtm_menu_category_columns');
function wtm_menu_category_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $val) {
        if ($key === 'name') {
            $new_columns['thumb'] = __('Image', 'webtalize-menu');
        }
        $new_columns[$key] = $val;
    }
    $new_columns['order'] = __('Order', 'webtalize-menu');
    return $new_columns;
}

add_action('manage_menu_category_custom_column', 'wtm_menu_category_custom_column', 10, 3);
function wtm_menu_category_custom_column($content, $column_name, $term_id) {
    if ($column_name === 'thumb') {
        $image_id = get_term_meta($term_id, 'wtm_category_image_id', true);
        $image_url = '';
        if ($image_id) {
            $img = wp_get_attachment_image_src($image_id, 'thumbnail');
            if ($img) {
                $image_url = $img[0];
                if (is_ssl()) $image_url = set_url_scheme($image_url, 'https');
            }
        }
        $out = $image_url ? '<img src="' . esc_url($image_url) . '" style="width:50px;height:auto;" />' : '';
        $out .= '<span class="hidden wtm-image-data" data-id="'.esc_attr($image_id).'" data-url="'.esc_attr($image_url).'"></span>';
        return $out;
    }
    if ($column_name === 'order') {
        $order = get_term_meta($term_id, 'wtm_category_order', true);
        if ($order === '' || $order === false) {
            $order = 0;
        }
        return esc_html($order) . '<span class="hidden wtm-order-data" data-val="'.esc_attr($order).'"></span>';
    }
    return $content;
}

// Make Order column sortable
add_filter('manage_edit-menu_category_sortable_columns', 'wtm_menu_category_sortable_columns');
function wtm_menu_category_sortable_columns($columns) {
    // Map 'order' to 'meta_value_num' so WordPress knows it's a meta field
    // We'll override the actual sorting in terms_clauses
    $columns['order'] = 'meta_value_num';
    return $columns;
}

// Debug: Output visible debug info on the page
add_action('admin_notices', 'wtm_debug_admin_notice');
function wtm_debug_admin_notice() {
    if (!isset($_GET['taxonomy']) || $_GET['taxonomy'] !== 'menu_category') {
        return;
    }
    $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : 'not set';
    $order = isset($_GET['order']) ? $_GET['order'] : 'not set';
    echo '<div class="notice notice-info is-dismissible"><p>';
    echo '<strong>WTM Debug:</strong> orderby=' . esc_html($orderby) . ', order=' . esc_html($order);
    echo '</p></div>';
}

// Handle sorting by Order column using pre_get_terms  
add_action('pre_get_terms', 'wtm_menu_category_pre_get_terms');
function wtm_menu_category_pre_get_terms($query) {
    // Only apply to menu_category taxonomy on the edit-tags.php admin page
    if (!is_admin() || !isset($_GET['taxonomy']) || $_GET['taxonomy'] !== 'menu_category') {
        return;
    }
    
    // Check if we're ordering by 'order' OR 'meta_value_num' (WordPress converts 'order' to 'meta_value_num' in URL)
    $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
    if ($orderby !== 'order' && $orderby !== 'meta_value_num') {
        return;
    }
    
    // Set meta_key and orderby to meta_value_num so WordPress knows we're sorting by meta
    // But we'll override the JOIN in terms_clauses to use LEFT JOIN instead of INNER JOIN
    $query->query_vars['meta_key'] = 'wtm_category_order';
    $query->query_vars['orderby'] = 'meta_value_num';
    
    // Set the order direction
    $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
    if ($order !== 'ASC' && $order !== 'DESC') {
        $order = 'ASC';
    }
    $query->query_vars['order'] = $order;
}

// Handle sorting using terms_clauses
// Use priority 999 to run LAST, after all other filters, so we can override completely
add_filter('terms_clauses', 'wtm_menu_category_orderby_clauses', 999, 3);
function wtm_menu_category_orderby_clauses($clauses, $taxonomies, $args) {
    // Debug: Always log when filter is called (for troubleshooting)
    static $debug_filter_called = false;
    if (!$debug_filter_called && is_admin() && isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'menu_category') {
        add_action('admin_footer', function() use ($taxonomies, $args) {
            echo '<script type="text/javascript">';
            echo 'console.log("=== WTM terms_clauses Filter Called ===");';
            echo 'console.log("GET taxonomy: ' . (isset($_GET['taxonomy']) ? esc_js($_GET['taxonomy']) : 'not set') . '");';
            echo 'console.log("GET orderby: ' . (isset($_GET['orderby']) ? esc_js($_GET['orderby']) : 'not set') . '");';
            echo 'console.log("GET order: ' . (isset($_GET['order']) ? esc_js($_GET['order']) : 'not set') . '");';
            echo 'console.log("Args orderby: ' . (isset($args['orderby']) ? esc_js($args['orderby']) : 'not set') . '");';
            echo 'console.log("Args meta_key: ' . (isset($args['meta_key']) ? esc_js($args['meta_key']) : 'not set') . '");';
            echo 'console.log("Taxonomies: ' . esc_js(is_array($taxonomies) ? implode(', ', $taxonomies) : $taxonomies) . '");';
            echo 'console.log("================================");';
            echo '</script>';
        }, 999);
        $debug_filter_called = true;
    }
    
    // Only apply in admin area
    if (!is_admin()) {
        return $clauses;
    }
    
    // Only apply on the edit-tags.php page for menu_category taxonomy
    if (!isset($_GET['taxonomy']) || $_GET['taxonomy'] !== 'menu_category') {
        return $clauses;
    }
    
    // Convert to array if it's a string
    $tax_array = is_array($taxonomies) ? $taxonomies : array($taxonomies);
    
    // Also check if menu_category is in the taxonomies array
    if (!empty($tax_array) && !in_array('menu_category', $tax_array)) {
        return $clauses;
    }
    
    // Check if we're ordering by 'order' OR 'meta_value_num' (WordPress converts 'order' to 'meta_value_num' in URL)
    // The column key is 'order' as defined in manage_edit-menu_category_sortable_columns
    // WordPress converts it to 'meta_value_num' in the URL, and meta_key might not be set yet
    $is_ordering_by_order = false;
    $get_orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
    $args_orderby = isset($args['orderby']) ? $args['orderby'] : '';
    $args_meta_key = isset($args['meta_key']) ? $args['meta_key'] : '';
    
    if ($get_orderby === 'order' || $get_orderby === 'meta_value_num') {
        $is_ordering_by_order = true;
    } elseif ($args_orderby === 'meta_value_num') {
        // If orderby is meta_value_num, check if meta_key matches OR if it's empty (might be set in pre_get_terms)
        if ($args_meta_key === 'wtm_category_order' || empty($args_meta_key)) {
            $is_ordering_by_order = true;
        }
    }
    
    // Debug: Log if we're not ordering by order
    if (!$is_ordering_by_order) {
        static $debug_not_ordering = false;
        if (!$debug_not_ordering && isset($_GET['orderby'])) {
            add_action('admin_footer', function() use ($args) {
                echo '<script type="text/javascript">';
                echo 'console.log("⚠️ WTM: Filter called but NOT ordering by order");';
                echo 'console.log("GET orderby: ' . (isset($_GET['orderby']) ? esc_js($_GET['orderby']) : 'not set') . '");';
                echo 'console.log("Args orderby: ' . (isset($args['orderby']) ? esc_js($args['orderby']) : 'not set') . '");';
                echo 'console.log("Args meta_key: ' . (isset($args['meta_key']) ? esc_js($args['meta_key']) : 'not set') . '");';
                echo '</script>';
            }, 999);
            $debug_not_ordering = true;
        }
        return $clauses;
    }
    
    // Output debug info to console
    static $debug_initial = false;
    if (!$debug_initial) {
        add_action('admin_footer', function() use ($args) {
            echo '<script type="text/javascript">';
            echo 'console.log("=== WTM terms_clauses Debug ===");';
            echo 'console.log("Filter was called for orderby=order");';
            echo 'console.log("GET orderby: ' . (isset($_GET['orderby']) ? esc_js($_GET['orderby']) : 'not set') . '");';
            echo 'console.log("GET order: ' . (isset($_GET['order']) ? esc_js($_GET['order']) : 'not set') . '");';
            echo 'console.log("Args orderby: ' . (isset($args['orderby']) ? esc_js($args['orderby']) : 'not set') . '");';
            echo 'console.log("Args meta_key: ' . (isset($args['meta_key']) ? esc_js($args['meta_key']) : 'not set') . '");';
            echo '</script>';
        }, 999);
        $debug_initial = true;
    }
    
    global $wpdb;
    
    // Get order direction
    $order = 'ASC';
    if (isset($args['order']) && !empty($args['order'])) {
        $order = strtoupper($args['order']);
    } elseif (isset($_GET['order']) && !empty($_GET['order'])) {
        $order = strtoupper(sanitize_text_field($_GET['order']));
    }
    if ($order !== 'ASC' && $order !== 'DESC') {
        $order = 'ASC';
    }
    
    // Initialize clauses if they don't exist
    if (!isset($clauses['join'])) {
        $clauses['join'] = '';
    }
    if (!isset($clauses['where'])) {
        $clauses['where'] = '';
    }
    if (!isset($clauses['fields'])) {
        $clauses['fields'] = '';
    }
    
    // Check if FIELDS clause references wp_termmeta (which we removed)
    // WordPress might add wp_termmeta.meta_value to the SELECT, which would fail
    if (!empty($clauses['fields']) && strpos($clauses['fields'], 'wp_termmeta') !== false && strpos($clauses['fields'], 'wtm_order_meta') === false) {
        $old_fields = $clauses['fields'];
        // Remove wp_termmeta references from FIELDS since we removed that JOIN
        $clauses['fields'] = preg_replace('/,\s*wp_termmeta\.\w+/i', '', $clauses['fields']);
        $clauses['fields'] = preg_replace('/wp_termmeta\.\w+\s*,/i', '', $clauses['fields']);
        // Also handle if it's the only field or at the start
        $clauses['fields'] = preg_replace('/^\s*wp_termmeta\.\w+\s*/i', '', $clauses['fields']);
        
        add_action('admin_footer', function() use ($old_fields, $clauses) {
            echo '<script type="text/javascript">';
            echo 'console.log("⚠️ Removed wp_termmeta references from FIELDS clause");';
            echo 'console.log("Old FIELDS: ' . esc_js(substr($old_fields, 0, 200)) . '");';
            echo 'console.log("New FIELDS: ' . esc_js(substr($clauses['fields'], 0, 200)) . '");';
            echo '</script>';
        }, 999);
    }
    
    // Always log the FIELDS clause for debugging
    static $debug_fields = false;
    if (!$debug_fields && !empty($clauses['fields'])) {
        add_action('admin_footer', function() use ($clauses) {
            echo '<script type="text/javascript">';
            echo 'console.log("=== WTM FIELDS Clause ===");';
            echo 'console.log("FIELDS: ' . esc_js(substr($clauses['fields'], 0, 300)) . '");';
            echo 'console.log("Contains wp_termmeta: ' . (strpos($clauses['fields'], 'wp_termmeta') !== false ? 'YES' : 'NO') . '");';
            echo 'console.log("=========================");';
            echo '</script>';
        }, 999);
        $debug_fields = true;
    }
    
    // IMPORTANT: Don't modify WHERE clause - only JOIN, ORDERBY, and FIELDS (if needed)
    
    // Output initial clauses to console
    static $debug_clauses = false;
    if (!$debug_clauses) {
        $initial_join = isset($clauses['join']) ? $clauses['join'] : '';
        $initial_where = isset($clauses['where']) ? $clauses['where'] : '';
        $initial_orderby = isset($clauses['orderby']) ? $clauses['orderby'] : '';
        add_action('admin_footer', function() use ($initial_join, $initial_where, $initial_orderby) {
            echo '<script type="text/javascript">';
            echo 'console.log("=== WTM Initial SQL Clauses ===");';
            echo 'console.log("Initial JOIN: ' . esc_js(substr($initial_join, 0, 400)) . '");';
            echo 'console.log("Initial WHERE: ' . esc_js(substr($initial_where, 0, 400)) . '");';
            echo 'console.log("Initial ORDERBY: ' . esc_js(substr($initial_orderby, 0, 200)) . '");';
            echo '</script>';
        }, 999);
        $debug_clauses = true;
    }
    
    // Remove WordPress's INNER JOIN for wp_termmeta if it exists
    // WordPress might add this even without meta_key if it thinks we're sorting by meta
    // This INNER JOIN excludes terms without meta values, which is why categories disappear
    $old_join = isset($clauses['join']) ? $clauses['join'] : '';
    if (strpos($clauses['join'], 'wp_termmeta') !== false && strpos($clauses['join'], 'wtm_order_meta') === false) {
        // Remove WordPress's INNER JOIN for wp_termmeta (the one without an alias)
        // Pattern: INNER JOIN wp_termmeta ON ( t.term_id = wp_termmeta.term_id )
        // More flexible pattern to match various spacing
        $clauses['join'] = preg_replace('/\s*INNER\s+JOIN\s+wp_termmeta\s+ON\s*\([^)]+\)/i', '', $clauses['join']);
        // Clean up any double spaces that might result
        $clauses['join'] = preg_replace('/\s+/', ' ', $clauses['join']);
        $clauses['join'] = trim($clauses['join']);
        
        // Log removal to console
        add_action('admin_footer', function() use ($old_join, $clauses) {
            echo '<script type="text/javascript">';
            echo 'console.log("⚠️ Removed WordPress INNER JOIN for wp_termmeta");';
            echo 'console.log("Old JOIN: ' . esc_js(substr($old_join, 0, 400)) . '");';
            echo 'console.log("New JOIN: ' . esc_js(substr($clauses['join'], 0, 400)) . '");';
            echo '</script>';
        }, 999);
    } else {
        // Log that no removal was needed
        add_action('admin_footer', function() use ($clauses) {
            echo '<script type="text/javascript">';
            echo 'console.log("✓ No wp_termmeta INNER JOIN found (or already using wtm_order_meta)");';
            echo 'console.log("Current JOIN: ' . esc_js(substr(isset($clauses['join']) ? $clauses['join'] : '', 0, 400)) . '");';
            echo '</script>';
        }, 999);
    }
    
    // Remove the WHERE clause condition that references wp_termmeta.meta_key if it exists
    // WordPress adds: wp_termmeta.meta_key = 'wtm_category_order' to WHERE
    // This condition requires the INNER JOIN and excludes terms without meta
    if (isset($clauses['where']) && strpos($clauses['where'], 'wp_termmeta.meta_key') !== false) {
        $old_where = $clauses['where'];
        // Remove the meta_key condition from WHERE clause
        // Pattern: AND ( wp_termmeta.meta_key = 'wtm_category_order' )
        // More flexible pattern to match various spacing
        $clauses['where'] = preg_replace('/\s*AND\s*\(\s*wp_termmeta\.meta_key\s*=\s*[\'"]wtm_category_order[\'"]\s*\)/i', '', $clauses['where']);
        // Clean up any double spaces
        $clauses['where'] = preg_replace('/\s+/', ' ', $clauses['where']);
        $clauses['where'] = trim($clauses['where']);
        
        // Log removal to console
        add_action('admin_footer', function() use ($old_where, $clauses) {
            echo '<script type="text/javascript">';
            echo 'console.log("⚠️ Removed wp_termmeta.meta_key condition from WHERE");';
            echo 'console.log("Old WHERE: ' . esc_js(substr($old_where, 0, 400)) . '");';
            echo 'console.log("New WHERE: ' . esc_js(substr(isset($clauses['where']) ? $clauses['where'] : '', 0, 400)) . '");';
            echo '</script>';
        }, 999);
    } else {
        // Log that no removal was needed
        add_action('admin_footer', function() use ($clauses) {
            echo '<script type="text/javascript">';
            echo 'console.log("✓ No wp_termmeta.meta_key condition found in WHERE");';
            echo 'console.log("Current WHERE: ' . esc_js(substr(isset($clauses['where']) ? $clauses['where'] : '', 0, 400)) . '");';
            echo '</script>';
        }, 999);
    }
    
    // Add our LEFT JOIN (only if not already present)
    // Use LEFT JOIN to include all terms, even those without order meta
    // WordPress uses 't' as the alias for wp_terms table in term queries
    if (strpos($clauses['join'], 'wtm_order_meta') === false) {
        $clauses['join'] .= " LEFT JOIN {$wpdb->termmeta} AS wtm_order_meta ON t.term_id = wtm_order_meta.term_id AND wtm_order_meta.meta_key = 'wtm_category_order'";
        
        // Log addition to console
        add_action('admin_footer', function() use ($clauses) {
            echo '<script type="text/javascript">';
            echo 'console.log("Added LEFT JOIN with wtm_order_meta alias");';
            echo 'console.log("Final JOIN: ' . esc_js(substr($clauses['join'], 0, 400)) . '");';
            echo '</script>';
        }, 999);
    }
    
    // IMPORTANT: Make sure the JOIN exists before setting ORDERBY
    $has_wtm_join = strpos($clauses['join'], 'wtm_order_meta') !== false;
    
    if (!$has_wtm_join) {
        add_action('admin_footer', function() use ($clauses) {
            echo '<script type="text/javascript">';
            echo 'console.error("⚠️ CRITICAL ERROR: JOIN with wtm_order_meta is missing!");';
            echo 'console.log("JOIN: ' . esc_js(substr($clauses['join'], 0, 400)) . '");';
            echo '</script>';
        }, 999);
        return $clauses;
    }
    
    // Get the old orderby to check what WordPress set
    $old_orderby = isset($clauses['orderby']) ? $clauses['orderby'] : '';
    
    // CRITICAL FIX: The SQL error shows "ORDER BY" keyword is missing
    // WordPress's terms_clauses['orderby'] should contain ONLY the ordering expressions
    // WordPress will prepend "ORDER BY " when building the SQL
    // But the SQL error suggests WordPress is NOT adding "ORDER BY" - this might be because
    // WordPress checks if orderby matches query_vars['orderby'], and if not, it might skip it
    // However, we're setting orderby='meta_value_num' in pre_get_terms, so it should work
    
    // Remove any "ORDER BY" keyword if WordPress already added it (shouldn't happen, but just in case)
    $old_orderby_clean = preg_replace('/^\s*ORDER\s+BY\s+/i', '', trim($old_orderby));
    
    // FIX: Include "ORDER BY" explicitly - WordPress is NOT adding it automatically
    // Do NOT include direction (ASC/DESC) - let WordPress add it based on query_vars['order']
    // Use '0' as default for NULL/empty values so they sort as 0
    // Put NULL/0 values at END regardless of ASC/DESC direction
    // First CASE: Always ASC to put real values (0) first, NULL/0 (1) last
    // Second: Sort by actual value with direction (ASC or DESC) - use 999999 for NULL/0 so they stay at end
    // Third: term_id as tiebreaker with same direction
    // FIX: WordPress only adds direction to the LAST expression in ORDER BY
    // So we need to add direction to the second expression ourselves
    // - CASE: Always ASC (ensures NULL/0 go to end) 
    // - Second (value sort): Use $order variable (ASC or DESC) - we add it explicitly
    // - Third (term_id): WordPress will add direction automatically (matches $order)
    $clauses['orderby'] = "ORDER BY CASE WHEN COALESCE(NULLIF(wtm_order_meta.meta_value, ''), '0') IS NULL OR CAST(COALESCE(NULLIF(wtm_order_meta.meta_value, ''), '0') AS SIGNED) = 0 THEN 1 ELSE 0 END ASC, CAST(COALESCE(NULLIF(wtm_order_meta.meta_value, ''), '999999') AS SIGNED) {$order}, t.term_id";
    
    // Debug: Log what order value we're using
    add_action('admin_footer', function() use ($order, $clauses) {
        echo '<script type="text/javascript">';
        echo 'console.log("=== WTM ORDERBY Direction Debug ===");';
        echo 'console.log("Order direction variable: ' . esc_js($order) . '");';
        echo 'console.log("Full ORDERBY clause: ' . esc_js($clauses['orderby']) . '");';
        echo 'console.log("Should contain: CAST(...) ' . esc_js($order) . '");';
        echo '</script>';
    }, 999);
    
    // Include direction explicitly since WordPress is NOT adding it automatically
    // First CASE always ASC (ensures NULL/0 go to end)
    // Second and third use $order (ASC or DESC) from query_vars
    
    // Debug: Show what we're setting
    add_action('admin_footer', function() use ($clauses, $old_orderby, $order) {
        echo '<script type="text/javascript">';
        echo 'console.log("=== WTM ORDERBY Setting (FIXED) ===");';
        echo 'console.log("Old ORDERBY: ' . esc_js($old_orderby) . '");';
        echo 'console.log("New ORDERBY: ' . esc_js($clauses['orderby']) . '");';
        echo 'console.log("Order direction: ' . esc_js($order) . '");';
        echo 'console.log("Expected final SQL should have: ORDER BY ' . esc_js($clauses['orderby']) . '");';
        echo '</script>';
    }, 999);
    
    // Debug: Verify the JOIN exists before we set ORDERBY
    add_action('admin_footer', function() use ($clauses) {
        $has_join = strpos($clauses['join'], 'wtm_order_meta') !== false;
        echo '<script type="text/javascript">';
        echo 'console.log("=== WTM ORDERBY Check ===");';
        echo 'console.log("JOIN contains wtm_order_meta: ' . ($has_join ? 'YES' : 'NO') . '");';
        echo 'console.log("ORDERBY: ' . esc_js($clauses['orderby']) . '");';
        if (!$has_join) {
            echo 'console.error("⚠️ ERROR: ORDERBY references wtm_order_meta but JOIN is missing!");';
        }
        echo '</script>';
    }, 999);
    
    // Debug: Log the ORDERBY change
    add_action('admin_footer', function() use ($old_orderby, $clauses, $order) {
        echo '<script type="text/javascript">';
        echo 'console.log("=== WTM ORDERBY Override ===");';
        echo 'console.log("Old ORDERBY: ' . esc_js($old_orderby) . '");';
        echo 'console.log("New ORDERBY: ' . esc_js($clauses['orderby']) . '");';
        echo 'console.log("Order direction: ' . esc_js($order) . '");';
        echo 'console.log("===========================");';
        echo '</script>';
    }, 999);
    
    // Output final clauses to console
    static $debug_final = false;
    if (!$debug_final) {
        add_action('admin_footer', function() use ($clauses, $order) {
            echo '<script type="text/javascript">';
            echo 'console.log("=== WTM Final SQL Clauses ===");';
            echo 'console.log("Final JOIN: ' . esc_js(substr(isset($clauses['join']) ? $clauses['join'] : '', 0, 400)) . '");';
            echo 'console.log("Final WHERE: ' . esc_js(substr(isset($clauses['where']) ? $clauses['where'] : '', 0, 400)) . '");';
            echo 'console.log("Final ORDERBY: ' . esc_js(isset($clauses['orderby']) ? $clauses['orderby'] : 'not set') . '");';
            echo 'console.log("Order direction: ' . esc_js($order) . '");';
            echo 'console.log("============================");';
            echo '</script>';
        }, 999);
        $debug_final = true;
    }
    
    return $clauses;
}

// Note: We don't use get_terms_orderby filter because it runs before the JOIN is added
// All sorting is handled in terms_clauses filter where we have access to the JOIN

// Debug: Log what get_terms actually returns
add_filter('get_terms', 'wtm_debug_get_terms_results', 10, 4);
function wtm_debug_get_terms_results($terms, $taxonomies, $args, $term_query) {
    // Only log when sorting by order or meta_value_num
    if (!is_admin() || !isset($_GET['taxonomy']) || $_GET['taxonomy'] !== 'menu_category') {
        return $terms;
    }
    
    $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
    if ($orderby !== 'order' && $orderby !== 'meta_value_num') {
        return $terms;
    }
    
    // Check if this is a COUNT query (fields = count) - these might fail differently
    $is_count_query = isset($args['fields']) && $args['fields'] === 'count';
    
    $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : '';
    if ($orderby !== 'order' && $orderby !== 'meta_value_num') {
        return $terms;
    }
    
    static $query_count = 0;
    $query_count++;
    
    // Try to get the actual SQL query from the term_query object
    $sql_debug = '';
    if (is_object($term_query)) {
        // Try different methods to get SQL
        if (property_exists($term_query, 'sql')) {
            $sql_debug = $term_query->sql;
        } elseif (method_exists($term_query, 'get_sql')) {
            try {
                $sql_debug = $term_query->get_sql();
            } catch (Exception $e) {
                $sql_debug = 'Error: ' . $e->getMessage();
            }
        } elseif (property_exists($term_query, 'request')) {
            $sql_debug = $term_query->request;
        }
        
        // Also check for database errors
        global $wpdb;
        if (!empty($wpdb->last_error)) {
            $sql_debug .= ' | DB Error: ' . $wpdb->last_error;
        }
    }
    
    if (is_array($terms)) {
        $term_count = count($terms);
        $term_ids = array();
        $term_data = array(); // Store term ID, name, and order value
        
        foreach ($terms as $term) {
            if (is_object($term) && isset($term->term_id)) {
                $term_ids[] = $term->term_id;
                $order_value = get_term_meta($term->term_id, 'wtm_category_order', true);
                $term_data[] = array(
                    'id' => $term->term_id,
                    'name' => isset($term->name) ? $term->name : 'N/A',
                    'order' => $order_value !== '' && $order_value !== false ? $order_value : 'NULL/empty'
                );
            }
        }
        
        add_action('admin_footer', function() use ($term_count, $term_ids, $query_count, $args, $sql_debug, $term_data) {
            echo '<script type="text/javascript">';
            echo 'console.log("=== WTM get_terms Results (Query #' . $query_count . ') ===");';
            echo 'console.log("Number of terms returned: ' . $term_count . '");';
            echo 'console.log("");';
            echo 'console.log("📊 CATEGORY META VALUES (wtm_category_order):");';
            echo 'console.log("==========================================");';
            
            if ($term_count > 0) {
                // Show all terms with their meta values
                $display_count = min($term_count, 50); // Show up to 50 terms
                for ($i = 0; $i < $display_count; $i++) {
                    $data = $term_data[$i];
                    $order_display = $data['order'] === 'NULL/empty' ? 'NULL/empty (will sort as 0)' : $data['order'];
                    echo 'console.log("' . ($i + 1) . '. ID: ' . $data['id'] . ' | Name: ' . esc_js($data['name']) . ' | meta_value: ' . esc_js($order_display) . '");';
                }
                
                if ($term_count > 50) {
                    echo 'console.log("... and ' . ($term_count - 50) . ' more terms");';
                }
                
                echo 'console.log("");';
                
                // Summary statistics
                $null_count = 0;
                $has_value_count = 0;
                $order_values = array();
                foreach ($term_data as $data) {
                    if ($data['order'] === 'NULL/empty') {
                        $null_count++;
                    } else {
                        $has_value_count++;
                        $order_values[] = $data['order'];
                    }
                }
                
                echo 'console.log("📈 SUMMARY:");';
                echo 'console.log("  - Terms with order values: ' . $has_value_count . '");';
                echo 'console.log("  - Terms with NULL/empty order: ' . $null_count . '");';
                if ($has_value_count > 0) {
                    echo 'console.log("  - Order value range: ' . min($order_values) . ' to ' . max($order_values) . '");';
                }
                
                if ($null_count === $term_count) {
                    echo 'console.warn("⚠️ ALL categories have NULL/empty order values!");';
                    echo 'console.warn("⚠️ They will all sort as 0, then by term_id (which may look like sorting by name)");';
                    echo 'console.warn("⚠️ Please set Sort Order values using Quick Edit!");';
                } elseif ($null_count > 0) {
                    echo 'console.warn("⚠️ Some categories (' . $null_count . ') have NULL/empty order values - they will sort as 0");';
                }
            } else {
                echo 'console.error("⚠️ NO TERMS RETURNED - This is why categories disappear!");';
            }
            
            echo 'console.log("");';
            echo 'console.log("Args fields: ' . (isset($args['fields']) ? esc_js($args['fields']) : 'not set') . '");';
            if (!empty($sql_debug)) {
                // Output full SQL query - split into multiple console.log if too long
                $sql_length = strlen($sql_debug);
                echo 'console.log("SQL Query Length: ' . $sql_length . ' characters");';
                if ($sql_length > 1500) {
                    // Split into chunks to avoid browser console limits
                    $chunks = str_split($sql_debug, 1500);
                    echo 'console.log("SQL Query (full, split into ' . count($chunks) . ' parts):");';
                    foreach ($chunks as $idx => $chunk) {
                        echo 'console.log("Part ' . ($idx + 1) . '/' . count($chunks) . ': ' . esc_js($chunk) . '");';
                    }
                } else {
                    echo 'console.log("SQL Query (full): ' . esc_js($sql_debug) . '");';
                }
            } else {
                echo 'console.warn("⚠️ SQL query is empty - cannot debug!");';
            }
            echo 'console.log("===========================");';
            echo '</script>';
        }, 999);
    }
    
    return $terms;
}

// Fix mixed content for site icon if SSL is on
add_filter('get_site_icon_url', 'wtm_fix_site_icon_url');
function wtm_fix_site_icon_url($url) {
    if (is_ssl() && !empty($url) && strpos($url, 'http://') === 0) {
        return set_url_scheme($url, 'https');
    }
    return $url;
}