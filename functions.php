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
    
    // დავამატოთ WooCommerce Store API nonce
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

// Existing WordPress configurations
add_action('init', function() {
    add_filter('rest_authentication_errors', function($result) {
        if (true === $result || is_wp_error($result)) {
            return $result;
        }
 
        if (!is_user_logged_in()) {
            return true;
        }
 
        return $result;
    });
});

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

add_action('init', function() {
    add_filter('woocommerce_rest_check_permissions', function($permission, $context, $object_id, $post_type){
        return true;
    }, 10, 4);
});

add_filter('woocommerce_rest_check_permissions', '__return_true');

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

// Add WooCommerce support with full features
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
    
    // Add support for WooCommerce features
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'mytheme_add_woocommerce_support');

// Ensure WooCommerce templates are loaded
function ensure_woocommerce_templates() {
    if (class_exists('WooCommerce')) {
        if (!file_exists(get_stylesheet_directory() . '/woocommerce')) {
            mkdir(get_stylesheet_directory() . '/woocommerce', 0755);
        }
        
        // Copy WooCommerce templates from plugin to theme
        $wc_template_path = WC()->plugin_path() . '/templates/';
        $theme_template_path = get_stylesheet_directory() . '/woocommerce/';
        
        // Ensure checkout directory exists
        if (!file_exists($theme_template_path . 'checkout')) {
            mkdir($theme_template_path . 'checkout', 0755, true);
        }
        
        // Copy checkout form template
        if (!file_exists($theme_template_path . 'checkout/form-checkout.php')) {
            copy($wc_template_path . 'checkout/form-checkout.php', $theme_template_path . 'checkout/form-checkout.php');
        }
    }
}
add_action('after_switch_theme', 'ensure_woocommerce_templates');

// Add checkout endpoint and page
function setup_checkout_page() {
    // Add checkout endpoint
    add_rewrite_endpoint('checkout', EP_PAGES);
    
    // Create checkout page if it doesn't exist
    $checkout_page = get_page_by_path('checkout');
    if (!$checkout_page) {
        $page_data = array(
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => 1,
            'post_name'      => 'checkout',
            'post_title'     => 'Checkout',
            'post_content'   => '[woocommerce_checkout]',
            'post_parent'    => 0,
            'comment_status' => 'closed'
        );
        wp_insert_post($page_data);
    }
    
    flush_rewrite_rules();
}
add_action('init', 'setup_checkout_page');

// Add this to your existing functions.php
add_action('rest_api_init', function() {
    // Add CORS headers for WooCommerce Store API
    add_filter('rest_pre_serve_request', function($served, $result, $request) {
        if (strpos($request->get_route(), '/wc/store/') !== false) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WC-Store-API-Nonce');
        }
        return $served;
    }, 10, 3);
});

// Disable nonce verification for Store API
add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');

// ჩექაუთი ველების მოდიფიკაცია
add_filter('woocommerce_checkout_fields', function($fields) {
    // წავშალოთ არასაჭირო ველები
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_last_name']);
    
    // შევცვალოთ დარჩენილი ველების ლეიბლები და პრიორიტეტები
    $fields['billing']['billing_first_name']['label'] = 'სახელი და გვარი';
    $fields['billing']['billing_first_name']['priority'] = 10;
    
    $fields['billing']['billing_phone']['label'] = 'ტელეფონი';
    $fields['billing']['billing_phone']['priority'] = 20;
    
    $fields['billing']['billing_phone_alt'] = array(
        'label' => 'სხვა საქოტაქტ',
        'required' => false,
        'type' => 'tel',
        'class' => array('form-row-wide'),
        'priority' => 25
    );
    
    $fields['billing']['billing_email']['label'] = 'ელ-ფოსტა';
    $fields['billing']['billing_email']['priority'] = 30;
    
    $fields['billing']['billing_city']['label'] = 'ქალაქი';
    $fields['billing']['billing_city']['priority'] = 40;
    $fields['billing']['billing_city']['required'] = true;
    
    $fields['billing']['billing_address_1']['label'] = 'მისამართი';
    $fields['billing']['billing_address_1']['priority'] = 50;
    $fields['billing']['billing_address_1']['required'] = true;
    
    $fields['billing']['billing_address_1']['placeholder'] = 'მისამართი';
    $fields['billing']['billing_city']['placeholder'] = 'ქალაქი';
    
    $fields['billing']['billing_country'] = array(
        'type' => 'hidden',
        'default' => 'GE',
        'required' => true
    );
    
    $fields['billing']['billing_state'] = array(
        'type' => 'hidden',
        'default' => '',
        'required' => false
    );
    
    // დავმალოთ order review-ს ცხრილის ეენტები
    add_filter('woocommerce_order_review_order_table_args', function($args) {
        $args['show_cart_contents'] = false;
        return $args;
    });
    
    return $fields;
});

// კუპონის ფომის გათიშა
remove_action('woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10);

// შეკვეთის დეტალების დამალვა
add_filter('woocommerce_cart_item_visible', '__return_false');
add_filter('woocommerce_cart_item_class', '__return_false');

// შეკვეთის ჯამური თანხის სექციის მოდიფიკაცია
add_filter('woocommerce_checkout_cart_item_visible', '__return_false');

// დამატებითი სტილები რომ დავმალოთ პროდუქტების ცხრილი
add_action('wp_head', 'custom_checkout_css');
function custom_checkout_css() {
    if (is_checkout()) {
        ?>
        <style>
            /* დავტოვოთ ცხრილი ხილული */
            .woocommerce-checkout-review-order-table {
                width: 100%;
                margin-bottom: 20px;
            }
            
            /* გავაფორმოთ ცხრილის სათაური */
            .woocommerce-checkout-review-order-table thead th {
                background: #f8f8f8;
                padding: 10px;
                text-align: left;
            }
            
            /* გავაფორმოთ პროდუქტების სტრიქონები */
            .woocommerce-checkout-review-order-table tbody td {
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
            
            /* გავაფორმოთ ჯამური თანხის სექცია */
            .woocommerce-checkout-review-order-table tfoot tr:last-child {
                font-weight: bold;
            }
        </style>
        <?php
    }
}

// შევცვალოთ "Place order" ღილაკის ტექსტი
add_filter('woocommerce_order_button_text', function() {
    return 'შეკვეთა';
});

add_filter('woocommerce_billing_fields_title', '__return_empty_string');

// დავამატოთ AJAX ქმედება შეკვეთის დასრულებისთვის
add_action('wp_ajax_wc_order_completed', 'handle_order_completed');
add_action('wp_ajax_nopriv_wc_order_completed', 'handle_order_completed');

function handle_order_completed() {
    if (isset($_POST['order_id'])) {
        $order_id = intval($_POST['order_id']);
        WC()->cart->empty_cart();
        wp_send_json_success([
            'success' => true
        ]);
    }
    wp_send_json_error();
}

// დავამატოთ JavaScript-ის ლოკალიზაცია
add_action('wp_enqueue_scripts', function() {
    wp_localize_script('react-app', 'wcCheckout', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wc_order_completed')
    ));
});

// დავამატოთ AJAX handler კალათი გასასუფთავებლდ
add_action('wp_ajax_clear_cart_after_order', 'handle_clear_cart_after_order');
add_action('wp_ajax_nopriv_clear_cart_after_order', 'handle_clear_cart_after_order');

function handle_clear_cart_after_order() {
    check_ajax_referer('clear_cart_nonce', 'nonce');
    
    if (!isset($_POST['order_id'])) {
        wp_send_json_error('No order ID provided');
        return;
    }
    
    $order_id = intval($_POST['order_id']);
    
    // შევამოწმოთ შეკვეთის არსებობა
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Invalid order ID');
        return;
    }
    
    // გავასუფთათ კალა���ა
    WC()->cart->empty_cart();
    
    // დავამატოთ შეკვეთის სტატუსის განახლება (თუ საჭიროა)
    $order->update_status('processing');
    
    wp_send_json_success(array(
        'message' => 'Cart cleared successfully',
        'order_id' => $order_id
    ));
}

// დავამატოთ შეკვეთის დასრულების hook
add_action('woocommerce_checkout_order_processed', 'handle_order_completion', 10, 1);

function handle_order_completion($order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
        // გავასუფთაოთ კალათა
        WC()->cart->empty_cart();
        
        // დავამატოთ ქმედებ შეკვეთის დასრულებისას
        do_action('custom_order_completed', $order_id);
    }
}

// დავამატოთ JavaScript-ი ლოკალიზაცია
add_action('wp_enqueue_scripts', function() {
    wp_localize_script('react-app', 'wcCheckout', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('clear_cart_nonce') 
    ));
});

// დავამატოთ WooCommerce Store API-ს endpoint-ები
add_action('rest_api_init', function() {
    register_rest_route('wc/store/v1', '/cart/items/(?P<key>[a-zA-Z0-9_-]+)', array(
        'methods' => WP_REST_Server::DELETABLE,
        'callback' => 'handle_remove_cart_item',
        'permission_callback' => '__return_true'
    ));
});

function handle_remove_cart_item($request) {
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
            'cart' => WC()->cart->get_cart_for_session()
        ), 200);
    }
    
    return new WP_Error('remove_failed', 'Failed to remove item from cart', array('status' => 500));
}

// დავამატოთ CORS headers
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($served, $result, $request) {
        if (strpos($request->get_route(), '/wc/store/') !== false) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WC-Store-API-Nonce');
        }
        return $served;
    }, 10, 3);
});

// დევცვალოთ add_wc_store_api_nonce ფუნქცია
function add_wc_store_api_nonce() {
    wp_enqueue_script('wc-store-api-nonce', null, array(), null);
    wp_add_inline_script('wc-store-api-nonce', sprintf(
        'window.wcStoreApiSettings = %s;',
        wp_json_encode(array(
            'nonce' => wp_create_nonce('wc_store_api'),
            'root' => esc_url_raw(rest_url()),
            'storeApiRoot' => esc_url_raw(rest_url('wc/store/v1'))
        ))
    ));
}
add_action('wp_enqueue_scripts', 'add_wc_store_api_nonce', 1);

// დევცვალოთ CORS-ის კონფიგურაცია
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

// განვაახლოთ WooCommerce Store API-ს CORS კონფიგურაცია
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($served, $result, $request) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        $allowed_origins = array(
            home_url(),
            'http://localhost:3000',
            'http://localhost',
            // დაამატეთ სხვა საჭირო დომენები
        );
        
        if (in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: ' . home_url());
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-WC-Store-API-Nonce, Accept');
        header('Vary: Origin');
        
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            return true;
        }
        
        return $served;
    }, 10, 3);
});

// დავამატოთ სესიის გაუმჯობესებული კონფიგურაცია
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

// შევცვალოთ WooCommerce სესიის კონფიგურაცია
add_filter('woocommerce_session_handler', function() {
    return 'WC_Session_Handler';
});

add_action('woocommerce_init', function() {
    if (isset(WC()->session) && !WC()->session->has_session()) {
        WC()->session->set_customer_session_cookie(true);
    }
}, 1);

// დავამატოთ სპეციფიური CORS headers Safari-სთვის
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($served, $result, $request) {
        $origin = get_http_origin();
        $allowed_origin = $origin ?: home_url();
        
        header('Access-Control-Allow-Origin: ' . esc_url_raw($allowed_origin));
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WC-Store-API-Nonce, X-WP-Nonce');
        
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            return true;
        }
        
        return $served;
    }, 10, 3);
});

// დავამტოთ ახალი endpoint კალათიდან პროდუქტის წასაშლელად
add_action('rest_api_init', function() {
    register_rest_route('wc/store/v1', '/cart/remove-item', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_params();
            $key = isset($params['key']) ? sanitize_text_field($params['key']) : '';
            
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
});

add_action('rest_api_init', function() {
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
            
            // წავშალოთ არსებული პროდუქტები კალათიდან
            $cart->empty_cart();
            
            // დავამატოთ პროდუქტი
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

// დავამატოთ WooCommerce Store API nonce
add_action('wp_enqueue_scripts', function() {
    wp_localize_script('react-app', 'wcStoreApiSettings', array(
        'nonce' => wp_create_nonce('wc_store_api'),
        'storeApiRoot' => rest_url('wc/store/v1')
    ));
});

// CORS-ის კონფიგურაცია WooCommerce Store API-სთვის
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

// დავამატოთ order review-ს კასტომიზაცია
add_filter('woocommerce_cart_item_name', function($name, $cart_item, $cart_item_key) {
    $product = $cart_item['data'];
    $quantity = $cart_item['quantity'];
    
    return sprintf(
        '%s <strong>x %s</strong>',
        $product->get_name(),
        $quantity
    );
}, 10, 3);

// დავამატოთ ჯამური თანხის ფორმატირება
add_filter('woocommerce_cart_totals_order_total_html', function($value) {
    $total = WC()->cart->get_total('edit');
    return sprintf(
        '<span class="amount">%s ₾</span>',
        number_format($total, 2)
    );
});

// მოვაშოროთ ზედმეტი ელემენტები order review-დან
add_action('wp_head', function() {
    if (is_checkout()) {
        ?>
        <style>
            /* დავმალოთ ყველა ზედმეტი ელემენტი */
            .woocommerce-checkout-review-order-table thead,
            .woocommerce-checkout-review-order-table tbody,
            .woocommerce-checkout-review-order-table tfoot tr:not(:last-child) {
                display: none !important;
            }
            
            /* გავაფორმოთ ჯამური თანხის სტრიქონი */
            .woocommerce-checkout-review-order-table tfoot tr:last-child {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none;
            }
            
            .woocommerce-checkout-review-order-table tfoot tr:last-child th {
                font-size: 16px;
                font-weight: normal;
            }
            
            .woocommerce-checkout-review-order-table tfoot tr:last-child td {
                font-size: 18px;
                font-weight: bold;
            }
            
            /* შევცვალოთ ტექსტი */
            .woocommerce-checkout-review-order-table tfoot tr:last-child th::before {
                content: "სულ ჯამი: ";
            }
            
            /* დავმალოთ ორიგინალი ტექსტი */
            .woocommerce-checkout-review-order-table tfoot tr:last-child th span {
                display: none;
            }
        </style>
        <?php
    }
});

add_action('wp_head', function() {
    if (is_checkout()) {
        ?>
        <style>
            /* დავმალოთ ორიგინალი order review ცხრილი */
            .woocommerce-checkout-review-order-table {
                display: none !important;
            }
            
            /* გავაფორმოთ სულ ჯამის სტრიქონი */
            .total-row {
                padding: 15px 0;
                border-top: 1px solid #eee;
                margin-top: 15px;
            }
            
            .total-label {
                font-size: 16px;
                font-weight: normal;
            }
            
            .total-amount {
                font-size: 18px;
                font-weight: bold;
            }
            
            /* დავმალოთ "Total" ტექსტი */
            .order-total th > span,
            .includes_tax {
                display: none !important;
            }

            /* გადახდის მეთოდების სტილები */
            .payment-section {
                margin-top: 2rem;
            }

            #payment {
                display: block !important;
            }

            /* შეკვეთის ღილაკის სტილი */
            #place_order {
                width: 100% !important;
                margin-top: 1rem !important;
            }
        </style>
        <?php
    }
});

// გავთიშოთ სტანდარტული order review
add_action('init', function() {
    remove_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10);
});

// დავამატოთ endpoint-ები კალათის მენეჯმენტისთვის
add_action('rest_api_init', function() {
    // აშლის endpoint
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

    // რაოდენობის განახლების endpoint
    register_rest_route('wc/store/v1', '/cart/items/(?P<key>[a-zA-Z0-9_-]+)', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $key = $request['key'];
            $params = $request->get_params();
            $quantity = isset($params['quantity']) ? intval($params['quantity']) : 1;
            
            if (empty($key)) {
                return new WP_Error('missing_key', 'Cart item key is required', array('status' => 400));
            }
            
            $cart = WC()->cart;
            $updated = $cart->set_quantity($key, $quantity);
            
            if ($updated) {
                $cart->calculate_totals();
                return new WP_REST_Response(array(
                    'success' => true,
                    'cart' => array(
                        'items' => array_values($cart->get_cart())
                    )
                ), 200);
            }
            
            return new WP_Error('update_failed', 'Failed to update cart item', array('status' => 500));
        },
        'permission_callback' => '__return_true'
    ));
});

// ჩართეთ custom-post-types.php ფაილი
require_once get_template_directory() . '/custom-post-types.php';

// REST API-ით შექმნილი პოსტების ვალიდაცია
add_action('rest_pre_insert_gatashoreba', function($prepared_post, $request) {
    // ... არსებული კოდი ...
}, 10, 2);

// დავამატოთ REST API-ს უფლებების კონფიგურაცია
add_filter('rest_authentication_errors', function($result) {
    // თუ მომხმარებელი არ არის ავტორიზებული, მაინც დავუშვათ მოთხოვნა
    if (!empty($result)) {
        return $result;
    }
    return true;
});

// დავამატოთ gatashoreba პოსტ ტიპისთვის სპეციფიური უფლება
add_filter('rest_gatashoreba_collection_params', function($params) {
    return $params;
});

// დავამატოთ gatashoreba პოსტ ტიპისთვის create უფლება
add_filter('rest_pre_insert_gatashoreba', function($prepared_post, $request) {
    // გავთიშოთ ავტორიზაციის შემოწმება
    remove_filter('rest_pre_insert_gatashoreba', 'rest_authorization_required_code');
    return $prepared_post;
}, 9, 2);

// დავამატოთ CORS-ის კონფიგურაცია
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
        
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            exit();
        }
        
        return $value;
    });
}, 15);

// გავთიშოთ nonce შემოწმება gatashoreba პოსტ ტიპისთვის
add_filter('rest_nonce_required_actions', function($required) {
    return array_diff($required, ['create_gatashoreba']);
});

// დავამატოთ ახალი custom endpoint გათამაშებისთვის
add_action('rest_api_init', function() {
    register_rest_route('custom/v1', '/gatashoreba', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $params = $request->get_params();
            
            // შევქმნათ ახალი პოსტი
            $post_data = array(
                'post_type' => 'gatashoreba',
                'post_status' => 'publish',
                'post_title' => 'temp_title' // დროებითი სათაური
            );
            
            $post_id = wp_insert_post($post_data);
            
            if (is_wp_error($post_id)) {
                return new WP_Error('create_failed', 'Failed to create post', array('status' => 500));
            }
            
            // დანვაახლოთ სათაური ID-ის მიხედვით
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => 'Ojaxi#' . $post_id
            ));
            
            // დავამატოთ მეტა მონაცემები
            update_post_meta($post_id, '_phone', sanitize_text_field($params['phone']));
            update_post_meta($post_id, '_first_name', sanitize_text_field($params['first_name']));
            update_post_meta($post_id, '_last_name', sanitize_text_field($params['last_name']));
            
            // შევამოწმოთ ტელეფონის ნომრის დუბლირება
            $normalized_phone = normalize_phone_number($params['phone']);
            $existing_posts = get_posts(array(
                'post_type' => 'gatashoreba',
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
            
            // შევინახოთ ნორმალიზებული ნომერი
            update_post_meta($post_id, '_phone_normalized', $normalized_phone);
            
            return new WP_REST_Response(array(
                'success' => true,
                'post_id' => $post_id
            ), 200);
        },
        'permission_callback' => '__return_true'
    ));
});