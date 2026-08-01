<?php
/**
 * Lightweight install telemetry (activation hook only).
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

/** WordPress option key for the persistent per-install store UUID. */
const ADFLIPR_STORE_UUID_OPTION = 'adflipr_store_uuid';

/**
 * Install telemetry endpoint (POST /api/v1/wp/plugin/install).
 * Override in wp-config.php before this file loads if needed.
 */
if (! defined('ADFLIPR_INSTALL_TELEMETRY_URL')) {
  define('ADFLIPR_INSTALL_TELEMETRY_URL', adflipr_api_url('api/v1/wp/plugin/install'));
}

/**
 * Ensure a persistent store UUID exists for this WordPress install.
 *
 * @return string UUID v4.
 */
function adflipr_ensure_store_uuid()
{
  $uuid = get_option(ADFLIPR_STORE_UUID_OPTION, false);
  if (is_string($uuid) && $uuid !== '') {
    return $uuid;
  }

  $uuid = wp_generate_uuid4();
  if (false === get_option(ADFLIPR_STORE_UUID_OPTION, false)) {
    add_option(ADFLIPR_STORE_UUID_OPTION, $uuid, '', false);
  } else {
    update_option(ADFLIPR_STORE_UUID_OPTION, $uuid, false);
  }

  return $uuid;
}

/**
 * Safely read the active WooCommerce version without fatal errors.
 *
 * @return string Empty when WooCommerce is unavailable.
 */
function adflipr_get_woocommerce_version_safe()
{
  if (defined('WC_VERSION')) {
    return (string) WC_VERSION;
  }

  if (function_exists('WC')) {
    $wc = WC();
    if (is_object($wc) && isset($wc->version)) {
      return (string) $wc->version;
    }
  }

  return '';
}

/**
 * Build the install telemetry payload for plugin activation.
 *
 * @param string|null $store_uuid Optional pre-resolved store UUID.
 * @return array<string, string>
 */
function adflipr_build_install_payload($store_uuid = null)
{
  if (! is_string($store_uuid) || $store_uuid === '') {
    $store_uuid = adflipr_ensure_store_uuid();
  }

  $plugin_version = '';
  if (defined('ADFLIPR_PLUGIN_VERSION')) {
    $plugin_version = (string) ADFLIPR_PLUGIN_VERSION;
  } elseif (defined('ADFLIPR_VERSION')) {
    $plugin_version = (string) ADFLIPR_VERSION;
  }

  $admin_email = sanitize_email((string) get_option('admin_email'));

  return array(
    'store_uuid'          => $store_uuid,
    'site_name'           => sanitize_text_field((string) get_bloginfo('name')),
    'site_url'            => (string) site_url(),
    'home_url'            => (string) home_url(),
    'wordpress_version'   => (string) get_bloginfo('version'),
    'woocommerce_version' => adflipr_get_woocommerce_version_safe(),
    'plugin_version'      => $plugin_version,
    'admin_email'         => $admin_email,
    'activated_at'        => gmdate('c'),
  );
}

/**
 * Best-effort non-blocking POST of install telemetry (never throws).
 *
 * @param array<string, string> $payload Install telemetry payload.
 */
function adflipr_send_install_telemetry(array $payload)
{
  if (! function_exists('wp_remote_post')) {
    return;
  }

  $url = defined('ADFLIPR_INSTALL_TELEMETRY_URL') ? (string) ADFLIPR_INSTALL_TELEMETRY_URL : '';
  if ($url === '') {
    return;
  }

  $response = wp_remote_post(
    $url,
    array(
      'timeout'  => 3,
      'blocking' => false,
      'headers'  => array(
        'Content-Type' => 'application/json; charset=utf-8',
      ),
      'body'     => wp_json_encode($payload),
    )
  );

}

/**
 * Run install telemetry during plugin activation (requirements must already pass).
 */
function adflipr_run_install_telemetry_on_activate()
{
  $store_uuid = adflipr_ensure_store_uuid();
  $payload = adflipr_build_install_payload($store_uuid);

  /**
   * Fires after AdFlipr plugin activation requirements pass and the install payload is built.
   *
   * @param array<string, string> $payload Install telemetry payload.
   */
  do_action('adflipr_plugin_activated', $payload);

  adflipr_send_install_telemetry($payload);
}
