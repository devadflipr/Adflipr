# Phase 7C-L — Coupon Merchant Presentation Landing Report

**Date:** 2026-07-06  
**Branch:** `css-reft` (merged with `fix-0607`)  
**HEAD:** `5a6b2b1e672060a6381d5b4195a5c9b7cfa1d161`  
**Verdict:** **ACCEPT**

---

## 1. Executive verdict

Proven Phase 7C Coupon migration **landed** on the canonical branch. The implementation was already committed at `5a6b2b1e6` but had been **reverted in the working tree**; this phase restored proven artifacts from HEAD, closed the hostile-theme test gap, added cross-family coexistence verification, and re-ran regression gates. Coupon is **FULLY MIGRATED**. Media not started.

---

## 2. Canonical starting-state verification

| Check | Result |
|---|---|
| Branch `css-reft` / merged `fix-0607` | **YES** |
| Checkbox compiler active | **YES** |
| Divider compiler active | **YES** |
| Coupon compiler absent (working tree) | **YES** — reverted on disk |
| Coupon serializer inline merchant CSS | **YES** — pre-restore |
| `coupon` in `buildBlockTraitResponsiveCss` | **YES** — pre-restore |

**Starting state matched report: YES** (coupon unmigrated on disk; committed at HEAD)

---

## 3. Proven Phase 7C artifact inventory

| Proven artifact | Source | Action |
|---|---|---|
| `couponPresentation.ts` | Commit `5a6b2b1e6` | **PORT** (git restore) |
| `coupon.export.css` | Commit `5a6b2b1e6` | **PORT** |
| `compileCouponPresentationRenderCss` wiring | Commit `5a6b2b1e6` | **PORT** |
| ElementStyles coupon resolvers | Commit `5a6b2b1e6` | **PORT** |
| Structure-only `coupon/serialize.ts` | Commit `5a6b2b1e6` | **PORT** |
| `coupon` in `MIGRATED_BLOCK_TRAIT_TYPES` | Commit `5a6b2b1e6` | **PORT** |
| Wrapper merchant stripping | Commit `5a6b2b1e6` | **PORT** |
| Sheet embedding in `serializeLayout.ts` | Commit `5a6b2b1e6` | **PORT** |
| Unit + browser tests | Commit `5a6b2b1e6` | **PORT** |
| G1 fixture coverage | Commit `5a6b2b1e6` | **PORT** |
| Hostile-theme browser test | **Added in 7C-L** | **NEW** |
| Cross-family coexistence test | **Added in 7C-L** | **NEW** |

---

## 4. Current-branch artifact gap (pre-landing)

Working tree had deleted/reverted all Coupon migration files while HEAD retained the proven commit. No architectural drift — integration gap only.

---

## 5. Proven-source reuse method

```text
git restore --source=HEAD <proven Phase 7C file list>
```

No redesign. Hostile-theme + coexistence tests added per 7C-L spec.

---

## 6–8. Revalidation

| Contract | Valid |
|---|---|
| DOM topology | **YES** — wrapper → shell → inner row → code + copy |
| Persisted style model | **YES** — unchanged |
| Copy runtime (`embed/blockInitializers/coupon.ts`) | **YES** — unchanged |

---

## 9. Coupon ownership model

| Owner | Role |
|---|---|
| Wrapper `[data-adflipr-block-id]` | Block layout (margin, width, position) |
| Shell `[data-adflipr-coupon]` | Padding, background, border, radius, shadow, height |
| Code `[data-adflipr-coupon-code-text]` | Typography |
| Copy `.pbn-coupon-copy-btn` | Structure in `coupon.export.css`; behavior in embed |

---

## 10–12. Compiler landed and registered

- `resolveCouponWrapperCssRules`, `resolveCouponShellPresentationCssRules`, `resolveCouponCodePresentationCssRules`
- `compileCouponPresentationRenderCss` in `compilePopupRenderCss.ts` after divider
- Checkbox and Divider compilers unchanged

---

## 13–14. Structural sheet + G1

`coupon.export.css` — structure only (inner row, copy control). **No merchant presentation.**

G1 marker: `.pbn-coupon-inner-row` — **PASS**

---

## 15–17. Serializer + responsive cleanup

- `serializeCoupon` — structure-only (no `style=""` on merchant owners)
- `buildCouponContainerStyle`, `buildCouponCodeStyle`, inline row/button constants — **removed** (zero consumers)
- `coupon` excluded from `buildBlockTraitResponsiveCss`

---

## 18. Mobile ownership

DIFF-ONLY per owner: shell padding, code font-size, wrapper margin. Unset mobile inherits desktop — verified in browser tests.

---

## 19–23. Union, conflict, isolation

- Multi-step dedup + `CouponPresentationConflictError` — unit tested
- Artifact isolation + multi-runtime — browser tested
- Deterministic compiler ordering — preserved

---

## 24. Copy behavior regression

`embed/__tests__/coupon.test.ts` — clipboard copies rendered code — **PASS**

---

## 25. Hostile-theme closure

Added browser test: global `button`/`div`/`span` rules do not override artifact-scoped shell/code presentation — **PASS**

---

## 26–28. Chromium + negative assertions

Desktop/mobile computed styles on shell and code owners — **PASS**  
Serializer negative assertions — **PASS**  
Generic responsive exclusion — **PASS**

---

## 29–30. Consumer + coexistence

Standard export path supplies HTML + renderCss + structural sheet — **PASS**  
Checkbox + Divider + Coupon in one artifact — **PASS**

---

## 31. Obsolete code deleted

- `buildCouponContainerStyle`, `buildCouponCodeStyle`, `COUPON_INNER_ROW_STYLE`, `COUPON_COPY_BUTTON_STYLE` from `commerceAndCrossStyles.ts`

---

## 32. Production-derived inventory (post-landing)

| Family | Inline | Generic responsive | Compiler | Status |
|---|---|---|---|---|
| coupon | NONE | NONE | YES | **MIGRATED** |
| image | YES | YES | NO | UNMIGRATED |
| video | YES | YES | NO | UNMIGRATED |
| timer | YES | YES | NO | UNMIGRATED |
| spinner | YES | YES | NO | UNMIGRATED |
| product | YES | YES | NO | UNMIGRATED |
| feedback | YES | YES | NO | UNMIGRATED |
| social | YES | YES | NO | UNMIGRATED |

**True remaining merchant-CSS debt: 7 families**

---

## 33. Current-state update

`ai/kb/popup-css-migration-current-state.md` updated.

---

## 34. Deferred debt

**Unchanged** — boxShadow no-panel-writer, customCss, Button defect, Checkbox orphans.

---

## 35–37. Regression gates

| Gate | Result |
|---|---|
| Phase 0 | 18 / 0 / 5 — **PASS** |
| G1 | **PASS** |
| Production build | **PASS** |

---

## 38–39. Remaining debt

- **Coupon:** none (deferred orphan traits documented only)
- **All-block:** image, video, timer, spinner, product, feedback, social

---

## 40. Architecture gates

| Gate | Status |
|---|---|
| Proven landing without redesign | **PASS** |
| No Media scope | **PASS** |
| No partial Coupon | **PASS** |

---

## 41. Final verdict

**ACCEPT**

---

## 42. Recommended next action

Execute **Phase 7D** Media migration (Image + Video) with evidence gate Topology B.
