=== WhatsApp Order Notifier ===
Contributors: whatsappordernotifier
Tags: whatsapp, woocommerce, order notification, abandoned cart, stock alert
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
WC requires at least: 5.0
WC tested up to: 8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Instant WhatsApp alerts for WooCommerce orders, low stock, refunds, and abandoned carts.

== Description ==

**Never miss another order again!** WhatsApp Order Notifier sends instant WhatsApp messages to your phone whenever important events happen in your WooCommerce store.

= Features =

* **New Order Alerts** — Instant WhatsApp notification with full order details
* **Low Stock Alerts** — Know when products are running low
* **Refund Notifications** — Get notified about refund requests immediately
* **Abandoned Cart Alerts** — Know when customers leave without buying
* **Custom Templates** — Customize every notification message
* **Multiple Providers** — Works with WhatsApp Business API (free) or Twilio
* **Queue System** — Reliable delivery with retry logic
* **Beautiful Dashboard** — Premium analytics and notification history
* **Dark Mode** — Easy on the eyes during late-night store management
* **HPOS Compatible** — Works with WooCommerce High-Performance Order Storage

= Why WhatsApp? =

* WhatsApp messages have 98% open rate vs 20% for email
* Instant delivery — no more checking email every few minutes
* Perfect for India, Southeast Asia, Latin America, and Middle East markets
* Your customers AND you are already on WhatsApp

= Supported Providers =

1. **WhatsApp Business Cloud API** (by Meta) — Free tier available, recommended
2. **Twilio WhatsApp** — Pay per message, great for high volume

== Installation ==

1. Upload the `whatsapp-order-notifier` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WhatsApp Notifier → Settings to configure your API credentials
4. Enter your WhatsApp phone number
5. Send a test message to verify everything works
6. Done! You'll now receive WhatsApp notifications for every new order.

= Quick Setup Guide =

**For WhatsApp Business API (Recommended — Free):**

1. Go to [Meta Developer Portal](https://developers.facebook.com/)
2. Create an app and add WhatsApp product
3. Get your Access Token and Phone Number ID from API Setup
4. Enter these in the plugin settings

**For Twilio:**

1. Sign up at [Twilio](https://www.twilio.com/)
2. Get a WhatsApp-enabled phone number
3. Copy your Account SID and Auth Token
4. Enter these in the plugin settings

== Frequently Asked Questions ==

= Is the WhatsApp Business API free? =

Meta offers 1,000 free conversations per month. For most stores, this is more than enough.

= Do I need a WhatsApp Business account? =

Yes, you need a Meta Business account and WhatsApp Business API access. The plugin guides you through setup.

= Will this work with my WooCommerce theme? =

Yes! This plugin works with any WooCommerce-compatible theme.

= Does it support multiple phone numbers? =

Currently it sends to one primary phone number. Multiple recipients will be added in a future update.

= Is it compatible with HPOS? =

Yes, full WooCommerce High-Performance Order Storage compatibility.

== Changelog ==

= 1.0.0 =
* Initial release
* New order WhatsApp notifications
* Low stock alerts
* Refund notifications
* Abandoned cart detection and alerts
* Custom message templates
* WhatsApp Business API integration
* Twilio WhatsApp integration
* Premium admin dashboard with analytics
* Dark mode support
* Queue system with retry logic
* Webhook support for delivery status

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install and configure to start receiving WhatsApp order notifications!
