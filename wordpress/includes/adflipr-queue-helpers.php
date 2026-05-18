<?php
/**
 * Outbound webhook retry queue helpers (single wp_option — see AdFlipr_Data).
 *
 * Persisted shape: `array{ queue: list<array>, id_set: array<string, true> }` for O(1) dedupe by `webhookId`.
 * Legacy shape: a plain indexed list of queue rows; normalized on read.
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

/** Option/array key for the queue list (wrapped storage). */
const ADFLIPR_QUEUE_STORAGE_QUEUE = 'queue';

/** Option/array key for webhookId → true (wrapped storage). */
const ADFLIPR_QUEUE_STORAGE_ID_SET = 'id_set';

/**
 * Extract webhookId from a payload if present.
 *
 * @param array<string, mixed> $payload
 */
function adflipr_queue_payload_webhook_id(array $payload)
{
  return isset($payload['webhookId']) && is_string($payload['webhookId']) && $payload['webhookId'] !== ''
    ? $payload['webhookId']
    : null;
}

/**
 * Whether an id_set already has this payload's webhookId (O(1)).
 *
 * @param array<string, true>    $id_set
 * @param array<string, mixed>   $payload
 */
function adflipr_queue_id_set_has_webhook_payload(array $id_set, array $payload)
{
  $want = adflipr_queue_payload_webhook_id($payload);

  return $want !== null && isset($id_set[$want]);
}

/**
 * Rebuild webhookId hash set from queue rows (used after drain and for legacy migration).
 *
 * @param array<int, array<string, mixed>> $queue
 *
 * @return array<string, true>
 */
function adflipr_queue_rebuild_id_set_from_queue(array $queue)
{
  $id_set = [];
  foreach ($queue as $item) {
    if (! is_array($item) || empty($item['payload']) || ! is_array($item['payload'])) {
      continue;
    }
    $existing = adflipr_queue_payload_webhook_id($item['payload']);
    if ($existing !== null) {
      $id_set[$existing] = true;
    }
  }

  return $id_set;
}

/**
 * Normalize option value to queue + id_set (migrates legacy flat list).
 *
 * @param mixed $raw Value from get_option(ADFLIPR_WEBHOOK_RETRY_QUEUE_OPTION)
 *
 * @return array{queue: array<int, array<string, mixed>>, id_set: array<string, true>}
 */
function adflipr_queue_normalize_storage($raw)
{
  $empty = [
    ADFLIPR_QUEUE_STORAGE_QUEUE  => [],
    ADFLIPR_QUEUE_STORAGE_ID_SET => [],
  ];
  if (! is_array($raw)) {
    return $empty;
  }

  $q_key  = ADFLIPR_QUEUE_STORAGE_QUEUE;
  $id_key = ADFLIPR_QUEUE_STORAGE_ID_SET;

  $is_wrapped = array_key_exists($q_key, $raw) && array_key_exists($id_key, $raw)
    && is_array($raw[$q_key]) && is_array($raw[$id_key]);

  if ($is_wrapped) {
    /** @var array<int, array<string, mixed>> $queue */
    $queue = array_values($raw[$q_key]);
    $id_set = [];
    foreach ($raw[$id_key] as $id => $_) {
      if (is_string($id) && $id !== '') {
        $id_set[$id] = true;
      }
    }
    if ($queue === []) {
      $id_set = [];
    }

    return [
      $q_key  => $queue,
      $id_key => $id_set,
    ];
  }

  // Partial save or hand-edited option: queue list without id_set — rebuild set.
  if (array_key_exists($q_key, $raw) && is_array($raw[$q_key])) {
    /** @var array<int, array<string, mixed>> $queue */
    $queue = array_values($raw[$q_key]);

    return [
      $q_key  => $queue,
      $id_key => adflipr_queue_rebuild_id_set_from_queue($queue),
    ];
  }

  /** @var array<int, array<string, mixed>> $queue */
  $queue = array_values($raw);

  return [
    $q_key  => $queue,
    $id_key => adflipr_queue_rebuild_id_set_from_queue($queue),
  ];
}

/**
 * Serialize storage for update_option (always wrapped).
 *
 * @param array<int, array<string, mixed>> $queue
 * @param array<string, true>              $id_set
 *
 * @return array{queue: array<int, array<string, mixed>>, id_set: array<string, true>}
 */
function adflipr_queue_pack_for_option(array $queue, array $id_set)
{
  return [
    ADFLIPR_QUEUE_STORAGE_QUEUE  => array_values($queue),
    ADFLIPR_QUEUE_STORAGE_ID_SET => $id_set,
  ];
}

/**
 * Whether the retry queue already contains the same webhookId (O(n); prefers id_set when available).
 *
 * @param array<int, array<string, mixed>> $queue
 * @param array<string, mixed>             $payload
 */
function adflipr_queue_contains_webhook_id(array $queue, array $payload)
{
  return adflipr_queue_id_set_has_webhook_payload(
    adflipr_queue_rebuild_id_set_from_queue($queue),
    $payload
  );
}
