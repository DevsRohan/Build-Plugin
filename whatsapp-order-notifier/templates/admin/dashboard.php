<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="won-app" id="won-app">
    <div class="won-header">
        <div class="won-header__left">
            <div class="won-logo">
                <div class="won-logo__icon">💬</div>
                <div>
                    <h1>WhatsApp Order Notifier</h1>
                    <span class="won-badge won-badge--version">v<?php echo esc_html( WON_VERSION ); ?> • Free & Self-Hosted</span>
                </div>
            </div>
        </div>
        <div class="won-header__right">
            <button class="won-btn won-btn--ghost" id="won-theme-toggle" title="Toggle dark mode">🌓</button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-settings' ) ); ?>" class="won-btn won-btn--outline">⚙️ Settings</a>
        </div>
    </div>

    <!-- Connection Status Card with QR -->
    <div class="won-connection-card" id="won-connection-card">
        <div class="won-connection-card__left">
            <h2>WhatsApp Connection</h2>
            <div class="won-connection-status" id="won-conn-status">
                <span class="won-status-dot won-status-dot--loading"></span>
                <span>Checking...</span>
            </div>
            <p class="won-connection-info" id="won-conn-info"></p>
            <div class="won-connection-actions">
                <button class="won-btn won-btn--primary won-btn--sm" id="won-refresh-status">🔄 Refresh</button>
                <button class="won-btn won-btn--outline won-btn--sm won-btn--danger" id="won-logout-wa" style="display:none;">Disconnect</button>
            </div>
        </div>
        <div class="won-connection-card__right" id="won-qr-area">
            <!-- QR code loads here dynamically -->
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="won-stats-grid" id="won-stats">
        <div class="won-stat-card won-stat-card--primary">
            <div class="won-stat-card__icon">📤</div>
            <div class="won-stat-card__content">
                <span class="won-stat-card__value" id="stat-total">--</span>
                <span class="won-stat-card__label">Total Sent</span>
            </div>
        </div>
        <div class="won-stat-card won-stat-card--success">
            <div class="won-stat-card__icon">✅</div>
            <div class="won-stat-card__content">
                <span class="won-stat-card__value" id="stat-sent">--</span>
                <span class="won-stat-card__label">Delivered</span>
            </div>
            <div class="won-stat-card__badge" id="stat-rate">--%</div>
        </div>
        <div class="won-stat-card won-stat-card--warning">
            <div class="won-stat-card__icon">⏳</div>
            <div class="won-stat-card__content">
                <span class="won-stat-card__value" id="stat-queued">--</span>
                <span class="won-stat-card__label">In Queue</span>
            </div>
        </div>
        <div class="won-stat-card won-stat-card--danger">
            <div class="won-stat-card__icon">❌</div>
            <div class="won-stat-card__content">
                <span class="won-stat-card__value" id="stat-failed">--</span>
                <span class="won-stat-card__label">Failed</span>
            </div>
        </div>
    </div>

    <!-- Quick Test + Breakdown -->
    <div class="won-content-grid">
        <div class="won-card">
            <div class="won-card__header"><h2>📊 Notification Activity</h2></div>
            <div class="won-card__body"><canvas id="won-chart" height="250"></canvas></div>
        </div>
        <div class="won-card">
            <div class="won-card__header"><h2>⚡ Quick Actions</h2></div>
            <div class="won-card__body">
                <div class="won-quick-action">
                    <h4>Send Test Message</h4>
                    <p>Verify your WhatsApp notifications work.</p>
                    <div class="won-input-group">
                        <input type="text" id="won-test-phone" class="won-input" placeholder="<?php echo esc_attr( get_option( 'won_country_code', '+91' ) ); ?>9876543210" />
                        <button class="won-btn won-btn--primary won-btn--sm" id="won-send-test">📤 Send</button>
                    </div>
                    <div id="won-test-result" class="won-test-result"></div>
                </div>
                <hr class="won-divider" />
                <div class="won-quick-action">
                    <h4>Today's Breakdown</h4>
                    <div class="won-breakdown">
                        <div class="won-breakdown__item"><span>🛒 Orders</span><span id="stat-orders">--</span></div>
                        <div class="won-breakdown__item"><span>📦 Stock Alerts</span><span id="stat-stock">--</span></div>
                        <div class="won-breakdown__item"><span>🔄 Refunds</span><span id="stat-refunds">--</span></div>
                        <div class="won-breakdown__item"><span>🛒 Abandoned</span><span id="stat-abandoned">--</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="won-card">
        <div class="won-card__header">
            <h2>📋 Recent Notifications</h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-logs' ) ); ?>" class="won-btn won-btn--ghost won-btn--sm">View All →</a>
        </div>
        <div class="won-card__body won-card__body--flush" id="won-recent-logs">
            <div class="won-loading"><div class="won-spinner"></div><p>Loading...</p></div>
        </div>
    </div>
</div>
