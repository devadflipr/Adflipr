# Gateway Selection

**Canonical.** Synced from code (not aspirational). Cursor pointer: `.cursor/rules/gateway-selection-kb.mdc`.

## Core Rule

Shopify is a convenience. Razorpay is the guarantee.

## Hybrid model (as implemented)

1. **Payment history wins** — once a successful paid gateway payment exists, that gateway is preferred (Shopify still requires connection; otherwise Razorpay fallback).
2. **First purchase follows signup source (`UserSource`)** — only when there is **no** prior successful Razorpay or Shopify payment:
   - `ADFLIPR` / `WOOCOMMERCE` / unknown → **Razorpay** (even if Shopify is connected)
   - `SHOPIFY` → **Shopify** if connected; otherwise **Razorpay** fallback

## Selection Flow (`GatewaySelectionService#selectGateway`)

Read-only. Does **not** block and does **not** throw for Shopify disconnect.

1. **Check Razorpay payment history**
   - If account has any successful Razorpay payment → **Gateway = RAZORPAY**

2. **Check Shopify payment history**
   - If account has any successful Shopify payment:
     - Shopify connected → **Gateway = SHOPIFY**
     - Shopify not connected → **Gateway = RAZORPAY** (fallback + WARN log)
     - Error resolving Shopify → **Gateway = RAZORPAY** (fallback + WARN log)

3. **First-time billing (no prior successful payments) — signup source**
   - Resolve owner `UserSource` for the account (`ADFLIPR` default if missing)
   - If source is **not** `SHOPIFY` → **Gateway = RAZORPAY**
   - If source is `SHOPIFY`:
     - Shopify connected → **Gateway = SHOPIFY**
     - Shopify not connected / error → **Gateway = RAZORPAY**

4. Blank / missing `accountId` → **Gateway = RAZORPAY** (WARN log)

## Gateway Resolution Table (`selectGateway`)

| Condition | Result |
| --- | --- |
| Razorpay payment exists | Razorpay |
| Shopify payment exists + Shopify connected | Shopify |
| Shopify payment exists + Shopify NOT connected | Razorpay (fallback) |
| Shopify payment exists + Shopify resolve error | Razorpay (fallback) |
| No payments + signup `ADFLIPR` / `WOOCOMMERCE` / unknown | Razorpay |
| No payments + signup `SHOPIFY` + Shopify connected | Shopify |
| No payments + signup `SHOPIFY` + Shopify NOT connected / error | Razorpay |
| Blank accountId | Razorpay |

## Successful payment definition

A subscription counts as successful gateway history when:

- `paymentGateway` matches the gateway
- `planType` is not `FREE`
- `status` is `ACTIVE` or `COMPLETED`

`PENDING` / abandoned checkouts do **not** count as history.

## Mutation guards (not part of `selectGateway`)

Blocking happens on **existing Shopify subscription mutations**, not during gateway selection:

| Path | Behavior when Shopify disconnected |
| --- | --- |
| `SubscriptionChangeService` upgrade/downgrade of **active** Shopify sub | **BLOCK** — WARN log + user message to reconnect Shopify |
| Soft cancel of Shopify subscription (`SubscriptionService`) | **BLOCK** — cannot cancel without Shopify connected |
| Scheduled execute-cancel of Shopify subscription | **BLOCK** — cannot execute cancel without Shopify connected |

User message on upgrade/downgrade block (`ShopifyConnectionMessages`):

> Your current subscription was purchased via Shopify. Please reconnect Shopify to modify your plan.

Shopify connection check errors in the upgrade path are treated as disconnected (WARN log), then the same block applies.

## Logging (as coded)

Fallback / disconnect in selection:

```
WARN: Shopify not connected for account {accountId}. Falling back to RAZORPAY. reason={reason}
WARN: Gateway selection: Shopify resolution failed for account {accountId}, falling back to RAZORPAY. Reason: {message}
```

First-purchase decision:

```
INFO: Gateway selection: account {accountId} first payment, signupSource={source}, selecting RAZORPAY
```

Upgrade block:

```
WARN: Blocking subscription update. Active Shopify subscription exists but Shopify is not connected for account {accountId}.
```

## UI / errors

- **`selectGateway`:** do not surface Shopify connectivity errors; fall back to Razorpay (logged).
- **Mutation paths:** do surface reconnect / not-connected messages when an active Shopify subscription cannot be modified or cancelled.

## Applies To

- `getPlan` / plan pricing (`PlanDetailsService`) — uses `selectGateway`
- `createSubscription` — uses `selectGateway` before checkout
- Upgrade / downgrade / cancel — use **current subscription’s** `paymentGateway` + mutation guards above

## Implementation

- Selection: `com.goveca.recruit.ad.service.GatewaySelectionService#selectGateway(String accountId)`
- Connect check: `ShopifyService#isShopifyConnected()` (session lookup; exceptions → `false` + WARN)
- Upgrade block: `SubscriptionChangeService` (active Shopify + disconnected)
- Cancel checks: `SubscriptionService` (soft cancel + execute cancel)
