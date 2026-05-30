<?php
/**
 * Message Templates Page.
 *
 * @package suspended_Order_Notifier
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$builder = new \suspended_Order_Notifier\Notifications\MessageBuilder();
$types = array(
    'order'          => __( 'New Order', 'suspended-order-notifier' ),
    'order_status'   => __( 'Order Status Change', 'suspended-order-notifier' ),
    'stock'          => __( 'Low Stock Alert', 'suspended-order-notifier' ),
    'refund'         => __( 'Refund Request', 'suspended-order-notifier' ),
    'abandoned_cart' => __( 'Abandoned Cart', 'suspended-order-notifier' ),
);
?>
<div class="won-app" id="won-app">
    <div class="won-header">
        <div class="won-header__left">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=won-dashboard' ) ); ?>" class="won-back-link">
                ← <?php esc_html_e( 'Back to Dashboard', 'suspended-order-notifier' ); ?>
            </a>
            <h1><?php esc_html_e( 'Message Templates', 'suspended-order-notifier' ); ?></h1>
        </div>
    </div>

    <div class="won-templates-layout">
        <!-- Template Type Selector -->
        <div class="won-card">
            <div class="won-card__body">
                <div class="won-template-tabs">
                    <?php foreach ( $types as $type_key => $type_label ) : ?>
                    <button class="won-template-tab <?php echo 'order' === $type_key ? 'active' : ''; ?>" data-type="<?php echo esc_attr( $type_key ); ?>">
                        <?php echo esc_html( $type_label ); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Template Editor & Preview -->
        <div class="won-content-grid won-content-grid--templates">
            <!-- Editor -->
            <div class="won-card">
                <div class="won-card__header">
                    <h2 class="won-card__title"><?php esc_html_e( 'Edit Template', 'suspended-order-notifier' ); ?></h2>
                    <button class="won-btn won-btn--ghost won-btn--sm" id="won-reset-template">
                        <?php esc_html_e( 'Reset to Default', 'suspended-order-notifier' ); ?>
                    </button>
                </div>
                <div class="won-card__body">
                    <?php foreach ( $types as $type_key => $type_label ) : ?>
                    <div class="won-template-editor <?php echo 'order' === $type_key ? 'active' : ''; ?>" data-type="<?php echo esc_attr( $type_key ); ?>">
                        <textarea class="won-textarea won-template-textarea" rows="12" data-setting="message_template_<?php echo esc_attr( $type_key ); ?>"><?php echo esc_textarea( get_option( 'won_message_template_' . $type_key, $builder->get_default_template( $type_key ) ) ); ?></textarea>
                        <div class="won-placeholders">
                            <h4><?php esc_html_e( 'Available Placeholders', 'suspended-order-notifier' ); ?></h4>
                            <div class="won-placeholder-list">
                                <?php foreach ( $builder->get_available_placeholders( $type_key ) as $placeholder => $desc ) : ?>
                                <button class="won-placeholder-tag" data-placeholder="<?php echo esc_attr( $placeholder ); ?>" title="<?php echo esc_attr( $desc ); ?>">
                                    <?php echo esc_html( $placeholder ); ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="won-form-actions">
                        <button class="won-btn won-btn--primary" id="won-save-template"><?php esc_html_e( 'Save Template', 'suspended-order-notifier' ); ?></button>
                        <button class="won-btn won-btn--outline" id="won-preview-template"><?php esc_html_e( 'Preview', 'suspended-order-notifier' ); ?></button>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="won-card won-card--preview">
                <div class="won-card__header">
                    <h2 class="won-card__title"><?php esc_html_e( 'Preview', 'suspended-order-notifier' ); ?></h2>
                </div>
                <div class="won-card__body">
                    <div class="won-phone-preview">
                        <div class="won-phone-preview__header">
                            <div class="won-phone-preview__avatar">WA</div>
                            <div class="won-phone-preview__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
                        </div>
                        <div class="won-phone-preview__body" id="won-template-preview">
                            <div class="won-phone-preview__message">
                                <?php echo nl2br( esc_html( $builder->preview_template( 'order' ) ) ); ?>
                            </div>
                            <div class="won-phone-preview__time"><?php echo esc_html( current_time( 'H:i' ) ); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
