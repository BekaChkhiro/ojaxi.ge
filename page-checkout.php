<?php
/**
 * Template Name: Checkout Page
 */

get_header(); 

// დავამატოთ სტილები
add_action('wp_head', function() {
    if (is_checkout()) {
        ?>
        <style>
            .custom-checkout-container {
                padding: 1rem;
                margin: 0 auto;
                width: 100%;
            }

            @media screen and (min-width: 768px) {
                .custom-checkout-container {
                    max-width: 600px;
                }
            }
        </style>
        <?php
    }
});
?>

<div class="content-area">
    <main id="main" class="site-main">
        <div class="custom-checkout-container">
            <div class="flex justify-center mb-8">
                <a href="/">
                <img 
                    src="<?php echo get_theme_file_uri('/build/images/ojaxi_logo.33140913.webp'); ?>" 
                    alt="Ojaxi Logo"
                    style="width: 100px;"
                />
                </a>
                
            </div>
            
            <?php
            // დავამატოთ მობილური დეტექციის ლოგიკა
            $is_mobile = wp_is_mobile();
            
            if (WC()->cart->is_empty() && !$is_mobile) {
                // მადლობის გვერდის HTML მხოლოდ დესკტოპზე
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
                <script>
                    setTimeout(function() {
                        window.top.location.href = '<?php echo home_url(); ?>';
                    }, 3000);
                </script>
                <?php
                return;
            }
            
            // მობილურზე გადამისამართება მთავარ გვერდზე
            if (WC()->cart->is_empty() && $is_mobile) {
                ?>
                <script>
                    window.location.href = '<?php echo home_url(); ?>';
                </script>
                <?php
                return;
            }
            
            // დავამატოთ კალათის განახლების ლოგიკა
            add_action('wp_head', function() {
                if (is_checkout()) {
                    WC()->cart->calculate_totals();
                    ?>
                    <script>
                        // კალათის განახლება გვერდის ჩატვირთვისას
                        document.addEventListener('DOMContentLoaded', function() {
                            if (typeof wc_checkout_params !== 'undefined') {
                                jQuery(function($) {
                                    // ძალით განვაახლოთ კალათის მონაცემები
                                    $('body').trigger('update_checkout');
                                    
                                    // დავაფიქსიროთ კალათის განახლება
                                    $(document.body).on('updated_checkout', function() {
                                        // გადავამოწმოთ თანხა განახლების შემდეგ
                                        if ($('.order-total .amount').length === 0) {
                                            location.reload();
                                        }
                                    });
                                });
                            }
                        });
                    </script>
                    <?php
                }
            }, 5);
            
            // შევცვალოთ ჩექაუთის ფორმის გამოტანის ლოგიკა
            if (!WC()->cart->is_empty()) {
                do_action('woocommerce_before_checkout_form');
                $checkout = WC()->checkout();
                ?>
                <form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url(wc_get_checkout_url()); ?>" enctype="multipart/form-data">
                    <?php if ($checkout->get_checkout_fields()) : ?>
                        <div class="billing-form mb-8">
                            <?php do_action('woocommerce_checkout_billing'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="order-review">
                        <div class="total-row flex justify-between items-center">
                            <span class="total-label">სულ ჯამი:</span>
                            <span class="total-amount"><?php echo number_format(WC()->cart->get_total('edit'), 2); ?> ₾</span>
                        </div>
                    </div>

                    <div id="payment" class="woocommerce-checkout-payment">
                        <div class="payment-section mt-8">
                            <h2 class="mb-4">აირჩიეთ გადახდის მეთოდი</h2>
                            <?php 
                            // გადახდის მეთოდების გამოტანა
                            do_action('woocommerce_checkout_payment'); 
                            ?>
                        </div>
                    </div>
                </form>
                <?php
            } else {
                // თუ კალათა ცარიელია, დავაბრუნოთ მთავარ გვერდზე
                wp_redirect(home_url());
                exit;
            }
            
            add_action('wp_footer', function() {
                ?>
                <script>
                jQuery(document).ready(function($) {
                    $(document.body).on('checkout_error', function() {
                        window.parent.postMessage({ checkoutError: true }, '*');
                    });

                    $(document.body).on('order_created', function(event, order_id) {
                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            type: 'POST',
                            data: {
                                action: 'clear_cart_after_order',
                                order_id: order_id,
                                nonce: '<?php echo wp_create_nonce('clear_cart_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    // შევატყობინოთ React აპლიკაციას
                                    window.parent.postMessage({ 
                                        orderComplete: true,
                                        orderId: order_id
                                    }, '*');
                                    
                                    // დავარეფრეშოთ გვერდი მცირე დაყოვნების შემდეგ
                                    setTimeout(function() {
                                        window.location.reload();
                                    }, 500);
                                }
                            }
                        });
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