<?php

defined('ABSPATH') || exit; // Exit if accessed directly

/**
 * Class AdFlipr_Admin
 */
if (! class_exists('AdFlipr_Admin')) {
  class AdFlipr_Admin
  {
    private static $ins = null;

    /** @var bool */
    private static $privacy_policy_content_registered = false;

    public function __construct()
    {
      add_action('init', array($this, 'check_woocommerce_added'));
      add_action('admin_menu', array($this, 'register_menu'));
      add_action('admin_init', array($this, 'handle_connect_notice_dismiss'), 5);
      add_action('admin_init', array($this, 'handle_admin_form_actions'), 1);
      add_action('admin_notices', array($this, 'admin_settings_feedback_notices'));
      add_action('admin_notices', array($this, 'adflipr_not_connected_notice'));
      add_action('admin_init', array($this, 'maybe_register_privacy_policy_suggested_text'), 5);
      add_action('wp_enqueue_scripts', array($this, 'add_user_scripts'));
      add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
      add_action('load-toplevel_page_adflipr-settings', array($this, 'send_admin_no_cache_headers'));
      add_shortcode('adflipr_react_app', array($this, 'render_react_app_shortcode'));
    }

    public function check_woocommerce_added()
    {
      if (! class_exists('WooCommerce')) {
        add_action('admin_notices', array($this, 'woocommerce_missing_notice'));

        return;
      }
    }

    /**
     * Secure disconnect and redirects (capabilities + nonces).
     */
    public function handle_admin_form_actions()
    {
      $page = '';
      if (isset($_REQUEST['page'])) {
        $page = sanitize_key(wp_unslash($_REQUEST['page']));
      }
      if ($page !== 'adflipr-settings') {
        return;
      }

      if (! current_user_can('manage_options')) {
        return;
      }

      if (isset($_GET['reset_token'], $_GET['_wpnonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'adflipr_reset_token')
      ) {
        $auth  = AdFlipr_Core()->auth;
        $token = get_option('adflipr_auth_token', false);
        if ($token) {
          $auth->notify_adflipr_disconnect($token);
        }
        $auth->soft_disconnect('local');
        delete_user_meta(get_current_user_id(), 'adflipr_connect_notice_dismissed');
        wp_safe_redirect(
          add_query_arg(
            array(
              'page'          => 'adflipr-settings',
              'adflipr_reset' => '1',
            ),
            admin_url('admin.php')
          )
        );
        exit;
      }
    }

    /**
     * Dismiss "not connected" admin notice (per user).
     */
    public function handle_connect_notice_dismiss()
    {
      if (! isset($_GET['adflipr_dismiss_connect'], $_GET['_wpnonce'])) {
        return;
      }

      if (! current_user_can('manage_options')) {
        return;
      }

      if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'adflipr_dismiss_connect_notice')) {
        return;
      }

      update_user_meta(get_current_user_id(), 'adflipr_connect_notice_dismissed', '1');

      $redirect = wp_get_referer();
      if (! is_string($redirect) || $redirect === '') {
        $redirect = admin_url();
      }

      wp_safe_redirect(remove_query_arg(array('adflipr_dismiss_connect', '_wpnonce'), $redirect));
      exit;
    }

    /**
     * Prompt administrators to connect when WooCommerce is active but Adflipr token is missing.
     */
    public function adflipr_not_connected_notice()
    {
      if (! current_user_can('manage_options')) {
        return;
      }

      if (! class_exists('WooCommerce')) {
        return;
      }

      if (! function_exists('AdFlipr_Core')) {
        return;
      }

      $core = AdFlipr_Core();
      if (! isset($core->auth) || ! is_object($core->auth)) {
        return;
      }

      if ($core->auth->valid_token()) {
        return;
      }

      if (get_user_meta(get_current_user_id(), 'adflipr_connect_notice_dismissed', true) === '1') {
        return;
      }

      $settings_url = admin_url('admin.php?page=adflipr-settings');
      $dismiss_url  = wp_nonce_url(
        add_query_arg('adflipr_dismiss_connect', '1', admin_url('index.php')),
        'adflipr_dismiss_connect_notice'
      );

      ?>
      <div class="notice notice-warning">
        <p>
          <?php esc_html_e('Adflipr is not connected. Store-related data may queue locally until you connect your Adflipr account.', 'adflipr-for-woocommerce'); ?>
          <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open Adflipr settings', 'adflipr-for-woocommerce'); ?></a>
          |
          <a href="<?php echo esc_url($dismiss_url); ?>"><?php esc_html_e('Dismiss this notice', 'adflipr-for-woocommerce'); ?></a>
        </p>
      </div>
      <?php
    }

    /**
     * Success notices after redirect (disconnect).
     */
    public function admin_settings_feedback_notices()
    {
      // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen routing; no state change.
      if (! isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== 'adflipr-settings') {
        return;
      }

      if (! current_user_can('manage_options')) {
        return;
      }


      // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only success notice after prior nonce-protected disconnect.
      if (isset($_GET['adflipr_reset'])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Adflipr integration disconnected successfully. Tracking and synchronization are paused until reconnect.', 'adflipr-for-woocommerce') . '</p></div>';
      }

      $notice_reason = get_transient(ADFLIPR_DISCONNECT_NOTICE_TRANSIENT);
      if ($notice_reason === 'remote') {
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Adflipr integration was disconnected from the Adflipr dashboard. Tracking and synchronization are paused until reconnect.', 'adflipr-for-woocommerce') . '</p></div>';
        delete_transient(ADFLIPR_DISCONNECT_NOTICE_TRANSIENT);
      }
    }

    /**
     * Suggested Privacy Policy text (Tools → Privacy → Policy guide).
     */
    public function maybe_register_privacy_policy_suggested_text()
    {
      if (self::$privacy_policy_content_registered || ! function_exists('wp_add_privacy_policy_content')) {
        return;
      }

      self::$privacy_policy_content_registered = true;

      $body = sprintf(
        '<p>%s</p><ul><li>%s</li><li>%s</li><li>%s</li><li>%s</li></ul><p>%s</p>',
        esc_html__(
          'This site uses the Adflipr plugin to connect WooCommerce with Adflipr (hosted service). Depending on your settings and store activity, data may be transmitted to Adflipr servers over HTTPS.',
          'adflipr-for-woocommerce'
        ),
        esc_html__(
          'Product, coupon, order, and (where applicable) subscription data for synchronization and automation.',
          'adflipr-for-woocommerce'
        ),
        esc_html__(
          'Checkout-related events (for example cart recovery or purchase notifications) when the shopper interacts with checkout.',
          'adflipr-for-woocommerce'
        ),
        esc_html__(
          'Authentication tokens stored locally after you sign in to Adflipr from WordPress admin.',
          'adflipr-for-woocommerce'
        ),
        esc_html__(
          'Optional marketing attribution cookies (utm_* keys from the URL) when that feature is enabled for your site.',
          'adflipr-for-woocommerce'
        ),
        esc_html__(
          'Data is sent so Adflipr can provide email marketing and automation features you configure in your Adflipr account. See Adflipr’s own privacy policy for how they process data.',
          'adflipr-for-woocommerce'
        )
      );

      wp_add_privacy_policy_content(__('Adflipr', 'adflipr-for-woocommerce'), wp_kses_post($body));
    }

    /**
     * Display notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice()
    {
      ?>
      <div class="notice notice-error"><p><?php echo esc_html__('Adflipr requires WooCommerce to be installed and active.', 'adflipr-for-woocommerce'); ?></p></div>
      <?php
    }

    /**
     * @return AdFlipr_Admin|null
     */
    public static function get_instance()
    {
      if (null === self::$ins) {
        self::$ins = new self();
      }

      return self::$ins;
    }

    public function register_menu()
    {
      $icon_svg = '<svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
    <path d="M16.0021 11.4982V15.8818H11.9543C11.4122 15.8818 10.8619 15.8279 10.3664 15.6079C9.01599 15.0084 8.4026 14.0158 8.13636 13.1052C7.76355 11.8301 8.11031 10.1352 9.03473 9.18119C9.37595 8.82904 9.81443 8.49377 10.3685 8.24815C11.7719 7.62606 13.5327 7.83371 14.6573 8.87865C15.572 9.72868 15.9309 10.8559 16.0021 11.4982Z" fill="#9ca2a7"/>
    <path d="M24 11.0847V23.9875C22.3509 24.186 20.5645 21.9749 19.8773 20.8445C19.8773 20.8445 20.537 16.0473 19.8773 11.0847C19.2177 6.12205 15.26 3.88889 12.0443 3.88889C10.7208 4.00957 9.16332 4.32593 7.73225 5.20913C4.0817 7.46212 3.08649 12.9629 5.45268 16.5411C5.75692 17.0012 6.09432 17.3941 6.44863 17.7311C8.85378 20.0184 12.5841 20.0146 15.9027 19.954C16.1808 19.9489 16.4621 19.9425 16.7441 19.9347C19.9809 21.0099 19.9873 23.1052 19.8773 23.9875H10.8899C7.09709 23.4085 -0.241256 20.3482 0.00609841 10.6711C1.65517 0.663176 11.0548 -1.4384 16.5792 0.828612C22.0211 3.06178 23.7252 8.3001 24 11.0847Z" fill="#9ca2a7"/>
    </svg>';

      add_menu_page(
        __('Adflipr', 'adflipr-for-woocommerce'),
        __('Adflipr', 'adflipr-for-woocommerce'),
        'manage_options',
        'adflipr-settings',
        array($this, 'render_react_admin_page'),
        'data:image/svg+xml;base64,' . base64_encode($icon_svg),
        56
      );
    }

    /**
     * Main AdFlipr admin page: React app shell.
     */
    public function render_react_admin_page()
    {
      if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to access this page.', 'adflipr-for-woocommerce'));
      }

      echo '<div class="wrap adflipr-admin-wrap">';
      echo '<div id="adflipr-wp-react-app"></div>';
      echo '</div>';
    }

    /**
     * Public storefront script only when the site administrator opts in.
     */
    private function is_utm_tracking_enabled()
    {
      if (! defined('ADFLIPR_UTM_TRACKING_OPTION')) {
        return false;
      }

      $v = get_option(ADFLIPR_UTM_TRACKING_OPTION, false);

      return $v === true || $v === 1 || $v === '1';
    }

    public function send_data_after_authentication()
    {
      $args = array(
        'product_offset'      => get_option('adflipr_products_last_offset', 0),
        'product_data'        => true,
        'order_offset'        => get_option('adflipr_orders_last_offset', 0),
        'order_data'          => true,
        'subscription_offset' => get_option('adflipr_subscriptions_last_offset', 0),
        'subscription_data'   => true,
        'coupon_offset'       => get_option('adflipr_coupons_last_offset', 0),
        'coupon_data'         => true,
      );

      $result = AdFlipr_Core()->data->send_data_all_data($args);

      // Token now exists: drain queued storefront payloads (cart/checkout captured while disconnected).
      if (AdFlipr_Core()->auth->valid_token()) {
        AdFlipr_Core()->data->process_webhook_retry_queue_manual();
      }

      return $result;
    }

    /**
     * Enqueue React scripts and styles (content-hashed via build/asset-manifest.json).
     */
    public function enqueue_scripts()
    {
      if (! class_exists('AdFlipr_Assets')) {
        return;
      }

      AdFlipr_Assets::enqueue_admin_bundle();
    }

    /**
     * Admin HTML shell must not be cached — only immutable hashed JS/CSS may be cached long-term.
     */
    public function send_admin_no_cache_headers()
    {
      if (! class_exists('AdFlipr_Assets')) {
        return;
      }

      AdFlipr_Assets::send_admin_no_cache_headers();
    }

    public function add_user_scripts()
    {
      if (! $this->is_utm_tracking_enabled()) {
        return;
      }

      wp_enqueue_script(
        'adflipr-wp-utm-tracking',
        ADFLIPR_PLUGIN_URL . '/assets/js/adflipr-utm-tracking.js',
        array(),
        ADFLIPR_VERSION,
        true
      );
    }

    /**
     * Enqueue admin-only assets on the Adflipr settings screen.
     */
    public function admin_enqueue_scripts($hook)
    {
      if ($hook !== 'toplevel_page_adflipr-settings') {
        return;
      }

      $this->enqueue_scripts();
    }

    /**
     * Render React app shortcode
     *
     * Enqueues the same admin bundle as the Adflipr settings screen (requires `build/` after `npm run build`).
     * Restricted to users who can `manage_options` because localized REST credentials are admin-scoped.
     *
     * @return string HTML for the React app container
     */
    public function render_react_app_shortcode()
    {
      if (! is_user_logged_in() || ! current_user_can('manage_options')) {
        return '<p class="adflipr-shortcode-login-notice">' . esc_html__(
          'The Adflipr app is available to site administrators after you sign in.',
          'adflipr-for-woocommerce'
        ) . '</p>';
      }

      if (! class_exists('AdFlipr_Assets') || ! AdFlipr_Assets::has_admin_bundle()) {
        return '<p class="adflipr-shortcode-missing-build">' . esc_html__(
          'Adflipr assets are missing. Run npm run build in the plugin directory or reinstall the plugin package.',
          'adflipr-for-woocommerce'
        ) . '</p>';
      }

      $this->enqueue_scripts();

      return '<div id="adflipr-wp-react-app"></div>';
    }
  }

  if (class_exists('AdFlipr_Core')) {
    AdFlipr_Core::register('admin', 'AdFlipr_Admin');
  }
}
