=== adflipr ===
Contributors: devadflipr
Tags: woocommerce, email marketing, automation, ecommerce, integration
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.0.19
Requires PHP: 8.1
Requires Plugins: woocommerce
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Connect WooCommerce to Adflipr for email marketing, automation, and secure store data sync.

== Description ==

This plugin is a **connector for an external service**. It bridges your WordPress/WooCommerce site to **Adflipr** (hosted SaaS) so you can use Adflipr features with store data. **You need an active Adflipr account** — nothing is sent to Adflipr until a store administrator connects the integration from WordPress admin.

After connection, the plugin can:

* Sync product, coupon, order, and (where available) subscription data to Adflipr.
* Send webhook-style updates when catalog entities change.
* Forward checkout and cart events used for automation (for example abandoned cart recovery configured in Adflipr).

The Adflipr admin screen shows a React store overview (connect, sync status, **View App**). Suggested Privacy Policy text is available under **Settings → Privacy** when this plugin is active.

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

== Development ==

Human-readable source ships with the plugin (`src/`, `package.json`, `webpack.config.js`). The admin UI is built with `@wordpress/scripts`; runtime loads hashed files from `build/`.

**Source repository:**

https://github.com/adflipr/adflipr

**Build from source** (from the plugin directory):

1. `npm install`
2. `npm run build`

Compiled assets are written to `build/` (`asset-manifest.json` lists entry files). Rebuild after changing `src/`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install through **Plugins → Add New**.
2. Activate the plugin. PHP 8.1+, WordPress 6.4+, and WooCommerce 8.0+ are required.
3. Open **Adflipr** in the WordPress admin menu and connect your Adflipr account. Use **View App** to open the Adflipr web application.

== Frequently Asked Questions ==

= Does this plugin work without WooCommerce? =

No. WooCommerce must be installed and active.

= Does this plugin work without an Adflipr account? =

No meaningful benefit. This is a service integration. Without signing in to Adflipr, sync and automation either do not run or have no destination.

= What data leaves my site? =

See **External services** above and **Settings → Privacy** suggested text.

= How do I disconnect? =

Disconnect from your **Adflipr account** (App Integrations). Uninstalling removes local options and notifies Adflipr when a token exists.

= Where can I change the API hostname? =

Define `ADFLIPR_API_BASE_URL` in `wp-config.php` or use the `adflipr_api_base_url` filter. See `README.md` in the plugin directory.

= How do I diagnose connection or queue issues? =

Site administrators can use the **Adflipr** screen in WordPress admin for connection status. Developers may **GET** `/wp-json/adflipr/v1/integration-health` with cookie authentication and the `X-WP-Nonce` header (`wp_rest`). No secrets are returned.

= How do I enable verbose logging? =

Use `define( 'ADFLIPR_DEBUG', true );` in `wp-config.php`. Logs use the `[Adflipr]` prefix and avoid tokens or payload bodies.

= How do I rebuild the admin UI from source? =

Run `npm install` and `npm run build` in the plugin directory. See **Development**.

== Screenshots ==

1. Adflipr WordPress admin — store overview and View App.

== Changelog ==

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

= 1.0.12 =
Compliance and documentation updates for WordPress.org; no intentional behavior changes to sync or recovery.

= 1.0.7 =
Admin layout and disconnect flow updates; reconnect from Adflipr if disconnected remotely.

== Privacy ==

* **Sync & webhooks:** Product, coupon, order, subscription (if applicable), and related payloads may be sent to Adflipr over HTTPS after you connect.
* **Authentication:** Login tokens are stored in `wp_options` after admin sign-in.
* **UTM cookies (optional):** When `adflipr_utm_tracking_enabled` is on, `utm_*` query keys may be stored in first-party session cookies for attribution.
* **Disconnect & uninstall:** Disconnect clears local tokens; uninstall removes plugin options and attempts to notify Adflipr.
