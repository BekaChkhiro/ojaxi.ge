<?php
/**
 * Template Name: React Template
 */

// Redirect to default template if this is a checkout or order-pay page
if (is_checkout() || is_order_pay_page()) {
    return include(WC()->plugin_path() . '/templates/checkout/form-checkout.php');
}

global $post;
$product = wc_get_product($post->ID);

// Get product data
$product_data = array(
    'productId' => $post->ID,
    'productSlug' => $post->post_name
);

get_header();
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($product): ?>
        <title><?php echo esc_html($product->get_name()); ?> - <?php bloginfo('name'); ?></title>
    <?php endif; ?>
    <?php wp_head(); ?>
    <script>
        window.initialProductData = <?php echo json_encode($product_data); ?>;
    </script>
</head>
<body <?php body_class(); ?>>
    <?php wp_body_open(); ?>
    
    <div id="root"></div>
    
    <?php wp_footer(); ?>
</body>
</html>

<?php get_footer(); ?>