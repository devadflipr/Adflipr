<?php
/**
 * One-time upgrade migrations (runs on plugins_loaded after updates).
 *
 * @package AdFlipr
 */

defined('ABSPATH') || exit;

/** Option key: map of migration id => applied unix timestamp. */
const ADFLIPR_MIGRATIONS_OPTION = 'adflipr_migrations';

/** Enable popup embed for stores connected before storefront popups shipped. */
const ADFLIPR_MIGRATION_POPUP_EMBED_CONNECTED_V1 = 'popup_embed_connected_v1';

/**
 * @param string $migration_id Stable migration identifier.
 * @return bool
 */
function adflipr_migration_is_applied($migration_id)
{
  $applied = get_option(ADFLIPR_MIGRATIONS_OPTION, array());
  if (! is_array($applied)) {
    return false;
  }

  return ! empty($applied[$migration_id]);
}

/**
 * @param string $migration_id Stable migration identifier.
 */
function adflipr_migration_mark_applied($migration_id)
{
  $applied = get_option(ADFLIPR_MIGRATIONS_OPTION, array());
  if (! is_array($applied)) {
    $applied = array();
  }

  $applied[$migration_id] = time();
  update_option(ADFLIPR_MIGRATIONS_OPTION, $applied, false);
}

/**
 * Whether this install has completed an Adflipr connect (token or signing key from connect).
 *
 * @return bool
 */
function adflipr_site_had_adflipr_connection()
{
  $token = get_option('adflipr_auth_token', '');
  if (is_string($token) && $token !== '') {
    return true;
  }

  if (defined('ADFLIPR_DISCONNECT_SIGNING_KEY_OPTION')) {
    $signing_key = get_option(ADFLIPR_DISCONNECT_SIGNING_KEY_OPTION, '');
    if (is_string($signing_key) && $signing_key !== '') {
      return true;
    }
  }

  return false;
}

/**
 * Turn on storefront popup embed for connected stores that predated the feature.
 */
function adflipr_upgrade_enable_popup_embed_for_connected_stores()
{
  if (! function_exists('adflipr_integration_is_active') || ! adflipr_integration_is_active()) {
    return;
  }

  if (! function_exists('adflipr_popup_embed_option_enabled')) {
    require_once __DIR__ . '/class-adflipr-popup-embed.php';
  }

  if (adflipr_popup_embed_option_enabled()) {
    return;
  }

  if (! adflipr_site_had_adflipr_connection()) {
    return;
  }

  if (function_exists('adflipr_enable_popup_embed_option')) {
    adflipr_enable_popup_embed_option();
  }

  if (function_exists('adflipr_debug_log')) {
    adflipr_debug_log(
      'upgrade_popup_embed_enabled',
      array(
        'migration' => ADFLIPR_MIGRATION_POPUP_EMBED_CONNECTED_V1,
      )
    );
  }
}

/**
 * Run pending migrations once per install.
 */
function adflipr_run_upgrades()
{
  if (adflipr_migration_is_applied(ADFLIPR_MIGRATION_POPUP_EMBED_CONNECTED_V1)) {
    return;
  }

  adflipr_upgrade_enable_popup_embed_for_connected_stores();
  adflipr_migration_mark_applied(ADFLIPR_MIGRATION_POPUP_EMBED_CONNECTED_V1);
}

add_action('plugins_loaded', 'adflipr_run_upgrades', 0);
