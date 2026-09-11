# Phase 7G — Spinner Merchant Presentation Migration Report

**Date:** 2026-07-06  
**Branch:** `css-reft`  
**Verdict:** **ACCEPT**

---

## 1. Executive verdict

Spinner merchant-configurable presentation moved from serializer inline CSS + `buildBlockTraitResponsiveCss` to canonical `spinnerMerchantPresentation.ts` resolvers + artifact-scoped `spinnerPresentation.ts` renderCss compiler + structural `spinner.export.css`.

Wheel art, animation, runtime state, and gamification mechanics preserved outside merchant renderCss.

Remaining merchant-CSS debt: **product** only.

---

## 2. Starting baseline (Part A)

| Check | Result |
|---|---|
| Timer compiler active | **YES** |
| Timer merchant inline absent | **YES** |
| Timer generic responsive absent | **YES** |
| Feedback compiler active | **YES** |
| Feedback canvas uses canonical resolvers | **YES** |
| Remaining `buildBlockTraitResponsiveCss` consumers | **spinner, product** |
| Spinner dedicated compiler | **ABSENT** (pre-7G) |
| Spinner serializer merchant inline | **PRESENT** (pre-7G) |

**Phase 7G starting baseline valid: YES**

---

## 3. Spinner debt inventory (Part B)

| Concern | Source file | Helper/compiler | Consumer |
|---|---|---|---|
| Block wrapper margin/width/overflow | `layoutStyles.ts` | `getExportWrapperStyleFromLayout('spinner')` | export wrapper → **renderCss** |
| Block shell padding/bg/border/radius/shadow | `ElementStyles.ts` | `getElementStyle` | canvas + **renderCss** |
| Root maxWidth + pointer padding | `spinnerMerchantPresentation.ts` | `resolveSpinnerRootCssRules` | **renderCss** |
| Canvas chrome (radius, shadow, cursor) | `spinnerMerchantPresentation.ts` | `resolveSpinnerCanvasCssRules` | **renderCss** |
| Result card typography | `spinnerMerchantPresentation.ts` | `resolveSpinnerResult*CssRules` | **renderCss** |
| Result overlay display:none | `spinner.export.css` | structural sheet | export `<style>` |
| Pointer SVG fill/position | `spinnerStyles.ts` | `buildSpinnerPointerSvg` | export HTML (data-dependent) |
| Wheel slice colors/fonts/sizes | `popupSpinnerContract.ts` | `buildSpinnerSerializedConfig` | `data-adflipr-popup-spinner-config` JSON |
| Canvas draw + spin animation | `embed/spinner.ts` | `PopupSpinnerController` | runtime |
| Email gate hint | `embed/spinner.ts` | `SPINNER_EMAIL_GATE_HINT_STYLE` | runtime-created DOM |
| Generic responsive padding/margin | `responsiveDeviceCss.ts` | `buildBlockTraitResponsiveCss` | **removed** (spinner in `MIGRATED_BLOCK_TRAIT_TYPES`) |

**Spinner debt reproduced: YES**

---

## 4. Architecture boundary

```text
merchant-configurable appearance → renderCss
fixed spinner geometry/scaffold → structural CSS (spinner.export.css, embed-responsive guards)
wheel slice art + fonts → serialized JSON config
canvas rotation + drawWheel → animation mechanics (runtime)
result display toggle → runtime state (inline display mutation)
```

**Pseudo-element merchant presentation exists: NO** — wheel uses `<canvas>`, pointer uses inline SVG fill from `pinColor`.

**Spinner runtime compatible with renderCss migration: YES**

**Spinner has stable migratable DOM ownership: YES**

---

## 5. DOM topology (export)

```text
div[data-block-type=spinner][data-adflipr-block-id]     ← wrapper (layout + shell → renderCss)
  div.pbn-spinner-outer                                   ← flex center (renderCss)
    div[data-adflipr-popup-spinner][config JSON]          ← root width/padding (renderCss)
      div.pbn-spinner-canvas-host                         ← overlay host (renderCss)
        canvas[data-adflipr-popup-spinner-canvas]         ← chrome in renderCss; art in runtime
        div.pbn-spinner-result                            ← state mechanics in spinner.export.css
          div.pbn-spinner-result-card                     ← renderCss
            div[data-adflipr-popup-spinner-result-heading].pbn-spinner-result-heading
            div[data-adflipr-popup-spinner-result-label].pbn-spinner-result-label
            div.pbn-spinner-result-code                   ← hidden in spinner.export.css
      SVG pointer (inline fill from pinColor)             ← data-dependent structure
```

---

## 6. Implementation delivered

| Artifact | Path |
|---|---|
| Canonical resolvers + canvas adapter | `components/form-builder/spinnerMerchantPresentation.ts` |
| renderCss compiler | `utils/popupHtmlSerializer/spinnerPresentation.ts` |
| Structural export sheet | `styles/sheets/spinner.export.css` |
| Structure-only serializer | `blocks/spinner/serialize.ts` |
| Canvas adapter wiring | `blocks/spinner/SpinnerCanvas.tsx` |
| Registry wiring | `compilePopupRenderCss.ts`, `MIGRATED_BLOCK_TRAIT_TYPES`, `migratedWrapperLayoutOwnership.ts` |
| G1 fixture | `scripts/export-css-production-fixture-entry.js`, `verify-export-css-production-build.js` |
| Tests | `spinnerPresentationRenderCss.test.ts` |

---

## 7. Verification

| Gate | Result |
|---|---|
| Phase 0 | **25/25 PASS** (18 migrated + 5 controls) |
| G1 export CSS production | **PASS** (includes `spinner.export.css`) |
| Phase 7G presentation tests | **7/7 PASS** |
| Gamification spinner serializer | **PASS** |
| Timer + wrapper parity suites | **18/18 PASS** |

---

## 8. Final inventory

| Family | Inline merchant | Generic responsive | Compiler | Status |
|---|---|---|---|---|
| spinner | **NONE** | **NONE** | **YES** | **MIGRATED** |
| product | YES | YES | NO | UNMIGRATED |

**Partial migrations: NONE**

**Recommended next action:** Product migration (Phase 7H+) — **not started per scope**.
