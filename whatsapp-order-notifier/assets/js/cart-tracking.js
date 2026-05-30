/**
 * WhatsApp Order Notifier - Cart Tracking
 *
 * Captures customer information during checkout for
 * abandoned cart notification functionality.
 *
 * @package suspended_Order_Notifier
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const CartTracker = {
        debounceTimer: null,
        trackedFields: ['billing_email', 'billing_phone', 'billing_first_name', 'billing_last_name'],

        init: function() {
            if (typeof wonCartTracking === 'undefined') return;
            this.bindEvents();
        },

        bindEvents: function() {
            const self = this;

            // Track field changes with debouncing
            this.trackedFields.forEach(function(field) {
                $(document).on('change blur', '#' + field, function() {
                    self.trackField(field, $(this).val());
                });

                // Also track on keyup with debounce for email/phone
                if (field === 'billing_email' || field === 'billing_phone') {
                    $(document).on('keyup', '#' + field, function() {
                        const value = $(this).val();
                        clearTimeout(self.debounceTimer);
                        self.debounceTimer = setTimeout(function() {
                            if (self.isValidField(field, value)) {
                                self.trackField(field, value);
                            }
                        }, 1500);
                    });
                }
            });
        },

        trackField: function(field, value) {
            if (!value || !this.isValidField(field, value)) return;

            const data = {
                action: 'won_track_checkout_field',
                nonce: wonCartTracking.nonce,
                field: field,
                value: value
            };

            // For name fields, include both
            if (field === 'billing_first_name' || field === 'billing_last_name') {
                data.first_name = $('#billing_first_name').val() || '';
                data.last_name = $('#billing_last_name').val() || '';
            }

            $.post(wonCartTracking.ajaxUrl, data);
        },

        isValidField: function(field, value) {
            if (!value || value.trim() === '') return false;

            if (field === 'billing_email') {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            }

            if (field === 'billing_phone') {
                return value.replace(/[^0-9]/g, '').length >= 7;
            }

            return value.trim().length >= 2;
        }
    };

    $(document).ready(function() {
        CartTracker.init();
    });

})(jQuery);
