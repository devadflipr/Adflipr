# Popup analytics & submissions — short spec

**Scope:** Popup Builder detail page (Overview + Submissions).  
**Related:** `ai/kb/popups-api.md` (CRUD), Cart/Contact analytics patterns.

---

## Current state

| Area | Status |
|------|--------|
| Popup CRUD | Live (`/api/v1/popups`) |
| List stats (views, conversion, rate) | Always `0` in API |
| Overview tab | Mock KPIs, chart, visitor donut |
| Submissions tab | Mock rows; row menu = **Delete only** (Rename/Duplicate removed) |
| CSV export | Not built (spec Step 4) |
| Event ingest | Not built |

---

## Step 1 — Popup stats API (Overview tab)

**Goal:** Power time filters, compare, KPIs, performance chart, visitor source.

### Endpoints

```
GET /api/v1/popups/{popupId}/analytics?startDate=&endDate=
GET /api/v1/popups/{popupId}/traffic-distribution?startDate=&endDate=
```

### Response shape (mirror Cart/Contact analytics)

```json
{
  "insights": {
    "totalViews": 1900,
    "conversions": 1102,
    "conversionRate": 57.89
  },
  "dateGroupedStats": [
    { "year": 2026, "month": 6, "day": 1, "stats": { "views": 60, "conversions": 34 } }
  ]
}
```

- **Compare:** frontend calls the same endpoint twice (current + comparison range), like `Cart.tsx`.
- **All time:** empty `startDate` / `endDate`; infer span from first bucket if needed.
- **Prerequisite:** public ingest `POST /api/v1/popups/events` (`VIEW` | `SUBMIT`) → `popupEvent` (+ optional daily buckets).

### Visitor identity (required for accurate CR)

Storefront JS must send on **every** VIEW and SUBMIT:

| Field | Source | Purpose |
|-------|--------|---------|
| `visitorId` | UUID in `localStorage` (persistent anonymous id) | Unique visitors, visitor-based conversion rate |
| `sessionId` | UUID in `sessionStorage` (per tab session) | Session-scoped dedupe in embed (optional server use later) |

**Metrics:**

| Metric | Meaning |
|--------|---------|
| `totalViews` | Raw VIEW impressions |
| `uniqueVisitors` | Distinct `visitorId` on VIEW |
| `conversions` | Raw SUBMIT count |
| `uniqueConversions` | Distinct `visitorId` on SUBMIT (fallback: `contactId`) |
| `conversionRate` | `uniqueConversions ÷ uniqueVisitors` when ids present; else legacy impression-based |

Example: 10 views, 1 visitor, 1 submit → CR **100%**, not 10%.

Legacy rows without `visitorId` fall back to counting each event as one visitor until embed ships.

### Bot filtering (crawler inflation)

Server resolves on every ingest (embed does **not** need to send these):

| Field | Source | Purpose |
|-------|--------|---------|
| `userAgent` | HTTP `User-Agent` header (optional body fallback) | Known-bot detection |
| `ipHash` | HMAC-SHA256 of client IP (`popup.telemetry.ip-hash-secret` or `auth.page-view.ip-hash-secret`) | Audit / future dedupe; raw IP never stored |
| `bot` | UA pattern match (`PopupTelemetryBotDetector`) | Exclude from merchant analytics |

**Behavior:**

- **VIEW from bot:** stored with `bot=true`; excluded from views, unique visitors, charts, list stats.
- **SUBMIT from bot:** stored with `bot=true`; **no** contact upsert, tag, or activity; excluded from submissions tab and conversion metrics.
- Legitimate browser traffic: `bot=false` (default for legacy rows without the field).

Known patterns include Googlebot, Bingbot, Facebook/Twitter/LinkedIn crawlers, SEO tools (Ahrefs, Semrush), headless/automation UAs, curl/wget, etc.

### UTM attribution (campaign source)

Embed sends `pageUrl` (typically `window.location.href`). Server parses query params on every VIEW and SUBMIT:

| Field | Query param |
|-------|-------------|
| `utmSource` | `utm_source` |
| `utmMedium` | `utm_medium` |
| `utmCampaign` | `utm_campaign` |
| `utmTerm` | `utm_term` |
| `utmContent` | `utm_content` |

Values are URL-decoded and sanitized (`AuthPageViewAttributionSanitizer`, max 128 chars). Stored on `popupEvent` for future analytics breakdowns (e.g. “which campaign converted?”).

**v1 exposure:** submissions list API + CSV export include UTM columns; contact activity metadata includes UTMs on submit. Overview attribution charts are a later product slice.

### Popup content versioning

Each popup has a monotonic `version` (starts at **1** on create/duplicate). It increments when the merchant saves changes to **`htmlContent`** or **`metadata`** (design, headline, fields) — not on name/status-only edits.

Every `popupEvent` stores **`popupVersion`** = popup’s current version at ingest time (server-stamped from DB; embed does not send it).

Enables future per-version analytics (e.g. v1 CR 5% vs v2 CR 12%). Legacy popups/events without version are treated as **1**.

### A/B variant id (reserved)

Optional **`variantId`** on VIEW and SUBMIT ingest (`PopupTelemetryRequest` → `popupEvent.variantId`). Embed may send a stable variant key when running experiments (e.g. `control`, `headline-b`). No A/B product UI or analytics breakdown in v1 — field is stored for future “which variant converts better?” reporting. Omitted when not experimenting.

### Revenue attribution (popup → contact → orders)

Merchants will ask: **“How much revenue did this popup generate?”** (Klaviyo-style).

**Stored on every ACTIVE SUBMIT:**

| Layer | Fields | Purpose |
|-------|--------|---------|
| `popupEvent` | `popupId`, `contactId`, `email` | Event-level audit; submissions list / CSV |
| `popupContactAttribution` | `accountId`, `popupId`, `contactId`, `firstSubmissionEventId`, `firstSubmittedAt` | First-touch index for revenue joins (one row per popup + contact; re-submits are no-op) |
| `contacts` | `source=POPUP`, `sourceId=popupId` | First-touch provenance when contact has no stronger source (does not overwrite Shopify/WooCommerce) |

**Join path (future revenue API):**

```
popupContactAttribution (popupId)
  → contactIds
  → transaction (type=ORDER, contactId in set, accountId)
  → sum(order totals)
```

`TransactionRepositoryWrapper.findOrdersByContactIds` supports the order leg. Product API (e.g. `GET /api/v1/popups/{id}/revenue`) and Overview KPI are **not in v1** — attribution data is persisted so Shopify order sync can attribute revenue without backfill.

Deleting a submission (`status=DELETED`) does **not** remove attribution rows; the contact still converted via that popup.

### Flexible submission snapshot

SUBMIT ingest accepts **`submissionData`** — a string map of all form fields at submit time:

```json
{
  "eventType": "SUBMIT",
  "popupId": "...",
  "submissionData": {
    "email": "a@b.com",
    "firstName": "Ann",
    "country": "US",
    "birthday": "1990-01-01",
    "couponCode": "SAVE10"
  }
}
```

Legacy top-level `email`, `firstName`, `lastName`, `phone` are merged into `submissionData` for backward compatibility.

Stored on **`popupEvent.submissionData`** (full snapshot). Denormalized `email` / `firstName` / `lastName` / `phone` remain for list search and contact upsert. Custom fields (birthday, country, gender, coupon, etc.) live only in `submissionData` until a future UI maps them.

**Limits:** max 50 fields; keys ≤ 64 chars; values ≤ 1024 chars (sanitized).

**v1 exposure:** submissions API returns `submissionData`; CSV includes a JSON **Submission Data** column; contact activity metadata includes the map.

### Conversion timing (view → submit)

Requires **`visitorId`** on VIEW and SUBMIT (already required for unique-visitor CR). Optional **`sessionId`** narrows the anchor VIEW to the same browser session.

On SUBMIT ingest, server finds the anchor VIEW:

| `sessionId` present | Anchor VIEW |
|---------------------|-------------|
| Yes | Earliest VIEW with same `visitorId` + `sessionId` before submit |
| No | Latest VIEW with same `visitorId` before submit |

Stored on SUBMIT events:

| Field | Meaning |
|-------|---------|
| `attributedViewAt` | Anchor VIEW timestamp |
| `viewToSubmitSeconds` | Delay in seconds (e.g. view 10:00, submit 10:05 → `300`) |

**Analytics:** `GET .../analytics` insights include `averageViewToSubmitSeconds` and `timedConversions` (SUBMITs with timing). Overview tab shows avg. time to convert when data exists.

Embed must send the same `visitorId` / `sessionId` on VIEW and SUBMIT for timing to populate.

### Rate limiting (public ingest)

`POST /api/v1/popups/events` is unauthenticated. Protect with layered limits (Mongo-backed, same stack as signup rate limits):

| Layer | Tier | Default (prod) | Scope |
|-------|------|----------------|--------|
| Filter | `popup-telemetry` | 240 / minute | Client IP on path |
| Service | `popup-telemetry-popup` | 2,000 / minute | All traffic to one `popupId` |
| Service | `popup-telemetry-ip-view` | 120 / minute | IP + popup VIEW |
| Service | `popup-telemetry-ip-submit` | 10 / hour | IP + popup SUBMIT |

Exceeded limits → **429** with `Retry-After`. Config: `adflipr.public-endpoint.rate-limit.tiers.*`, `adflipr.public-endpoint.rate-limit.popup-telemetry-service-limits-enabled`.

---
- Connect `AnalyticsTimePeriodCompareBar` → `popupApi.getAnalytics`
- Map `dateGroupedStats` → `PerformanceChart`
- Visitor donut uses popup traffic endpoint (not campaign API)

---

## Step 2 — Submissions as contacts (list / tag)

**Goal:** Submissions are not a separate datastore; they are **contacts + membership + activity**.

### On form submit (ingest)

1. **Upsert contact** by email (`ContactService.upsertContact`); set `ContactSource.POPUP` (new enum value).
2. Map form fields → contact profile / `dynamicField`.
3. Apply popup-configured **list** and/or **tag** (`ContactMembershipService`).
   - Default tag: `Popup: {popupName}` (or `popup:{popupId}`).
   - Optional list: `{popupName} submissions`.
4. Record **ContactActivity** `POPUP_SUBMITTED` with `{ popupId, device, url, submittedAt }`.

### Submissions tab API

```
GET /api/v1/popups/{popupId}/submissions?pageNum=&pageSize=&search=
```

Returns rows: `contactId`, `name`, `email`, `submittedOn`, `phone`, `device`, `url` — query by popup tag/list or linked `popupSubmission` events.

**Search:** `search` filter uses Elasticsearch **`popupevent_*`** index (change-stream synced from `popupEvent`). Wildcard on email, name, phone, URL. Results are id-ordered from ES and hydrated from Mongo. Mongo regex fallback if ES is unavailable (e.g. index not yet backfilled).

**Index:** add `popupEvent` to `EsSyncCollection` + `elasticsearch/mappings/popupevent.json`; run delta sync / index migration before relying on search in prod.

### Open product decisions

- Re-submit same email: update contact + new activity row?
- Delete submission: soft-delete event only; **do not** delete the contact.
- Consent / double opt-in: align with list signup rules?

### Delete submission → tag removal

Soft-delete sets `status=DELETED` (and `deleted=true` for legacy queries). Remove the popup’s submission **tag** from the contact **only when** they have **zero** remaining **ACTIVE** SUBMIT events for that popup. If the contact submitted three times and one row is deleted, the tag stays until all three are deleted.

### Submission status

SUBMIT events use **`status`** instead of `deleted` alone:

| Status | Meaning | In analytics / submissions tab |
|--------|---------|----------------------------------|
| `ACTIVE` | Valid submission (default on ingest) | Yes |
| `DELETED` | Merchant deleted from Submissions tab | No |
| `SPAM` | Marked spam (future / admin) | No |
| `INVALID` | Invalid email / failed validation (future) | No |

Legacy rows without `status` treat `deleted=true` as `DELETED`, else `ACTIVE`. Only `ACTIVE` counts toward conversions, tag retention, and exports.

---

## Step 3 — Submissions UI cleanup

**Goal:** Row actions match contact-backed submissions (not editable clones).

| Action | Keep? | Rationale |
|--------|-------|-----------|
| **Delete** | Yes | Remove from popup list / revoke tag (or soft-delete activity) |
| **Rename** | **No** | Edit contact in **Contacts**, not in popup table |
| **Duplicate** | **No** | Submissions are events, not duplicable rows |

**Implemented (UI):** `PopupDetail.tsx` row menu = **Delete only**; bulk delete unchanged.

**Future:** Delete calls API; link row name/email to contact detail.

---

## Step 4 — Submissions CSV export (Submissions tab)

**Goal:** Merchant can download submission data from the same page as the table (search + pagination), for reporting and offline use.

### UI placement

- **Export** control on the Submissions tab toolbar — same row as **Search** (mirror Contacts table: export icon action in `TableComp.actions`).
- Icon: `icon-export` (same as All Contacts).
- Flow: toast “Preparing export…” → download → success/error toast (`downloadCSV` utility).

### Export scope

| Mode | Behavior |
|------|----------|
| **Default (v1)** | Export **all submissions for this popup** matching current **search** filter (ignore table page — full result set). |
| **Optional (v1.1)** | Export **selected rows only** when checkboxes are selected (bulk toolbar). |

Max rows: align with contact export limits (document cap e.g. 50k; return error if exceeded).

### Endpoint

```
GET /api/v1/popups/{popupId}/submissions/export
  ?search=optional
  &format=csv
```

Alternative (if POST preferred for future filters): `POST .../submissions/export` with `{ search, submissionIds? }` — same pattern as `POST /api/v1/contact/export` with `GenericFilterRequest`.

**Response**

- `Content-Type: text/csv; charset=UTF-8`
- `Content-Disposition: attachment; filename="{popup-slug}-submissions-{yyyy-MM-dd}.csv"`
- Body: UTF-8 CSV bytes (BOM optional for Excel)

### CSV columns (match table + useful ids)

| Column | Source |
|--------|--------|
| Name | Contact / submission |
| Email | Contact |
| Submitted on | Activity or event timestamp (ISO or locale-neutral `yyyy-MM-dd HH:mm:ss`) |
| Phone | Contact |
| Device | Event metadata |
| URL | Event metadata |
| Popup Version | `popupVersion` at submit time |
| Submission Data | JSON snapshot of all form fields (`submissionData`) |
| Time to convert (sec) | `viewToSubmitSeconds` |
| UTM Source / Medium / Campaign / Term / Content | Parsed from `pageUrl` at ingest |
| Contact ID | `contactId` (for support / re-import) |

Use RFC-style escaping for commas/quotes (same as `ContactEmailAnalyticsService.generateCSV` / contact export).

### Frontend

- `popupApi.exportSubmissions(popupId, { search })` → blob → `downloadCSV(data, fileName)`.
- i18n keys under `popupBuilder.submissions.export*` (all locales per project rule).
- Disabled while export in flight; re-use search term from table state.

### Backend

- Reuse submissions query from Step 2 (tag/list or `popupSubmission` events); **no separate export query logic**.
- `PopupService` or `PopupSubmissionService.exportToCsv(popupId, search)` → `byte[]`.
- Auth: account-scoped; popup must belong to caller’s `accountId`.

### Not in v1

- XLSX (contacts/campaigns support csv first; xlsx optional later)
- Export from Overview tab (analytics export is separate product ask)
- Scheduled / email delivery of exports

---

## Build order

1. Telemetry ingest + event model  
2. Analytics + traffic endpoints; wire Overview  
3. Submit → contact + tag/list + activity; submissions list API; wire Submissions tab  
4. Submissions CSV export API + Submissions tab export button  

---

## Out of scope (v1)

- Rename/duplicate submission rows in Popup Builder  
- Standalone submissions collection without `contactId`  
- Per-popup audience auto-creation (merchants use tag/list + existing audience builder)
- Analytics Overview CSV export
