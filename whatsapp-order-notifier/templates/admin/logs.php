<?php
/**
 * Notification Logs Template.
 *
 * @package suspended_Order_Notifier
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="won-app" id="won-app">
    <div class="won-header">
        <div class="won-header__left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-dashboard' ) ); ?>" class="won-back-link">
                ← <?php esc_html_e( 'Back to Dashboard', 'suspended-order-notifier' ); ?>
            </a>
            <h1><?php esc_html_e( 'Notification Log', 'suspended-order-notifier' ); ?></h1>
        </div>
    </div>

    <!-- Filters -->
    <div class="won-card">
        <div class="won-card__body">
            <div class="won-filters">
                <div class="won-filter-group">
                    <input type="text" id="won-log-search" class="won-input" placeholder="<?php esc_attr_e( 'Search by phone number...', 'suspended-order-notifier' ); ?>" />
                </div>
                <div class="won-filter-group">
                    <select id="won-log-type" class="won-select">
                        <option value=""><?php esc_html_e( 'All Types', 'suspended-order-notifier' ); ?></option>
                        <option value="order"><?php esc_html_e( 'Orders', 'suspended-order-notifier' ); ?></option>
                        <option value="stock"><?php esc_html_e( 'Stock Alerts', 'suspended-order-notifier' ); ?></option>
                        <option value="refund"><?php esc_html_e( 'Refunds', 'suspended-order-notifier' ); ?></option>
                        <option value="abandoned_cart"><?php esc_html_e( 'Abandoned Carts', 'suspended-order-notifier' ); ?></option>
                        <option value="test"><?php esc_html_e( 'Test Messages', 'suspended-order-notifier' ); ?></option>
                    </select>
                </div>
                <div class="won-filter-group">
                    <select id="won-log-status" class="won-select">
                        <option value=""><?php esc_html_e( 'All Statuses', 'suspended-order-notifier' ); ?></option>
                        <option value="sent"><?php esc_html_e( 'Sent', 'suspended-order-notifier' ); ?></option>
                        <option value="failed"><?php esc_html_e( 'Failed', 'suspended-order-notifier' ); ?></option>
                        <option value="pending"><?php esc_html_e( 'Pending', 'suspended-order-notifier' ); ?></option>
                    </select>
                </div>
                <div class="won-filter-group">
                    <input type="date" id="won-log-date-from" class="won-input" />
                    <span class="won-filter-sep"><?php esc_html_e( 'to', 'suspended-order-notifier' ); ?></span>
                    <input type="date" id="won-log-date-to" class="won-input" />
                </div>
                <button class="won-btn won-btn--outline won-btn--sm" id="won-log-filter">
                    <?php esc_html_e( 'Filter', 'suspended-order-notifier' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="won-card">
        <div class="won-card__body won-card__body--table">
            <div id="won-logs-table">
                <div class="won-loading">
                    <div class="won-spinner"></div>
                    <p><?php esc_html_e( 'Loading notification logs...', 'suspended-order-notifier' ); ?></p>
                </div>
            </div>
            <div class="won-pagination" id="won-logs-pagination"></div>
        </div>
    </div>
</div>
