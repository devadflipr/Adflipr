# Phase G2F — Narrow CSS Architecture Cleanup Report

**Date:** 2026-07-06  
**Scope:** Phone Input wrapper collision + Preview teaser artifact-scope parity  
**Verdict:** **ACCEPT**

---

## 1. Pre-change Phone wrapper HTML evidence

`serializeElements.ts` applied `applyMigratedWrapperLayoutExportStripping` to `text-input`, `email-input`, `date`, `textarea`, `dropdown`, and `radio` — but **not** `phone-input`.

For a phone block with `marginDesktop.bottom = 20` and `elementPosition = center`, `getExportWrapperStyleFromLayout('phone-input', …)` produced wrapper inline similar to:

```html
<div style="flex-shrink:0;margin-bottom:20px;width:100%;max-width:300px;margin-left:auto;margin-right:auto"
     data-block-type="phone-input" data-adflipr-block-id="phone-wrap">
```

## 2. Pre-change Phone renderCss evidence

`compilePhoneInputPresentationRenderCss` emitted the same merchant properties on the **same** wrapper selector:

```css
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="phone-wrap"]{margin-bottom:20px;...}
@media only screen and (max-width: 480px){
  [data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="phone-wrap"]{margin-bottom:4px}
}
```

## 3. Same-property collision

**PROVED — YES**

| Property | Inline owner | renderCss owner | Same DOM node |
|---|---|---|---|
| `margin-bottom` | wrapper `style=""` | wrapper selector | **YES** |
| `width` / `max-width` | wrapper `style=""` (non-fullWidth) | wrapper selector | **YES** |
| `margin-left` / `margin-right` (auto alignment) | wrapper `style=""` | wrapper selector | **YES** |

Mobile `@media` margin could not override desktop inline margin (PCL-008).

## 4. Root cause

Phase 6E migrated Phone Input merchant presentation to `compilePhoneInputPresentationRenderCss` and excluded `phone-input` from `buildBlockTraitResponsiveCss`, but **`serializeElements.ts` was never updated** to strip wrapper layout the way peer standard-field families were.

## 5. Exact serializer fix

- Added `migratedWrapperLayoutOwnership.ts` with `MIGRATED_WRAPPER_LAYOUT_BLOCK_TYPES` and `applyMigratedWrapperLayoutExportStripping()`.
- Replaced four duplicated strip blocks in `serializeElements.ts` with one call to `applyMigratedWrapperLayoutExportStripping(element.type, wrapperStyle)` — includes **`phone-input`**.

## 6. Canonical migrated-family registry

**YES** — `MIGRATED_WRAPPER_LAYOUT_BLOCK_TYPES` in `migratedWrapperLayoutOwnership.ts` is the serializer-side canonical list for wrapper layout stripping.

## 7. Duplicated family lists remaining

**YES** — intentional separation documented in module comment:

| List | Location | Purpose |
|---|---|---|
| `MIGRATED_WRAPPER_LAYOUT_BLOCK_TYPES` | `migratedWrapperLayoutOwnership.ts` | Strip merchant wrapper inline at export |
| `MIGRATED_BLOCK_TRAIT_TYPES` | `responsiveDeviceCss.ts` | Skip generic embedded responsive CSS |

Not merged — `MIGRATED_BLOCK_TRAIT_TYPES` is a superset (adds button, text, cross, floating-image).

## 8. Post-change Phone wrapper HTML

```html
<div style="flex-shrink:0" data-block-type="phone-input" data-adflipr-block-id="phone-wrap">
```

No `margin`, `width`, `max-width`, or auto-margin in inline `style`.

## 9. Post-change Phone renderCss

Unchanged ownership — wrapper margin desktop/mobile still in `compilePhoneInputPresentationRenderCss`:

```css
[data-adflipr-popup-artifact="g2f-wrapper-parity"] [data-adflipr-block-id="phone-wrap"]{margin-bottom:20px;...}
@media ...{...margin-bottom:4px}
```

## 10. Desktop computed style

Chromium (`phoneInputPresentation.browser.test.ts` — Phase G2F wrapper margin test):

- `marginBottom` on `[data-adflipr-block-id="phone-1"]` wrapper = **20px** (desktop viewport)

## 11. Mobile computed style

- `marginBottom` on same wrapper = **4px** (mobile viewport 375px)
- Wrapper `style` attribute contains **no** `margin` substring

## 12. Preview main artifact scope before

`buildPreviewArtifactExport` → `artifactScopeId = 'pbn-preview'` (`PREVIEW_SPIN_OUTCOME_POPUP_ID`)

## 13. Preview teaser scope before

`resolvePreviewTeaserHtml` hardcoded `'pbn-teaser-preview'` in `serializedTeaserPreviewMount.ts`

## 14. Teaser scope mismatch

**PROVED — YES**

```text
main renderCss:  [data-adflipr-popup-artifact="pbn-preview"]
teaser HTML:     data-adflipr-popup-artifact="pbn-teaser-preview"
```

Teaser chip rules in `compileTeaserPresentationRenderCss` would not apply to teaser preview HTML.

## 15. Style-only teaser preview result

| Path | Before | After |
|---|---|---|
| `resolvePreviewTeaserHtml` (serialized) | `pbn-teaser-preview` | **`pbn-preview`** |
| `FormPreview` + `useCanvasTeaser` | React `TeaserPreview` (no artifact attr) | **Unchanged** — valid canvas overlay; receives `renderCss` via embed runtime mount |

## 16. Block-based teaser preview result

| Path | Before | After |
|---|---|---|
| `resolvePreviewTeaserHtml` with teaser elements | `pbn-teaser-preview` | **`pbn-preview`** |
| `SerializedTeaserPreviewCanvas` | wrong scope | passes `PREVIEW_SPIN_OUTCOME_POPUP_ID` |
| `buildPreviewEmbedConfig` simulateTeaserFlow | mismatched | passes `artifact.artifactScopeId` |

## 17. Scope fix implemented

- `resolvePreviewTeaserHtml(..., artifactScopeId = PREVIEW_SPIN_OUTCOME_POPUP_ID)`
- `buildPreviewEmbedConfig` passes `artifact.artifactScopeId`
- `SerializedTeaserPreviewCanvas` passes `PREVIEW_SPIN_OUTCOME_POPUP_ID`

## 18. Post-change HTML/renderCss scope proof

```text
HTML:      data-adflipr-popup-artifact="pbn-preview"
renderCss: [data-adflipr-popup-artifact="pbn-preview"]
```

Verified in `previewArtifactScopeParity.test.ts` for main, style-only teaser, block teaser, and `buildPreviewEmbedConfig`.

## 19. Save path regression result

`popupHtmlSerializer.test.ts` — **PASS** (no changes to save `popupId` scope)

## 20. Storefront regression result

No storefront code changed; save artifact contract unchanged — **PASS** (by design)

## 21. Thumbnail regression result

`popupThumbnailArtifact.browser.test.ts` — **PASS**

## 22. Multi-step regression result

`popupHtmlSerializer.test.ts` multi-step cases — **PASS**

## 23. `!important` added

**NO**

## 24. Compatibility branches added

**NO**

## 25. RTE production code changed

**NO**

## 26. Other block families migrated

**NO**

## 27. Tests passed/failed

| Suite | Result |
|---|---|
| `migratedWrapperLayoutExport.test.ts` | **PASS** (9) |
| `previewArtifactScopeParity.test.ts` | **PASS** (4) |
| `phoneInputPresentationRenderCss.test.ts` | **PASS** (+ G2F gate) |
| `serializedTeaserPreviewMount.test.ts` | **PASS** |
| `phoneInputPresentation.browser.test.ts` | **PASS** (+ G2F Chromium margin) |
| `popupHtmlSerializer.test.ts` | **PASS** |
| `renderArtifactCssLifecycle.test.ts` | **PASS** |
| `popupProductionCssRteOwnership.audit.test.ts` | **FAIL** (2 pre-existing RTE text-align) |
| `popupEmbedRuntime.test.ts` | **FAIL** (4 pre-existing) |

## 28. Remaining known CSS architecture risks

- Text RTE block-level `font-size`/`color` vs renderCss (G2B — out of scope)
- 10 unmigrated families with PCL-008 hybrid (Phase 6H+ migrations)
- `useCanvasTeaser` React overlay does not use serialized teaser HTML (documented valid difference)
- Element `style.customCss` persisted but not exported

## 29. Ready for Phase 6H Checkbox migration

**YES**

## 30. Final verdict

**ACCEPT** — Both narrow defects fixed with focused regression gates; no scope creep.

---

## Files changed

| File | Change |
|---|---|
| `popupHtmlSerializer/migratedWrapperLayoutOwnership.ts` | **NEW** — canonical wrapper strip registry |
| `popupHtmlSerializer/serializeElements.ts` | Use shared strip helper; includes phone-input |
| `serializedTeaserPreviewMount.ts` | Parameterized `artifactScopeId`; default `pbn-preview` |
| `formPreviewEmbedPresentation.ts` | Pass main artifact scope to teaser HTML |
| `SerializedTeaserPreviewCanvas.tsx` | Pass `PREVIEW_SPIN_OUTCOME_POPUP_ID` |
| `__tests__/migratedWrapperLayoutExport.test.ts` | **NEW** — Gate A + phone negative inline |
| `__tests__/previewArtifactScopeParity.test.ts` | **NEW** — Gate B |
| `__tests__/phoneInputPresentationRenderCss.test.ts` | Gate C phone wrapper |
| `__tests__/phoneInputPresentation.browser.test.ts` | Chromium wrapper margin |
| `__tests__/serializedTeaserPreviewMount.test.ts` | Updated scope expectations |
