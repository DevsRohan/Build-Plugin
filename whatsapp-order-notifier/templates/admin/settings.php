<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="won-app" id="won-app">
    <div class="won-header">
        <div class="won-header__left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-dashboard' ) ); ?>" class="won-back-link">← Back</a>
            <h1>Settings</h1>
        </div>
    </div>

    <div class="won-settings-layout">
        <nav class="won-settings-nav">
            <a href="#connection" class="won-settings-nav__item active" data-tab="connection">🔗 Connection</a>
            <a href="#notifications" class="won-settings-nav__item" data-tab="notifications">🔔 Notifications</a>
            <a href="#templates" class="won-settings-nav__item" data-tab="templates">📝 Templates</a>
            <a href="#advanced" class="won-settings-nav__item" data-tab="advanced">⚙️ Advanced</a>
        </nav>

        <div class="won-settings-content">
            <!-- Connection Tab -->
            <div class="won-settings-panel active" id="panel-connection">
                <div class="won-card">
                    <div class="won-card__header">
                        <h2>Hugging Face Space Connection</h2>
                        <p class="won-card__desc">Connect to your self-hosted WhatsApp backend. No paid APIs needed!</p>
                    </div>
                    <div class="won-card__body">
                        <div class="won-setup-steps">
                            <div class="won-step">
                                <div class="won-step__num">1</div>
                                <div class="won-step__content">
                                    <strong>Deploy Backend on Hugging Face</strong>
                                    <p>Upload the Node.js code to a new HF Space (Docker SDK). Files needed: <code>server.js</code>, <code>package.json</code>, <code>Dockerfile</code></p>
                                </div>
                            </div>
                            <div class="won-step">
                                <div class="won-step__num">2</div>
                                <div class="won-step__content">
                                    <strong>Enter Space URL Below</strong>
                                    <p>Your Space URL looks like: <code>https://username-spacename.hf.space</code></p>
                                </div>
                            </div>
                            <div class="won-step">
                                <div class="won-step__num">3</div>
                                <div class="won-step__content">
                                    <strong>Scan QR Code from Dashboard</strong>
                                    <p>Go to Dashboard, scan the QR with WhatsApp, and you're done!</p>
                                </div>
                            </div>
                        </div>

                        <div class="won-form-group">
                            <label class="won-label">Hugging Face Space URL <span class="won-required">*</span></label>
                            <input type="url" class="won-input" id="won-hf-url" placeholder="https://your-username-whatsapp-notifier.hf.space" value="<?php echo esc_attr( get_option( 'won_hf_space_url', '' ) ); ?>" data-setting="hf_space_url" />
                            <p class="won-help">The URL of your deployed Hugging Face Space.</p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">API Secret (Optional)</label>
                            <input type="password" class="won-input" placeholder="Leave blank if not set on backend" value="<?php echo esc_attr( get_option( 'won_api_secret' ) ? '••••••••' : '' ); ?>" data-setting="api_secret" />
                            <p class="won-help">If you set <code>API_SECRET</code> env variable on HF Space, enter same value here.</p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">Your WhatsApp Number <span class="won-required">*</span></label>
                            <div class="won-input-row">
                                <select class="won-select" style="width:110px;" data-setting="country_code">
                                    <option value="+91" <?php selected( get_option( 'won_country_code' ), '+91' ); ?>>🇮🇳 +91</option>
                                    <option value="+62" <?php selected( get_option( 'won_country_code' ), '+62' ); ?>>🇮🇩 +62</option>
                                    <option value="+55" <?php selected( get_option( 'won_country_code' ), '+55' ); ?>>🇧🇷 +55</option>
                                    <option value="+52" <?php selected( get_option( 'won_country_code' ), '+52' ); ?>>🇲🇽 +52</option>
                                    <option value="+63" <?php selected( get_option( 'won_country_code' ), '+63' ); ?>>🇵🇭 +63</option>
                                    <option value="+66" <?php selected( get_option( 'won_country_code' ), '+66' ); ?>>🇹🇭 +66</option>
                                    <option value="+60" <?php selected( get_option( 'won_country_code' ), '+60' ); ?>>🇲🇾 +60</option>
                                    <option value="+1" <?php selected( get_option( 'won_country_code' ), '+1' ); ?>>🇺🇸 +1</option>
                                    <option value="+44" <?php selected( get_option( 'won_country_code' ), '+44' ); ?>>🇬🇧 +44</option>
                                    <option value="+971" <?php selected( get_option( 'won_country_code' ), '+971' ); ?>>🇦🇪 +971</option>
                                    <option value="+234" <?php selected( get_option( 'won_country_code' ), '+234' ); ?>>🇳🇬 +234</option>
                                </select>
                                <input type="text" class="won-input" placeholder="9876543210" value="<?php echo esc_attr( get_option( 'won_phone_number', '' ) ); ?>" data-setting="phone_number" />
                            </div>
                            <p class="won-help">This is where you'll receive all notifications.</p>
                        </div>
                    </div>
                </div>
                <div class="won-form-actions">
                    <button class="won-btn won-btn--primary" id="won-save-settings">💾 Save Settings</button>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div class="won-settings-panel" id="panel-notifications">
                <div class="won-card">
                    <div class="won-card__header"><h2>Notification Toggles</h2></div>
                    <div class="won-card__body">
                        <div class="won-toggle-group">
                            <div class="won-toggle-item">
                                <div><strong>🛒 New Order Alerts</strong><p>Instant notification when order is placed</p></div>
                                <label class="won-switch"><input type="checkbox" <?php checked( get_option( 'won_enable_order_alerts' ), 'yes' ); ?> data-setting="enable_order_alerts" /><span class="won-switch__slider"></span></label>
                            </div>
                            <div class="won-toggle-item">
                                <div><strong>📦 Low Stock Alerts</strong><p>When product stock falls below threshold</p></div>
                                <label class="won-switch"><input type="checkbox" <?php checked( get_option( 'won_enable_stock_alerts' ), 'yes' ); ?> data-setting="enable_stock_alerts" /><span class="won-switch__slider"></span></label>
                            </div>
                            <div class="won-toggle-item">
                                <div><strong>🔄 Refund Alerts</strong><p>When a refund request is made</p></div>
                                <label class="won-switch"><input type="checkbox" <?php checked( get_option( 'won_enable_refund_alerts' ), 'yes' ); ?> data-setting="enable_refund_alerts" /><span class="won-switch__slider"></span></label>
                            </div>
                            <div class="won-toggle-item">
                                <div><strong>🛒 Abandoned Cart Alerts</strong><p>When customers leave without buying</p></div>
                                <label class="won-switch"><input type="checkbox" <?php checked( get_option( 'won_enable_abandoned_cart' ), 'yes' ); ?> data-setting="enable_abandoned_cart" /><span class="won-switch__slider"></span></label>
                            </div>
                        </div>
                        <div class="won-form-group" style="margin-top:24px;">
                            <label class="won-label">Low Stock Threshold</label>
                            <input type="number" class="won-input" style="width:100px;" min="1" max="100" value="<?php echo esc_attr( get_option( 'won_stock_threshold', 5 ) ); ?>" data-setting="stock_threshold" />
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">Abandoned Cart Delay (minutes)</label>
                            <input type="number" class="won-input" style="width:100px;" min="15" max="1440" value="<?php echo esc_attr( get_option( 'won_abandoned_cart_delay', 60 ) ); ?>" data-setting="abandoned_cart_delay" />
                        </div>
                    </div>
                </div>
                <div class="won-form-actions"><button class="won-btn won-btn--primary" id="won-save-settings">💾 Save Settings</button></div>
            </div>

            <!-- Templates Tab -->
            <div class="won-settings-panel" id="panel-templates">
                <div class="won-card">
                    <div class="won-card__header"><h2>Message Templates</h2><p class="won-card__desc">Customize your notification messages. Use {placeholders} for dynamic data.</p></div>
                    <div class="won-card__body">
                        <div class="won-form-group">
                            <label class="won-label">🛒 New Order Template</label>
                            <textarea class="won-textarea" rows="8" data-setting="template_order"><?php echo esc_textarea( get_option( 'won_template_order', '' ) ); ?></textarea>
                            <p class="won-help">Placeholders: {order_id}, {customer_name}, {customer_phone}, {customer_email}, {order_items}, {order_total}, {payment_method}, {shipping_address}, {order_date}</p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">⚠️ Stock Alert Template</label>
                            <textarea class="won-textarea" rows="5" data-setting="template_stock"><?php echo esc_textarea( get_option( 'won_template_stock', '' ) ); ?></textarea>
                            <p class="won-help">Placeholders: {product_name}, {stock_quantity}, {product_sku}, {stock_status}, {alert_time}</p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">🔄 Refund Template</label>
                            <textarea class="won-textarea" rows="5" data-setting="template_refund"><?php echo esc_textarea( get_option( 'won_template_refund', '' ) ); ?></textarea>
                            <p class="won-help">Placeholders: {order_id}, {customer_name}, {refund_amount}, {refund_reason}, {refund_date}</p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">🛒 Abandoned Cart Template</label>
                            <textarea class="won-textarea" rows="5" data-setting="template_abandoned_cart"><?php echo esc_textarea( get_option( 'won_template_abandoned_cart', '' ) ); ?></textarea>
                            <p class="won-help">Placeholders: {customer_name}, {customer_email}, {cart_items}, {cart_total}, {abandoned_time}</p>
                        </div>
                    </div>
                </div>
                <div class="won-form-actions"><button class="won-btn won-btn--primary" id="won-save-settings">💾 Save Settings</button></div>
            </div>

            <!-- Advanced Tab -->
            <div class="won-settings-panel" id="panel-advanced">
                <div class="won-card">
                    <div class="won-card__header"><h2>Queue & Performance</h2></div>
                    <div class="won-card__body">
                        <div class="won-form-group">
                            <label class="won-label">Batch Size</label>
                            <input type="number" class="won-input" style="width:100px;" min="1" max="50" value="<?php echo esc_attr( get_option( 'won_queue_batch_size', 10 ) ); ?>" data-setting="queue_batch_size" />
                            <p class="won-help">Messages processed per cron run.</p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">Retry Attempts</label>
                            <input type="number" class="won-input" style="width:100px;" min="1" max="10" value="<?php echo esc_attr( get_option( 'won_retry_attempts', 3 ) ); ?>" data-setting="retry_attempts" />
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">Log Retention (days)</label>
                            <input type="number" class="won-input" style="width:100px;" min="7" max="365" value="<?php echo esc_attr( get_option( 'won_log_retention_days', 30 ) ); ?>" data-setting="log_retention_days" />
                        </div>
                        <div class="won-form-group">
                            <label class="won-label">Theme</label>
                            <div class="won-radio-group">
                                <label><input type="radio" name="dark_mode" value="light" <?php checked( get_option( 'won_dark_mode' ), 'light' ); ?> data-setting="dark_mode" /> Light</label>
                                <label><input type="radio" name="dark_mode" value="dark" <?php checked( get_option( 'won_dark_mode' ), 'dark' ); ?> data-setting="dark_mode" /> Dark</label>
                                <label><input type="radio" name="dark_mode" value="auto" <?php checked( get_option( 'won_dark_mode' ), 'auto' ); ?> data-setting="dark_mode" /> Auto</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="won-form-actions"><button class="won-btn won-btn--primary" id="won-save-settings">💾 Save Settings</button></div>
            </div>
        </div>
    </div>
</div>
