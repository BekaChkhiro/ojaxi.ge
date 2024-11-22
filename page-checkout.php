<?php
/**
 * Template Name: Checkout Page
 */

get_header(); ?>

<div class="content-area">
    <main id="main" class="site-main">
        <div class="custom-checkout-container p-4">
            <?php
            if (WC()->cart->is_empty()) {
                // მადლობის გვერდის HTML
                ?>
                <div class="flex flex-col items-center justify-center p-8 text-center">
                    <div class="w-16 h-16 bg-[#1a691a] rounded-full flex items-center justify-center mb-6">
                        <svg 
                            class="w-8 h-8 text-white" 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path 
                                stroke-linecap="round" 
                                stroke-linejoin="round" 
                                stroke-width="2" 
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-semibold mb-4">მადლობა შეკვეთისთვის!</h2>
                    <p class="text-gray-600 mb-8">თქვენი შეკვეთა წარმატებით გაფორმდა</p>
            
                </div>
                <?php
                return;
            }
            
            do_action('woocommerce_before_checkout_form');
            $checkout = WC()->checkout();
            ?>
            <form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
                <?php if ($checkout->get_checkout_fields()) : ?>
                    <div class="billing-form">
                        <?php do_action('woocommerce_checkout_billing'); ?>
                    </div>
                <?php endif; ?>

                <div class="order-review">
                    <h2 class="mb-4">აირჩიეთ გადახდის მეთოდი</h2>
                    <?php do_action('woocommerce_checkout_order_review'); ?>
                </div>
            </form>
            <?php 
            add_action('wp_footer', function() {
                ?>
                <script>
                jQuery(document).ready(function($) {
                    $(document.body).on('checkout_error', function() {
                        window.parent.postMessage({ checkoutError: true }, '*');
                    });

                    $(document.body).on('order_created', function(event, order_id) {
                        window.parent.postMessage({ 
                            orderComplete: true,
                            orderId: order_id
                        }, '*');
                    });
                });
                </script>
                <?php
            });
            
            do_action('woocommerce_after_checkout_form'); 
            ?>
        </div>
    </main>
</div>

<?php get_footer(); ?> 