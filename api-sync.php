<?php
/**
 * API Sync Module for Webtalize Menu
 * Fetches menu data from GlobalFood API and syncs prices
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get API configuration
 */
function wtm_get_api_config() {
    return array(
        'api_key' => get_option('wtm_api_key', 'pwN1vU6YpfdPXYVGXr'),
        'api_endpoint' => get_option('wtm_api_endpoint', 'https://pos.globalfoodsoft.com/pos/menu'),
        'api_email' => get_option('wtm_api_email', 'API@leofus.com'),
        'sync_enabled' => get_option('wtm_sync_enabled', '0'),
        'sync_interval' => get_option('wtm_sync_interval', 'hourly'), // hourly, twicedaily, daily
        'ssl_verify' => get_option('wtm_ssl_verify', '1'), // SSL verification (1 = verify, 0 = skip)
    );
}

/**
 * Fetch menu data from API
 */
function wtm_fetch_menu_from_api() {
    $config = wtm_get_api_config();
    
    if (empty($config['api_key']) || empty($config['api_endpoint'])) {
        return new WP_Error('api_config_missing', __('API key or endpoint is not configured.', 'webtalize-menu'));
    }
    
    // Prepare request arguments according to GlobalFood API documentation
    // Endpoint: https://pos.globalfoodsoft.com/pos/menu
    // Required headers:
    //   - Authorization: {api_key} (just the key, no "Bearer" prefix)
    //   - Accept: application/json
    //   - Glf-Api-Version: 2
    $args = array(
        'timeout' => 30,
        'headers' => array(
            'Accept' => 'application/json',
            'Authorization' => $config['api_key'], // API key directly, no "Bearer" prefix
            'Glf-Api-Version' => '2', // Required API version header
            'User-Agent' => 'WebtalizeMenu/1.0.3',
        ),
    );
    
    // Handle SSL verification
    if ($config['ssl_verify'] === '0') {
        $args['sslverify'] = false;
        // Also disable SSL verification via filter as fallback
        add_filter('https_ssl_verify', '__return_false', 999);
        add_filter('https_local_ssl_verify', '__return_false', 999);
    } else {
        $args['sslverify'] = true;
    }
    
    // Use the endpoint as-is (no query string parameters needed)
    $endpoint = $config['api_endpoint'];
    
    // Make the API request
    $response = wp_remote_get($endpoint, $args);
    
    // Remove SSL filters after request
    if ($config['ssl_verify'] === '0') {
        remove_filter('https_ssl_verify', '__return_false', 999);
        remove_filter('https_local_ssl_verify', '__return_false', 999);
    }
    
    // Check for errors
    if (is_wp_error($response)) {
        $error_code = $response->get_error_code();
        $error_message = $response->get_error_message();
        
        // Provide more helpful error messages for common SSL/TLS errors
        if (strpos($error_message, 'TLS') !== false || strpos($error_message, 'SSL') !== false || strpos($error_code, '35') !== false) {
            $enhanced_message = $error_message . ' ' . __('This is usually an SSL/TLS connection issue. Try disabling SSL verification in settings (for testing only).', 'webtalize-menu');
            return new WP_Error($error_code, $enhanced_message);
        }
        
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        $response_message = wp_remote_retrieve_response_message($response);
        return new WP_Error('api_error', sprintf(__('API returned error: %d %s', 'webtalize-menu'), $response_code, $response_message));
    }
    
    $body = wp_remote_retrieve_body($response);
    
    // Check if response is XML (some APIs return XML by default)
    if (strpos(trim($body), '<?xml') === 0 || strpos(trim($body), '<root') === 0) {
        // Try to convert XML to array using simplexml
        if (function_exists('simplexml_load_string')) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            if ($xml !== false) {
                // Convert XML to array
                $data = json_decode(json_encode($xml), true);
                return $data;
            } else {
                $xml_errors = libxml_get_errors();
                libxml_clear_errors();
                return new WP_Error('xml_error', __('Failed to parse XML response. ', 'webtalize-menu') . (isset($xml_errors[0]) ? $xml_errors[0]->message : ''));
            }
        } else {
            return new WP_Error('xml_error', __('API returned XML but XML parsing is not available. Please request JSON format by setting Accept header to application/json.', 'webtalize-menu'));
        }
    }
    
    // Try JSON parsing
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If JSON parsing fails, return error with first 500 chars of response for debugging
        $body_preview = substr($body, 0, 500);
        return new WP_Error('json_error', __('Failed to parse API response as JSON. ', 'webtalize-menu') . __('Response preview: ', 'webtalize-menu') . $body_preview);
    }
    
    return $data;
}

/**
 * Find matching menu item by name or external ID
 */
function wtm_find_matching_menu_item($api_item) {
    // Try to match by external ID first (if API provides one)
    $external_id = '';
    if (isset($api_item['id'])) {
        $external_id = sanitize_text_field($api_item['id']);
    } elseif (isset($api_item['external_id'])) {
        $external_id = sanitize_text_field($api_item['external_id']);
    } elseif (isset($api_item['sku'])) {
        $external_id = sanitize_text_field($api_item['sku']);
    }
    
    if (!empty($external_id)) {
        // Search for existing menu item with this external ID
        $posts = get_posts(array(
            'post_type' => 'menu_item',
            'meta_key' => 'wtm_external_id',
            'meta_value' => $external_id,
            'posts_per_page' => 1,
            'post_status' => 'any',
        ));
        
        if (!empty($posts)) {
            return $posts[0];
        }
    }
    
    // Fallback: match by name/title
    $item_name = '';
    if (isset($api_item['name'])) {
        $item_name = sanitize_text_field($api_item['name']);
    } elseif (isset($api_item['title'])) {
        $item_name = sanitize_text_field($api_item['title']);
    }
    
    if (!empty($item_name)) {
        // Try exact match first
        $posts = get_posts(array(
            'post_type' => 'menu_item',
            'title' => $item_name,
            'posts_per_page' => 1,
            'post_status' => 'any',
        ));
        
        if (!empty($posts)) {
            return $posts[0];
        }
        
        // Try case-insensitive match
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'menu_item' AND LOWER(post_title) = LOWER(%s) LIMIT 1",
            $item_name
        ));
        
        if ($post_id) {
            return get_post($post_id);
        }
    }
    
    return null;
}

/**
 * Extract price from API item data
 */
function wtm_extract_price_from_api_item($api_item) {
    // Try different possible price field names
    $price_fields = array('price', 'base_price', 'cost', 'amount', 'value');
    
    foreach ($price_fields as $field) {
        if (isset($api_item[$field])) {
            $price = $api_item[$field];
            // Handle empty, null, or false values
            if ($price === '' || $price === null || $price === false) {
                continue;
            }
            // Remove currency symbols and clean up
            $price = preg_replace('/[^0-9.]/', '', $price);
            // Only return if it's a valid positive number
            if (is_numeric($price) && (float)$price > 0) {
                return number_format((float)$price, 2, '.', '');
            }
        }
    }
    
    return null;
}

/**
 * Sync menu items from API
 */
function wtm_sync_menu_from_api() {
    $result = array(
        'success' => false,
        'items_processed' => 0,
        'items_updated' => 0,
        'items_created' => 0,
        'errors' => array(),
    );
    
    // Fetch menu data from API
    $api_data = wtm_fetch_menu_from_api();
    
    if (is_wp_error($api_data)) {
        $result['errors'][] = $api_data->get_error_message();
        return $result;
    }
    
    // Determine the structure of the API response
    // GlobalFood API structure: root -> category[] -> item[]
    $menu_items = array();
    
    // Check for GlobalFood API structure (categories with nested items)
    if (isset($api_data['category']) && is_array($api_data['category'])) {
        // Extract items from categories
        foreach ($api_data['category'] as $category) {
            if (isset($category['item'])) {
                $items = $category['item'];
                
                // Handle both single item (object) and multiple items (array)
                if (is_array($items)) {
                    // Check if it's a numeric array (multiple items) or associative (single item)
                    if (isset($items[0])) {
                        // Multiple items (numeric array)
                        foreach ($items as $item) {
                            $menu_items[] = $item;
                        }
                    } else {
                        // Single item (associative array/object)
                        $menu_items[] = $items;
                    }
                } else {
                    // Single item (not an array - shouldn't happen with JSON, but handle it)
                    $menu_items[] = $items;
                }
            }
        }
    } elseif (isset($api_data['items']) && is_array($api_data['items'])) {
        // Fallback: flat items array
        $menu_items = $api_data['items'];
    } elseif (isset($api_data['menu']) && is_array($api_data['menu'])) {
        // Fallback: menu array
        $menu_items = $api_data['menu'];
    } elseif (isset($api_data['data']) && is_array($api_data['data'])) {
        // Fallback: data array
        $menu_items = $api_data['data'];
    } elseif (is_array($api_data) && isset($api_data[0]) && isset($api_data[0]['id'])) {
        // Fallback: direct array of items
        $menu_items = $api_data;
    } else {
        $result['errors'][] = __('API response format not recognized. Expected category array with nested items, or items/menu/data array.', 'webtalize-menu');
        // Debug: log the structure we received
        $result['errors'][] = __('Response keys: ', 'webtalize-menu') . implode(', ', array_keys($api_data));
        return $result;
    }
    
    if (empty($menu_items)) {
        $result['errors'][] = __('No menu items found in API response.', 'webtalize-menu');
        return $result;
    }
    
    // Process each menu item
    foreach ($menu_items as $api_item) {
        $result['items_processed']++;
        
        try {
            // Find matching menu item
            $existing_post = wtm_find_matching_menu_item($api_item);
            
            // Extract price
            $api_price = wtm_extract_price_from_api_item($api_item);
            
            if ($existing_post) {
                // Update existing item
                $current_price = get_post_meta($existing_post->ID, 'wtm_price', true);
                
                // Compare prices (with small tolerance for floating point)
                // IMPORTANT: Only update price if API has a valid price value
                // Never delete or overwrite existing price with empty/null value
                $price_changed = false;
                if ($api_price !== null && $api_price !== '' && is_numeric($api_price) && (float)$api_price > 0) {
                    $current_price_float = !empty($current_price) && is_numeric($current_price) ? (float)$current_price : 0;
                    $api_price_float = (float)$api_price;
                    
                    if (abs($current_price_float - $api_price_float) > 0.01) {
                        $price_changed = true;
                        update_post_meta($existing_post->ID, 'wtm_price', $api_price);
                    }
                }
                // If API price is null/empty, keep existing price - don't delete it!
                
                // Update external ID (always sync to keep it current)
                $external_id = '';
                if (isset($api_item['id'])) {
                    $external_id = sanitize_text_field($api_item['id']);
                } elseif (isset($api_item['external_id'])) {
                    $external_id = sanitize_text_field($api_item['external_id']);
                }
                
                if (!empty($external_id)) {
                    update_post_meta($existing_post->ID, 'wtm_external_id', $external_id);
                }
                
                // Update description if provided
                if (isset($api_item['description']) && !empty($api_item['description'])) {
                    $description = sanitize_textarea_field($api_item['description']);
                    update_post_meta($existing_post->ID, 'wtm_description', $description);
                }
                
                // Update SKU if provided (check both extras.sku and direct sku field)
                $sku = '';
                if (isset($api_item['extras']['sku']) && !empty($api_item['extras']['sku'])) {
                    $sku = sanitize_text_field($api_item['extras']['sku']);
                } elseif (isset($api_item['sku']) && !empty($api_item['sku'])) {
                    $sku = sanitize_text_field($api_item['sku']);
                }
                if (!empty($sku)) {
                    update_post_meta($existing_post->ID, 'wtm_sku', $sku);
                }
                
                // Update sort order if provided
                if (isset($api_item['sort']) && is_numeric($api_item['sort'])) {
                    // WordPress uses menu_order for sorting
                    wp_update_post(array(
                        'ID' => $existing_post->ID,
                        'menu_order' => intval($api_item['sort']),
                    ));
                }
                
                // Update active status if provided
                if (isset($api_item['active'])) {
                    $is_active = ($api_item['active'] === true || $api_item['active'] === 'true' || $api_item['active'] === 1);
                    update_post_meta($existing_post->ID, 'wtm_active', $is_active ? '1' : '0');
                    
                    // If item is inactive, we might want to set post status to draft
                    // Uncomment if you want inactive items to be hidden
                    // if (!$is_active && $existing_post->post_status === 'publish') {
                    //     wp_update_post(array('ID' => $existing_post->ID, 'post_status' => 'draft'));
                    // } elseif ($is_active && $existing_post->post_status === 'draft') {
                    //     wp_update_post(array('ID' => $existing_post->ID, 'post_status' => 'publish'));
                    // }
                }
                
                // Store active date ranges if provided
                if (isset($api_item['active_begin']) && !empty($api_item['active_begin'])) {
                    update_post_meta($existing_post->ID, 'wtm_active_begin', sanitize_text_field($api_item['active_begin']));
                }
                if (isset($api_item['active_end']) && !empty($api_item['active_end'])) {
                    update_post_meta($existing_post->ID, 'wtm_active_end', sanitize_text_field($api_item['active_end']));
                }
                if (isset($api_item['active_days'])) {
                    update_post_meta($existing_post->ID, 'wtm_active_days', sanitize_text_field($api_item['active_days']));
                }
                
                // Store sizes/variants if provided (as JSON for later use)
                if (isset($api_item['sizes']) && is_array($api_item['sizes']) && !empty($api_item['sizes'])) {
                    update_post_meta($existing_post->ID, 'wtm_sizes', json_encode($api_item['sizes']));
                }
                
                // Store allergens if provided
                if (isset($api_item['extras']) && is_array($api_item['extras'])) {
                    if (isset($api_item['extras']['menu_item_allergens_value']) && is_array($api_item['extras']['menu_item_allergens_value'])) {
                        $allergens = array();
                        foreach ($api_item['extras']['menu_item_allergens_value'] as $allergen) {
                            if (is_array($allergen) && isset($allergen['name'])) {
                                $allergens[] = sanitize_text_field($allergen['name']);
                            } elseif (is_string($allergen)) {
                                $allergens[] = sanitize_text_field($allergen);
                            }
                        }
                        if (!empty($allergens)) {
                            update_post_meta($existing_post->ID, 'wtm_allergens', $allergens);
                        }
                    }
                }
                
                // Store nutritional values if provided
                if (isset($api_item['sizes']) && is_array($api_item['sizes'])) {
                    // Get nutritional values from default size or first size
                    foreach ($api_item['sizes'] as $size) {
                        if (isset($size['default']) && $size['default'] === true && isset($size['extras']['menu_item_nutritional_value'])) {
                            update_post_meta($existing_post->ID, 'wtm_nutritional_values', json_encode($size['extras']['menu_item_nutritional_value']));
                            break;
                        }
                    }
                    // If no default size, use first size
                    if (empty(get_post_meta($existing_post->ID, 'wtm_nutritional_values', true)) && isset($api_item['sizes'][0]['extras']['menu_item_nutritional_value'])) {
                        update_post_meta($existing_post->ID, 'wtm_nutritional_values', json_encode($api_item['sizes'][0]['extras']['menu_item_nutritional_value']));
                    }
                }
                
                // Store kitchen internal name if provided
                if (isset($api_item['extras']) && is_array($api_item['extras'])) {
                    if (isset($api_item['extras']['menu_item_kitchen_internal_name']) && !empty($api_item['extras']['menu_item_kitchen_internal_name'])) {
                        update_post_meta($existing_post->ID, 'wtm_kitchen_internal_name', sanitize_text_field($api_item['extras']['menu_item_kitchen_internal_name']));
                    }
                }
                
                // Sync category if provided
                if (isset($api_item['menu_category_id'])) {
                    // Find or create category by external ID
                    // Note: This requires category sync logic which we'll add if needed
                    // For now, we'll store the category ID
                    update_post_meta($existing_post->ID, 'wtm_category_id', intval($api_item['menu_category_id']));
                }
                
                if ($price_changed) {
                    $result['items_updated']++;
                }
            } else {
                // Create new menu item (optional - you may want to disable this)
                // For now, we'll skip creating new items and only update existing ones
                // Uncomment below if you want to create new items from API
                /*
                $item_name = '';
                if (isset($api_item['name'])) {
                    $item_name = sanitize_text_field($api_item['name']);
                } elseif (isset($api_item['title'])) {
                    $item_name = sanitize_text_field($api_item['title']);
                }
                
                if (!empty($item_name)) {
                    $new_post = array(
                        'post_title' => $item_name,
                        'post_type' => 'menu_item',
                        'post_status' => 'publish',
                    );
                    
                    $post_id = wp_insert_post($new_post);
                    
                    if ($post_id && !is_wp_error($post_id)) {
                        if ($api_price !== null) {
                            update_post_meta($post_id, 'wtm_price', $api_price);
                        }
                        
                        if (isset($api_item['description'])) {
                            update_post_meta($post_id, 'wtm_description', sanitize_textarea_field($api_item['description']));
                        }
                        
                        // Store external ID
                        $external_id = '';
                        if (isset($api_item['id'])) {
                            $external_id = sanitize_text_field($api_item['id']);
                        } elseif (isset($api_item['external_id'])) {
                            $external_id = sanitize_text_field($api_item['external_id']);
                        } elseif (isset($api_item['sku'])) {
                            $external_id = sanitize_text_field($api_item['sku']);
                        }
                        
                        if (!empty($external_id)) {
                            update_post_meta($post_id, 'wtm_external_id', $external_id);
                        }
                        
                        $result['items_created']++;
                    }
                }
                */
            }
        } catch (Exception $e) {
            $result['errors'][] = sprintf(__('Error processing item: %s', 'webtalize-menu'), $e->getMessage());
        }
    }
    
    // Update last sync timestamp
    update_option('wtm_last_sync_time', current_time('mysql'));
    update_option('wtm_last_sync_result', $result);
    
    $result['success'] = true;
    return $result;
}

/**
 * Register external_id meta field
 */
function wtm_register_external_id_meta() {
    register_post_meta('menu_item', 'wtm_external_id', array(
        'type' => 'string',
        'description' => 'External ID from API for matching menu items',
        'single' => true,
        'show_in_rest' => false,
    ));
}
add_action('init', 'wtm_register_external_id_meta');

/**
 * Schedule automatic sync
 */
function wtm_schedule_api_sync() {
    $config = wtm_get_api_config();
    
    if ($config['sync_enabled'] === '1') {
        if (!wp_next_scheduled('wtm_api_sync_event')) {
            wp_schedule_event(time(), $config['sync_interval'], 'wtm_api_sync_event');
        }
    } else {
        // Clear scheduled event if sync is disabled
        $timestamp = wp_next_scheduled('wtm_api_sync_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wtm_api_sync_event');
        }
    }
}
add_action('wp', 'wtm_schedule_api_sync');

/**
 * Hook into scheduled sync event
 */
add_action('wtm_api_sync_event', 'wtm_sync_menu_from_api');

/**
 * AJAX handler for test connection
 */
function wtm_ajax_test_connection() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'webtalize-menu')));
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wtm_api_sync_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'webtalize-menu')));
    }
    
    // Test API connection
    $api_data = wtm_fetch_menu_from_api();
    
    if (is_wp_error($api_data)) {
        $error_message = $api_data->get_error_message();
        $error_code = $api_data->get_error_code();
        
        // Provide helpful suggestions for common errors
        $suggestion = '';
        if (strpos($error_message, 'TLS') !== false || strpos($error_message, 'SSL') !== false || strpos($error_code, '35') !== false) {
            $suggestion = ' ' . __('Try disabling SSL verification in settings (for testing only).', 'webtalize-menu');
        } elseif (strpos($error_message, '401') !== false || strpos($error_message, '403') !== false) {
            $suggestion = ' ' . __('Check if your API key is correct and still valid.', 'webtalize-menu');
        } elseif (strpos($error_message, '404') !== false) {
            $suggestion = ' ' . __('Check if the API endpoint URL is correct.', 'webtalize-menu');
        }
        
        wp_send_json_error(array(
            'message' => __('Connection failed:', 'webtalize-menu') . ' ' . $error_message . $suggestion,
        ));
    }
    
    // Success - provide info about the response
    $item_count = 0;
    $category_count = 0;
    $response_structure = array();
    
    // Debug: Get top-level keys
    $top_level_keys = is_array($api_data) ? array_keys($api_data) : array();
    $response_structure['top_level_keys'] = $top_level_keys;
    
    // Check for GlobalFood API structure (categories with nested items)
    if (isset($api_data['category']) && is_array($api_data['category'])) {
        $category_count = count($api_data['category']);
        $response_structure['has_categories'] = true;
        $response_structure['category_count'] = $category_count;
        
        foreach ($api_data['category'] as $idx => $category) {
            if (isset($category['item'])) {
                $items = $category['item'];
                if (is_array($items)) {
                    if (isset($items[0])) {
                        $item_count += count($items);
                        $response_structure['categories'][$idx] = array(
                            'name' => isset($category['name']) ? $category['name'] : 'Unknown',
                            'item_count' => count($items),
                        );
                    } else {
                        $item_count += 1; // Single item (associative array)
                        $response_structure['categories'][$idx] = array(
                            'name' => isset($category['name']) ? $category['name'] : 'Unknown',
                            'item_count' => 1,
                        );
                    }
                } else {
                    $item_count += 1; // Single item (not array)
                    $response_structure['categories'][$idx] = array(
                        'name' => isset($category['name']) ? $category['name'] : 'Unknown',
                        'item_count' => 1,
                    );
                }
            } else {
                $response_structure['categories'][$idx] = array(
                    'name' => isset($category['name']) ? $category['name'] : 'Unknown',
                    'item_count' => 0,
                    'note' => 'No items found in this category',
                );
            }
        }
    } elseif (isset($api_data['items']) && is_array($api_data['items'])) {
        $item_count = count($api_data['items']);
        $response_structure['has_items_array'] = true;
    } elseif (isset($api_data['menu']) && is_array($api_data['menu'])) {
        $item_count = count($api_data['menu']);
        $response_structure['has_menu_array'] = true;
    } elseif (isset($api_data['data']) && is_array($api_data['data'])) {
        $item_count = count($api_data['data']);
        $response_structure['has_data_array'] = true;
    } elseif (is_array($api_data) && isset($api_data[0])) {
        $item_count = count($api_data);
        $response_structure['is_direct_array'] = true;
    } else {
        $response_structure['unrecognized'] = true;
        // Try to find any array that might contain items
        foreach ($api_data as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $response_structure['potential_arrays'][] = $key;
            }
        }
    }
    
    $message = __('Connection successful!', 'webtalize-menu');
    if ($item_count > 0) {
        if ($category_count > 0) {
            $message .= ' ' . sprintf(__('Found %d menu item(s) in %d categor(ies).', 'webtalize-menu'), $item_count, $category_count);
        } else {
            $message .= ' ' . sprintf(__('Found %d menu item(s) in API response.', 'webtalize-menu'), $item_count);
        }
    } else {
        $message .= ' ' . __('API responded but no menu items found.', 'webtalize-menu');
        $message .= ' ' . __('Response structure: ', 'webtalize-menu') . implode(', ', $top_level_keys);
        if (!empty($response_structure['potential_arrays'])) {
            $message .= ' ' . __('Potential item arrays: ', 'webtalize-menu') . implode(', ', $response_structure['potential_arrays']);
        }
    }
    
    // Include sample of first category/item for debugging (limited to avoid huge responses)
    if (!empty($api_data['category']) && is_array($api_data['category'])) {
        $first_category = $api_data['category'][0];
        $response_structure['sample_category_keys'] = array_keys($first_category);
        if (isset($first_category['item'])) {
            $first_item = is_array($first_category['item']) && isset($first_category['item'][0]) 
                ? $first_category['item'][0] 
                : $first_category['item'];
            if (is_array($first_item)) {
                $response_structure['sample_item_keys'] = array_keys($first_item);
            }
        }
    }
    
    wp_send_json_success(array(
        'message' => $message,
        'item_count' => $item_count,
        'category_count' => $category_count,
        'response_structure' => $response_structure,
        'top_level_keys' => $top_level_keys,
    ));
}
add_action('wp_ajax_wtm_test_connection', 'wtm_ajax_test_connection');

/**
 * AJAX handler for manual sync
 */
function wtm_ajax_manual_sync() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'webtalize-menu')));
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wtm_api_sync_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'webtalize-menu')));
    }
    
    // Perform sync
    $result = wtm_sync_menu_from_api();
    
    if ($result['success']) {
        $message = sprintf(
            __('Sync completed. Processed: %d items, Updated: %d items, Created: %d items.', 'webtalize-menu'),
            $result['items_processed'],
            $result['items_updated'],
            $result['items_created']
        );
        
        if (!empty($result['errors'])) {
            $message .= ' ' . __('Errors:', 'webtalize-menu') . ' ' . implode(', ', $result['errors']);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'result' => $result,
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Sync failed.', 'webtalize-menu') . ' ' . implode(', ', $result['errors']),
            'result' => $result,
        ));
    }
}
add_action('wp_ajax_wtm_manual_sync', 'wtm_ajax_manual_sync');

