# Super-admin Razorpay pricing — implementation plan

**Status:** v1 implemented (read + validate only)  
**Last updated:** 2026-06-20  
**Related:** `planVersionPricing` (Mongo), `RazorpayPlanSyncService`, `AdminController` (`POST /api/v1/admin/plans/sync-razorpay`)

---

## 1. Problem

Subscription checkout uses **`razorpayPlanId`** from the flat catalog (`planVersionPricing`). When that id is **missing**, **404 at Razorpay**, or **out of sync** (amount, currency, notes), users hit failures at upgrade/checkout (`PlanUpgrade`, `SubscriptionService`, webhooks).

Today there is **no super-admin UI** to inspect or repair catalog ↔ Razorpay alignment. The only sync path is:

- `POST /api/v1/admin/plans/sync-razorpay` — requires JWT **and** `X-Adflipr-Admin-Key` (not exposed in super-admin app).

**Goal:** A dedicated **Platform → Razorpay pricing** screen for `SUPER_ADMIN` users to list tiers, edit catalog fields, **validate** against Razorpay, and **fix** broken rows (create new plan + persist id).

---

## 2. Product decisions (agreed)

| Topic | Decision |
|--------|----------|
| **Fix action** | Per-row **Fix** creates a **new** Razorpay plan via API and saves the new `razorpayPlanId` on the Mongo row (same semantics as sync, scoped to one tier). |
| **Editable fields** | `priceCents`, `emailQuota`, `numOfContacts`, and **`razorpayPlanId`** (manual link/paste when needed). |
| **Validate checks** | Plan **exists**, **active**, **amount** = `priceCents`, **currency** matches, **notes metadata** matches (`productType`, `billingInterval`, `currency`, `numOfContacts`, `planVersionId`). |
| **List scope** | **All tiers**, including trial / zero-price rows. |

### Terminology

| Field | Meaning |
|--------|---------|
| `pricingId` | Stable internal tier key in catalog seeds (Long). **Not** a Razorpay id. |
| `razorpayPlanId` | Razorpay plan id (e.g. `plan_RnGvbyUCukp3DR`) — this is what checkout sends. |
| `documentId` | Mongo `_id` on `planVersionPricing` row (edit/validate/fix target). |

---

## 3. Current system (baseline)

### 3.1 Data model

- **Collection:** `planVersionPricing` — one document per (planVersionId × currency × contact tier).
- **Model:** `PlanVersionPricing` — `priceCents`, `emailQuota`, `numOfContacts`, `razorpayPlanId`, `planIdCreationDate`, `productType`, `billingInterval`, `currency`, `isTrial`, etc.
- **Reads:** `PlanVersionRepositoryWrapper` aggregates rows into in-memory `PlanVersion` for customer-facing pricing APIs.

### 3.2 Razorpay integration

- **Create plan:** `RazorpayApiService.createPlan` → `POST /plans`
- **Fetch plan:** `RazorpayApiService.getPlan` → `GET /plans/{id}` (throws on error today)
- **Bulk sync:** `RazorpayPlanSyncService.syncPlansToRazorpay(forceUpdate)` — skips `priceCents == 0`, creates plans with notes `source=adflipr_sync`

### 3.3 Existing admin sync (keep, do not remove)

- Route: `POST /api/v1/admin/plans/sync-razorpay?forceUpdate=false`
- Auth: JWT + **admin API key header**
- Super-admin screen should **not** depend on the admin key; use `Permission.PLATFORM_ADMIN_DASHBOARD` like other super-admin routes.

---

## 4. Proposed architecture

```
SuperAdmin UI (new nav)
    ↓
SuperAdminController  (/api/v1/super-admin/razorpay-pricing/*)
    ↓
SuperAdminRazorpayPricingService
    ├── PlanVersionRepositoryWrapper  (list/update row — super-admin, no segment filter)
    ├── RazorpayApiService            (GET plan, safe 404 handling)
    └── RazorpayPlanSyncService       (shared single-row create + persist)
```

**Reuse:** Extract or add `createRazorpayPlanForPricingRow(PlanVersionPricing row)` on `RazorpayPlanSyncService` so bulk sync and per-row Fix share one code path (plan name, notes, period mapping).

**Do not** call `AdminController` from the UI (different auth model).

---

## 5. API design (super-admin)

All routes: `@RequiresPermission(Permission.PLATFORM_ADMIN_DASHBOARD)`, prefix `/api/v1/super-admin/razorpay-pricing`.

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/` | Paginated list of `planVersionPricing` rows |
| `PUT` | `/{documentId}` | Update editable catalog fields |
| `POST` | `/{documentId}/validate` | Validate one row vs Razorpay |
| `POST` | `/validate` | Batch validate (body: `documentIds[]`) — for “Validate page” |
| `POST` | `/{documentId}/fix` | Create new Razorpay plan + save `razorpayPlanId` |

### 5.1 List query params

- `pageNum`, `pageSize` (default 20, max 100)
- `currency` (optional, e.g. `USD`, `INR`)
- `productType` (optional, e.g. `standard`, `premium`)
- `validationStatus` (optional, **phase 2** — requires server-side validate or cached status; defer unless needed)

**Phase 1:** Client holds validation results in component state after Validate clicks (no list filter by status).

### 5.2 List response row (`RazorpayPricingRowView`)

- Identity: `documentId`, `planVersionId`, `tierCompositeId`, `pricingId`
- Catalog: `productType`, `billingInterval`, `currency`, `numOfContacts`, `priceCents`, `emailQuota`, `trial`
- Razorpay: `razorpayPlanId`, `planIdCreationDate`, `updated`

### 5.3 Update body (`UpdatePlanPricingRequest`)

- `priceCents`, `emailQuota`, `numOfContacts`, `razorpayPlanId` (all optional; patch semantics)
- On `priceCents` change: UI should prompt re-validate; Fix creates **new** plan (old Razorpay plan remains at gateway until deprecated manually).

### 5.4 Validation result (`RazorpayPricingValidationResult`)

**Status enum:**

| Status | When |
|--------|------|
| `NOT_APPLICABLE` | Zero/missing `priceCents` and no `razorpayPlanId` |
| `MISSING_PLAN_ID` | Paid tier (or row with price) but empty `razorpayPlanId` |
| `NOT_FOUND` | GET `/plans/{id}` → 404 |
| `INACTIVE` | Plan exists but `item.active == false` |
| `MISMATCH` | Exists but amount / currency / notes differ |
| `VALID` | All checks pass |
| `ERROR` | Razorpay or unexpected failure |

**Payload:** `issues[]` (human-readable strings), optional `razorpayAmountCents`, `razorpayCurrency`, `razorpayActive`.

**Zero-price with stray `razorpayPlanId`:** Still validate (treat as paid path) so ops can detect orphaned ids on trial rows.

### 5.5 Fix result (`RazorpayPricingFixResult`)

- `success`, `message`
- Updated `row`
- `createdPlan` (reuse `RazorpayPlanSyncResponse.PlanSyncDetail` shape)

**Fix preconditions:**

- `priceCents > 0`
- `currency` present
- Razorpay env configured (`RazorpayProperties`)

**Fix postconditions:**

- New `razorpayPlanId`, `planIdCreationDate`, `updated` on Mongo row
- Notes `source=adflipr_super_admin_fix` (distinct from bulk sync for audit)

---

## 6. UI design (super-admin)

**Nav:** Platform → **Razorpay pricing** (new item; English-only OK per SUPER_ADMIN exception).

**Route:** `/app/super-admin/razorpay-pricing`

### 6.1 Layout

1. **Header** — short explanation + link to this plan doc / internal runbook.
2. **Filters** — currency, productType, search (optional: planVersionId substring).
3. **Table** — one row per catalog tier.

| Column | Notes |
|--------|--------|
| Plan / tier | `planVersionId`, contacts, billing interval |
| Product | `productType`, trial badge |
| Price | `priceCents` + currency |
| Email quota | `emailQuota` |
| Razorpay plan id | monospace; empty = warning |
| Validation | chip from last validate (Valid / Missing / Not found / Mismatch / N/A) |
| Actions | Edit · Validate · Fix |

4. **Row edit dialog** — edit four fields; Save → `PUT`.
5. **Validate page** — button validates all rows on current page (batch POST).
6. **Fix** — confirm dialog (“Creates new Razorpay plan and updates catalog”); disabled when `priceCents == 0` or Fix in flight.

### 6.2 UX rules

- After **Save**, clear prior validation chip → “Not validated”.
- **Fix** enabled when status is `MISSING_PLAN_ID`, `NOT_FOUND`, or `MISMATCH` (and price &gt; 0).
- Show validation **issues** in tooltip or expandable row detail.
- Open questions: show destructive styling when `NOT_FOUND` (checkout will fail).

---

## 7. Repository changes

Add to `PlanVersionRepositoryWrapper` (super-admin only; **no** `PlanSegmentPolicy` filter):

- `listAllPricingRowsForSuperAdmin(currency, productType, pageNum, pageSize)` → `Page<PlanVersionPricing>`
- `findPricingRowById(documentId)`
- `savePricingRow(row)` — set `updated` timestamp

Sort: `planVersionId`, `currency`, `numOfContacts` ASC.

---

## 8. Razorpay API helper

Add `fetchPlanOrNull(planId)` on `RazorpayApiService`:

- 404 → `null`
- Other errors → propagate (validation status `ERROR`)

Keep existing `getPlan` for callers that require throw-on-miss.

---

## 9. Implementation phases

### Phase 1 — Read + validate (MVP) ✅

- [x] Backend list + validate (single + batch)
- [x] UI table + Validate + validation chips
- [x] No edit/fix — confirms Razorpay connectivity and surfaces broken rows

### Phase 2 — Edit catalog

- [ ] `PUT` update endpoint + edit dialog
- [ ] Re-validate prompt after save

### Phase 3 — Fix

- [ ] `createRazorpayPlanForPricingRow` in sync service
- [ ] Fix endpoint + UI with confirm
- [ ] Logging: `super_admin.razorpay_pricing.fix documentId=… oldPlanId=… newPlanId=… userId=…`

### Phase 4 — Optional enhancements

- [ ] Filter list by last validation status (needs cache or scan)
- [ ] “Validate all” across full catalog (background job / progress)
- [ ] Deprecate or document when to use legacy `POST /admin/plans/sync-razorpay`
- [ ] Compare against **active subscriptions** still pointing at old plan ids (separate audit)

---

## 10. Testing plan

### Backend unit tests

- Validation logic: amount/currency/notes mismatch detection (mock `fetchPlanOrNull`)
- `NOT_APPLICABLE` for zero price without plan id
- Fix: mock `createPlan`, assert Mongo update fields

### Manual QA (staging Razorpay)

1. List loads all tiers including trial.
2. Row with valid id → Validate → `VALID`.
3. Row with bogus id → `NOT_FOUND` → Fix → new id → Validate → `VALID`.
4. Edit `priceCents` → Validate → `MISMATCH` → Fix → `VALID`.
5. Zero-price trial → Validate → `NOT_APPLICABLE`; Fix button disabled.
6. Manual paste `razorpayPlanId` → Validate confirms link.

---

## 11. Security & audit

- Permission: `PLATFORM_ADMIN_DASHBOARD` only (same as other super-admin screens).
- Log all **PUT** and **Fix** with super-admin user id from `TokenService`.
- No exposure of Razorpay secret in API responses.
- Fix creates **new** plans (does not delete old Razorpay plans) — document ops cleanup if needed.

---

## 12. Open questions (resolve before Phase 3)

1. **Price change + existing subscribers:** Fix creates a new plan id; active subscriptions keep old Razorpay plan until they change plan — is that acceptable, or do we need a subscription migration story?
2. **INR vs USD environments:** One Razorpay account per deployment — confirm staging/prod keys match catalog currencies in each env.
3. **Bulk sync in UI:** Do we also want a “Sync all missing” button (wraps existing sync service), or is per-row Fix enough?
4. **Manual `razorpayPlanId` edit:** Should Save require Validate pass before persisting, or allow save-then-validate?

---

## 13. Out of scope (this plan)

- Changing customer-facing plan picker / `PlanPriceService` behavior
- Shopify billing catalog
- Editing `planVersionId`, `effectiveFrom`, `version`, or segment visibility
- Deleting Razorpay plans at the gateway
- Replacing seed JSON / migration scripts for initial catalog load

---

## 14. Files touched (expected when implementing)

| Area | Files |
|------|--------|
| Backend service | `SuperAdminRazorpayPricingService`, DTOs under `superadmin/request|response` |
| Controller | `SuperAdminController` |
| Repository | `PlanVersionRepositoryWrapper` |
| Razorpay | `RazorpayApiService`, `RazorpayPlanSyncService` |
| Frontend | `SuperAdminRazorpayPricing*.tsx`, `resourceapi.tsx`, `MainContent.tsx`, `Sidebar.tsx` |
| Tests | `SuperAdminRazorpayPricingServiceTest` (recommended) |

**Reverted:** Partial implementation from 2026-06-20 was removed; this doc is the source of truth until implementation starts.
