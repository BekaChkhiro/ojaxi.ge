<?php
// გათამაშების პოსტ ტაიპის რეგისტრაცია
add_action('init', function() {
    register_post_type('gatashoreba', array(
        'labels' => array(
            'name' => 'გათამაშებები',
            'singular_name' => 'გათამაშება',
            'add_new' => 'ახალი გათამაშება',
            'add_new_item' => 'ახალი გათამაშების დამატება',
            'edit_item' => 'გათამაშების რედაქტირება',
        ),
        'public' => true,
        'menu_icon' => 'dashicons-tickets-alt',
        'supports' => array('custom-fields'),
        'show_in_rest' => true
    ));
});

// მეტა ველების დამატება
add_action('add_meta_boxes', function() {
    add_meta_box(
        'gatashoreba_details',
        'მონაწილის დეტალები',
        'render_gatashoreba_fields',
        'gatashoreba',
        'normal',
        'high'
    );
});

// მელეფონის ნომრის ფორმატირება ადმინ პანელისთვის
function format_phone_for_display($phone) {
    // მოვაშოროთ ყველა არაციფრული სიმბოლო
    $numbers_only = preg_replace('/[^0-9]/', '', $phone);
    
    // თუ იწყება 995-ით, მოვაშოროთ
    if (strpos($numbers_only, '995') === 0) {
        $numbers_only = substr($numbers_only, 3);
    }
    
    // თავფორმატოთ 9 ციფრიანი ნომერი
    if (strlen($numbers_only) === 9) {
        return '+995 ' . substr($numbers_only, 0, 3) . ' ' . 
               substr($numbers_only, 3, 2) . ' ' . 
               substr($numbers_only, 5, 2) . ' ' . 
               substr($numbers_only, 7, 2);
    }
    
    return $phone;
}

// ადმინ პანელში ქოლუმების მართვა
add_filter('manage_gatashoreba_posts_columns', function($columns) {
    $new_columns = array();
    $new_columns['cb'] = $columns['cb'];
    $new_columns['title'] = 'ID';
    $new_columns['phone'] = 'ტელეფონი';
    $new_columns['name'] = 'სახელი გვარი';
    $new_columns['date'] = $columns['date'];
    return $new_columns;
});

add_action('manage_gatashoreba_posts_custom_column', function($column, $post_id) {
    switch ($column) {
        case 'phone':
            $phone = get_post_meta($post_id, '_phone', true);
            echo format_phone_for_display($phone);
            break;
        case 'name':
            $first_name = get_post_meta($post_id, '_first_name', true);
            $last_name = get_post_meta($post_id, '_last_name', true);
            echo $first_name . ' ' . $last_name;
            break;
    }
}, 10, 2);

// მეტა ველების რენდერი
function render_gatashoreba_fields($post) {
    $phone = get_post_meta($post->ID, '_phone', true);
    $first_name = get_post_meta($post->ID, '_first_name', true);
    $last_name = get_post_meta($post->ID, '_last_name', true);
    
    wp_nonce_field('gatashoreba_nonce', 'gatashoreba_nonce');
    ?>
    <style>
        .gatashoreba-field { margin-bottom: 15px; }
        .gatashoreba-field label { display: block; margin-bottom: 5px; }
        .gatashoreba-field input { width: 100%; }
    </style>
    
    <div class="gatashoreba-field">
        <label for="phone">ტელეფონი:</label>
        <input type="tel" id="phone" name="phone" value="<?php echo esc_attr(format_phone_for_display($phone)); ?>">
    </div>
    
    <div class="gatashoreba-field">
        <label for="first_name">სახელი:</label>
        <input type="text" id="first_name" name="first_name" value="<?php echo esc_attr($first_name); ?>">
    </div>
    
    <div class="gatashoreba-field">
        <label for="last_name">გვარი:</label>
        <input type="text" id="last_name" name="last_name" value="<?php echo esc_attr($last_name); ?>">
    </div>
    <?php
}

// მეტა ველების შენახვა
add_action('save_post_gatashoreba', function($post_id) {
    if (!isset($_POST['gatashoreba_nonce']) || !wp_verify_nonce($_POST['gatashoreba_nonce'], 'gatashoreba_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    $fields = array('phone', 'first_name', 'last_name');
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        }
    }
    
    // სათაურის ავტომატური გენერაცია
    $title = 'Ojaxi#' . $post_id;
    
    // გამოვრთოთ save_post_gatashoreba hook-ის გამოძახება
    remove_action('save_post_gatashoreba', 'wp_insert_post');
    
    wp_update_post(array(
        'ID' => $post_id,
        'post_title' => $title
    ));
    
    // დავაბრუნოთ hook
    add_action('save_post_gatashoreba', 'wp_insert_post');
});

// მეტა ველების რეგისტრაცია REST API-სთვის
add_action('rest_api_init', function() {
    register_rest_field('gatashoreba', 'meta', array(
        'get_callback' => function($post) {
            return get_post_meta($post['id']);
        },
        'update_callback' => function($meta, $post) {
            foreach ($meta as $key => $value) {
                update_post_meta($post->ID, $key, $value);
            }
        }
    ));
});

// ტელეფონის ნომრის ნორმალიზაცია (მხოლოდ 9 ციფრიანი ნომერი)
function normalize_phone_number($phone) {
    // მოვაშოროთ ყველა არაციფრული სიმბოლო
    $numbers_only = preg_replace('/[^0-9]/', '', $phone);
    
    // თუ იწყება 995-ით, მოვაშოროთ
    if (strpos($numbers_only, '995') === 0) {
        $numbers_only = substr($numbers_only, 3);
    }
    
    // თუ იწყება 5-ით და არის 9 ციფრა, დავაბრუნოთ
    if (strlen($numbers_only) === 9 && $numbers_only[0] === '5') {
        return $numbers_only;
    }
    
    // თუ იწყება 0-ით (მაგ: 0555...), მოვაშოროთ 0
    if (strlen($numbers_only) === 10 && $numbers_only[0] === '0') {
        return substr($numbers_only, 1);
    }
    
    return $numbers_only;
}

// REST API-ით შექმნილი პოსტების ვალიდაცია
add_action('rest_pre_insert_gatashoreba', function($prepared_post, $request) {
    $meta = $request->get_param('meta');
    $phone = isset($meta['_phone']) ? $meta['_phone'] : '';
    
    // ნომრის ნორმალიზაცია
    $normalized_phone = normalize_phone_number($phone);
    
    // შევამოწმოთ არსებობს თუ არა უკვე ეს ნომერი
    $existing_posts = get_posts(array(
        'post_type' => 'gatashoreba',
        'meta_query' => array(
            array(
                'key' => '_phone_normalized',
                'value' => $normalized_phone
            )
        ),
        'posts_per_page' => 1
    ));

    if (!empty($existing_posts)) {
        return new WP_Error(
            'phone_exists',
            'ეს ნომერი უკვე დარეგისტრირებულია',
            array('status' => 400)
        );
    }

    // დავამატოთ ნორმალიზებული ნომერი მეტა მონაცემებში
    $meta['_phone_normalized'] = $normalized_phone;
    $request->set_param('meta', $meta);

    return $prepared_post;
}, 10, 2);

// REST API-ით შექმნილი პოსტების სათაურის გენერაცია
add_action('rest_after_insert_gatashoreba', function($post, $request, $creating) {
    if ($creating) {
        $title = 'Ojaxi#' . $post->ID;
        
        wp_update_post(array(
            'ID' => $post->ID,
            'post_title' => $title
        ));
    }
}, 10, 3);
?> 