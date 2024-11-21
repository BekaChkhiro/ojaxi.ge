<?php
/**
 * Template Name: Checkout Page
 */

get_header(); ?>

<div class="content-area">
    <main id="main" class="site-main">
        <div class="custom-checkout-container">
            <?php
            // ბილინგის ფორმა
            ?>
            <div class="billing-form">
                <h2>სააანგარიშსწორებო ინფორმაცია</h2>
                <form id="billing-form" method="post">
                    <div class="form-row">
                        <label for="billing_first_name">სახელი და გვარი *</label>
                        <input type="text" id="billing_first_name" name="billing_first_name" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="billing_phone">ტელეფონი *</label>
                        <input type="tel" id="billing_phone" name="billing_phone" required>
                    </div>

                    <div class="form-row">
                        <label for="billing_phone_alt">ალტერნატიული ტელეფონი</label>
                        <input type="tel" id="billing_phone_alt" name="billing_phone_alt">
                    </div>

                    <div class="form-row">
                        <label for="billing_email">ელ-ფოსტა *</label>
                        <input type="email" id="billing_email" name="billing_email" required>
                    </div>

                    <div class="form-row">
                        <label for="billing_city">ქალაქი *</label>
                        <input type="text" id="billing_city" name="billing_city" required>
                    </div>

                    <div class="form-row">
                        <label for="billing_address">მისამართი *</label>
                        <input type="text" id="billing_address" name="billing_address" required>
                    </div>
                </form>
            </div>

            <?php
            // გადახდის მეთოდები
            ?>
            <div class="payment-methods">
                <h2>გადახდის მეთოდები</h2>
                <div class="payment-method">
                    <input type="radio" id="pay_card" name="payment_method" value="card">
                    <label for="pay_card">ბარათით გადახდა</label>
                </div>
                <div class="payment-method">
                    <input type="radio" id="pay_transfer" name="payment_method" value="transfer">
                    <label for="pay_transfer">საბანკო გადარიცხვა</label>
                </div>
            </div>

            <button type="submit" class="checkout-submit">შეკვეთის განთავსება</button>
        </div>
    </main>
</div>

<?php get_footer(); ?> 