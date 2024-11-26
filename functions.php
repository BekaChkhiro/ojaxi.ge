<?php
function load_react_styles_scripts() {
    wp_enqueue_style('tailwind-styles', get_theme_file_uri('/build/index.css'));
    
    wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.development.js', array(), '18', true);
    wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.development.js', array('react'), '18', true);
    wp_enqueue_script('react-app', get_theme_file_uri('/build/index.js'), array('react', 'react-dom'), '1.0', true);
    
    wp_localize_script('react-app', 'wpApiSettings', array(
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
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
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
        
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

// Add Store API nonce to React app
function add_wc_store_api_nonce() {
    wp_localize_script('react-app', 'wcStoreApiSettings', array(
        'nonce' => wp_create_nonce('wc_store_api')
    ));
}
add_action('wp_enqueue_scripts', 'add_wc_store_api_nonce');

// Allow Store API access
add_filter('woocommerce_rest_check_permissions', function($permission, $context, $object_id, $post_type){
    if (strpos($_SERVER['REQUEST_URI'], '/wc/store/') !== false) {
        return true;
    }
    return $permission;
}, 10, 4);

add_action('wp_enqueue_scripts', function() {
    wp_localize_script('react-app', 'wcStoreApiSettings', array(
        'nonce' => wp_create_nonce('wc_store_api')
    ));
});

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

// დავამატოთ JavaScript-ი ლოკაიზაცია
add_action('wp_enqueue_scripts', function() {
    wp_localize_script('react-app', 'wcCheckout', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('clear_cart_nonce')
    ));
});

// დავამატოთ მობაილ ვერსიისთვის საჭირო JavaScript
add_action('wp_footer', function() {
    if (is_checkout()) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // მობაილ ვერსიაში ღილაკებზე დაჭერის ჰენდლერი
            function handleMobileButtons() {
                $(document).on('click touchstart', 'button[type="submit"], input[type="submit"]', function(e) {
                    // თუ ღილაკი disabled არის, გავაუქმოთ ქმედება
                    if ($(this).prop('disabled')) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // დავამატოთ active კლასი
                    $(this).addClass('button-active');
                });

                // თითის აღების შემთხვევაში მოვაშოროთ active კლასი
                $(document).on('touchend', 'button[type="submit"], input[type="submit"]', function() {
                    $(this).removeClass('button-active');
                });
            }

            handleMobileButtons();

            // დავამატოთ სტილები მობაილ ვერსიისთვის
            $('<style>')
                .prop('type', 'text/css')
                .html(`
                    @media (max-width: 768px) {
                        button[type="submit"],
                        input[type="submit"] {
                            -webkit-appearance: none;
                            -webkit-tap-highlight-color: transparent;
                            cursor: pointer;
                            touch-action: manipulation;
                        }
                        
                        .button-active {
                            opacity: 0.8;
                            transform: scale(0.98);
                        }
                    }
                `)
                .appendTo('head');
        });
        </script>
        <?php
    }
}, 99);

// დავამატოთ ფილტრი რომ გავაუქმოთ ავტომატური disabled ატრიბუტი
add_filter('woocommerce_order_button_html', function($button_html) {
    return str_replace('disabled', '', $button_html);
}, 10, 1);

// შევცვალოთ ღილაკის HTML სტრუქტურა მობაილისთვის
add_filter('woocommerce_order_button_html', function($button_html) {
    return str_replace(
        'class="button alt"',
        'class="button alt mobile-friendly-button"',
        $button_html
    );
}, 20, 1);

// დავამატოთ CSS სტილები
add_action('wp_head', function() {
    if (is_checkout()) {
        ?>
        <style>
            @media (max-width: 768px) {
                .mobile-friendly-button {
                    width: 100%;
                    padding: 15px !important;
                    font-size: 16px !important;
                    -webkit-appearance: none !important;
                    border-radius: 4px !important;
                    margin-top: 10px !important;
                }

                /* გავაუმჯობესოთ ღილაკზე დაჭერის ვიზუალი */
                .mobile-friendly-button:active {
                    transform: scale(0.98);
                    transition: transform 0.1s ease;
                }

                /* Safari-სთვის სპეციფიური ფიქსები */
                input[type="submit"],
                button[type="submit"] {
                    -webkit-appearance: none;
                    -webkit-border-radius: 4px;
                }
            }
        </style>
        <?php
    }
}, 999);

// დავამატოთ მობაილ-სპეციფიური ფიქსები
add_action('wp_head', function() {
    if (is_checkout()) {
        ?>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <script>
        // ვცადოთ scroll-ის დაფიქსვა
        document.addEventListener('touchmove', function(e) {
            if (e.target.closest('.f-card') || e.target.closest('.f-button-pay')) {
                e.stopPropagation();
            }
        }, { passive: false });

        // დავაფიქსიროთ iOS-ზე ღილაკების პრობლემები
        document.addEventListener('DOMContentLoaded', function() {
            if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                document.querySelectorAll('button, input[type="submit"]')
                    .forEach(button => {
                        button.addEventListener('touchend', function(e) {
                            e.preventDefault();
                            this.click();
                        });
                    });
            }
        });
        </script>
        <?php
    }
}, 5);

// დავამატოთ CORS headers გადახდის სისტემისთვის
add_action('init', function() {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
});

// დავამატოთ Google Pay-ს მხარდაჭერა მობაილისთვის
add_action('wp_head', function() {
    if (is_checkout()) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // შევამოწმოთ არის თუ არა Google Pay ხელმისაწვდომი
            if (window.PaymentRequest) {
                const supportedInstruments = [{
                    supportedMethods: 'https://google.com/pay',
                    data: {
                        environment: 'TEST',
                        apiVersion: 2,
                        apiVersionMinor: 0,
                        merchantInfo: {
                            merchantId: '1551317',
                            merchantName: 'Ojaxi.ge'
                        },
                        allowedPaymentMethods: [{
                            type: 'CARD',
                            parameters: {
                                allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                                allowedCardNetworks: ['VISA', 'MASTERCARD']
                            }
                        }]
                    }
                }];

                // მობაილზე Google Pay-ს ღილაკის დამუშავება
                document.querySelector('.button-pay-wallet-inner_btn_uc8mB').addEventListener('click', function(e) {
                    if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                        e.preventDefault();
                        
                        const paymentRequest = new PaymentRequest(
                            supportedInstruments,
                            {
                                total: {
                                    label: 'Total',
                                    amount: {
                                        currency: 'GEL',
                                        value: '<?php echo WC()->cart->total; ?>'
                                    }
                                }
                            }
                        );

                        paymentRequest.show()
                            .then(function(paymentResponse) {
                                // გადახდის დამუშავება
                                return paymentResponse.complete('success');
                            })
                            .catch(function(err) {
                                console.error('Payment failed:', err);
                            });
                    }
                });
            }
        });
        </script>
        <?php
    }
}, 999);

// დავამატოთ Google Pay-ს კონფიგურაცია
add_action('wp_footer', function() {
    if (is_checkout()) {
        ?>
        <script>
        window.googlePayConfig = {
            environment: 'TEST',
            merchantId: '1551317',
            merchantName: 'Ojaxi.ge',
            buttonColor: 'black',
            buttonType: 'long'
        };
        </script>
        <?php
    }
}, 10);

add_action('wp_head', function() {
    if (is_checkout()) {
        ?>
        <style>
            @media (max-width: 768px) {
                .button-pay-wallet-inner_btn_uc8mB {
                    width: 100% !important;
                    height: 48px !important;
                    margin-bottom: 15px !important;
                    border-radius: 4px !important;
                    overflow: hidden !important;
                }

                .button-pay-wallet-inner_iframe_lQcdp {
                    width: 100% !important;
                    height: 100% !important;
                    pointer-events: none !important;
                }

                .button-pay-wallet-inner_click_QLqcd {
                    position: absolute !important;
                    top: 0 !important;
                    left: 0 !important;
                    width: 100% !important;
                    height: 100% !important;
                    cursor: pointer !important;
                }
            }
        </style>
        <?php
    }
}, 999);

// დავამატოთ საჭირო iframe permissions policy headers
add_action('send_headers', function() {
    if (is_checkout()) {
        header('Permissions-Policy: payment=*');
        header('Cross-Origin-Embedder-Policy: require-corp');
        header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    }
});

// შევცვალოთ Google Pay-ს კონფიგურაცია
add_action('wp_footer', function() {
    if (is_checkout()) {
        ?>
        <script>
        // Google Pay-ს ინიციალიზაცია
        let googlePayClient;

        function initGooglePay() {
            if (!window.google || !window.google.payments) {
                console.error('Google Pay API not loaded');
                return;
            }

            googlePayClient = new google.payments.api.PaymentsClient({
                environment: 'TEST', // TEST ან PRODUCTION
                paymentDataCallbacks: {
                    onPaymentAuthorized: onPaymentAuthorized
                }
            });

            const button = googlePayClient.createButton({
                onClick: onGooglePayButtonClicked,
                allowedPaymentMethods: [{
                    type: 'CARD',
                    parameters: {
                        allowedAuthMethods: ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                        allowedCardNetworks: ['VISA', 'MASTERCARD']
                    }
                }]
            });

            // ჩავანაცვლოთ არსებული ღილაკი
            const container = document.querySelector('.button-pay-wallet-inner_btn_uc8mB');
            if (container) {
                container.innerHTML = '';
                container.appendChild(button);
            }
        }

        async function onGooglePayButtonClicked() {
            try {
                const paymentData = await googlePayClient.loadPaymentData({
                    merchantInfo: {
                        merchantId: '1551317',
                        merchantName: 'Ojaxi.ge'
                    },
                    transactionInfo: {
                        totalPriceStatus: 'FINAL',
                        totalPrice: '<?php echo WC()->cart->total; ?>',
                        currencyCode: 'GEL'
                    },
                    callbackIntents: ['PAYMENT_AUTHORIZATION']
                });
                
                // გადახდის დამუშავება
                processPayment(paymentData);
            } catch (err) {
                console.error(err);
            }
        }

        function onPaymentAuthorized(paymentData) {
            return new Promise(function(resolve, reject) {
                // აქ დაამუშავეთ გადახდის ავტორიზაცია
                processPayment(paymentData)
                    .then(() => resolve({transactionState: 'SUCCESS'}))
                    .catch(() => reject());
            });
        }

        async function processPayment(paymentData) {
            // აქ დაამატეთ გადახდის დამუშავების ლოგიკა
            console.log('Processing payment:', paymentData);
        }

        // ვიტვირთავთ Google Pay SDK-ს
        const script = document.createElement('script');
        script.src = 'https://pay.google.com/gp/p/js/pay.js';
        script.onload = initGooglePay;
        document.body.appendChild(script);
        </script>
        <?php
    }
}, 99);

// დავამატოთ საჭირო მეტა თეგები
add_action('wp_head', function() {
    if (is_checkout()) {
        ?>
        <meta name="google-site-verification" content="YOUR_VERIFICATION_CODE">
        <meta name="google-pay-api-version" content="2">
        <meta name="google-pay-merchant-id" content="1551317">
        <?php
    }
}, 1);

