<?php
// Enqueue scripts and styles.
function wtm_enqueue_scripts() {
    wp_enqueue_style('wtm-styles', WTM_PLUGIN_URL . 'css/wtm-styles.css', array(), '1.0.3');
    wp_enqueue_script('wtm-scripts', WTM_PLUGIN_URL . 'js/wtm-quick-edit.js', array('jquery'), '1.0.3', true);
}
add_action('wp_enqueue_scripts', 'wtm_enqueue_scripts');

//Shortcode to display the menu (modified to include price and description)
function wtm_display_menu($atts) {
    ob_start();

    $terms = get_terms(array(
        'taxonomy' => 'menu_category',
        'hide_empty' => false,
    ));

    if (!empty($terms) && !is_wp_error($terms)) {
        echo '<div class="wtm-menu-container">';
        foreach ($terms as $term) {
            echo '<section class="wtm-category">';
            echo '<h2>' . esc_html($term->name) . '</h2>'; 

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
                'orderby' => 'menu_order title',
                'order' => 'ASC',
            );

            $query = new WP_Query($args);

            if ($query->have_posts()) {
                echo '<ul class="wtm-menu-items">';
                while ($query->have_posts()) {
                    $query->the_post();
                    echo '<li class="wtm-menu-item">';

                    echo '<div class="wtm-item-header">'; // Container for name and price
                    echo '<h3 class="wtm-item-name">' . esc_html(get_the_title()) . '</h3>'; 
                    $price = get_post_meta(get_the_ID(), 'wtm_price', true);
                    if ($price !== '') {
                        echo '<span class="wtm-item-price">$' . esc_html($price) . '</span>';
                    }
                    echo '</div>'; // Close header

                    $description = get_post_meta(get_the_ID(), 'wtm_description', true);
                    if ($description) {
                        echo '<div class="wtm-item-description">' . wp_kses_post($description) . '</div>';
                    }

                    echo '</li>';
                }
                echo '</ul>';
                wp_reset_postdata();
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