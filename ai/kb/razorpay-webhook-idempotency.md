# Razorpay webhook idempotency — locked decisions

**Status:** Decisions locked (2026-07-25) — **implementation not started**  
**Audience:** Engineering review (incl. external LLM review)  
**Related code (as-is):** `AbstractWebhookProcessingService`, `RazorpayWebhookUtils.buildDedupeKey`, `OrderWebhookEvent` (`dedupeKey` unique), `RazorpaySubscriptionProcessingService.shouldWebhookOverwrite`  
**Related KB:** [`plan-pricing-resolution.md`](./plan-pricing-resolution.md), [`entitlements-and-subscription.md`](./entitlements-and-subscription.md)  
**Platform parallel:** Commerce TI-5 / Woo `webhookId` idempotent ingest (`service/docs/TRANSACTION_TARGET_ARCHITECTURE.md`)

---

## 1. Executive decision

| # | Decision | Choice |
|---|----------|--------|
| D1 | Must Razorpay webhook processing be idempotent? | **Yes** — required |
| D2 | Idempotency model | **Event-occurrence / delivery idempotency** (Option A), not “subscription forever” |
| D3 | Primary dedupe key | **Composite** `razorpay:{event}:{immutableResourceId}` (see §3) — not Razorpay event-id-first until ingress captures a stable event id |
| D4 | When to skip vs retry | Skip only **`PROCESSED`** (and fresh in-flight **`PROCESSING`**); allow retry on **`FAILED`** and **stale `PROCESSING`** |
| D5 | Handler safety | Ledger is primary; handlers remain **upsert-safe** (defense in depth) |
| D6 | Out-of-order / overwrite | **Separate** from idempotency; fix with Razorpay timestamps / last-processed state — do **not** treat “new Mongo webhook doc id” as newer business event |
| D7 | Key migration / versioning | **No `v2:` (or similar) prefix.** Keep existing `orderWebhookEvents` collection and `razorpay:{event}:{id}` shape. **Change key rules only where broken** (see §3.1). |

**Rationale for D1:** Razorpay **retries** webhook deliveries. Duplicates are normal. Billing side effects (subscription activation, invoice, quota provision, emails) must not double-apply on replay.

**Rationale for D2 vs resource-lifecycle keys:** Today’s key shape `razorpay:{event}:{subscriptionId}` over-deduplicates **legitimate recurring** events (e.g. next month’s `subscription.charged`). Idempotency must mean “same delivery/occurrence,” not “same subscription id for this event type forever.”

---

## 2. Options considered

### Option A — Event-occurrence idempotency (**chosen**)

Dedup one **occurrence** of a provider event (invoice paid once, payment captured once, one charge cycle, one status transition delivery).

| Pros | Cons |
|------|------|
| Safe under Razorpay retry | Must pick stable occurrence ids carefully |
| Allows monthly `subscription.charged` | Requires retry policy on FAILED |
| Aligns with commerce webhook ledger pattern | Key rules are event-type-specific |

### Option B — Resource-lifecycle idempotency (current partial behavior)

Dedup `eventType + subscriptionId` for the life of the subscription.

| Pros | Cons |
|------|------|
| Simple | **Blocks recurring charges** after first |
| | Blocks retry after FAILED |
| | Wrong semantic for “same event” |

### Option C — No ledger; natural handler idempotency only

Rely only on upserts by gateway ids.

| Pros | Cons |
|------|------|
| Fewer collections | Side effects (welcome email, events, quota) hard to make safe |
| | Weaker observability / ops |

**Rejected:** B and C as primary model. C remains a **secondary** requirement (handlers upsert-safe).

---

## 3. Dedupe key contract (D3)

### Format

```text
razorpay:{event}:{immutableResourceId}
```

Same format as today — **do not** introduce a versioned prefix (`v2:…`). New occurrence ids naturally produce new key strings, so old ledger rows do not block new charges.

### 3.1 Migration approach (D7) — change only where broken

| Event family | Action |
|--------------|--------|
| Invoice / payment | **Keep** resource-id keys if already correct; align implementation with §3 table |
| `subscription.charged` (and money movement) | **Fix:** stop using bare `subscriptionId`; prefer `payment_id` / `invoice_id`, else `subscriptionId` + `created_at` |
| Pure status (`authenticated`, `activated`, `cancelled`, …) | **Prefer minimal change:** keep `subscriptionId` alone **only** where product confirms single-fire; otherwise add `created_at` when needed to avoid over-dedupe |
| Wall-clock `System.currentTimeMillis()` fallback | **Remove** (anti-pattern) |

**Leave existing `PROCESSED` rows alone.** They only affect exact same `dedupeKey` redelivery. An old `razorpay:subscription.charged:sub_XXX` does **not** block `razorpay:subscription.charged:pay_YYY`.

**Do not** dual-write, rekey historical rows, or add a key-version namespace unless a concrete collision is found in production.

### Resource id precedence (by event family)

| Event family | Prefer for `{immutableResourceId}` | Fallback |
|--------------|--------------------------------------|----------|
| Invoice (`invoice.*`) | `invoice_id` | — |
| Payment (`payment.*`) | `payment_id` | — |
| Subscription charge / money movement (`subscription.charged`, etc.) | `payment_id` or `invoice_id` when present in payload | `subscriptionId` + `created_at` |
| Pure subscription status (`subscription.authenticated`, `activated`, `cancelled`, `halted`, `completed`, `expired`, `pending`, `created`, …) | `subscriptionId` + `created_at` | If `created_at` missing: do **not** use bare `subscriptionId` alone for recurring-capable events; log error and fail closed or use a one-shot allowlist (see below) |

### Explicit anti-patterns (forbidden as sole key)

- `razorpay:subscription.charged:{subscriptionId}` alone — **over-dedupes** renewals  
- Mongo `OrderWebhookEvent.id` as business identity — internal only  
- Wall-clock `System.currentTimeMillis()` as production key — non-deterministic across retries  

### Razorpay event id (deferred primary)

If / when ingress reliably captures a **stable Razorpay event id** (body or header) on every delivery, prefer:

```text
razorpay:event:{razorpayEventId}
```

as primary, with the composite key above as secondary/migration. **Not primary today** because `RazorpayWebhookRequest` models `event`, `created_at`, and nested entities — no wired stable event-id field in current ingress.

### One-shot subscription events allowlist (optional hardening)

Events that can legitimately fire only once per subscription *lifecycle state* may use `subscriptionId` without `created_at` **only if** product confirms single-fire semantics. Until confirmed, prefer `subscriptionId + created_at` for all subscription status events to stay consistent.

---

## 4. Skip / retry policy (D4)

Ledger collection: `orderWebhookEvents` (`OrderWebhookEvent`), unique index on `dedupeKey`.

| Existing status | On redelivery | Action |
|-----------------|---------------|--------|
| **`processed`** | Same key | **Skip** (idempotent success) |
| **`processing`**, age **&lt; stale TTL** (recommend **15–30 minutes**, config) | Same key | **Skip** (in-flight) |
| **`processing`**, age **≥ stale TTL** | Same key | **Retry** (treat as crashed worker); bump `attempts` |
| **`failed`** | Same key | **Retry** (Razorpay retry is the recovery path) |
| **`dead_letter`** | Same key | **Skip** (ops must reset manually or re-drive) |
| **`received`** (if used) | Same key | Treat like in-flight / stale by age |

### Concurrent insert race

Two workers may both miss `findByDedupeKey` and race insert. Unique index on `dedupeKey` must remain. On duplicate-key exception: **re-read** existing row and apply the table above (do not treat as hard 500 to Razorpay if the winner is already `processed`).

### As-is bug to fix

Today `AbstractWebhookProcessingService` skips **any** existing row regardless of status — so **FAILED never retries**. That violates D4.

---

## 5. Handler defense in depth (D5)

Even with a correct ledger:

| Concern | Rule |
|---------|------|
| Invoice | Upsert / find by `externalInvoiceId` (Razorpay invoice id) |
| Payment | Upsert by gateway payment id |
| Subscription | Upsert by `gatewaySubscriptionId` |
| Email quota provision | Idempotent ensure (no double-allocate for same period without intent) |
| Customer emails / notifications | Prefer send-once guards keyed by event occurrence |

Ledger prevents duplicate *entry*; handlers prevent duplicate *effects* if ledger policy is wrong or partially rolled out.

---

## 6. Ordering vs idempotency (D6)

These are **different** problems:

| Concern | Goal |
|---------|------|
| Idempotency | Same occurrence twice → one effect |
| Ordering | Older occurrence must not clobber newer state |

### Locked rule for overwrite

- Do **not** use “Mongo webhook document id ≠ `subscription.lastProcessedEventId`” as proof the business event is newer (every ledger insert gets a new Mongo id).
- Prefer: compare Razorpay **`created_at`** (or equivalent) to `subscription.lastProcessedAt` / last processed provider timestamp.
- Provisional callback state may still be overwritten by a later webhook (**provisional → final**), as today, but only when the webhook occurrence is not older than last final process time.

### As-is bug to fix

`shouldWebhookOverwrite` currently returns true whenever `webhookEventId` (Mongo id) differs from `lastProcessedEventId`, which defeats timestamp ordering.

---

## 7. As-is vs target (gap summary)

| Area | As-is (code) | Target (this KB) |
|------|----------------|------------------|
| Key for subscription events | Often `event + subscriptionId` | Occurrence key (§3) |
| FAILED redelivery | Skipped forever | Retry (§4) |
| Stale PROCESSING | Skipped forever | Retry after TTL (§4) |
| Duplicate-key race | May surface as hard error | Re-read + status policy |
| Overwrite guard | Mongo event id novelty | Provider timestamp / provisional rules (§6) |
| invoice.paid / payment.* | Generally OK if resource id used | Keep; align with §3 |

---

## 8. Explicitly deferred

| Topic | Notes |
|-------|--------|
| Support email alerts (`support@adflipr.com`) on billing failures | Separate initiative |
| Making Razorpay event-id primary | When ingress captures it reliably |
| Shopify subscription webhook ledger parity | Follow same **decisions**; keys differ by provider |
| Automatic dead-letter reaper / admin replay UI | Ops process later |
| Changing plan-pricing resolution | Separate; already decided in `plan-pricing-resolution.md` |
| `v2:` / versioned dedupe key prefix | **Rejected (D7)** — unnecessary; occurrence ids change the key string |

---

## 9. Implementation checklist (when coding)

- [x] Rewrite `RazorpayWebhookUtils.buildDedupeKey` per §3 / §3.1 (**no** key-version prefix) — **first-level done** (charged uses payment/invoice; status keeps subscriptionId; no wall-clock)  
- [ ] Change `AbstractWebhookProcessingService` (or Razorpay subclass override) to apply §4 skip/retry  
- [ ] Handle unique-index race as idempotent re-read  
- [ ] Fix `shouldWebhookOverwrite` per §6  
- [x] Unit tests for dedupe key (charged months differ; no wall-clock) — `RazorpayWebhookUtilsDedupeKeyTest`  
- [ ] Unit tests: replay PROCESSED → skip; FAILED → retry; stale PROCESSING → retry  
- [ ] Document config: `webhook.processing.stale-ttl-minutes` (name TBD)  
- [ ] Update this doc status to **Implemented** when merged  

---

## 10. Review notes for external reviewers (ChatGPT / humans)

Please challenge especially:

1. Is `subscriptionId + created_at` sufficient uniqueness for pure status events if Razorpay retries the **exact same** payload (same `created_at`)? (Expected: **yes** — same occurrence → same key → skip after PROCESSED / retry if FAILED.)  
2. For `subscription.charged`, is payment/invoice id always present in our payload path? If not, is `subscriptionId + created_at` acceptable?  
3. Stale TTL 15 vs 30 minutes — any ops preference?  
4. Should `dead_letter` ever auto-retry, or always manual? (Locked: **manual**.)  
5. Any event types that must be **exactly-once side effects** beyond upsert (e.g. welcome email) needing an extra send-once table?

---

## 11. Changelog

| Date | Change |
|------|--------|
| 2026-07-25 | Locked D1–D6; Option A + composite key; FAILED/stale retry; ordering separated from idempotency. |
| 2026-07-26 | PE audit: `service/docs/RAZORPAY_WEBHOOK_IDEMPOTENCY_AUDIT.md` — conditional approve; Must Fix before implement. |
| 2026-07-26 | **D7:** no `v2:` key prefix; migrate by changing key rules only where broken (§3.1). |
| 2026-07-26 | **First-level impl:** `RazorpayWebhookUtils.buildDedupeKey` — charged prefers payment/invoice; status keeps subscriptionId; wall-clock removed. |
