# Phase 6F — Date Input Merchant Presentation Migration Report

**Date:** 2026-07-06  
**Scope:** Date block merchant presentation → artifact-scoped `renderCss` via `standardFieldPresentation` extension  
**References:** H2 audit, Phase 6B standard-field pattern, Phase 0 baseline, G1 export CSS contract

---

## 1. Executive verdict

Phase 6F migrated **Date merchant presentation** from missing renderCss ownership (generic mobile trait CSS only) into **`compileStandardFieldPresentationRenderCss()`** by extending `StandardFieldBlockType` to include `'date'`. Date export HTML remains structure-only; wrapper margin/width moved from inline block wrapper to renderCss; `date` excluded from `buildBlockTraitResponsiveCss`.

**Verdict: ACCEPT**

---

## 2. H2 defect verification (Part A)

H2 diagnosis **confirmed** before implementation.

| Trait | UI writer | Persisted field | Canvas result | Pre-6F export result |
|-------|-----------|-----------------|---------------|----------------------|
| `backgroundColor` | `DateProperties` | `style.backgroundColor` | Shell via `buildCanvasInputShellStyles` | **Not in renderCss** |
| `borderStyle/Width/Color` | `DateProperties` | `style.*` | Shell outline | **Not in renderCss** |
| `focusBorderColor` | `DateProperties` | `style.focusBorderColor` | CSS var on canvas | **Not in renderCss** |
| `cornerRadius` (+ mobile) | `DeviceCornerRadiusSection` | `style.cornerRadius*` | Shell | Generic mobile only |
| `paddingDesktop/Mobile` | `DeviceSpacingSection` | `style.padding*` | Shell | Generic mobile only |
| `height` | `DateProperties` | `style.height` | Shell min-height | **Not in renderCss** |
| `fontFamily/Size/Weight` | `InputTypographyAccordion` | `style.*` | Input typography | Generic mobile font-size only |
| `fontColor` / `textColor` | `InputTypographyAccordion` | `style.fontColor` | Input color | **Not in renderCss** |
| `placeholderColor` | `DateProperties` | `style.placeholderColor` | CSS var | **Not in renderCss** |
| Label traits | `FormFieldProperties` | `style.label*` | `CanvasFieldLabel` | **Not in renderCss** |
| `marginDesktop/Mobile` | `DeviceSpacingSection` | `style.margin*` | Wrapper | **Inline on block wrapper** |
| `elementWidth/Position` | `PositionWidthSection` | `style.*` | Wrapper layout | **Inline on block wrapper** |

**Proof points:**

1. Traits written to `properties.style` — `DateProperties.tsx` `handleStyleChange` / shared sections  
2. Canvas reads traits — `DateCanvas.tsx` → `buildCanvasInputShellStyles`, `getInputFieldTypographyStyle`  
3. Serializer emits stable structure — `date/serialize.ts` → `serializeTextInputLike(..., 'date', ...)`  
4. No renderCss path — `StandardFieldBlockType` excluded `'date'`  
5. Generic mobile CSS — `buildBlockTraitResponsiveCss` included `date`  
6. Storefront lost base presentation — only `floating-label.export.css` + wrapper inline + mobile overrides

---

## 3. Date trait inventory

Same fields as H2 table above. Date-specific **content/configuration** (not presentation):

- `dateFieldType`, `dateOrder`, `dateSeparator`, `placeholder` (format hint)
- `required`, `requiredText`, `invalidText`
- Export attrs: `min`, `max` (from `getPbnDateFieldRangeBounds`)

**Floating label:** NOT supported in Date UI (no `floatingLabel` in `DateProperties` or canvas).

---

## 4. Exact Date DOM topology (Part B)

Serialized standard-label Date block:

```html
<div data-block-type="date" data-adflipr-block-id="{id}">
  <label for="{name}">{labelText}</label>
  <div class="pbn-canvas-input-shell">
    <input type="date" name="{name}" min="{iso}" max="{iso}" …>
  </div>
</div>
```

Floating-label topology is available via shared `serializeTextInputLike` if model ever sets `floatingLabel`, but **current Date UI does not expose it**.

---

## 5. DOM ownership matrix

| DOM node | Stable selector | Owner role | Presentation source (post-6F) |
|----------|-----------------|------------|-------------------------------|
| Block wrapper `div` | `[data-adflipr-block-id]` | BLOCK LAYOUT | renderCss wrapper (margin, width, align-self) |
| Static `label` | `> label` | LABEL PRESENTATION | renderCss label rules |
| `.pbn-canvas-input-shell` | `.pbn-canvas-input-shell` | PAINTED SHELL | renderCss outline (bg, border, radius, padding, height, focus vars) |
| `input[type=date]` | `.pbn-canvas-input-shell > input` | VALUE PRESENTATION | renderCss value typography |
| `min`/`max`/`required` attrs | `input[type=date]` | BEHAVIOR | HTML attributes (unchanged) |
| Native calendar indicator | browser pseudo | BEHAVIOR | Native (not suppressed) |
| Floating-label transform | `.pbn-floating-field` | STRUCTURE | `floating-label.export.css` (if ever used) |

---

## 6. Compiler reuse decision (Part C)

**Selected: A — Extend `StandardFieldBlockType` to include `'date'`**

Evidence:

- Same `properties.style` semantics as text-input/email-input
- Same DOM owners (wrapper, shell, `> input`, `> label`)
- Same mobile diff-only inheritance via `diffMobilePresentation`
- Same focus presentation (`:focus-within` + `--pbn-canvas-input-focus`)
- Same label mode resolver (`resolveStandardFieldLabelMode`) — standard only in current UI
- `input[type=date]` matches selector `.pbn-canvas-input-shell > input`

---

## 7. Rejected compiler options

| Option | Reason rejected |
|--------|-----------------|
| B — Dedicated Date compiler | Duplicate of 6B logic; no unique DOM owners or trait semantics |
| C — Shared core + Date descendant compiler | No native-date-specific paint owner distinct from standard field value selector |

---

## 8. Canonical normalization (Part D)

Date canvas and renderCss both use:

- `resolveStandardFieldOutlineCssRules`
- `resolveStandardFieldValueCssRules`
- `resolveStandardFieldStaticLabelCssRules`
- `resolveStandardFieldLabelMode`
- `getExportWrapperStyleFromLayout('date', …)` for wrapper rules

No Date-specific trait interpreter added.

---

## 9. Merchant properties compiled (Part E)

| Owner | Compiled properties |
|-------|---------------------|
| Wrapper | margin (device-paired), width, max-width, align-self |
| Shell | background, border style/width/color, radius, padding, min-height, focus/placeholder CSS vars |
| `input[type=date]` | font-family, font-size, color, font-weight, line-height, letter-spacing, text-align, padding |
| Static label | color, font-size, font-family, font-weight |
| `:focus-within` | border-color, box-shadow via CSS vars |
| `::placeholder` | opacity, color via CSS var (browser support limited on `type=date`) |

---

## 10. Native Date behavior audit (Part F)

| Concern | Handling |
|---------|----------|
| `input[type=date]` | Preserved; export unchanged |
| `::-webkit-calendar-picker-indicator` | Not styled; native control retained |
| Native value rendering | Browser default |
| Placeholder | `placeholder` attr emitted but **ignored by browsers** for `type=date` — documented limitation |
| Focus | `:focus-within` on shell; Chromium test PASS |
| `min`/`max` | Preserved on input attrs |
| `required` | Preserved via `buildFieldValidationInputAttrs` |

No browser-specific CSS hacks added.

---

## 11. Mobile strategy (Part G)

**DIFF-ONLY** — same as Phase 6B.

Desktop and mobile target the same owners; mobile rules emit only changed declarations in `@media (max-width: 480px)`. Date does not change owner topology between devices.

---

## 12. Generic responsive CSS removed (Part H)

`'date'` added to `MIGRATED_BLOCK_TRAIT_TYPES` in `responsiveDeviceCss.ts`.

**Negative proof:** `standardFieldPresentationRenderCss.test.ts` — `buildBlockTraitResponsiveCss` output does not contain `[data-adflipr-block-id="date-1"]`.

Shared `floating-label.export.css` and validation behavior CSS unchanged.

---

## 13. Inline style audit (Part I)

Post-6F Date export:

| Declaration | Classification | Status |
|-------------|----------------|--------|
| Shell `style=""` | — | **None** |
| Input `style=""` | — | **None** |
| Block wrapper `style=""` (margin/width) | Was MERCHANT | **Removed** — moved to renderCss |
| `min`/`max` attrs | BEHAVIOR | Retained as attributes |

**Date merchant inline CSS remaining: NONE**

---

## 14. Focus ownership (Part J)

| Concern | Owner |
|---------|-------|
| Merchant `focusBorderColor` | renderCss outline CSS var `--pbn-canvas-input-focus` |
| Focus activation | `:focus-within` on `.pbn-canvas-input-shell` |

Chromium test: focus border `rgb(68, 85, 102)` matches `#445566` trait — **PASS**

---

## 15. Label ownership (Part K)

| Mode | Status |
|------|--------|
| Standard label | renderCss `> label` — color/size **PASS** (Chromium) |
| Floating label | **NOT SUPPORTED** in Date UI |

Floating-label mechanics remain in `floating-label.export.css` only.

---

## 16. Validation boundary (Part L)

Unchanged:

- `required`, `requiredText` via validation attrs
- `min`/`max` ISO dates on `input[type=date]`
- Embed validation in `embed-responsive.export.css`

No validation rules in renderCss compiler.

---

## 17–18. Multi-step union and conflict (Part M)

Uses existing `collectArtifactStandardFieldPresentations`:

- Same block id + same presentation → emit once (**test PASS**)
- Same block id + conflicting presentation → `StandardFieldPresentationConflictError` (**existing 6B test**)

Presentation-only conflict keys; different date values with same style do not conflict.

---

## 19. Artifact isolation (Part N)

Chromium test: artifact `artifact-a` vs `artifact-b` with different backgrounds — no bleed — **PASS**

Selectors: `[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"]`

---

## 20. Canvas/export parity (Part O)

Representative traits tested in Chromium:

- Default/custom background, border, radius, padding, typography, focus, label, desktop/mobile diff

Canvas uses same `ElementStyles` resolvers; export uses renderCss compiled from same normalizers — **PASS**

---

## 21. Consumer completeness (Part P)

All consumers use `serializePopupExport` → `renderCss` artifact:

- Builder preview, fullscreen preview, thumbnails, storefront embed — no Date-specific patch required

`compilePopupRenderCss` already invokes `compileStandardFieldPresentationRenderCss` — Date included automatically.

---

## 22. Chromium evidence (Part Q)

`dateInputPresentation.browser.test.ts` — **8 tests PASS**:

- Desktop background, border, padding, typography, radius
- Mobile padding, font-size
- Focus border color
- Label presentation
- Native `type=date` usability (`min`/`max` present)
- Artifact isolation

---

## 23. Negative architecture assertions (Part R)

| Assertion | Result |
|-----------|--------|
| No Date merchant inline | PASS |
| No Date in `buildBlockTraitResponsiveCss` | PASS |
| No fixed structure in renderCss | PASS (only co-located structural rules in value/outline per 6B pattern) |
| No validation in renderCss | PASS |
| No floating-label mechanics in renderCss | PASS |
| Native date behavior available | PASS |

---

## 24. Obsolete production code deleted (Part S)

No files deleted. Obsolete **behavior** removed:

- Date branch in generic `buildBlockTraitResponsiveCss` (exclusion via migrated set)
- Date wrapper inline margin/width in `serializeElements.ts` (stripped like text-input)

No broad deprecated-helper cleanup performed.

---

## 25. Phase 0 result

`responsiveCssBaseline.browser.test.ts` — **PASS**

- 18 migrated regression green  
- 0 risk red  
- 5 controls green  

---

## 26. G1 result

`npm run test:export-css-production-contract` — **PASS**

---

## 27. Production build result

G1 production webpack contract exercised (compiler + serializer dispatch changed). Full app production build **NOT REQUIRED** beyond G1 gate per phase scope.

---

## 28. Remaining Date debt

| Item | Severity |
|------|----------|
| Placeholder color on `type=date` | Low — browsers ignore placeholder on date inputs |
| Floating label mode | Not in UI; shared serializer path exists if product adds it later |
| Canvas uses custom `DatePickerElement` vs export native `input[type=date]` | Intentional preview vs export split (behavior, not presentation) |

---

## 29. Remaining all-block CSS debt

Per H2 (unchanged by 6F):

- 11 families still on inline + generic embedded merchant CSS
- Button/Cross hover not exported
- Text `align` orphan dual persistence
- Button `fontWeight` vocabulary drift

---

## 30. Architecture gates (Part U)

| Question | Answer |
|----------|--------|
| Date merchant presentation fully artifact-scoped? | **YES** |
| Any Date merchant presentation inline? | **NO** |
| Any Date merchant presentation in generic embedded CSS? | **NO** |
| Date canvas same canonical trait semantics? | **YES** |
| Storefront reproduces saved Date presentation? | **YES** (Chromium) |
| Native Date behavior intact? | **YES** |
| Structure/behavior outside renderCss? | **YES** |
| Same-concern hybrid CSS left? | **NO** |

---

## 31. Final verdict

**ACCEPT**

---

## 32. Recommended next action

**Phase 5F — Button + Cross hover state export parity** (highest remaining cross-cutting state defect), or begin next unmigrated family migration per H2 ranking (e.g. `divider` or `image`).

---

## Production changes

| File | Change |
|------|--------|
| `standardFieldPresentation.ts` | `StandardFieldBlockType` includes `'date'` |
| `responsiveDeviceCss.ts` | `'date'` in `MIGRATED_BLOCK_TRAIT_TYPES` |
| `serializeElements.ts` | Strip wrapper inline layout for `date` (renderCss owns margin/width) |
| `standardFieldPresentationRenderCss.test.ts` | Phase 6F unit tests |
| `dateInputPresentation.browser.test.ts` | Phase 6F Chromium tests |

---

*End of Phase 6F report.*
