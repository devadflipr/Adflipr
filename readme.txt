=== Adflipr for WooCommerce ===
Contributors: devadflipr
Tags: woocommerce, email marketing, automation, ecommerce, integration
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Requires Plugins: woocommerce
WC requires at least: 8.0
WC tested up to: 10.3
Stable tag: 1.0.22
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Connect WooCommerce to Adflipr for email marketing, automation, and secure store data sync.

== Description ==

Adflipr is a connector plugin that bridges your WordPress/WooCommerce store to **Adflipr** (hosted SaaS) for email marketing, automation, and commerce data sync. **You need an active Adflipr account** — nothing is sent to Adflipr until a store administrator connects the integration from the WordPress admin **Adflipr** screen.

The Adflipr admin screen provides a React store overview (connect, sync status, **View App**). Suggested Privacy Policy text is registered for the WordPress privacy guide (**Settings → Privacy**) when this plugin is active.

= Key Features =

* **Catalog & order synchronization**
  * Sync product, coupon, order, and (where available) subscription data to Adflipr
  * Webhook-style updates when catalog entities change
  * Offline retry queue when Adflipr is temporarily unreachable

* **Checkout & cart automation**
  * Forward checkout and cart events for flows such as abandoned cart recovery (configured in Adflipr)
  * Capture customer email at checkout for recovery workflows
  * Support for classic and block checkout where WooCommerce provides hooks

* **Secure connection**
  * Administrator sign-in from WordPress admin
  * Authentication tokens stored in WordPress (`wp_options`) and sent over HTTPS
  * Disconnect from the Adflipr dashboard or local admin actions

* **Admin experience**
  * React-based admin UI built with `@wordpress/scripts`
  * Connect and open the full Adflipr web application from WordPress
  * Admin notices when the store is not connected

* **Developer-friendly**
  * REST endpoints under `/wp-json/adflipr/v1/` (including `integration-health` for diagnostics)
  * Human-readable source and build tooling included in the plugin package
  * Configurable API base URL via `wp-config.php` or filters

* **WooCommerce integration**
  * Requires WooCommerce 8.0+
  * HPOS (custom order tables) compatibility declared
  * Works with WooCommerce products, orders, coupons, and checkout flows

= Requirements =

* WordPress 6.4 or higher
* WooCommerce 8.0 or higher
* PHP 8.1 or higher
* Active Adflipr account (for meaningful use after connection)

**UTM parameter storage on the storefront is disabled by default.** A small script runs only when the `adflipr_utm_tracking_enabled` option is enabled.

== External services ==

This plugin connects to Adflipr services to provide:

* Abandoned cart recovery
* Checkout synchronization
* Email marketing automation
* Campaign and analytics features

The plugin sends store and commerce data to Adflipr, including:

* Store information
* Cart and checkout events
* Order events
* Customer email addresses associated with checkout activity

Data is only transmitted after the merchant connects their Adflipr account from the plugin settings.

Service provider:
https://adflipr.com

Terms of Service:
https://adflipr.com/terms

Privacy Policy:
https://adflipr.com/privacy

Support:
https://adflipr.com/contact-us

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to **Plugins → Add New**
3. Search for **Adflipr**
4. Click **Install Now**
5. Activate the plugin

= Manual Installation =

1. Download the plugin zip file
2. Log in to your WordPress admin panel
3. Navigate to **Plugins → Add New**
4. Click **Upload Plugin**
5. Choose the zip file and click **Install Now**
6. Activate the plugin

= After Installation =

1. Ensure WooCommerce is installed and active (PHP 8.1+, WordPress 6.4+)
2. Open **Adflipr** in the WordPress admin menu
3. Connect your Adflipr account
4. Use **View App** to open the Adflipr web application and configure campaigns and automation

== Frequently Asked Questions ==

= Do I need WooCommerce? =

Yes. WooCommerce must be installed and active. This plugin does not provide standalone value without WooCommerce.

= Do I need an Adflipr account? =

Yes. This is a service integration. Without signing in to Adflipr, sync and automation either do not run or have no destination.

= What data leaves my site? =

See **External services** above and **Settings → Privacy** suggested text when this plugin is active.

= How do I disconnect? =

Disconnect from your **Adflipr account** (App Integrations). Uninstalling removes local options and notifies Adflipr when a token exists.

= Where can I change the API hostname? =

Define `ADFLIPR_API_BASE_URL` in `wp-config.php` or use the `adflipr_api_base_url` filter. See `README.md` in the plugin directory.

= How do I diagnose connection or queue issues? =

Site administrators can use the **Adflipr** screen in WordPress admin for connection status. Developers may **GET** `/wp-json/adflipr/v1/integration-health` with cookie authentication and the `X-WP-Nonce` header (`wp_rest`). No secrets are returned.

= How do I enable verbose logging? =

Use `define( 'ADFLIPR_DEBUG', true );` in `wp-config.php`. Logs use the `[Adflipr]` prefix and avoid tokens or payload bodies.

== Screenshots ==

1. Adflipr WordPress admin — store overview and View App

== Changelog ==

= 1.0.20 =
* Readme and packaging updates for WordPress.org submission.

= 1.0.17 =
* Removed PHP Adflipr Health panel (diagnostics remain via REST `integration-health` for developers).

= 1.0.12 =
* WordPress.org review: system fonts, text domain, cookie sanitization, readme and source in distribution zip.

= 1.0.7 =
* Admin UI: React overview; disconnect via Adflipr app.
* Integration lifecycle alignment with Adflipr dashboard disconnect and reconnect.

= 1.0 =
* Initial public structure: WooCommerce sync and webhooks, retry queue, checkout/cart helpers.

== Upgrade Notice ==

= 1.0.20 =
Documentation and compliance updates. Requires WooCommerce 8.0+ and an Adflipr account for connected features.

= 1.0.12 =
Compliance and documentation updates for WordPress.org; no intentional behavior changes to sync or recovery.

= 1.0.7 =
Admin layout and disconnect flow updates; reconnect from Adflipr if disconnected remotely.

== Support ==

For support, visit:
https://adflipr.com/contact-us

== Credits ==

Developed by Adflipr
Website: https://adflipr.com

== License ==

Adflipr is licensed under the GPLv3 or later.

== Privacy Policy ==

* **Sync & webhooks:** Product, coupon, order, subscription (if applicable), and related payloads may be sent to Adflipr over HTTPS after you connect.
* **Authentication:** Login tokens are stored in `wp_options` after admin sign-in.
* **UTM cookies (optional):** When `adflipr_utm_tracking_enabled` is on, `utm_*` query keys may be stored in first-party session cookies for attribution.
* **Disconnect & uninstall:** Disconnect clears local tokens; uninstall removes plugin options and attempts to notify Adflipr.
* **Third-party processing:** Review Adflipr’s privacy policy at https://adflipr.com/privacy for how they process data.
