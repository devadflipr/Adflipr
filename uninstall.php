<?php
/**
 * AdFlipr uninstall cleanup (options, cron, optional remote disconnect).
 *
 * @package AdFlipr
 */

if (! defined('WP_UNINSTALL_PLUGIN')) {
  exit;
}

require_once dirname(__FILE__) . '/includes/adflipr-api-config.php';
require_once dirname(__FILE__) . '/includes/adflipr-disconnect.php';
require_once dirname(__FILE__) . '/includes/adflipr-debug.php';

adflipr_debug_log('uninstall_start', array());

$adflipr_token = get_option('adflipr_auth_token', false);
if (! $adflipr_token) {
  adflipr_debug_log('uninstall_skip_disconnect', array('reason' => 'no_token'));
}

if ($adflipr_token) {
  adflipr_debug_log('uninstall_disconnect_request', array());
  adflipr_notify_adflipr_disconnect($adflipr_token);
}

adflipr_run_soft_disconnect('uninstall');

$adflipr_option_keys = array(
  'adflipr_auth_token',
  'adflipr_token_expiry',
  'adflipr_store_uuid',
  'adflipr_products_last_offset',
  'adflipr_orders_last_offset',
  'adflipr_coupons_last_offset',
  'adflipr_subscriptions_last_offset',
  'adflipr_disabled_wc_emails',
  'adflipr_disabled_wc_emails_pending',
  'adflipr_webhook_retry_queue',
  'adflipr_last_webhook_success_at',
  'adflipr_last_webhook_success_action',
  'adflipr_last_cart_event',
  'adflipr_last_order_event',
  'adflipr_last_retry_run',
  'adflipr_utm_tracking_enabled',
  'adflipr_popup_embed_enabled',
  'adflipr_migrations',
  ADFLIPR_INTEGRATION_ACTIVE_OPTION,
  ADFLIPR_DISCONNECT_SIGNING_KEY_OPTION,
);

foreach ($adflipr_option_keys as $adflipr_option_key) {
  delete_option($adflipr_option_key);
  if (is_multisite()) {
    delete_site_option($adflipr_option_key);
  }
}

if (function_exists('wp_clear_scheduled_hook')) {
  wp_clear_scheduled_hook('adflipr_send_data_event');
  wp_clear_scheduled_hook('adflipr_webhook_retry_process');
}

adflipr_debug_log('uninstall_complete', array());
