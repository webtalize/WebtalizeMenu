<?php
// Enqueue scripts and styles.
function wtm_enqueue_scripts() {
    wp_enqueue_style('wtm-styles', WTM_PLUGIN_URL . 'css/wtm-styles.css', array(), '1.0.8');
    
}
add_action('wp_enqueue_scripts', 'wtm_enqueue_scripts');

function wtm_format_time_display($time) {
    if (empty($time)) {
        return '';
    }
    $dt = date_create_from_format('H:i', $time, wp_timezone());
    if (!$dt) {
        return $time;
    }
    return $dt->format(get_option('time_format'));
}

function wtm_format_datetime_display($value) {
    if (empty($value)) {
        return '';
    }
    $dt = date_create_from_format('Y-m-d H:i', $value, wp_timezone());
    if (!$dt) {
        return $value;
    }
    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $dt->getTimestamp());
}

function wtm_get_active_special_hours() {
    $specials = get_option('wtm_special_hours', array());
    if (empty($specials) || !is_array($specials)) {
        return array();
    }
    $now = current_time('timestamp');
    $active = array();
    foreach ($specials as $special) {
        $start = isset($special['start']) ? $special['start'] : '';
        $end = isset($special['end']) ? $special['end'] : '';
        if ($start === '' || $end === '') {
            continue;
        }
        $start_dt = date_create_from_format('Y-m-d H:i', $start, wp_timezone());
        $end_dt = date_create_from_format('Y-m-d H:i', $end, wp_timezone());
        if (!$start_dt || !$end_dt) {
            continue;
        }
        if ($now >= $start_dt->getTimestamp() && $now <= $end_dt->getTimestamp()) {
            $active[] = $special;
        }
    }
    return $active;
}

function wtm_format_hours_row($row) {
    if (!empty($row['closed'])) {
        return __('Closed', 'webtalize-menu');
    }
    $open = wtm_format_time_display(isset($row['open']) ? $row['open'] : '');
    $close = wtm_format_time_display(isset($row['close']) ? $row['close'] : '');
    $break_start = wtm_format_time_display(isset($row['break_start']) ? $row['break_start'] : '');
    $break_end = wtm_format_time_display(isset($row['break_end']) ? $row['break_end'] : '');

    if ($open === '' || $close === '') {
        return __('Hours not set', 'webtalize-menu');
    }

    if ($break_start !== '' && $break_end !== '') {
        return sprintf(
            __('%1$s - %2$s, break %3$s - %4$s', 'webtalize-menu'),
            $open,
            $close,
            $break_start,
            $break_end
        );
    }
    return sprintf(__('%1$s - %2$s', 'webtalize-menu'), $open, $close);
}

function wtm_get_today_status() {
    $now = date_create('now', wp_timezone());
    $current_time = $now->format('H:i');
    $today_key = strtolower($now->format('l'));
    
    // Helper function to calculate hours until opening
    $hours_until_open = function($open_time_str, $current_time_str) {
        $open_dt = date_create_from_format('H:i', $open_time_str, wp_timezone());
        $current_dt = date_create_from_format('H:i', $current_time_str, wp_timezone());
        if (!$open_dt || !$current_dt) {
            return null;
        }
        // Calculate difference in minutes
        $open_minutes = (int)$open_dt->format('H') * 60 + (int)$open_dt->format('i');
        $current_minutes = (int)$current_dt->format('H') * 60 + (int)$current_dt->format('i');
        
        // Since we know current_time < open_time, calculate hours until open
        $minutes_diff = $open_minutes - $current_minutes;
        $hours = $minutes_diff / 60;
        
        return $hours;
    };
    
    // Helper function to get before-hours message
    $get_before_hours_message = function($open_time_str, $current_time_str) use ($hours_until_open) {
        $hours = $hours_until_open($open_time_str, $current_time_str);
        if ($hours === null) {
            return __('We are not open yet.', 'webtalize-menu');
        }
        if ($hours <= 3) {
            return __('We are getting ready. See you later', 'webtalize-menu');
        }
        return __('We are not open yet.', 'webtalize-menu');
    };
    
    // Helper function to calculate minutes until closing
    $minutes_until_close = function($close_time_str, $current_time_str) {
        $close_dt = date_create_from_format('H:i', $close_time_str, wp_timezone());
        $current_dt = date_create_from_format('H:i', $current_time_str, wp_timezone());
        if (!$close_dt || !$current_dt) {
            return null;
        }
        // Calculate difference in minutes
        $close_minutes = (int)$close_dt->format('H') * 60 + (int)$close_dt->format('i');
        $current_minutes = (int)$current_dt->format('H') * 60 + (int)$current_dt->format('i');
        
        // Calculate minutes until close (if current is before close)
        $minutes_diff = $close_minutes - $current_minutes;
        
        return $minutes_diff;
    };
    
    // Check special hours first
    $active_specials = wtm_get_active_special_hours();
    if (!empty($active_specials)) {
        foreach ($active_specials as $special) {
            if (isset($special['type']) && $special['type'] === 'closed') {
                return array('status' => 'closed_all_day', 'message' => __('We are Closed Today', 'webtalize-menu'));
            }
            // If special hours are set, use them
            if (isset($special['open']) && isset($special['close'])) {
                $open_time = isset($special['open']) ? $special['open'] : '';
                $close_time = isset($special['close']) ? $special['close'] : '';
                if ($open_time !== '' && $close_time !== '') {
                    if ($current_time >= $open_time && $current_time <= $close_time) {
                        // Check if within 30 minutes of closing
                        $minutes = $minutes_until_close($close_time, $current_time);
                        if ($minutes !== null && $minutes <= 30 && $minutes >= 0) {
                            return array('status' => 'closing_soon', 'message' => __('We are closing soon. Call to see if we still accept takeout/delivery orders', 'webtalize-menu'));
                        }
                        return array('status' => 'open', 'message' => __('We are Open today', 'webtalize-menu'));
                    } elseif ($current_time < $open_time) {
                        $message = $get_before_hours_message($open_time, $current_time);
                        return array('status' => 'not_open_yet', 'message' => $message);
                    } else {
                        return array('status' => 'closed_after_hours', 'message' => __('We are now closed', 'webtalize-menu'));
                    }
                }
            }
        }
    }
    
    // Check regular hours
    $hours = get_option('wtm_opening_hours', array());
    if (empty($hours[$today_key])) {
        return array('status' => 'closed_all_day', 'message' => __('We are Closed Today', 'webtalize-menu'));
    }
    
    // Check if explicitly closed (must be true, '1', or 1)
    $is_closed = isset($hours[$today_key]['closed']) && 
                 ($hours[$today_key]['closed'] === true || 
                  $hours[$today_key]['closed'] === '1' || 
                  $hours[$today_key]['closed'] === 1);
    if ($is_closed) {
        return array('status' => 'closed_all_day', 'message' => __('We are Closed Today', 'webtalize-menu'));
    }
    
    $row = $hours[$today_key];
    $open_time = isset($row['open']) ? $row['open'] : '';
    $close_time = isset($row['close']) ? $row['close'] : '';
    
    if ($open_time === '' || $close_time === '') {
        return array('status' => 'closed_all_day', 'message' => __('We are Closed Today', 'webtalize-menu'));
    }
    
    // Check if we're in a break period
    $break_start = isset($row['break_start']) ? $row['break_start'] : '';
    $break_end = isset($row['break_end']) ? $row['break_end'] : '';
    if ($break_start !== '' && $break_end !== '' && $current_time >= $break_start && $current_time <= $break_end) {
        return array('status' => 'closed_after_hours', 'message' => __('We are now closed', 'webtalize-menu'));
    }
    
    // Determine status based on current time
    if ($current_time >= $open_time && $current_time <= $close_time) {
        // Check if within 30 minutes of closing
        $minutes = $minutes_until_close($close_time, $current_time);
        if ($minutes !== null && $minutes <= 30 && $minutes >= 0) {
            return array('status' => 'closing_soon', 'message' => __('We are closing soon. Call to see if we still accept takeout/delivery orders', 'webtalize-menu'));
        }
        return array('status' => 'open', 'message' => __('We are Open today', 'webtalize-menu'));
    } elseif ($current_time < $open_time) {
        $message = $get_before_hours_message($open_time, $current_time);
        return array('status' => 'not_open_yet', 'message' => $message);
    } else {
        return array('status' => 'closed_after_hours', 'message' => __('We are now closed', 'webtalize-menu'));
    }
}

function wtm_is_open_today() {
    $status = wtm_get_today_status();
    return $status['status'] === 'open' || $status['status'] === 'closing_soon';
}

function wtm_render_opening_hours($show_today_only = false, $layout = 'table', $show_specials = true) {
    $days = array(
        'monday'    => __('Monday', 'webtalize-menu'),
        'tuesday'   => __('Tuesday', 'webtalize-menu'),
        'wednesday' => __('Wednesday', 'webtalize-menu'),
        'thursday'  => __('Thursday', 'webtalize-menu'),
        'friday'    => __('Friday', 'webtalize-menu'),
        'saturday'  => __('Saturday', 'webtalize-menu'),
        'sunday'    => __('Sunday', 'webtalize-menu'),
    );
    $hours = get_option('wtm_opening_hours', function_exists('wtm_get_default_opening_hours') ? wtm_get_default_opening_hours() : array());
    $note = wp_unslash(get_option('wtm_opening_hours_note', ''));

    $output = '<div class="wtm-opening-hours">';
    
    if ($show_today_only) {
        // Today view: show open/closed status with icon
        $status_info = wtm_get_today_status();
        $status = $status_info['status'];
        $status_text = $status_info['message'];
        
        // Determine status class and icon
        $is_open = ($status === 'open');
        $is_closing_soon = ($status === 'closing_soon');
        if ($is_closing_soon) {
            $status_class = 'wtm-status-closing-soon';
            $status_icon = '⚠';
        } elseif ($is_open) {
            $status_class = 'wtm-status-open';
            $status_icon = '✓';
        } else {
            $status_class = 'wtm-status-closed';
            $status_icon = '✗';
        }
        
        $output .= '<div class="wtm-opening-hours-status ' . esc_attr($status_class) . '">';
        $output .= '<span class="wtm-status-icon">' . esc_html($status_icon) . '</span>';
        $output .= '<h4 class="wtm-opening-hours-title">' . esc_html($status_text) . '</h4>';
        $output .= '</div>';
        
        $today_key = strtolower(date_create('now', wp_timezone())->format('l'));
        if (isset($hours[$today_key])) {
            $output .= '<div class="wtm-opening-hours-today">';
            $output .= '<strong>' . esc_html__('Today', 'webtalize-menu') . ':</strong> ';
            $output .= esc_html(wtm_format_hours_row($hours[$today_key]));
            $output .= '</div>';
        }
        
        // Show phone number if set (don't show optional note for today view)
        $phone = get_option('wtm_opening_hours_phone', '');
        if (!empty($phone)) {
            $output .= '<div class="wtm-opening-hours-phone">' . esc_html($phone) . '</div>';
        }
        
        $output .= '</div>';
        return $output;
    }
    
    $output .= '<h4 class="wtm-opening-hours-title">' . esc_html__('Hours of Operation', 'webtalize-menu') . '</h4>';

    $active_specials = $show_specials ? wtm_get_active_special_hours() : array();
    if (!empty($active_specials)) {
        $output .= '<div class="wtm-opening-hours-special">';
        $output .= '<h4>' . esc_html__('Special Hours', 'webtalize-menu') . '</h4>';
        $output .= '<ul>';
        foreach ($active_specials as $special) {
            $type = isset($special['type']) ? $special['type'] : 'open';
            $label = isset($special['label']) && $special['label'] !== '' ? $special['label'] : __('Special Hours', 'webtalize-menu');
            $start = wtm_format_datetime_display(isset($special['start']) ? $special['start'] : '');
            $end = wtm_format_datetime_display(isset($special['end']) ? $special['end'] : '');

            if ($type === 'closed') {
                $output .= '<li>' . esc_html($label) . ': ' . esc_html__('Closed', 'webtalize-menu') . ' (' . esc_html($start) . ' - ' . esc_html($end) . ')</li>';
            } else {
                $row = array(
                    'closed' => false,
                    'open' => isset($special['open']) ? $special['open'] : '',
                    'close' => isset($special['close']) ? $special['close'] : '',
                    'break_start' => isset($special['break_start']) ? $special['break_start'] : '',
                    'break_end' => isset($special['break_end']) ? $special['break_end'] : '',
                );
                $output .= '<li>' . esc_html($label) . ': ' . esc_html(wtm_format_hours_row($row)) . ' (' . esc_html($start) . ' - ' . esc_html($end) . ')</li>';
            }
        }
        $output .= '</ul></div>';
    }

    if ($layout === 'compact') {
        $output .= '<ul class="wtm-opening-hours-list">';
        foreach ($days as $day_key => $label) {
            $row = isset($hours[$day_key]) ? $hours[$day_key] : array();
            $output .= '<li><span class="wtm-opening-hours-day">' . esc_html($label) . '</span> ';
            $output .= '<span class="wtm-opening-hours-time">' . esc_html(wtm_format_hours_row($row)) . '</span></li>';
        }
        $output .= '</ul>';
    } else {
        $output .= '<table class="wtm-opening-hours-table"><tbody>';
        foreach ($days as $day_key => $label) {
            $row = isset($hours[$day_key]) ? $hours[$day_key] : array();
            $output .= '<tr>';
            $output .= '<td class="wtm-opening-hours-day">' . esc_html($label) . '</td>';
            $output .= '<td class="wtm-opening-hours-time">' . esc_html(wtm_format_hours_row($row)) . '</td>';
            $output .= '</tr>';
        }
        $output .= '</tbody></table>';
    }
    if (!empty($note)) {
        $output .= '<div class="wtm-opening-hours-note">' . esc_html($note) . '</div>';
    }
    $output .= '</div>';

    return $output;
}

function wtm_shortcode_opening_hours($atts = array()) {
    $atts = shortcode_atts(
        array(
            'show' => 'week', // week|today
            'layout' => 'table', // table|compact
            'specials' => 'yes', // yes|no
        ),
        $atts,
        'wtm_opening_hours'
    );

    $show_today = strtolower($atts['show']) === 'today';
    $layout = strtolower($atts['layout']) === 'compact' ? 'compact' : 'table';
    $show_specials = strtolower($atts['specials']) !== 'no';

    return wtm_render_opening_hours($show_today, $layout, $show_specials);
}
add_shortcode('wtm_opening_hours', 'wtm_shortcode_opening_hours');

function wtm_shortcode_opening_hours_today() {
    return wtm_render_opening_hours(true);
}
add_shortcode('wtm_opening_hours_today', 'wtm_shortcode_opening_hours_today');

// Template helper for theme files
function wtm_print_opening_hours($show_today_only = false) {
    echo wtm_render_opening_hours($show_today_only);
}

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
                // Use 'large' size for better quality (typically 1024px width)
                // This ensures crisp images even on retina displays when scaled down to 210-270px
                $image_url = wp_get_attachment_image_url($image_id, 'large');
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