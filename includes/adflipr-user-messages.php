<?php
/**
 * User-facing error messages for AdFlipr WordPress plugin (no technical HTTP details).
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

if (! function_exists('adflipr_error_codes')) {
  /**
   * @return array<string, string>
   */
  function adflipr_error_codes()
  {
    return array(
      'INVALID_CREDENTIALS'                    => 'INVALID_CREDENTIALS',
      'WRONG_PASSWORD'                           => 'WRONG_PASSWORD',
      'EMAIL_NOT_FOUND'                          => 'EMAIL_NOT_FOUND',
      'SERVER_ERROR'                             => 'SERVER_ERROR',
      'NETWORK_ERROR'                            => 'NETWORK_ERROR',
      'WC_SETUP_ERROR'                           => 'WC_SETUP_ERROR',
      'ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE'    => 'ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE',
      'STORE_ALREADY_ON_ANOTHER_ACCOUNT'         => 'STORE_ALREADY_ON_ANOTHER_ACCOUNT',
      'ACCOUNT_ALREADY_HAS_STORE_INTEGRATION'    => 'ACCOUNT_ALREADY_HAS_STORE_INTEGRATION',
      'SESSION_EXPIRED'                          => 'SESSION_EXPIRED',
      'RATE_LIMITED'                             => 'RATE_LIMITED',
      'INVALID_REQUEST'                        => 'INVALID_REQUEST',
      'UNKNOWN'                                  => 'UNKNOWN',
    );
  }
}

if (! function_exists('adflipr_is_known_api_error_code')) {
  /**
   * @param string $code API {@code ErrorResponse.code} or plugin error_code.
   */
  function adflipr_is_known_api_error_code($code)
  {
    if (! is_string($code) || $code === '') {
      return false;
    }
    return array_key_exists($code, adflipr_error_codes());
  }
}

if (! function_exists('adflipr_user_message_for_code')) {
  /**
   * @param string $code One of adflipr_error_codes() values.
   */
  function adflipr_user_message_for_code($code)
  {
    $messages = array(
      'INVALID_CREDENTIALS'                 => __('The email or password you entered is incorrect.', 'adflipr-for-woocommerce'),
      'WRONG_PASSWORD'                      => __('The password you entered is incorrect. Please try again.', 'adflipr-for-woocommerce'),
      'EMAIL_NOT_FOUND'                     => __('We could not find an account with that email address.', 'adflipr-for-woocommerce'),
      'SERVER_ERROR'                        => __('Something went wrong on our end. Please try again in a few minutes.', 'adflipr-for-woocommerce'),
      'NETWORK_ERROR'                       => __('Could not reach Adflipr. Check your internet connection and try again.', 'adflipr-for-woocommerce'),
      'WC_SETUP_ERROR'                      => __('We could not connect WooCommerce on this site. Ensure WooCommerce is active and try again.', 'adflipr-for-woocommerce'),
      'ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE' => __('This Adflipr account is already connected to another WooCommerce store. Disconnect it in Adflipr (App Integrations) or on the original WordPress site, then try again here.', 'adflipr-for-woocommerce'),
      'STORE_ALREADY_ON_ANOTHER_ACCOUNT'    => __('This WooCommerce store is already connected to another Adflipr account. Sign in with that account or disconnect the store there first.', 'adflipr-for-woocommerce'),
      'ACCOUNT_ALREADY_HAS_STORE_INTEGRATION' => __('This Adflipr account already has a store connected (for example Shopify). Disconnect it in Adflipr App Integrations before connecting WooCommerce.', 'adflipr-for-woocommerce'),
      'SESSION_EXPIRED'                     => __('Your session expired. Refresh this page and try again.', 'adflipr-for-woocommerce'),
      'RATE_LIMITED'                        => __('Too many attempts. Please wait a moment and try again.', 'adflipr-for-woocommerce'),
      'INVALID_REQUEST'                     => __('Please check your details and try again.', 'adflipr-for-woocommerce'),
      'UNKNOWN'                             => __('Something went wrong. Please try again.', 'adflipr-for-woocommerce'),
    );

    return isset($messages[$code]) ? $messages[$code] : $messages['UNKNOWN'];
  }
}

if (! function_exists('adflipr_classify_auth_failure')) {
  /**
   * Map auth/login failure context to a stable error code (fallback when API omits {@code code}).
   *
   * @param int    $http_code  HTTP status from upstream (0 if unknown).
   * @param string $raw_detail Technical detail for logs only.
   */
  function adflipr_classify_auth_failure($http_code, $raw_detail = '')
  {
    $codes   = adflipr_error_codes();
    $detail  = is_string($raw_detail) ? strtolower($raw_detail) : '';
    $http    = (int) $http_code;

    if (strpos($detail, 'password you entered is incorrect') !== false) {
      return $codes['WRONG_PASSWORD'];
    }

    if (strpos($detail, 'could not find an account') !== false) {
      return $codes['EMAIL_NOT_FOUND'];
    }

    if (
      $http === 401
      || $http === 403
      || strpos($detail, 'invalid') !== false
      || strpos($detail, 'incorrect') !== false
      || strpos($detail, 'unauthorized') !== false
      || strpos($detail, 'authentication failed') !== false
      || strpos($detail, 'bad credentials') !== false
      || strpos($detail, 'wrong password') !== false
    ) {
      return $codes['INVALID_CREDENTIALS'];
    }

    if ($http === 429 || strpos($detail, 'too many') !== false || strpos($detail, 'rate limit') !== false) {
      return $codes['RATE_LIMITED'];
    }

    if (strpos($detail, 'already has a connected woocommerce store') !== false) {
      return $codes['ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE'];
    }

    if (strpos($detail, 'already connected to another account') !== false) {
      return $codes['STORE_ALREADY_ON_ANOTHER_ACCOUNT'];
    }

    if (strpos($detail, 'already has a connected store') !== false) {
      return $codes['ACCOUNT_ALREADY_HAS_STORE_INTEGRATION'];
    }

    if (
      strpos($detail, 'api key') !== false
      || strpos($detail, 'super admin') !== false
      || strpos($detail, 'wc_keys') !== false
      || strpos($detail, 'could not prepare woocommerce') !== false
    ) {
      return $codes['WC_SETUP_ERROR'];
    }

    if (
      $http >= 502
      || strpos($detail, 'service unavailable') !== false
    ) {
      return $codes['SERVER_ERROR'];
    }

    if (
      $http === 400
      || $http === 422
      || strpos($detail, 'invalid request') !== false
    ) {
      return $codes['INVALID_REQUEST'];
    }

    if (
      strpos($detail, 'timed out') !== false
      || strpos($detail, 'timeout') !== false
      || strpos($detail, 'could not resolve') !== false
      || strpos($detail, 'connection refused') !== false
      || strpos($detail, 'curl error') !== false
    ) {
      return $codes['NETWORK_ERROR'];
    }

    if (
      strpos($detail, 'nonce') !== false
      || strpos($detail, 'not connected') !== false
      || strpos($detail, 'session') !== false
    ) {
      return $codes['SESSION_EXPIRED'];
    }

    return $codes['UNKNOWN'];
  }
}

if (! function_exists('adflipr_auth_failure_response')) {
  /**
   * Build a REST-safe login failure payload.
   *
   * @param int    $http_code  Upstream HTTP status (0 if N/A).
   * @param string $raw_detail Technical detail (logged, not shown verbatim).
   * @param string $forced_code Optional plugin/API error code (skips classification).
   * @return array{status: false, error_code: string, message: string}
   */
  function adflipr_auth_failure_response($http_code, $raw_detail = '', $forced_code = '')
  {
    $code = is_string($forced_code) && adflipr_is_known_api_error_code($forced_code)
      ? $forced_code
      : adflipr_classify_auth_failure($http_code, $raw_detail);

    if ($raw_detail !== '') {
      adflipr_debug_log(
        'auth_failure_classified',
        array(
          'http_code'  => (int) $http_code,
          'error_code' => $code,
          'detail'     => is_string($raw_detail) ? substr($raw_detail, 0, 500) : '',
        )
      );
    }

    return array(
      'status'     => false,
      'error_code' => $code,
      'message'    => adflipr_user_message_for_code($code),
    );
  }
}

if (! function_exists('adflipr_extract_api_error_detail')) {
  /**
   * Combine ErrorResponse message + details for classification (login failures often put the user text in details).
   *
   * @param array<string, mixed> $body Decoded JSON body.
   */
  function adflipr_extract_api_error_detail($body)
  {
    if (! is_array($body)) {
      return '';
    }

    $parts = array();
    if (isset($body['message']) && is_string($body['message'])) {
      $trimmed = trim($body['message']);
      if ($trimmed !== '') {
        $parts[] = $trimmed;
      }
    }
    if (isset($body['details']) && is_array($body['details'])) {
      foreach ($body['details'] as $item) {
        if (is_string($item)) {
          $trimmed = trim($item);
          if ($trimmed !== '') {
            $parts[] = $trimmed;
          }
        }
      }
    }

    return implode(' ', $parts);
  }
}

if (! function_exists('adflipr_map_api_code_to_plugin_code')) {
  /**
   * Map Adflipr API ErrorResponse.code (e.g. EG-005) to plugin error_code keys.
   *
   * @param string $api_code Value from API {@code code} field.
   */
  function adflipr_map_api_code_to_plugin_code($api_code)
  {
    if (! is_string($api_code) || $api_code === '') {
      return '';
    }

    $api_to_plugin = array(
      'EG-005' => 'WRONG_PASSWORD',
      'EG-003' => 'EMAIL_NOT_FOUND',
    );

    if (isset($api_to_plugin[ $api_code ])) {
      return $api_to_plugin[ $api_code ];
    }

    if (adflipr_is_known_api_error_code($api_code)) {
      return $api_code;
    }

    return '';
  }
}

if (! function_exists('adflipr_auth_failure_from_api_response')) {
  /**
   * Prefer stable API {@code code} from ErrorResponse when login fails.
   *
   * @param int                  $http_code HTTP status from Adflipr API.
   * @param array<string, mixed> $body    Decoded JSON body (may be empty).
   * @return array{status: false, error_code: string, message: string}
   */
  function adflipr_auth_failure_from_api_response($http_code, $body)
  {
    $detail = adflipr_extract_api_error_detail($body);

    $forced_code = '';
    if (is_array($body) && isset($body['code']) && is_string($body['code'])) {
      $forced_code = adflipr_map_api_code_to_plugin_code($body['code']);
    }

    return adflipr_auth_failure_response($http_code, $detail, $forced_code);
  }
}
