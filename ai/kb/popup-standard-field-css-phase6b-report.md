# Popup Standard Field CSS — Phase 6B Report

**Status:** Implemented  
**Date:** 2026-07-05  
**Scope:** `text-input` + `email-input` — standard and floating label modes  
**Compiler:** `standardFieldPresentation.ts`

---

## A. Executive summary

Phase 6B completed **Option A — Complete Standard Field family migration**. Stage 1 proved standard and floating modes share the same CSS ownership model (wrapper / outline / field / label); floating adds behavior structure in embedded CSS, not different merchant owners.

Split-target responsive defects (padding, border-radius, font-size) are fixed via artifact-scoped `renderCss` with diff-only mobile rules on correct owners. `text-input` and `email-input` are removed from `buildBlockTraitResponsiveCss`. No permanent hybrid within these block types.

---

## B. Stage 1 architecture gate

| Gate | Result |
| ---- | ------ |
| A — Standard vs floating DOM ownership | **PARTIALLY same shape, SAME CSS owners** |
| B — One compiler for both modes | **YES** |
| C — floating-label.export.css audit | Merchant rules scoped out; structure/behavior retained |
| D — Focus ownership | **Migrated to renderCss** (`:focus-within` on outline) |
| E — Validation/error | **Runtime behavior unchanged**; hard-coded error CSS remains global |

**Stage 1 decision: A — COMPLETE FAMILY**

---

## C. Standard vs floating DOM ownership

### Standard label mode

```text
[data-block-type][data-adflipr-block-id]     wrapper
├── <label>                                   static label
└── .pbn-canvas-input-shell                   outline
    └── <input>                                 field
```

### Floating label mode

```text
[data-block-type][data-adflipr-block-id]     wrapper
└── .pbn-canvas-input-shell--floating         outline
    └── .pbn-floating-field                     structure wrap
        ├── <input.pbn-canvas-input-inner>      field
        └── <label.pbn-floating-label>          floating label
```

| Owner | Standard | Floating | Same merchant owner? |
| ----- | -------- | -------- | -------------------- |
| Wrapper layout | wrapper | wrapper | **Yes** |
| Shell chrome | outline | outline | **Yes** |
| Value typography | field `input` | field `.pbn-canvas-input-inner` | **Yes** (different selector) |
| Label typography | static `label` | `.pbn-floating-label` | **Yes** (different selector) |
| Focus presentation | outline `:focus-within` | outline `:focus-within` | **Yes** |
| Float animation | — | embedded structure CSS | Behavior only |

---

## D. floating-label.export.css classification

| Selector / rule | Classification | After migration |
| --------------- | -------------- | --------------- |
| `.pbn-floating-field` layout | ARTIFACT STRUCTURE | **Kept** |
| `.pbn-floating-label` transform/transition | BEHAVIOR STATE | **Kept** |
| `.pbn-floating-field:focus-within .pbn-floating-label` color | BEHAVIOR + merchant var | **Kept** (uses `--pbn-canvas-input-focus` from renderCss) |
| `.pbn-fanvas-input-shell--floating` generic padding/focus | MERCHANT (was) | **Removed** — phone-scoped only |
| `phone-input` focus-within `!important` | MERCHANT (phone) | **Kept phone-scoped** until Phone Compound phase |
| `input::placeholder` global | STRUCTURE hook | **Kept** for non-migrated families |
| `.pbn-floating-field input` padding animation | BEHAVIOR STATE | **Kept** |

---

## E. Final implementation scope decision

**A — Complete family:** `text-input`, `email-input`, standard + floating modes.

---

## F. Exact DOM owners

| Owner | Selector |
| ----- | -------- |
| Wrapper | `[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"]` |
| Outline | `… .pbn-canvas-input-shell` |
| Field (standard) | `… .pbn-canvas-input-shell > input` |
| Field (floating) | `… .pbn-canvas-input-inner` |
| Static label | `… > label` |
| Floating label | `… .pbn-floating-label` |
| Focus | `… .pbn-canvas-input-shell:focus-within` |
| Placeholder | `… .pbn-canvas-input-shell input::placeholder` |

---

## G. Canonical normalization

| Resolver | Location |
| -------- | -------- |
| `resolveStandardFieldLabelMode` | `ElementStyles.ts` |
| `resolveStandardFieldOutlineCssRules` | `ElementStyles.ts` |
| `resolveStandardFieldValueCssRules` | `ElementStyles.ts` |
| `resolveStandardFieldStaticLabelCssRules` | `ElementStyles.ts` |
| `resolveStandardFieldFloatingLabelCssRules` | `ElementStyles.ts` |
| `resolveStandardFieldWrapperCssRules` | `standardFieldPresentation.ts` |
| `compileStandardFieldPresentationRenderCss` | `standardFieldPresentation.ts` |

Canvas adapters: `buildCanvasInputShellStyles`, `CanvasTextInputField` delegate to canonical resolvers.

---

## H. Properties migrated

**Wrapper:** margin, width, maxWidth, minWidth, alignSelf, boxSizing  
**Outline:** padding, background, border, border-radius, box-shadow, height, min-height, CSS vars (`--pbn-canvas-input-focus`, `--pbn-floating-label-bg`, `--pbn-canvas-input-placeholder`)  
**Field:** font-size, font-family, color, line-height, letter-spacing, font-weight, text-align, structural padding  
**Static label:** color, font-family, font-size, font-weight, display, margin-bottom  
**Floating label:** color, font-family, font-size, font-weight, line-height, text-align  
**Focus:** border-color, box-shadow on `:focus-within`  
**Placeholder:** opacity, color via scoped `::placeholder`

---

## I. Responsive strategy

Base artifact-scoped rules + `@media (max-width: 480px)` diff-only mobile on **same owners**:

- padding: outline → outline  
- border-radius: outline → outline  
- font-size: field → field  
- margin: wrapper → wrapper  

No `!important`.

---

## J. Floating-label handling

- Merchant label typography → renderCss  
- Transform/position/float animation → embedded `floating-label.export.css`  
- Generic shell merchant rules removed; phone retains scoped `!important` focus until Phone Compound migration  

---

## K. Focus-state ownership

- **Selector owner:** outline `.pbn-canvas-input-shell:focus-within`  
- **Presentation value:** `--pbn-canvas-input-focus` from `focusBorderColor` trait in renderCss base rules  
- **State rule:** artifact-scoped `:focus-within` border + box-shadow (no `!important`)  

---

## L. Validation/error ownership

- Runtime: `formValidation.ts` toggles `.pbn-export-field-error-shell`, injects `[data-adflipr-field-error]`  
- Global `embed-responsive.export.css` error colors (`!important`) — **unchanged**, runtime-owned  
- No merchant error traits invented  

---

## M. Inline CSS removed

From export HTML for `text-input` / `email-input`:

- Wrapper margin/width  
- Outline shell merchant styles  
- Field typography inline  
- Static and floating label inline  
- Floating field wrap merchant CSS vars  

---

## N. Embedded CSS removed

- `text-input` / `email-input` no longer in `buildBlockTraitResponsiveCss`  
- Generic `.pbn-canvas-input-shell--floating` merchant padding/focus removed (phone-scoped replacement)  

---

## O. Generic responsive cleanup

```text
text-input generic responsive merchant rules → zero
email-input generic responsive merchant rules → zero
```

Verified in `standardFieldPresentationRenderCss.test.ts`.

---

## P. Multi-step behavior

- `collectArtifactStandardFieldPresentations` dedupes by block id  
- `StandardFieldPresentationConflictError` on conflicting presentation across steps  
- Deterministic sort by block id  

---

## Q. Before/after HTML

**Before:** wrapper inline margin; outline inline padding/bg/radius; field inline font-size; label inline color  
**After:** wrapper structural `flex-shrink:0` only; shell/field/label classes without merchant `style=`; presentation in `renderCss`

---

## R. Generated renderCss shape

```text
[artifact][block-id]{margin…}
[artifact][block-id] .pbn-canvas-input-shell{padding…;background…;--pbn-canvas-input-focus:…}
[artifact][block-id] .pbn-canvas-input-shell > input{font-size…}
[artifact][block-id] .pbn-canvas-input-shell:focus-within{border-color:var(--pbn-canvas-input-focus);…}
@media (max-width:480px){…outline…field…wrapper diff-only…}
```

---

## S. Chromium computed-style evidence

| Case | Desktop | Mobile | Result |
| ---- | ------- | ------ | ------ |
| text-input padding | outline 12px | outline 4px | **PASS** |
| text-input font-size | field 16px | field 12px | **PASS** |
| text-input border-radius | outline 8px | outline 4px | **PASS** |
| email-input padding | outline 10px | outline 3px | **PASS** |
| floating padding/font | outline/field correct owners | same | **PASS** |

Tests: `standardFieldPresentation.browser.test.ts`

---

## T. Phase 0 regression contract

```text
18 migrated green / 0 red / 5 controls green — PASS
```

---

## U. Consumer completeness

| Consumer | Status |
| -------- | ------ |
| serializePopupExport + renderCss | **PASS** |
| popupHtmlSerializer tests | **PASS** |
| responsiveDeviceCss | **PASS** |
| mountRenderArtifactCss | **PASS** |
| formValidation | **PASS** |
| floatingLabel initializer | **PASS** |

---

## V. Phone-input non-regression

- Phone export test **PASS**  
- `floating-label.export.css` phone-scoped rules preserved  
- `buildPhoneInputCountrySelectResponsiveCss` unchanged  

---

## W. Obsolete production code deleted

- Merchant inline emission in `serializeTextInputLike` (outline/field/label styles)  
- Merchant inline on `renderLabel` / `floatingLabelHtml`  
- Merchant CSS vars on `buildFloatingFieldWrapStyle`  
- Generic `buildBlockTraitResponsiveCss` path for text-input/email-input  
- Generic floating shell `:focus-within !important` (replaced with phone-scoped)  

---

## X. Remaining Standard Field CSS debt

**None** for `text-input` / `email-input` within this family.

Global placeholder rule remains for textarea/dropdown/phone until their phases.

---

## Y. Remaining buildBlockTraitResponsiveCss consumers

```text
divider, phone-input, textarea, dropdown, check-box, radio, date,
timer, spinner, image, video, product, coupon, feedback, social
```

(15 types — text-input and email-input removed)

---

## Z. Architecture gates

| Gate | Status |
| ---- | ------ |
| Complete family (no hybrid) | **PASS** |
| Correct owner responsive | **PASS** |
| No new !important (standard field) | **PASS** |
| Phone non-regression | **PASS** |
| Phase 0 contract | **PASS** |

---

## AA. Test evidence

- `standardFieldPresentationRenderCss.test.ts` — 8 tests  
- `standardFieldPresentation.browser.test.ts` — 5 tests  
- `responsiveCssBaseline.browser.test.ts` — 25 tests  
- `popupHtmlSerializer.test.ts` — floating label updated expectations  

---

## Files changed

- `ElementStyles.ts` — canonical Standard Field resolvers  
- `standardFieldPresentation.ts` — new compiler  
- `compilePopupRenderCss.ts` — wired compiler  
- `serializeFieldHelpers.ts`, `inputFieldMarkup.ts` — structure-only HTML  
- `serializeElements.ts` — strip wrapper merchant CSS  
- `responsiveDeviceCss.ts` — skip migrated types  
- `floating-label.export.css` — surgical cleanup  
- `inputFieldStyles.ts`, `buildCanvasInputShellStyles.ts`, `CanvasTextInputField.tsx` — canvas adapters  
