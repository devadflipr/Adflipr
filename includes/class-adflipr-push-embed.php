<?php
/**
 * Storefront Web Push embed — loads adflipr-push-embed.js next to the popup loader.
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

if (! class_exists('Adflipr_Push_Embed')) {

  class Adflipr_Push_Embed
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
      add_action('wp_enqueue_scripts', array($this, 'enqueue_storefront_embed'), 21);
    }

    public function enqueue_storefront_embed()
    {
      if (! function_exists('adflipr_popup_embed_should_load') || ! adflipr_popup_embed_should_load()) {
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
      $embed_version = defined('ADFLIPR_VERSION') ? ADFLIPR_VERSION : '1';
      $script_src = rtrim($app_base, '/') . '/app/adflipr-push-embed.js?v=' . rawurlencode($embed_version);
      $sw_url     = home_url('/adflipr-push-sw.js');
      $wait       = function_exists('adflipr_popup_embed_wait_for_consent') && adflipr_popup_embed_wait_for_consent();

      wp_register_script('adflipr-push-embed-loader', false, array(), ADFLIPR_VERSION, true);
      wp_enqueue_script('adflipr-push-embed-loader');
      wp_add_inline_script(
        'adflipr-push-embed-loader',
        sprintf(
          '(function(){var api=%2$s,site=%3$s,src=%1$s,sw=%5$s,wait=%4$s;function load(){if(window.__adfliprPushEmbedLoaded){return;}window.__adfliprPushEmbedLoaded=true;var s=document.createElement("script");s.src=src;s.async=true;s.setAttribute("data-adflipr-popup-api",api);s.setAttribute("data-adflipr-site",site);s.setAttribute("data-adflipr-push-sw",sw);document.head.appendChild(s);}if(window.adfliprPopupEmbedConsent===false){return;}if(window.adfliprPopupEmbedConsent===true||!wait){load();return;}document.addEventListener("adflipr:popup-embed:allow",load,{once:true});})();',
          wp_json_encode($script_src),
          wp_json_encode($api_base),
          wp_json_encode($site_id),
          $wait ? 'true' : 'false',
          wp_json_encode($sw_url)
        )
      );
    }
  }
}

if (class_exists('AdFlipr_Core') && class_exists('Adflipr_Push_Embed')) {
  AdFlipr_Core::register('push_embed', 'Adflipr_Push_Embed');
}
