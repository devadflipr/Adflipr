# Phase 6H — Checkbox Merchant Presentation Migration Report

**Date:** 2026-07-06  
**Verdict:** **ACCEPT**

---

## 1. Executive summary

Checkbox (`check-box`) merchant presentation migrated from hybrid **desktop inline + generic mobile `@media`** to **artifact-scoped `renderCss`** with **dedicated `checkbox.export.css`** for native square/checkmark mechanics. Runtime behavior (multi-select, validation, label click) unchanged. H3 audit assumptions revalidated against current code; no material drift.

---

## 2. Pre-implementation audit revalidation

| Audit claim | Current code | Match |
|---|---|---|
| Single family `check-box` | `registerBuilderBlocks.ts`, `serializeCheckboxGroup` | **YES** |
| Split-target PCL-008 | `check-box` ∉ `MIGRATED_BLOCK_TRAIT_TYPES` pre-change | **YES** |
| Inline group/label/accent | `check-box/serialize.ts` pre-change | **YES** |
| No checkbox sheet | No `checkbox.export.css` pre-change | **YES** |
| outlineColor/backgroundColor dead | Panel traits; not in export pre-change | **YES** — now wired via tokens |
| Dedicated compiler (not Radio) | Implemented `checkboxPresentation.ts` | **YES** |

---

## 3. Exact Checkbox family inventory

**One production family:** `check-box` only.

---

## 4. Exact exported DOM topology (post-migration)

```html
<div data-block-type="check-box" data-adflipr-block-id="{id}" style="flex-shrink:0" …>
  <label for="{fieldName}">{labelText}</label>
  <div class="pbn-export-native-checkbox-scope">
    <div class="pbn-display-checkbox-row">
      <input type="checkbox" class="pbn-display-native-checkbox" … />
      <label for="check-{id}-{optionId}">{option label}</label>
    </div>
  </div>
</div>
```

Embedded: `<style data-adflipr-popup-checkbox-css="true">` with `checkbox.export.css`.

---

## 5. DOM ownership matrix

| DOM node | Owner | Channel |
|---|---|---|
| Block wrapper | Identity + layout margin/width | renderCss |
| Group field label `> label` | Static label presentation | renderCss |
| `.pbn-export-native-checkbox-scope` | Group shell, layout, gap, tokens | renderCss |
| `.pbn-display-checkbox-row` | Row flex structure | checkbox.export.css |
| `input.pbn-display-native-checkbox` | Native appearance | checkbox.export.css + inherited vars |
| Option label | Typography | renderCss |

---

## 6. Migration boundary

```
MERCHANT PRESENTATION → renderCss (+ CSS vars on group)
REUSABLE STRUCTURE    → checkbox.export.css (row flex)
NATIVE MECHANICS      → checkbox.export.css
RUNTIME BEHAVIOR      → HTML attrs + formValidation.ts
```

---

## 7. Before architecture

- Desktop: inline wrapper, group, labels, `accent-color` on inputs
- Mobile: `buildBlockTraitResponsiveCss` on **wrapper** (split-target for padding/font-size)
- No renderCss compiler
- No native sheet

---

## 8. After architecture

- Desktop + mobile: `compileCheckboxPresentationRenderCss` → `CompiledPopupArtifact.renderCss`
- Structure-only export HTML + scope classes
- `checkbox.export.css` embedded per step
- `check-box` ∈ `MIGRATED_BLOCK_TRAIT_TYPES` and `MIGRATED_WRAPPER_LAYOUT_BLOCK_TYPES`

---

## 9. Canonical normalization functions

| Function | Source |
|---|---|
| `resolveCheckboxWrapperCssRules` | `checkboxPresentation.ts` |
| `resolveCheckboxGroupPresentationCssRules` | `ElementStyles.ts` |
| `resolveRadioOptionLabelCssRules` | `ElementStyles.ts` (shared semantics) |
| `resolveStandardFieldStaticLabelCssRules` | `ElementStyles.ts` |
| `resolveCheckboxColorTokenCssRules` | `ElementStyles.ts` |
| `normalizeCheckboxPresentation` | `checkboxPresentation.ts` |

---

## 10. Compiler topology

**A — Dedicated `checkboxPresentation.ts`** integrated via `compilePopupRenderCss()`.

---

## 11–15. Ownership summary

| Owner | Properties | Mobile strategy |
|---|---|---|
| Wrapper | margin, width, alignSelf | DIFF-ONLY |
| Group | padding, border, radius, bg, shadow, gap, layout, min-height, CSS vars | DIFF-ONLY |
| Group field label | color, font-size, etc. | NO MOBILE RULE |
| Option label | typography | DIFF-ONLY |
| Native control | appearance, checkmark | NO MOBILE RULE (vars not device-paired) |

---

## 16. Native-control sheet strategy

**C — dedicated `checkbox.export.css`** (H3 audit decision). G1 production contract updated.

---

## 17. Hostile-theme defense

`!important` on native sheet only (appearance, border, background) — **defends against external storefront CSS**, not internal inline repair.

---

## 18–21. Inline / responsive removal

| Removed | Retained |
|---|---|
| Group inline merchant CSS | `flex-shrink:0` on wrapper |
| Option label inline | Row structure in sheet |
| Input `accent-color` inline | `checked`, `name`, `value`, `id` attrs |
| Generic responsive for `check-box` | Device visibility CSS (shared) |

---

## 22–25. Multi-step behavior

- Same block id + same normalized presentation → emit once
- Conflict → `CheckboxPresentationConflictError`
- Key excludes options content/selection state

---

## 26. Runtime behavior preservation

Unchanged: multi-select toggles, shared `name`, label `for`, required validation (`formValidation.test.ts` PASS).

---

## 27. Saved artifact evidence

`serializePopupExport` tests prove:
- No merchant inline on group/labels/inputs
- `renderCss` contains padding, tokens, typography
- Embedded checkbox sheet marker in HTML

---

## 28–32. Computed-style evidence

`checkboxPresentation.browser.test.ts` — **8/8 PASS**:
- Group padding/gap desktop
- Group padding + option font-size mobile (same owners)
- Wrapper margin desktop/mobile (no inline)
- Unchecked outline, checked fill
- Horizontal layout
- Static group label
- Artifact isolation

---

## 33–34. Consumer completeness

Builder/fullscreen preview, thumbnails, embed, multi-step — unchanged consumer paths; artifact mount supplies `renderCss` + hoisted embedded sheets.

---

## 35. Phase 0 result

`responsiveCssBaseline.browser.test.ts` — **39/39 PASS** (no regression; Checkbox not added as risk case — migrated family).

---

## 36. G1 production contract result

`npm run test:export-css-production-contract` — **PASS** (checkbox sheet marker `.pbn-export-native-checkbox-scope`).

---

## 37. Obsolete production code deleted

- Merchant inline builders removed from `check-box/serialize.ts` export path
- `check-box` removed from generic responsive merchant branch

---

## 38. Existing unrelated debt

| Debt | Status |
|---|---|
| popupEmbedRuntime.test.ts (4) | STILL PRESENT |
| popupProductionCssRteOwnership (2 RTE) | STILL PRESENT |
| Text RTE block-level ownership | STILL PRESENT (deferred) |
| Phone flag-without-URL | STILL PRESENT |
| element style.customCss not exported | STILL PRESENT |

---

## 39. Architecture gate results

All 25 gates from Phase 6H spec — **PASS**.

---

## 40. Recommended next family

**Timer** — next P0 hybrid family in unmigrated queue (per G2D grouping).

---

## Files changed

| File | Change |
|---|---|
| `checkboxPresentation.ts` | **NEW** |
| `checkbox.export.css` | **NEW** |
| `check-box/serialize.ts` | Structure-only export |
| `ElementStyles.ts` | Checkbox tokens + group resolver |
| `compilePopupRenderCss.ts` | Wire compiler |
| `serializeLayout.ts` | Embed checkbox sheet |
| `migratedWrapperLayoutOwnership.ts` | Add `check-box` |
| `responsiveDeviceCss.ts` | Add `check-box` to migrated set |
| `popupEmbedContract.ts` | `POPUP_ATTR_STYLE_CHECKBOX` |
| `styles/sheets/index.ts`, `floatingLabelExportStyles.ts` | Export checkbox CSS |
| G1 fixture + verify script | Checkbox sheet gate |
| Tests | `checkboxPresentationRenderCss`, `checkboxPresentation.browser`, wrapper parity |
