<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="won-app" id="won-app">
    <div class="won-header">
        <div class="won-header__left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-dashboard' ) ); ?>" class="won-back-link">← Back</a>
            <h1>Notification Log</h1>
        </div>
    </div>

    <div class="won-card">
        <div class="won-card__body">
            <div class="won-filters">
                <input type="text" id="won-log-search" class="won-input" placeholder="Search phone..." />
                <select id="won-log-type" class="won-select">
                    <option value="">All Types</option>
                    <option value="order">Orders</option>
                    <option value="stock">Stock</option>
                    <option value="refund">Refunds</option>
                    <option value="abandoned_cart">Abandoned</option>
                    <option value="test">Test</option>
                </select>
                <select id="won-log-status" class="won-select">
                    <option value="">All Status</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                    <option value="pending">Pending</option>
                </select>
                <button class="won-btn won-btn--outline won-btn--sm" id="won-log-filter">Filter</button>
            </div>
        </div>
    </div>

    <div class="won-card">
        <div class="won-card__body won-card__body--flush" id="won-logs-table">
            <div class="won-loading"><div class="won-spinner"></div><p>Loading logs...</p></div>
        </div>
        <div class="won-pagination" id="won-pagination"></div>
    </div>
</div>
