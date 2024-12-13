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
    
    wp_localize_script('react-app', 'wcCheckout', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('clear_cart_nonce')
    ));
    
    wp_localize_script('react-app', 'wcStoreApiSettings', array(
        'nonce' => wp_create_nonce('wc_store_api'),
        'storeApiRoot' => rest_url('wc/store/v1')
    ));
}
add_action('wp_enqueue_scripts', 'load_react_styles_scripts');

// WooCommerce Store API-ს CORS კონფიგურაცია
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($served, $result, $request) {
        if (strpos($request->get_route(), '/wc/store/') !== false) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw(home_url()));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WC-Store-API-Nonce');
        }
        return $served;
    }, 10, 3);
});

// სტანდარტული WooCommerce ფუნქციონალის ჩართვა
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

// კალათის API endpoints
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