<?php
/**
 * Stable idempotency keys for outbound WooCommerce webhook payloads (field: webhookId).
 * The backend deduplicates using Mongo webhookId + provider "WooCommerce".
 *
 * Retry / loss semantics: {@see AdFlipr_Data::send_data_on_webhook} queues on non-200 unless
 * `skip_retry_enqueue`. Queue cap and max attempts can drop payloads — see PLUGIN_BEHAVIOR.md.
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

/**
 * Recursively ksort arrays for deterministic JSON (stable webhookId hashes).
 *
 * @param array<string, mixed> $arr
 * @return array<string, mixed>
 */
function adflipr_webhook_deep_ksort(array $arr)
{
  ksort($arr, SORT_STRING);
  foreach ($arr as $k => $v) {
    if (is_array($v)) {
      $arr[ $k ] = adflipr_webhook_deep_ksort($v);
    }
  }

  return $arr;
}

/**
 * JSON encode with sorted object keys at all levels (avoids hash churn from key order).
 *
 * @param mixed $value
 */
function adflipr_webhook_stable_json($value)
{
  if (is_array($value)) {
    return wp_json_encode(adflipr_webhook_deep_ksort($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  return wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

if (! function_exists('adflipr_webhook_payload_with_idempotency')) {
  /**
   * Ensures payload contains webhookId for backend deduplication (does not replace if already set).
   *
   * @param array<string, mixed> $data
   * @return array<string, mixed>
   */
  function adflipr_webhook_payload_with_idempotency(array $data)
  {
    if (! empty($data['webhookId'])) {
      return $data;
    }

    $host   = wp_parse_url(home_url(), PHP_URL_HOST) ?: 'site';
    $action = isset($data['action']) ? strtolower((string) $data['action']) : 'unknown';

    switch ($action) {
      case 'add_product':
        $body = isset($data['product']) && is_array($data['product'])
          ? adflipr_webhook_stable_json($data['product'])
          : '';
        $data['webhookId'] = 'wcwp_' . hash('sha256', $host . '|add_product|' . $body);
        break;

      case 'delete_product':
        $data['webhookId'] = 'wcwp_' . hash('sha256', $host . '|delete_product|' . (string) ($data['product_id'] ?? ''));
        break;

      case 'add_coupon':
        $coupon = $data['product'] ?? $data['coupon'] ?? [];
        $body   = is_array($coupon) ? adflipr_webhook_stable_json($coupon) : '';
        $data['webhookId'] = 'wcwp_' . hash('sha256', $host . '|add_coupon|' . $body);
        break;

      case 'delete_coupon':
        $data['webhookId'] = 'wcwp_' . hash('sha256', $host . '|delete_coupon|' . (string) ($data['coupon_id'] ?? ''));
        break;

      case 'send_data_on_connect':
        $copy = $data;
        unset($copy['webhookId']);
        $data['webhookId'] = 'wcwp_' . hash('sha256', $host . '|send_data_on_connect|' . adflipr_webhook_stable_json($copy));
        break;

      case 'cart_abandonment':
        $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : '';
        $ch    = isset($data['cart_hash']) ? (string) $data['cart_hash'] : '';
        $data['webhookId'] = 'wcwp_' . hash('sha256', $host . '|cart_abandonment|' . $email . '|' . $ch);
        break;

      case 'checkout_purchased':
        $oid = '';
        if (isset($data['order_data']['id'])) {
          $oid = (string) $data['order_data']['id'];
        }
        $data['webhookId'] = 'wcwp_' . hash('sha256', $host . '|checkout_purchased|' . $oid);
        break;

      default:
        // Unknown actions: random id — backend may not dedupe retries of the same logical event.
        $data['webhookId'] = 'wcwp_' . hash('sha256', $host . '|' . $action . '|' . wp_generate_uuid4());
        break;
    }

    return $data;
  }
}
