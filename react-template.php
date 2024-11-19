<?php
/**
 * Template Name: React Template
 */
global $post;
$product = wc_get_product($post->ID);

// მივიღოთ პროდუქტის მონაცემები
$product_data = array(
    'productId' => $post->ID,
    'productSlug' => $post->post_name
);
?>

<head>
    <?php if ($product): ?>
        <title><?php echo esc_html($product->get_name()); ?> - <?php bloginfo('name'); ?></title>
    <?php endif; ?>
    <script>
        // გადავცეთ პროდუქტის მონაცემები React აპლიკაციას
        window.initialProductData = <?php echo json_encode($product_data); ?>;
    </script>
</head>
<?php  wp_head();
    get_header();
?>

<div id="root"></div>

<?php get_footer(); ?>