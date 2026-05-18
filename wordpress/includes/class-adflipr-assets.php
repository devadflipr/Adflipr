<?php

defined('ABSPATH') || exit;

/**
 * Discovers content-hashed build assets via build/asset-manifest.json.
 *
 * Falls back to legacy build/index.js + index.css when manifest is missing (local dev).
 */
if (! class_exists('AdFlipr_Assets')) {
  class AdFlipr_Assets
  {
    private const MANIFEST_REL_PATH = '/build/asset-manifest.json';

    private const LEGACY_JS = '/build/index.js';

    private const LEGACY_CSS = array(
      '/build/index.css',
      '/build/style-index.css',
    );

    /** @var array<string, mixed>|null */
    private static $manifest = null;

    /** @var bool */
    private static $manifest_loaded = false;

    /**
     * @return array<string, mixed>|null
     */
    private static function load_manifest()
    {
      if (self::$manifest_loaded) {
        return self::$manifest;
      }

      self::$manifest_loaded = true;
      self::$manifest       = null;

      if (! defined('ADFLIPR_PLUGIN_DIR')) {
        return null;
      }

      $manifest_path = ADFLIPR_PLUGIN_DIR . self::MANIFEST_REL_PATH;
      if (! is_readable($manifest_path)) {
        return null;
      }

      $raw = file_get_contents($manifest_path);
      if (! is_string($raw) || $raw === '') {
        return null;
      }

      $decoded = json_decode($raw, true);
      if (! is_array($decoded)) {
        return null;
      }

      if (! self::is_valid_manifest($decoded)) {
        return null;
      }

      self::$manifest = $decoded;

      return self::$manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private static function is_valid_manifest(array $manifest): bool
    {
      if (! isset($manifest['js']) || ! is_string($manifest['js'])) {
        return false;
      }

      if (! self::is_safe_build_filename($manifest['js'])) {
        return false;
      }

      if (isset($manifest['css']) && ! is_array($manifest['css'])) {
        return false;
      }

      if (isset($manifest['css'])) {
        foreach ($manifest['css'] as $css_file) {
          if (! is_string($css_file) || ! self::is_safe_build_filename($css_file)) {
            return false;
          }
        }
      }

      if (isset($manifest['dependencies']) && ! is_array($manifest['dependencies'])) {
        return false;
      }

      if (isset($manifest['dependencies'])) {
        foreach ($manifest['dependencies'] as $dependency) {
          if (! is_string($dependency) || ! preg_match('/^[a-z0-9_-]+$/i', $dependency)) {
            return false;
          }
        }
      }

      return is_readable(ADFLIPR_PLUGIN_DIR . '/build/' . $manifest['js']);
    }

    private static function is_safe_build_filename(string $filename): bool
    {
      return (bool) preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*\.(js|css)$/', $filename);
    }

    /**
     * Whether a loadable admin React bundle exists.
     */
    public static function has_admin_bundle(): bool
    {
      $resolved = self::resolve_bundle();
      return $resolved !== null && is_readable($resolved['js_path']);
    }

    /**
     * @return array{
     *   js_url: string,
     *   js_path: string,
     *   css: list<array{url: string, path: string}>,
     *   dependencies: list<string>,
     *   version: string,
     *   hashed: bool
     * }|null
     */
    public static function resolve_bundle()
    {
      if (! defined('ADFLIPR_PLUGIN_DIR') || ! defined('ADFLIPR_PLUGIN_URL')) {
        return null;
      }

      $manifest = self::load_manifest();
      if (is_array($manifest)) {
        $js_path = ADFLIPR_PLUGIN_DIR . '/build/' . $manifest['js'];
        if (! is_readable($js_path)) {
          return null;
        }

        $css = array();
        if (! empty($manifest['css']) && is_array($manifest['css'])) {
          foreach ($manifest['css'] as $css_file) {
            $css_path = ADFLIPR_PLUGIN_DIR . '/build/' . $css_file;
            if (is_readable($css_path)) {
              $css[] = array(
                'url'  => ADFLIPR_PLUGIN_URL . '/build/' . $css_file,
                'path' => $css_path,
              );
            }
          }
        }

        $dependencies = array();
        if (! empty($manifest['dependencies']) && is_array($manifest['dependencies'])) {
          $dependencies = array_values(array_map('strval', $manifest['dependencies']));
        }

        $version = isset($manifest['version']) && is_string($manifest['version'])
          ? $manifest['version']
          : '';

        return array(
          'js_url'       => ADFLIPR_PLUGIN_URL . '/build/' . $manifest['js'],
          'js_path'      => $js_path,
          'css'          => $css,
          'dependencies' => $dependencies,
          'version'      => $version,
          'hashed'       => true,
        );
      }

      return self::resolve_legacy_bundle();
    }

    /**
     * @return array{
     *   js_url: string,
     *   js_path: string,
     *   css: list<array{url: string, path: string}>,
     *   dependencies: list<string>,
     *   version: string,
     *   hashed: bool
     * }|null
     */
    private static function resolve_legacy_bundle()
    {
      $js_path = ADFLIPR_PLUGIN_DIR . self::LEGACY_JS;
      if (! is_readable($js_path)) {
        return null;
      }

      $css = array();
      foreach (self::LEGACY_CSS as $rel) {
        $css_path = ADFLIPR_PLUGIN_DIR . $rel;
        if (is_readable($css_path)) {
          $css[] = array(
            'url'  => ADFLIPR_PLUGIN_URL . $rel,
            'path' => $css_path,
          );
        }
      }

      $dependencies = self::read_legacy_asset_dependencies();
      $version      = defined('ADFLIPR_VERSION') ? ADFLIPR_VERSION : '1.0.0';

      return array(
        'js_url'       => ADFLIPR_PLUGIN_URL . self::LEGACY_JS,
        'js_path'      => $js_path,
        'css'          => $css,
        'dependencies' => $dependencies,
        'version'      => $version,
        'hashed'       => false,
      );
    }

    /**
     * @return list<string>
     */
    private static function read_legacy_asset_dependencies()
    {
      $asset_php = self::find_legacy_asset_php_path();
      if ($asset_php === null) {
        return array('wp-element', 'wp-api-fetch');
      }

      $asset = include $asset_php;
      if (! is_array($asset) || empty($asset['dependencies']) || ! is_array($asset['dependencies'])) {
        return array('wp-element', 'wp-api-fetch');
      }

      return array_values(array_map('strval', $asset['dependencies']));
    }

    private static function find_legacy_asset_php_path()
    {
      $candidates = array(
        ADFLIPR_PLUGIN_DIR . '/build/index.asset.php',
      );

      $hashed = glob(ADFLIPR_PLUGIN_DIR . '/build/index.*.asset.php');
      if (is_array($hashed)) {
        $candidates = array_merge($candidates, $hashed);
      }

      foreach ($candidates as $path) {
        if (is_readable($path)) {
          return $path;
        }
      }

      return null;
    }

    /**
     * Enqueue the admin React bundle (script, styles, localize).
     */
    public static function enqueue_admin_bundle(): void
    {
      $bundle = self::resolve_bundle();
      if ($bundle === null) {
        return;
      }

      $deps = ! empty($bundle['dependencies'])
        ? $bundle['dependencies']
        : array('wp-element', 'wp-api-fetch');

      $version = $bundle['version'] !== '' ? $bundle['version'] : null;

      wp_enqueue_script(
        'adflipr-wp-react-app-script',
        $bundle['js_url'],
        $deps,
        $version,
        true
      );

      wp_localize_script(
        'adflipr-wp-react-app-script',
        'adfliprWp',
        array(
          'appBaseUrl'                 => untrailingslashit((string) ADFLIPR_APP_BASE_URL),
          'apiBaseUrl'                 => untrailingslashit((string) ADFLIPR_API_BASE_URL),
          'restUrl'                    => esc_url_raw(trailingslashit(rest_url('adflipr/v1'))),
          'webhookRetryRestProcessUrl' => esc_url_raw(rest_url('adflipr/v1/webhook-retry/process')),
          'webhookRetryRestStatusUrl'  => esc_url_raw(rest_url('adflipr/v1/webhook-retry/status')),
          'integrationHealthRestUrl'   => esc_url_raw(rest_url('adflipr/v1/integration-health')),
          'restNonce'                  => wp_create_nonce('wp_rest'),
          'ajaxUrl'                    => admin_url('admin-ajax.php'),
          'webhookRetryAjaxNonce'      => wp_create_nonce('adflipr_webhook_retry'),
        )
      );

      $style_deps = array();
      $index      = 0;
      foreach ($bundle['css'] as $css_file) {
        $handle = $index === 0
          ? 'adflipr-wp-react-app-style'
          : 'adflipr-wp-react-app-style-' . (string) $index;

        wp_enqueue_style(
          $handle,
          $css_file['url'],
          $style_deps,
          $version
        );

        $style_deps = array($handle);
        ++$index;
      }
    }

    /**
     * Prevent admin HTML shell caching (manifest + localized config must stay fresh).
     */
    public static function send_admin_no_cache_headers(): void
    {
      if (headers_sent()) {
        return;
      }

      nocache_headers();
    }
  }
}
