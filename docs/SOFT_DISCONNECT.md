# WooCommerce soft disconnect (bidirectional)

## Overview

AdFlipr and the WordPress plugin use **soft disconnect** only: trust and sync stop on both sides, but **store identity**, **metadata**, and **WooCommerce REST API keys** are kept so reconnect is smooth.

## States (`Session` on AdFlipr)

See **`service/docs/INTEGRATION_LIFECYCLE.md`** for the unified Shopify + WooCommerce model.

| Status | Behavior |
|--------|----------|
| `ACTIVE` | Webhooks, proxy, sync, retries allowed |
| `DISCONNECTED` | Intentional disconnect; WP tokens revoked; plugin stops outbound work |
| `EXPIRED` | Auth invalid; reconnect required |

`deleted` is archival; runtime uses `integrationStatus` via `IntegrationLifecycleService`.

## Disconnect from AdFlipr dashboard

1. `PUT /api/v1/integration/connection/disconnect`
2. Session → `DISCONNECTED`, `deleted=true`, `disconnectedAt` set
3. All WP integration tokens for the workspace owner are cleared
4. Best-effort `POST {siteUrl}/wp-json/adflipr/v1/disconnect` with HMAC headers (`X-Adflipr-Store-Uuid`, `X-Adflipr-Timestamp`, `X-Adflipr-Signature`)

## Disconnect from WordPress

1. Admin double-confirms (explains will / will not stop)
2. `PUT` AdFlipr disconnect API (existing token)
3. `soft_disconnect('local')` — clears `adflipr_auth_token`, sets `adflipr_integration_active=0`, clears retry queue

## Remote sync when AdFlipr rejects webhooks

If the API returns HTTP 403 with `"code":"integration_deleted"`, the plugin runs `soft_disconnect('remote')` and shows an admin notice. Retry queue items are not retried after that.

## What stops after disconnect

- Outbound webhooks and retry drain
- Cart/checkout event sending (no valid token + inactive flag)
- WP proxy calls to AdFlipr (403 `integration_deleted` or invalid token)
- Backend webhook processing for inactive sessions

## What is preserved

- `adflipr_store_uuid` (WordPress option)
- WooCommerce `AdFlipr API Key` rows (no revocation yet)
- AdFlipr `Session` document (soft-deleted, credentials retained)
- Historical analytics in AdFlipr

## Reconnect

WordPress login sends `woocommerce_connect` again → `upsertWooCommerceSession` revives the session (`deleted=false`, `ACTIVE`), refreshes credentials, sets `adflipr_integration_active=1`.

## Security

- Remote disconnect endpoint verifies HMAC + store UUID + timestamp skew (5 minutes)
- No unauthenticated public disconnect without valid signature
- Signing key derived at connect: `hash_hmac('sha256', consumer_secret, 'adflipr-wc-disconnect-v1')`

## Future: hard revoke

Optional later step: delete WooCommerce API keys and purge signing material. Not implemented in this phase.
