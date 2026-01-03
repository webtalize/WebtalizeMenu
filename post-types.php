<?php
// Create custom post type for menu items.
function wtm_create_post_type() {
    $labels = array(
        'name'                  => _x('Menu Items', 'Post Type General Name', 'webtalize-menu'),
        'singular_name'         => _x('Menu Item', 'Post Type Singular Name', 'webtalize-menu'),
        'menu_name'             => __('Menu Items', 'webtalize-menu'),
        'name_admin_bar'        => __('Menu Item', 'webtalize-menu'),
        'archives'              => __('Menu Item Archives', 'webtalize-menu'),
        'attributes'            => __('Menu Item Attributes', 'webtalize-menu'),
        'parent_item_colon'     => __('Parent Menu Item:', 'webtalize-menu'),
        'all_items'             => __('All Menu Items', 'webtalize-menu'),
        'add_new_item'          => __('Add New Menu Item', 'webtalize-menu'),
        'add_new'               => __('Add New', 'webtalize-menu'),
        'new_item'              => __('New Menu Item', 'webtalize-menu'),
        'edit_item'             => __('Edit Menu Item', 'webtalize-menu'),
        'update_item'           => __('Update Menu Item', 'webtalize-menu'),
        'view_item'             => __('View Menu Item', 'webtalize-menu'),
        'view_items'            => __('View Menu Items', 'webtalize-menu'),
        'search_items'          => __('Search Menu Item', 'webtalize-menu'),
        'not_found'             => __('Not found', 'webtalize-menu'),
        'not_found_in_trash'    => __('Not found in Trash', 'webtalize-menu'),
        'featured_image'        => __('Featured Image', 'webtalize-menu'),
        'set_featured_image'    => __('Set featured image', 'webtalize-menu'),
        'remove_featured_image' => __('Remove featured image', 'webtalize-menu'),
        'use_featured_image'    => __('Use as featured image', 'webtalize-menu'),
        'insert_into_item'      => __('Insert into menu item', 'webtalize-menu'),
        'uploaded_to_this_item' => __('Uploaded to this menu item', 'webtalize-menu'),
        'items_list'            => __('Menu items list', 'webtalize-menu'),
        'items_list_navigation' => __('Menu items list navigation', 'webtalize-menu'),
        'filter_items_list'     => __('Filter menu items list', 'webtalize-menu'),
    );
    $args = array(
        'label'                 => __('Menu Item', 'webtalize-menu'),
        'description'           => __('Restaurant Menu Items', 'webtalize-menu'),
        'labels'                => $labels,
        'supports'              => array('title', 'editor', 'thumbnail', 'custom-fields', 'page-attributes'),
        'taxonomies'            => array('menu_category'),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true, // This is key!
        'menu_position'         => 25, // Set a specific menu position
        'menu_icon' => 'dashicons-food', // Add an icon
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest' => true,
    );
    register_post_type('menu_item', $args);
}
add_action('init', 'wtm_create_post_type', 0);

// Create custom taxonomy for menu categories.
function wtm_create_taxonomy() {
    $labels = array(
        'name'                       => _x('Menu Categories', 'Taxonomy General Name', 'webtalize-menu'),
        'singular_name'              => _x('Menu Category', 'Taxonomy Singular Name', 'webtalize-menu'),
        'menu_name'                  => __('Menu Categories', 'webtalize-menu'),
        'all_items'                  => __('All Menu Categories', 'webtalize-menu'),
        'parent_item'                => __('Parent Menu Category', 'webtalize-menu'),
        'parent_item_colon'          => __('Parent Menu Category:', 'webtalize-menu'),
        'new_item_name'              => __('New Menu Category Name', 'webtalize-menu'),
        'add_new_item'               => __('Add New Menu Category', 'webtalize-menu'),
        'edit_item'                  => __('Edit Menu Category', 'webtalize-menu'),
        'update_item'                => __('Update Menu Category', 'webtalize-menu'),
        'view_item'                  => __('View Menu Category', 'webtalize-menu'),
        'separate_items_with_commas' => __('Separate menu categories with commas', 'webtalize-menu'),
        'add_or_remove_items'        => __('Add or remove menu categories', 'webtalize-menu'),
        'choose_from_most_used'      => __('Choose from the most used', 'webtalize-menu'),
        'not_found'                  => __('Not Found', 'webtalize-menu'),
        'no_terms'                   => __('No menu categories', 'webtalize-menu'),
        'items_list'                 => __('Menu categories list', 'webtalize-menu'),
        'items_list_navigation'      => __('Menu categories list navigation', 'webtalize-menu'),
    );
    $args = array(
        'hierarchical'          => true,
        'labels'                => $labels,
        'show_ui'               => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'menu-category'),
        'show_in_rest' => true,
    );
    register_taxonomy('menu_category', array('menu_item'), $args);
}
add_action('init', 'wtm_create_taxonomy', 0);

function wtm_register_meta_fields() {
    register_post_meta('menu_item', 'wtm_price', array(
        'type' => 'string',
        'description' => 'The price of the menu item',
        'single' => true,
        'show_in_rest' => true,
    ));
    register_post_meta('menu_item', 'wtm_description', array(
        'type' => 'string',
        'description' => 'A short description of the menu item',
        'single' => true,
        'show_in_rest' => true,
    ));
}
add_action('init', 'wtm_register_meta_fields');