# WooCommerce connect — behavior by scenario

**Scope:** WordPress plugin login/connect flow + Adflipr backend session rules (`CommonUtil.validateWooCommerceConnect`, `UserService.handleWpIntegrationLogin`).

**Related:** `docs/PLUGIN_BEHAVIOR.md` (plugin runtime), `includes/adflipr-user-messages.php` (user-facing error codes).

---

## 1. Connect flow (happy path)

```mermaid
sequenceDiagram
  participant Admin as WP admin (plugin UI)
  participant Plugin as Adflipr WP plugin
  participant WC as WooCommerce (local)
  participant API as Adflipr API

  Admin->>Plugin: POST /adflipr/v1/login-token (email, password)
  Plugin->>WC: Create/read REST API key (Adflipr API Key)
  alt WC key step fails
    Plugin-->>Admin: WC_SETUP_ERROR (no API call)
  end
  Plugin->>API: POST /api/v1/wp/users/login (context=WORDPRESS, woocommerce_connect=…)
  API->>API: Validate password; upsert WooCommerce Session
  API-->>Plugin: 200 + wp_token
  Plugin->>Plugin: Store adflipr_auth_token; mark_integration_connected
  Plugin->>Plugin: Bulk sync + drain retry queue
  Plugin-->>Admin: Success
```

**Identity sent in `woocommerce_connect`:**

| Field | Source | Purpose |
|--------|--------|---------|
| `store_uuid` | WP option `adflipr_store_uuid` (created once per install) | Primary store identity across reconnects |
| `site_url` | `site_url()` | Secondary match; URL normalization on API |
| `consumer_key` / `consumer_secret` | Fresh WC REST key for super-admin | Backend + sync calls |

---

## 2. Backend rules (authoritative)

Enforced in `service/.../CommonUtil.java` → `validateWooCommerceConnect` before session upsert.

| Rule | Error message (API body `message`) |
|------|-------------------------------------|
| **One WooCommerce store per Adflipr account** | `This account already has a connected WooCommerce store. Only one store per account is allowed.` |
| **One Adflipr account per store** (operational session on another account) | `This store is already connected to another account. Only one account per store is allowed.` |
| **One store platform per account** (e.g. already on Shopify) | `This account already has a connected store. Disconnect it before connecting another platform.` |

**Same store reconnect** (same `store_uuid` or normalized `site_url`): allowed — session revived, metadata refreshed.

**Clone / URL change** (same `store_uuid`, different `site_url`): allowed; API sets `cloneDetected` on metadata (does not block).

**Tests:** `service/.../CommonUtilWooCommerceSessionTest.java`.

---

## 3. WordPress plugin error codes (what the merchant sees)

Classification: `adflipr_classify_auth_failure()` / `wordpress/src/utils/error-messages.js` → `classifyErrorDetail()`.

| Code | User message (English) |
|------|-------------------------|
| `INVALID_CREDENTIALS` | The email or password you entered is incorrect. |
| `WC_SETUP_ERROR` | We could not connect WooCommerce on this site. Ensure WooCommerce is active and try again. |
| `NETWORK_ERROR` | Could not reach Adflipr. Check your internet connection and try again. |
| `SERVER_ERROR` | Something went wrong on our end. Please try again in a few minutes. |
| `SESSION_EXPIRED` | Your session expired. Refresh this page and try again. |
| `RATE_LIMITED` | Too many attempts. Please wait a moment and try again. |
| `INVALID_REQUEST` | Please check your details and try again. |
| `UNKNOWN` | Something went wrong. Please try again. |

### When `WC_SETUP_ERROR` is chosen

Only for **local WordPress / WC key setup** failures (or legacy API text matching `api key`, `super admin`, `wc_keys`). Integration pairing failures use dedicated codes (below).

### Integration pairing codes (API `code` → plugin `error_code`)

| API / plugin code | User message (summary) |
|-------------------|-------------------------|
| `ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE` | Account already connected to **another** WooCommerce store |
| `STORE_ALREADY_ON_ANOTHER_ACCOUNT` | This store is on **another** Adflipr account |
| `ACCOUNT_ALREADY_HAS_STORE_INTEGRATION` | Account already has **Shopify** (or other platform) connected |

---

## 4. Scenario matrix

### A. WordPress / WooCommerce environment (before API login)

| # | Situation | WooCommerce active? | API called? | Plugin code | Message shown today | Correct message? |
|---|-----------|---------------------|-------------|-------------|-------------------|------------------|
| A1 | WooCommerce not installed/inactive | No | No | `WC_SETUP_ERROR` | Ensure WooCommerce is active… | **Yes** |
| A2 | No super-admin / admin for WC keys | No | No | `WC_SETUP_ERROR` | Ensure WooCommerce is active… | **Misleading** (admin/WC key setup) |
| A3 | WC REST key insert/validate fails | Yes | No | `WC_SETUP_ERROR` | Ensure WooCommerce is active… | **Partially misleading** (key/SSL/DB) |
| A4 | Cannot reach Adflipr API | Yes | Attempted | `NETWORK_ERROR` / `SERVER_ERROR` | Per classifier | **Yes** |
| A5 | Wrong email/password | Yes | Yes | `INVALID_CREDENTIALS` | Incorrect email/password | **Yes** |

### B. Adflipr account ↔ store pairing (API login succeeds only if rules pass)

| # | Situation | API HTTP | API `message` (typical) | Plugin code today | Message shown today | Should show |
|---|-----------|----------|-------------------------|-------------------|---------------------|-------------|
| B1 | **First connect** this site + this account | 200 | — (token returned) | — | Success | Success |
| B2 | **Reconnect same site** (same account, same `store_uuid` / URL) | 200 | — | — | Success | Success |
| B3 | **Account already linked to another WC site**; login from **new** site with **same** Adflipr credentials | 400* | `This account already has a connected WooCommerce store…` | **`ACCOUNT_ALREADY_HAS_WOOCOMMERCE_STORE`** | Specific disconnect guidance | Disconnect in Adflipr App Integrations or on the **original** site |
| B4 | **Store already linked to another Adflipr account**; login with different account | 400* | `This store is already connected to another account…` | **`STORE_ALREADY_ON_ANOTHER_ACCOUNT`** | Store tied to another account | Use that account or disconnect there |
| B5 | **Account already has Shopify**; connect WooCommerce | 400* | `This account already has a connected store. Disconnect…` | **`ACCOUNT_ALREADY_HAS_STORE_INTEGRATION`** | Disconnect other platform first | Disconnect Shopify in **Adflipr app → Integrations** first |
| B6 | Login with valid user but **no** `woocommerce_connect` payload | 200 | — | — | Success (WP token only; no session upsert) | N/A (incomplete connect) |

\* `AdGenericException` → typically **400 Bad Request** (`CommonControllerAdvice`).

† Classifier only maps to `WC_SETUP_ERROR` if the substring `woocommerce` appears in the API message. B3 **does** contain “WooCommerce” → **wrong WC_SETUP_ERROR message**. B4/B5 may not.

### C. After connect (runtime)

| # | Situation | Behavior |
|---|-----------|----------|
| C1 | Token valid; sync/webhooks | Normal operation |
| C2 | Disconnected in **Adflipr app** (Integrations) | Backend soft-disconnects session; may call WP signed disconnect; plugin token invalidated |
| C3 | Disconnected in **WP** (reset token / remote callback) | Local token cleared; sync paused; backend notified when possible |
| C4 | API returns `integration_deleted` on webhook | Plugin soft-disconnect |
| C5 | Same account, second physical store without disconnecting first | New login hits **B3** |

---

## 5. Your reported case (B3)

**Symptoms:** Adflipr account already connected to **Store A**; merchant installs plugin on **Store B** and signs in with the **same** Adflipr email/password. WooCommerce is active on Store B.

**What happens:**

1. Plugin creates WC API keys on Store B — **succeeds**.
2. API login runs `upsertWooCommerceSession` → `validateWooCommerceConnect` sees operational WC session for **Store A** on this account → **throws**.
3. Login fails; response body includes: *“This account already has a connected WooCommerce store…”*
4. Plugin sees `woocommerce` in that string → **`WC_SETUP_ERROR`**.
5. Merchant reads: *“Ensure WooCommerce is active”* — **incorrect** for this scenario.

**What the merchant should do:**

1. In **Adflipr web app → App Integrations**, disconnect the existing WooCommerce store, **or**
2. On **Store A** WordPress admin, disconnect Adflipr, **then**
3. Connect from **Store B** again.

**Implemented:** Backend returns stable `ErrorResponse.code`; plugin maps via `adflipr_auth_failure_from_api_response()` and `adflipr-user-messages.php` / `error-messages.js`.

---

## 6. Disconnect / switch store (intended path)

| Goal | Where to disconnect | Then |
|------|---------------------|------|
| Move account from Store A → Store B | Adflipr app **Integrations** or Store A WP plugin | Connect on Store B |
| Move Store A to another Adflipr account | Current account disconnect + other account login on Store A | — |
| Pause sync only | Either side soft-disconnect | Reconnect same site when ready |

Backend: `WooCommerceService.disconnectSession()` — session inactive, WP tokens invalidated, optional signed callback to WordPress.

---

## 7. Quick reference — files

| Layer | File |
|-------|------|
| User messages + classifier | `wordpress/includes/adflipr-user-messages.php`, `wordpress/src/utils/error-messages.js` |
| Login + WC keys | `wordpress/includes/class-adflipr-auth.php` |
| Session rules | `service/.../CommonUtil.java`, `IntegrationMessages.java` |
| WP login API | `service/.../WordPressController.java`, `UserService.handleWpIntegrationLogin` |
| App Integrations UI | `adflipr-app/src/pages/appIntegration/AppIntegration.tsx` |

---

## 8. Changelog

| Date | Note |
|------|------|
| 2026-05-17 | Initial scenario doc; integration error codes + plugin messages |
