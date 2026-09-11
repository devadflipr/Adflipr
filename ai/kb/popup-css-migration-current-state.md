# Popup CSS Migration — Current Repository State

**Updated:** 2026-07-06 (Phase 8A final architecture closure)  
**Branch:** `css-reft`

---

## Architecture status: CLOSED

All known block-family merchant presentation compiles through artifact-scoped `renderCss`.

```text
Partial migrations:                    NONE
Generic responsive merchant consumers: NONE
Migration-era exclusion registries:    DELETED
Serializer merchant wrapper inline:    NONE (structural flex-shrink only)
```

---

## Steady-state architecture

1. Merchant presentation → canonical owner-specific resolvers → artifact `renderCss`
2. Serializers → identity, content, structure, behavior hooks (not merchant presentation)
3. Structural `*.export.css` sheets → structure, responsive safety, behavior/state mechanics
4. Embedded step device CSS → viewport hide rules + phone select structural width only
5. Export block wrappers → `getStructuralExportBlockWrapperStyle()` (`flex-shrink: 0` only)
6. No generic fallback merchant-responsive generator
7. New block families must implement forward-only architecture directly

See: `ai/kb/popup-css-migration-final-architecture-closure-report.md`

---

## Fully migrated families (dedicated `renderCss` compiler)

| Family | Compiler module | Export structural sheet |
|---|---|---|
| form-surface | `formSurfacePresentation.ts` | — |
| outer-layout | `outerLayoutPresentation.ts` | — |
| columns-container | `columnsContainerPresentation.ts` | — |
| column | `columnPresentation.ts` | — |
| button | `buttonPresentation.ts` | — |
| text | `textPresentation.ts` | — |
| text-input / email-input / date | `standardFieldPresentation.ts` | `floating-label.export.css` |
| textarea | `textareaPresentation.ts` | — |
| dropdown | `dropdownPresentation.ts` | — |
| phone-input | `phoneInputPresentation.ts` | — |
| radio | `radioPresentation.ts` | `radio.export.css` |
| check-box | `checkboxPresentation.ts` | `checkbox.export.css` |
| divider | `dividerPresentation.ts` | — |
| coupon | `couponPresentation.ts` | `coupon.export.css` |
| image | `mediaPresentation.ts` | `image.export.css` |
| video | `mediaPresentation.ts` | `video.export.css` |
| social | `socialPresentation.ts` | `social.export.css` |
| feedback | `feedbackPresentation.ts` | `feedback.export.css` |
| timer | `timerPresentation.ts` | `timer.export.css` |
| spinner | `spinnerPresentation.ts` | `spinner.export.css` |
| product | `productPresentation.ts` | `product.export.css` |
| cross / floating-image | `positionedDecorativePresentation.ts` | — |
| teaser | `teaserPresentation.ts` | — |
| gamification | `gamificationPresentation.ts` | — |

---

## Deleted migration infrastructure (Phase 8A)

| Component | Action |
|---|---|
| `buildBlockTraitResponsiveCss` | **DELETED** |
| `MIGRATED_BLOCK_TRAIT_TYPES` | **DELETED** |
| `migratedWrapperLayoutOwnership.ts` | **DELETED** → `exportBlockWrapperLayout.ts` |
| Per-family "excluded from legacy generator" tests | **REPLACED** with steady-state contracts |

---

## Embedded step device CSS (retained)

`buildEmbeddedStepDeviceCss()` emits only:

- `buildBlockDeviceVisibilityCss()` — viewport hide (behavior)
- `buildPhoneInputCountrySelectResponsiveCss()` — structural phone select width

---

## Remaining legitimate inline CSS

| Location | Purpose |
|---|---|
| Export block wrapper | `flex-shrink: 0` (structure) |
| Button align container | `text-align` (structure) |
| Button RTE `<a>` | hostile-theme link color guard |
| Teaser / floating-image | `pointer-events`, img dimensions (behavior/structure) |
| Form root | width/box-sizing scaffold |

---

## Deferred defects (unchanged — not fixed in Phase 8A)

- Button linked-label hover defect
- Checkbox `backgroundColor` orphan trait
- Coupon legacy `boxShadowEnabled` data path
- Image `elementWidth` orphan (non-image blocks)
- Timer runtime fit mechanics
- Date/phone canvas `getElementStyle` drift

## Block custom styling (Track 2B)

| Field | Status |
|---|---|
| `block.style.customClass` | **ACTIVE** — normalized on read; canvas + export wrapper `[data-adflipr-block-id]` |
| `block.style.customCss` | **UNWIRED** — Track 2B.2 blocked pending read-only Mongo audit |

See: `ai/kb/popup-block-custom-class-track-2b1-implementation.md`

---

## Regression gates

| Gate | Result |
|---|---|
| Phase 0 (`test:popup-responsive-baseline`) | **25/25 PASS** |
| G1 (`test:export-css-production-contract`) | **PASS** |
| Production build | **PASS** |
| Steady-state architecture tests | **PASS** |

---

## Recommended next phase

**Track 2 Stream A** — block-level `customCss` / `customClass` artifact export pipeline
