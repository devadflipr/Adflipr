# Growave integration (v1) — locked decisions

**Status:** Decisions locked (2026-07-26) — v1 implemented  
**Audience:** Engineering  
**Related:** [`service/docs/integration-connection-config-kb.md`](../../service/docs/integration-connection-config-kb.md), [`ai/kb/decisions.md`](./decisions.md) ADR-011, Razorpay idempotency pattern [`razorpay-webhook-idempotency.md`](./razorpay-webhook-idempotency.md)

**Parity gaps vs Growave→Klaviyo/Omnisend (product inventory + side-by-side trigger matrix):** [`growave-klaviyo-omnisend-parity-gaps.md`](./growave-klaviyo-omnisend-parity-gaps.md)  
**Model migration (Klaviyo-style Growave-specific events/fields):** [`growave-klaviyo-model-migration.md`](./growave-klaviyo-model-migration.md) (ADR-014)

---

## 1. Product scope (v1)

| # | Decision | Choice |
|---|----------|--------|
| P1 | Outcome | **A+B** — contact enrichment/segmentation **and** event-driven automations |
| P2 | Surfaces | Loyalty + reviews + wishlists (not Q&A / Instagram gallery) |
| P3 | Direction | **Inbound only** (Growave → Adflipr). No award-points / redeem from Adflipr |
| P4 | Prerequisite | Active Shopify `Session` required (Growave is Shopify-scoped via `shop`) |

---

## 2. Architecture decisions

| # | Decision | Choice |
|---|----------|--------|
| D1 | Integration family | Hub kind **`PARTNER`** (Apps sibling; not an ESP import provider) |
| D2 | Credential store | **`integration_connection_config` row with `family=PARTNER`, `provider=GROWAVE`** + `partnerSettings`; secrets via `SecretFieldEncryptionService`. **Not** on `Session`, **no** `EspConfig`, **not** a new `IntegrationType` — see [`integration-connection-family.md`](./integration-connection-family.md) (ADR-013) |
| D3 | Uniqueness | One active connection row per `(accountId, GROWAVE)` |
| D4 | Auth | Merchant pastes Growave **API Key** + **API Secret** only (no shop input, no OAuth redirect/popup). Server derives `expectedShopDomain` from the account's active Shopify `Session`, verifies credentials against that shop (machine-to-machine bearer exchange + shop-scoped API check), then encrypts and stores credentials |
| D5 | Enrichment keys | **`growave_*` UDFs** on `Contact.dynamicField` (ADR-014; supersedes interim `loyalty_*`) |
| D6 | Event naming | **`GROWAVE_*` EventTypes** (Klaviyo-style integration-specific; legacy enums aliased during migration) |
| D7 | Idempotency | Shared `Webhook` collection (`from=Growave`); HTTP saves ledger; change stream processes; skip when `webhookId` already exists |

### PARTNER, not an ESP import provider

Growave uses the shared `integration_connection_config` collection (`family=PARTNER`) for connect/credential/status reuse, but it is **not** an email-list ESP: no `EspConfig`, no `EspImportAdapter`, no lists/segments/import jobs; it uses `partnerDefaults()` authority and is gated out of ESP-only paths. Family rationale: [`integration-connection-family.md`](./integration-connection-family.md) (ADR-013).

### Credential shape (integration_connection_config, family=PARTNER)

`family=PARTNER`, `provider=GROWAVE`, shared encrypted `credentials` + provider-neutral connection state:

- `credentials.apiKeyEncrypted`
- `credentials.clientSecretEncrypted` (Growave API Secret)
- `credentials.accessTokenEncrypted` (bearer from token exchange; refresh as needed)
- `partnerSettings.externalAccountKey` — Shopify shop domain from Adflipr's session (never client-supplied)
- `webhookSubscriptionIds` — shared connection webhook state for disconnect cleanup
- `credentials.accessTokenExpiresAt` (optional)
- provider behavior is registered through `GrowavePartnerIntegrationAdapter`

No Growave data lives on `Session`.

---

## 3. Webhook topics (v1)

Required subscriptions created during connect (`GrowaveConstants.V1_WEBHOOK_TOPICS`):

- `v1/customer/balanceUpdated`
- `v1/customer/tierUpdated`
- `v1/customer/rewardCreated`
- `v1/customer/birthdayUpdated`
- `v1/customer/referralActivated`
- `v1/customer/referralCompleted`
- `v1/review/created`
- `v1/review/deleted`
- `v1/wishlistItem/created`
- `v1/wishlistItem/updated`
- `v1/wishlistItem/deleted`

Ingress: `POST /api/v1/webhooks/growave`

**Webhook callback host:** Prod `server.url` is the apex host (`https://adflipr.com/`), not `www`. Cloudflare 301s www→apex and many webhook senders do not follow redirects for POST.

**Processing path (same as WooCommerce):** HTTP ingress verifies + dedupes + saves to `Webhook` (`from=Growave`); Mongo change stream → `WebhookHandlerService` → `GrowaveWebhookService.processSavedWebhook` (enrich + automations).

Idempotency: `webhookId` + `from=Growave`. Prefer `X-Growave-Webhook-Id`; fallback:

```text
growave:{topic}:{shop}:{immutableResourceId}
```

**Processing status** (on `Webhook.data`): `_processingStatus` = `RECEIVED` → `PROCESSING` → `PROCESSED` | `FAILED`; `_processingAttempts` capped at 3. Change-stream skips `PROCESSED`/`PROCESSING`; `FAILED` retries until max attempts. HTTP duplicate of `FAILED` also re-invokes processing.

HMAC: verify `X-Growave-Hmac-Sha256` (Base64 HMAC-SHA256 of raw body with API secret). Shop domain from `X-Shopify-Shop-Domain`. Balance payloads map `currentBalance` → `growave_points_balance`.

**Enrichment source:** event payload fields first (deserialized to {@code GrowaveModels.WebhookPayload} POJO, then mapped to {@code WebhookEnvelope}). **No routine Growave `getCustomer`.** Core profile (name/phone) is expected from **Shopify sync**. If the contact has **neither first nor last name**, fall back once to Growave `getCustomer` to backfill profile + loyalty fields, then still apply envelope event fields.

**Exception — `tierUpdated`:** live Growave payloads send `tierId` / `prevTierId` only (no `currentTier.title`). When `tierTitle` is missing on that topic, call `getCustomer` once and write `currentTier.title` → `growave_tier` (and automation `tierTitle` context).

**Exception — `rewardCreated`:** live redeem payloads use `activity.type` like `MANUAL_REDEEM_REWARD` / `action.source=SPENDING_RULE` and omit balance — call `getCustomer` once to refresh `growave_points_balance`. Always map to **`GROWAVE_REDEEM_REWARD`**. Negative `balanceUpdated` delta remains a secondary redeem signal. (`GROWAVE_REWARD_CREATED` / `GROWAVE_POINTS_UPDATED` enums remain for stored automations but are no longer emitted or selectable.)

**Exception — missing `growave_referral_link`:** referral link is not on webhook payloads (Klaviyo/Omnisend sync it as a profile property). When the contact UDF is blank, call `getCustomer` once and write `referralLink` → `growave_referral_link` (also picks up other profile loyalty fields). Outbound Growave API clients refresh the bearer token when near expiry, and retry once on `Incorrect access token`.

**New contacts from Growave** start as `SUBSCRIBED` (product choice so loyalty automations can email).

---

## 4. Contact enrichment fields

| Label | Type | Source |
|-------|------|--------|
| `growave_points_balance` | NUMBER | webhook `currentBalance` / `pointsBalance` (envelope); fallback API if fetched |
| `growave_tier` | TEXT | `tierUpdated` has no title in payload — resolve via getCustomer `currentTier.title`; also from API when profile fallback runs |
| `growave_points_expires_at` | DATE | fallback API when profile fetch runs |
| `growave_referral_link` | TEXT | `getCustomer` when UDF blank (webhooks omit link) |
| `growave_review_count` | NUMBER | increments on review events |
| `growave_wishlist_count` | NUMBER | increments/decrements on wishlist events |

Also update system `birthDate` on `birthdayUpdated`. Merge tags appear via existing prefs merge-tag assembly once UDFs exist.

---

## 5. Workflow EventTypes (v1)

| EventType | Topic(s) | Contact update |
|-----------|----------|----------------|
| `GROWAVE_EARNED_POINTS` | `balanceUpdated` when delta &gt; 0 | `growave_points_balance` |
| `GROWAVE_REDEEM_REWARD` | `rewardCreated` **or** `balanceUpdated` when delta &lt; 0 | `growave_points_balance` via getCustomer when omitted |
| `GROWAVE_REACHED_TIER` | `tierUpdated` | `growave_tier` |
| `GROWAVE_REVIEW_SUBMITTED` | `review/created` | `growave_review_count++` |
| `GROWAVE_REVIEW_DELETED` | `review/deleted` | `growave_review_count--` |
| `GROWAVE_REFERRAL_ACTIVATED` | `referralActivated` | Resolve **referred** (friend) — Klaviyo `FriendReceivedReferral` / Omnisend `friend-received-referral` |
| `GROWAVE_REFERRAL_COMPLETED` | `referralCompleted` | Resolve **referrer** — Klaviyo/Omnisend `ReferralCompleted` |
| `GROWAVE_WISHLIST_ITEM_ADDED` | `wishlistItem/created` **and** `wishlistItem/updated` | `growave_wishlist_count++` |
| `GROWAVE_WISHLIST_ITEM_REMOVED` | `wishlistItem/deleted` | `growave_wishlist_count--` |
| `GROWAVE_BIRTHDAY_UPDATED` | `birthdayUpdated` | system `birthDate` |

`balanceUpdated` with null/zero delta → enrich only (no automation event). Legacy enums `GROWAVE_POINTS_UPDATED` / `GROWAVE_REWARD_CREATED` are not selectable and are not emitted.

Order is fixed: **enrich contact first** (`applyEnrichment` → **`update`** on existing contacts; see [`contact-repository-save-vs-update.md`](./contact-repository-save-vs-update.md)), then `AutomationService.processContactEvent(...)` so workflow nodes see fresh UDFs.

### Automation binding (how customer data connects)

Automations are **not** hard-wired to Growave. Binding is:

1. **Trigger:** published automation whose root `EventType` matches the mapped `GROWAVE_*` event. Optional trigger criteria (Any allowed):
   - Earned Points / Redeem Reward → `balanceDelta` `gte` threshold (redeemed uses absolute delta)
   - Reached Tier → `tierTitle` `equals` (free-text, case-insensitive)
   - Review / Wishlist / Birthday / Referral → Any only (no extra filters in v1)
2. **Contact data:** enriched `dynamicField.growave_*` on the contact — used by audience filters, segment recipes, and workflow condition/filter nodes.
3. **Merchant setup:** create/publish a Growave automation (or use recipes `growave-vip-members` / `growave-wishlist-shoppers`). Without a matching published automation, enrichment still runs; no journey starts.

Implementation: `LoyaltyEventCriteriaRules` + workflow UI `loyaltyAutomation.tsx` / `LoyaltyEventCriteriaDialog` / `loyaltyEventCriteria.ts`.

Dispatch: HTTP → save `Webhook` → change stream → resolve/create contact → enrich → `AutomationEventService.processContactEvent(...)` → `AutomationService`.

---

## 6. Non-goals (v1)

- Outbound award/redeem/send-event
- Q&A webhook topics; Instagram gallery
- Treating Growave as ESP import source
- Full historical review/wishlist import on connect (bounded profile backfill only)
- Headless storefront SDK

---

## 7. API surface

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/v1/growave/connect` | Merchant (`APP_INTEGRATIONS`); body `{ apiKey, apiSecret }` only — shop derived server-side |
| GET | `/api/v1/growave/status` | Merchant |
| DELETE | `/api/v1/growave/disconnect` | Merchant |
| POST | `/api/v1/webhooks/growave` | Public + signature verify |

External API base: `https://api.growave.io/v2`

---

## 8. Ops notes

- After deploying contacts ES mapping changes (`dynamicField.growave_*` properties), run the Elasticsearch mapping update / reindex workflow for the `contacts` alias before relying on Growave audience filters in production. Repo mapping includes `growave_*` under `elasticsearch/mappings/contacts.json`.
- Seed Growave recipes via `service/scripts/mongo/seed-prebuilt-recipes.js` (`growave-vip-members`, `growave-wishlist-shoppers`).
- **Audience filter gate:** `attributes.growave.*` in `application.properties` is catalog-only. `DynamicFilterService.getAllSections()` includes the Growave section **only** when the current account has an operational Growave `integration_connection_config` row (`family=PARTNER`, `provider=GROWAVE`).
- **Automation Growave tab:** Workflow `EventSelector` always shows the Growave tab and its event cards. When any catalog partner with `PARTNER_CAPABILITY.LOYALTY` is connected (`useLoyaltyPartnerConnected` — Growave today), events are selectable. When not connected, events stay visible but disabled, and a connect banner CTA navigates to App Integrations (`/app-integration`). Display labels omit the redundant “Growave” prefix (canonical persisted English labels still include it per ADR-014). On the canvas, Growave trigger nodes show brand in the top chrome as `Start when · Growave`.
- Migration tracking: [`growave-klaviyo-model-migration.md`](./growave-klaviyo-model-migration.md).

