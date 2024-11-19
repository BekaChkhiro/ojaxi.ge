<?php
/**
 * Template Name: Checkout Page
 */

get_header(); ?>

<div class="content-area">
    <main id="main" class="site-main">
        <?php
        while ( have_posts() ) :
            the_post();
            do_action( 'woocommerce_before_main_content' );
            ?>
            <div class="entry-content">
                <?php the_content(); ?>
                <?php
                if ( function_exists( 'woocommerce_checkout' ) ) {
                    woocommerce_checkout();
                }
                ?>
            </div>
            <?php
            do_action( 'woocommerce_after_main_content' );
        endwhile;
        ?>
    </main>
</div>

<?php get_footer(); ?> 