<?php
/**
 * Stable Adflipr recovery key for cart_hash webhooks (not WooCommerce content hash).
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

if (! class_exists('Adflipr_Recovery_Cart_Hash')) {

  class Adflipr_Recovery_Cart_Hash
  {
    /**
     * @return string
     */
    public static function session_key()
    {
      return defined('ADFLIPR_RECOVERY_CART_SESSION_KEY')
        ? ADFLIPR_RECOVERY_CART_SESSION_KEY
        : 'adflipr_recovery_cart_hash';
    }

    /**
     * @return string
     */
    public static function url_param()
    {
      return defined('ADFLIPR_RECOVERY_CART_URL_PARAM')
        ? ADFLIPR_RECOVERY_CART_URL_PARAM
        : 'adflipr_recovery_cart';
    }

    /**
     * @return string
     */
    public static function order_meta_key()
    {
      return defined('ADFLIPR_RECOVERY_CART_ORDER_META')
        ? ADFLIPR_RECOVERY_CART_ORDER_META
        : '_adflipr_cart_hash';
    }

    /**
     * @param string $value
     * @return bool
     */
    public static function is_valid_key($value)
    {
      if (! is_string($value) || $value === '') {
        return false;
      }

      return (bool) preg_match('/^[0-9a-f]{32,64}$/i', $value);
    }

    /**
     * @return string|null
     */
    public static function get_active()
    {
      if (! function_exists('WC') || ! WC()->session) {
        return null;
      }

      $stored = WC()->session->get(self::session_key());
      if (! is_string($stored) || ! self::is_valid_key($stored)) {
        return null;
      }

      return $stored;
    }

    /**
     * First successful abandon save for this journey — create once, reuse until cleared.
     *
     * @return string
     */
    public static function get_or_create()
    {
      $existing = self::get_active();
      if ($existing !== null) {
        return $existing;
      }

      return self::assign_new('save_cart');
    }

    /**
     * @param string $reason
     * @return string
     */
    private static function assign_new($reason)
    {
      $key = md5(wp_generate_uuid4());
      if (function_exists('WC') && WC()->session) {
        WC()->session->set(self::session_key(), $key);
      }

      if (function_exists('adflipr_debug_log')) {
        adflipr_debug_log(
          'recovery_cart_hash_created',
          array(
            'reason'             => $reason,
            'recovery_cart_hash' => $key,
          )
        );
      }

      return $key;
    }

    /**
     * Restore journey key from recovery URL query param.
     */
    public static function restore_from_request_url()
    {
      // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only recovery param.
      if (! isset($_GET[ self::url_param() ])) {
        return;
      }

      $raw = sanitize_text_field(wp_unslash((string) $_GET[ self::url_param() ]));
      if (! self::is_valid_key($raw)) {
        return;
      }

      if (! function_exists('WC') || ! WC()->session) {
        return;
      }

      WC()->session->set(self::session_key(), $raw);

      if (function_exists('adflipr_debug_log')) {
        adflipr_debug_log(
          'recovery_cart_hash_restored_from_url',
          array(
            'recovery_cart_hash' => $raw,
          )
        );
      }
    }

    /**
     * @param string $reason
     */
    public static function clear($reason)
    {
      if (! function_exists('WC') || ! WC()->session) {
        return;
      }

      $previous = self::get_active();
      WC()->session->__unset(self::session_key());

      if ($previous !== null && function_exists('adflipr_debug_log')) {
        adflipr_debug_log(
          'recovery_cart_hash_cleared',
          array(
            'reason'             => $reason,
            'recovery_cart_hash' => $previous,
          )
        );
      }
    }

    /**
     * @param int[]  $product_ids
     * @param string $recovery_cart_hash
     * @return string
     */
    public static function build_recovery_url(array $product_ids, $recovery_cart_hash)
    {
      $base = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/checkout/');
      $args = array(
        self::url_param() => $recovery_cart_hash,
      );

      if (count($product_ids) > 0) {
        $args['add-to-cart'] = implode(',', array_map('absint', $product_ids));
      }

      return add_query_arg($args, $base);
    }
  }
}
