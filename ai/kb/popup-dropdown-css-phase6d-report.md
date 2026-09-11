# Popup Dropdown CSS — Phase 6D Report

**Status:** Implemented  
**Date:** 2026-07-05  
**Scope:** `dropdown` — dedicated compiler with shared canonical normalization  
**Compiler:** `dropdownPresentation.ts`

---

## A. Executive summary

Phase 6D migrated **dropdown** merchant presentation into artifact-scoped `renderCss` using **Option B — dedicated Dropdown compiler with shared canonical normalization**. Stage 1 proved dropdown shares Standard Field outline/label/focus semantics but uses native `<select>` for selected-value typography; height/min-height coordination follows the **Standard Field outline pattern** (not Textarea field pattern).

Split-target responsive defects are fixed via diff-only mobile rules on correct owners. `dropdown` is removed from `buildBlockTraitResponsiveCss`. Native browser arrow preserved; no `::placeholder` CSS (native select limitation documented). No permanent hybrid, no `!important` for migrated presentation.

**Phase 0 regression:** 18 migrated green / 0 red / 5 controls green — **unchanged (PASS)**.

---

## B. Stage 1 ownership gate

| Gate | Result |
| ---- | ------ |
| A — Pipeline trace | Complete (see §C) |
| B — Serialized DOM | Proven from `serialize.ts` + export tests |
| C — Painted owner matrix | Complete (see §E) |
| D — Standard Field / Textarea comparison | Shared normalization; separate compiler |
| E — Split-target defects | Confirmed pre-migration |
| F — Height ownership | **A — outline shell** (Standard Field pattern) |
| G — Placeholder / empty-state | Model metadata only; no reliable CSS pseudo |
| H — Native arrow | **A — browser-native** |
| I — Native appearance | Browser-default; no custom `appearance:none` on export |
| J — Focus | Outline `:focus-within` |
| K — Disabled | Native `disabled` attribute; no merchant traits |
| L — Validation/error | Runtime only |

**Stage 1 decision: B — Dedicated Dropdown compiler with shared canonical normalization**

---

## C. Exact Dropdown pipeline

| Stage | File | Function / component |
| ----- | ---- | -------------------- |
| Model | `types.ts` | `FormElement` with `type: 'dropdown'` |
| Trait UI | `DropdownProperties.tsx` | Style traits + options editor |
| Canvas | `DropdownCanvas.tsx` | `buildCanvasDropdownShellStyle` + `resolveDropdownSelectCssRules` |
| Style helpers | `ElementStyles.ts` | `resolveDropdownOutlineCssRules`, `resolveDropdownSelectCssRules` |
| Canvas shell adapter | `buildCanvasInputShellStyles.ts` | `buildCanvasDropdownShellStyle` |
| Serializer | `blocks/dropdown/serialize.ts` | `serializeDropdown` — structure-only HTML |
| Wrapper strip | `serializeElements.ts` | Deletes wrapper margin/width for `dropdown` |
| renderCss compiler | `dropdownPresentation.ts` | `compileDropdownPresentationRenderCss` |
| Composition | `compilePopupRenderCss.ts` | Wires dropdown compiler |
| Generic responsive | `responsiveDeviceCss.ts` | **skips dropdown** |
| Validation | `formValidation.ts` | `select[required]` + focus — runtime only |

---

## D. Exact serialized DOM

```text
[data-block-type="dropdown"]
[data-adflipr-block-id="{id}"]              wrapper (identity + layout hooks only)
├── <label for="{fieldName}">               static label (structure)
└── .pbn-canvas-input-shell                 outline (class only)
    .pbn-canvas-dropdown-field
    └── <select name="...">                 native field
        ├── <option value="..." selected>   option content
        └── <option value="...">            option content
```

**No custom arrow markup.** Browser-native select chevron only.

**No floating-label mode** for dropdown.

---

## E. Owner matrix

| Concern | Owner | Selector |
| ------- | ----- | -------- |
| Identity | wrapper | `[data-adflipr-popup-artifact][data-adflipr-block-id]` |
| Wrapper layout | wrapper | same |
| Outline chrome + shell height | outline | `.pbn-canvas-input-shell` |
| Selected-value typography | native `<select>` | `.pbn-canvas-input-shell select` |
| Option content | `<option>` elements | HTML content |
| Static label | `> label` | direct child label |
| Placeholder text trait | model only | **not serialized as empty option** |
| Placeholder color trait | **unsupported for native select** | documented limitation |
| Arrow | browser-native | no merchant CSS |
| Focus presentation | outline `:focus-within` | border-color + box-shadow via CSS vars |
| Disabled | native `disabled` | browser-default |
| Validation/error | runtime | shared error shell CSS |

---

## F. Standard Field / Textarea comparison

| Concern | Standard Field | Textarea | Dropdown | Classification |
| ------- | -------------- | -------- | -------- | -------------- |
| Wrapper margin/width | wrapper | wrapper | wrapper | **IDENTICAL — reuse** |
| Outline chrome | outline | outline | outline | **IDENTICAL — reuse `resolveStandardFieldOutlineCssRules`** |
| Height/min-height | outline | native field | outline + select min-height | **SIMILAR to Standard Field** |
| Field typography | `input` | `textarea` | `select` | **SAME TRAIT, DIFFERENT OWNER** |
| Label | static `label` | static `label` | static `label` | **IDENTICAL — reuse** |
| Focus | outline `:focus-within` | outline `:focus-within` | outline `:focus-within` | **IDENTICAL** |
| Placeholder | `input::placeholder` | `textarea::placeholder` | options only | **DROPDOWN-SPECIFIC — no pseudo CSS** |
| Arrow | N/A | N/A | browser-native | **NATIVE BROWSER BEHAVIOR** |

**Not A (Standard Field compiler directly):** field selector is `select`, not `input`; no placeholder pseudo-element.

---

## G. Architecture decision

**B — Dedicated Dropdown compiler with shared canonical normalization**

---

## H. Split-target defects before migration

| Property | Desktop owner | Mobile owner (generic responsive) | Classification |
| -------- | ------------- | --------------------------------- | -------------- |
| padding | outline (inline) | wrapper | **SPLIT TARGET — OWNERSHIP DEFECT** |
| border-radius | outline (inline) | wrapper | **SPLIT TARGET — OWNERSHIP DEFECT** |
| font-size | select (inline) | wrapper | **SPLIT TARGET — OWNERSHIP DEFECT** |
| margin | wrapper | wrapper | **SAME OWNER — SAFE** |
| box-shadow | outline | wrapper (if diff) | **SPLIT TARGET — OWNERSHIP DEFECT** |
| min-height | outline + select | wrapper (if emitted) | **SPLIT TARGET — OWNERSHIP DEFECT** |

All fixed post-migration.

---

## I. Height ownership decision

| Trait | Canvas | Serializer (pre) | Owner |
| ----- | ------ | ---------------- | ----- |
| `height` | outline via `resolveStandardFieldOutlineCssRules` | outline inline | **outline** |
| `min-height` | outline + select coordinated | outline + select inline | **outline + select** |
| padding-driven height | outline padding + select min-height | same | coordinated |

**Answer: A — outline shell owns shell height (Standard Field pattern).**

Select receives coordinated `min-height` for readable selected-value box; structural `flex: 1 1 auto` for stretch inside outline.

---

## J. Placeholder / empty-option semantics

| Item | Finding |
| ---- | ------- |
| `style.placeholder` / `properties.placeholder` | Stored in model; edited in trait UI |
| Serialized DOM | **Not emitted** as `<option value="">` — only configured `options[]` serialize |
| Empty-state representation | First selected option or merchant-configured options |
| `placeholderColor` trait | Exists in defaults but **no `select::placeholder` CSS** — native select cannot express separate placeholder paint reliably |
| Decision | **Do not emit placeholder pseudo CSS**; preserve option content + validation behavior |

---

## K. Native arrow ownership

**A — Preserve native arrow completely**

No custom arrow markup, background-image, or pseudo-element in export or canvas. Browser renders native chevron.

---

## L. Native appearance behavior

| Property | Classification |
| -------- | -------------- |
| `appearance` | **NATIVE BROWSER BEHAVIOR** — not overridden on export select |
| `-webkit-appearance` | **NATIVE BROWSER BEHAVIOR** |
| `cursor: pointer` on select | **ARTIFACT STRUCTURE** — in `resolveDropdownSelectCssRules` |
| `background: transparent` on select | **ARTIFACT STRUCTURE** — shell owns background |
| Option rendering | **NATIVE BROWSER BEHAVIOR** |

---

## M. Canonical normalization

### Reused

| Resolver | Usage |
| -------- | ----- |
| `resolveStandardFieldOutlineCssRules(..., 'standard')` | Via `resolveDropdownOutlineCssRules` |
| `resolveStandardFieldValueCssRules(..., 'standard')` | Typography base inside select rules |
| `resolveStandardFieldStaticLabelCssRules` | Static label |
| `resolveInputShellMinHeightPx` | Select min-height coordination |
| `getExportWrapperStyleFromLayout('dropdown', ...)` | Wrapper margin/width |

### Dropdown-specific

| Resolver | Purpose |
| -------- | ------- |
| `resolveDropdownOutlineCssRules` | Thin alias to Standard Field standard outline |
| `resolveDropdownSelectCssRules` | Select structural + typography + min-height + cursor |
| `DROPDOWN_SELECT_STRUCTURAL_RULES` | flex, transparent bg, borderless select |

---

## N. Properties migrated

**Wrapper:** margin, width, max-width, min-width, alignSelf  
**Outline:** padding, background, border, border-radius, box-shadow, height, min-height, focus CSS vars  
**Select:** font-family, font-size, font-weight, color, line-height, letter-spacing, text-align, padding, min-height, cursor  
**Label:** color, font-family, font-size, font-weight, spacing  
**Focus:** border-color, box-shadow on outline `:focus-within`

**Not migrated:** placeholder color (unsupported), arrow (native), disabled paint (native)

---

## O. Responsive strategy

Base artifact-scoped desktop rules + `@media (max-width: 480px)` diff-only per owner (wrapper, outline, select). No topology switch. No `!important`.

---

## P. Focus ownership

| Item | Value |
| ---- | ----- |
| State selector | `.pbn-canvas-input-shell:focus-within` |
| Painted owner | outline shell |
| Merchant value | `focusBorderColor` → `--pbn-canvas-input-focus` |
| renderCss rules | `border-color` + `box-shadow: 0 0 0 1px var(--pbn-canvas-input-focus)` |

---

## Q. Disabled-state ownership

Native `disabled` attribute semantics only. No merchant disabled-style traits. Browser-default opacity/cursor.

---

## R. Validation/error ownership

Shared runtime: `formValidation.ts` (`select[required]`), `pbn-export-field-error-shell`, `aria-invalid`, global `embed-responsive.export.css` error rules. **Not migrated.**

---

## S. Inline CSS removed

Export HTML: no `style=""` on wrapper merchant layout, outline shell, or `<select>`. Label structure-only.

---

## T. Embedded CSS removed

`dropdown` added to `MIGRATED_BLOCK_TRAIT_TYPES` — zero generic trait responsive rules.

---

## U. Generic responsive cleanup

**Proof:** `dropdownPresentationRenderCss.test.ts` — `buildEmbeddedStepResponsiveCss` does not contain `[data-adflipr-block-id="dd-1"]`.

**Generic responsive merchant rules for dropdown: zero**

---

## V. Canvas parity

`DropdownCanvas` delegates to `buildCanvasDropdownShellStyle` + `resolveDropdownSelectCssRules` — same trait interpretation as export compiler. Canvas uses additional utility classes (`pbn-canvas-input-inner`) for structural stretch; merchant values from canonical resolvers.

---

## W. Multi-step behavior

Same block id + same presentation → emit once. Conflicting presentation → `DropdownPresentationConflictError` with block id + step ids. Deterministic block-id ordering.

---

## X. Before/after HTML

### Before

```html
<div data-block-type="dropdown" data-adflipr-block-id="dd-1" style="margin:...">
  <label for="dd-1">Choose</label>
  <div class="pbn-canvas-input-shell" style="padding:12px;background:#f0f0f0;min-height:64px;...">
    <select style="font-size:48px;min-height:64px;padding:8px 12px;..." name="dd-1">
      <option value="option2" selected>Option 2</option>
    </select>
  </div>
</div>
```

### After

```html
<div data-block-type="dropdown" data-adflipr-block-id="dd-1" style="flex-shrink:0;...">
  <label for="dd-1">Choose</label>
  <div class="pbn-canvas-input-shell pbn-canvas-dropdown-field">
    <select name="dd-1">
      <option value="option2" selected>Option 2</option>
    </select>
  </div>
</div>
```

---

## Y. Generated renderCss shape

```css
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="dd-1"]{margin-bottom:16px;...}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="dd-1"] .pbn-canvas-input-shell{padding:12px;min-height:64px;--pbn-canvas-input-focus:#4f46e5;...}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="dd-1"] .pbn-canvas-input-shell select{font-size:48px;min-height:64px;padding:8px 12px;cursor:pointer;color:#112233;...}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="dd-1"] > label{color:#6b7280;...}
[data-adflipr-popup-artifact="scope"] [data-adflipr-block-id="dd-1"] .pbn-canvas-input-shell:focus-within{border-color:var(--pbn-canvas-input-focus);box-shadow:0 0 0 1px var(--pbn-canvas-input-focus)}
@media only screen and (max-width: 480px){...}
```

---

## Z. Chromium computed-style evidence

| Property | Selector | Desktop | Mobile | Owner |
| -------- | -------- | ------- | ------ | ----- |
| paddingTop | outline | 12px | 4px | outline |
| fontSize | select | 16px | 12px | select |
| borderTopLeftRadius | outline | 8px | 4px | outline |
| minHeight | outline (fontSize 48) | 64px | — | outline |
| color | select | rgb(34,51,68) | — | select |
| borderTopColor (focused) | outline | rgb(17,34,51) | — | outline |

Source: `dropdownPresentation.browser.test.ts` — **PASS**

---

## AA. Native select behavior regression

`wave3-3-blocks.test.tsx` — dropdown selection, registry canvas, option updates — **PASS**

---

## AB. Phase 0 regression contract

18 migrated green / 0 red / 5 controls green — **PASS**

---

## AC. Standard Field non-regression

**PASS**

---

## AD. Textarea non-regression

**PASS**

---

## AE. Phone Input non-regression

**PASS**

---

## AF. Consumer completeness

`compilePopupRenderCss` composes `compileDropdownPresentationRenderCss` automatically. No consumer wiring changes. **PASS**

---

## AG. Obsolete production code deleted

| Item | Action |
| ---- | ------ |
| Dropdown inline merchant CSS in `serialize.ts` | **Removed** |
| Redundant `applyInputHeightTraitToShell` in `buildDropdownFieldStyles` | **Removed** (outline resolver owns height) |
| Duplicate `resolveDropdown*` declarations in ElementStyles | **Removed** |

`buildDropdownFieldStyles` retained as thin canvas adapter delegating to canonical resolvers.

---

## AH. Remaining Dropdown CSS debt

**Canvas builder CSS** (`popup-builder.css`): `.pbn-canvas-input-inner` structural stretch + canvas focus `!important` for unmigrated canvas editing — acceptable canvas surface debt.

**Placeholder text trait** not serialized to DOM — product/content gap outside CSS migration scope.

---

## AI. Remaining buildBlockTraitResponsiveCss consumers

Verified — **13 types:**

```text
divider
phone-input
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

---

## AJ. Repository search evidence

| Pattern | Classification |
| ------- | -------------- |
| `dropdownPresentation.ts` | **MIGRATED COMPILER** |
| `resolveDropdownOutlineCssRules` / `resolveDropdownSelectCssRules` | **CANONICAL NORMALIZATION** |
| `DropdownCanvas.tsx` | **CANVAS ADAPTER** |
| `serialize.ts` (dropdown) | **STRUCTURE-ONLY SERIALIZER** |
| `buildDropdownFieldStyles` | **CANVAS ADAPTER** (thin) |
| `inputOutlineStyle` / `innerInputFieldStyle` | **UNMIGRATED FAMILY** (phone) |
| `buildFieldLabelStyle` | **UNMIGRATED FAMILY** (deprecated, phone paths) |
| `formValidation.ts` select | **RUNTIME BEHAVIOR** |
| `floating-label.export.css` `input::placeholder` | **UNMIGRATED FAMILY** hook |

---

## AK. Architecture gates

| Gate | Result |
| ---- | ------ |
| Forward-only / no hybrid | **PASS** |
| No `!important` for migrated presentation | **PASS** |
| Height on correct owner | **PASS** |
| Generic responsive zero for dropdown | **PASS** |
| Phase 0 unchanged | **PASS** |
| Native select semantics preserved | **PASS** |

---

## AL. Test evidence

| Suite | Result |
| ----- | ------ |
| `dropdownPresentationRenderCss.test.ts` (8 tests) | **PASS** |
| `dropdownPresentation.browser.test.ts` (6 tests) | **PASS** |
| `popupHtmlSerializer.test.ts` (dropdown case) | **PASS** |
| `responsiveCssBaseline.browser.test.ts` | **PASS** |
| `standardFieldPresentation*` | **PASS** |
| `textareaPresentation*` | **PASS** |
| `phoneInput` + `inputShellHeight` + `wave3-3` | **PASS** |

**Total regression run: 127 tests PASS**

---

## Files changed

| File | Role |
| ---- | ---- |
| `dropdownPresentation.ts` | New dedicated compiler |
| `ElementStyles.ts` | Dropdown canonical resolvers |
| `blocks/dropdown/serialize.ts` | Structure-only HTML |
| `serializeElements.ts` | Strip wrapper merchant CSS |
| `compilePopupRenderCss.ts` | Wire compiler |
| `responsiveDeviceCss.ts` | Add `dropdown` to migrated set |
| `buildCanvasInputShellStyles.ts` | `buildCanvasDropdownShellStyle` |
| `DropdownCanvas.tsx` | Canonical canvas adapter |
| `inputFieldStyles.ts` | Thin dropdown canvas adapter |
| `dropdownPresentationRenderCss.test.ts` | Unit tests |
| `dropdownPresentation.browser.test.ts` | Chromium tests |
| `popupHtmlSerializer.test.ts` | Updated dropdown assertions |
