/**
 * WhatsApp Order Notifier - Admin JavaScript
 *
 * Handles all admin dashboard interactions, API calls,
 * settings management, and UI state.
 *
 * @package suspended_Order_Notifier
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const WON = {
        /**
         * Initialize the admin module.
         */
        init: function() {
            this.initTheme();
            this.bindEvents();
            this.loadDashboardData();
            this.initSettingsTabs();
            this.initTemplateTabs();
            this.initLogs();
        },

        /**
         * Initialize dark mode theme.
         */
        initTheme: function() {
            const darkMode = wonAdmin.darkMode || 'auto';
            const app = document.querySelector('.won-app');
            if (!app) return;

            if (darkMode === 'dark') {
                document.body.classList.add('won-dark-mode');
            } else if (darkMode === 'auto') {
                app.setAttribute('data-theme', 'auto');
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.body.classList.add('won-dark-mode');
                }
            }
        },

        /**
         * Bind all event handlers.
         */
        bindEvents: function() {
            // Theme toggle
            $(document).on('click', '#won-theme-toggle', this.toggleTheme.bind(this));

            // Send test message
            $(document).on('click', '#won-send-test', this.sendTestMessage.bind(this));

            // Test connection
            $(document).on('click', '#won-test-connection', this.testConnection.bind(this));

            // Save settings
            $(document).on('click', '#won-save-api-settings', this.saveSettings.bind(this));
            $(document).on('click', '#won-save-notification-settings', this.saveSettings.bind(this));
            $(document).on('click', '#won-save-advanced-settings', this.saveSettings.bind(this));

            // Provider selection
            $(document).on('change', 'input[data-setting="api_provider"]', this.switchProvider.bind(this));

            // Template save
            $(document).on('click', '#won-save-template', this.saveTemplate.bind(this));
            $(document).on('click', '#won-preview-template', this.previewTemplate.bind(this));
            $(document).on('click', '#won-reset-template', this.resetTemplate.bind(this));

            // Placeholder insertion
            $(document).on('click', '.won-placeholder-tag', this.insertPlaceholder.bind(this));

            // Chart period change
            $(document).on('change', '#won-chart-period', this.updateChart.bind(this));

            // Log filters
            $(document).on('click', '#won-log-filter', this.filterLogs.bind(this));

            // Resend notification
            $(document).on('click', '.won-resend-btn', this.resendNotification.bind(this));

            // Settings tabs
            $(document).on('click', '.won-settings-nav__item', this.switchSettingsTab.bind(this));
        },

        /**
         * Toggle dark/light theme.
         */
        toggleTheme: function(e) {
            e.preventDefault();
            document.body.classList.toggle('won-dark-mode');
            const isDark = document.body.classList.contains('won-dark-mode');
            this.apiCall('settings', 'POST', { dark_mode: isDark ? 'dark' : 'light' });
        },


        /**
         * Load dashboard statistics and recent logs.
         */
        loadDashboardData: function() {
            if (!$('#won-stats').length) return;

            this.apiCall('stats?period=today', 'GET').then(function(data) {
                if (data && data.notifications) {
                    $('#stat-total').text(data.notifications.total || 0);
                    $('#stat-sent').text(data.notifications.sent || 0);
                    $('#stat-failed').text(data.notifications.failed || 0);
                    $('#stat-pending').text(data.queue ? data.queue.queued : 0);
                    $('#stat-rate').text(data.notifications.success_rate + '%');
                    $('#stat-orders').text(data.notifications.orders || 0);
                    $('#stat-stock').text(data.notifications.stock_alerts || 0);
                    $('#stat-refunds').text(data.notifications.refunds || 0);
                    $('#stat-abandoned').text(data.notifications.abandoned_carts || 0);
                }
            });

            this.loadRecentLogs();
            this.loadChart(7);
        },

        /**
         * Load recent notifications for dashboard.
         */
        loadRecentLogs: function() {
            if (!$('#won-recent-logs').length) return;

            this.apiCall('logs?per_page=5', 'GET').then(function(data) {
                if (data && data.items) {
                    WON.renderLogsTable(data.items, '#won-recent-logs', true);
                }
            });
        },

        /**
         * Load and render chart.
         */
        loadChart: function(days) {
            if (!$('#won-chart').length) return;

            this.apiCall('stats/chart?days=' + days, 'GET').then(function(data) {
                WON.renderChart(data || []);
            });
        },

        /**
         * Update chart when period changes.
         */
        updateChart: function(e) {
            this.loadChart($(e.target).val());
        },

        /**
         * Render the notification chart.
         */
        renderChart: function(data) {
            const ctx = document.getElementById('won-chart');
            if (!ctx) return;

            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }

            const labels = data.map(d => d.date);
            const sent = data.map(d => parseInt(d.sent) || 0);
            const failed = data.map(d => parseInt(d.failed) || 0);

            const isDark = document.body.classList.contains('won-dark-mode');
            const gridColor = isDark ? 'rgba(148, 163, 184, 0.1)' : 'rgba(0, 0, 0, 0.05)';
            const textColor = isDark ? '#94A3B8' : '#64748B';

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Sent',
                            data: sent,
                            borderColor: '#25D366',
                            backgroundColor: 'rgba(37, 211, 102, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        },
                        {
                            label: 'Failed',
                            data: failed,
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top', labels: { color: textColor, usePointStyle: true } }
                    },
                    scales: {
                        x: { grid: { color: gridColor }, ticks: { color: textColor } },
                        y: { grid: { color: gridColor }, ticks: { color: textColor }, beginAtZero: true }
                    }
                }
            });
        },


        /**
         * Send a test WhatsApp message.
         */
        sendTestMessage: function(e) {
            e.preventDefault();
            const phone = $('#won-test-phone').val().trim();
            if (!phone) {
                this.showToast('Please enter a phone number.', 'error');
                return;
            }

            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(wonAdmin.strings.saving);

            this.apiCall('test', 'POST', { phone: phone }).then(function(data) {
                if (data.success) {
                    WON.showToast(wonAdmin.strings.testSent, 'success');
                } else {
                    WON.showToast(data.error || wonAdmin.strings.testFailed, 'error');
                }
            }).catch(function(err) {
                WON.showToast(wonAdmin.strings.testFailed, 'error');
            }).always(function() {
                $btn.prop('disabled', false).html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Send');
            });
        },

        /**
         * Test API connection.
         */
        testConnection: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true);

            this.apiCall('validate', 'POST', {}).then(function(data) {
                if (data.success) {
                    WON.showToast(data.message || wonAdmin.strings.connected, 'success');
                    $('#won-connection-status').html(
                        '<span class="won-status-dot won-status-dot--success"></span><span>Connected</span>'
                    );
                } else {
                    WON.showToast(data.message || wonAdmin.strings.disconnected, 'error');
                }
            }).catch(function() {
                WON.showToast(wonAdmin.strings.disconnected, 'error');
            }).always(function() {
                $btn.prop('disabled', false);
            });
        },

        /**
         * Save settings via REST API.
         */
        saveSettings: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(wonAdmin.strings.saving);

            const settings = {};
            $('[data-setting]').each(function() {
                const $el = $(this);
                const key = $el.data('setting');
                let value;

                if ($el.is(':checkbox')) {
                    value = $el.is(':checked') ? 'yes' : 'no';
                } else if ($el.is(':radio')) {
                    if ($el.is(':checked')) {
                        value = $el.val();
                    } else {
                        return;
                    }
                } else {
                    value = $el.val();
                }

                settings[key] = value;
            });

            this.apiCall('settings', 'POST', settings).then(function(data) {
                if (data.success) {
                    WON.showToast(wonAdmin.strings.saved, 'success');
                } else {
                    WON.showToast(data.message || wonAdmin.strings.error, 'error');
                }
            }).catch(function() {
                WON.showToast(wonAdmin.strings.error, 'error');
            }).always(function() {
                $btn.prop('disabled', false).text('Save Settings');
            });
        },

        /**
         * Switch API provider.
         */
        switchProvider: function(e) {
            const provider = $(e.target).val();
            $('.won-provider-settings').hide();
            $('#settings-' + provider).fadeIn(200);
            $('.won-provider-card').removeClass('won-provider-card--active');
            $(e.target).closest('.won-provider-card').addClass('won-provider-card--active');
        },


        /**
         * Initialize settings tabs.
         */
        initSettingsTabs: function() {
            if (!$('.won-settings-nav').length) return;
            // Check hash
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                this.activateTab(hash);
            }
        },

        /**
         * Switch settings tab.
         */
        switchSettingsTab: function(e) {
            e.preventDefault();
            const tab = $(e.currentTarget).data('tab');
            this.activateTab(tab);
            window.location.hash = tab;
        },

        /**
         * Activate a settings tab.
         */
        activateTab: function(tab) {
            $('.won-settings-nav__item').removeClass('active');
            $('.won-settings-nav__item[data-tab="' + tab + '"]').addClass('active');
            $('.won-settings-panel').removeClass('active');
            $('#panel-' + tab).addClass('active');
        },

        /**
         * Initialize template tabs.
         */
        initTemplateTabs: function() {
            $(document).on('click', '.won-template-tab', function() {
                const type = $(this).data('type');
                $('.won-template-tab').removeClass('active');
                $(this).addClass('active');
                $('.won-template-editor').removeClass('active');
                $('.won-template-editor[data-type="' + type + '"]').addClass('active');
            });
        },

        /**
         * Insert placeholder into textarea.
         */
        insertPlaceholder: function(e) {
            e.preventDefault();
            const placeholder = $(e.currentTarget).data('placeholder');
            const $editor = $(e.currentTarget).closest('.won-template-editor');
            const $textarea = $editor.find('.won-template-textarea');
            const textarea = $textarea[0];

            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            textarea.value = text.substring(0, start) + placeholder + text.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + placeholder.length;
            textarea.focus();
        },

        /**
         * Save template.
         */
        saveTemplate: function(e) {
            e.preventDefault();
            const $active = $('.won-template-editor.active');
            const type = $active.data('type');
            const content = $active.find('.won-template-textarea').val();

            const settings = {};
            settings['message_template_' + type] = content;

            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(wonAdmin.strings.saving);

            this.apiCall('settings', 'POST', settings).then(function(data) {
                if (data.success) {
                    WON.showToast(wonAdmin.strings.saved, 'success');
                }
            }).catch(function() {
                WON.showToast(wonAdmin.strings.error, 'error');
            }).always(function() {
                $btn.prop('disabled', false).text('Save Template');
            });
        },

        /**
         * Preview template with sample data.
         */
        previewTemplate: function(e) {
            e.preventDefault();
            const $active = $('.won-template-editor.active');
            const content = $active.find('.won-template-textarea').val();
            const rendered = content
                .replace(/\{order_id\}/g, '1234')
                .replace(/\{customer_name\}/g, 'Rajesh Kumar')
                .replace(/\{customer_phone\}/g, '+919876543210')
                .replace(/\{customer_email\}/g, 'rajesh@example.com')
                .replace(/\{order_items\}/g, '• iPhone 15 Pro x1 — ₹1,29,999\n• AirPods Pro x1 — ₹24,999')
                .replace(/\{order_total\}/g, '₹1,54,998')
                .replace(/\{payment_method\}/g, 'UPI / PhonePe')
                .replace(/\{shipping_address\}/g, '123 MG Road, Bengaluru')
                .replace(/\{order_date\}/g, new Date().toLocaleString())
                .replace(/\{product_name\}/g, 'Premium Wireless Headphones')
                .replace(/\{stock_quantity\}/g, '3')
                .replace(/\{product_sku\}/g, 'WH-PRO-100')
                .replace(/\{stock_status\}/g, 'LOW STOCK')
                .replace(/\{alert_time\}/g, new Date().toLocaleString())
                .replace(/\{refund_amount\}/g, '₹24,999')
                .replace(/\{refund_reason\}/g, 'Product not as described')
                .replace(/\{refund_date\}/g, new Date().toLocaleString())
                .replace(/\{cart_items\}/g, '• MacBook Air M2 x1 — ₹1,14,999')
                .replace(/\{cart_total\}/g, '₹1,22,998')
                .replace(/\{abandoned_time\}/g, new Date().toLocaleString())
                .replace(/\{site_name\}/g, document.title)
                .replace(/\{current_time\}/g, new Date().toLocaleString())
                .replace(/\{[a-z_]+\}/g, '');

            $('#won-template-preview .won-phone-preview__message').html(
                rendered.replace(/\n/g, '<br>').replace(/\*(.*?)\*/g, '<strong>$1</strong>')
            );
        },

        /**
         * Reset template to default.
         */
        resetTemplate: function(e) {
            e.preventDefault();
            if (!confirm(wonAdmin.strings.confirm)) return;

            const defaults = {
                order: "🛒 *New Order #{order_id}*\n\n👤 {customer_name}\n📱 {customer_phone}\n📧 {customer_email}\n\n📦 Items:\n{order_items}\n\n💰 Total: {order_total}\n💳 Payment: {payment_method}\n📍 Shipping: {shipping_address}\n\n🕐 {order_date}",
                stock: "⚠️ *{stock_status} Alert*\n\n📦 {product_name}\n🔢 Stock: {stock_quantity}\n🆔 SKU: {product_sku}\n\n⏰ {alert_time}",
                refund: "🔄 *Refund Request*\n\n🛒 Order #{order_id}\n👤 {customer_name}\n💰 Refund Amount: {refund_amount}\n📝 Reason: {refund_reason}\n\n💵 Order Total: {order_total}\n⏰ {refund_date}",
                abandoned_cart: "🛒 *Abandoned Cart Alert*\n\n👤 {customer_name}\n📧 {customer_email}\n📱 {customer_phone}\n\n📦 Items:\n{cart_items}\n\n💰 Cart Value: {cart_total}\n⏰ Abandoned: {abandoned_time}",
                order_status: "📋 *Order Status Update*\n\n🛒 Order #{order_id}\n👤 {customer_name}\n\n📊 Status: {old_status} → *{new_status}*\n💰 Total: {order_total}\n\n🕐 {current_time}"
            };

            const $active = $('.won-template-editor.active');
            const type = $active.data('type');
            if (defaults[type]) {
                $active.find('.won-template-textarea').val(defaults[type]);
                this.showToast('Template reset to default.', 'success');
            }
        },


        /**
         * Initialize logs page.
         */
        initLogs: function() {
            if (!$('#won-logs-table').length) return;
            this.loadLogs(1);
        },

        /**
         * Load notification logs.
         */
        loadLogs: function(page) {
            const params = new URLSearchParams({
                page: page || 1,
                per_page: 20,
                type: $('#won-log-type').val() || '',
                status: $('#won-log-status').val() || '',
                search: $('#won-log-search').val() || '',
                date_from: $('#won-log-date-from').val() || '',
                date_to: $('#won-log-date-to').val() || ''
            });

            this.apiCall('logs?' + params.toString(), 'GET').then(function(data) {
                if (data && data.items) {
                    WON.renderLogsTable(data.items, '#won-logs-table', false);
                    WON.renderPagination(data.page, data.total_pages);
                }
            });
        },

        /**
         * Filter logs.
         */
        filterLogs: function(e) {
            e.preventDefault();
            this.loadLogs(1);
        },

        /**
         * Render logs table.
         */
        renderLogsTable: function(items, container, compact) {
            if (!items.length) {
                $(container).html('<div class="won-loading"><p>No notifications yet.</p></div>');
                return;
            }

            const typeIcons = {
                order: '🛒', stock: '📦', refund: '🔄',
                abandoned_cart: '🛒', test: '✅', order_status: '📋'
            };

            const statusClasses = {
                sent: 'won-badge--success', failed: 'won-badge--danger',
                pending: 'won-badge--warning', delivered: 'won-badge--success',
                read: 'won-badge--success'
            };

            let html = '<table class="won-table"><thead><tr>';
            html += '<th>Type</th><th>Recipient</th><th>Status</th>';
            if (!compact) html += '<th>Message</th>';
            html += '<th>Date</th>';
            if (!compact) html += '<th>Actions</th>';
            html += '</tr></thead><tbody>';

            items.forEach(function(item) {
                html += '<tr>';
                html += '<td>' + (typeIcons[item.notification_type] || '📨') + ' ' + (item.notification_type || '').replace('_', ' ') + '</td>';
                html += '<td><code>' + (item.recipient_phone || '-') + '</code></td>';
                html += '<td><span class="won-badge ' + (statusClasses[item.status] || '') + '">' + (item.status || '-') + '</span></td>';
                if (!compact) {
                    const msg = (item.message_content || '').substring(0, 60) + ((item.message_content || '').length > 60 ? '...' : '');
                    html += '<td>' + msg + '</td>';
                }
                html += '<td>' + WON.formatDate(item.created_at) + '</td>';
                if (!compact) {
                    html += '<td>';
                    if (item.status === 'failed') {
                        html += '<button class="won-btn won-btn--ghost won-btn--sm won-resend-btn" data-id="' + item.id + '">Resend</button>';
                    }
                    html += '</td>';
                }
                html += '</tr>';
            });

            html += '</tbody></table>';
            $(container).html(html);
        },

        /**
         * Render pagination.
         */
        renderPagination: function(current, total) {
            if (total <= 1) {
                $('#won-logs-pagination').empty();
                return;
            }

            let html = '';
            for (let i = 1; i <= Math.min(total, 10); i++) {
                html += '<button class="won-pagination__btn ' + (i === current ? 'active' : '') + '" onclick="WON.loadLogs(' + i + ')">' + i + '</button>';
            }
            $('#won-logs-pagination').html(html);
        },

        /**
         * Resend a failed notification.
         */
        resendNotification: function(e) {
            e.preventDefault();
            const id = $(e.currentTarget).data('id');
            const $btn = $(e.currentTarget);
            $btn.prop('disabled', true).text(wonAdmin.strings.resending);

            this.apiCall('resend/' + id, 'POST', {}).then(function(data) {
                if (data.success) {
                    WON.showToast(wonAdmin.strings.resent, 'success');
                    WON.loadLogs(1);
                }
            }).catch(function() {
                WON.showToast(wonAdmin.strings.error, 'error');
            }).always(function() {
                $btn.prop('disabled', false).text('Resend');
            });
        },

        /**
         * Make API call to REST endpoint.
         */
        apiCall: function(endpoint, method, data) {
            const options = {
                url: wonAdmin.apiUrl + endpoint,
                method: method || 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wonAdmin.nonce);
                }
            };

            if (data && (method === 'POST' || method === 'PUT')) {
                options.contentType = 'application/json';
                options.data = JSON.stringify(data);
            }

            return $.ajax(options);
        },

        /**
         * Show toast notification.
         */
        showToast: function(message, type) {
            const $existing = $('.won-toast');
            if ($existing.length) $existing.remove();

            const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : '⚠';
            const $toast = $('<div class="won-toast won-toast--' + type + '">' + icon + ' ' + message + '</div>');
            $('body').append($toast);

            setTimeout(function() {
                $toast.fadeOut(300, function() { $(this).remove(); });
            }, 4000);
        },

        /**
         * Format date string.
         */
        formatDate: function(dateStr) {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    };

    // Make WON globally accessible for pagination clicks
    window.WON = WON;

    // Initialize on DOM ready
    $(document).ready(function() {
        WON.init();
    });

})(jQuery);
