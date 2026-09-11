# Phase 6E — Phone Input Merchant Presentation Migration Report

**Date:** 2026-07-06  
**Scope:** Phone Input merchant-configurable presentation → artifact-scoped `renderCss`  
**References:** H1 audit, Phase 6B/6C/6D input-shell reports, G1 export CSS, Phase 0 baseline

---

## 1. Executive verdict

Phase 6E migrated **Phone Input merchant presentation** from serializer inline styles and generic embedded mobile trait CSS into **`compilePhoneInputPresentationRenderCss()`**. Reusable compound structure (row flex, country wrap, invisible select overlay, flag, divider) remains in **`floating-label.export.css`**. Structural country-select overflow/max-width remains in **`buildPhoneInputCountrySelectResponsiveCss()`** + **`embed-responsive.export.css`**. Floating-label mechanics and validation behavior unchanged.

**Verdict: ACCEPT**

---

## 2. H1 architecture boundary applied

Four-concern model enforced:

| Concern | Phone Input destination |
|---------|-------------------------|
| Merchant presentation | `renderCss` via `phoneInputPresentation.ts` |
| Reusable structure | `floating-label.export.css` compound rules |
| Runtime behavior | Floating-label transforms, validation in embed sheets |
| Content-local | N/A for Phone |

H1 ambiguity resolved: **fixed select width / row flex → SHARED STRUCTURE**, not `renderCss`.

---

## 3. Phone model field inventory

| Field | Merchant? | Desktop/mobile? | Canvas | Export | Concern |
|-------|-----------|-----------------|--------|--------|---------|
| `properties.style.backgroundColor` | YES | shared | shell | renderCss outline | MERCHANT PRESENTATION |
| `borderStyle/Width/Color` | YES | shared | shell | renderCss outline | MERCHANT PRESENTATION |
| `cornerRadius` / `cornerRadiusMobile` | YES | both | shell | renderCss outline @media | MERCHANT PRESENTATION |
| `paddingDesktop/Mobile` | YES | both | shell | renderCss outline @media | MERCHANT PRESENTATION |
| `marginDesktop/Mobile` | YES | both | wrapper | renderCss wrapper @media | MERCHANT PRESENTATION |
| `elementWidth/Position/fullWidth` | YES | both | wrapper | renderCss wrapper | MERCHANT PRESENTATION |
| `height` / min-height trait | YES | both | shell | renderCss outline | MERCHANT PRESENTATION |
| `fontSize/fontSizeMobile` | YES | both | tel input | renderCss tel @media | MERCHANT PRESENTATION |
| `fontFamily/fontWeight/fontColor` | YES | both | tel/label | renderCss | MERCHANT PRESENTATION |
| `label/labelText/labelColor/floatingLabel` | YES | shared | label | renderCss label | MERCHANT PRESENTATION |
| `focusBorderColor` | YES | shared | CSS vars | renderCss :focus-within | MERCHANT PRESENTATION |
| `placeholder` (attr) | YES | shared | input attr | HTML attr | RUNTIME BEHAVIOR |
| `showCountrySelector` | YES | shared | topology | HTML structure | STRUCTURE (not CSS) |
| `defaultCountry` | YES | shared | select options | HTML | RUNTIME BEHAVIOR |
| Row flex / country wrap 2.75rem | NO | — | Tailwind/classes | shared CSS | REUSABLE STRUCTURE |
| Invisible select overlay | NO | — | canvas | shared CSS | REUSABLE STRUCTURE |
| Divider `#DEDFEA` | NO (fixed) | — | canvas | shared CSS | REUSABLE STRUCTURE |
| Float transform/animation | NO | — | shared sheet | shared sheet | RUNTIME BEHAVIOR |
| Validation attrs/classes | NO | — | embed | embed-responsive | RUNTIME BEHAVIOR |

---

## 4. Exported DOM topology (verified from `serializePhoneInput`)

**Standard label + country:**

```html
<div data-adflipr-block-id="…" data-block-type="phone-input">
  <label for="…">…</label>
  <div class="pbn-canvas-input-shell">
    <div class="pbn-phone-row pbn-phone-row--standard-compound">
      <div class="pbn-phone-country-wrap" data-pbn-phone-country-select>
        <img data-pbn-phone-flag …>
        <select name="…_country">…</select>
      </div>
      <div class="pbn-phone-divider" data-pbn-phone-divider aria-hidden></div>
      <input type="tel" name="…" …>
    </div>
  </div>
</div>
```

**Floating label + country:**

```html
<div class="pbn-canvas-input-shell pbn-canvas-input-shell--floating">
  <div class="pbn-floating-field pbn-floating-field--phone">
    <div class="pbn-floating-field__row pbn-phone-row">…compound…</div>
    <label class="pbn-floating-label">…</label>
  </div>
</div>
```

---

## 5. DOM ownership matrix

| DOM node | Stable selector | Owner role |
|----------|-----------------|------------|
| Block wrapper | `[data-adflipr-block-id]` | IDENTITY + LAYOUT (margin/width in renderCss) |
| Static label | `> label` | PAINTED (renderCss) |
| Outline shell | `.pbn-canvas-input-shell` | PAINTED (renderCss) |
| Phone row | `.pbn-phone-row` | STRUCTURE |
| Country wrap | `.pbn-phone-country-wrap` | STRUCTURE |
| Invisible select | `.pbn-phone-country-wrap select` | STRUCTURE + BEHAVIOR |
| Flag | `[data-pbn-phone-flag]` | STRUCTURE |
| Divider | `.pbn-phone-divider` | STRUCTURE |
| Tel value | `input[type="tel"]` or `.pbn-canvas-input-inner` | PAINTED (renderCss) |
| Floating label | `.pbn-floating-label` | PAINTED (renderCss) |

---

## 6. Current CSS source trace (post-migration)

| Property | Desktop source | Mobile source | DOM target | Concern | Phase 6E action |
|----------|----------------|---------------|------------|---------|-----------------|
| Wrapper margin/width | renderCss | renderCss @media | block wrapper | MERCHANT | Migrated |
| Outline border/bg/radius/padding/height | renderCss | renderCss @media | `.pbn-canvas-input-shell` | MERCHANT | Migrated |
| Tel typography/padding | renderCss | renderCss @media | tel input | MERCHANT | Migrated |
| Label typography | renderCss | — | label | MERCHANT | Migrated |
| Focus border | renderCss :focus-within | — | outline | MERCHANT | Migrated |
| Placeholder color | renderCss ::placeholder | — | tel | MERCHANT | Migrated |
| Row flex | floating-label.export.css | — | `.pbn-phone-row` | STRUCTURE | Retained |
| Country wrap dimensions | floating-label.export.css | — | country wrap | STRUCTURE | Retained |
| Select max-width mobile | buildPhoneInputCountrySelectResponsiveCss | @media | `phone-input select` | STRUCTURE | Retained |
| Select overflow | embed-responsive.export.css | — | `phone-input select` | STRUCTURE | Retained |
| Float transform | floating-label.export.css | — | `.pbn-floating-label` | BEHAVIOR | Retained |
| Validation error | embed-responsive.export.css | — | error shell | BEHAVIOR | Retained |

---

## 7. Classification matrix (summary)

All merchant-configurable traits from `properties.style` → **renderCss**.  
All compound geometry / invisible select / flag / divider → **shared structural CSS**.  
All float mechanics / validation → **runtime behavior CSS**.

---

## 8. Canonical normalization

**`normalizePhoneInputPresentation()`** in `phoneInputPresentation.ts`:

- Reuses `resolveStandardFieldOutlineCssRules`, `resolveStandardFieldStaticLabelCssRules`, `resolveStandardFieldFloatingLabelCssRules`
- Phone-specific: **`resolvePhoneInputTelCssRules()`** (compound padding variants keyed by `showCountrySelector` + label mode)
- Wrapper via `resolvePhoneInputWrapperCssRules()` → `getExportWrapperStyleFromLayout('phone-input', …)`

Canvas continues via `buildCanvasInputShellStyles` + `getInputFieldTypographyStyle` (same trait sources).

---

## 9. Compiler topology decision

**Selected: A — `compilePhoneInputPresentationRenderCss()`** (dedicated compiler)

---

## 10. Rejected compiler options

| Option | Rejected because |
|--------|------------------|
| B — Extend standard-field compiler | Phone tel selectors differ; compound padding; `showCountrySelector` in presentation key |
| C — Shared core + phone descendant only | Outline/label already shared; tel compound semantics differ enough to warrant dedicated collector/conflict surface |

---

## 11. Base merchant rules (renderCss)

Per block: wrapper layout, outline chrome + CSS vars, tel typography/padding, static/floating label, `:focus-within`, `::placeholder`.

---

## 12. Mobile strategy

**DIFF-ONLY** for wrapper, outline, tel — same DOM owners desktop/mobile; omitted mobile values inherit desktop per PCL-008 cascade.

---

## 13. Country select boundary

- **Merchant presentation on invisible export select:** none (opacity 0 overlay; typography applies to visible tel input)
- **Structural:** fixed wrap width (2.75rem), invisible absolute select, mobile max-width via `buildPhoneInputCountrySelectResponsiveCss` — **RETAINED**
- Test: `phoneInputPresentationRenderCss.test.ts` asserts structural CSS retained after inline removal

---

## 14. Floating-label boundary

- **Merchant:** label color/font → renderCss
- **Behavior:** transform, translate, floated padding-top on row → floating-label.export.css (unchanged)

Removed phone-specific merchant chrome from floating-label sheet (old `min-height/padding/focus-within !important` on phone floating shell).

---

## 15. Validation boundary

Unchanged — `buildFieldValidationInputAttrs`, embed-responsive error rules.

---

## 16. Inline CSS removed

All `style=""` from phone export: shell, row, country wrap, select, flag, divider, tel input.

---

## 17. Inline CSS retained

**None** for merchant presentation. No `style=""` on phone export nodes.

---

## 18. Generic responsive CSS removed

`phone-input` added to `MIGRATED_BLOCK_TRAIT_TYPES` — excluded from `buildBlockTraitResponsiveCss`.

---

## 19. Shared structural CSS retained

`floating-label.export.css`: `.pbn-phone-row`, `.pbn-phone-country-wrap`, select overlay, flag, divider, tel flex participation.

---

## 20. Runtime behavior CSS retained

Floating-label transforms, validation error visibility, embed overflow guards.

---

## 21. Multi-step union

Same `blockId` + same normalized presentation → emit once (`collectArtifactPhoneInputPresentations`).

---

## 22. Conflict behavior

Conflicting presentation across steps → `PhoneInputPresentationConflictError`.

---

## 23. Artifact isolation

Selectors: `[data-adflipr-popup-artifact="…"] [data-adflipr-block-id="…"]`. Unit test scopes distinct block ids.

---

## 24. Canvas parity

Same trait resolvers (`ElementStyles` outline/value/label + canvas shell builders). Equivalent interpretation; different CSS delivery path.

---

## 25. Consumer completeness

All consumers use `serializePopupExport` → HTML + `renderCss` + hoisted embedded sheets. No consumer-specific phone patches.

---

## 26. Chromium computed-style evidence

`phoneInputPresentation.browser.test.ts`: outline padding, tel font-size, border-radius, row flex, country wrap width, focus border, label color — **PASS**.

---

## 27. Negative architecture assertions

Tests assert: no inline merchant on shell/tel/select; phone excluded from generic block trait responsive; structural sheet retains compound rules; no phone merchant focus rules in floating-label sheet.

---

## 28. Obsolete production code deleted

- `buildPhoneRowStyle`, `buildPhoneSelectStyle`, `buildPhoneDividerStyle` from `inputFieldStyles.ts`
- Phone serializer inline style builders (entire `styleAttr` usage removed)

---

## 29. Phase 0 scoreboard

**18 migrated green / 0 red / 5 controls green** — `npm run test:popup-responsive-baseline` PASS

---

## 30. G1 production contract

`npm run test:export-css-production-contract` — **PASS**

---

## 31. Production build

**NOT REQUIRED** — serializer/compiler/sheet changes validated by unit + browser + G1 webpack contract. Full `npm run build:ci` not executed in this session.

---

## 32. Remaining Phone CSS debt

- Fixed divider color `#DEDFEA` not merchant-configurable (acceptable fixed chrome)
- Canvas-only Tailwind classes on phone row (export uses shared CSS classes)

---

## 33. Remaining overall CSS architecture debt

Product, timer, radio, checkbox, image, video, coupon, feedback, spinner families still inline/generic responsive per H1.

---

## 34. Architecture gates

| Gate | Result |
|------|--------|
| Merchant presentation artifact-scoped | YES |
| Structure outside renderCss | YES |
| Behavior outside renderCss | YES |
| Same-concern permanent hybrid | NO |
| renderCss dumping ground | NO |

---

## 35. Final verdict

**ACCEPT**

---

## 36. Recommended next action

**Phase 6F — Radio merchant presentation** (or Product structural module per H1) — not Phone follow-up.

---

## Files changed

| File | Action |
|------|--------|
| `phoneInputPresentation.ts` | NEW — compiler + normalization |
| `compilePopupRenderCss.ts` | Wire phone compiler |
| `phone-input/serialize.ts` | Structure-only HTML |
| `ElementStyles.ts` | `resolvePhoneInputTelCssRules` |
| `responsiveDeviceCss.ts` | Exclude phone from generic traits |
| `floating-label.export.css` | Compound structure; remove phone merchant chrome |
| `inputFieldStyles.ts` | Delete obsolete phone export adapters |
| `phoneInputPresentationRenderCss.test.ts` | NEW |
| `phoneInputPresentation.browser.test.ts` | NEW |
