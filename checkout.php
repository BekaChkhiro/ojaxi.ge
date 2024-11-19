<?php
/**
 * Template Name: Checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header('shop');

while (have_posts()) :
    the_post();
    do_action('woocommerce_checkout');
endwhile;

get_footer('shop'); 