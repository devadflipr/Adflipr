# Spin-to-Win — Business Requirements Document (BRD)

**Product:** Adflipr Popup Builder — Gamification (spin wheel)  
**Version:** 1.0  
**Status:** Approved for phased delivery  
**Last updated:** 2026-06-27  

---

## 1. Executive summary

Merchants use spin-to-win popups to capture email addresses and offer discounts. Today the wheel **visually** picks a prize, but Adflipr does **not** persist **email + prize + code** as one outcome. Thank You pages show **static** coupon codes unrelated to the spin.

This BRD defines requirements to link **visitor email**, **winning slice**, and **discount code** into a single auditable outcome, surface the correct code on Thank You, and include spin data in submissions.

---

## 2. Goals & success metrics

| Goal | Success metric |
|------|------------------|
| Tie email to prize | 100% of live spins produce a stored outcome with email |
| Correct Thank You code | Coupon block shows the code from the visitor's spin |
| Submission linkage | SUBMIT events include spin fields when applicable |
| Fraud resistance | Server picks winner; duplicate email cannot re-spin (default) |
| Merchant reporting | Submissions export includes prize label + code (Phase P1) |

---

## 3. Scope

### In scope

- Server-authoritative spin API
- Spin outcome persistence
- Embed integration (live storefront)
- Dynamic Thank You coupon block
- Preview behavior (no writes; client demo)
- Email gate before spin (existing)

### Out of scope (later phases)

- Shopify unique-code generation per visitor (Phase P3)
- Automation trigger “spin completed” email (Phase P2)
- Spin analytics dashboard slice breakdown (Phase P1)
- CAPTCHA / advanced bot protection

---

## 4. Personas

| Persona | Need |
|---------|------|
| **Merchant** | Configure slices, probabilities, codes; see who won what |
| **Visitor** | Enter email, spin once, get honest result + usable code |
| **Support** | Look up submission and see prize + code |

---

## 5. User stories

### US-1 — Configure wheel slices

**As a** merchant  
**I want** to define wheel slices with label, probability, and optional discount code  
**So that** visitors see prizes and I control win rates.

**Acceptance criteria**

- Each slice: label, probability (0–100), optional discount code
- Probabilities must sum to 100 before save (existing wheel settings)
- Loss slices may have empty discount code
- Post-spin navigation is configured per wheel slice (Action / Step / URL); button Close still wins over Step (SPIN-011)

### US-2 — Email gate

**As a** visitor  
**I must** enter a valid email before spinning  
**So that** the merchant can follow up.

**Acceptance criteria**

- Spin blocked until required email field is valid (existing)
- Hint shown when blocked (existing)

### US-3 — Server spin

**As a** visitor on a live store  
**When** I spin with a valid email  
**Then** Adflipr picks my prize on the server and the wheel lands on that slice.

**Acceptance criteria**

- `POST /api/v1/popups/{popupId}/spin` returns outcome before animation completes
- Response includes: `outcomeId`, `sliceId`, `label`, `discountCode`, `winningIndex`, `sliceCount`, optional `action` / `redirectStep` / `redirectUrl` (embed also merges nav fields from exported spinner config)
- Same email + popup → returns existing outcome (unless `allowMultipleSpin`)
- Unpublished popup → 404

### US-4 — Thank You shows my code

**As a** visitor who won a code  
**I want** the Thank You coupon block to show **my** code  
**Not** a generic placeholder.

**Acceptance criteria**

- Coupon block mode **From spin outcome** displays code from last spin in session
- Loss slice → coupon hidden or fallback message (merchant-configurable in P1.5)
- Static coupon mode unchanged for non-spin popups

### US-5 — Submission includes spin

**As a** merchant  
**When** a visitor submits the form  
**I want** submission data to include spin prize and code  
**So that** I can export and segment contacts.

**Acceptance criteria**

- SUBMIT `submissionData` includes: `spinOutcomeId`, `spinSliceLabel`, `spinDiscountCode`, `spinSliceId`
- Contact ingest unchanged (email still required for SUBMIT)

---

## 6. Functional requirements

### FR-1 Spin outcome entity

Store one record per spin with: popupId, accountId, email (normalized), visitorId, sessionId, slice snapshot, popupVersion, timestamp, status.

### FR-2 Idempotency

- Default: one outcome per **normalized email** per **popup** (campaign lifetime)
- `allowMultipleSpin=true` on spinner element: new outcome each spin
- Re-request with same email returns cached outcome (same response shape)

### FR-3 Weighted random selection

Server uses slice `probability` from published `builderState` (same algorithm as client preview).

### FR-4 Published config source

Slice config read from popup `metadata.builderState` → spinner element → `properties.slices`.

### FR-5 Preview / builder

- No spin API calls in preview
- Client-side pick allowed for UX demo only
- Preview SUBMIT blocked (existing)

### FR-6 Security

- Public endpoint, rate-limited (same tier family as popup telemetry)
- Email validation server-side
- Bot traffic logged; outcomes may be flagged later (P1)
- No raw IP stored (HMAC hash only, consistent with popup events)

---

## 7. Non-functional requirements

| Area | Requirement |
|------|-------------|
| Latency | Spin API p95 < 300ms |
| Availability | Failure must not break popup; embed falls back to client pick + log (degraded) |
| Privacy | Email stored per existing popup submission policy |
| i18n | New merchant UI strings in all 7 locales |

---

## 8. Data model (conceptual)

### Spin outcome

| Field | Type | Notes |
|-------|------|-------|
| outcomeId | string | Public id returned to embed |
| popupId | string | |
| accountId | string | |
| email | string | Lowercase normalized |
| visitorId | string | Optional |
| sessionId | string | Optional |
| spinnerElementId | string | |
| sliceId | string | |
| sliceLabel | string | Snapshot |
| discountCode | string? | |
| redirectStep | string? | In-popup step when Action=button |
| action | string? | `button` \| `url` ownership switch |
| redirectUrl | string? | External URL when Action=url |
| winningIndex | int | For wheel animation |
| sliceCount | int | |
| popupVersion | int | |
| created | datetime | |

### Submission extension keys

| Key | Description |
|-----|-------------|
| `spinOutcomeId` | Links to outcome |
| `spinSliceId` | Slice id |
| `spinSliceLabel` | e.g. "5% off" |
| `spinDiscountCode` | e.g. "SAVE5" |

---

## 9. API specification (Phase P0)

### POST `/api/v1/popups/{popupId}/spin`

**Auth:** None (public embed)

**Request body**

```json
{
  "email": "visitor@example.com",
  "visitorId": "uuid",
  "sessionId": "uuid",
  "spinnerElementId": "spinner-1"
}
```

**Response 200**

```json
{
  "outcomeId": "abc123",
  "sliceId": "slice-6",
  "sliceLabel": "5% off",
  "discountCode": "SAVE5",
  "redirectStep": "thank-you",
  "winningIndex": 5,
  "sliceCount": 10,
  "existingOutcome": false
}
```

**Errors**

| Code | Condition |
|------|-----------|
| 400 | Invalid email or missing spinner |
| 404 | Popup not found / not published |
| 429 | Rate limit |

---

## 10. Visitor flow (target)

1. Popup opens → visitor enters email  
2. Visitor clicks **Spin** CTA (`submit` or `next-step` only — wheel is not clickable)  
3. Embed validates → calls spin API with email  
4. Server picks slice, saves outcome, returns result  
5. Wheel animates to `winningIndex`  
6. Overlay shows label + code  
7. Button-owned navigation (e.g. Thank You via `actionStep`)  
8. Coupon block (spin mode) shows code from session  
9. On form SUBMIT **or** next-step (when spinner present), telemetry includes spin fields after spin completes  

**Architecture / ADR:** [`docs/POPUP_GAMIFICATION_SPINNER_ARCHITECTURE.md`](../../docs/POPUP_GAMIFICATION_SPINNER_ARCHITECTURE.md), [`docs/POPUP_GAMIFICATION_SPINNER_ADR.md`](../../docs/POPUP_GAMIFICATION_SPINNER_ADR.md)

---

## 11. Phased delivery plan

| Phase | Deliverables | Status |
|-------|--------------|--------|
| **P0** | BRD, spin API, outcome storage, embed integration, dynamic coupon, SUBMIT fields | In progress |
| **P1** | Submissions list/export columns, spin count analytics | Planned |
| **P2** | Automation trigger on spin completed | Planned |
| **P3** | Store coupon picker on slices, Shopify validation | Planned |
| **P4** | Unique per-email Shopify codes | Planned |

---

## 12. Open decisions

| # | Question | Recommendation |
|---|----------|----------------|
| 1 | Server pick vs client pick + log | **Server pick** (FR-3) |
| 2 | Idempotency key | **email + popupId** (default) |
| 3 | API failure fallback | Client pick + no persist (degraded); optional retry |
| 4 | Loss slice Thank You | Hide coupon; show text only (P0) |

---

## 13. Risks & mitigations

| Risk | Mitigation |
|------|------------|
| Client tampering before P0 | Server pick |
| builderState parse fails | 400 + log; merchant must republish |
| Email changed after spin | Outcome locked to email at spin time |
| High traffic spin abuse | Rate limits + one spin per email |

---

## 14. References

- `ai/kb/popup-analytics-and-submissions-spec.md`
- `ai/kb/popups-api.md`
- Popup embed: `adflipr-app/src/layouts/PopupBuilder/embed/`
