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
 * Auto-detect dietary labels from item description
 * Only returns labels if they can be clearly determined from the description
 * 
 * @param string $description The item description text
 * @return array Array of detected dietary label keys (empty if nothing detected)
 */
function wtm_auto_detect_dietary_labels($description) {
    if (empty($description)) {
        return array();
    }
    
    // Normalize description: lowercase, remove extra spaces
    $text = strtolower(trim($description));
    $text = preg_replace('/\s+/', ' ', $text);
    
    $detected_labels = array();
    
    // Vegan detection - look for explicit vegan mentions
    if (preg_match('/\b(vegan|plant.?based|no.?animal|no.?dairy|no.?meat|no.?eggs)\b/i', $text)) {
        // Make sure it's not "can be vegan" or "not vegan"
        if (!preg_match('/\b(can.?be|may.?be|not|non.?|without)\s+(vegan|plant.?based)\b/i', $text)) {
            $detected_labels[] = 'vegan';
        }
    }
    
    // Vegetarian detection - look for explicit vegetarian mentions
    if (preg_match('/\b(vegetarian|veggie|no.?meat|no.?fish|no.?seafood|no.?chicken|no.?beef|no.?pork)\b/i', $text)) {
        // Make sure it's not "can be vegetarian" or "not vegetarian"
        if (!preg_match('/\b(can.?be|may.?be|not|non.?|without)\s+(vegetarian|veggie)\b/i', $text)) {
            // Check if it's vegan (vegan is more specific)
            if (!in_array('vegan', $detected_labels)) {
                $detected_labels[] = 'vegetarian';
            }
        }
    }
    
    // "Can be vegetarian" detection
    if (preg_match('/\b(can.?be|may.?be|available.?as|option.?to.?make)\s+(vegetarian|veggie)\b/i', $text)) {
        $detected_labels[] = 'can_be_vegetarian';
    }
    
    // Gluten-free detection
    if (preg_match('/\b(gluten.?free|gf|no.?gluten|without.?gluten)\b/i', $text)) {
        // Make sure it's not "can be gluten free" or "not gluten free"
        if (!preg_match('/\b(can.?be|may.?be|not|non.?|without)\s+(gluten.?free|gf)\b/i', $text)) {
            $detected_labels[] = 'gluten_free';
        }
    }
    
    // "Can be gluten free" detection
    if (preg_match('/\b(can.?be|may.?be|available.?as|option.?to.?make)\s+(gluten.?free|gf)\b/i', $text)) {
        $detected_labels[] = 'can_be_gluten_free';
    }
    
    // Spicy detection - look for spicy indicators
    // Very Very Spicy (spicy_3)
    if (preg_match('/\b(extremely|extraordinarily|insanely|deathly|dangerously|very.?very|super.?hot|extremely.?hot|extra.?hot|ultra.?hot|spiciest|hottest)\s+(spicy|hot)\b/i', $text) ||
        preg_match('/\b(spicy|hot)\s+(extremely|extraordinarily|insanely|deathly|dangerously|very.?very|super|extra|ultra)\b/i', $text) ||
        preg_match('/\b(5|five)\s*(out.?of|/)\s*(5|five)\s*(spicy|hot|heat)\b/i', $text) ||
        preg_match('/\b(ghost.?pepper|carolina.?reaper|scorpion.?pepper|habanero|scotch.?bonnet)\b/i', $text)) {
        $detected_labels[] = 'spicy_3';
    }
    // Very Spicy (spicy_2)
    elseif (preg_match('/\b(very|really|quite|pretty|super|extra|ultra)\s+(spicy|hot)\b/i', $text) ||
            preg_match('/\b(spicy|hot)\s+(very|really|quite|pretty|super|extra|ultra)\b/i', $text) ||
            preg_match('/\b(4|four)\s*(out.?of|/)\s*(5|five)\s*(spicy|hot|heat)\b/i', $text) ||
            preg_match('/\b(jalapeño|serrano|thai.?chili|red.?chili)\b/i', $text)) {
        $detected_labels[] = 'spicy_2';
    }
    // Spicy (spicy_1)
    elseif (preg_match('/\b(spicy|hot|heat|chili|chilli|pepper|peppery|fiery|piquant)\b/i', $text)) {
        // Make sure it's not "not spicy" or "mild"
        if (!preg_match('/\b(not|non.?|without|mild|no.?spice|no.?heat)\s+(spicy|hot)\b/i', $text)) {
            $detected_labels[] = 'spicy_1';
        }
    }
    
    // Peanuts detection
    if (preg_match('/\b(peanut|peanuts|contains.?peanut|may.?contain.?peanut|peanut.?allergen)\b/i', $text)) {
        $detected_labels[] = 'contains_peanuts';
    }
    
    // Remove duplicates and return
    return array_unique($detected_labels);
}

/**
 * Extract item number from name (e.g., "1. Item" -> "1", "5a. Item" -> "5a")
 */
function wtm_extract_item_number($item_name) {
    if (empty($item_name)) {
        return '';
    }
    // Match pattern: number(s) optionally followed by letter(s), then a dot and space
    // Examples: "1. ", "2. ", "5a. ", "10b. ", "123. "
    if (preg_match('/^(\d+[a-zA-Z]*)\s*\.\s*/', $item_name, $matches)) {
        return $matches[1]; // Return the item number (e.g., "1", "5a", "10b")
    }
    return '';
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
    
    // Try to match by item number (the prefix number like "1", "2", "5a", "5b")
    $item_name = '';
    if (isset($api_item['name'])) {
        $item_name = sanitize_text_field($api_item['name']);
    } elseif (isset($api_item['title'])) {
        $item_name = sanitize_text_field($api_item['title']);
    }
    
    if (!empty($item_name)) {
        $item_number = wtm_extract_item_number($item_name);
        
        if (!empty($item_number)) {
            // Search for items that start with this item number
            // Pattern: item_number followed by optional space and dot (e.g., "1. ", "5a. ", "10b. ")
            global $wpdb;
            $escaped_number = $wpdb->esc_like($item_number);
            // Use LIKE for better compatibility (item_number followed by . or space)
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'menu_item' AND (post_title LIKE %s OR post_title LIKE %s) LIMIT 1",
                $escaped_number . '. %',
                $escaped_number . ' %'
            ));
            
            if ($post_id) {
                $matched_post = get_post($post_id);
                // Check if names are different (item number matches but name changed)
                $wp_title = $matched_post->post_title;
                if (strcasecmp(trim($wp_title), trim($item_name)) !== 0) {
                    // Item number matches but name differs - return special marker
                    $matched_post->wtm_name_mismatch = true;
                    $matched_post->wtm_api_name = $item_name;
                    $matched_post->wtm_wp_name = $wp_title;
                }
                return $matched_post;
            }
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
        // Clean the name - remove common prefixes and suffixes
        // API names often have: "1. Item Name (description)" or "Item Name (2)" etc.
        $clean_name = $item_name;
        
        // Remove leading numbers and dots (e.g., "1. ", "2. ", "10. ")
        $clean_name = preg_replace('/^\d+\.\s*/', '', $clean_name);
        
        // Remove parenthetical descriptions at the end (e.g., " (2)", " (8-10)", " (description)")
        $clean_name = preg_replace('/\s*\([^)]*\)\s*$/', '', $clean_name);
        
        // Trim whitespace
        $clean_name = trim($clean_name);
        
        // Try exact match first (with original name)
        $posts = get_posts(array(
            'post_type' => 'menu_item',
            'title' => $item_name,
            'posts_per_page' => 1,
            'post_status' => 'any',
        ));
        
        if (!empty($posts)) {
            return $posts[0];
        }
        
        // Try exact match with cleaned name
        if ($clean_name !== $item_name && !empty($clean_name)) {
            $posts = get_posts(array(
                'post_type' => 'menu_item',
                'title' => $clean_name,
                'posts_per_page' => 1,
                'post_status' => 'any',
            ));
            
            if (!empty($posts)) {
                return $posts[0];
            }
        }
        
        // Try case-insensitive match with original name
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'menu_item' AND LOWER(post_title) = LOWER(%s) LIMIT 1",
            $item_name
        ));
        
        if ($post_id) {
            return get_post($post_id);
        }
        
        // Try case-insensitive match with cleaned name
        if ($clean_name !== $item_name && !empty($clean_name)) {
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'menu_item' AND LOWER(post_title) = LOWER(%s) LIMIT 1",
                $clean_name
            ));
            
            if ($post_id) {
                return get_post($post_id);
            }
        }
        
        // Try partial match - check if WordPress title contains the cleaned name or vice versa
        if (!empty($clean_name)) {
            // Find items where WordPress title contains the cleaned API name
            $post_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'menu_item' AND (LOWER(post_title) LIKE LOWER(%s) OR LOWER(%s) LIKE CONCAT('%%', LOWER(post_title), '%%')) LIMIT 1",
                '%' . $wpdb->esc_like($clean_name) . '%',
                $clean_name
            ));
            
            if ($post_id) {
                return get_post($post_id);
            }
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
        'categories_created' => 0,
        'errors' => array(),
        'api_response_sample' => '', // Store API response sample for debugging
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
    $category_map = array(); // Map API category IDs to WordPress term IDs
    
    // Check for GlobalFood API structure - try both 'category' and 'categories' (plural)
    $categories = null;
    if (isset($api_data['categories']) && is_array($api_data['categories'])) {
        $categories = $api_data['categories'];
    } elseif (isset($api_data['category']) && is_array($api_data['category'])) {
        $categories = $api_data['category'];
    }
    
    if ($categories !== null) {
        // First pass: Create/update categories
        foreach ($categories as $category) {
            $category_name = '';
            $category_id = null;
            
            if (isset($category['name'])) {
                $category_name = sanitize_text_field($category['name']);
            }
            
            if (isset($category['id'])) {
                $category_id = intval($category['id']);
            }
            
            if (!empty($category_name)) {
                // Find or create WordPress category term
                $term = get_term_by('name', $category_name, 'menu_category');
                
                if (!$term) {
                    // Category doesn't exist, create it
                    $term_result = wp_insert_term($category_name, 'menu_category');
                    if (!is_wp_error($term_result)) {
                        $term_id = $term_result['term_id'];
                        // Store API category ID as meta for reference
                        if ($category_id) {
                            update_term_meta($term_id, 'wtm_api_category_id', $category_id);
                        }
                        $category_map[$category_id] = $term_id;
                        $result['categories_created']++;
                    }
                } else {
                    // Category exists, update meta if needed
                    $term_id = $term->term_id;
                    if ($category_id) {
                        update_term_meta($term_id, 'wtm_api_category_id', $category_id);
                    }
                    $category_map[$category_id] = $term_id;
                }
            }
        }
        
        // Second pass: Extract items from categories and attach category info
        foreach ($categories as $category) {
            $category_name = '';
            $category_id = null;
            
            if (isset($category['name'])) {
                $category_name = sanitize_text_field($category['name']);
            }
            
            if (isset($category['id'])) {
                $category_id = intval($category['id']);
            }
            
            // Try different possible field names for items
            $items = null;
            if (isset($category['item'])) {
                $items = $category['item'];
            } elseif (isset($category['items']) && is_array($category['items'])) {
                $items = $category['items'];
            } elseif (isset($category['menu_items']) && is_array($category['menu_items'])) {
                $items = $category['menu_items'];
            }
            
            if ($items !== null) {
                // Handle both single item (object) and multiple items (array)
                if (is_array($items)) {
                    // Check if it's a numeric array (multiple items) or associative (single item)
                    if (isset($items[0]) || (count($items) > 0 && array_keys($items) !== range(0, count($items) - 1))) {
                        // Multiple items (numeric array) or array with numeric keys
                        foreach ($items as $item) {
                            if (is_array($item) && !empty($item)) {
                                // Attach category info to item
                                if ($category_id) {
                                    $item['_api_category_id'] = $category_id;
                                }
                                if (!empty($category_name)) {
                                    $item['_api_category_name'] = $category_name;
                                }
                                // Also ensure menu_category_id is set if not already
                                if (!isset($item['menu_category_id']) && $category_id) {
                                    $item['menu_category_id'] = $category_id;
                                }
                                $menu_items[] = $item;
                            }
                        }
                    } else {
                        // Single item (associative array/object)
                        if ($category_id) {
                            $items['_api_category_id'] = $category_id;
                        }
                        if (!empty($category_name)) {
                            $items['_api_category_name'] = $category_name;
                        }
                        if (!isset($items['menu_category_id']) && $category_id) {
                            $items['menu_category_id'] = $category_id;
                        }
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
            // Debug: Log first item structure for troubleshooting
            if ($result['items_processed'] === 1) {
                $result['debug']['first_item_keys'] = is_array($api_item) ? array_keys($api_item) : array();
                $result['debug']['first_item_sample'] = array();
                foreach (array('id', 'name', 'title', 'price', 'description', 'menu_category_id') as $key) {
                    if (isset($api_item[$key])) {
                        $result['debug']['first_item_sample'][$key] = $api_item[$key];
                    }
                }
            }
            
            // Find matching menu item
            $existing_post = wtm_find_matching_menu_item($api_item);
            
            // Extract price
            $api_price = wtm_extract_price_from_api_item($api_item);
            
            // Get item name for mismatch detection
            $item_name = '';
            if (isset($api_item['name'])) {
                $item_name = sanitize_text_field($api_item['name']);
            } elseif (isset($api_item['title'])) {
                $item_name = sanitize_text_field($api_item['title']);
            }
            
            // Check if this is a name mismatch (item number matches but name differs)
            if ($existing_post && isset($existing_post->wtm_name_mismatch) && $existing_post->wtm_name_mismatch) {
                // Store for user review instead of auto-updating
                $item_number = wtm_extract_item_number($item_name);
                $result['name_mismatches'][] = array(
                    'post_id' => $existing_post->ID,
                    'item_number' => $item_number,
                    'wp_name' => $existing_post->wtm_wp_name,
                    'api_name' => $existing_post->wtm_api_name,
                    'api_item' => $api_item, // Store full API item data for later update
                    'api_price' => $api_price,
                );
                // Skip auto-update for this item - user will review and approve
                continue;
            }
            
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
                    
                    // Update if prices differ OR if current price is empty (first time setting price)
                    if (abs($current_price_float - $api_price_float) > 0.01 || empty($current_price)) {
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
                    
                    // Auto-detect dietary labels from description
                    // Only update if we can detect labels AND no labels are currently set
                    $current_labels = get_post_meta($existing_post->ID, 'wtm_dietary_labels', true);
                    if (empty($current_labels) || !is_array($current_labels)) {
                        $detected_labels = wtm_auto_detect_dietary_labels($description);
                        if (!empty($detected_labels)) {
                            update_post_meta($existing_post->ID, 'wtm_dietary_labels', $detected_labels);
                        }
                    }
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
                $category_term_id = null;
                if (isset($api_item['menu_category_id'])) {
                    $api_category_id = intval($api_item['menu_category_id']);
                    update_post_meta($existing_post->ID, 'wtm_category_id', $api_category_id);
                    
                    // Find WordPress category term by API category ID
                    if (isset($category_map[$api_category_id])) {
                        $category_term_id = $category_map[$api_category_id];
                    } else {
                        // Try to find by API category ID stored in term meta
                        $terms = get_terms(array(
                            'taxonomy' => 'menu_category',
                            'hide_empty' => false,
                            'meta_query' => array(
                                array(
                                    'key' => 'wtm_api_category_id',
                                    'value' => $api_category_id,
                                    'compare' => '='
                                )
                            )
                        ));
                        if (!empty($terms) && !is_wp_error($terms)) {
                            $category_term_id = $terms[0]->term_id;
                            $category_map[$api_category_id] = $category_term_id;
                        }
                    }
                }
                
                // Also check for category name attached during extraction
                if ($category_term_id === null && isset($api_item['_api_category_name']) && !empty($api_item['_api_category_name'])) {
                    $term = get_term_by('name', $api_item['_api_category_name'], 'menu_category');
                    if ($term && !is_wp_error($term)) {
                        $category_term_id = $term->term_id;
                    }
                }
                
                // Assign item to category
                if ($category_term_id) {
                    wp_set_post_terms($existing_post->ID, array($category_term_id), 'menu_category', false);
                    // Store category name as meta for easy reference
                    $category_term = get_term($category_term_id, 'menu_category');
                    if ($category_term && !is_wp_error($category_term)) {
                        update_post_meta($existing_post->ID, 'wtm_category_name', $category_term->name);
                    }
                }
                
                // Count as updated if price changed OR if other fields were updated
                // (We update external_id, description, etc. even if price doesn't change)
                if ($price_changed) {
                    $result['items_updated']++;
                } else {
                    // Item was matched and processed, but price didn't change
                    // Still count it as processed (for debugging)
                    if (!isset($result['debug']['matched_no_price_change'])) {
                        $result['debug']['matched_no_price_change'] = 0;
                    }
                    $result['debug']['matched_no_price_change']++;
                }
            } else {
                // Item not found - track for debugging
                if (!isset($result['debug']['unmatched_items'])) {
                    $result['debug']['unmatched_items'] = 0;
                }
                $result['debug']['unmatched_items']++;
                
                // Get item name for debugging
                $item_name = '';
                if (isset($api_item['name'])) {
                    $item_name = sanitize_text_field($api_item['name']);
                } elseif (isset($api_item['title'])) {
                    $item_name = sanitize_text_field($api_item['title']);
                }
                if (!isset($result['debug']['unmatched_sample'])) {
                    $result['debug']['unmatched_sample'] = array();
                }
                if (count($result['debug']['unmatched_sample']) < 3 && !empty($item_name)) {
                    $result['debug']['unmatched_sample'][] = $item_name;
                }
                
                // Create new menu item from API
                // IMPORTANT: Keep the FULL original name including numbers - they are part of the menu!
                $item_name = '';
                if (isset($api_item['name'])) {
                    $item_name = sanitize_text_field($api_item['name']);
                } elseif (isset($api_item['title'])) {
                    $item_name = sanitize_text_field($api_item['title']);
                }
                
                // Use the FULL original name - don't remove numbers or descriptions!
                // The numbers are important for the business (menu item numbers)
                if (!empty($item_name)) {
                    $new_post = array(
                        'post_title' => $item_name, // Use FULL original name with numbers
                        'post_type' => 'menu_item',
                        'post_status' => 'publish',
                    );
                    
                    $post_id = wp_insert_post($new_post);
                    
                    if ($post_id && !is_wp_error($post_id)) {
                        // Set price if available
                        if ($api_price !== null && $api_price !== '' && is_numeric($api_price) && (float)$api_price > 0) {
                            update_post_meta($post_id, 'wtm_price', $api_price);
                        }
                        
                        // Set description if available
                        if (isset($api_item['description']) && !empty($api_item['description'])) {
                            $description = sanitize_textarea_field($api_item['description']);
                            update_post_meta($post_id, 'wtm_description', $description);
                            
                            // Auto-detect dietary labels from description
                            $detected_labels = wtm_auto_detect_dietary_labels($description);
                            if (!empty($detected_labels)) {
                                update_post_meta($post_id, 'wtm_dietary_labels', $detected_labels);
                            }
                        }
                        
                        // Store external ID
                        $external_id = '';
                        if (isset($api_item['id'])) {
                            $external_id = sanitize_text_field($api_item['id']);
                        } elseif (isset($api_item['external_id'])) {
                            $external_id = sanitize_text_field($api_item['external_id']);
                        }
                        
                        if (!empty($external_id)) {
                            update_post_meta($post_id, 'wtm_external_id', $external_id);
                        }
                        
                        // Assign to category if menu_category_id is provided
                        $category_term_id = null;
                        if (isset($api_item['menu_category_id'])) {
                            $api_category_id = intval($api_item['menu_category_id']);
                            update_post_meta($post_id, 'wtm_category_id', $api_category_id);
                            
                            // Find WordPress category term by API category ID
                            if (isset($category_map[$api_category_id])) {
                                $category_term_id = $category_map[$api_category_id];
                            } else {
                                // Try to find by API category ID stored in term meta
                                $terms = get_terms(array(
                                    'taxonomy' => 'menu_category',
                                    'hide_empty' => false,
                                    'meta_query' => array(
                                        array(
                                            'key' => 'wtm_api_category_id',
                                            'value' => $api_category_id,
                                            'compare' => '='
                                        )
                                    )
                                ));
                                if (!empty($terms) && !is_wp_error($terms)) {
                                    $category_term_id = $terms[0]->term_id;
                                    $category_map[$api_category_id] = $category_term_id;
                                }
                            }
                        }
                        
                        // Also check for category name attached during extraction
                        if ($category_term_id === null && isset($api_item['_api_category_name']) && !empty($api_item['_api_category_name'])) {
                            $term = get_term_by('name', $api_item['_api_category_name'], 'menu_category');
                            if ($term && !is_wp_error($term)) {
                                $category_term_id = $term->term_id;
                            }
                        }
                        
                        // Assign item to category
                        if ($category_term_id) {
                            wp_set_post_terms($post_id, array($category_term_id), 'menu_category', false);
                            // Store category name as meta for easy reference
                            $category_term = get_term($category_term_id, 'menu_category');
                            if ($category_term && !is_wp_error($category_term)) {
                                update_post_meta($post_id, 'wtm_category_name', $category_term->name);
                            }
                        }
                        
                        // Set sort order if provided
                        if (isset($api_item['sort']) && is_numeric($api_item['sort'])) {
                            wp_update_post(array(
                                'ID' => $post_id,
                                'menu_order' => intval($api_item['sort']),
                            ));
                        }
                        
                        // Set active status
                        if (isset($api_item['active'])) {
                            $is_active = ($api_item['active'] === true || $api_item['active'] === 'true' || $api_item['active'] === 1);
                            update_post_meta($post_id, 'wtm_active', $is_active ? '1' : '0');
                        }
                        
                        // Store other fields (same as update logic)
                        // SKU
                        $sku = '';
                        if (isset($api_item['extras']['sku']) && !empty($api_item['extras']['sku'])) {
                            $sku = sanitize_text_field($api_item['extras']['sku']);
                        } elseif (isset($api_item['sku']) && !empty($api_item['sku'])) {
                            $sku = sanitize_text_field($api_item['sku']);
                        }
                        if (!empty($sku)) {
                            update_post_meta($post_id, 'wtm_sku', $sku);
                        }
                        
                        // Active date ranges
                        if (isset($api_item['active_begin']) && !empty($api_item['active_begin'])) {
                            update_post_meta($post_id, 'wtm_active_begin', sanitize_text_field($api_item['active_begin']));
                        }
                        if (isset($api_item['active_end']) && !empty($api_item['active_end'])) {
                            update_post_meta($post_id, 'wtm_active_end', sanitize_text_field($api_item['active_end']));
                        }
                        if (isset($api_item['active_days'])) {
                            update_post_meta($post_id, 'wtm_active_days', sanitize_text_field($api_item['active_days']));
                        }
                        
                        // Sizes/variants
                        if (isset($api_item['sizes']) && is_array($api_item['sizes']) && !empty($api_item['sizes'])) {
                            update_post_meta($post_id, 'wtm_sizes', json_encode($api_item['sizes']));
                        }
                        
                        // Allergens
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
                                    update_post_meta($post_id, 'wtm_allergens', $allergens);
                                }
                            }
                        }
                        
                        // Nutritional values
                        if (isset($api_item['sizes']) && is_array($api_item['sizes'])) {
                            foreach ($api_item['sizes'] as $size) {
                                if (isset($size['default']) && $size['default'] === true && isset($size['extras']['menu_item_nutritional_value'])) {
                                    update_post_meta($post_id, 'wtm_nutritional_values', json_encode($size['extras']['menu_item_nutritional_value']));
                                    break;
                                }
                            }
                            if (empty(get_post_meta($post_id, 'wtm_nutritional_values', true)) && isset($api_item['sizes'][0]['extras']['menu_item_nutritional_value'])) {
                                update_post_meta($post_id, 'wtm_nutritional_values', json_encode($api_item['sizes'][0]['extras']['menu_item_nutritional_value']));
                            }
                        }
                        
                        // Kitchen internal name
                        if (isset($api_item['extras']) && is_array($api_item['extras'])) {
                            if (isset($api_item['extras']['menu_item_kitchen_internal_name']) && !empty($api_item['extras']['menu_item_kitchen_internal_name'])) {
                                update_post_meta($post_id, 'wtm_kitchen_internal_name', sanitize_text_field($api_item['extras']['menu_item_kitchen_internal_name']));
                            }
                        }
                        
                        $result['items_created']++;
                    }
                }
            }
        } catch (Exception $e) {
            $result['errors'][] = sprintf(__('Error processing item: %s', 'webtalize-menu'), $e->getMessage());
        }
    }
    
    // Create API response sample for debugging (first category with first item)
    if (is_array($api_data) && !is_wp_error($api_data)) {
        $sample_data = array();
        if (isset($api_data['categories']) && is_array($api_data['categories']) && !empty($api_data['categories'])) {
            $first_cat = $api_data['categories'][0];
            $sample_data['categories'] = array($first_cat);
        } elseif (isset($api_data['category']) && is_array($api_data['category']) && !empty($api_data['category'])) {
            $first_cat = $api_data['category'][0];
            $sample_data['category'] = array($first_cat);
        }
        // Also include top-level keys
        foreach (array('id', 'name', 'currency', 'default_language') as $key) {
            if (isset($api_data[$key])) {
                $sample_data[$key] = $api_data[$key];
            }
        }
        $result['api_response_sample'] = json_encode($sample_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    // Store name mismatches for user review (persist for 7 days)
    if (!empty($result['name_mismatches'])) {
        set_transient('wtm_name_mismatches', $result['name_mismatches'], 7 * DAY_IN_SECONDS);
    } else {
        // Clear old mismatches if none found
        delete_transient('wtm_name_mismatches');
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
    
    // Check for GlobalFood API structure - try both 'category' and 'categories' (plural)
    $categories = null;
    if (isset($api_data['categories']) && is_array($api_data['categories'])) {
        $categories = $api_data['categories'];
    } elseif (isset($api_data['category']) && is_array($api_data['category'])) {
        $categories = $api_data['category'];
    }
    
    if ($categories !== null) {
        $category_count = count($categories);
        $response_structure['has_categories'] = true;
        $response_structure['category_count'] = $category_count;
        
        foreach ($categories as $idx => $category) {
            // Try different possible field names for items
            $items = null;
            if (isset($category['item'])) {
                $items = $category['item'];
            } elseif (isset($category['items']) && is_array($category['items'])) {
                $items = $category['items'];
            } elseif (isset($category['menu_items']) && is_array($category['menu_items'])) {
                $items = $category['menu_items'];
            }
            
            if ($items !== null) {
                if (is_array($items)) {
                    if (isset($items[0]) || (count($items) > 0 && array_keys($items) !== range(0, count($items) - 1))) {
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
    $categories_sample = null;
    if (isset($api_data['categories']) && is_array($api_data['categories']) && !empty($api_data['categories'])) {
        $categories_sample = $api_data['categories'];
    } elseif (isset($api_data['category']) && is_array($api_data['category']) && !empty($api_data['category'])) {
        $categories_sample = $api_data['category'];
    }
    
    if ($categories_sample !== null) {
        $first_category = $categories_sample[0];
        $response_structure['sample_category_keys'] = array_keys($first_category);
        
        // Try different possible field names for items
        $first_items = null;
        if (isset($first_category['item'])) {
            $first_items = $first_category['item'];
        } elseif (isset($first_category['items']) && is_array($first_category['items'])) {
            $first_items = $first_category['items'];
        } elseif (isset($first_category['menu_items']) && is_array($first_category['menu_items'])) {
            $first_items = $first_category['menu_items'];
        }
        
        if ($first_items !== null) {
            $first_item = is_array($first_items) && (isset($first_items[0]) || count($first_items) > 0)
                ? (isset($first_items[0]) ? $first_items[0] : reset($first_items))
                : $first_items;
            if (is_array($first_item)) {
                $response_structure['sample_item_keys'] = array_keys($first_item);
            }
        }
    }
    
    // Include a sample of the raw API response for debugging (limited size)
    $api_response_sample = '';
    if (is_array($api_data)) {
        // Create a limited sample (first category with first item, max 2 levels deep)
        $sample_data = array();
        if (isset($api_data['categories']) && is_array($api_data['categories']) && !empty($api_data['categories'])) {
            $first_cat = $api_data['categories'][0];
            $sample_data['categories'] = array($first_cat);
        } elseif (isset($api_data['category']) && is_array($api_data['category']) && !empty($api_data['category'])) {
            $first_cat = $api_data['category'][0];
            $sample_data['category'] = array($first_cat);
        }
        // Also include top-level keys
        foreach (array('id', 'name', 'currency', 'default_language') as $key) {
            if (isset($api_data[$key])) {
                $sample_data[$key] = $api_data[$key];
            }
        }
        $api_response_sample = json_encode($sample_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    wp_send_json_success(array(
        'message' => $message,
        'item_count' => $item_count,
        'category_count' => $category_count,
        'response_structure' => $response_structure,
        'top_level_keys' => $top_level_keys,
        'api_response_sample' => $api_response_sample,
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
        
        // Add categories created info
        if (isset($result['categories_created']) && $result['categories_created'] > 0) {
            $message .= ' ' . sprintf(__('Created %d category/categories.', 'webtalize-menu'), $result['categories_created']);
        }
        
        // Add name mismatch info
        if (!empty($result['name_mismatches']) && is_array($result['name_mismatches'])) {
            $mismatch_count = count($result['name_mismatches']);
            $message .= '<br><strong style="color:#d63638;">' . sprintf(
                __('⚠ %d item(s) need review: Item number matches but name differs. Please review in the "Name Mismatch Review" section below.', 'webtalize-menu'),
                $mismatch_count
            ) . '</strong>';
        }
        
        // Add debug info to help troubleshoot matching issues
        if (isset($result['debug'])) {
            $debug_info = array();
            
            if (isset($result['debug']['unmatched_items']) && $result['debug']['unmatched_items'] > 0) {
                $debug_info[] = sprintf(__('%d items not matched', 'webtalize-menu'), $result['debug']['unmatched_items']);
                if (isset($result['debug']['unmatched_sample']) && !empty($result['debug']['unmatched_sample'])) {
                    $debug_info[] = __('Sample: ', 'webtalize-menu') . implode(', ', array_slice($result['debug']['unmatched_sample'], 0, 3));
                }
            }
            
            if (isset($result['debug']['matched_no_price_change']) && $result['debug']['matched_no_price_change'] > 0) {
                $debug_info[] = sprintf(__('%d matched but prices unchanged', 'webtalize-menu'), $result['debug']['matched_no_price_change']);
            }
            
            if (isset($result['debug']['first_item_keys'])) {
                $debug_info[] = __('First item fields: ', 'webtalize-menu') . implode(', ', array_slice($result['debug']['first_item_keys'], 0, 8));
            }
            
            if (!empty($debug_info)) {
                $message .= '<br><small style="color:#666;">' . implode(' | ', $debug_info) . '</small>';
            }
        }
        
        if (!empty($result['errors'])) {
            $message .= ' ' . __('Errors:', 'webtalize-menu') . ' ' . implode(', ', $result['errors']);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'result' => $result,
        ));
    } else {
        $error_message = __('Sync failed.', 'webtalize-menu');
        if (!empty($result['errors'])) {
            $error_message .= ' ' . implode(', ', $result['errors']);
        }
        wp_send_json_error(array(
            'message' => $error_message,
            'result' => $result,
        ));
    }
}
add_action('wp_ajax_wtm_manual_sync', 'wtm_ajax_manual_sync');

/**
 * Apply update to a single menu item (approve name change)
 */
function wtm_apply_name_update($post_id, $api_item, $api_price) {
    // Get item name from API
    $item_name = '';
    if (isset($api_item['name'])) {
        $item_name = sanitize_text_field($api_item['name']);
    } elseif (isset($api_item['title'])) {
        $item_name = sanitize_text_field($api_item['title']);
    }
    
    // Update the post title (name) - use FULL original name with numbers
    if (!empty($item_name)) {
        wp_update_post(array(
            'ID' => $post_id,
            'post_title' => $item_name,
        ));
    }
    
    // Update price if provided
    if ($api_price !== null && $api_price !== '' && is_numeric($api_price) && (float)$api_price > 0) {
        update_post_meta($post_id, 'wtm_price', $api_price);
    }
    
    // Update other fields (same logic as in sync function)
    $external_id = '';
    if (isset($api_item['id'])) {
        $external_id = sanitize_text_field($api_item['id']);
    } elseif (isset($api_item['external_id'])) {
        $external_id = sanitize_text_field($api_item['external_id']);
    }
    
    if (!empty($external_id)) {
        update_post_meta($post_id, 'wtm_external_id', $external_id);
    }
    
    if (isset($api_item['description']) && !empty($api_item['description'])) {
        $description = sanitize_textarea_field($api_item['description']);
        update_post_meta($post_id, 'wtm_description', $description);
        
        // Auto-detect dietary labels from description
        // Only update if we can detect labels AND no labels are currently set
        $current_labels = get_post_meta($post_id, 'wtm_dietary_labels', true);
        if (empty($current_labels) || !is_array($current_labels)) {
            $detected_labels = wtm_auto_detect_dietary_labels($description);
            if (!empty($detected_labels)) {
                update_post_meta($post_id, 'wtm_dietary_labels', $detected_labels);
            }
        }
    }
    
    // Update other meta fields as needed (same as sync function)
    // ... (you can add more field updates here if needed)
}

/**
 * AJAX handler: Approve single name update
 */
function wtm_ajax_approve_name_update() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'webtalize-menu')));
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wtm_api_sync_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'webtalize-menu')));
    }
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $mismatch_index = isset($_POST['mismatch_index']) ? intval($_POST['mismatch_index']) : -1;
    
    if ($post_id <= 0 || $mismatch_index < 0) {
        wp_send_json_error(array('message' => __('Invalid parameters.', 'webtalize-menu')));
    }
    
    // Get stored mismatches
    $name_mismatches = get_transient('wtm_name_mismatches');
    if (empty($name_mismatches) || !is_array($name_mismatches) || !isset($name_mismatches[$mismatch_index])) {
        wp_send_json_error(array('message' => __('Mismatch data not found.', 'webtalize-menu')));
    }
    
    $mismatch = $name_mismatches[$mismatch_index];
    
    // Verify post ID matches
    if ($mismatch['post_id'] != $post_id) {
        wp_send_json_error(array('message' => __('Post ID mismatch.', 'webtalize-menu')));
    }
    
    // Apply the update
    wtm_apply_name_update($post_id, $mismatch['api_item'], $mismatch['api_price']);
    
    // Remove this mismatch from the list
    unset($name_mismatches[$mismatch_index]);
    $name_mismatches = array_values($name_mismatches); // Re-index array
    
    if (empty($name_mismatches)) {
        delete_transient('wtm_name_mismatches');
    } else {
        set_transient('wtm_name_mismatches', $name_mismatches, 7 * DAY_IN_SECONDS);
    }
    
    wp_send_json_success(array(
        'message' => __('Item updated successfully.', 'webtalize-menu'),
    ));
}
add_action('wp_ajax_wtm_approve_name_update', 'wtm_ajax_approve_name_update');

/**
 * AJAX handler: Reject single name update
 */
function wtm_ajax_reject_name_update() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'webtalize-menu')));
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wtm_api_sync_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'webtalize-menu')));
    }
    
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $mismatch_index = isset($_POST['mismatch_index']) ? intval($_POST['mismatch_index']) : -1;
    
    if ($post_id <= 0 || $mismatch_index < 0) {
        wp_send_json_error(array('message' => __('Invalid parameters.', 'webtalize-menu')));
    }
    
    // Get stored mismatches
    $name_mismatches = get_transient('wtm_name_mismatches');
    if (empty($name_mismatches) || !is_array($name_mismatches) || !isset($name_mismatches[$mismatch_index])) {
        wp_send_json_error(array('message' => __('Mismatch data not found.', 'webtalize-menu')));
    }
    
    $mismatch = $name_mismatches[$mismatch_index];
    
    // Verify post ID matches
    if ($mismatch['post_id'] != $post_id) {
        wp_send_json_error(array('message' => __('Post ID mismatch.', 'webtalize-menu')));
    }
    
    // Remove this mismatch from the list (reject = skip, don't update)
    unset($name_mismatches[$mismatch_index]);
    $name_mismatches = array_values($name_mismatches); // Re-index array
    
    if (empty($name_mismatches)) {
        delete_transient('wtm_name_mismatches');
    } else {
        set_transient('wtm_name_mismatches', $name_mismatches, 7 * DAY_IN_SECONDS);
    }
    
    wp_send_json_success(array(
        'message' => __('Item skipped.', 'webtalize-menu'),
    ));
}
add_action('wp_ajax_wtm_reject_name_update', 'wtm_ajax_reject_name_update');

/**
 * AJAX handler: Approve all name updates
 */
function wtm_ajax_approve_all_name_updates() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'webtalize-menu')));
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wtm_api_sync_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'webtalize-menu')));
    }
    
    // Get stored mismatches
    $name_mismatches = get_transient('wtm_name_mismatches');
    if (empty($name_mismatches) || !is_array($name_mismatches)) {
        wp_send_json_error(array('message' => __('No items to update.', 'webtalize-menu')));
    }
    
    $updated_count = 0;
    foreach ($name_mismatches as $mismatch) {
        wtm_apply_name_update($mismatch['post_id'], $mismatch['api_item'], $mismatch['api_price']);
        $updated_count++;
    }
    
    // Clear all mismatches
    delete_transient('wtm_name_mismatches');
    
    wp_send_json_success(array(
        'message' => sprintf(__('Successfully updated %d items.', 'webtalize-menu'), $updated_count),
    ));
}
add_action('wp_ajax_wtm_approve_all_name_updates', 'wtm_ajax_approve_all_name_updates');

/**
 * AJAX handler: Reject all name updates
 */
function wtm_ajax_reject_all_name_updates() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'webtalize-menu')));
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wtm_api_sync_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'webtalize-menu')));
    }
    
    // Clear all mismatches
    delete_transient('wtm_name_mismatches');
    
    wp_send_json_success(array(
        'message' => __('All items skipped.', 'webtalize-menu'),
    ));
}
add_action('wp_ajax_wtm_reject_all_name_updates', 'wtm_ajax_reject_all_name_updates');

/**
 * AJAX handler: Auto-detect dietary labels for all menu items
 */
function wtm_ajax_auto_detect_labels() {
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'webtalize-menu')));
    }
    
    // Check nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wtm_api_sync_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'webtalize-menu')));
    }
    
    // Get all menu items
    $menu_items = get_posts(array(
        'post_type' => 'menu_item',
        'posts_per_page' => -1,
        'post_status' => 'any',
    ));
    
    $stats = array(
        'scanned' => 0,
        'detected' => 0,
        'skipped' => 0,
        'no_description' => 0,
    );
    
    foreach ($menu_items as $item) {
        $stats['scanned']++;
        
        // Get description
        $description = get_post_meta($item->ID, 'wtm_description', true);
        
        if (empty($description)) {
            $stats['no_description']++;
            continue;
        }
        
        // Check if item already has dietary labels
        $current_labels = get_post_meta($item->ID, 'wtm_dietary_labels', true);
        if (!empty($current_labels) && is_array($current_labels)) {
            $stats['skipped']++;
            continue;
        }
        
        // Auto-detect labels from description
        $detected_labels = wtm_auto_detect_dietary_labels($description);
        
        if (!empty($detected_labels)) {
            update_post_meta($item->ID, 'wtm_dietary_labels', $detected_labels);
            $stats['detected']++;
        }
    }
    
    $message = sprintf(
        __('Processed %d items. Detected labels for %d items, skipped %d items (already had labels), %d items had no description.', 'webtalize-menu'),
        $stats['scanned'],
        $stats['detected'],
        $stats['skipped'],
        $stats['no_description']
    );
    
    wp_send_json_success(array(
        'message' => $message,
        'stats' => $stats,
    ));
}
add_action('wp_ajax_wtm_auto_detect_labels', 'wtm_ajax_auto_detect_labels');

