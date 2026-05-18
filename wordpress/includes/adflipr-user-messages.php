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
      'INVALID_CREDENTIALS'                 => __('The email or password you entered is incorrect.', 'adflipr'),
      'SERVER_ERROR'                        => __('Something went wrong on our end. Please try again in a few minutes.', 'adflipr'),
      'NETWORK_ERROR'                       => __('Could not reach Adflipr. Check your internet connection and try again.', 'adflipr'),
      'WC_SETUP_ERROR'                      => __('We could not connect WooCommerce on this site. Ensure WooCommerce is active and try again.', 'adflipr'),
      'ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE' => __('This Adflipr account is already connected to another WooCommerce store. Disconnect it in Adflipr (App Integrations) or on the original WordPress site, then try again here.', 'adflipr'),
      'STORE_ALREADY_ON_ANOTHER_ACCOUNT'    => __('This WooCommerce store is already connected to another Adflipr account. Sign in with that account or disconnect the store there first.', 'adflipr'),
      'ACCOUNT_ALREADY_HAS_STORE_INTEGRATION' => __('This Adflipr account already has a store connected (for example Shopify). Disconnect it in Adflipr App Integrations before connecting WooCommerce.', 'adflipr'),
      'SESSION_EXPIRED'                     => __('Your session expired. Refresh this page and try again.', 'adflipr'),
      'RATE_LIMITED'                        => __('Too many attempts. Please wait a moment and try again.', 'adflipr'),
      'INVALID_REQUEST'                     => __('Please check your details and try again.', 'adflipr'),
      'UNKNOWN'                             => __('Something went wrong. Please try again.', 'adflipr'),
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
    $detail = '';
    if (is_array($body) && isset($body['message'])) {
      $detail = is_string($body['message'])
        ? $body['message']
        : wp_json_encode($body['message']);
    } elseif (is_array($body) && isset($body['details']) && is_array($body['details']) && ! empty($body['details'])) {
      $first = $body['details'][0];
      $detail = is_string($first) ? $first : wp_json_encode($first);
    }

    if (is_array($body) && isset($body['code']) && is_string($body['code']) && adflipr_is_known_api_error_code($body['code'])) {
      return adflipr_auth_failure_response($http_code, $detail, $body['code']);
    }

    return adflipr_auth_failure_response($http_code, $detail);
  }
}
