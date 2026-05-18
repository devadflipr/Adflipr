<?php
/**
 * Optional debug logging (no PII — never log tokens or full payloads).
 */

defined('ABSPATH') || exit;

/**
 * Whether diagnostic logging is enabled for {@see adflipr_debug_log()}.
 *
 * Production / WordPress.org policy: **only** explicit {@see ADFLIPR_DEBUG} in `wp-config.php`.
 * Do not tie integration logs to {@see WP_DEBUG} (avoids noisy hosting logs).
 */
function adflipr_debug_logging_enabled()
{
  return defined('ADFLIPR_DEBUG') && ADFLIPR_DEBUG;
}

/**
 * Files to append the same line to (besides PHP's default error_log). Makes lines visible in `wp-content/debug.log`.
 *
 * @return string[]
 */
function adflipr_debug_mirror_log_files()
{
  if (! defined('WP_CONTENT_DIR')) {
    return array();
  }

  $paths = array();

  if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    if (is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== ''
      && (strpos(WP_DEBUG_LOG, '/') !== false || strpos(WP_DEBUG_LOG, '\\') !== false)) {
      $paths[] = WP_DEBUG_LOG;
    } else {
      $paths[] = WP_CONTENT_DIR . '/debug.log';
    }
  } elseif (adflipr_debug_logging_enabled() && defined('WP_CONTENT_DIR')) {
    $paths[] = WP_CONTENT_DIR . '/debug.log';
  }

  return array_values(array_unique($paths));
}

/**
 * Log a diagnostic line when {@see adflipr_debug_logging_enabled()} is true.
 * Mirrors to `wp-content/debug.log` (or {@see WP_DEBUG_LOG} path) when configured so local debugging matches WordPress conventions.
 *
 * @param array<string, mixed> $context Safe metadata only (action keys, HTTP codes, counts).
 */
function adflipr_debug_log($message, array $context = [])
{
  if (! adflipr_debug_logging_enabled()) {
    return;
  }

  $line = '[AdFlipr] ' . (string) $message;
  if (count($context) > 0) {
    $line .= ' ' . wp_json_encode(adflipr_debug_redact_context($context));
  }

  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated by ADFLIPR_DEBUG; no PII in $line.
  error_log($line);

  foreach (adflipr_debug_mirror_log_files() as $log_file) {
    if ($log_file === '') {
      continue;
    }
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional mirror for local diagnostics.
    @error_log($line . "\n", 3, $log_file);
  }
}

/**
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function adflipr_debug_redact_context(array $context)
{
  $redact_keys = array(
    'Authorization',
    'authorization',
    'token',
    'password',
    'secret',
    'consumer_key',
    'consumer_secret',
  );
  foreach ($redact_keys as $key) {
    if (array_key_exists($key, $context)) {
      $context[$key] = '[redacted]';
    }
  }

  return $context;
}
