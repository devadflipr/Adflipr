# Popup columns-container CSS — Phase 3C implementation report

**Status:** Implemented (2026-07-05)  
**Type:** Production migration report for `renderCss` columns-container + coupled slot mechanics  
**Related:** [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md), [`popup-responsive-css-phase0-baseline.md`](./popup-responsive-css-phase0-baseline.md), [`popup-columns-container-css-phase3c-report.md`](./popup-columns-container-css-phase3c-report.md)

**Depends on (implemented):** Phase 2B form-surface, Phase 3B outer-layout `renderCss` migrations

---

## Summary

Phase 3C migrates multi-column flex layout mechanics from inline HTML + embedded `buildLayoutResponsiveCss` into artifact-scoped `renderCss`. The primary owner is `[data-adflipr-popup-columns]`; coupled structural slot wrappers (`[data-adflipr-popup-columns] > div`) migrate in the same unit because desktop slot `calc(...)` inline values defeat mobile stylesheet rules without `!important` (PCL-008).

**Scoreboard change:**

```text
Before: 10 risk red / 5 controls green / 8 migrated risk green
After:   9 risk red / 5 controls green / 9 migrated risk green
```

Exactly one risk case turned green: `layout-column-gap`. No other risk or control cases changed unexpectedly.

**Verified artifact state (post Phase 3C):**

```text
Popup Artifact
├── htmlByStep
└── renderCss
    ├── form-surface presentation (Phase 2B)
    ├── outer-layout presentation (Phase 3B)
    └── columns-container presentation (Phase 3C)
```

---

## A. Final migration boundary

| Role | Selector | Phase |
|------|----------|-------|
| **Primary owner** | `[data-adflipr-popup-columns]` | 3C |
| **Coupled structural child** | `[data-adflipr-popup-columns] > div` | 3C (intentional exception to “migrate by one styled DOM owner”) |
| **Deferred owner** | `[data-column-id]` | Phase 4 (padding, margin, background, border, alignment, etc.) |

`[data-column-id]` presentation was not migrated. Column merchant styling remains inline + embedded responsive rules.

---

## B. Exact moved properties

### Parent — `[data-adflipr-popup-columns]`

| Property | Desktop | Mobile (`mobileResponsive !== false`) |
|----------|---------|---------------------------------------|
| `display` | `flex` | (unchanged) |
| `flex-direction` | `row` | `column` |
| `gap` | `columnGapDesktop` / legacy `columnGap` fallback | `columnGapMobile` when different from desktop |
| `width` | `100%` | (unchanged) |
| `min-width` | `0` | (unchanged) |
| `align-items` | `stretch` | (unchanged) |

### Coupled slot — `[data-adflipr-popup-columns] > div`

| Property | Desktop | Mobile (`mobileResponsive !== false`) |
|----------|---------|---------------------------------------|
| `width` | `calc(...)` via `getColumnSlotStyle()` | `100%` |
| `flex-basis` | `calc(...)` via `getColumnSlotStyle()` | `100%` |
| `flex-grow` | `0` | (unchanged) |
| `flex-shrink` | `0` | (unchanged) |
| `align-self` | `stretch` | (unchanged) |
| `display` | `flex` | (unchanged) |
| `min-width` | `0` | (unchanged) |

---

## C. Why parent + slot must migrate together (PCL-008)

If only the parent moved to `renderCss` while desktop slot sizing remained inline:

```text
Desktop slot: style="width:calc(...);flex-basis:calc(...)"
Mobile slot:    @media { width:100%; flex-basis:100% }   // no !important
Result:         inline desktop values win → narrow slots on mobile
```

This is the same cascade failure as `layout-column-gap` (desktop inline `gap` defeating mobile `@media` `gap`). Parent and slot responsive mechanics must live in the same stylesheet cascade on the new artifact path.

---

## D. Compiler architecture

**Module:** `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/columnsContainerPresentation.ts`

**Entry point:** `compileColumnsContainerRenderCss({ formElementsByStep, artifactScopeId })`

**Pipeline:**

1. Walk all popup steps via `collectArtifactColumnsContainerLayouts()`
2. Select only `two-column` and `three-column` layouts (`isMultiColumnLayout()`)
3. Normalize mechanics: `columnGapDesktop`, `columnGapMobile`, `mobileResponsive`, `columnWidths`, `layoutType`
4. Deduplicate identical `layoutId` across steps (same normalized mechanics → one CSS unit)
5. Reject conflicting duplicate `layoutId` values → `ColumnsContainerPresentationConflictError` with `layoutId` + `stepIds`
6. Emit deterministic base container CSS
7. Emit deterministic per-slot desktop CSS (`nth-child(n)`)
8. Emit deterministic mobile parent + per-slot CSS inside `@media only screen and (max-width: 480px)`
9. Scope every selector by `artifactScopeId` + `layoutId`
10. Return empty string when no eligible multi-column layouts exist (one-column popups)

**Composition in `serializePopupExport()`:**

```text
serializePopupExport()
└── renderCss
    ├── compileFormSurfaceRenderCss()        (Phase 2B)
    ├── compileOuterLayoutRenderCss()          (Phase 3B)
    └── compileColumnsContainerRenderCss()     (Phase 3C)
```

**Shared semantics reused (not duplicated):**

- `getColumnSlotStyle()` from `ElementStyles.ts` — desktop + mobile slot sizing
- `resolveColumnGapDesktopPx()` / `resolveColumnGapMobilePx()` — gap resolution matching legacy serializer
- `resolveLayoutColumnWidths()` — `columnWidths` array or type defaults

**Selector specificity note:** Mobile slot rules use `> div:nth-child(n)` (not a bare `> div` selector) so they match desktop per-slot selector specificity. Otherwise desktop `calc(...)` rules win at mobile viewport without `!important`.

---

## E. Multi-step union

One popup has one `renderCss`. Phase 3C CSS is compiled for all eligible multi-column layouts across all steps.

**Example:**

```text
Step 1 → Layout A (two-column, gap 24/6)
Step 2 → Layout C (three-column, gap 12/3)

renderCss contains both Layout A and Layout C columns rules
```

**Test evidence:** `columnsContainerRenderCss.test.ts` — `compiles union of multi-column layouts across all steps`

---

## F. Duplicate layout identity and conflict detection

| Scenario | Behavior |
|----------|----------|
| Same `layoutId` + same normalized Phase 3C mechanics | Emit one columns-container CSS unit |
| Same `layoutId` + different normalized mechanics | `ColumnsContainerPresentationConflictError` |

**Normalized conflict fields:** resolved `columnGapDesktop`, `columnGapMobile`, `mobileResponsive`, `columnWidths`, `layoutType`.

**Rendered-meaning comparison:** `columnGap: '16'` (legacy) and `columnGapDesktop: '16'` do not conflict — both resolve to desktop gap `16`.

Conflict behavior does not depend on step order. Error identifies `layoutId` and conflicting `stepIds`.

---

## G. Before / after HTML

### Legacy path (no `artifactScopeId`)

```html
<div data-adflipr-popup-columns="true"
     style="display:flex;flex-direction:row;gap:24px;width:100%;min-width:0;align-items:stretch">
  <div style="width:calc((100% - 24px) * 0.5);flex-basis:calc((100% - 24px) * 0.5);flex-grow:0;flex-shrink:0;align-self:stretch;display:flex;min-width:0">
    <div data-column-id="layout-2col-left">...</div>
  </div>
  <div style="width:calc((100% - 24px) * 0.5);flex-basis:calc((100% - 24px) * 0.5);...">
    <div data-column-id="layout-2col-right">...</div>
  </div>
</div>
```

### New artifact path (`artifactScopeId` + `useStandardSurfaceRenderCss`)

```html
<div data-adflipr-popup-columns="true">
  <div>
    <div data-column-id="layout-2col-left">...</div>
  </div>
  <div>
    <div data-column-id="layout-2col-right">...</div>
  </div>
</div>
```

Migrated parent and slot mechanics are absent from inline `style` attributes. `[data-column-id]` and required selector attributes are preserved.

---

## H. Generated `renderCss` shape

```css
[data-adflipr-popup-artifact="popup-123"]
[data-adflipr-layout-id="layout-A"]
[data-adflipr-popup-columns] {
  display:flex;
  flex-direction:row;
  gap:24px;
  width:100%;
  min-width:0;
  align-items:stretch;
}

[data-adflipr-popup-artifact="popup-123"]
[data-adflipr-layout-id="layout-A"]
[data-adflipr-popup-columns] > div:nth-child(1) {
  width:calc((100% - 24px) * 0.5);
  flex-basis:calc((100% - 24px) * 0.5);
  flex-grow:0;
  flex-shrink:0;
  align-self:stretch;
  display:flex;
  min-width:0;
}

@media only screen and (max-width: 480px) {
  [data-adflipr-popup-artifact="popup-123"]
  [data-adflipr-layout-id="layout-A"]
  [data-adflipr-popup-columns] {
    flex-direction:column;
    gap:6px;
  }

  [data-adflipr-popup-artifact="popup-123"]
  [data-adflipr-layout-id="layout-A"]
  [data-adflipr-popup-columns] > div:nth-child(1) {
    width:100%;
    flex-basis:100%;
  }
  /* ... nth-child(2), nth-child(3) for three-column ... */
}
```

No `!important` in new Phase 3C artifact CSS.

---

## I. `mobileResponsive: false`

Preserves legacy behavior exactly:

- `buildLayoutResponsiveCss` skips the entire layout when `mobileResponsive === false`
- New artifact path emits **no** mobile stack rules in Phase 3C `renderCss`
- At mobile viewport: `flex-direction: row`, desktop `gap` (e.g. `24px`), slots keep `calc(...)` widths

**Verified computed values (mobile viewport, `mobileResponsive: false`):**

| Property | Value |
|----------|-------|
| Parent `flex-direction` | `row` |
| Parent `gap` | desktop trait (e.g. `24px`) |
| Slot `flex-basis` | `calc(...)` (not `100%`) |

---

## J. Legacy compatibility

Without `artifactScopeId` / new artifact migration context:

- Columns container inline CSS unchanged
- Desktop slot inline CSS unchanged
- `buildLayoutResponsiveCss` embedded mobile CSS unchanged (including `!important` on stack rules)
- Old popup records are not rewritten

Verified in `columnsContainerRenderCss.test.ts` and `columnsContainerPresentation.browser.test.ts`.

---

## K. Embedded CSS removal (artifact path only)

On new artifact path, `skipColumnsContainerResponsive: true` causes `buildLayoutResponsiveCss` to return empty string for migrated multi-column layouts.

**Not disabled:**

- `buildLayoutSectionResponsiveCss` (outer layout section rules — Phase 3B skip unchanged)
- `[data-column-id]` responsive rules
- Block responsive rules
- Surface responsive rules
- Teaser rules
- Gamification rules
- Visibility rules

Skip occurs only after all four columns responsive responsibilities are represented in `renderCss`: mobile gap, mobile flex-direction, mobile slot width, mobile slot flex-basis.

---

## L. `!important` verification

New Phase 3C `renderCss` contains **no** `!important`.

Computed-style tests confirm mobile gap, parent `flex-direction: column`, and full-width slots work without cascade overrides. Legacy embedded CSS retains `!important` where it existed before.

---

## M. Strong visual stack test

Beyond `control-column-stack` (parent `flex-direction` only), `columnsContainerPresentation.browser.test.ts` verifies at mobile viewport:

| Invariant | Result |
|-----------|--------|
| Parent `flex-direction` | `column` |
| Each direct slot width | ≈ container width (delta &lt; 2%) |
| Each direct slot `flex-basis` | `100%` or full pixel equivalent |

Test fails if desktop slot `calc(...)` widths accidentally remain active at mobile.

---

## N. Files changed

### Production

| File | Role |
|------|------|
| `popupHtmlSerializer/columnsContainerPresentation.ts` | **New** — compiler, normalization, conflict detection, selector helpers |
| `popupHtmlSerializer/index.ts` | Compose Phase 3C into `renderCss` |
| `popupHtmlSerializer/serializeLayout.ts` | Dual-path: omit columns + slot inline on artifact path |
| `popupHtmlSerializer/responsiveDeviceCss.ts` | `skipColumnsContainerResponsive` → skip `buildLayoutResponsiveCss` |

### Tests

| File | Role |
|------|------|
| `__tests__/columnsContainerRenderCss.test.ts` | **New** — compiler, conflict, multi-step, legacy, one-column, payload |
| `__tests__/columnsContainerPresentation.browser.test.ts` | **New** — computed gap, stack, full-width slots, isolation, legacy |
| `__tests__/outerLayoutRenderCss.test.ts` | Updated for Phase 3C columns behavior |
| `__tests__/outerLayoutPresentation.browser.test.ts` | `layout-column-gap` now green |
| `__tests__/responsiveCssBaseline.browser.test.ts` | Scoreboard 9 red / 9 migrated green |

---

## O. Test results

| Category | Result |
|----------|--------|
| Compiler tests (`columnsContainerRenderCss.test.ts`) | 16/16 pass |
| Computed-style tests (`columnsContainerPresentation.browser.test.ts`) | 16/16 pass |
| Multi-step union | pass |
| Duplicate-ID dedup + conflict | pass |
| Artifact isolation | pass |
| Legacy compatibility | pass |
| `mobileResponsive: false` | pass |
| One-column no-op | pass |
| Strong full-width stack test | pass |
| Phase 0 baseline (`responsiveCssBaseline.browser.test.ts`) | 56/56 pass |

---

## P. Phase 0 scoreboard

| Metric | Before | After |
|--------|--------|-------|
| Risk red | 10 | **9** |
| Controls green | 5 | **5** |
| Migrated risk green | 8 | **9** |

**Turned green:** `layout-column-gap`

**Remaining red:**

- `block-padding`, `block-margin`, `block-border-radius`
- `cross-top`, `cross-right`
- `floating-bottom`, `floating-left`
- `teaser-padding`, `teaser-border-radius`

---

## Q. Controls

All five existing controls remain green:

- `control-column-stack`
- `control-product-horizontal`
- `control-product-grid`
- `control-hide-on-mobile`
- `control-gamification-layout`

---

## R. Payload impact

Total `renderCss` size (form-surface + outer-layout + columns-container) for popups with N multi-column layouts:

| Multi-column layouts | Total `renderCss` bytes |
|---------------------|-------------------------|
| 1 | 2,010 |
| 5 | 7,062 |
| 10 | 13,387 |
| 20 | 26,087 |

**Phase 3B reference totals** (form + outer layout only, from prior migration):

| Multi-column layouts | Phase 3B bytes |
|---------------------|----------------|
| 1 | 917 |
| 5 | 1,781 |
| 10 | 2,862 |
| 20 | 5,032 |

> **Correction (Phase 4A audit):** The “~90–160 bytes per layout” estimate below was **incorrect math** — it subtracted incompatible totals. Decomposed measurement shows columns-container adds **~1,250 bytes per two-column layout** (~625 per slot). See [`popup-column-presentation-css-phase4a-inventory.md`](./popup-column-presentation-css-phase4a-inventory.md) Part 1.

**Decomposed reference (unified audit fixture):**

| Layouts | Form | Outer | Columns | Total |
|--------:|-----:|------:|--------:|------:|
| 1 | 711 | 379 | 1,288 | 2,380 |
| 5 | 711 | 1,727 | 6,268 | 8,708 |
| 10 | 711 | 3,429 | 12,503 | 16,645 |
| 20 | 711 | 6,849 | 25,023 | 32,585 |

No optimization applied — payload is within expected bounds for scoped selectors + per-slot `nth-child` rules.

Measured by `columnsContainerRenderCss.test.ts` (`PHASE_3C_RENDER_CSS_PAYLOAD_SAMPLES`) for headline totals; decomposition verified in Phase 4A analysis.

---

## S. Architecture verification

| Invariant | Status |
|-----------|--------|
| HTML and `renderCss` remain separate | ✓ |
| Flex remains the layout engine (no Grid migration) | ✓ |
| Parent + coupled slot responsive mechanics migrated together | ✓ |
| `[data-column-id]` presentation remains untouched | ✓ |
| No `!important` added to new artifact CSS | ✓ |
| No consumer contract changed | ✓ |
| No runtime lifecycle changed | ✓ |

---

## T. Phase 4 readiness

`[data-column-id]` is ready for a separate ownership inventory (Phase 4). Column presentation traits — padding, margin, background, border-radius, box-shadow, alignment — remain inline + embedded today and are cleanly separable from Phase 3C flex mechanics.

**Do not implement Phase 4 in this change.**

---

## Key implementation detail: selector specificity

During implementation, mobile slot rules initially used a bare `> div` selector. Desktop per-slot rules use `> div:nth-child(n)`, which has higher specificity. Mobile slot `width: 100%` lost to desktop `calc(...)` at mobile viewport — the strong stack test caught this.

**Fix:** Emit mobile slot rules with the same `> div:nth-child(n)` selectors as desktop. Equal specificity + `@media` ordering allows mobile values to win without `!important`.
