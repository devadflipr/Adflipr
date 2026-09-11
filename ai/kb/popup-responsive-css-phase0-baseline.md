# Popup responsive CSS — Phase 0 regression contract

**Status:** **CLOSED** — all 18 original risk cases migrated (2026-07-05)  
**Tests:** `adflipr-app/src/layouts/PopupBuilder/utils/__tests__/responsiveCssBaseline.browser.test.ts`  
**Harness:** `adflipr-app/src/layouts/PopupBuilder/test-utils/responsiveCssBaselineHarness.ts`  
**Cases:** `adflipr-app/src/layouts/PopupBuilder/test-utils/responsiveCssBaselineCases.ts`  
**Related:** [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md) §17 Phase 0, [`popup-teaser-presentation-css-next-phase-report.md`](./popup-teaser-presentation-css-next-phase-report.md)

---

## Summary

| Metric | Original (2026-07-04) | Final (2026-07-05) |
| ------ | --------------------: | -----------------: |
| Original risk cases | 18 | 18 (preserved) |
| Risk — mobile override fails | **18** | **0** |
| Migrated regression green | 0 | **18** |
| Control categories | 5 | 5 |
| Control green | 5 | **5** |

**Verdict:** The hybrid model (desktop inline + mobile `@media` without `!important`) caused PCL-008 failures on all 18 trait-driven risk properties. Architecture migration to artifact-scoped `renderCss` fully repaired the matrix. The suite is now a **responsive CSS regression contract**, not an expected-red migration evidence suite.

---

## Migration milestones

| Phase | Families migrated | Scoreboard after |
| ----- | ----------------- | ---------------- |
| 2B | Form surface | 4 green |
| 3B–3C | Outer layout, columns container | 6 green |
| 4B–4D | Column presentation | 10–12 green |
| 5C | Button | 12 green / 6 red |
| 5D | Text | 12 green / 6 red |
| 5E | Cross, floating-image | 16 green / 2 red |
| Teaser | Style-only teaser chip | **18 green / 0 red** |

---

## Test harness design

1. **Builder snapshot** with distinct desktop/mobile trait values (`responsiveCssBaselineCases.ts`).
2. **Production serializer** — `serializePopupExport()` / `serializeTeaserHtml()` + `renderCss` (no mocks).
3. **Real Chromium** via Playwright inside Jest.
4. **Production hoist path** — embedded `<style>` cloned to `document.head`; `renderCss` mounted separately.
5. **Viewports** — desktop `1280×900`, mobile `375×812` (breakpoint `480px`).
6. **`getComputedStyle()`** — assert desktop at desktop viewport, mobile trait at mobile viewport.

**All 18 original risk tests expect mobile pass.** Control tests remain harness sanity checks.

---

## Original risk cases (now regression green)

| ID | Category | Property |
| -- | -------- | -------- |
| form-surface-padding | form-surface | padding |
| form-surface-margin | form-surface | margin |
| form-surface-width | form-surface | width |
| form-surface-border-radius | form-surface | border-radius |
| layout-padding | layout | padding |
| layout-margin | layout | margin |
| layout-border-radius | layout | border-radius |
| layout-box-shadow | layout | box-shadow |
| layout-column-gap | layout | gap |
| block-padding | block | padding |
| block-margin | block | margin |
| block-border-radius | block | border-radius |
| cross-top | cross | top |
| cross-right | cross | right |
| floating-bottom | floating-image | bottom |
| floating-left | floating-image | left |
| teaser-padding | teaser | padding |
| teaser-border-radius | teaser | border-radius |

---

## Controls (unchanged)

| ID | Property |
| -- | -------- |
| control-column-stack | flex-direction |
| control-product-horizontal | flex-direction |
| control-product-grid | grid-template-columns |
| control-hide-on-mobile | display |
| control-gamification-layout | flex-direction |

---

## Run

```bash
cd adflipr-app && npm test -- --watchAll=false --testPathPattern=responsiveCssBaseline.browser
```

**Expected:** 25 Jest tests (18 risk + 5 control + 2 meta), all pass.

---

## Historical root cause (PCL-008)

Desktop presentation was serialized inline; mobile rules lived in `@media` without `!important`. Inline beat stylesheet on the same element — mobile computed equaled desktop. **Repaired by moving merchant presentation to artifact-scoped `renderCss` with unified base + mobile cascade.**
