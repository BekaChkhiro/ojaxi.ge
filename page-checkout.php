<?php
/**
 * Template Name: Checkout Page
 */
get_header(); 
?>
<div class="content-area">
    <main id="main" class="site-main">
        <?php
        while ( have_posts() ) :
            the_post();
            
            if ( function_exists( 'woocommerce_content' ) ) {
                // Display WooCommerce checkout
                echo do_shortcode('[woocommerce_checkout]');
            }
            
        endwhile;
        ?>
    </main>
</div>
<?php
get_footer();
?>