<?php
// Enqueue scripts and styles.
function wtm_enqueue_scripts() {
    wp_enqueue_style('wtm-styles', WTM_PLUGIN_URL . 'css/wtm-styles.css', array(), '1.0.6');
    
}
add_action('wp_enqueue_scripts', 'wtm_enqueue_scripts');

//Shortcode to display the menu (modified to include price and description)
function wtm_display_menu($atts) {
    ob_start();

    // Get ALL categories (including those without sort order)
    // Don't use meta_key/orderby in get_terms() as it excludes terms without that meta key
    $terms = get_terms(array(
        'taxonomy' => 'menu_category',
        'hide_empty' => false,
    ));
    
    // Sort categories by their custom order (wtm_category_order)
    // Categories without sort order (NULL or 0) will appear at the end
    if (!empty($terms) && !is_wp_error($terms)) {
        usort($terms, function($a, $b) {
            $order_a = get_term_meta($a->term_id, 'wtm_category_order', true);
            $order_b = get_term_meta($b->term_id, 'wtm_category_order', true);
            
            // Convert to integers, defaulting to 999999 if empty/null/0
            // This ensures categories without sort order appear at the end
            $order_a = ($order_a !== '' && $order_a !== null && $order_a !== false && $order_a !== '0') ? intval($order_a) : 999999;
            $order_b = ($order_b !== '' && $order_b !== null && $order_b !== false && $order_b !== '0') ? intval($order_b) : 999999;
            
            if ($order_a !== $order_b) {
                return $order_a - $order_b;
            }
            // If same order, sort by name
            return strcmp($a->name, $b->name);
        });
    }

    if (!empty($terms) && !is_wp_error($terms)) {
        echo '<div class="wtm-menu-container">';
        foreach ($terms as $term) {
            echo '<section class="wtm-category">';
            
            // Get category image if available
            $image_id = get_term_meta($term->term_id, 'wtm_category_image_id', true);
            $image_url = '';
            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                if ($image_url && is_ssl()) {
                    $image_url = set_url_scheme($image_url, 'https');
                }
            }
            
            // Category header with image and title grouped together
            echo '<div class="wtm-category-header">';
            
            // Display category image if available
            if ($image_url) {
                echo '<div class="wtm-category-image">';
                echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($term->name) . '" />';
                echo '</div>';
            }
            
            echo '<h2>' . esc_html($term->name) . '</h2>';
            echo '</div>'; // Close category-header 

            $args = array(
                'post_type' => 'menu_item',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'menu_category',
                        'field' => 'term_id',
                        'terms' => $term->term_id,
                    ),
                ),
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            );

            $query = new WP_Query($args);
            $items = array();
            
            // Collect all items
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $items[] = array(
                        'ID' => get_the_ID(),
                        'title' => get_the_title(),
                        'price' => get_post_meta(get_the_ID(), 'wtm_price', true),
                        'description' => get_post_meta(get_the_ID(), 'wtm_description', true),
                    );
                }
                wp_reset_postdata();
            }
            
            // Sort items by title with natural numeric sorting
            // This ensures "7. Hot & Sour Soup" comes before "15. Cantonese Wor Wonton"
            usort($items, function($a, $b) {
                return strnatcmp($a['title'], $b['title']);
            });

            if (!empty($items)) {
                // Check if 3-column layout is enabled
                $three_column_enabled = get_option('wtm_three_column_layout', '0');
                $items_class = 'wtm-menu-items';
                if ($three_column_enabled === '1') {
                    $items_class .= ' wtm-three-column';
                }
                echo '<ul class="' . esc_attr($items_class) . '">';
                foreach ($items as $item) {
                    echo '<li class="wtm-menu-item">';

                    echo '<div class="wtm-item-header">'; // Container for name and price
                    echo '<h3 class="wtm-item-name">' . esc_html($item['title']) . '</h3>'; 
                    if ($item['price'] !== '') {
                        echo '<span class="wtm-item-price">$' . esc_html($item['price']) . '</span>';
                    }
                    echo '</div>'; // Close header

                    if ($item['description']) {
                        echo '<div class="wtm-item-description">' . wp_kses_post($item['description']) . '</div>';
                    }

                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p class="wtm-no-items">' . esc_html__('No items found in this category.', 'webtalize-menu') . '</p>';
            }
            echo '</section>'; // Close category section
        }
        echo '</div>'; // Close menu container
    } else {
        echo '<p class="wtm-no-categories">' . esc_html__('No menu categories found.', 'webtalize-menu') . '</p>';
    }

    return ob_get_clean();
}


add_shortcode('restaurant_menu', 'wtm_display_menu');
add_shortcode('wtm_menu', 'wtm_display_menu');