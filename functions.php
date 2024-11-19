<?php

function load_react_styles_scripts() {
    // CSS
    wp_enqueue_style('tailwind-styles', get_theme_file_uri('/build/index.css'));
    
    // JavaScript
    wp_enqueue_script('react', 'https://unpkg.com/react@18/umd/react.development.js', array(), '18', true);
    wp_enqueue_script('react-dom', 'https://unpkg.com/react-dom@18/umd/react-dom.development.js', array('react'), '18', true);
    wp_enqueue_script('react-app', get_theme_file_uri('/build/index.js'), array('react', 'react-dom'), '1.0', true);
    
    wp_localize_script('react-app', 'wpApiSettings', array(
        'root' => esc_url_raw(rest_url()),
        'nonce' => wp_create_nonce('wp_rest')
    ));
}
add_action('wp_enqueue_scripts', 'load_react_styles_scripts');

function add_google_verification() {
    echo '<meta name="google-site-verification" content="PJVPfFFKyO0MVEry9TD8Rk_mP2BWIaR0qDGlG09IH3A" />' . "\n";
}
add_action('wp_head', 'add_google_verification');

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
    if ($template === 'single-product.php') {
        return array(get_theme_file_path('react-template.php'));
    }
    return $files;
}, 10, 2);

// React-ის თემფლეითის ჩატვირთვა
function load_react_template($template) {
    global $post;
    
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