<?php
/**
 * Path sanitization helpers (REST params, proxy segments, etc.).
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

/**
 * Strip traversal, NULs, and backslashes; collapse segments. Relative path only (no leading slash).
 *
 * @param mixed $path Raw value (e.g. {@see \WP_REST_Request::get_param()}).
 *
 * @return string Non-empty normalized path, or '' if invalid / empty after sanitization.
 */
function adflipr_sanitize_relative_path($path)
{
  if (! is_string($path)) {
    return '';
  }

  $path = str_replace(array("\0", '\\'), '', $path);
  // If a caller accidentally includes a querystring in the path (e.g. "automation?status=ACTIVE"),
  // strip it so upstream query params don't get duplicated.
  $qpos = strpos($path, '?');
  if ($qpos !== false) {
    $path = substr($path, 0, $qpos);
  }
  $hpos = strpos($path, '#');
  if ($hpos !== false) {
    $path = substr($path, 0, $hpos);
  }
  $path = trim($path, '/');
  if ($path === '') {
    return '';
  }

  $parts = array();
  foreach (explode('/', $path) as $seg) {
    if ($seg === '' || $seg === '.' || $seg === '..') {
      continue;
    }
    $parts[] = $seg;
  }

  return implode('/', $parts);
}
