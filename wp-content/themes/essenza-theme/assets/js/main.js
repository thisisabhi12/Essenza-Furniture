/**
 * Essenza Furniture Luxury Theme - Main JavaScript Controller
 * Handles off-canvas cart interactions, WooCommerce AJAX hooks, and drawer animations.
 */

(function($) {
    'use strict';

    const EssenzaTheme = {
        init: function() {
            this.cacheDOM();
            this.bindEvents();
        },

        cacheDOM: function() {
            this.$body = $('body');
            this.$cartToggle = $('#essenza-cart-toggle');
            this.$cartDrawer = $('#essenza-cart-drawer');
            this.$drawerBackdrop = $('#essenza-drawer-backdrop');
            this.$drawerClose = $('#essenza-drawer-close');
        },

        bindEvents: function() {
            const self = this;

            // 1. Open drawer on cart toggle click
            this.$cartToggle.on('click', function(e) {
                e.preventDefault();
                self.openDrawer();
            });

            // 2. Close drawer on close button click
            this.$drawerClose.on('click', function(e) {
                e.preventDefault();
                self.closeDrawer();
            });

            // 3. Close drawer on backdrop click
            this.$drawerBackdrop.on('click', function(e) {
                e.preventDefault();
                self.closeDrawer();
            });

            // 4. Close drawer on ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.$cartDrawer.hasClass('is-active')) {
                    self.closeDrawer();
                }
            });

            // 5. AUTO-OPEN DRAWER ON WOOCOMMERCE AJAX "added_to_cart" EVENT
            $(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
                self.openDrawer();
            });

            // 6. Handle mini-cart remove item AJAX
            $(document.body).on('removed_from_cart', function() {
                // WooCommerce automatically refreshes fragments
            });
        },

        openDrawer: function() {
            this.$cartDrawer.addClass('is-active');
            this.$drawerBackdrop.addClass('is-active');
            this.$body.css('overflow', 'hidden');
        },

        closeDrawer: function() {
            this.$cartDrawer.removeClass('is-active');
            this.$drawerBackdrop.removeClass('is-active');
            this.$body.css('overflow', '');
        }
    };

    $(document).ready(function() {
        EssenzaTheme.init();
    });

})(jQuery);
