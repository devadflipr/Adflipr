<?php
defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Class Adflipr_Cart_Tracker
 */
if (! class_exists('Adflipr_Cart_Tracker')) {

  class Adflipr_Cart_Tracker
  {
    /**
     * @var null
     */
    public static $_instance = null;

    public function __construct()
    {
      add_action('wp_enqueue_scripts', [$this, 'track_cart_abandonment_script']);
      add_action('woocommerce_init', array($this, 'restore_recovery_cart_hash_from_url'), 5);
      add_action('wp_ajax_adflipr_save_cart', [$this, 'save_cart']);
      add_action('wp_ajax_nopriv_adflipr_save_cart', [$this, 'save_cart']);
      add_action(
        'woocommerce_checkout_create_order',
        array($this, 'persist_recovery_cart_hash_on_order'),
        10,
        2
      );

      add_action('woocommerce_checkout_order_created', [$this, 'handle_order_completion']);
      // Block / Store API checkout: fire when order is processed (not merely updated from request).
      add_action('woocommerce_store_api_checkout_order_processed', [$this, 'handle_order_completion'], 10, 1);
      add_action('woocommerce_thankyou', array($this, 'clear_recovery_cart_hash_after_order'), 20, 1);
    }

    /**
     * Restore stable recovery cart_hash from recovery email URL into WC session.
     */
    public function restore_recovery_cart_hash_from_url()
    {
      if (! class_exists('Adflipr_Recovery_Cart_Hash')) {
        return;
      }

      Adflipr_Recovery_Cart_Hash::restore_from_request_url();
    }

    /**
     * Persist stable recovery cart_hash on order (Adflipr join key — not WC content hash).
     *
     * @param WC_Order $order
     * @param array    $data
     */
    public function persist_recovery_cart_hash_on_order($order, $data)
    {
      if (! $order instanceof WC_Order || ! class_exists('Adflipr_Recovery_Cart_Hash')) {
        return;
      }

      $recovery_cart_hash = Adflipr_Recovery_Cart_Hash::get_active();
      if ($recovery_cart_hash === null) {
        return;
      }

      $meta_key = Adflipr_Recovery_Cart_Hash::order_meta_key();
      $order->update_meta_data($meta_key, $recovery_cart_hash);

      if (function_exists('adflipr_debug_log')) {
        adflipr_debug_log(
          'recovery_cart_hash_order_meta_set',
          array(
            'order_id'           => $order->get_id(),
            'recovery_cart_hash' => $recovery_cart_hash,
          )
        );
      }
    }

    /**
     * Clear journey key after successful purchase.
     *
     * @param int $order_id
     */
    public function clear_recovery_cart_hash_after_order($order_id)
    {
      if (! class_exists('Adflipr_Recovery_Cart_Hash')) {
        return;
      }

      Adflipr_Recovery_Cart_Hash::clear('order_completed');
    }

    /**
     * @return Adflipr_Cart_Tracker|null
     */
    public static function get_instance()
    {
      if (null === self::$_instance) {
        self::$_instance = new self();
      }

      return self::$_instance;
    }

    /**
     * Enqueue storefront attribution on all WooCommerce pages; checkout cart recovery when on checkout.
     *
     * Integration test (manual):
     * 1. Product URL with ?utm_source=adflipr&utm_medium=email&utm_campaign=…
     * 2. Confirm utm_* cookies in DevTools before add-to-cart.
     * 3. Add to cart → checkout → place order.
     * 4. checkout_purchased webhook must include tracking; backend txnSource = ADFLIPR_UTM.
     */
    public function track_cart_abandonment_script()
    {
      if (is_admin() || ! class_exists('WooCommerce')) {
        return;
      }

      $is_checkout = function_exists('is_checkout') && is_checkout();
      $deps        = $is_checkout ? array('jquery') : array();

      wp_enqueue_script(
        'adflipr-cart-tracker',
        ADFLIPR_PLUGIN_URL . '/assets/js/adflipr-cart-tracker.js',
        $deps,
        ADFLIPR_VERSION,
        true
      );

      $vars = array(
        'is_checkout' => $is_checkout,
      );

      if ($is_checkout) {
        $nonce_action     = defined('ADFLIPR_SAVE_CART_NONCE_ACTION') ? ADFLIPR_SAVE_CART_NONCE_ACTION : 'adflipr_save_cart';
        $vars['ajax_url'] = admin_url('admin-ajax.php');
        $vars['nonce']    = wp_create_nonce($nonce_action);
        $vars['security'] = wp_create_nonce($nonce_action);
      }

      wp_add_inline_script(
        'adflipr-cart-tracker',
        'var adflipr_vars = ' . wp_json_encode($vars) . ';',
        'before'
      );
    }

    /**
     * Simple per-IP rate limit for public AJAX (abuse / cost control).
     */
    private function save_cart_rate_limit_ok()
    {
      if (! defined('ADFLIPR_SAVE_CART_RATE_PER_MINUTE')) {
        return true;
      }

      $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
      $key = 'adflipr_cart_rl_' . md5($ip);
      $n   = (int) get_transient($key);
      if ($n >= (int) ADFLIPR_SAVE_CART_RATE_PER_MINUTE) {
        return false;
      }

      set_transient($key, $n + 1, MINUTE_IN_SECONDS);

      return true;
    }

    public function save_cart()
    {
      if (defined('ADFLIPR_SAVE_CART_MAX_CONTENT_LENGTH')) {
        $content_length = isset($_SERVER['CONTENT_LENGTH'])
          ? (int) sanitize_text_field(wp_unslash((string) $_SERVER['CONTENT_LENGTH']))
          : 0;
        if ($content_length > (int) ADFLIPR_SAVE_CART_MAX_CONTENT_LENGTH) {
          wp_send_json_error(array('message' => __('Request too large.', 'adflipr-for-woocommerce')), 413);
        }
      }

      $nonce_action = defined('ADFLIPR_SAVE_CART_NONCE_ACTION') ? ADFLIPR_SAVE_CART_NONCE_ACTION : 'adflipr_save_cart';
      $nonce_value  = '';
      if (isset($_POST['security'])) {
        $nonce_value = sanitize_text_field(wp_unslash($_POST['security']));
      } elseif (isset($_POST['nonce'])) {
        $nonce_value = sanitize_text_field(wp_unslash($_POST['nonce']));
      }
      if ($nonce_value === '' || ! wp_verify_nonce($nonce_value, $nonce_action)) {
        wp_send_json_error(array('message' => __('Invalid security token.', 'adflipr-for-woocommerce')), 403);
      }

      if (! $this->save_cart_rate_limit_ok()) {
        wp_send_json_error(array('message' => __('Too many requests.', 'adflipr-for-woocommerce')), 429);
      }

      if (! function_exists('WC') || is_null(WC()->cart) || empty(WC()->cart->get_cart())) {
        wp_send_json_success(array('message' => 'empty cart'));
      }

      $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
      $url   = '';
      if (isset($_POST['url'])) {
        $url = esc_url_raw(wp_unslash($_POST['url']));
      }
      if ($url === '' && isset($_SERVER['HTTP_REFERER'])) {
        $url = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
      }

      if ($email === '') {
        wp_send_json_error(array('message' => __('Email is missing.', 'adflipr-for-woocommerce')));

        return;
      }

      $cart           = WC()->cart->get_cart();
      $wc_cart_hash   = WC()->cart->get_cart_hash();
      $recovery_cart_hash = class_exists('Adflipr_Recovery_Cart_Hash')
        ? Adflipr_Recovery_Cart_Hash::get_or_create()
        : $wc_cart_hash;
      $currency       = get_woocommerce_currency();

      $cart_items = [];
      foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        if (! $product) {
          continue;
        }

        $cart_items[] = array(
          'product_id'    => $cart_item['product_id'],
          'variation_id'  => $cart_item['variation_id'],
          'name'          => $product->get_name(),
          'sku'           => $product->get_sku(),
          'quantity'      => $cart_item['quantity'],
          'price'         => $product->get_price(),
          'line_total'    => $cart_item['line_total'],
          'line_subtotal' => $cart_item['line_subtotal'],
          'image'         => wp_get_attachment_url(get_post_thumbnail_id($cart_item['product_id'])),
          'product_url'   => get_permalink($cart_item['product_id']),
        );
      }

      if ($url !== '' && class_exists('Adflipr_Recovery_Cart_Hash')) {
        $product_ids = array();
        foreach (WC()->cart->get_cart() as $cart_item) {
          $product_ids[] = $cart_item['product_id'];
        }

        $url = Adflipr_Recovery_Cart_Hash::build_recovery_url($product_ids, $recovery_cart_hash);
      }

      // Persist UTM cookies to WC session so checkout_purchased can read them (e.g. block checkout).
      $this->fetch_utm_from_cookies();

      $user_data = array();
      $user_id   = get_current_user_id();
      if ($user_id) {
        $user = get_userdata($user_id);
        if ($user) {
          $user_data = array(
            'id'         => $user_id,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'username'   => $user->user_login,
            'email'      => $user->user_email,
          );
        }
      }

      $customer_data = array();
      if (WC()->customer) {
        $customer_data['billing'] = array(
          'first_name' => WC()->customer->get_billing_first_name(),
          'last_name'  => WC()->customer->get_billing_last_name(),
          'company'    => WC()->customer->get_billing_company(),
          'address_1'  => WC()->customer->get_billing_address_1(),
          'address_2'  => WC()->customer->get_billing_address_2(),
          'city'       => WC()->customer->get_billing_city(),
          'state'      => WC()->customer->get_billing_state(),
          'postcode'   => WC()->customer->get_billing_postcode(),
          'country'    => WC()->customer->get_billing_country(),
          'email'      => WC()->customer->get_billing_email(),
          'phone'      => WC()->customer->get_billing_phone(),
        );

        $customer_data['shipping'] = array(
          'first_name' => WC()->customer->get_shipping_first_name(),
          'last_name'  => WC()->customer->get_shipping_last_name(),
          'company'    => WC()->customer->get_shipping_company(),
          'address_1'  => WC()->customer->get_shipping_address_1(),
          'address_2'  => WC()->customer->get_shipping_address_2(),
          'city'       => WC()->customer->get_shipping_city(),
          'state'      => WC()->customer->get_shipping_state(),
          'postcode'   => WC()->customer->get_shipping_postcode(),
          'country'    => WC()->customer->get_shipping_country(),
        );
      }

      $cart_totals = array(
        'subtotal'              => WC()->cart->get_subtotal(),
        'subtotal_tax'          => WC()->cart->get_subtotal_tax(),
        'cart_contents_total'   => WC()->cart->get_cart_contents_total(),
        'cart_contents_tax'     => WC()->cart->get_cart_contents_tax(),
        'cart_discount'         => WC()->cart->get_discount_total(),
        'discount_tax'          => WC()->cart->get_discount_tax(),
        'shipping_total'        => WC()->cart->get_shipping_total(),
        'shipping_tax'          => WC()->cart->get_shipping_tax(),
        'total'                 => WC()->cart->get_total('edit'),
        'total_tax'             => WC()->cart->get_total_tax(),
      );

      $applied_coupons = WC()->cart->get_applied_coupons();
      $coupons         = array();
      foreach ($applied_coupons as $coupon_code) {
        $coupon    = new WC_Coupon($coupon_code);
        $coupons[] = array(
          'code'          => $coupon->get_code(),
          'discount_type' => $coupon->get_discount_type(),
          'amount'        => $coupon->get_amount(),
        );
      }

      $cart_abandonment = array(
        'action'          => 'cart_abandonment',
        'email'           => $email,
        'cart_data'       => $cart,
        'cart_items'      => $cart_items,
        'cart_totals'     => $cart_totals,
        'applied_coupons' => $coupons,
        'url'             => $url,
        'cart_hash'       => $recovery_cart_hash,
        'wc_cart_hash'    => $wc_cart_hash,
        'currency'        => $currency,
        'user'            => $user_data,
        'customer'        => $customer_data,
        'type'            => 'recoverable',
      );

      if (isset($_POST['web_push_subscription_id'])) {
        $push_subscription_id = sanitize_text_field(wp_unslash((string) $_POST['web_push_subscription_id']));
        if ($push_subscription_id !== '') {
          $cart_abandonment['web_push_subscription_id'] = $push_subscription_id;
        }
      }
      if (isset($_POST['web_push_endpoint'])) {
        $push_endpoint = esc_url_raw(wp_unslash((string) $_POST['web_push_endpoint']));
        if ($push_endpoint !== '') {
          $cart_abandonment['web_push_endpoint'] = $push_endpoint;
        }
      }

      $result = AdFlipr_Core()->data->send_data_on_webhook($cart_abandonment);
      wp_send_json_success($result);
    }

    public function handle_order_completion($order)
    {
      if (is_numeric($order)) {
        $order = wc_get_order($order);
      }

      if (! $order instanceof WC_Order) {
        return;
      }

      $sent_meta = defined('ADFLIPR_ORDER_CHECKOUT_WEBHOOK_SENT_META')
        ? ADFLIPR_ORDER_CHECKOUT_WEBHOOK_SENT_META
        : '_adflipr_checkout_webhook_sent';

      if ($order->get_meta($sent_meta) === 'yes') {
        return;
      }

      $meta_key  = class_exists('Adflipr_Recovery_Cart_Hash')
        ? Adflipr_Recovery_Cart_Hash::order_meta_key()
        : '_adflipr_cart_hash';
      $cart_hash = $order->get_meta($meta_key, true);

      if ($cart_hash === '' && class_exists('Adflipr_Recovery_Cart_Hash')) {
        $active = Adflipr_Recovery_Cart_Hash::get_active();
        if ($active !== null) {
          $cart_hash = $active;
        }
      }

      // Legacy fallback for orders placed before stable recovery cart_hash rollout.
      if ($cart_hash === '' && function_exists('WC') && ! empty(WC()->cart)) {
        $cart_hash = WC()->cart->get_cart_hash();
      }

      $email      = $order->get_billing_email();
      $order_data = AdFlipr_Core()->data->get_order_all_possible_data($order);

      $tracking = $this->fetch_utm_from_cookies();
      adflipr_debug_log(
        'Attribution loaded at checkout',
        array(
          'utm_source'   => isset($tracking['utm_source']) ? $tracking['utm_source'] : '',
          'utm_medium'   => isset($tracking['utm_medium']) ? $tracking['utm_medium'] : '',
          'utm_campaign' => isset($tracking['utm_campaign']) ? $tracking['utm_campaign'] : '',
          'has_tracking' => count($tracking) > 0,
        )
      );

      $data = array(
        'action'     => 'checkout_purchased',
        'email'      => $email,
        'cart_hash'  => $cart_hash ? $cart_hash : '',
        'type'       => 'recovered',
        'order_data' => $order_data,
        'tracking'   => $tracking,
        'ip_address' => $this->get_client_ip(),
      );

      $result = AdFlipr_Core()->data->send_data_on_webhook($data);

      $ok_http    = ! empty($result['status']) && isset($result['code']) && (int) $result['code'] === 200;
      $queued     = ! empty($result['queued']);
      $queue_full = ! empty($result['queue_full']);

      // Mark order only after durable handoff: HTTP 200 from AdFlipr, or payload persisted in the retry queue.
      // Do not set when queue is full — checkout may be retried by WooCommerce / admin without duplicating remote state incorrectly.
      if (($ok_http || $queued) && ! $queue_full) {
        $order->update_meta_data($sent_meta, 'yes');
        $order->save();
      }
    }

    /**
     * Build a recovery URL with add-to-cart query args (avoids duplicate ? when URL already has params).
     *
     * @param string        $url         Base checkout or cart URL.
     * @param array<int>    $product_ids Product IDs to add.
     */
    private function build_cart_recovery_url($url, array $product_ids)
    {
      $product_ids = array_filter(array_map('absint', $product_ids));
      if ($url === '' || empty($product_ids)) {
        return $url;
      }

      return add_query_arg('add-to-cart', implode(',', $product_ids), $url);
    }

    /**
     * Read UTM attribution from cookies, with WooCommerce session fallback for order completion.
     *
     * @return array<string, string>
     */
    public function fetch_utm_from_cookies()
    {
      $from_cookies = $this->read_utm_prefixed_cookies();
      if (count($from_cookies) > 0) {
        $this->persist_utm_to_wc_session($from_cookies);

        return $from_cookies;
      }

      return $this->read_utm_from_wc_session();
    }

    /**
     * @param array<string, string> $tracking
     */
    private function persist_utm_to_wc_session(array $tracking)
    {
      if (empty($tracking) || ! function_exists('WC') || ! WC()->session) {
        return;
      }

      $session_key = defined('ADFLIPR_UTM_SESSION_KEY') ? ADFLIPR_UTM_SESSION_KEY : 'adflipr_utm_tracking';
      WC()->session->set($session_key, $tracking);
    }

    /**
     * @return array<string, string>
     */
    private function read_utm_from_wc_session()
    {
      if (! function_exists('WC') || ! WC()->session) {
        return array();
      }

      $session_key = defined('ADFLIPR_UTM_SESSION_KEY') ? ADFLIPR_UTM_SESSION_KEY : 'adflipr_utm_tracking';
      $stored      = WC()->session->get($session_key);
      if (! is_array($stored) || empty($stored)) {
        return array();
      }

      $filtered = array();
      foreach ($stored as $raw_key => $raw_value) {
        $key = sanitize_key((string) $raw_key);
        if (strpos($key, 'utm_') !== 0 || ! is_scalar($raw_value)) {
          continue;
        }
        $value = sanitize_text_field((string) $raw_value);
        if ($value === '') {
          continue;
        }
        $filtered[ $key ] = $value;
      }

      return $filtered;
    }

    /**
     * @return array<string, string>
     */
    private function read_utm_prefixed_cookies()
    {
      $filtered = array();

      foreach ($_COOKIE as $raw_key => $raw_value) {
        $key = sanitize_key((string) $raw_key);
        if (strpos($key, 'utm_') !== 0) {
          continue;
        }
        if (! is_scalar($raw_value)) {
          continue;
        }
        $value = sanitize_text_field(wp_unslash((string) $raw_value));
        if ($value === '') {
          continue;
        }
        $filtered[ $key ] = $value;
      }

      return $filtered;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip()
    {
      $ip_keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
      );

      foreach ($ip_keys as $key) {
        if (! array_key_exists($key, $_SERVER)) {
          continue;
        }
        $raw_ip = sanitize_text_field(wp_unslash($_SERVER[ $key ]));
        if ($raw_ip === '') {
          continue;
        }
        // Proxies may send a comma-separated list (e.g. X-Forwarded-For).
        $ip = trim(strtok($raw_ip, ','));
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
          return $ip;
        }
      }

      return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }
  }

  if (class_exists('Adflipr_Cart_Tracker')) {
    AdFlipr_Core::register('cart_tracker', 'Adflipr_Cart_Tracker');
  }
}
