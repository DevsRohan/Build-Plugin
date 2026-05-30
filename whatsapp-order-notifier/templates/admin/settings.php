<?php
/**
 * Admin Settings Template.
 *
 * @package suspended_Order_Notifier
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$providers = \suspended_Order_Notifier\Api\ProviderFactory::get_available_providers();
$current_provider = get_option( 'won_api_provider', 'whatsapp_business' );
?>
<div class="won-app" id="won-app">
    <div class="won-header">
        <div class="won-header__left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-dashboard' ) ); ?>" class="won-back-link">
                ← <?php esc_html_e( 'Back to Dashboard', 'suspended-order-notifier' ); ?>
            </a>
            <h1><?php esc_html_e( 'Settings', 'suspended-order-notifier' ); ?></h1>
        </div>
    </div>

    <div class="won-settings-layout">
        <!-- Settings Navigation -->
        <nav class="won-settings-nav">
            <a href="#general" class="won-settings-nav__item active" data-tab="general">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
                <?php esc_html_e( 'General', 'suspended-order-notifier' ); ?>
            </a>

            <a href="#api" class="won-settings-nav__item" data-tab="api">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <?php esc_html_e( 'API Connection', 'suspended-order-notifier' ); ?>
            </a>
            <a href="#notifications" class="won-settings-nav__item" data-tab="notifications">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <?php esc_html_e( 'Notifications', 'suspended-order-notifier' ); ?>
            </a>
            <a href="#advanced" class="won-settings-nav__item" data-tab="advanced">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
                </svg>
                <?php esc_html_e( 'Advanced', 'suspended-order-notifier' ); ?>
            </a>
        </nav>

        <!-- Settings Content -->
        <div class="won-settings-content">

            <!-- General Tab -->
            <div class="won-settings-panel active" id="panel-general">
                <div class="won-card">
                    <div class="won-card__header">
                        <h2 class="won-card__title"><?php esc_html_e( 'General Settings', 'suspended-order-notifier' ); ?></h2>
                        <p class="won-card__description"><?php esc_html_e( 'Configure your primary notification settings.', 'suspended-order-notifier' ); ?></p>
                    </div>
                    <div class="won-card__body">
                        <div class="won-form-group">
                            <label class="won-label" for="won-phone-number"><?php esc_html_e( 'Your WhatsApp Number', 'suspended-order-notifier' ); ?></label>
                            <p class="won-help-text"><?php esc_html_e( 'The phone number where you want to receive notifications.', 'suspended-order-notifier' ); ?></p>
                            <div class="won-input-row">
                                <select id="won-country-code" class="won-select" style="width: 120px;" data-setting="country_code">
                                    <option value="+91" <?php selected( get_option( 'won_country_code' ), '+91' ); ?>>🇮🇳 +91</option>
                                    <option value="+62" <?php selected( get_option( 'won_country_code' ), '+62' ); ?>>🇮🇩 +62</option>
                                    <option value="+55" <?php selected( get_option( 'won_country_code' ), '+55' ); ?>>🇧🇷 +55</option>
                                    <option value="+52" <?php selected( get_option( 'won_country_code' ), '+52' ); ?>>🇲🇽 +52</option>
                                    <option value="+63" <?php selected( get_option( 'won_country_code' ), '+63' ); ?>>🇵🇭 +63</option>
                                    <option value="+66" <?php selected( get_option( 'won_country_code' ), '+66' ); ?>>🇹🇭 +66</option>
                                    <option value="+84" <?php selected( get_option( 'won_country_code' ), '+84' ); ?>>🇻🇳 +84</option>
                                    <option value="+60" <?php selected( get_option( 'won_country_code' ), '+60' ); ?>>🇲🇾 +60</option>
                                    <option value="+1" <?php selected( get_option( 'won_country_code' ), '+1' ); ?>>🇺🇸 +1</option>
                                    <option value="+44" <?php selected( get_option( 'won_country_code' ), '+44' ); ?>>🇬🇧 +44</option>
                                    <option value="+971" <?php selected( get_option( 'won_country_code' ), '+971' ); ?>>🇦🇪 +971</option>
                                    <option value="+966" <?php selected( get_option( 'won_country_code' ), '+966' ); ?>>🇸🇦 +966</option>
                                    <option value="+234" <?php selected( get_option( 'won_country_code' ), '+234' ); ?>>🇳🇬 +234</option>
                                    <option value="+254" <?php selected( get_option( 'won_country_code' ), '+254' ); ?>>🇰🇪 +254</option>
                                </select>
                                <input type="text" id="won-phone-number" class="won-input" placeholder="9876543210" value="<?php echo esc_attr( get_option( 'won_phone_number', '' ) ); ?>" data-setting="phone_number" />
                            </div>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label"><?php esc_html_e( 'Dark Mode', 'suspended-order-notifier' ); ?></label>
                            <div class="won-radio-group">
                                <label class="won-radio"><input type="radio" name="dark_mode" value="light" <?php checked( get_option( 'won_dark_mode', 'auto' ), 'light' ); ?> data-setting="dark_mode" /> <?php esc_html_e( 'Light', 'suspended-order-notifier' ); ?></label>
                                <label class="won-radio"><input type="radio" name="dark_mode" value="dark" <?php checked( get_option( 'won_dark_mode', 'auto' ), 'dark' ); ?> data-setting="dark_mode" /> <?php esc_html_e( 'Dark', 'suspended-order-notifier' ); ?></label>
                                <label class="won-radio"><input type="radio" name="dark_mode" value="auto" <?php checked( get_option( 'won_dark_mode', 'auto' ), 'auto' ); ?> data-setting="dark_mode" /> <?php esc_html_e( 'Auto (System)', 'suspended-order-notifier' ); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- API Connection Tab -->
            <div class="won-settings-panel" id="panel-api">
                <div class="won-card">
                    <div class="won-card__header">
                        <h2 class="won-card__title"><?php esc_html_e( 'API Provider', 'suspended-order-notifier' ); ?></h2>
                        <p class="won-card__description"><?php esc_html_e( 'Choose your WhatsApp messaging provider.', 'suspended-order-notifier' ); ?></p>
                    </div>
                    <div class="won-card__body">
                        <div class="won-provider-cards">
                            <?php foreach ( $providers as $provider ) : ?>
                            <label class="won-provider-card <?php echo $current_provider === $provider['id'] ? 'won-provider-card--active' : ''; ?>">
                                <input type="radio" name="api_provider" value="<?php echo esc_attr( $provider['id'] ); ?>" <?php checked( $current_provider, $provider['id'] ); ?> data-setting="api_provider" />
                                <div class="won-provider-card__content">
                                    <strong><?php echo esc_html( $provider['name'] ); ?></strong>
                                    <p><?php echo esc_html( $provider['description'] ); ?></p>
                                    <?php if ( $provider['free_tier'] ) : ?>
                                    <span class="won-badge won-badge--success"><?php esc_html_e( 'Free Tier Available', 'suspended-order-notifier' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Business API Settings -->
                <div class="won-card won-provider-settings" id="settings-whatsapp_business" <?php echo 'whatsapp_business' !== $current_provider ? 'style="display:none;"' : ''; ?>>
                    <div class="won-card__header">
                        <h2 class="won-card__title"><?php esc_html_e( 'WhatsApp Business API Credentials', 'suspended-order-notifier' ); ?></h2>
                    </div>
                    <div class="won-card__body">
                        <div class="won-form-group">
                            <label class="won-label" for="won-wa-token"><?php esc_html_e( 'Access Token', 'suspended-order-notifier' ); ?> <span class="won-required">*</span></label>
                            <input type="password" id="won-wa-token" class="won-input" value="<?php echo esc_attr( get_option( 'won_whatsapp_access_token' ) ? '••••••••' : '' ); ?>" data-setting="whatsapp_access_token" />
                            <p class="won-help-text"><?php esc_html_e( 'Get this from Meta Developer Portal → WhatsApp → API Setup.', 'suspended-order-notifier' ); ?></p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label" for="won-wa-phone-id"><?php esc_html_e( 'Phone Number ID', 'suspended-order-notifier' ); ?> <span class="won-required">*</span></label>
                            <input type="text" id="won-wa-phone-id" class="won-input" value="<?php echo esc_attr( get_option( 'won_whatsapp_phone_number_id', '' ) ); ?>" data-setting="whatsapp_phone_number_id" />
                        </div>
                        <div class="won-form-group">
                            <label class="won-label" for="won-wa-biz-id"><?php esc_html_e( 'Business Account ID', 'suspended-order-notifier' ); ?></label>
                            <input type="text" id="won-wa-biz-id" class="won-input" value="<?php echo esc_attr( get_option( 'won_whatsapp_business_account_id', '' ) ); ?>" data-setting="whatsapp_business_account_id" />
                        </div>
                        <div class="won-form-group">
                            <label class="won-label" for="won-wa-webhook-token"><?php esc_html_e( 'Webhook Verify Token', 'suspended-order-notifier' ); ?></label>
                            <input type="text" id="won-wa-webhook-token" class="won-input" value="<?php echo esc_attr( get_option( 'won_whatsapp_webhook_verify_token', '' ) ); ?>" data-setting="whatsapp_webhook_verify_token" />
                            <p class="won-help-text"><?php esc_html_e( 'Webhook URL:', 'suspended-order-notifier' ); ?> <code><?php echo esc_html( rest_url( 'won/v1/webhook' ) ); ?></code></p>
                        </div>
                    </div>
                </div>

                <!-- Twilio Settings -->
                <div class="won-card won-provider-settings" id="settings-twilio" <?php echo 'twilio' !== $current_provider ? 'style="display:none;"' : ''; ?>>
                    <div class="won-card__header">
                        <h2 class="won-card__title"><?php esc_html_e( 'Twilio Credentials', 'suspended-order-notifier' ); ?></h2>
                    </div>
                    <div class="won-card__body">
                        <div class="won-form-group">
                            <label class="won-label" for="won-twilio-sid"><?php esc_html_e( 'Account SID', 'suspended-order-notifier' ); ?> <span class="won-required">*</span></label>
                            <input type="text" id="won-twilio-sid" class="won-input" value="<?php echo esc_attr( get_option( 'won_twilio_account_sid', '' ) ); ?>" data-setting="twilio_account_sid" />
                        </div>
                        <div class="won-form-group">
                            <label class="won-label" for="won-twilio-token"><?php esc_html_e( 'Auth Token', 'suspended-order-notifier' ); ?> <span class="won-required">*</span></label>
                            <input type="password" id="won-twilio-token" class="won-input" value="<?php echo esc_attr( get_option( 'won_twilio_auth_token' ) ? '••••••••' : '' ); ?>" data-setting="twilio_auth_token" />
                        </div>
                        <div class="won-form-group">
                            <label class="won-label" for="won-twilio-from"><?php esc_html_e( 'WhatsApp Sender Number', 'suspended-order-notifier' ); ?> <span class="won-required">*</span></label>
                            <input type="text" id="won-twilio-from" class="won-input" placeholder="+14155238886" value="<?php echo esc_attr( get_option( 'won_twilio_from_number', '' ) ); ?>" data-setting="twilio_from_number" />
                        </div>
                    </div>
                </div>

                <div class="won-form-actions">
                    <button class="won-btn won-btn--primary" id="won-save-api-settings">
                        <?php esc_html_e( 'Save & Test Connection', 'suspended-order-notifier' ); ?>
                    </button>
                </div>
            </div>


            <!-- Notifications Tab -->
            <div class="won-settings-panel" id="panel-notifications">
                <div class="won-card">
                    <div class="won-card__header">
                        <h2 class="won-card__title"><?php esc_html_e( 'Notification Settings', 'suspended-order-notifier' ); ?></h2>
                        <p class="won-card__description"><?php esc_html_e( 'Choose which notifications you want to receive.', 'suspended-order-notifier' ); ?></p>
                    </div>
                    <div class="won-card__body">
                        <div class="won-toggle-group">
                            <div class="won-toggle-item">
                                <div class="won-toggle-item__info">
                                    <strong>🛒 <?php esc_html_e( 'New Order Alerts', 'suspended-order-notifier' ); ?></strong>
                                    <p><?php esc_html_e( 'Get notified instantly when a new order is placed.', 'suspended-order-notifier' ); ?></p>
                                </div>
                                <label class="won-switch">
                                    <input type="checkbox" <?php checked( get_option( 'won_enable_order_alerts', 'yes' ), 'yes' ); ?> data-setting="enable_order_alerts" />
                                    <span class="won-switch__slider"></span>
                                </label>
                            </div>
                            <div class="won-toggle-item">
                                <div class="won-toggle-item__info">
                                    <strong>📦 <?php esc_html_e( 'Low Stock Alerts', 'suspended-order-notifier' ); ?></strong>
                                    <p><?php esc_html_e( 'Get alerted when product stock falls below threshold.', 'suspended-order-notifier' ); ?></p>
                                </div>
                                <label class="won-switch">
                                    <input type="checkbox" <?php checked( get_option( 'won_enable_stock_alerts', 'yes' ), 'yes' ); ?> data-setting="enable_stock_alerts" />
                                    <span class="won-switch__slider"></span>
                                </label>
                            </div>
                            <div class="won-toggle-item">
                                <div class="won-toggle-item__info">
                                    <strong>🔄 <?php esc_html_e( 'Refund Alerts', 'suspended-order-notifier' ); ?></strong>
                                    <p><?php esc_html_e( 'Get notified when a refund request is made.', 'suspended-order-notifier' ); ?></p>
                                </div>
                                <label class="won-switch">
                                    <input type="checkbox" <?php checked( get_option( 'won_enable_refund_alerts', 'yes' ), 'yes' ); ?> data-setting="enable_refund_alerts" />
                                    <span class="won-switch__slider"></span>
                                </label>
                            </div>
                            <div class="won-toggle-item">
                                <div class="won-toggle-item__info">
                                    <strong>🛒 <?php esc_html_e( 'Abandoned Cart Alerts', 'suspended-order-notifier' ); ?></strong>
                                    <p><?php esc_html_e( 'Get notified when a customer abandons their cart.', 'suspended-order-notifier' ); ?></p>
                                </div>
                                <label class="won-switch">
                                    <input type="checkbox" <?php checked( get_option( 'won_enable_abandoned_cart', 'yes' ), 'yes' ); ?> data-setting="enable_abandoned_cart" />
                                    <span class="won-switch__slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="won-form-group" style="margin-top: 24px;">
                            <label class="won-label"><?php esc_html_e( 'Low Stock Threshold', 'suspended-order-notifier' ); ?></label>
                            <input type="number" class="won-input" style="width: 120px;" value="<?php echo esc_attr( get_option( 'won_stock_threshold', 5 ) ); ?>" data-setting="stock_threshold" min="1" max="100" />
                            <p class="won-help-text"><?php esc_html_e( 'Alert when stock falls below this number.', 'suspended-order-notifier' ); ?></p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label"><?php esc_html_e( 'Abandoned Cart Delay (minutes)', 'suspended-order-notifier' ); ?></label>
                            <input type="number" class="won-input" style="width: 120px;" value="<?php echo esc_attr( get_option( 'won_abandoned_cart_delay', 60 ) ); ?>" data-setting="abandoned_cart_delay" min="15" max="1440" />
                            <p class="won-help-text"><?php esc_html_e( 'Minutes of inactivity before a cart is considered abandoned.', 'suspended-order-notifier' ); ?></p>
                        </div>
                    </div>
                </div>
                <div class="won-form-actions">
                    <button class="won-btn won-btn--primary" id="won-save-notification-settings"><?php esc_html_e( 'Save Settings', 'suspended-order-notifier' ); ?></button>
                </div>
            </div>


            <!-- Advanced Tab -->
            <div class="won-settings-panel" id="panel-advanced">
                <div class="won-card">
                    <div class="won-card__header">
                        <h2 class="won-card__title"><?php esc_html_e( 'Queue & Performance', 'suspended-order-notifier' ); ?></h2>
                    </div>
                    <div class="won-card__body">
                        <div class="won-form-group">
                            <label class="won-label"><?php esc_html_e( 'Batch Size', 'suspended-order-notifier' ); ?></label>
                            <input type="number" class="won-input" style="width: 120px;" value="<?php echo esc_attr( get_option( 'won_queue_batch_size', 10 ) ); ?>" data-setting="queue_batch_size" min="1" max="50" />
                            <p class="won-help-text"><?php esc_html_e( 'Number of messages to process per batch.', 'suspended-order-notifier' ); ?></p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label"><?php esc_html_e( 'Retry Attempts', 'suspended-order-notifier' ); ?></label>
                            <input type="number" class="won-input" style="width: 120px;" value="<?php echo esc_attr( get_option( 'won_retry_attempts', 3 ) ); ?>" data-setting="retry_attempts" min="1" max="10" />
                            <p class="won-help-text"><?php esc_html_e( 'Number of times to retry a failed message.', 'suspended-order-notifier' ); ?></p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label"><?php esc_html_e( 'Retry Delay (seconds)', 'suspended-order-notifier' ); ?></label>
                            <input type="number" class="won-input" style="width: 120px;" value="<?php echo esc_attr( get_option( 'won_retry_delay', 300 ) ); ?>" data-setting="retry_delay" min="60" max="3600" />
                            <p class="won-help-text"><?php esc_html_e( 'Base delay between retries (uses exponential backoff).', 'suspended-order-notifier' ); ?></p>
                        </div>
                        <div class="won-form-group">
                            <label class="won-label"><?php esc_html_e( 'Log Retention (days)', 'suspended-order-notifier' ); ?></label>
                            <input type="number" class="won-input" style="width: 120px;" value="<?php echo esc_attr( get_option( 'won_log_retention_days', 30 ) ); ?>" data-setting="log_retention_days" min="7" max="365" />
                            <p class="won-help-text"><?php esc_html_e( 'How long to keep notification logs.', 'suspended-order-notifier' ); ?></p>
                        </div>
                    </div>
                </div>
                <div class="won-form-actions">
                    <button class="won-btn won-btn--primary" id="won-save-advanced-settings"><?php esc_html_e( 'Save Settings', 'suspended-order-notifier' ); ?></button>
                </div>
            </div>

        </div>
    </div>
</div>
