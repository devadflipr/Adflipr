# WordPress Plugin Check / WordPress.org audit tracker

Living checklist for [Plugin Check](https://wordpress.org/plugins/plugin-check/) and WordPress.org submission readiness.  
**Update this file** when you fix an item or re-run the scan.

| Field | Value |
|-------|--------|
| Plugin | Adflipr for WooCommerce |
| Stable tag | 1.0.7 |
| Last scan | 2026-05-16 (manual export) |
| Zip command | `./create-plugin-zip.sh` (from `wordpress/`) |
| Org account email | developer@adflipr.com |
| Org username | devadflipr |

## Contributors (readme.txt)

WordPress.org **`Contributors:`** must be **WordPress.org username slug(s)** (comma-separated), **not** an email address.

| Item | Status | Notes |
|------|--------|--------|
| Org account | `developer@adflipr.com` | Login email for wordpress.org |
| readme `Contributors:` | **Done** | `devadflipr` — [profile](https://profiles.wordpress.org/devadflipr/) |

## Scan results

Legend: **Fixed** | **Open** | **Accepted** (intentional / documented)

### Errors

| Status | File | Line | Code | Message | Resolution |
|--------|------|------|------|---------|------------|
| Fixed | `includes/class-adflipr-auth.php` | 154 | `WordPress.WP.I18n.NonSingularStringLiteralText` | Concatenated string in `__()` | Use `sprintf( __( 'Authentication Failed: %s', 'adflipr' ), … )` |
| Fixed | `readme.txt` | — | `outdated_tested_upto_header` | Tested up to &lt; 6.9 | Set `Tested up to: 6.9` in readme + plugin header |

### Warnings

| Status | File | Line | Code | Message | Resolution |
|--------|------|------|------|---------|------------|
| Fixed | `includes/adflipr-debug.php` | 63 | `WordPress.PHP.DevelopmentFunctions.error_log_error_log` | `error_log()` in production | `phpcs:ignore` on gated `ADFLIPR_DEBUG` path |
| Fixed | `includes/class-adflipr-admin-page.php` | 160, 169, 599 | `WordPress.Security.NonceVerification.Recommended` | `$_GET` without nonce | `phpcs:ignore` — read-only admin routing / display notices |
| N/A | `includes/class-adflipr-transactional-email.php` | — | — | File removed (transactional email feature dropped) | — |
| Fixed | `includes/class-adflipr-cart-tracker.php` | 341 | `MissingUnslash` / `InputNotSanitized` | `$_SERVER[$key]` | `wp_unslash` + `sanitize_text_field`; comma-separated proxy IPs |
| Accepted | `includes/class-adflipr-auth.php` | 430, 458, 506 | `WordPress.DB.DirectDatabaseQuery.*` | Direct `$wpdb` for WC API keys | No WC API for programmatic keys; `phpcs:disable` / `phpcs:ignore` with comment |
| Accepted | `includes/class-adflipr-data.php` | 272 | `WordPress.DB.SlowDBQuery.slow_db_query_meta_query` | `meta_query` on `wc_get_orders` | Batched bulk sync only; `phpcs:ignore` — no WC arg for “meta not exists” without meta_query |
| Fixed | `readme.txt` | — | `readme_parser_warnings_too_many_tags` | &gt; 5 tags | Reduced to 5 tags |
| Fixed | `readme.txt` | — | `readme_parser_warnings_trimmed_short_description` | Short description &gt; 150 chars | Shortened tagline under header |
| Fixed | `uninstall.php` | 18, 30, 51 | `PrefixAllGlobals.NonPrefixedVariableFound` | `$token`, `$option_keys`, `$key` | Renamed to `$adflipr_*` |

## Pre-submission (not from Plugin Check)

| Status | Item | Notes |
|--------|------|--------|
| Open | Directory assets (SVN `assets/`) | `icon-256x256.png`, `banner-772x250.png`, screenshots |
| Open | Confirm `Contributors:` wp.org username | See above — email cannot be used in readme |
| Open | Manual test WP 6.9 + latest WooCommerce | After bumping Tested up to |
| Open | Re-run Plugin Check on fresh `adflipr.zip` | `./create-plugin-zip.sh` after `npm run build` |
| Open | Sync `docs/PLUGIN_BEHAVIOR.md` with admin UI | Health panel / disconnect flow |

## Changelog (this audit doc)

| Date | Change |
|------|--------|
| 2026-05-16 | Initial tracker from Plugin Check export; code + readme fixes applied |

## How to re-run

1. `cd wordpress && npm run build`
2. `./create-plugin-zip.sh`
3. Upload `adflipr.zip` to Plugin Check (or run PHPCS with WordPress rules on `includes/`)
4. Move rows to **Fixed** / **Accepted** and update **Last scan** date
