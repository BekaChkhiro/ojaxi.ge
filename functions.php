<?php

function load_react_styles_scripts() {
    if (!is_checkout() && !is_order_pay_page()) {
        wp_enqueue_style('tailwind-styles', get_theme_file_uri('/build/index.css'));
        wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.development.js', array(), '18', true);
        wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.development.js', array('react'), '18', true);
        wp_enqueue_script('react-app', get_theme_file_uri('/build/index.js'), array('react', 'react-dom'), '1.0', true);
        
        wp_localize_script('react-app', 'wpApiSettings', array(
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }
}
add_action('wp_enqueue_scripts', 'load_react_styles_scripts');

function add_google_verification() {
    echo '<meta name="google-site-verification" content="PJVPfFFKyO0MVEry9TD8Rk_mP2BWIaR0qDGlG09IH3A" />' . "\n";
}
add_action('wp_head', 'add_google_verification');

add_action('init', function() {
    add_filter('rest_authentication_errors', function($result) {
        if (strpos($_SERVER['REQUEST_URI'], '/wp-json/wc/v2/orders') !== false) {
            return null;
        }
        
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
        $origin = get_home_url();
        
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Expose-Headers: Link');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
        }
        
        return $value;
    });
}, 15);

add_action('init', function() {
    add_filter('woocommerce_rest_check_permissions', function($permission, $context, $object_id, $post_type) {
        if ($post_type === 'shop_order') {
            return true;
        }
        return $permission;
    }, 10, 4);
});

add_filter('woocommerce_rest_check_permissions', '__return_true');

// Add custom rewrite rules
function add_custom_rewrite_rules() {
    add_rewrite_rule(
        'product/([^/]+)/?$',
        'index.php?product=$matches[1]',
        'top'
    );
}
add_action('init', 'add_custom_rewrite_rules');

// გამორთეთ WooCommerce-ის სტანდარტული თემფლეითი
add_filter('woocommerce_template_loader_files', function($files, $template) {
    // გამოვრიცხოთ checkout გვერდები
    if (is_checkout() || is_order_pay_page()) {
        return $files;
    }
    
    if ($template === 'single-product.php') {
        return array(get_theme_file_path('react-template.php'));
    }
    return $files;
}, 10, 2);

// React-ის თემფლეითის ჩატვირთვა
function load_react_template($template) {
    global $post;
    
    // გამოვრიცხოთ checkout გვერდები
    if (is_checkout() || is_order_pay_page()) {
        return $template;
    }
    
    // Check if this is a product page or preview
    if ($post && $post->post_type === 'product') {
        return get_theme_file_path('react-template.php');
    }
    
    return $template;
}
add_filter('template_include', 'load_react_template', 999);

// პროდუქტის ID-ის გადაცემა React-ისთვის
function add_product_data() {
    global $post;
    
    // Check if this is a product page or preview
    if ($post && $post->post_type === 'product') {
        wp_localize_script('react-app', 'wpProductData', array(
            'product_id' => $post->ID,
            'product_slug' => $post->post_name,
            'is_preview' => isset($_GET['preview'])
        ));
    }
}
add_action('wp_enqueue_scripts', 'add_product_data');

// Modify product permalinks
function modify_product_permalink($permalink, $post) {
    if ($post->post_type === 'product') {
        return home_url('/product/' . $post->post_name);
    }
    return $permalink;
}
add_filter('post_type_link', 'modify_product_permalink', 10, 2);

// Handle view/preview links in admin
function modify_product_preview_link($preview_link, $post) {
    if ($post->post_type === 'product') {
        return home_url('/product/' . $post->post_name . '?preview=true');
    }
    return $preview_link;
}
add_filter('preview_post_link', 'modify_product_preview_link', 10, 2);

// Flush rewrite rules on theme activation
function flush_rewrite_rules_on_activation() {
    add_custom_rewrite_rules();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'flush_rewrite_rules_on_activation');

// დავამატოთ ფუნქცია checkout გვერდის შესამოწმებლად
function is_order_pay_page() {
    global $wp;
    return (isset($wp->query_vars['order-pay']) || 
            (isset($_GET['pay_for_order']) && isset($_GET['key'])));
}

// დავამატოთ Fondy-ის გადახდის URL-ის მიღების ფუნქცია
function get_fondy_payment_url($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return false;

    // Fondy configuration
    $merchant_id = '5ad6b888f4becb0c33d543d54e57d86c';
    $secret_key = 'YOUR_SECRET_KEY'; // Replace with your actual secret key

    // Order data
    $amount = $order->get_total() * 100; // Convert to cents
    $order_desc = sprintf('Order #%s from %s %s', 
        $order->get_order_number(),
        $order->get_billing_first_name(),
        $order->get_billing_last_name()
    );

    // Payment data
    $payment_data = array(
        'order_id'    => $order_id,
        'merchant_id' => $merchant_id,
        'order_desc'  => $order_desc,
        'amount'      => $amount,
        'currency'    => 'GEL',
        'server_callback_url' => home_url('/wc-api/fondy-callback'),
        'response_url'        => $order->get_checkout_order_received_url(),
        'sender_email'        => $order->get_billing_email()
    );

    // Generate signature
    ksort($payment_data);
    $signature = '';
    foreach ($payment_data as $key => $value) {
        $signature .= $value . '|';
    }
    $signature = substr($signature, 0, -1);
    $signature = hash_hmac('sha256', $signature, $secret_key);

    $payment_data['signature'] = $signature;

    // Generate payment URL
    return 'https://pay.fondy.eu/merchants/' . $merchant_id . '/default/index.html?token=' . $signature;
}

// დავამატოთ REST API endpoint Fondy-ის URL-ის მისაღებად
add_action('rest_api_init', function() {
    register_rest_route('wc/v2', '/orders/(?P<id>\d+)/fondy-url', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $order_id = $request->get_param('id');
            $payment_url = get_fondy_payment_url($order_id);
            
            if (!$payment_url) {
                return new WP_Error('no_url', 'Payment URL not found', array('status' => 404));
            }
            
            return array('payment_url' => $payment_url);
        },
        'permission_callback' => '__return_true'
    ));
});

// Add REST API endpoint for Fondy payment URL
add_action('rest_api_init', function() {
    register_rest_route('wc/v2', '/orders/(?P<id>\d+)/fondy-url', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $order_id = $request->get_param('id');
            $order = wc_get_order($order_id);
            
            if (!$order) {
                return new WP_Error('no_order', 'Order not found', array('status' => 404));
            }
            
            // Get Fondy payment data
            $payment_data = $order->get_meta('_fondy_payment_data');
            if (empty($payment_data)) {
                return new WP_Error('no_payment_data', 'Fondy payment data not found', array('status' => 404));
            }
            
            $data = maybe_unserialize($payment_data);
            $payment_url = isset($data['payment_url']) ? $data['payment_url'] : false;
            
            if (!$payment_url) {
                return new WP_Error('no_payment_url', 'Fondy payment URL not found', array('status' => 404));
            }
            
            return array(
                'payment_url' => $payment_url
            );
        },
        'permission_callback' => '__return_true'
    ));
});

// Allow orders to be created without authentication
add_filter('woocommerce_rest_check_permissions', function($permission, $context, $object_id, $post_type) {
    if ($post_type === 'shop_order' && $context === 'create') {
        return true;
    }
    return $permission;
}, 10, 4);

// Add nonce verification bypass for order creation
add_filter('woocommerce_rest_nonce_check_permission', function($permission) {
    if (strpos($_SERVER['REQUEST_URI'], '/wp-json/wc/v2/orders') !== false) {
        return true;
    }
    return $permission;
});

// Add proper CORS headers
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_home_url();
        
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            exit();
        }
        
        return $value;
    });
}, 15);

// Add debugging for order creation
add_action('rest_api_init', function() {
    register_rest_route('wc/v2', '/debug-order', array(
        'methods' => 'POST',
        'callback' => function($request) {
            $parameters = $request->get_params();
            error_log('Order Creation Debug: ' . print_r($parameters, true));
            return new WP_REST_Response(array('status' => 'logged'), 200);
        },
        'permission_callback' => '__return_true'
    ));
});

// Add WooCommerce support
function mytheme_add_woocommerce_support() {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'mytheme_add_woocommerce_support');

// Make sure WooCommerce templates are loaded
function mytheme_woocommerce_templates($template) {
    if (is_checkout()) {
        return WC()->plugin_path() . '/templates/checkout/form-checkout.php';
    } elseif (is_order_pay_page()) {
        return WC()->plugin_path() . '/templates/checkout/form-pay.php';
    }
    
    // Handle React template for product pages
    global $post;
    if (!is_checkout() && !is_order_pay_page() && $post && $post->post_type === 'product') {
        return get_theme_file_path('react-template.php');
    }
    
    return $template;
}
add_filter('template_include', 'mytheme_woocommerce_templates', 999);

// Remove custom checkout template for WooCommerce pages
function mytheme_remove_react_template($template, $template_name, $template_path) {
    if (is_checkout() || is_order_pay_page()) {
        return $template;
    }
    return $template;
}
add_filter('woocommerce_locate_template', 'mytheme_remove_react_template', 10, 3);

// Make sure payment gateways are loaded
function mytheme_init_gateway() {
    if (class_exists('WC_Payment_Gateways')) {
        $gateways = WC()->payment_gateways->payment_gateways();
    }
}
add_action('init', 'mytheme_init_gateway');

// Load WooCommerce scripts and styles
function mytheme_enqueue_woocommerce() {
    if (is_checkout() || is_order_pay_page()) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('wc-checkout');
        wp_enqueue_script('wc-cart-fragments');
        wp_enqueue_script('woocommerce');
        wp_enqueue_style('woocommerce-general');
        wp_enqueue_style('woocommerce-layout');
        wp_enqueue_style('woocommerce-smallscreen');
    }
}
add_action('wp_enqueue_scripts', 'mytheme_enqueue_woocommerce', 20);

// Add body classes
function mytheme_add_body_class($classes) {
    if (is_checkout() || is_order_pay_page()) {
        $classes[] = 'woocommerce-checkout';
        $classes[] = 'woocommerce-page';
    }
    return $classes;
}
add_filter('body_class', 'mytheme_add_body_class');

// Ensure WooCommerce templates are used for checkout
function mytheme_use_wc_templates($located, $template_name, $args, $template_path, $default_path) {
    if (is_checkout() || is_order_pay_page()) {
        $default_path = WC()->plugin_path() . '/templates/';
        $new_located = locate_template(
            array(
                trailingslashit($template_path) . $template_name,
                $template_name
            )
        );
        
        if (!$new_located) {
            $new_located = $default_path . $template_name;
        }
        
        if (file_exists($new_located)) {
            return $new_located;
        }
    }
    return $located;
}
add_filter('wc_get_template', 'mytheme_use_wc_templates', 10, 5);

// Add AJAX handler for order creation
add_action('wp_ajax_create_order', 'handle_create_order');
add_action('wp_ajax_nopriv_create_order', 'handle_create_order');

function handle_create_order() {
    check_ajax_referer('wp_rest', 'security');

    try {
        $order = wc_create_order();
        
        // Set billing data
        $billing_address = array(
            'first_name' => sanitize_text_field($_POST['billing_first_name']),
            'last_name'  => sanitize_text_field($_POST['billing_last_name']),
            'phone'      => sanitize_text_field($_POST['billing_phone']),
            'address_1'  => sanitize_text_field($_POST['billing_address_1']),
            'city'       => sanitize_text_field($_POST['billing_city']),
            'country'    => 'GE'
        );
        
        $order->set_address($billing_address, 'billing');
        $order->set_address($billing_address, 'shipping');

        // Add items to order
        if (isset($_POST['cart_items']) && is_array($_POST['cart_items'])) {
            foreach ($_POST['cart_items'] as $item) {
                $product_id = absint($item['product_id']);
                $quantity = absint($item['quantity']);
                $product = wc_get_product($product_id);
                
                if ($product) {
                    $order->add_product($product, $quantity);
                }
            }
        }

        // Set payment method
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $order->set_payment_method($payment_method);

        // Add order note if alternative phone provided
        if (!empty($_POST['order_comments'])) {
            $order->add_order_note(sanitize_text_field($_POST['order_comments']));
        }

        $order->calculate_totals();
        $order->save();

        // Generate Fondy payment URL immediately if needed
        if ($payment_method === 'fondy') {
            $payment_url = generate_fondy_payment_url($order);
            if (!$payment_url) {
                throw new Exception('Fondy payment URL generation failed');
            }
            wp_send_json_success(array(
                'order_id' => $order->get_id(),
                'payment_url' => $payment_url
            ));
        } else {
            wp_send_json_success(array(
                'order_id' => $order->get_id(),
                'order_received_url' => $order->get_checkout_order_received_url()
            ));
        }

    } catch (Exception $e) {
        wp_send_json_error(array(
            'message' => $e->getMessage()
        ));
    }
}

function generate_fondy_payment_url($order) {
    $merchant_id = '5ad6b888f4becb0c33d543d54e57d86c';
    $secret_key = 'whNuTCpCJgUSMRyshXEBaqMbKbJWD3IH';

    $payment_data = array(
        'order_id'      => time() . '_' . $order->get_id(),
        'merchant_id'   => $merchant_id,
        'order_desc'    => sprintf('Order #%s', $order->get_id()),
        'amount'        => round($order->get_total() * 100),
        'currency'      => 'GEL',
        'response_url'  => $order->get_checkout_order_received_url(),
        'server_callback_url' => home_url('/wc-api/fondy-callback'),
        'sender_email'  => 'customer@example.com',
        'required_rectoken' => 'Y',
        'lifetime'      => 36000,
        'lang'          => 'ka'
    );

    // Generate signature
    $signature_data = $payment_data;
    ksort($signature_data);
    $signature_string = $secret_key;
    foreach ($signature_data as $key => $value) {
        $signature_string .= '|' . $value;
    }
    
    $signature = sha1($signature_string);
    $payment_data['signature'] = $signature;

    // Create payment request
    $request = array(
        'request' => $payment_data
    );

    // Send request to Fondy API
    $response = wp_remote_post('https://api.fondy.eu/api/checkout/url/', array(
        'body' => json_encode($request),
        'headers' => array('Content-Type' => 'application/json'),
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (isset($result['response']['checkout_url'])) {
        return $result['response']['checkout_url'];
    }

    return false;
}

// Add Store API support for custom checkout fields
add_filter('woocommerce_store_api_checkout_update_order_from_request', function($order, $request) {
    // Update phone number
    if (!empty($request['billing']['phone'])) {
        $order->update_meta_data('_billing_phone', $request['billing']['phone']);
    }
    
    // Add alternative phone as order note
    if (!empty($request['customer_note'])) {
        $order->add_order_note($request['customer_note'], 0, true);
    }
    
    return $order;
}, 10, 2);

// Add Store API endpoint for Fondy URL
add_action('rest_api_init', function() {
    register_rest_route('wc/store', '/checkout/payment-url', array(
        'methods' => 'GET',
        'callback' => function($request) {
            $order_id = $request->get_param('order_id');
            $payment_url = get_fondy_payment_url($order_id);
            
            if (!$payment_url) {
                return new WP_Error('no_url', 'Payment URL not found', array('status' => 404));
            }
            
            return array('payment_url' => $payment_url);
        },
        'permission_callback' => '__return_true'
    ));
});

// Enable CORS for Store API
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Content-Type');
        return $value;
    });
}, 15);

