<?php
/**
 * Admin Dashboard Template.
 *
 * @package suspended_Order_Notifier
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$provider = \suspended_Order_Notifier\Api\ProviderFactory::get_provider();
$is_configured = $provider->is_configured();
?>
<div class="won-app" id="won-app">
    <div class="won-header">
        <div class="won-header__left">
            <div class="won-logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="32" height="32" rx="8" fill="url(#gradient1)"/>
                    <path d="M16 6C10.477 6 6 10.477 6 16C6 17.89 6.525 19.66 7.438 21.174L6 26L10.91 24.594C12.395 25.455 14.133 26 16 26C21.523 26 26 21.523 26 16C26 10.477 21.523 6 16 6Z" fill="white" fill-opacity="0.9"/>
                    <path d="M20.5 18.5C20.25 18.375 19 17.75 18.75 17.65C18.5 17.55 18.325 17.5 18.15 17.75C17.975 18 17.5 18.575 17.35 18.75C17.2 18.925 17.05 18.95 16.8 18.825C16.55 18.7 15.7 18.425 14.7 17.525C13.925 16.825 13.4 15.975 13.25 15.725C13.1 15.475 13.235 15.35 13.36 15.225C13.473 15.112 13.61 14.925 13.735 14.775C13.86 14.625 13.9 14.512 14 14.337C14.1 14.162 14.05 14.012 13.988 13.887C13.925 13.762 13.4 12.512 13.175 12.012C12.955 11.525 12.73 11.6 12.565 11.59C12.415 11.582 12.24 11.58 12.065 11.58C11.89 11.58 11.6 11.643 11.35 11.893C11.1 12.143 10.425 12.768 10.425 14.018C10.425 15.268 11.375 16.475 11.5 16.65C11.625 16.825 13.4 19.6 16.225 20.7C17.025 21.025 17.65 21.212 18.138 21.35C18.938 21.575 19.663 21.544 20.238 21.481C20.875 21.406 21.875 20.856 22.1 20.256C22.325 19.656 22.325 19.143 22.263 19.043C22.2 18.943 22.025 18.881 21.775 18.756L20.5 18.5Z" fill="url(#gradient1)"/>
                    <defs>
                        <linearGradient id="gradient1" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#25D366"/>
                            <stop offset="1" stop-color="#128C7E"/>
                        </linearGradient>
                    </defs>
                </svg>
                <div class="won-logo__text">
                    <h1><?php esc_html_e( 'WhatsApp Order Notifier', 'suspended-order-notifier' ); ?></h1>
                    <span class="won-badge won-badge--version">v<?php echo esc_html( WON_VERSION ); ?></span>
                </div>
            </div>
        </div>
        <div class="won-header__right">
            <button class="won-btn won-btn--ghost" id="won-theme-toggle" title="<?php esc_attr_e( 'Toggle dark mode', 'suspended-order-notifier' ); ?>">
                <svg class="won-icon won-icon--sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                <svg class="won-icon won-icon--moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-settings' ) ); ?>" class="won-btn won-btn--outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <?php esc_html_e( 'Settings', 'suspended-order-notifier' ); ?>
            </a>
        </div>
    </div>

    <?php if ( ! $is_configured ) : ?>
    <div class="won-notice won-notice--warning">
        <div class="won-notice__icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="won-notice__content">
            <strong><?php esc_html_e( 'Setup Required', 'suspended-order-notifier' ); ?></strong>
            <p><?php esc_html_e( 'Please configure your WhatsApp API credentials to start receiving notifications.', 'suspended-order-notifier' ); ?></p>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-settings' ) ); ?>" class="won-btn won-btn--sm won-btn--primary">
            <?php esc_html_e( 'Configure Now', 'suspended-order-notifier' ); ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="won-stats-grid" id="won-stats">
        <div class="won-stat-card won-stat-card--primary">
            <div class="won-stat-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </div>
            <div class="won-stat-card__content">
                <span class="won-stat-card__value" id="stat-total">--</span>
                <span class="won-stat-card__label"><?php esc_html_e( 'Total Sent', 'suspended-order-notifier' ); ?></span>
            </div>
            <div class="won-stat-card__trend won-stat-card__trend--up">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            </div>
        </div>

        <div class="won-stat-card won-stat-card--success">
            <div class="won-stat-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="won-stat-card__content">
                <span class="won-stat-card__value" id="stat-sent">--</span>
                <span class="won-stat-card__label"><?php esc_html_e( 'Delivered', 'suspended-order-notifier' ); ?></span>
            </div>
            <div class="won-stat-card__badge" id="stat-rate">--%</div>
        </div>

        <div class="won-stat-card won-stat-card--warning">
            <div class="won-stat-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="won-stat-card__content">
                <span class="won-stat-card__value" id="stat-pending">--</span>
                <span class="won-stat-card__label"><?php esc_html_e( 'In Queue', 'suspended-order-notifier' ); ?></span>
            </div>
        </div>

        <div class="won-stat-card won-stat-card--danger">
            <div class="won-stat-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            </div>
            <div class="won-stat-card__content">
                <span class="won-stat-card__value" id="stat-failed">--</span>
                <span class="won-stat-card__label"><?php esc_html_e( 'Failed', 'suspended-order-notifier' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="won-content-grid">
        <!-- Chart Section -->
        <div class="won-card won-card--chart">
            <div class="won-card__header">
                <h2 class="won-card__title"><?php esc_html_e( 'Notification Activity', 'suspended-order-notifier' ); ?></h2>
                <div class="won-card__actions">
                    <select id="won-chart-period" class="won-select won-select--sm">
                        <option value="7"><?php esc_html_e( 'Last 7 days', 'suspended-order-notifier' ); ?></option>
                        <option value="14"><?php esc_html_e( 'Last 14 days', 'suspended-order-notifier' ); ?></option>
                        <option value="30"><?php esc_html_e( 'Last 30 days', 'suspended-order-notifier' ); ?></option>
                    </select>
                </div>
            </div>
            <div class="won-card__body">
                <canvas id="won-chart" height="280"></canvas>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="won-card won-card--actions">
            <div class="won-card__header">
                <h2 class="won-card__title"><?php esc_html_e( 'Quick Actions', 'suspended-order-notifier' ); ?></h2>
            </div>
            <div class="won-card__body">
                <div class="won-quick-actions">
                    <div class="won-quick-action">
                        <h4><?php esc_html_e( 'Send Test Message', 'suspended-order-notifier' ); ?></h4>
                        <p><?php esc_html_e( 'Verify your setup is working correctly.', 'suspended-order-notifier' ); ?></p>
                        <div class="won-input-group">
                            <input type="text" id="won-test-phone" class="won-input" placeholder="+919876543210" />
                            <button class="won-btn won-btn--primary won-btn--sm" id="won-send-test">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                <?php esc_html_e( 'Send', 'suspended-order-notifier' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="won-quick-action">
                        <h4><?php esc_html_e( 'Connection Status', 'suspended-order-notifier' ); ?></h4>
                        <div class="won-connection-status" id="won-connection-status">
                            <span class="won-status-dot <?php echo $is_configured ? 'won-status-dot--success' : 'won-status-dot--danger'; ?>"></span>
                            <span><?php echo $is_configured ? esc_html__( 'Connected', 'suspended-order-notifier' ) : esc_html__( 'Not Configured', 'suspended-order-notifier' ); ?></span>
                        </div>
                        <button class="won-btn won-btn--outline won-btn--sm" id="won-test-connection">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                            <?php esc_html_e( 'Test Connection', 'suspended-order-notifier' ); ?>
                        </button>
                    </div>

                    <!-- Notification Breakdown -->
                    <div class="won-quick-action">
                        <h4><?php esc_html_e( 'Today\'s Breakdown', 'suspended-order-notifier' ); ?></h4>
                        <div class="won-breakdown">
                            <div class="won-breakdown__item">
                                <span class="won-breakdown__label">🛒 <?php esc_html_e( 'Orders', 'suspended-order-notifier' ); ?></span>
                                <span class="won-breakdown__value" id="stat-orders">--</span>
                            </div>
                            <div class="won-breakdown__item">
                                <span class="won-breakdown__label">📦 <?php esc_html_e( 'Stock Alerts', 'suspended-order-notifier' ); ?></span>
                                <span class="won-breakdown__value" id="stat-stock">--</span>
                            </div>
                            <div class="won-breakdown__item">
                                <span class="won-breakdown__label">🔄 <?php esc_html_e( 'Refunds', 'suspended-order-notifier' ); ?></span>
                                <span class="won-breakdown__value" id="stat-refunds">--</span>
                            </div>
                            <div class="won-breakdown__item">
                                <span class="won-breakdown__label">🛒 <?php esc_html_e( 'Abandoned', 'suspended-order-notifier' ); ?></span>
                                <span class="won-breakdown__value" id="stat-abandoned">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="won-card">
        <div class="won-card__header">
            <h2 class="won-card__title"><?php esc_html_e( 'Recent Notifications', 'suspended-order-notifier' ); ?></h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-logs' ) ); ?>" class="won-btn won-btn--ghost won-btn--sm">
                <?php esc_html_e( 'View All', 'suspended-order-notifier' ); ?> →
            </a>
        </div>
        <div class="won-card__body won-card__body--table">
            <div class="won-table-wrapper" id="won-recent-logs">
                <div class="won-loading">
                    <div class="won-spinner"></div>
                    <p><?php esc_html_e( 'Loading...', 'suspended-order-notifier' ); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>
