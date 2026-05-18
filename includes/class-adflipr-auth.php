<?php
defined('ABSPATH') || exit; //Exit if accessed directly

/**
 * Class AdFlipr_Auth
 */
if (! class_exists('AdFlipr_Auth')) {

  class AdFlipr_Auth
  {
    /** WooCommerce REST stores {@see wc_api_hash()} of the plaintext consumer key — never insert plaintext into {@see $wpdb->prefix}woocommerce_api_keys. */
    private const WC_API_KEY_DESCRIPTION = 'Adflipr API Key';

    private static $ins = null;
    private $token;
    private $token_expiry;

    private $ADFLIPR_TIMEOUT = 30;

    public function __construct()
    {
      add_action('rest_api_init', [$this, 'adflipr_register_login_token_route']);
    }

    /**
     * Authenticate with Adflipr. When {@code $with_integration} is false, validates credentials only
     * (no WooCommerce keys, no token persistence, no store session).
     *
     * @param string $email
     * @param string $password
     * @param bool   $with_integration When false, omits woocommerce_connect from the login request.
     */
    public function authenticate($email, $password, $with_integration = true)
    {
      $connect_payload = null;

      if ($with_integration) {
      $data = $this->get_or_create_valid_woocommerce_api_key();
      if (empty($data['status'])) {
        adflipr_debug_log(
          'authenticate_blocked_wc_keys',
          array(
            'message' => isset($data['message']) && is_string($data['message']) ? $data['message'] : 'unknown',
          )
        );
        $wc_detail = isset($data['message']) && is_string($data['message'])
          ? $data['message']
          : __('Could not prepare WooCommerce API credentials.', 'adflipr-for-woocommerce');

        return array_merge(
          adflipr_auth_failure_response(0, $wc_detail),
          array('message' => adflipr_user_message_for_code('WC_SETUP_ERROR'))
        );
      }

      // Stable WooCommerce store identity:
      // - persists across reconnects and temporary disconnects
      // - resets only on uninstall (explicit reset)
      $store_uuid = get_option('adflipr_store_uuid', '');
      if (! is_string($store_uuid) || $store_uuid === '') {
        $store_uuid = wp_generate_uuid4();
        // autoload=false: keep options lightweight.
        update_option('adflipr_store_uuid', $store_uuid, false);
      }

      $hpos_enabled = false;
      if (! empty($data['status']) && class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
        $hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
      }

      // Best-effort checkout capability detection (no remote calls).
      $block_checkout_enabled = false;
      if (function_exists('wc_get_page_id')) {
        $checkout_id = (int) wc_get_page_id('checkout');
        if ($checkout_id > 0) {
          $checkout_post = get_post($checkout_id);
          if ($checkout_post instanceof WP_Post) {
            if (function_exists('has_block') && has_block('woocommerce/checkout', $checkout_post)) {
              $block_checkout_enabled = true;
            } else {
              $content = (string) $checkout_post->post_content;
              if (strpos($content, 'woocommerce/checkout') !== false
                || strpos($content, '[woocommerce_checkout') !== false) {
                $block_checkout_enabled = true;
              }
            }
          }
        }
      }

      $super_admin_id = $this->get_super_admin_id();
      $admin_email = '';
      if ($super_admin_id) {
        $u = get_userdata($super_admin_id);
        $admin_email = isset($u->user_email) ? sanitize_email((string) $u->user_email) : '';
      }

      $wordpress_version = function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '';
      $woocommerce_version = defined('WC_VERSION') ? (string) WC_VERSION : '';
      $plugin_version = defined('ADFLIPR_PLUGIN_VERSION') ? (string) ADFLIPR_PLUGIN_VERSION : '';

      $wp_cron_disabled = defined('DISABLE_WP_CRON') ? (bool) DISABLE_WP_CRON : false;
      $permalink_structure = get_option('permalink_structure', '');
      $permalink_structure = is_string($permalink_structure) ? trim($permalink_structure) : '';

      $data = array_merge([
        // Identity
        'store_uuid' => $store_uuid,
        'site_url' => site_url(),
        'home_url' => home_url(),
        'site_name' => sanitize_text_field((string) get_bloginfo('name')),

        // Environment
        'wordpress_version' => $wordpress_version,
        'woocommerce_version' => $woocommerce_version,
        'plugin_version' => $plugin_version,
        'php_version' => (string) PHP_VERSION,

        // Capabilities
        'hpos_enabled' => $hpos_enabled,
        'block_checkout_enabled' => $block_checkout_enabled,

        // Admin
        'admin_email' => $admin_email,

        // Optional diagnostics
        'wp_cron_disabled' => $wp_cron_disabled,
        'permalink_structure' => $permalink_structure,

        'from' => 'wordpress',
      ], $data);
      $connect_payload = $data;
      }

      $login_url = defined('ADFLIPR_API_WP_USERS_LOGIN_URL') ? ADFLIPR_API_WP_USERS_LOGIN_URL : ADFLIPR_API_USERS_LOGIN_URL;
      $login_parsed = wp_parse_url($login_url);
      adflipr_debug_log(
        'authenticate_adflipr_login_request',
        array(
          'login_host'   => isset($login_parsed['host']) ? (string) $login_parsed['host'] : '',
          'login_scheme' => isset($login_parsed['scheme']) ? (string) $login_parsed['scheme'] : '',
        )
      );
      $login_body = array(
        'email'    => $email,
        'password' => $password,
        'context'  => defined('ADFLIPR_LOGIN_CONTEXT_WORDPRESS') ? ADFLIPR_LOGIN_CONTEXT_WORDPRESS : 'WORDPRESS',
      );
      if ($with_integration && is_array($connect_payload)) {
        $login_body['woocommerce_connect'] = $connect_payload;
      }

      $response  = wp_remote_post($login_url, [
        'body'    => wp_json_encode($login_body),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 30,
      ]);

      $result = adflipr_auth_failure_response(0, 'authentication failed');

      if (is_wp_error($response)) {
        adflipr_debug_log(
          'authenticate_adflipr_login_wp_error',
          array(
            'error_code'    => $response->get_error_code(),
            'error_message' => $response->get_error_message(),
          )
        );

        return adflipr_auth_failure_response(0, $response->get_error_message());
      }

      $http_code   = (int) wp_remote_retrieve_response_code($response);
      $raw_body    = wp_remote_retrieve_body($response);
      $response_body = json_decode($raw_body, true);

      if ($http_code < 200 || $http_code >= 300) {
        adflipr_debug_log(
          'authenticate_adflipr_login_http_error',
          array(
            'http_status' => $http_code,
          )
        );
        if (is_array($response_body)) {
          return adflipr_auth_failure_from_api_response($http_code, $response_body);
        }
        $detail = $raw_body !== '' && strlen($raw_body) < 500 ? $raw_body : '';
        return adflipr_auth_failure_response($http_code, $detail);
      }

      if (! is_array($response_body)) {
        return adflipr_auth_failure_response(
          $http_code,
          sprintf('invalid response (HTTP %d)', $http_code)
        );
      }

      // JWT session login (/api/v1/users/login) uses loginToken + tokenExpiry.
      if (! empty($response_body['loginToken'])) {
        if ($with_integration) {
          $this->token        = $response_body['loginToken'];
          $this->token_expiry = isset($response_body['tokenExpiry']) ? $response_body['tokenExpiry'] : null;
          update_option('adflipr_auth_token', $this->token);
          update_option('adflipr_token_expiry', $this->token_expiry);
          if (is_array($connect_payload)) {
            $this->mark_integration_connected($connect_payload);
          }
        }

        return array(
          'status'  => true,
          'message' => $with_integration
            ? __('Authentication successful.', 'adflipr-for-woocommerce')
            : __('Credentials verified.', 'adflipr-for-woocommerce'),
        );
      }

      // WordPress integration login (/api/v1/wp/users/login + context WORDPRESS) returns type wp_token and JSON "token".
      if (! empty($response_body['token']) && isset($response_body['type']) && $response_body['type'] === 'wp_token') {
        if ($with_integration) {
          $this->token = $response_body['token'];
          if (! empty($response_body['tokenExpiry'])) {
            $this->token_expiry = $response_body['tokenExpiry'];
          } else {
            $ttl_sec            = (defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : 31536000) * 10;
            $this->token_expiry = (time() + $ttl_sec) * 1000;
          }
          update_option('adflipr_auth_token', $this->token);
          update_option('adflipr_token_expiry', $this->token_expiry);
          if (is_array($connect_payload)) {
            $this->mark_integration_connected($connect_payload);
          }
        }

        return array(
          'status'  => true,
          'message' => $with_integration
            ? __('Authentication successful.', 'adflipr-for-woocommerce')
            : __('Credentials verified.', 'adflipr-for-woocommerce'),
        );
      }

      return adflipr_auth_failure_from_api_response($http_code, $response_body);
    }

    /**
     * @return AdFlipr_Auth|null
     */
    public static function get_instance()
    {
      if (null === self::$ins) {
        self::$ins = new self();
      }

      return self::$ins;
    }

    // Check if the token has expired
    public function is_token_expired()
    {
      $token_expiry    = get_option('adflipr_token_expiry', false);
      $current_time_ms = round(microtime(true) * 1000);
      if (! $token_expiry) {
        return true; // Token expiry time not set, considered expired
      }

      return $current_time_ms >= intval($token_expiry);
    }

    // Get the stored token (or check if it's expired)
    public function get_token()
    {
      if ($this->is_token_expired()) {
        return false; // Token is expired or missing
      }

      return get_option('adflipr_auth_token', false); // Return the stored token
    }

    /**
     * Token present, not expired, and integration not soft-disconnected locally.
     */
    public function valid_token()
    {
      return $this->integration_active() && ! $this->is_token_expired() && (bool) $this->get_token();
    }

    /**
     * Integration allowed to sync (independent of token — used with valid_token() for outbound).
     */
    public function integration_active()
    {
      return function_exists('adflipr_integration_is_active') && adflipr_integration_is_active();
    }

    /**
     * Soft disconnect: stop trust and sync; keep storeUuid and WooCommerce API keys for reconnect.
     *
     * @param string $reason local|remote|uninstall
     */
    public function soft_disconnect($reason = 'local')
    {
      if (function_exists('adflipr_run_soft_disconnect')) {
        adflipr_run_soft_disconnect($reason);
      }

      if (defined('ADFLIPR_LAST_CART_EVENT_OPTION')) {
        delete_option(ADFLIPR_LAST_CART_EVENT_OPTION);
      }
      if (defined('ADFLIPR_LAST_ORDER_EVENT_OPTION')) {
        delete_option(ADFLIPR_LAST_ORDER_EVENT_OPTION);
      }
      if (defined('ADFLIPR_LAST_RETRY_RUN_OPTION')) {
        delete_option(ADFLIPR_LAST_RETRY_RUN_OPTION);
      }
      if (defined('ADFLIPR_LAST_WEBHOOK_SUCCESS_AT_OPTION')) {
        delete_option(ADFLIPR_LAST_WEBHOOK_SUCCESS_AT_OPTION);
      }
      if (defined('ADFLIPR_LAST_WEBHOOK_SUCCESS_ACTION_OPTION')) {
        delete_option(ADFLIPR_LAST_WEBHOOK_SUCCESS_ACTION_OPTION);
      }

      adflipr_debug_log('soft_disconnect', array('reason' => sanitize_key($reason)));
    }

    /**
     * @deprecated Use {@see soft_disconnect()}.
     */
    public function reset_token()
    {
      $this->soft_disconnect('local');
    }

    /**
     * Best-effort notify AdFlipr when disconnect starts on WordPress.
     *
     * @param string|false $token Bearer token before soft_disconnect cleared it.
     */
    public function notify_adflipr_disconnect($token)
    {
      if (function_exists('adflipr_notify_adflipr_disconnect')) {
        adflipr_notify_adflipr_disconnect($token);
      }
    }

    /**
     * Persist reconnect signing material and active flag after successful login.
     *
     * @param array<string, mixed> $connect_payload woocommerce_connect fields.
     */
    private function mark_integration_connected(array $connect_payload)
    {
      update_option(ADFLIPR_INTEGRATION_ACTIVE_OPTION, '1', false);

      $secret = isset($connect_payload['consumer_secret']) ? (string) $connect_payload['consumer_secret'] : '';
      if ($secret !== '' && function_exists('adflipr_store_disconnect_signing_key')) {
        update_option(
          ADFLIPR_DISCONNECT_SIGNING_KEY_OPTION,
          adflipr_store_disconnect_signing_key($secret),
          false
        );
      }
    }

    /**
     * REST: AdFlipr-initiated soft disconnect (HMAC-signed).
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response
     */
    public function rest_remote_disconnect(WP_REST_Request $request)
    {
      $store_uuid = (string) $request->get_header('X-Adflipr-Store-Uuid');
      $timestamp  = (string) $request->get_header('X-Adflipr-Timestamp');
      $signature  = (string) $request->get_header('X-Adflipr-Signature');

      $local_uuid = get_option('adflipr_store_uuid', '');
      if (! is_string($local_uuid) || $local_uuid === '' || $store_uuid === '' || ! hash_equals($local_uuid, $store_uuid)) {
        return new WP_REST_Response(
          array('status' => false, 'message' => 'Store identity mismatch.'),
          403
        );
      }

      if (! function_exists('adflipr_verify_remote_disconnect_signature')
        || ! adflipr_verify_remote_disconnect_signature($store_uuid, (int) $timestamp, $signature)
      ) {
        return new WP_REST_Response(
          array('status' => false, 'message' => 'Invalid disconnect signature.'),
          403
        );
      }

      $this->soft_disconnect('remote');

      return new WP_REST_Response(
        array(
          'status'  => true,
          'message' => __('Adflipr integration disconnected on this site.', 'adflipr-for-woocommerce'),
        ),
        200
      );
    }

    public function get_super_admin_id()
    {
      if (is_multisite()) {
        $super_admins = get_super_admins();

        return ! empty($super_admins) ? get_user_by('login', $super_admins[0])->ID : null;
      } else {
        $admins = get_users(['role' => 'administrator', 'number' => 1]);

        return ! empty($admins) ? $admins[0]->ID : null;
      }
    }

    public function get_or_create_valid_woocommerce_api_key($permissions = 'read')
    {
      global $wpdb;


      $user_id = $this->get_super_admin_id();
      if (! $user_id) {
        adflipr_debug_log('wc_keys_super_admin_missing', array());
        return ['status' => false, 'message' => 'Super Admin not found'];
      }

      // WooCommerce stores only wc_api_hash(consumer_key) — plaintext cannot be read back. Drop our prior row and issue fresh keys.
      // No WC API for programmatic key rows; integration uses the same table shape as WC_Auth::create_keys().
      // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
      $deleted = $wpdb->delete(
        "{$wpdb->prefix}woocommerce_api_keys",
        array(
          'user_id'     => $user_id,
          'permissions' => $permissions,
          'description' => self::WC_API_KEY_DESCRIPTION,
        ),
        array('%d', '%s', '%s')
      );
      if ($deleted > 0) {
        adflipr_debug_log(
          'wc_keys_removed_prior_adflipr_row',
          array(
            'rows' => (int) $deleted,
          )
        );
      }

      // Create a new API key (plaintext returned to callers / AdFlipr only in this request).
      $consumer_key    = 'ck_' . wc_rand_hash();
      $consumer_secret = 'cs_' . wc_rand_hash();

      if (! function_exists('wc_api_hash')) {
        adflipr_debug_log('wc_keys_wc_api_hash_missing', array());
        return ['status' => false, 'message' => 'API key validation failed'];
      }

      // Same shape as WC core {@see WC_Auth::create_keys}: hashed consumer_key in DB, plaintext secret.
      $insert_ok = $wpdb->insert("{$wpdb->prefix}woocommerce_api_keys", [
        'user_id'         => $user_id,
        'description'     => self::WC_API_KEY_DESCRIPTION,
        'permissions'     => $permissions,
        'consumer_key'    => wc_api_hash($consumer_key),
        'consumer_secret' => $consumer_secret,
        'truncated_key'   => substr($consumer_key, -7),
        'last_access'     => current_time('mysql')
      ]);

      if ($insert_ok === false) {
        adflipr_debug_log(
          'wc_keys_insert_failed',
          array(
            'wpdb_error' => $wpdb->last_error !== '' ? $wpdb->last_error : 'insert_returned_false',
          )
        );
        return ['status' => false, 'message' => 'API key validation failed'];
      }

      // Validate new key (HTTP probe and/or DB match — HTTP Basic is skipped by WooCommerce on non-SSL sites).
      if ($this->validate_woocommerce_api_key($consumer_key, $consumer_secret, 'after_insert', $permissions)) {
        return [
          'status'          => true,
          'super_admin_id'  => $user_id,
          'consumer_key'    => $consumer_key,
          'consumer_secret' => $consumer_secret
        ];
      } else {
        return ['status' => false, 'message' => 'API key validation failed'];
      }
      // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }


    /**
     * Same lookup WooCommerce uses after hashing the plaintext consumer key (REST auth).
     *
     * @param string $permissions Key permission slug (e.g. read).
     */
    private function validate_woocommerce_api_key_stored_match($consumer_key, $consumer_secret, $permissions)
    {
      if (! function_exists('wc_api_hash')) {
        return false;
      }

      global $wpdb;

      $hashed = wc_api_hash(sanitize_text_field($consumer_key));
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WC REST auth table lookup; no core wrapper.
      $row    = $wpdb->get_row(
        $wpdb->prepare(
          "SELECT consumer_secret FROM {$wpdb->prefix}woocommerce_api_keys WHERE consumer_key = %s AND permissions = %s LIMIT 1",
          $hashed,
          $permissions
        )
      );

      return $row !== null && hash_equals((string) $row->consumer_secret, (string) $consumer_secret);
    }

    /**
     * Validate WooCommerce API Key by probing WC REST and/or matching the DB row WooCommerce uses for auth.
     *
     * Note: {@see WC_REST_Authentication} runs Basic auth only when {@see is_ssl()} is true; HTTP stores need the DB fallback.
     *
     * @param string $key_source Diagnostic label (e.g. after_insert).
     * @param string $permissions Key permission (must match stored row).
     */
    public function validate_woocommerce_api_key($consumer_key, $consumer_secret, $key_source = 'unknown', $permissions = 'read')
    {
      $site_url = get_site_url();
      $endpoint = $site_url . '/wp-json/wc/v3/products';

      adflipr_debug_log(
        'wc_rest_validate_start',
        array(
          'endpoint'      => $endpoint,
          'key_source'    => $key_source,
          'site_is_ssl'   => is_ssl(),
          'wc_basic_auth' => is_ssl(),
        )
      );

      $response = wp_remote_get($endpoint, [
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode("$consumer_key:$consumer_secret"),
          'Content-Type'  => 'application/json'
        ],
        'timeout' => 30,
      ]);


      if (is_wp_error($response)) {
        adflipr_debug_log(
          'wc_rest_validate_wp_error',
          array(
            'key_source'    => $key_source,
            'error_code'    => $response->get_error_code(),
            'error_message' => $response->get_error_message(),
          )
        );
      } else {
        $status_code = (int) wp_remote_retrieve_response_code($response);
        $ok_http     = ($status_code === 200);
        adflipr_debug_log(
          $ok_http ? 'wc_rest_validate_ok_http' : 'wc_rest_validate_failed_http',
          array(
            'key_source'    => $key_source,
            'http_status'   => $status_code,
            'endpoint'      => $endpoint,
          )
        );
        if ($ok_http) {
          return true;
        }
      }

      // Non-SSL: WooCommerce does not apply Basic auth from headers; confirm keys match stored row instead.
      if ($this->validate_woocommerce_api_key_stored_match($consumer_key, $consumer_secret, $permissions)) {
        adflipr_debug_log(
          'wc_rest_validate_ok_stored_fallback',
          array(
            'key_source' => $key_source,
          )
        );

        return true;
      }

      adflipr_debug_log(
        'wc_rest_validate_failed',
        array(
          'key_source' => $key_source,
        )
      );

      return false;
    }

    public function adflipr_register_login_token_route()
    {
      register_rest_route('adflipr/v1', '/login-token', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => function () {
          return ['loginToken' => $this->get_token()];
        },
        'permission_callback' => [$this, 'rest_permission_login_token_read'],
      ]);

      register_rest_route('adflipr/v1', '/login-token', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => [$this, 'store_token'],
        'permission_callback' => [$this, 'rest_permission_login_token_write'],
      ]);

      // Single proxy endpoint that will handle all API requests
      register_rest_route('adflipr/v1', '/proxy/(?P<path>.*)', [
        'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
        'callback' => [$this, 'proxy_handler'],
        'permission_callback' => [$this, 'rest_permission_proxy'],
        'args' => [
          'path' => [
            'required' => true,
            'validate_callback' => function ($param) {
              return is_string($param);
            }
          ]
        ]
      ]);

      // Signed callback from AdFlipr when merchant disconnects in dashboard (not public).
      register_rest_route(
        'adflipr/v1',
        '/disconnect',
        array(
          'methods'             => WP_REST_Server::CREATABLE,
          'callback'            => array($this, 'rest_remote_disconnect'),
          'permission_callback' => '__return_true',
        )
      );
    }

    public function store_token($request)
    {
      $request_data = $request->get_body();
      $parsed       = [];
      if (! empty($request_data)) {
        $parsed = json_decode($request_data, true);
      }

      if (! is_array($parsed)) {
        return rest_ensure_response(
          array_merge(
            adflipr_auth_failure_response(400, 'invalid request body'),
            array('message' => adflipr_user_message_for_code('INVALID_REQUEST'))
          )
        );
      }

      $email_raw = isset($parsed['email']) ? $parsed['email'] : '';
      $email     = sanitize_email(is_string($email_raw) ? $email_raw : '');
      $password  = isset($parsed['password']) && is_string($parsed['password']) ? $parsed['password'] : '';

      $with_integration = true;
      if (array_key_exists('integration', $parsed) && $parsed['integration'] === false) {
        $with_integration = false;
      }

      $get_data = $this->authenticate($email, $password, $with_integration);

      if (is_array($get_data) && isset($get_data['status']) && $get_data['status']) {
        if (! $with_integration) {
          return rest_ensure_response(array(
            'status'  => true,
            'message' => isset($get_data['message']) ? $get_data['message'] : __('Credentials verified.', 'adflipr-for-woocommerce'),
          ));
        }

        delete_user_meta(get_current_user_id(), 'adflipr_connect_notice_dismissed');
        $sync_warning = '';
        try {
          $sync_result = AdFlipr_Core()->admin->send_data_after_authentication();
          if (is_array($sync_result) && isset($sync_result['status']) && $sync_result['status'] === false) {
            $sync_warning = isset($sync_result['message']) && is_string($sync_result['message'])
              ? $sync_result['message']
              : __('Initial sync did not complete.', 'adflipr-for-woocommerce');
          }
        } catch (\Throwable $e) {
          adflipr_debug_log('login_token_sync_throw', ['error' => $e->getMessage()]);
          $sync_warning = __('Initial sync failed; connection is saved.', 'adflipr-for-woocommerce');
        }
        $message = __('Token stored successfully.', 'adflipr-for-woocommerce');
        if ($sync_warning !== '') {
          $message .= ' ' . sprintf(
            /* translators: %s: sync failure detail */
            __('Note: %s', 'adflipr-for-woocommerce'),
            $sync_warning
          );
        }

        return rest_ensure_response(array(
          'status' => true,
          'message' => $message,
        ));
      } else {
        if (
          is_array($get_data)
          && isset($get_data['error_code'], $get_data['message'], $get_data['status'])
        ) {
          return rest_ensure_response($get_data);
        }

        $fail_detail = isset($get_data['message']) && is_string($get_data['message'])
          ? $get_data['message']
          : 'authentication failed';

        return rest_ensure_response(adflipr_auth_failure_response(0, $fail_detail));
      }
    }

    public function proxy_handler($request)
    {
      $path = $request->get_param('path');
      $path = adflipr_sanitize_relative_path($path);
      if ($path === '') {
        return new WP_Error(
          'adflipr_proxy_invalid_path',
          __('Invalid or empty proxy path.', 'adflipr-for-woocommerce'),
          array('status' => 400)
        );
      }

      // Get request method
      $method = $request->get_method();

      // Get request parameters based on method
      $params = [];
      if ($method === 'GET') {
        $params = $request->get_query_params();
        $params = $this->adflipr_proxy_sanitize_query_params_for_upstream($params);
        return $this->handle_internal_api_get_request($path, $params);
      }

      // Handle POST, PUT, DELETE, ... requests
      $params = $request->get_body();
      if (! empty($params)) {
        $params =  json_decode($params, true);
      } else {
        $params = [];
      }
      return $this->handle_internal_api_request($path, $method, $params);
    }

    /**
     * Upstream base path for PHP→AdFlipr proxy (must stay under api/v1/wp/ so the API applies WP integration auth).
     */
    private function adflipr_upstream_proxy_api_path($relative_path)
    {
      return 'api/v1/wp/proxy/' . ltrim((string) $relative_path, '/');
    }

    /**
     * Flatten REST query params before {@see http_build_query} so the Java API does not receive
     * bracketed keys (e.g. {@code pageNumber[0]=1}). Spring Security's default firewall rejects those
     * with HTTP 400. Also drops WordPress-only keys ({@code _locale}, etc.) and duplicate {@code path}.
     *
     * @param array<string, mixed> $params Raw {@see \WP_REST_Request::get_query_params()} output.
     * @return array<string, scalar>
     */
    private function adflipr_proxy_sanitize_query_params_for_upstream(array $params)
    {
      $out = array();
      foreach ($params as $key => $value) {
        if (! is_string($key) || $key === '') {
          continue;
        }
        if ($key[0] === '_' || $key === 'path') {
          continue;
        }
        while (is_array($value)) {
          if ($value === array()) {
            $value = null;
            break;
          }
          $value = reset($value);
        }
        if ($value === null) {
          continue;
        }
        if (is_scalar($value)) {
          $out[ $key ] = $value;
        }
      }

      return $out;
    }

    public function handle_internal_api_get_request($path, $params = [])
    {
      $qs    = is_array($params) ? http_build_query($params) : '';
      $base  = adflipr_api_url($this->adflipr_upstream_proxy_api_path($path));
      $url   = $qs !== '' ? $base . '?' . $qs : $base;
      $response = wp_remote_get($url, [
        'headers' => [
          'Authorization' => $this->get_token()
        ],
        'timeout' => $this->ADFLIPR_TIMEOUT,
      ]);

      return $this->format_remote_proxy_response($response);
    }

    public function handle_internal_api_request($path, $method, $params = [])
    {
      $url = adflipr_api_url($this->adflipr_upstream_proxy_api_path($path));
      $body = '';
      if (is_array($params)) {
        $body = wp_json_encode($params);
      } elseif (is_string($params)) {
        $body = $params;
      }

      $response = wp_remote_request($url, [
        'headers' => [
          'Authorization' => $this->get_token(),
          'Content-Type'  => 'application/json'
        ],
        'method' => $method,
        'body'   => $body,
        'timeout' => $this->ADFLIPR_TIMEOUT,
      ]);

      return $this->format_remote_proxy_response($response);
    }

    /**
     * Normalize upstream HTTP into a WP REST response (avoid leaking raw wp_remote_* arrays).
     *
     * @param array|\WP_Error $response
     * @return \WP_REST_Response|\WP_Error
     */
    private function format_remote_proxy_response($response)
    {
      if (is_wp_error($response)) {
        return new WP_Error(
          'adflipr_proxy_http_error',
          $response->get_error_message(),
          array('status' => 502)
        );
      }

      $code = (int) wp_remote_retrieve_response_code($response);
      $body = wp_remote_retrieve_body($response);
      $decoded = json_decode($body, true);

      if (! is_array($decoded)) {
        $decoded = array(
          'body' => $body,
        );
      }

      return new WP_REST_Response($decoded, $code >= 100 && $code < 600 ? $code : 200);
    }

    /**
     * GET /login-token — administrators only, valid REST + referer, existing session token.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function rest_permission_login_token_read($request)
    {
      if (! current_user_can('manage_options')) {
        return false;
      }

      $nonce_check = $this->rest_require_wp_nonce($request);
      if (is_wp_error($nonce_check)) {
        return $nonce_check;
      }

      if (! $this->valid_token()) {
        return new WP_Error(
          'adflipr_no_token',
          __('Not connected to Adflipr.', 'adflipr-for-woocommerce'),
          array('status' => 403)
        );
      }

      return $this->api_permissions_check($request);
    }

    /**
     * POST /login-token — administrators only, valid REST + referer (stores credentials server-side).
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function rest_permission_login_token_write($request)
    {
      if (! current_user_can('manage_options')) {
        return false;
      }

      $nonce_check = $this->rest_require_wp_nonce($request);
      if (is_wp_error($nonce_check)) {
        return $nonce_check;
      }

      return $this->api_permissions_check($request);
    }

    /**
     * Proxy — administrators only, REST nonce, referer, integration token.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public function rest_permission_proxy($request)
    {
      if (! current_user_can('manage_options')) {
        return false;
      }

      $nonce_check = $this->rest_require_wp_nonce($request);
      if (is_wp_error($nonce_check)) {
        return $nonce_check;
      }

      if (! $this->valid_token()) {
        return new WP_Error(
          'adflipr_no_token',
          __('Not connected to Adflipr.', 'adflipr-for-woocommerce'),
          array('status' => 403)
        );
      }

      return $this->api_permissions_check($request);
    }

    /**
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    private function rest_require_wp_nonce($request)
    {
      return adflipr_rest_verify_wp_nonce_header($request);
    }

    public function api_permissions_check($request)
    {
      // Check if the request is coming from the same site
      $referer = $request->get_header('referer');
      if (!$referer || strpos($referer, site_url()) !== 0) {
        return new WP_Error('rest_forbidden', 'Invalid request origin', ['status' => 403]);
      }
      return true;
    }
  }

  if (class_exists('AdFlipr_Core')) {
    AdFlipr_Core::register('auth', 'AdFlipr_Auth');
  }
}
