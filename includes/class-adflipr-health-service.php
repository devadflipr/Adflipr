<?php
/**
 * Lightweight operational health metrics (timestamps/counts only — no payloads, no PII).
 *
 * Used by REST integration-health and internal activity timestamps. Options use
 * update_option( ..., false ) so autoload stays off. Values are not a durable audit trail:
 * they answer “what is the latest known signal?” for support and merchant trust.
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

if (! class_exists('AdFlipr_Health_Service')) {

  /**
   * Collects and updates tiny health markers. Intentionally minimal — no history tables.
   */
  class AdFlipr_Health_Service
  {

    /** If the retry queue has items but the processor has not run recently, surface a warning. */
    private const RETRY_RUN_STALE_SECONDS = 900;

    private static $ins = null;

    public static function get_instance()
    {
      if (null === self::$ins) {
        self::$ins = new self();
      }

      return self::$ins;
    }

    /**
     * Record HTTP 200 outbound webhook (reuses existing success options for REST parity).
     *
     * @param array<string, mixed> $data Payload metadata only; action key is stored, not body.
     */
    public function update_last_successful_webhook(array $data)
    {
      if (! defined('ADFLIPR_LAST_WEBHOOK_SUCCESS_AT_OPTION')
        || ! defined('ADFLIPR_LAST_WEBHOOK_SUCCESS_ACTION_OPTION')) {
        return;
      }

      update_option(ADFLIPR_LAST_WEBHOOK_SUCCESS_AT_OPTION, time(), false);
      $action = isset($data['action']) ? (string) $data['action'] : '';
      update_option(ADFLIPR_LAST_WEBHOOK_SUCCESS_ACTION_OPTION, $action, false);

      if (defined('ADFLIPR_DEBUG') && ADFLIPR_DEBUG && function_exists('adflipr_debug_log')) {
        adflipr_debug_log('health_webhook_success_tick', array('action' => $action));
      }
    }

    /** Cart abandonment payload durably sent or queued (timestamp only). */
    public function update_last_cart_event()
    {
      if (! defined('ADFLIPR_LAST_CART_EVENT_OPTION')) {
        return;
      }
      update_option(ADFLIPR_LAST_CART_EVENT_OPTION, time(), false);
    }

    /** checkout_purchased durably sent or queued (timestamp only). */
    public function update_last_order_event()
    {
      if (! defined('ADFLIPR_LAST_ORDER_EVENT_OPTION')) {
        return;
      }
      update_option(ADFLIPR_LAST_ORDER_EVENT_OPTION, time(), false);
    }

    /**
     * Retry drain ran (cron, manual REST/AJAX, or CLI). Called at processor entry — even if queue empty.
     */
    public function update_last_retry_run()
    {
      if (! defined('ADFLIPR_LAST_RETRY_RUN_OPTION')) {
        return;
      }
      update_option(ADFLIPR_LAST_RETRY_RUN_OPTION, time(), false);
    }

    /**
     * Oldest queued_at among valid queue rows (for “stuck queue” age). No payload inspection.
     *
     * @param array<int, array<string, mixed>> $queue Normalized queue list.
     *
     * @return int|null Unix timestamp of oldest pending row, or null if none.
     */
    private function oldest_queued_at_unix(array $queue)
    {
      $oldest = null;
      foreach ($queue as $item) {
        if (! is_array($item)) {
          continue;
        }
        $t = isset($item['queued_at']) ? (int) $item['queued_at'] : 0;
        if ($t <= 0) {
          continue;
        }
        if ($oldest === null || $t < $oldest) {
          $oldest = $t;
        }
      }

      return $oldest;
    }

    /**
     * Snapshot for admin UI / REST (scalars and short strings only).
     *
     * @return array<string, mixed>
     */
    public function get_health_status()
    {
      $connected = false;
      if (function_exists('AdFlipr_Core') && isset(AdFlipr_Core()->auth)) {
        $connected = AdFlipr_Core()->auth->valid_token();
      }

      $token_expiry_raw = get_option('adflipr_token_expiry', false);
      $token_expiry_unix = null;
      $token_expiry_display = null;
      if ($token_expiry_raw !== false && $token_expiry_raw !== '' && is_numeric($token_expiry_raw)) {
        $ms = (int) $token_expiry_raw;
        $token_expiry_unix = (int) floor($ms / 1000);
        if ($token_expiry_unix > 0) {
          $token_expiry_display = wp_date(get_option('date_format'), $token_expiry_unix);
        }
      }

      $queue_count = 0;
      $oldest_queued_unix = null;
      if (function_exists('AdFlipr_Core') && isset(AdFlipr_Core()->data)) {
        $queue_count = (int) AdFlipr_Core()->data->get_webhook_retry_queue_count();
      }
      if ($queue_count > 0 && defined('ADFLIPR_WEBHOOK_RETRY_QUEUE_OPTION')) {
        $raw = get_option(ADFLIPR_WEBHOOK_RETRY_QUEUE_OPTION, array());
        if (is_array($raw) && function_exists('adflipr_queue_normalize_storage')) {
          $storage = adflipr_queue_normalize_storage($raw);
          $oldest_queued_unix = $this->oldest_queued_at_unix($storage['queue']);
        }
      }

      $now = time();
      $last_webhook = defined('ADFLIPR_LAST_WEBHOOK_SUCCESS_AT_OPTION')
        ? (int) get_option(ADFLIPR_LAST_WEBHOOK_SUCCESS_AT_OPTION, 0)
        : 0;
      $last_cart = defined('ADFLIPR_LAST_CART_EVENT_OPTION')
        ? (int) get_option(ADFLIPR_LAST_CART_EVENT_OPTION, 0)
        : 0;
      $last_order = defined('ADFLIPR_LAST_ORDER_EVENT_OPTION')
        ? (int) get_option(ADFLIPR_LAST_ORDER_EVENT_OPTION, 0)
        : 0;
      $last_retry = defined('ADFLIPR_LAST_RETRY_RUN_OPTION')
        ? (int) get_option(ADFLIPR_LAST_RETRY_RUN_OPTION, 0)
        : 0;

      $wc_active = class_exists('WooCommerce');
      $hpos_enabled = false;
      if ($wc_active && class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
        $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
      }

      $checkout_mode = $this->detect_checkout_mode();
      $checkout_page_id = 0;
      if ($wc_active && function_exists('wc_get_page_id')) {
        $checkout_page_id = (int) wc_get_page_id('checkout');
      }
      $save_cart_registered = has_action('wp_ajax_adflipr_save_cart')
        && has_action('wp_ajax_nopriv_adflipr_save_cart');

      $wp_cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
      $retry_stale = $queue_count > 0
        && ($last_retry <= 0 || ($now - $last_retry) > self::RETRY_RUN_STALE_SECONDS);

      $queue_stuck_level = 'ok';
      if ($queue_count > 0 && $oldest_queued_unix !== null && ($now - $oldest_queued_unix) > self::RETRY_RUN_STALE_SECONDS) {
        $queue_stuck_level = 'warn';
      }
      if ($retry_stale) {
        $queue_stuck_level = 'warn';
      }

      $cron_level = 'ok';
      if ($wp_cron_disabled) {
        $cron_level = 'warn';
      }
      if ($retry_stale && $queue_count > 0) {
        $cron_level = 'warn';
      }

      return array(
        'connection' => array(
          'connected' => $connected,
          'token_expiry_unix' => $token_expiry_unix,
          'token_expiry_display' => $token_expiry_display,
        ),
        'retry_queue' => array(
          'pending_count' => $queue_count,
          'oldest_queued_unix' => $oldest_queued_unix,
          'queue_stuck_level' => $queue_stuck_level,
        ),
        'activity' => array(
          'last_webhook_success_unix' => $last_webhook > 0 ? $last_webhook : null,
          'last_cart_event_unix' => $last_cart > 0 ? $last_cart : null,
          'last_order_event_unix' => $last_order > 0 ? $last_order : null,
          'last_retry_run_unix' => $last_retry > 0 ? $last_retry : null,
        ),
        'environment' => array(
          'woocommerce_active' => $wc_active,
          'hpos_enabled' => $hpos_enabled,
          'checkout_mode' => $checkout_mode,
          'checkout_page_configured' => $checkout_page_id > 0,
          'save_cart_ajax_registered' => (bool) $save_cart_registered,
          'wp_cron_disabled' => $wp_cron_disabled,
          'cron_health_level' => $cron_level,
        ),
      );
    }

    /**
     * Block vs classic checkout from the checkout page content (best-effort; no remote calls).
     *
     * @return string block|classic|unknown
     */
    private function detect_checkout_mode()
    {
      if (! function_exists('wc_get_page_id')) {
        return 'unknown';
      }
      $page_id = (int) wc_get_page_id('checkout');
      if ($page_id <= 0) {
        return 'unknown';
      }
      $post = get_post($page_id);
      if (! $post instanceof WP_Post) {
        return 'unknown';
      }
      if (function_exists('has_block') && has_block('woocommerce/checkout', $post)) {
        return 'block';
      }
      $content = (string) $post->post_content;
      if (strpos($content, 'woocommerce/checkout') !== false) {
        return 'block';
      }
      if (strpos($content, 'woocommerce/classic-shortcode') !== false
        || strpos($content, '[woocommerce_checkout') !== false) {
        return 'classic';
      }

      return 'unknown';
    }
  }

  if (class_exists('AdFlipr_Core')) {
    AdFlipr_Core::register('health', 'AdFlipr_Health_Service');
  }
}
