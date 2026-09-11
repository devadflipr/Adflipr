# Hardening & WordPress.org compliance — change log

## 1.0.1 (hardening pass)

- **Distribution:** Removed `bin/sample.php`. Added `.distignore`. Zip bundles **runtime + `build/` + source** (`src/`, `package.json`, build scripts); excludes `node_modules/` and repo-only paths (WordPress.org compiled-plugin policy).
- **Logging:** All plugin `error_log` usage removed except inside `adflipr_debug_log()` (guarded by **`ADFLIPR_DEBUG` only** — no `WP_DEBUG` auto-enable). Uninstall uses `adflipr_debug_log` with no response bodies.
- **Version:** Single semver in `includes/adflipr-version.php` (`ADFLIPR_PLUGIN_VERSION`); `ADFLIPR_VERSION` mirrors it; `readme.txt` Stable tag + `package.json` aligned; `scripts/bump-plugin-version.sh` updated.
- **Sync offsets:** `send_data_after_authentication()` uses `adflipr_subscriptions_last_offset` and `adflipr_coupons_last_offset` (not orders).
- **Cron:** `maybe_schedule_next_event()` clears `adflipr_send_data_event` (was dead `adflipr_send_product_data`). Deactivation clears `adflipr_send_data_event` singles.
- **WC REST email route:** Removed query-string consumer key acceptance; permission uses `WC_REST_Authentication::authenticate` + **`manage_woocommerce`**; `setup_email_setting` sanitizes allowed keys.
- **Proxy:** Path normalization (strip `..`, `\`, NUL); empty path → 400.
- **Queue:** Corrupt queue resets to `[]` with debug log; duplicate `webhookId` not re-enqueued (`queue` + `id_set` in the option for O(1) dedupe; legacy flat list migrates on write); `queue_full` on failure; checkout meta respects `queue_full`.
- **Health dashboard:** Admin “AdFlipr Health” panel + `AdFlipr_Health_Service` (timestamps/counts only, autoload no); REST `integration-health` includes a `health` object; enqueue return distinguishes dedupe vs new row for activity timestamps.
- **Idempotency:** Deep ksort + stable JSON for product/coupon/bulk/email_settings hashes.
- **Shortcode:** Enqueues React assets when `build/` exists; requires `manage_options` + logged in.
- **Integration health:** Adds `webhook_retry_queue_max_items`; `debug_mode` reflects `ADFLIPR_DEBUG` only.
- **WC bulk orders:** `wc_get_orders` `WP_Error` → empty array + debug log.

See `PLUGIN_BEHAVIOR.md` for runtime details.
