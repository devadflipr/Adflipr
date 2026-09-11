<?php
/**
 * Storefront popup embed — helpers and loader.
 *
 * Popups load only when the merchant has connected Adflipr and enabled this feature
 * (auto-enabled on connect; disabled on disconnect). Merchants using a cookie-consent
 * plugin may defer loading via {@see adflipr_popup_embed_wait_for_consent()} or the
 * {@code adflipr:popup-embed:allow} DOM event.
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

if (! defined('ADFLIPR_POPUP_EMBED_OPTION')) {
  /** 1/0 — storefront popup script (opt-in; enabled automatically on Adflipr connect). */
  define('ADFLIPR_POPUP_EMBED_OPTION', 'adflipr_popup_embed_enabled');
}

/**
 * Whether the merchant has enabled the storefront popup embed in WordPress.
 */
function adflipr_popup_embed_option_enabled()
{
  $flag = get_option(ADFLIPR_POPUP_EMBED_OPTION, '0');

  return $flag === '1' || $flag === 1 || $flag === true;
}

/**
 * When true, the loader waits for {@code window.adfliprPopupEmbedConsent = true}
 * or a one-time {@code adflipr:popup-embed:allow} event before fetching the script.
 *
 * @return bool
 */
function adflipr_popup_embed_wait_for_consent()
{
  /**
   * Return true to defer popup embed until consent is granted in the browser.
   *
   * Cookie-banner plugins can listen for their own consent and then dispatch:
   *   document.dispatchEvent(new Event('adflipr:popup-embed:allow'));
   */
  return (bool) apply_filters('adflipr_popup_embed_wait_for_consent', false);
}

/**
 * Whether the Adflipr popup embed script should load on this request.
 */
function adflipr_popup_embed_should_load()
{
  if (is_admin()) {
    return false;
  }

  if (! function_exists('adflipr_integration_is_active') || ! adflipr_integration_is_active()) {
    return false;
  }

  if (! adflipr_popup_embed_option_enabled()) {
    return false;
  }

  /**
   * Final gate for themes, cookie-consent plugins, or custom rules.
   *
   * @param bool $load Default true when integration is active and the option is on.
   */
  return (bool) apply_filters('adflipr_popup_embed_enabled', true);
}

/**
 * Enable storefront popup embed (called on successful Adflipr connect).
 */
function adflipr_enable_popup_embed_option()
{
  update_option(ADFLIPR_POPUP_EMBED_OPTION, '1', false);
}

/**
 * Disable storefront popup embed (disconnect / merchant opt-out).
 */
function adflipr_disable_popup_embed_option()
{
  update_option(ADFLIPR_POPUP_EMBED_OPTION, '0', false);
}

if (! class_exists('Adflipr_Popup_Embed')) {

  class Adflipr_Popup_Embed
  {
    /** @var self|null */
    public static $_instance = null;

    public static function get_instance()
    {
      if (null === self::$_instance) {
        self::$_instance = new self();
      }

      return self::$_instance;
    }

    private function __construct()
    {
      add_action('wp_enqueue_scripts', array($this, 'enqueue_storefront_embed'), 20);
    }

    /**
     * Inject async Adflipr popup overlay loader (data-adflipr-site = store UUID).
     */
    public function enqueue_storefront_embed()
    {
      if (! adflipr_popup_embed_should_load()) {
        return;
      }

      if (! function_exists('adflipr_ensure_store_uuid')) {
        require_once ADFLIPR_PLUGIN_DIR . '/includes/adflipr-install-telemetry.php';
      }

      $site_id = adflipr_ensure_store_uuid();
      if (! is_string($site_id) || $site_id === '') {
        return;
      }

      $api_base   = function_exists('adflipr_api_url') ? adflipr_api_url('api/v1') : rtrim((string) ADFLIPR_API_BASE_URL, '/') . '/api/v1';
      $app_base   = defined('ADFLIPR_APP_BASE_URL') ? (string) ADFLIPR_APP_BASE_URL : (string) ADFLIPR_API_BASE_URL;
      // Cache-bust the stable embed filename on each plugin release; the app host also sends
      // a revalidation Cache-Control header so existing installs pick up new deploys.
      $embed_version = defined('ADFLIPR_VERSION') ? ADFLIPR_VERSION : '1';
      $script_src = rtrim($app_base, '/') . '/app/adflipr-popup-embed.js?v=' . rawurlencode($embed_version);
      $wait       = adflipr_popup_embed_wait_for_consent();

      wp_register_script('adflipr-popup-embed-loader', false, array(), ADFLIPR_VERSION, true);
      wp_enqueue_script('adflipr-popup-embed-loader');
      wp_add_inline_script(
        'adflipr-popup-embed-loader',
        sprintf(
          '(function(){var api=%2$s,site=%3$s,src=%1$s,wait=%4$s;function load(){if(window.__adfliprPopupEmbedLoaded){return;}window.__adfliprPopupEmbedLoaded=true;var s=document.createElement("script");s.src=src;s.async=true;s.setAttribute("data-adflipr-popup-api",api);s.setAttribute("data-adflipr-site",site);document.head.appendChild(s);}if(window.adfliprPopupEmbedConsent===false){return;}if(window.adfliprPopupEmbedConsent===true||!wait){load();return;}document.addEventListener("adflipr:popup-embed:allow",load,{once:true});})();',
          wp_json_encode($script_src),
          wp_json_encode($api_base),
          wp_json_encode($site_id),
          $wait ? 'true' : 'false'
        )
      );
    }
  }
}

if (class_exists('AdFlipr_Core') && class_exists('Adflipr_Popup_Embed')) {
  AdFlipr_Core::register('popup_embed', 'Adflipr_Popup_Embed');
}
