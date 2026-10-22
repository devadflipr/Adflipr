<?php
defined('ABSPATH') || exit;

/**
 * Woo storefront browse → Adflipr tracking-events (SS-5).
 * Server-to-server with the WP integration token. Does not inject gtag/fbq
 * and never puts the Adflipr token in storefront JavaScript.
 */
if (! class_exists('Adflipr_Storefront_Tracking')) {

  class Adflipr_Storefront_Tracking
  {
    /**
     * @var null|self
     */
    public static $_instance = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    private $pending = array();

    public function __construct()
    {
      add_action('template_redirect', array($this, 'queue_page_events'), 20);
      add_action('woocommerce_add_to_cart', array($this, 'queue_add_to_cart'), 20, 6);
      add_action('shutdown', array($this, 'flush_pending'), 20);
    }

    /**
     * @return self
     */
    public static function get_instance()
    {
      if (null === self::$_instance) {
        self::$_instance = new self();
      }
      return self::$_instance;
    }

    public function queue_page_events()
    {
      if (! $this->should_track_request()) {
        return;
      }
      $this->ensure_visitor_id();
      $this->queue_event('PAGE_VIEW', null);
      if (function_exists('is_product') && is_product()) {
        $this->queue_event('VIEW_CONTENT', $this->current_product_payload());
      }
    }

    /**
     * @param string $cart_item_key
     * @param int    $product_id
     * @param int    $quantity
     * @param int    $variation_id
     * @param array  $variation
     * @param array  $cart_item_data
     */
    public function queue_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
      if (! $this->connected()) {
        return;
      }
      $this->ensure_visitor_id();
      $id = $variation_id ? (int) $variation_id : (int) $product_id;
      $this->queue_event('ADD_TO_CART', $this->product_payload_from_id($id, (int) $quantity));
    }

    public function flush_pending()
    {
      if ($this->pending === array() || ! $this->connected()) {
        return;
      }
      if (! is_object(AdFlipr_Core()->data) || ! method_exists(AdFlipr_Core()->data, 'send_storefront_tracking_event')) {
        return;
      }
      foreach ($this->pending as $payload) {
        AdFlipr_Core()->data->send_storefront_tracking_event($payload);
      }
      $this->pending = array();
    }

    /**
     * @param string                    $event_name
     * @param array<string, mixed>|null $product
     */
    private function queue_event($event_name, $product)
    {
      $domain = $this->shop_domain();
      if ($domain === null) {
        return;
      }
      $payload = array(
        'eventId'    => wp_generate_uuid4(),
        'eventName'  => $event_name,
        'occurredAt' => time(),
        'pageUrl'    => $this->current_page_url(),
        'shopDomain' => $domain,
        'clientId'   => $this->visitor_id(),
        'userAgent'  => $this->request_user_agent(),
        'source'     => 'WOOCOMMERCE_STOREFRONT',
      );
      $email = $this->logged_in_email();
      if ($email !== '') {
        $payload['email'] = $email;
      }
      if ($product !== null) {
        $payload['product'] = $product;
      }
      $cookies = $this->pixel_cookies();
      if ($cookies !== null) {
        $payload['cookies'] = $cookies;
      }
      $click_ids = $this->click_ids();
      if ($click_ids !== null) {
        $payload['clickIds'] = $click_ids;
      }
      $this->pending[] = $payload;
    }

    private function should_track_request()
    {
      if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() || is_feed()) {
        return false;
      }
      if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
      }
      if (! class_exists('WooCommerce')) {
        return false;
      }
      return $this->connected();
    }

    private function connected()
    {
      return is_object(AdFlipr_Core())
        && is_object(AdFlipr_Core()->auth)
        && AdFlipr_Core()->auth->valid_token();
    }

    private function ensure_visitor_id()
    {
      $cookie = defined('ADFLIPR_WOO_VID_COOKIE') ? ADFLIPR_WOO_VID_COOKIE : 'adf_woo_vid';
      $existing = isset($_COOKIE[ $cookie ]) ? sanitize_text_field(wp_unslash((string) $_COOKIE[ $cookie ])) : '';
      if ($existing !== '' && strlen($existing) <= 128) {
        return;
      }
      $id = wp_generate_uuid4();
      $_COOKIE[ $cookie ] = $id;
      if (! headers_sent()) {
        setcookie(
          $cookie,
          $id,
          array(
            'expires'  => time() + YEAR_IN_SECONDS,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
          )
        );
      }
    }

    private function visitor_id()
    {
      $cookie = defined('ADFLIPR_WOO_VID_COOKIE') ? ADFLIPR_WOO_VID_COOKIE : 'adf_woo_vid';
      $value = isset($_COOKIE[ $cookie ]) ? sanitize_text_field(wp_unslash((string) $_COOKIE[ $cookie ])) : '';
      if ($value === '') {
        return null;
      }
      return $this->clip($value, 128);
    }

    private function shop_domain()
    {
      $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
      return $this->clip(is_string($host) ? $host : '', 255);
    }

    private function current_page_url()
    {
      if (! empty($_SERVER['HTTP_HOST']) && ! empty($_SERVER['REQUEST_URI'])) {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST']));
        $uri    = esc_url_raw(wp_unslash((string) $_SERVER['REQUEST_URI']));
        return $this->clip($scheme . '://' . $host . $uri, 2048);
      }
      return $this->clip(home_url('/'), 2048);
    }

    private function request_user_agent()
    {
      if (empty($_SERVER['HTTP_USER_AGENT'])) {
        return null;
      }
      return $this->clip(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT'])), 512);
    }

    private function logged_in_email()
    {
      if (! is_user_logged_in()) {
        return '';
      }
      $user = wp_get_current_user();
      $email = isset($user->user_email) ? sanitize_email((string) $user->user_email) : '';
      return is_email($email) ? $email : '';
    }

    /**
     * @return array<string, string>|null
     */
    private function pixel_cookies()
    {
      $fbp = $this->cookie_value('_fbp', 256);
      $fbc = $this->cookie_value('_fbc', 256);
      if ($fbp === null && $fbc === null) {
        return null;
      }
      $out = array();
      if ($fbp !== null) {
        $out['fbp'] = $fbp;
      }
      if ($fbc !== null) {
        $out['fbc'] = $fbc;
      }
      return $out;
    }

    /**
     * @return array<string, string>|null
     */
    private function click_ids()
    {
      $out = array();
      foreach (array('gclid', 'gbraid', 'wbraid') as $key) {
        $value = $this->cookie_value($key, 256);
        if ($value !== null) {
          $out[ $key ] = $value;
        }
      }
      return $out === array() ? null : $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function current_product_payload()
    {
      $product = function_exists('wc_get_product') ? wc_get_product(get_queried_object_id()) : null;
      if (! $product) {
        return null;
      }
      return $this->product_payload($product, 1);
    }

    /**
     * @param int $product_id
     * @param int $quantity
     * @return array<string, mixed>|null
     */
    private function product_payload_from_id($product_id, $quantity)
    {
      if ($product_id < 1 || ! function_exists('wc_get_product')) {
        return null;
      }
      $product = wc_get_product($product_id);
      if (! $product) {
        return null;
      }
      return $this->product_payload($product, max(1, $quantity));
    }

    /**
     * @param WC_Product $product
     * @param int        $quantity
     * @return array<string, mixed>
     */
    private function product_payload($product, $quantity)
    {
      $price = $product->get_price();
      $payload = array(
        'id'       => $this->clip((string) $product->get_id(), 128),
        'name'     => $this->clip(wp_strip_all_tags((string) $product->get_name()), 256),
        'quantity' => $quantity,
      );
      if (is_numeric($price)) {
        $payload['price'] = (float) $price;
      }
      if (function_exists('get_woocommerce_currency')) {
        $payload['currency'] = $this->clip((string) get_woocommerce_currency(), 8);
      }
      return $payload;
    }

    private function cookie_value($name, $max)
    {
      if (! isset($_COOKIE[ $name ])) {
        return null;
      }
      $value = sanitize_text_field(wp_unslash((string) $_COOKIE[ $name ]));
      return $this->clip($value, $max);
    }

    private function clip($value, $max)
    {
      $value = trim((string) $value);
      if ($value === '') {
        return null;
      }
      if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
      }
      return substr($value, 0, $max);
    }
  }

  if (class_exists('AdFlipr_Core')) {
    AdFlipr_Core::register('storefront_tracking', 'Adflipr_Storefront_Tracking');
  }
}
