<?php

/**
 * Plugin Name: Adflipr for WooCommerce
 * Plugin URI: https://github.com/devadflipr/adflipr
 * Description: Email marketing and automation for WooCommerce stores, powered by secure catalog, cart, checkout, and order sync with Adflipr.
 * Version: 1.0.74
 * Requires at least: 6.4
 * Tested up to: 6.9
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 10.3.0
 * Author: Adflipr
 * Author URI: https://adflipr.com
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: adflipr-for-woocommerce
 */

defined('ABSPATH') || exit;

/**
 * Load plugin translations.
 */
add_action(
  'init',
  static function () {
    load_plugin_textdomain(
      'adflipr-for-woocommerce',
      false,
      dirname(plugin_basename(__FILE__)) . '/languages'
    );
  }
);

require_once __DIR__ . '/includes/adflipr-version.php';

/**
 * Tell WooCommerce this plugin supports High-Performance Order Storage (HPOS).
 * Must register on load so the callback runs when WooCommerce fires `before_woocommerce_init`
 * (typically Adflipr loads before WooCommerce alphabetically; keep this at file top).
 */
add_action(
  'before_woocommerce_init',
  static function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        __FILE__,
        true
      );
    }
  }
);

require_once __DIR__ . '/includes/adflipr-api-config.php';
require_once __DIR__ . '/includes/adflipr-requirements.php';
if (! defined('ADFLIPR_DEBUG')) {
  /** Set true in wp-config.php for verbose diagnostic logs (never logs tokens). */
  define('ADFLIPR_DEBUG', false);
}
require_once __DIR__ . '/includes/adflipr-debug.php';
require_once __DIR__ . '/includes/adflipr-install-telemetry.php';
require_once __DIR__ . '/includes/adflipr-disconnect.php';
require_once __DIR__ . '/includes/adflipr-rest-permissions.php';
require_once __DIR__ . '/includes/adflipr-webhook-idempotency.php';
require_once __DIR__ . '/includes/adflipr-queue-helpers.php';
require_once __DIR__ . '/includes/adflipr-path-utils.php';
require_once __DIR__ . '/includes/class-adflipr-assets.php';
require_once __DIR__ . '/includes/adflipr-user-messages.php';
if (! defined('ADFLIPR_WEBHOOK_RETRY_QUEUE_OPTION')) {
  define('ADFLIPR_WEBHOOK_RETRY_QUEUE_OPTION', 'adflipr_webhook_retry_queue');
}
if (! defined('ADFLIPR_WEBHOOK_RETRY_CRON_HOOK')) {
  define('ADFLIPR_WEBHOOK_RETRY_CRON_HOOK', 'adflipr_webhook_retry_process');
}
if (! defined('ADFLIPR_WEBHOOK_RETRY_MAX_ITEMS')) {
  define('ADFLIPR_WEBHOOK_RETRY_MAX_ITEMS', 300);
}
if (! defined('ADFLIPR_WEBHOOK_RETRY_MAX_ATTEMPTS')) {
  define('ADFLIPR_WEBHOOK_RETRY_MAX_ATTEMPTS', 15);
}
if (! defined('ADFLIPR_WEBHOOK_RETRY_BATCH_PER_RUN')) {
  define('ADFLIPR_WEBHOOK_RETRY_BATCH_PER_RUN', 25);
}
if (! defined('ADFLIPR_WEBHOOK_HTTP_TIMEOUT')) {
  define('ADFLIPR_WEBHOOK_HTTP_TIMEOUT', 15);
}
if (! defined('ADFLIPR_WEBHOOK_RETRY_MANUAL_MAX_LOOPS')) {
  /** Max cron-equivalent batches per manual / WP-CLI drain (each batch ≤ ADFLIPR_WEBHOOK_RETRY_BATCH_PER_RUN items). */
  define('ADFLIPR_WEBHOOK_RETRY_MANUAL_MAX_LOOPS', 10);
}
if (! defined('ADFLIPR_UTM_TRACKING_OPTION')) {
  /** Option value 1/0 — public UTM cookie script loads only when enabled (opt-in). */
  define('ADFLIPR_UTM_TRACKING_OPTION', 'adflipr_utm_tracking_enabled');
}
if (! defined('ADFLIPR_UTM_SESSION_KEY')) {
  /** WooCommerce session key for UTM attribution (survives checkout when cookies are not on the order request). */
  define('ADFLIPR_UTM_SESSION_KEY', 'adflipr_utm_tracking');
}
if (! defined('ADFLIPR_SAVE_CART_NONCE_ACTION')) {
  define('ADFLIPR_SAVE_CART_NONCE_ACTION', 'adflipr_save_cart');
}
if (! defined('ADFLIPR_SAVE_CART_RATE_PER_MINUTE')) {
  /** Max cart AJAX submissions per IP per minute (abuse protection). */
  define('ADFLIPR_SAVE_CART_RATE_PER_MINUTE', 40);
}
if (! defined('ADFLIPR_SAVE_CART_MAX_CONTENT_LENGTH')) {
  /** Reject cart AJAX if HTTP Content-Length exceeds this (bytes). */
  define('ADFLIPR_SAVE_CART_MAX_CONTENT_LENGTH', 524288);
}
if (! defined('ADFLIPR_ORDER_CHECKOUT_WEBHOOK_SENT_META')) {
  /** HPOS-safe order meta: checkout_purchased webhook accepted (HTTP 200 or queued). */
  define('ADFLIPR_ORDER_CHECKOUT_WEBHOOK_SENT_META', '_adflipr_checkout_webhook_sent');
}
if (! defined('ADFLIPR_LAST_WEBHOOK_SUCCESS_AT_OPTION')) {
  /** Unix time of last outbound webhook that returned HTTP 200 from Adflipr (diagnostics only). */
  define('ADFLIPR_LAST_WEBHOOK_SUCCESS_AT_OPTION', 'adflipr_last_webhook_success_at');
}
if (! defined('ADFLIPR_LAST_WEBHOOK_SUCCESS_ACTION_OPTION')) {
  /** Payload action key for the last successful webhook (diagnostics only). */
  define('ADFLIPR_LAST_WEBHOOK_SUCCESS_ACTION_OPTION', 'adflipr_last_webhook_success_action');
}
if (! defined('ADFLIPR_LAST_CART_EVENT_OPTION')) {
  /** Unix time of last cart_abandonment durably sent or queued (autoload no). */
  define('ADFLIPR_LAST_CART_EVENT_OPTION', 'adflipr_last_cart_event');
}
if (! defined('ADFLIPR_LAST_ORDER_EVENT_OPTION')) {
  /** Unix time of last checkout_purchased durably sent or queued (autoload no). */
  define('ADFLIPR_LAST_ORDER_EVENT_OPTION', 'adflipr_last_order_event');
}
if (! defined('ADFLIPR_LAST_RETRY_RUN_OPTION')) {
  /** Unix time the outbound retry drain last ran (cron/manual/CLI; autoload no). */
  define('ADFLIPR_LAST_RETRY_RUN_OPTION', 'adflipr_last_retry_run');
}
if (! defined('ADFLIPR_RECOVERY_CART_SESSION_KEY')) {
  /** WC session key: stable cart_hash sent to Adflipr for the abandonment journey. */
  define('ADFLIPR_RECOVERY_CART_SESSION_KEY', 'adflipr_recovery_cart_hash');
}
if (! defined('ADFLIPR_RECOVERY_CART_URL_PARAM')) {
  /** Query arg on recovery URLs to restore the stable cart_hash into WC session. */
  define('ADFLIPR_RECOVERY_CART_URL_PARAM', 'adflipr_recovery_cart');
}
if (! defined('ADFLIPR_RECOVERY_CART_ORDER_META')) {
  /** Order meta: stable recovery cart_hash at purchase. */
  define('ADFLIPR_RECOVERY_CART_ORDER_META', '_adflipr_cart_hash');
}

if (! class_exists('AdFlipr_Core')) {

  /**
   * Class AdFlipr_Core
   */
  class AdFlipr_Core
  {

    /**
     * @var null
     */
    public static $_instance = null;

    /**
     * @var array
     */
    private static $_registered_entity = array(
      'active'   => array(),
      'inactive' => array(),
    );

    /**
     * Registered module instances (keys match AdFlipr_Core::register names).
     *
     * @var AdFlipr_Admin|null
     */
    public $admin;

    /** @var AdFlipr_Auth|null */
    public $auth;

    /** @var AdFlipr_Data|null */
    public $data;

    /** @var Adflipr_Cart_Tracker|null */
    public $cart_tracker;

    /** @var Adflipr_Product_Price_Tracker|null */
    public $product_price_tracker;

    /** @var Adflipr_Product_Sku_Tracker|null */
    public $product_sku_tracker;

    /** @var AdFlipr_Health_Service|null */
    public $health;

    /** @var Adflipr_Popup_Embed|null */
    public $popup_embed;

    /** @var Adflipr_Push_Sw|null */
    public $push_sw;

    /** @var Adflipr_Push_Embed|null */
    public $push_embed;

    /**
     * AdFlipr_Core constructor.
     */
    public function __construct()
    {
      $this->define_plugin_properties();
      $this->load_classes();
    }

    public function define_plugin_properties()
    {

      define('ADFLIPR_VERSION', ADFLIPR_PLUGIN_VERSION);
      define('ADFLIPR_SLUG', 'adflipr-for-woocommerce');
      define('ADFLIPR_PLUGIN_FILE', __FILE__);
      define('ADFLIPR_PLUGIN_DIR', __DIR__);
      define('ADFLIPR_PLUGIN_URL', untrailingslashit(plugin_dir_url(ADFLIPR_PLUGIN_FILE)));
      define('ADFLIPR_PLUGIN_BASENAME', plugin_basename(__FILE__));
      add_action('plugins_loaded', array($this, 'register_classes'), 1);
      add_action('admin_notices', 'adflipr_maybe_requirements_notice');
    }

    public function load_classes()
    {
      require __DIR__ . '/includes/class-adflipr-health-service.php';
      require __DIR__ . '/includes/class-adflipr-auth.php';
      require __DIR__ . '/includes/class-adflipr-data.php';
      require __DIR__ . '/includes/class-adflipr-admin-page.php';
      require __DIR__ . '/includes/class-adflipr-recovery-cart-hash.php';
      require __DIR__ . '/includes/class-adflipr-cart-tracker.php';
      require __DIR__ . '/includes/class-adflipr-product-price-tracker.php';
      require __DIR__ . '/includes/class-adflipr-product-sku-tracker.php';
      require __DIR__ . '/includes/class-adflipr-popup-embed.php';
      require __DIR__ . '/includes/class-adflipr-push-sw.php';
      require __DIR__ . '/includes/class-adflipr-push-embed.php';
    }

    /**
     * @return AdFlipr_Core|null
     */
    public static function get_instance()
    {
      if (null === self::$_instance) {
        self::$_instance = new self;
      }

      return self::$_instance;
    }

    /**
     * Register classes
     */
    public function register_classes()
    {

      $load_classes = self::get_registered_class();
      if (is_array($load_classes) && count($load_classes) > 0) {
        foreach ($load_classes as $access_key => $class) {
          $this->$access_key = $class::get_instance();
        }
      }
    }

    /**
     * @return mixed
     */
    public static function get_registered_class()
    {
      return self::$_registered_entity['active'];
    }

    public static function register($short_name, $class)
    {

      self::$_registered_entity['active'][$short_name] = $class;
    }
  }
}
if (! function_exists('AdFlipr_Core')) {
  /**
   * @return AdFlipr_Core|null
   */
  function AdFlipr_Core()
  {
    return AdFlipr_Core::get_instance();
  }
}

$GLOBALS['AdFlipr_Core'] = AdFlipr_Core();

require_once __DIR__ . '/includes/adflipr-upgrade.php';

/**
 * Schedule periodic drain of the outbound WooCommerce webhook retry queue.
 */
function adflipr_schedule_webhook_retry_cron()
{
  if (! defined('ADFLIPR_WEBHOOK_RETRY_CRON_HOOK')) {
    return;
  }
  if (! wp_next_scheduled(ADFLIPR_WEBHOOK_RETRY_CRON_HOOK)) {
    wp_schedule_event(time() + 120, 'adflipr_five_minutes', ADFLIPR_WEBHOOK_RETRY_CRON_HOOK);
  }
}

/**
 * Plugin activation: requirements check, then webhook retry cron.
 */
function adflipr_on_activate() 
{
  if (! adflipr_meets_requirements()) {
    if (! function_exists('deactivate_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    deactivate_plugins(ADFLIPR_PLUGIN_BASENAME);
    wp_die(
      wp_kses_post('<div class="wrap">' . adflipr_requirements_fail_html() . '</div>'),
      esc_html__('Plugin activation blocked', 'adflipr-for-woocommerce'),
      array(
        'response' => 200,
        'back_link' => true,
      )
    );
  }

  adflipr_schedule_webhook_retry_cron();
  delete_option('adflipr_disabled_wc_emails');
  delete_option('adflipr_disabled_wc_emails_pending');

  if (function_exists('adflipr_run_install_telemetry_on_activate')) {
    adflipr_run_install_telemetry_on_activate();
  }
}

/**
 * Plugin deactivation: clear webhook retry cron (queue option is retained).
 */
function adflipr_on_deactivate()
{
  wp_clear_scheduled_hook('adflipr_install_telemetry_debug_tick');
  delete_option('adflipr_install_telemetry_debug_snapshot');

  if (defined('ADFLIPR_WEBHOOK_RETRY_CRON_HOOK')) {
    wp_clear_scheduled_hook(ADFLIPR_WEBHOOK_RETRY_CRON_HOOK);
  }
  // Bulk sync uses singles on `adflipr_send_data_event`; clear orphans on deactivate.
  if (function_exists('wp_clear_scheduled_hook')) {
    wp_clear_scheduled_hook('adflipr_send_data_event');
  }
}

register_activation_hook(ADFLIPR_PLUGIN_FILE, 'adflipr_on_activate');
register_deactivation_hook(ADFLIPR_PLUGIN_FILE, 'adflipr_on_deactivate');
