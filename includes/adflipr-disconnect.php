<?php
/**
 * Soft disconnect helpers — bidirectional trust sync with AdFlipr backend.
 *
 * Soft disconnect stops tokens, sync, and queue processing but keeps storeUuid,
 * WooCommerce API keys, and AdFlipr analytics continuity for reconnect.
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

if (! defined('ADFLIPR_INTEGRATION_ACTIVE_OPTION')) {
  define('ADFLIPR_INTEGRATION_ACTIVE_OPTION', 'adflipr_integration_active');
}

if (! defined('ADFLIPR_DISCONNECT_SIGNING_KEY_OPTION')) {
  define('ADFLIPR_DISCONNECT_SIGNING_KEY_OPTION', 'adflipr_disconnect_signing_key');
}

if (! defined('ADFLIPR_DISCONNECT_NOTICE_TRANSIENT')) {
  define('ADFLIPR_DISCONNECT_NOTICE_TRANSIENT', 'adflipr_disconnect_notice');
}

if (! defined('ADFLIPR_DISCONNECT_SIGNING_SALT')) {
  define('ADFLIPR_DISCONNECT_SIGNING_SALT', 'adflipr-wc-disconnect-v1');
}

/**
 * Derive HMAC signing key from WooCommerce consumer secret (matches Java backend).
 *
 * @param string $consumer_secret Plaintext consumer secret.
 * @return string Hex-encoded signing key.
 */
function adflipr_store_disconnect_signing_key($consumer_secret)
{
  return hash_hmac('sha256', (string) $consumer_secret, ADFLIPR_DISCONNECT_SIGNING_SALT);
}

/**
 * Whether the plugin should send webhooks / process retry queue.
 */
function adflipr_integration_is_active()
{
  $flag = get_option(ADFLIPR_INTEGRATION_ACTIVE_OPTION, '1');
  return $flag === '1' || $flag === 1 || $flag === true;
}

/**
 * Parse AdFlipr API JSON body for integration_deleted.
 *
 * @param int    $http_code Response code.
 * @param string $body      Raw body.
 * @return bool
 */
function adflipr_response_is_integration_deleted($http_code, $body)
{
  if ((int) $http_code !== 403 || ! is_string($body) || $body === '') {
    return false;
  }
  $json = json_decode($body, true);
  if (! is_array($json)) {
    return false;
  }

  return isset($json['code']) && $json['code'] === 'integration_deleted';
}

/**
 * Verify remote disconnect callback headers from AdFlipr.
 *
 * @param string $store_uuid Store UUID.
 * @param int    $timestamp  Unix seconds.
 * @param string $signature  Hex HMAC from header.
 * @return bool
 */
/**
 * Soft disconnect without AdFlipr_Core (uninstall-safe).
 *
 * @param string $reason local|remote|uninstall
 */
function adflipr_run_soft_disconnect($reason = 'local')
{
  delete_option('adflipr_auth_token');
  delete_option('adflipr_token_expiry');
  update_option(ADFLIPR_INTEGRATION_ACTIVE_OPTION, '0', false);

  if (function_exists('adflipr_disable_popup_embed_option')) {
    adflipr_disable_popup_embed_option();
  }

  if (defined('ADFLIPR_WEBHOOK_RETRY_QUEUE_OPTION')) {
    delete_option(ADFLIPR_WEBHOOK_RETRY_QUEUE_OPTION);
  }
  delete_option('adflipr_disabled_wc_emails');
  delete_option('adflipr_disabled_wc_emails_pending');

  set_transient(
    ADFLIPR_DISCONNECT_NOTICE_TRANSIENT,
    sanitize_key($reason),
    DAY_IN_SECONDS
  );
}

/**
 * @param string|false $token Bearer token.
 */
function adflipr_notify_adflipr_disconnect($token)
{
  if (! $token || ! function_exists('wp_remote_request') || ! defined('ADFLIPR_API_DISCONNECT_URL')) {
    return;
  }

  wp_remote_request(
    ADFLIPR_API_DISCONNECT_URL,
    array(
      'method'  => 'PUT',
      'headers' => array(
        'Content-Type'  => 'application/json',
        'Authorization' => $token,
      ),
      'timeout' => 30,
    )
  );
}

function adflipr_verify_remote_disconnect_signature($store_uuid, $timestamp, $signature)
{
  $signing_key = get_option(ADFLIPR_DISCONNECT_SIGNING_KEY_OPTION, '');
  if (! is_string($signing_key) || $signing_key === '' || ! is_string($store_uuid) || $store_uuid === '') {
    return false;
  }

  $ts = (int) $timestamp;
  if ($ts <= 0 || abs(time() - $ts) > 300) {
    return false;
  }

  $payload   = $store_uuid . '|' . $ts;
  $expected  = hash_hmac('sha256', $payload, $signing_key);
  $provided  = is_string($signature) ? strtolower(trim($signature)) : '';

  return $provided !== '' && hash_equals($expected, $provided);
}
