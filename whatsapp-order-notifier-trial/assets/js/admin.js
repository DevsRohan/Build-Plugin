/**
 * WhatsApp Order Notifier - Admin JS (v2 - HF Backend)
 */
(function($) {
'use strict';

const WON = {
    chart: null,

    init() {
        this.initTheme();
        this.bindEvents();
        this.loadStatus();
        this.loadStats();
        this.loadRecentLogs();
        this.initChart();
        this.initTabs();
        this.initLogsPage();
    },

    // ====== Theme ======
    initTheme() {
        const mode = wonAdmin.darkMode || 'auto';
        if (mode === 'dark' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.body.classList.add('won-dark-mode');
        }
    },

    // ====== Events ======
    bindEvents() {
        $(document).on('click', '#won-theme-toggle', () => {
            document.body.classList.toggle('won-dark-mode');
            const isDark = document.body.classList.contains('won-dark-mode');
            this.api('settings', 'POST', { dark_mode: isDark ? 'dark' : 'light' });
        });
        $(document).on('click', '#won-refresh-status', () => this.loadStatus());
        $(document).on('click', '#won-send-test', () => this.sendTest());
        $(document).on('click', '#won-logout-wa', () => this.logout());
        $(document).on('click', '#won-save-settings', () => this.saveSettings());
        $(document).on('click', '#won-log-filter', () => this.loadLogs(1));
        $(document).on('click', '.won-resend-btn', (e) => this.resend($(e.target).data('id')));
        $(document).on('click', '.won-settings-nav__item', (e) => {
            e.preventDefault();
            this.switchTab($(e.currentTarget).data('tab'));
        });
    },

    // ====== WhatsApp Connection Status ======
    loadStatus() {
        if (!$('#won-conn-status').length) return;
        this.api('status', 'GET').then(data => {
            const connected = data.connected;
            const status = data.status || 'unknown';

            let dot = 'won-status-dot--danger';
            let text = status.replace('_', ' ').toUpperCase();
            if (connected) { dot = 'won-status-dot--success'; text = '🟢 CONNECTED'; }
            else if (status === 'qr_ready') { dot = 'won-status-dot--warning'; text = '📱 SCAN QR CODE'; }

            $('#won-conn-status').html(`<span class="won-status-dot ${dot}"></span><span>${text}</span>`);

            if (data.info) {
                $('#won-conn-info').html(`Connected as <strong>${data.info.pushname}</strong> (${data.info.phone}) • ${data.messagesSent || 0} messages sent`);
                $('#won-logout-wa').show();
            } else {
                $('#won-conn-info').text(data.error || '');
                $('#won-logout-wa').hide();
            }

            // Load QR if needed
            if (status === 'qr_ready' && !connected) {
                this.loadQR();
            } else {
                $('#won-qr-area').html(connected ? '<div class="won-qr-connected">✅<br>Connected!</div>' : '<div class="won-qr-waiting">Waiting for backend...</div>');
            }
        }).catch(() => {
            $('#won-conn-status').html('<span class="won-status-dot won-status-dot--danger"></span><span>UNREACHABLE</span>');
            $('#won-conn-info').text('Cannot reach HF Space. Check your URL in Settings.');
            $('#won-qr-area').html('<div class="won-qr-waiting">⚠️ Backend offline</div>');
        });
    },

    loadQR() {
        this.api('qr', 'GET').then(data => {
            if (data.qr) {
                $('#won-qr-area').html(`<img src="${data.qr}" alt="Scan QR" class="won-qr-img" /><p class="won-qr-help">Open WhatsApp → Linked Devices → Scan</p>`);
            } else if (data.status === 'already_connected') {
                $('#won-qr-area').html('<div class="won-qr-connected">✅<br>Connected!</div>');
            } else {
                $('#won-qr-area').html('<div class="won-qr-waiting">QR loading... refresh in 5s</div>');
            }
        });
    },

    // ====== Stats ======
    loadStats() {
        if (!$('#won-stats').length) return;
        this.api('stats?period=today', 'GET').then(data => {
            if (data.notifications) {
                const n = data.notifications;
                $('#stat-total').text(n.total); $('#stat-sent').text(n.sent);
                $('#stat-failed').text(n.failed); $('#stat-rate').text(n.success_rate + '%');
                $('#stat-orders').text(n.orders); $('#stat-stock').text(n.stock_alerts);
                $('#stat-refunds').text(n.refunds); $('#stat-abandoned').text(n.abandoned_carts);
            }
            if (data.queue) { $('#stat-queued').text(data.queue.queued); }
        });
    },

    // ====== Chart ======
    initChart() {
        if (!$('#won-chart').length || typeof Chart === 'undefined') return;
        // Load chart data from notification log
        this.api('logs?per_page=100', 'GET').then(data => {
            // Group by date
            const byDate = {};
            (data.items || []).forEach(item => {
                const d = (item.created_at || '').substring(0, 10);
                if (!d) return;
                if (!byDate[d]) byDate[d] = { sent: 0, failed: 0 };
                if (item.status === 'sent') byDate[d].sent++;
                else if (item.status === 'failed') byDate[d].failed++;
            });
            const labels = Object.keys(byDate).sort().slice(-7);
            const sent = labels.map(d => byDate[d]?.sent || 0);
            const failed = labels.map(d => byDate[d]?.failed || 0);

            const isDark = document.body.classList.contains('won-dark-mode');
            this.chart = new Chart($('#won-chart')[0], {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        { label: 'Sent', data: sent, borderColor: '#25D366', backgroundColor: 'rgba(37,211,102,0.1)', fill: true, tension: 0.4 },
                        { label: 'Failed', data: failed, borderColor: '#EF4444', backgroundColor: 'rgba(239,68,68,0.1)', fill: true, tension: 0.4 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { color: isDark ? '#94A3B8' : '#64748B', usePointStyle: true } } },
                    scales: {
                        x: { grid: { color: isDark ? 'rgba(148,163,184,0.1)' : 'rgba(0,0,0,0.05)' }, ticks: { color: isDark ? '#94A3B8' : '#64748B' } },
                        y: { beginAtZero: true, grid: { color: isDark ? 'rgba(148,163,184,0.1)' : 'rgba(0,0,0,0.05)' }, ticks: { color: isDark ? '#94A3B8' : '#64748B' } }
                    }
                }
            });
        });
    },

    // ====== Test Message ======
    sendTest() {
        const phone = $('#won-test-phone').val().trim();
        if (!phone) { this.toast('Enter a phone number', 'error'); return; }
        const $btn = $('#won-send-test');
        $btn.prop('disabled', true).text('Sending...');
        this.api('send-test', 'POST', { phone }).then(data => {
            if (data.success) {
                $('#won-test-result').html('<span class="won-success-text">✅ Message sent successfully!</span>');
                this.toast('Test message sent!', 'success');
            } else {
                $('#won-test-result').html(`<span class="won-error-text">❌ ${data.error || 'Failed'}</span>`);
                this.toast(data.error || 'Failed to send', 'error');
            }
        }).catch(e => {
            $('#won-test-result').html('<span class="won-error-text">❌ Network error</span>');
        }).always(() => { $btn.prop('disabled', false).text('📤 Send'); });
    },

    // ====== Logout ======
    logout() {
        if (!confirm('Disconnect WhatsApp? You will need to scan QR again.')) return;
        this.api('logout', 'POST').then(() => { this.loadStatus(); this.toast('Disconnected', 'success'); });
    },

    // ====== Settings ======
    saveSettings() {
        const settings = {};
        $('[data-setting]').each(function() {
            const $el = $(this); const key = $el.data('setting');
            if ($el.is(':checkbox')) settings[key] = $el.is(':checked') ? 'yes' : 'no';
            else if ($el.is(':radio')) { if ($el.is(':checked')) settings[key] = $el.val(); }
            else settings[key] = $el.val();
        });

        const $btn = $('#won-save-settings');
        $btn.prop('disabled', true).text('Saving...');
        this.api('settings', 'POST', settings).then(data => {
            if (data.success) this.toast('Settings saved!', 'success');
            else this.toast('Error saving', 'error');
        }).catch(() => this.toast('Network error', 'error'))
        .always(() => $btn.prop('disabled', false).text('💾 Save Settings'));
    },

    // ====== Tabs ======
    initTabs() {
        const hash = window.location.hash.replace('#', '');
        if (hash && $('#panel-' + hash).length) this.switchTab(hash);
    },

    switchTab(tab) {
        $('.won-settings-nav__item').removeClass('active');
        $(`.won-settings-nav__item[data-tab="${tab}"]`).addClass('active');
        $('.won-settings-panel').removeClass('active');
        $('#panel-' + tab).addClass('active');
        window.location.hash = tab;
    },

    // ====== Logs ======
    loadRecentLogs() {
        if (!$('#won-recent-logs').length) return;
        this.api('logs?per_page=5', 'GET').then(data => this.renderLogs(data.items || [], '#won-recent-logs', true));
    },

    initLogsPage() {
        if (!$('#won-logs-table').length) return;
        this.loadLogs(1);
    },

    loadLogs(page) {
        const params = new URLSearchParams({
            page, per_page: 20,
            type: $('#won-log-type').val() || '',
            status: $('#won-log-status').val() || '',
            search: $('#won-log-search').val() || ''
        });
        this.api('logs?' + params, 'GET').then(data => {
            this.renderLogs(data.items || [], '#won-logs-table', false);
            this.renderPagination(page, data.total_pages || 1);
        });
    },

    renderLogs(items, container, compact) {
        if (!items.length) { $(container).html('<div class="won-empty">No notifications yet.</div>'); return; }
        const icons = { order:'🛒', stock:'📦', refund:'🔄', abandoned_cart:'🛒', test:'✅' };
        const badges = { sent:'won-badge--success', failed:'won-badge--danger', pending:'won-badge--warning' };
        let html = '<table class="won-table"><thead><tr><th>Type</th><th>Phone</th><th>Status</th>';
        if (!compact) html += '<th>Message</th>';
        html += '<th>Time</th>';
        if (!compact) html += '<th></th>';
        html += '</tr></thead><tbody>';
        items.forEach(i => {
            const msg = (i.message_content||'').substring(0,50) + ((i.message_content||'').length>50?'...':'');
            html += `<tr><td>${icons[i.notification_type]||'📨'} ${(i.notification_type||'').replace('_',' ')}</td>`;
            html += `<td><code>${i.recipient_phone||'-'}</code></td>`;
            html += `<td><span class="won-badge ${badges[i.status]||''}">${i.status}</span></td>`;
            if (!compact) html += `<td class="won-msg-cell">${msg}</td>`;
            html += `<td>${this.fmtDate(i.created_at)}</td>`;
            if (!compact) html += `<td>${i.status==='failed'?`<button class="won-btn won-btn--ghost won-btn--sm won-resend-btn" data-id="${i.id}">Resend</button>`:''}</td>`;
            html += '</tr>';
        });
        html += '</tbody></table>';
        $(container).html(html);
    },

    renderPagination(current, total) {
        if (total <= 1) { $('#won-pagination').empty(); return; }
        let html = '';
        for (let i = 1; i <= Math.min(total, 10); i++) {
            html += `<button class="won-page-btn ${i===current?'active':''}" onclick="WON.loadLogs(${i})">${i}</button>`;
        }
        $('#won-pagination').html(html);
    },

    resend(id) {
        this.api('resend/' + id, 'POST').then(data => {
            if (data.success) { this.toast('Queued for resend!', 'success'); this.loadLogs(1); }
        });
    },

    // ====== Utilities ======
    api(endpoint, method, data) {
        const opts = { url: wonAdmin.restUrl + endpoint, method: method || 'GET', beforeSend: xhr => xhr.setRequestHeader('X-WP-Nonce', wonAdmin.nonce) };
        if (data && (method === 'POST' || method === 'PUT')) { opts.contentType = 'application/json'; opts.data = JSON.stringify(data); }
        return $.ajax(opts);
    },

    toast(msg, type) {
        $('.won-toast').remove();
        const icon = type === 'success' ? '✓' : '✕';
        $('body').append(`<div class="won-toast won-toast--${type}">${icon} ${msg}</div>`);
        setTimeout(() => $('.won-toast').fadeOut(300, function() { $(this).remove(); }), 4000);
    },

    fmtDate(s) {
        if (!s) return '-';
        const d = new Date(s);
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
    }
};

window.WON = WON;
$(document).ready(() => WON.init());
})(jQuery);
