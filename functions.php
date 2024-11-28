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
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Expose-Headers: Link');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization, X-WC-Store-API-Nonce, X-WP-Nonce');
        
        return $value;
    });
}, 15);

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
add_filter('woocommerce_checkout_fields', 'custom_override_checkout_fields');
function custom_override_checkout_fields($fields) {
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
        'label' => 'სხვა საქოტაქტო',
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
    
    $fields['billing']['billing_address_1']['placeholder'] = 'შეკვეთა';
    $fields['billing']['billing_city']['placeholder'] = 'შეკვეთა';
    
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
    
    return $fields;
}

// კუპონის ფორმის გათიშვა
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
            .woocommerce-checkout-review-order-table thead,
            .woocommerce-checkout-review-order-table tbody {
                display: none !important;
            }
            .woocommerce-checkout-review-order-table tfoot tr:not(:last-child) {
                display: none !important;
            }
        </style>
        <?php
    }
}

remove_action('woocommerce_checkout_order_review', 'woocommerce_order_review', 10);

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

// დავამატოთ AJAX handler კალათის გასასუფთავებლად
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
    
    // გავასუფთაოთ კალათა
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
        
        // დავამატოთ ქმედება შეკვეთის დასრულებისას
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
    register_rest_route('wc/store/v1', '/cart/remove-item', array(
        'methods' => 'POST',
        'callback' => 'handle_remove_cart_item',
        'permission_callback' => '__return_true'
    ));
});

function handle_remove_cart_item($request) {
    $params = $request->get_params();
    
    if (!isset($params['key'])) {
        return new WP_Error('missing_key', 'Cart item key is required', array('status' => 400));
    }
    
    $cart = WC()->cart;
    $removed = $cart->remove_cart_item($params['key']);
    
    if ($removed) {
        $cart->calculate_totals();
        return new WP_REST_Response(array(
            'success' => true,
            'cart' => WC()->cart->get_cart()
        ), 200);
    }
    
    return new WP_Error('remove_failed', 'Failed to remove item from cart', array('status' => 500));
}

// დავამატოთ CORS headers WooCommerce Store API-სთვის
add_action('rest_api_init', function() {
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