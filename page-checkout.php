<?php
/**
 * Template Name: Checkout Page
 */

get_header(); ?>

<div class="content-area">
    <main id="main" class="site-main">
        <div class="custom-checkout-container">
            <?php
            if (WC()->cart->is_empty()) {
                echo '<p>თქვენი კალათა ცარიელია</p>';
                echo '<a href="' . get_permalink(wc_get_page_id('shop')) . '">დაბრუნდით მაღაზიაში</a>';
            } else {
                do_action('woocommerce_before_checkout_form');
                
                $checkout = WC()->checkout();
                ?>
                <form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
                    
                    <?php if ($checkout->get_checkout_fields()) : ?>
                        <div class="billing-form">
                            <h2>სააანგარიშსწორებო ინფორმაცია</h2>
                            <?php do_action('woocommerce_checkout_billing'); ?>
                            <?php do_action('woocommerce_checkout_shipping'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="payment-section">
                        <h2>გადახდის მეთოდი</h2>
                        <?php 
                        do_action('woocommerce_checkout_payment'); 
                        ?>
                    </div>

                </form>
                <?php do_action('woocommerce_after_checkout_form'); ?>
            <?php } ?>
        </div>
    </main>
</div>

<?php get_footer(); ?> 