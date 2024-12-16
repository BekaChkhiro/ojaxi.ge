<?php
function load_react_styles_scripts() {
    wp_enqueue_style('tailwind-styles', get_theme_file_uri('/build/index.css'));
    
    wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.production.min.js', array(), '18', true);
    wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js', array('react'), '18', true);
    wp_enqueue_script('react-app', get_theme_file_uri('/build/index.js'), array('react', 'react-dom'), '1.0', true);
    
    wp_localize_script('react-app', 'wpApiSettings', array(
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ));
    
    wp_localize_script('react-app', 'wcStoreApiSettings', array(
        'nonce' => wp_create_nonce('wc_store_api'),
        'storeApiRoot' => rest_url('wc/store/v1')
    ));
}
add_action('wp_enqueue_scripts', 'load_react_styles_scripts');

// Fondy Integration
add_action('rest_api_init', function () {
    register_rest_route('fondy/v1', '/checkout', array(
        'methods' => 'POST',
        'callback' => 'handle_fondy_checkout',
        'permission_callback' => '__return_true'
    ));
});

function handle_fondy_checkout($request) {
    $params = $request->get_params();
    
    $secretKey = 'whNuTCpCJgUSMRyshXEBaqMbKbJWD3IH';
    
    $signatureParams = array(
        'merchant_id' => $params['merchant_id'],
        'order_id' => $params['order_id'],
        'order_desc' => $params['order_desc'],
        'amount' => $params['amount'],
        'currency' => $params['currency']
    );
    ksort($signatureParams);
    $signature = hash_hmac('sha256', implode('|', $signatureParams), $secretKey);
    
    $fondyData = array(
        'request' => array_merge($signatureParams, array(
            'signature' => $signature
        ))
    );
    
    $response = wp_remote_post('https://pay.fondy.eu/api/checkout/url/', array(
        'body' => json_encode($fondyData),
        'headers' => array('Content-Type' => 'application/json'),
    ));
    
    if (is_wp_error($response)) {
        return new WP_Error('fondy_error', 'Failed to connect to Fondy', array('status' => 500));
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($body['response']['checkout_url'])) {
        return new WP_Error('fondy_error', 'Invalid response from Fondy', array('status' => 500));
    }
    
    return new WP_REST_Response(array(
        'checkout_url' => $body['response']['checkout_url']
    ), 200);
}

// CORS Configuration
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_http_origin();
        $allowed_origins = array(
            home_url(),
            'http://localhost:3000',
            'http://localhost'
        );
        
        if (in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
        } else {
            header('Access-Control-Allow-Origin: ' . esc_url_raw(home_url()));
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-WC-Store-API-Nonce');
        header('Vary: Origin');
        
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            exit();
        }
        
        return $value;
    });
}, 1);

// Product Template and URL Configuration
function add_custom_rewrite_rules() {
    add_rewrite_rule(
        'product/([^/]+)/?$',
        'index.php?product=$matches[1]',
        'top'
    );
}
add_action('init', 'add_custom_rewrite_rules');

add_filter('woocommerce_template_loader_files', function($files, $template) {
    if ($template === 'single-product.php') {
        return array(get_theme_file_path('react-template.php'));
    }
    return $files;
}, 10, 2);

function load_react_template($template) {
    global $post;
    
    if ($post && $post->post_type === 'product') {
        return get_theme_file_path('react-template.php');
    }
    
    return $template;
}
add_filter('template_include', 'load_react_template', 999);

function add_product_data() {
    global $post;
    
    if ($post && $post->post_type === 'product') {
        wp_localize_script('react-app', 'wpProductData', array(
            'product_id' => $post->ID,
            'product_slug' => $post->post_name,
            'is_preview' => isset($_GET['preview'])
        ));
    }
}
add_action('wp_enqueue_scripts', 'add_product_data');

function modify_product_permalink($permalink, $post) {
    if ($post->post_type === 'product') {
        return home_url('/product/' . $post->post_name);
    }
    return $permalink;
}
add_filter('post_type_link', 'modify_product_permalink', 10, 2);

function modify_product_preview_link($preview_link, $post) {
    if ($post->post_type === 'product') {
        return home_url('/product/' . $post->post_name . '?preview=true');
    }
    return $preview_link;
}
add_filter('preview_post_link', 'modify_product_preview_link', 10, 2);

function flush_rewrite_rules_on_activation() {
    add_custom_rewrite_rules();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'flush_rewrite_rules_on_activation');

// Basic WooCommerce Support
function mytheme_add_woocommerce_support() {
    add_theme_support('woocommerce', array(
        'thumbnail_image_width' => 150,
        'single_image_width'    => 300,
        'product_grid'          => array(
            'default_rows'    => 3,
            'min_rows'        => 2,
            'max_rows'        => 8,
            'default_columns' => 4,
            'min_columns'     => 2,
            'max_columns'     => 5,
        ),
    ));
    
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'mytheme_add_woocommerce_support');

// Cart API Endpoints
add_action('rest_api_init', function() {
    register_rest_route('wc/store/v1', '/cart/items/(?P<key>[a-zA-Z0-9_-]+)', array(
        'methods' => 'DELETE',
        'callback' => function($request) {
            $key = $request['key'];
            
            if (empty($key)) {
                return new WP_Error('missing_key', 'Cart item key is required', array('status' => 400));
            }
            
            $cart = WC()->cart;
            $removed = $cart->remove_cart_item($key);
            
            if ($removed) {
                $cart->calculate_totals();
                return new WP_REST_Response(array(
                    'success' => true,
                    'cart' => WC()->cart->get_cart()
                ), 200);
            }
            
            return new WP_Error('remove_failed', 'Failed to remove item from cart', array('status' => 500));
        },
        'permission_callback' => '__return_true'
    ));

    register_rest_route('wc/store/v1', '/cart/add-item', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_params();
            $product_id = isset($params['id']) ? intval($params['id']) : 0;
            $quantity = isset($params['quantity']) ? intval($params['quantity']) : 1;
            
            if (empty($product_id)) {
                return new WP_Error('missing_id', 'Product ID is required', array('status' => 400));
            }
            
            $cart = WC()->cart;
            $cart->empty_cart();
            $added = $cart->add_to_cart($product_id, $quantity);
            
            if ($added) {
                $cart->calculate_totals();
                return new WP_REST_Response(array(
                    'success' => true,
                    'cart' => WC()->cart->get_cart()
                ), 200);
            }
            
            return new WP_Error('add_failed', 'Failed to add item to cart', array('status' => 500));
        },
        'permission_callback' => '__return_true'
    ));
});

// Disable nonce verification for Store API
add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');

// API Gateway Permissions
add_action('init', function() {
    add_filter('woocommerce_rest_check_permissions', function($permission, $context, $object_id, $post_type){
        return true;
    }, 10, 4);
});

add_filter('woocommerce_rest_check_permissions', '__return_true');

// Session handling
add_action('init', function() {
    if (PHP_SESSION_NONE === session_status()) {
        session_start(array(
            'cookie_secure' => is_ssl(),
            'cookie_httponly' => true,
            'cookie_samesite' => 'None',
            'use_strict_mode' => true
        ));
    }
}, 1);

// Include custom post types
require_once get_template_directory() . '/custom-post-types.php';

// Gatamasheba Custom Endpoint
add_action('rest_api_init', function() {
    register_rest_route('custom/v1', '/gatamasheba', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_params();
            
            $post_data = array(
                'post_type' => 'gatamasheba',
                'post_status' => 'publish',
                'post_title' => 'temp_title'
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                return new WP_Error('create_failed', 'Failed to create post', array('status' => 500));
            }
            
            $post_title = 'Ojaxi#' . $post_id;
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $post_title
            ));
            
            update_post_meta($post_id, '_phone', sanitize_text_field($params['phone']));
            update_post_meta($post_id, '_first_name', sanitize_text_field($params['first_name']));
            update_post_meta($post_id, '_last_name', sanitize_text_field($params['last_name']));
            
            $normalized_phone = normalize_phone_number($params['phone']);
            $existing_posts = get_posts(array(
                'post_type' => 'gatamasheba',
                'meta_query' => array(
                    array(
                        'key' => '_phone_normalized',
                        'value' => $normalized_phone
                    )
                ),
                'posts_per_page' => 1,
                'post__not_in' => array($post_id)
            ));
            
            if (!empty($existing_posts)) {
                wp_delete_post($post_id, true);
                return new WP_Error(
                    'phone_exists',
                    'ეს ნომერი უკვე დარეგისტრირებულია',
                    array('status' => 400)
                );
            }
            
            update_post_meta($post_id, '_phone_normalized', $normalized_phone);
            
            return new WP_REST_Response(array(
                'success' => true,
                'post_id' => $post_id,
                'post_title' => $post_title
            ), 200);
        },
        'permission_callback' => '__return_true'
    ));
});


// Checkout fields modification
add_filter('woocommerce_checkout_fields', function($fields) {
    // Remove unnecessary fields
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_email']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_email']);
    unset($fields['order']['order_comments']);
    unset($fields['shipping']);

    // Modify remaining fields
    $fields['billing']['billing_first_name']['label'] = 'სახელი';
    $fields['billing']['billing_first_name']['placeholder'] = 'სახელი';
    $fields['billing']['billing_first_name']['priority'] = 10;
    $fields['billing']['billing_first_name']['required'] = true;

    $fields['billing']['billing_last_name']['label'] = 'გვარი';
    $fields['billing']['billing_last_name']['placeholder'] = 'გვარი';
    $fields['billing']['billing_last_name']['priority'] = 20;
    $fields['billing']['billing_last_name']['required'] = true;

    $fields['billing']['billing_city']['label'] = 'ქალაქი';
    $fields['billing']['billing_city']['placeholder'] = 'ქალაქი';
    $fields['billing']['billing_city']['priority'] = 30;
    $fields['billing']['billing_city']['required'] = true;

    $fields['billing']['billing_address_1']['label'] = 'მისამართი';
    $fields['billing']['billing_address_1']['placeholder'] = 'მისამართი';
    $fields['billing']['billing_address_1']['priority'] = 40;
    $fields['billing']['billing_address_1']['required'] = true;

    $fields['billing']['billing_phone']['label'] = 'ტელეფონი';
    $fields['billing']['billing_phone']['placeholder'] = 'ტელეფონი';
    $fields['billing']['billing_phone']['priority'] = 50;
    $fields['billing']['billing_phone']['required'] = true;

    return $fields;
});

// Hide order comments
add_filter('woocommerce_enable_order_notes_field', '__return_false');