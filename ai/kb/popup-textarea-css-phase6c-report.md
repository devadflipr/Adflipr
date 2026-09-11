# Popup Textarea CSS — Phase 6C Report

**Status:** Implemented  
**Date:** 2026-07-05  
**Scope:** `textarea` — dedicated compiler with shared canonical normalization  
**Compiler:** `textareaPresentation.ts`

---

## A. Executive summary

Phase 6C migrated **textarea** merchant presentation into artifact-scoped `renderCss` using **Option B — dedicated Textarea compiler with shared canonical normalization**. Stage 1 proved textarea shares Standard Field trait semantics for shell chrome, label, focus, and placeholder, but **height/min-height ownership differs**: height belongs on native `<textarea>`, not the outline shell.

Split-target responsive defects (padding/border-radius on outline vs wrapper; font-size on field vs wrapper) are fixed via diff-only mobile rules on correct owners. `textarea` is removed from `buildBlockTraitResponsiveCss`. Inline merchant presentation is stripped from serialized HTML. No permanent hybrid, no `!important` for migrated presentation, no backward-compatibility branches.

**Phase 0 regression:** 18 migrated green / 0 red / 5 controls green — **unchanged (PASS)**.

---

## B. Stage 1 ownership gate

| Gate | Result |
| ---- | ------ |
| A — Pipeline trace | Complete (see §C) |
| B — Serialized DOM | Proven from `serialize.ts` + export tests |
| C — Height ownership | **B — native `<textarea>`** (not outline) |
| D — Standard Field comparison | Shared normalization; separate compiler |
| E — Split-target defects | Confirmed pre-migration (same pattern as 6B) |
| F — Placeholder | Artifact-scoped `textarea::placeholder` in renderCss |
| G — Focus | Outline `:focus-within` + merchant vars in renderCss |
| H — Validation/error | Runtime only; unchanged global error CSS |

**Stage 1 decision: B — Dedicated Textarea compiler with shared canonical normalization**

---

## C. Exact Textarea pipeline

| Stage | File | Function / component |
| ----- | ---- | -------------------- |
| Model | `types.ts` | `FormElement` with `type: 'textarea'` |
| Trait UI | `TextAreaProperties.tsx` | Style traits (height, padding, label, focus, etc.) |
| Canvas | `TextareaCanvas.tsx` | `buildCanvasTextareaShellStyle` + `resolveTextareaFieldCssRules` |
| Canvas field | `PbnCanvasTextareaField.tsx` | Native `<Textarea>` with canonical field rules |
| Style helpers | `ElementStyles.ts` | `resolveTextareaOutlineCssRules`, `resolveTextareaFieldCssRules`, `resolveTextareaShellHeightPx` |
| Canvas shell adapter | `buildCanvasInputShellStyles.ts` | `buildCanvasTextareaShellStyle` |
| Serializer | `blocks/textarea/serialize.ts` | `serializeTextarea` — structure-only HTML |
| Wrapper strip | `serializeElements.ts` | Deletes wrapper margin/width for `textarea` |
| renderCss compiler | `textareaPresentation.ts` | `compileTextareaPresentationRenderCss` |
| Composition | `compilePopupRenderCss.ts` | Wires textarea compiler into artifact CSS |
| Generic responsive | `responsiveDeviceCss.ts` | `buildBlockTraitResponsiveCss` — **skips textarea** |
| Placeholder (export) | `floating-label.export.css` | Global `textarea::placeholder` **removed** |
| Validation | `formValidation.ts` | `textarea[required]` query + focus — **runtime only** |

---

## D. Exact serialized DOM

```text
[data-block-type="textarea"]
[data-adflipr-block-id="{id}"]              wrapper (identity + layout hooks only)
├── <label>                                  static label (structure)
└── .pbn-canvas-input-shell                  outline (class only, no inline merchant CSS)
    .pbn-canvas-input-shell--textarea
    └── <textarea>                             native field (rows, placeholder, validation attrs)
```

**No floating-label mode** for textarea — standard static label only.

---

## E. Owner matrix

| Concern | Owner | Selector |
| ------- | ----- | -------- |
| Identity | wrapper | `[data-adflipr-popup-artifact][data-adflipr-block-id]` |
| Wrapper layout | wrapper | same |
| Outline chrome | outline | `.pbn-canvas-input-shell` |
| Field typography | native `<textarea>` | `.pbn-canvas-input-shell textarea` |
| Height / min-height | native `<textarea>` | same |
| Resize / overflow | native `<textarea>` | `resize:none` on field |
| Static label | `> label` | direct child label |
| Placeholder paint | `textarea::placeholder` | artifact-scoped |
| Focus presentation | outline `:focus-within` | border-color + box-shadow via CSS vars |
| Validation/error | runtime | `pbn-export-field-error-shell`, `aria-invalid` — **not migrated** |

---

## F. Height ownership decision

| Trait | Model field | Canvas target | Serializer target | Desktop owner | Mobile owner | Pre-migration inline | Pre-migration responsive |
| ----- | ----------- | ------------- | ------------------- | ------------- | ------------ | -------------------- | ----------------------- |
| `height` | `style.height` | `<textarea>` via `resolveTextareaFieldCssRules` | was inner shell inline | field | field (was wrapper mobile) | field/shell inline | wrapper mobile (**defect**) |
| `min-height` | derived from `height` or `rows` | `<textarea>` | field | field | field | field | N/A |
| `rows` | `style.rows` | HTML `rows` attr + min-height fallback | `<textarea rows>` | structural | structural | N/A | N/A |
| `resize` | fixed | field `resize:none` | field | field | field | inline | N/A |
| `overflow` | browser default | native | native | runtime | runtime | N/A | N/A |

**Answer: B — height belongs to native `<textarea>`, not outline shell.**

Evidence: `resolveTextareaOutlineCssRules` explicitly omits height; `resolveTextareaFieldCssRules` emits `height` + `minHeight`; Chromium test proves field `180px` with outline `204px` when outline has `12px` padding top/bottom.

---

## G. Standard Field reuse decision

| Concern | vs text-input/email-input | Classification |
| ------- | ------------------------- | -------------- |
| Wrapper margin/width | Same `getExportWrapperStyleFromLayout` | **IDENTICAL — reuse** |
| Outline padding/bg/border/radius/shadow | Same `resolveInputShellChromeRules` | **IDENTICAL — reuse** |
| Focus presentation | Same `focusBorderColor` + `:focus-within` on outline | **IDENTICAL — reuse vars** |
| Field typography | Same font traits; different DOM tag | **SIMILAR — shared normalization, different selector** |
| Placeholder | Same `placeholderColor` trait | **IDENTICAL — artifact-scoped `::placeholder`** |
| Label | Static label only (no floating) | **IDENTICAL — `resolveStandardFieldStaticLabelCssRules`** |
| Height | Standard Field on **outline**; Textarea on **field** | **SIMILAR PROPERTY, DIFFERENT OWNER** |
| min-height | Outline vs field | **DIFFERENT OWNER** |
| resize | N/A on single-line input | **TEXTAREA-SPECIFIC** |
| Validation | Same runtime architecture | **RUNTIME — unchanged** |

**Decision: B — Dedicated Textarea compiler using shared canonical field resolvers**

Not A (extend Standard Field compiler) because height ownership on outline would be incorrect for textarea DOM.

---

## H. Split-target defects before migration

| Property | Desktop owner | Mobile owner (generic responsive) | Classification |
| -------- | ------------- | --------------------------------- | -------------- |
| padding | outline (inline) | wrapper (generic responsive) | **SPLIT TARGET — OWNERSHIP DEFECT** |
| border-radius | outline (inline) | wrapper (generic responsive) | **SPLIT TARGET — OWNERSHIP DEFECT** |
| font-size | field (inline) | wrapper (generic responsive) | **SPLIT TARGET — OWNERSHIP DEFECT** |
| margin | wrapper (inline) | wrapper | **SAME OWNER — SAFE** |
| box-shadow | outline | wrapper (if diff) | **SPLIT TARGET — OWNERSHIP DEFECT** |
| width | wrapper | wrapper | **SAME OWNER — SAFE** |
| height | field | wrapper (if emitted) | **SPLIT TARGET — OWNERSHIP DEFECT** |

All fixed post-migration: mobile rules target same owners as desktop in `compileTextareaPresentationRenderCss`.

---

## I. Canonical normalization

### Reused from Phase 6B

| Resolver | Usage |
| -------- | ----- |
| `resolveStandardFieldStaticLabelCssRules` | Static label typography/color |
| `resolveInputShellChromeRules` (private) | Outline padding, border, radius, shadow, background |
| `resolveInputPlaceholderColor` | `--pbn-canvas-input-placeholder` CSS var on outline |
| `getExportWrapperStyleFromLayout('textarea', ...)` | Wrapper margin/width |
| `resolveInputFontSizePx` | Field font-size desktop/mobile |
| `resolveDevicePaddingCss` / `resolveCornerRadiusCss` / `resolveDeviceBoxShadowCss` | Device-paired traits |

### Textarea-specific

| Resolver | Purpose |
| -------- | ------- |
| `resolveTextareaShellHeightPx` | Height trait or rows-derived fallback |
| `resolveTextareaOutlineCssRules` | Shell chrome + structural flex; **no height** |
| `resolveTextareaFieldCssRules` | Typography + height/min-height + resize:none on `<textarea>` |
| `TEXTAREA_OUTLINE_STRUCTURAL_RULES` | `display:flex`, `alignItems:stretch`, `width:100%` |

---

## J. Properties migrated

**Wrapper:** margin, width, max-width, min-width, alignSelf, boxSizing  
**Outline:** padding, background, border, border-radius, box-shadow, focus CSS vars  
**Field:** font-family, font-size, font-weight, color, line-height, letter-spacing, text-align, height, min-height, resize  
**Label:** color, font-family, font-size, font-weight, display, spacing  
**Placeholder:** opacity, color via `textarea::placeholder`  
**Focus:** border-color, box-shadow on outline `:focus-within`

---

## K. Responsive strategy

```text
base artifact-scoped rules (desktop values)
+
@media only screen and (max-width: 480px) { DIFF-ONLY mobile overrides }
```

Mobile diff emitted per owner:

- wrapper: margin/width deltas
- outline: padding, border-radius, box-shadow deltas
- field: font-size, height/min-height deltas (when traits differ)

No topology switch for textarea. No `!important`.

---

## L. Placeholder ownership

| Source | Owner |
| ------ | ----- |
| Placeholder text | HTML `placeholder` attribute (content) |
| `placeholderColor` trait | `--pbn-canvas-input-placeholder` on outline + artifact `textarea::placeholder` rule |
| Global `textarea::placeholder` in `floating-label.export.css` | **Removed** |
| Builder canvas `popup-builder.css` | Canvas-only; export uses renderCss |

**Architecture:** state/pseudo `::placeholder` + merchant value in artifact-scoped renderCss.

---

## M. Focus ownership

| Item | Value |
| ---- | ----- |
| State selector | `.pbn-canvas-input-shell:focus-within` |
| Painted owner | outline shell |
| Merchant value | `focusBorderColor` trait → `--pbn-canvas-input-focus` on outline |
| Focus rules in renderCss | `border-color` + `box-shadow: 0 0 0 1px var(--pbn-canvas-input-focus)` |
| Global dependency | None for migrated textarea |
| `!important` | **None** |

Reuses same `focusBorderColor` normalization as Standard Field.

---

## N. Validation/error ownership

| Item | Owner |
| ---- | ----- |
| Required field detection | `formValidation.ts` — `textarea[required]` |
| Error shell class | `pbn-export-field-error-shell` / `pbn-canvas-preview-field-error-shell` |
| Error text | Runtime insertion |
| `aria-invalid` | Runtime |
| Global error CSS | `embed-responsive.export.css` — **kept** (shared runtime infrastructure) |

**Boundary:** Merchant error presentation not migrated; runtime validation behavior unchanged.

---

## O. Inline CSS removed

From serialized export HTML for `textarea`:

- Wrapper: margin, width (moved to renderCss)
- Outline shell: no `style=""` attribute
- `<textarea>`: no `style=""` attribute
- Label: structure only (presentation in renderCss when label enabled)

Verified by `popupHtmlSerializer.test.ts` and `textareaPresentationRenderCss.test.ts`.

---

## P. Embedded CSS removed

| Location | Change |
| -------- | ------ |
| `buildBlockTraitResponsiveCss` | `textarea` added to `MIGRATED_BLOCK_TRAIT_TYPES` — zero generic trait rules |
| `floating-label.export.css` | Removed global `textarea::placeholder` (kept `input::placeholder` for unmigrated families) |

Builder canvas CSS (`popup-builder.css`) retains canvas editing helpers — not export merchant ownership.

---

## Q. Generic responsive cleanup

**Proof:** `textareaPresentationRenderCss.test.ts` — `buildEmbeddedStepResponsiveCss` output does not contain `[data-adflipr-block-id="ta-1"]` for textarea with padding mobile diff.

**Generic responsive merchant rules for textarea: zero**

---

## R. Multi-step behavior

| Case | Behavior |
| ---- | -------- |
| Same block id + same normalized presentation | Emit once; `stepIds` merged |
| Same block id + conflicting presentation | `TextareaPresentationConflictError` with `blockId` + sorted `stepIds` |
| Ordering | Block ids sorted deterministically (`localeCompare`) |

Tested in `textareaPresentationRenderCss.test.ts`.

---

## S. Before/after HTML

### Before (pre-6C)

```html
<div data-block-type="textarea" data-adflipr-block-id="ta-1" style="margin-bottom:16px;...">
  <label style="color:#6b7280;font-size:14px">Notes</label>
  <div class="pbn-canvas-input-shell" style="padding:12px;background:#f0f0f0;border-radius:8px;...">
    <textarea style="min-height:180px;height:180px;font-size:16px;..." rows="4" placeholder="Message"></textarea>
  </div>
</div>
```

### After (post-6C)

```html
<div data-block-type="textarea" data-adflipr-block-id="ta-1" style="flex-shrink:0;...">
  <label>Notes</label>
  <div class="pbn-canvas-input-shell pbn-canvas-input-shell--textarea">
    <textarea rows="4" placeholder="Message" name="..." ></textarea>
  </div>
</div>
```

Merchant presentation in `renderCss` only.

---

## T. Generated renderCss shape

```css
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="ta-1"]{margin-bottom:16px;...}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="ta-1"] .pbn-canvas-input-shell{padding:12px;...;--pbn-canvas-input-focus:#4f46e5;--pbn-canvas-input-placeholder:#9ca3af}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="ta-1"] .pbn-canvas-input-shell textarea{font-size:16px;min-height:180px;height:180px;resize:none;...}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="ta-1"] > label{color:#6b7280;font-size:14px;...}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="ta-1"] .pbn-canvas-input-shell:focus-within{border-color:var(--pbn-canvas-input-focus);box-shadow:0 0 0 1px var(--pbn-canvas-input-focus)}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="ta-1"] .pbn-canvas-input-shell textarea::placeholder{opacity:1;color:var(--pbn-canvas-input-placeholder)}
@media only screen and (max-width: 480px){
  [data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="ta-1"] .pbn-canvas-input-shell{padding:4px;...}
  [data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="ta-1"] .pbn-canvas-input-shell textarea{font-size:12px;...}
}
```

---

## U. Chromium computed-style evidence

| Property | Selector | Desktop expected | Desktop actual | Mobile expected | Mobile actual | Owner |
| -------- | -------- | ---------------- | -------------- | --------------- | ------------- | ----- |
| paddingTop | outline | 12px | 12px | 4px | 4px | outline |
| paddingTop | wrapper | 0px | 0px | — | — | wrapper |
| fontSize | textarea field | 16px | 16px | 12px | 12px | field |
| borderTopLeftRadius | outline | 8px | 8px | 4px | 4px | outline |
| height | textarea field | 180px | 180px | — | — | field |
| height | outline (with 12px pad) | 204px | 204px | — | — | outline (content-sized, no height trait) |
| borderTopColor (focused) | outline | rgb(17,34,51) | matches | — | — | outline :focus-within |

Source: `textareaPresentation.browser.test.ts` — **PASS**

---

## V. Phase 0 regression contract

```
18 migrated green
0 red
5 controls green
```

Source: `responsiveCssBaseline.browser.test.ts` — **PASS** (run with Phase 6C suite)

---

## W. Standard Field non-regression

`standardFieldPresentationRenderCss.test.ts` + `standardFieldPresentation.browser.test.ts` — **PASS**

---

## X. Phone Input non-regression

Phone input test suite — **PASS** (included in regression run)

---

## Y. Consumer completeness

`compilePopupRenderCss` composes `compileTextareaPresentationRenderCss` automatically. No consumer-specific wiring added.

Consumers receiving `renderCss` via established `CompiledPopupArtifact` contract:

| Consumer | Path | Change required |
| -------- | ---- | ----------------- |
| Storefront embed | `embed/runtime.ts` + `mountRenderArtifactCss.ts` | **None** |
| Builder preview | export serialization | **None** |
| Fullscreen preview | export serialization | **None** |
| Detail thumbnail | `metadata.renderCss` | **None** |
| Campaign list thumbnail | paired `thumbnailRenderCss` | **None** |

**PASS**

---

## Z. Obsolete production code deleted

| Item | Action |
| ---- | ------ |
| `buildTextareaInnerStyle` in `inputFieldStyles.ts` | **Deleted** (zero imports) |
| Textarea inline merchant CSS in `serialize.ts` | **Removed** |
| Global `textarea::placeholder` in `floating-label.export.css` | **Removed** |

---

## AA. Remaining Textarea CSS debt

**Canvas builder CSS** (`popup-builder.css`): structural canvas helpers for `.pbn-canvas-input-shell--textarea` and `.pbn-canvas-textarea-field::placeholder` — canvas editing surface, not export merchant ownership. Acceptable until canvas fully adapter-driven.

**No export merchant presentation debt.**

---

## AB. Remaining buildBlockTraitResponsiveCss consumers

Verified from `blockRegistry.ts` + `MIGRATED_BLOCK_TRAIT_TYPES` in `responsiveDeviceCss.ts`:

```text
divider
phone-input
dropdown
check-box
radio
date
timer
spinner
image
video
product
coupon
feedback
social
```

**Count: 14 types**

---

## AC. Repository search evidence

| Pattern | Classification |
| ------- | -------------- |
| `textareaPresentation.ts` / `compileTextareaPresentationRenderCss` | **MIGRATED COMPILER** |
| `resolveTextareaOutlineCssRules` / `resolveTextareaFieldCssRules` | **CANONICAL NORMALIZATION** |
| `TextareaCanvas.tsx` / `PbnCanvasTextareaField.tsx` | **CANVAS ADAPTER** |
| `serialize.ts` (textarea) | **STRUCTURE-ONLY SERIALIZER** |
| `formValidation.ts` textarea selectors | **RUNTIME BEHAVIOR** |
| `TextAreaProperties.tsx` focusBorderColor | **TRAIT UI** (not CSS ownership) |
| `inputOutlineStyle` / `innerInputFieldStyle` in `inputFieldStyles.ts` | **UNMIGRATED FAMILY** (phone, dropdown) |
| `buildFieldLabelStyle` | **UNMIGRATED FAMILY** (dropdown label inline) |
| `floating-label.export.css` `input::placeholder` | **UNMIGRATED FAMILY** hook |
| `popup-builder.css` textarea focus/placeholder | **CANVAS ADAPTER** |
| `buildBlockTraitResponsiveCss` | **GENERIC RESPONSIVE** (14 remaining types) |
| `buildTextareaInnerStyle` | **DELETED (was OBSOLETE)** |

---

## AD. Architecture gates

| Gate | Result |
| ---- | ------ |
| Forward-only / no hybrid | **PASS** |
| No `!important` for migrated presentation | **PASS** |
| Height on correct owner | **PASS** |
| Generic responsive zero for textarea | **PASS** |
| Phase 0 unchanged | **PASS** |
| Consumer contract unchanged | **PASS** |

---

## AE. Test evidence

| Suite | Result |
| ----- | ------ |
| `textareaPresentationRenderCss.test.ts` (8 tests) | **PASS** |
| `textareaPresentation.browser.test.ts` (5 tests) | **PASS** |
| `popupHtmlSerializer.test.ts` (textarea height case) | **PASS** |
| `responsiveCssBaseline.browser.test.ts` (Phase 0) | **PASS** |
| `standardFieldPresentationRenderCss.test.ts` | **PASS** |
| `standardFieldPresentation.browser.test.ts` | **PASS** |
| Phone input regression | **PASS** |

**Total Phase 6C regression run: 103 tests PASS**

---

## Files changed

| File | Role |
| ---- | ---- |
| `textareaPresentation.ts` | New dedicated compiler |
| `ElementStyles.ts` | Textarea canonical resolvers |
| `blocks/textarea/serialize.ts` | Structure-only HTML |
| `serializeElements.ts` | Strip wrapper merchant CSS |
| `compilePopupRenderCss.ts` | Wire compiler |
| `responsiveDeviceCss.ts` | Add `textarea` to migrated set |
| `buildCanvasInputShellStyles.ts` | Canvas outline adapter |
| `TextareaCanvas.tsx` / `PbnCanvasTextareaField.tsx` | Canvas field adapter |
| `floating-label.export.css` | Remove global textarea placeholder |
| `inputFieldStyles.ts` | Delete obsolete `buildTextareaInnerStyle` |
| `textareaPresentationRenderCss.test.ts` | Unit tests |
| `textareaPresentation.browser.test.ts` | Chromium tests |
| `popupHtmlSerializer.test.ts` | Updated textarea height assertion |
