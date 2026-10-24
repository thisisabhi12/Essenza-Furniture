<?php
/**
 * Essenza Furniture Luxury Theme - Footer Template
 *
 * @package Essenza_Theme
 */
?>

<!-- Minimal Australian Legal Footer -->
<footer class="essenza-footer">
    <div class="essenza-container">
        <div class="essenza-footer-grid">
            
            <!-- Brand Philosophy Column -->
            <div class="essenza-footer-brand">
                <h3>ESSENZA</h3>
                <p>Architectural furniture celebrating timeless Italian lines, honest natural materials, and transparent multi-origin fulfillment across Australia.</p>
                <div class="essenza-footer-abn">
                    <strong>ABN:</strong> 84 629 104 293 &bull; Registered for Australian GST
                </div>
            </div>

            <!-- Collections -->
            <div class="essenza-footer-col">
                <h4>Curated Collections</h4>
                <ul>
                    <li><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Complete Portfolio</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/#living' ) ); ?>">Living & Modular Seating</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/#dining' ) ); ?>">Dining Tables & Chairs</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/#bedroom' ) ); ?>">Architectural Bedroom</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/#custom' ) ); ?>">Custom Italian Pre-Orders</a></li>
                </ul>
            </div>

            <!-- Client Concierge & Sourcing Model -->
            <div class="essenza-footer-col">
                <h4>Client Concierge</h4>
                <ul>
                    <li><a href="<?php echo esc_url( home_url( '/#sourcing' ) ); ?>">Supply Chain Transparency</a></li>
                    <li><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">Track Split Shipments</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>">Freight & White Glove Delivery</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/trade-program/' ) ); ?>">Architect & Trade Program</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/admin-dashboard/' ) ); ?>" style="color:var(--color-primary);font-weight:600;">Admin Inventory Portal &rarr;</a></li>
                    <li><a href="mailto:concierge@essenzafurniture.com.au">Sydney Studio Consultation</a></li>
                </ul>
            </div>

            <!-- Australian Legal & Compliance -->
            <div class="essenza-footer-col">
                <h4>Australian Compliance</h4>
                <ul>
                    <li><a href="<?php echo esc_url( home_url( '/terms-conditions/' ) ); ?>">Terms & Conditions</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy (AU Privacy Act)</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/warranty-returns/' ) ); ?>">Consumer Guarantees & Returns</a></li>
                    <li><a href="<?php echo esc_url( home_url( '/biosecurity/' ) ); ?>">Timber Import & Biosecurity Compliance</a></li>
                </ul>
            </div>

        </div>

        <!-- Footer Bottom Bar -->
        <div class="essenza-footer-bottom">
            <div>
                &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Essenza Furniture Pty Ltd. All Rights Reserved. Prices in AUD include 10% Australian GST.
            </div>
            
            <!-- Australian Payment Badges -->
            <div class="essenza-payment-badges">
                <span class="essenza-badge-pill">Stripe Secure</span>
                <span class="essenza-badge-pill">Apple Pay</span>
                <span class="essenza-badge-pill">Google Pay</span>
                <span class="essenza-badge-pill">Afterpay (AUD)</span>
                <span class="essenza-badge-pill">Zip Pay</span>
            </div>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
