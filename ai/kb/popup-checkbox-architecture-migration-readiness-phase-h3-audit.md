# Phase H3 — Checkbox Architecture and Migration-Readiness Audit

**Date:** 2026-07-06  
**Mode:** READ-ONLY AUDIT — no production code or test changes  
**Scope:** `check-box` family vs accepted Phase 6G Radio architecture  
**References:** Phase 6G Radio report, Phase H2 selection audit, `popup-rendering-css-architecture.md`

---

## 1. Executive verdict

Production code proves **one** popup form Checkbox family (`check-box`). Its **group/label/wrapper merchant presentation** topology is **partially** shared with Radio (same trait model, same shell resolvers, same split-target defect), but **native-control mechanics differ materially**: Checkbox uses **native browser controls + `accent-color` inline only** — no custom appearance sheet, no CSS variables, no hostile-theme `!important` defense, and **no export of `outlineColor` / `backgroundColor` traits** despite panel support.

Checkbox can safely reuse the **Radio migration pattern** (artifact `renderCss` + structure-only export) but **must not reuse `radio.export.css` or the Radio compiler directly**. Recommended path: **dedicated `checkboxPresentation.ts`** + **new `checkbox.export.css`** if outline/background parity and hostile-theme defense are required (matching merchant trait surface).

**Implementation readiness: READY FOR IMPLEMENTATION**

**Verdict: ACCEPT**

---

## 2. Audit-only statement

All conclusions derived from current repository state (July 2026). Phase 6G Radio report used only as comparison baseline — Checkbox code inspected independently. No production code or tests modified.

---

## 3. Checkbox-related family inventory

| Block/type | Model | Canvas | Serializer | Export CSS | Runtime | Popup form export? |
| ---------- | ----- | ------ | ---------- | ---------- | ------- | ------------------ |
| **`check-box`** | `ElementDefaults` + `CheckBoxProperties` | `CheckboxCanvas` | `check-box/serialize.ts` | Inline + generic `@media` | `formValidation.ts` choice block | **YES** |
| `radio` | separate | `RadioCanvas` | `radio/serialize.ts` | renderCss + `radio.export.css` | shared choice validation | No (comparison only) |
| `feedback` | feedback model | `FeedbackCanvas` | `feedback/serialize.ts` | inline `accentColor` on rating inputs | feedback runtime | **NO** — different block |
| Builder UI (`@mui/material` Checkbox, `ui/checkbox`) | N/A | property panels | N/A | N/A | N/A | **NO** |
| `SpinnerProperties` duplicate-slices checkbox | N/A | panel only | N/A | N/A | N/A | **NO** |

**How many Checkbox-related production block families exist?** **1** — `check-box` only.

---

## 4. Current Checkbox architecture

| Stage | Source |
| ----- | ------ |
| Registration | `registerBuilderBlocks.ts` → `id: 'check-box'`, `CheckboxCanvas` |
| Default model | `ElementDefaults` → `createOptionsDefaults('Checkbox Group')` |
| Traits | `CheckBoxProperties.tsx` — layout, gap, padding/margin (device), typography, fill/outline colors, required |
| Canvas | `CheckboxCanvas.tsx` — inline `groupStyle`, `optionLabelStyle`, `accentColor` on inputs |
| Serializer | `check-box/serialize.ts` — inline group, row, label, `accentColor` on input |
| Generic responsive | `buildBlockTraitResponsiveCss()` — **still active** (`check-box` ∉ `MIGRATED_BLOCK_TRAIT_TYPES`) |
| Family export CSS | **None** |
| Shared export CSS | **None** (unlike Radio's `radio.export.css`) |
| renderCss | **None** |
| Validation | `formValidation.ts` — `CHOICE_BLOCK_TYPES` with radio; block-level required |

### Concern matrix

| Concern | Model | Canvas | Export | DOM owner | Classification |
| ------- | ----- | ------ | ------ | --------- | -------------- |
| Wrapper margin/width | `style.*` | selection + block layout | inline wrapper | `[data-adflipr-block-id]` | MERCHANT PRESENTATION |
| Group padding/border/radius/bg/shadow/minH | `style.*` | `getChoiceGroupShellStyle` | inline group `<div>` | inner group div | MERCHANT PRESENTATION |
| Layout direction + gap | `layout`, `optionGap` | `buildCheckboxGroupStyle` | inline group | inner group div | MERCHANT PRESENTATION |
| Option label typography | `fontFamily`, `fontSize`, `fontColor` | `buildChoiceOptionLabelStyle` | inline option `<label>` | option label | MERCHANT PRESENTATION |
| Group field label | `label`, `labelText`, `labelColor` | `CanvasFieldLabel` | `renderFieldLabel` HTML only | `> label` | MERCHANT PRESENTATION (unstyled export) |
| Fill / selected color | `fillColor` | `accentColor` inline | `accent-color` inline on input | native `input` | MERCHANT PRESENTATION |
| Outline / unselected bg | `outlineColor`, `backgroundColor` | **not applied** | **not exported** | — | MERCHANT PRESENTATION (dead trait) |
| Option row flex | fixed | `buildFormOptionRowStyle` | inline row | option row | REUSABLE STRUCTURE |
| Native checkbox appearance | browser default | native | native + accent-color | `input[type=checkbox]` | NATIVE CONTROL MECHANIC |
| Multi-select toggle | `options[].selected` | canvas handlers | `checked` attr | native input | RUNTIME BEHAVIOR |
| Required group validation | `style.required` | preview | block attrs | wrapper block | RUNTIME BEHAVIOR |
| `name` / `value` / `id` | options + fieldName | canvas | HTML attrs | native input | RUNTIME BEHAVIOR |

---

## 5. Exact exported DOM topology

From `serializeCheckboxGroup` + `serializeElements` wrapper:

```html
<div data-block-type="check-box" data-adflipr-block-id="{id}"
     data-adflipr-field-required="true" …
     style="{wrapper margin/width from getExportWrapperStyleFromLayout}">
  <label for="{fieldName}">{labelText}</label>
  <div style="{group shell + flex layout + gap + minHeight}">
    <div style="display:flex;align-items:center;gap:8px">
      <input type="checkbox" name="{fieldName}" value="{option.value}"
             id="check-{id}-{optionId}"
             style="accent-color:{fillColor}"
             checked?>
      <label for="check-{id}-{optionId}" style="{typography}">{option label}</label>
    </div>
    <!-- repeated per option -->
  </div>
</div>
```

**Notable absences vs Radio (post-6G):**

- No `role="radiogroup"` / `role="group"`
- No `.pbn-export-native-checkbox-scope` (or any scope class)
- No per-input custom appearance / CSS variables
- No `radio.export.css` embedding

Variants: vertical/horizontal layout, single/multiple options, checked/unchecked, required attrs — all follow same shape.

---

## 6. DOM ownership matrix

| DOM node | Stable selector | Inline CSS | Embedded CSS | Role |
| -------- | --------------- | ---------- | ------------ | ---- |
| Block wrapper | `[data-adflipr-block-id="{id}"]` | margin, width | generic `@media` margin/padding/radius/font | IDENTITY + LAYOUT |
| Group field label | `> label` | none | none | PAINTED (no export CSS today) |
| Group container | `> div` (first child after label) | full group chrome | none | PAINTED + LAYOUT |
| Option row | inner `div` (no class) | row flex | none | STRUCTURE |
| Native input | `input[type=checkbox]` | `accent-color` | none | NATIVE CONTROL + PAINTED |
| Option label | `label[for^="check-"]` | typography | none | PAINTED |

---

## 7. Radio-vs-Checkbox topology comparison

| Concern | Radio (6G) | Checkbox (current) | Same semantics? | Reusable? |
| ------- | ---------- | ------------------ | --------------- | --------- |
| Block wrapper | renderCss margin/width | inline wrapper | Yes | REUSABLE THROUGH SHARED ABSTRACTION (wrapper resolver pattern) |
| Group field label | renderCss static label | HTML only, no CSS | Yes (traits) | DIRECTLY REUSABLE resolver (`resolveStandardFieldStaticLabelCssRules`) |
| Group container | `.pbn-export-native-radio-scope` | plain `<div>` | Partial (container role) | SAME DOM SHAPE BUT DIFFERENT SEMANTICS (no radiogroup; multi-select) |
| Option row | `.pbn-display-radio-row` + sheet | plain `div` inline | Yes (layout) | REUSABLE THROUGH SHARED ABSTRACTION (row structure in sheet) |
| Native input | custom appearance + sheet | native + `accent-color` | **No** | **CHECKBOX-SPECIFIC** |
| Option label | renderCss | inline | Yes | DIRECTLY REUSABLE (`resolveRadioOptionLabelCssRules`) |
| Selected state | single `:checked` per group | independent per option | **No** | **CHECKBOX-SPECIFIC** (runtime) |
| Focus-visible | sheet `:focus-visible` | browser default | Partial | **CHECKBOX-SPECIFIC** if custom sheet added |
| Validation | block-level choice | block-level choice | Yes | DIRECTLY REUSABLE (runtime) |
| Color tokens | 3 CSS vars on group | `accent-color` only | Partial | **CHECKBOX-SPECIFIC** unless vars + sheet added |

**Is Checkbox architecturally the same choice-family topology as Radio?** **PARTIAL**

Shared: options array, group shell traits, option label typography, wrapper layout, choice validation pattern.  
Different: native control model, selection semantics, export scope class, color application, hostile-theme strategy.

---

## 8. Merchant presentation field inventory

| Property | Merchant configurable? | Desktop source | Mobile source | DOM owner | Migration candidate? |
| -------- | --------------------- | -------------- | ------------- | --------- | ---------------------- |
| marginDesktop/Mobile | Yes | inline wrapper | generic `@media` wrapper | wrapper | **YES** → renderCss |
| elementWidth/Position | Yes | inline wrapper | wrapper (width stable) | wrapper | **YES** |
| paddingDesktop/Mobile | Yes | inline group | generic `@media` **wrapper** | group (desktop) / wrapper (mobile) | **YES** (fix owner) |
| borderStyle/Width/Color | Model-capable via `getElementStyle` | inline group if set | generic radius on wrapper | group | **YES** if trait exposed |
| cornerRadius(Mobile) | Not in panel UI | inline if in model | generic wrapper | split | **YES** if added to panel |
| boxShadow | Not in panel UI | inline if in model | — | group | **YES** if exposed |
| height/min-height | Not in panel UI | inline group | — | group | **YES** |
| layout h/v | Yes | inline group | same | group | **YES** |
| optionGap | Yes | inline group `gap` | same | group | **YES** |
| fillColor | Yes | `accent-color` inline input | same | input | **YES** → renderCss or var |
| outlineColor | Yes (panel) | **not applied** | — | — | **YES** (currently dead — wire or drop) |
| backgroundColor | Yes (panel) | **not applied** | — | — | **YES** (dead — wire or drop) |
| fontFamily/Size/Color | Yes | inline option label | generic `font-size` on **wrapper** | label / wrapper split | **YES** (fix owner) |
| label/labelText/labelColor | Yes | canvas label component | — | `> label` | **YES** |
| checkbox size | **No** | native | native | browser | **NO** |

---

## 9. Desktop/mobile ownership matrix

| Property | Desktop owner | Mobile owner | Same owner? | Defect? |
| -------- | ------------- | ------------ | ----------- | ------- |
| Wrapper margin | wrapper inline | generic `@media` wrapper | **Yes** | No |
| Wrapper width | wrapper inline | wrapper inline | **Yes** | No |
| Group padding | inner group inline | generic `@media` **wrapper** | **No** | **YES** |
| Group border-radius | inner group inline | generic `@media` **wrapper** | **No** | **YES** (if radius in model) |
| Option font-size | option label inline | generic `@media` **wrapper** | **No** | **YES** |
| Fill color (accent) | input inline | input inline | **Yes** | No |

**Checkbox split-target defect: YES**

**Split-target properties:** `padding`, `border-radius` (when present), `font-size` (option labels via wrapper targeting).

---

## 10. Split-target defect result

Proven in `responsiveDeviceCss.ts` — `check-box` not in `MIGRATED_BLOCK_TRAIT_TYPES`; mobile rules target `[data-adflipr-block-id]` while desktop group padding and label typography live on **inner** nodes (`serialize.ts`).

Same PCL-008 class as pre-6G Radio.

---

## 11. Generic responsive CSS audit

| Property | Selector | Mobile source | Desktop equivalent | Classification |
| -------- | -------- | ------------- | ------------------ | -------------- |
| padding | block wrapper | `resolveDevicePaddingCss` mobile | group inline | MERCHANT — wrong owner |
| margin | block wrapper | mobile margin | wrapper inline | MERCHANT — same owner OK |
| border-radius | block wrapper | mobile radius | group inline | MERCHANT — wrong owner |
| font-size | block wrapper | mobile font | label inline | MERCHANT — wrong owner |

**Can Checkbox be completely excluded from generic merchant responsive CSS after migration?** **YES**

No non-merchant rules require Checkbox to remain in `buildBlockTraitResponsiveCss()`.

---

## 12. Inline-style inventory

| Inline declaration | DOM node | Classification | Proposed action |
| ------------------ | -------- | -------------- | --------------- |
| wrapper margin/width | block wrapper | MERCHANT PRESENTATION | MOVE TO renderCss |
| group shell (padding, border, bg, radius, shadow, minH) | inner group div | MERCHANT PRESENTATION | MOVE TO renderCss |
| flex direction, gap | inner group div | MERCHANT PRESENTATION | MOVE TO renderCss |
| option label typography | option label | MERCHANT PRESENTATION | MOVE TO renderCss |
| row flex align gap 8px | option row div | REUSABLE STRUCTURE | MOVE TO SHARED STRUCTURE (new sheet) |
| accent-color | native input | MERCHANT PRESENTATION | MOVE TO renderCss **or** var consumed by sheet |
| flex-shrink:0 wrapper | block wrapper | REUSABLE STRUCTURE | RETAIN structural inline or class |

---

## 13. Native-control mechanics inventory

| Mechanic | Current source | Merchant-derived? | Current owner | Correct target owner |
| -------- | -------------- | ----------------- | ------------- | -------------------- |
| appearance | browser native | No | UA | NATIVE CONTROL (browser or sheet if customized) |
| dimensions | browser native | No | UA | NATIVE CONTROL |
| border radius (square) | browser native | No | UA | NATIVE CONTROL |
| unchecked border/bg | browser native | Partial (outline trait dead) | UA | SHEET + vars if parity required |
| checked fill / checkmark | `accent-color` inline | Yes (fillColor) | input inline | renderCss `accent-color` **or** sheet + var |
| focus-visible | browser default | No | UA | NATIVE CONTROL / sheet if customized |
| cursor | browser default | No | UA | NATIVE CONTROL |

**Does Checkbox currently have a dedicated native-control sheet?** **NO**

**Where do mechanics live?** Native browser rendering + single `accent-color` inline declaration per input.

---

## 14. Current export/native sheet inventory

| Sheet | Checkbox consumer? |
| ----- | ------------------ |
| `radio.export.css` | **No** |
| `floating-label.export.css` | No |
| `embed-responsive.export.css` | Global form only |
| Dedicated checkbox sheet | **Does not exist** |

---

## 15. Shared sheet strategy

**Sheet strategy: C — dedicated `checkbox.export.css`**

**Rationale:**

- `radio.export.css` is **radio-specific** (circle, `::after` dot, radiogroup scope) — strategy **A rejected**
- Strategy **B** (rename to shared choice sheet) would reopen accepted Radio — **defer**
- Strategy **D** (no sheet, accent-color only) leaves `outlineColor` trait dead and no hostile-theme defense — insufficient for trait parity with panel
- Checkbox needs square geometry, checkmark (native or `appearance:none` + custom), and optional var-based outline/bg — **checkbox-specific sheet**

Rename/refactor of `radio.export.css` → shared choice sheet: **defer** to post-6H stability.

---

## 16. Merchant token strategy

| Merchant value | Model source | Proposed CSS variable | Owner | Consumer |
| -------------- | ------------ | --------------------- | ----- | -------- |
| fillColor | `style.fillColor` | `--pbn-checkbox-accent` | group scope renderCss | sheet checked state / `accent-color` fallback |
| outlineColor | `style.outlineColor` | `--pbn-checkbox-outline` | group scope renderCss | sheet unchecked border |
| backgroundColor | `style.backgroundColor` | `--pbn-checkbox-bg` | group scope renderCss | sheet unchecked bg |

**Can Checkbox merchant tokens be owned once?** **YES** — on proposed `.pbn-export-native-checkbox-scope` (new scope class mirroring Radio).

---

## 17. Hostile-theme ownership gate

| Property | Current source | Current owner | Why priority | Classification | Proposed owner |
| -------- | -------------- | ------------- | ------------ | -------------- | -------------- |
| `accent-color` | fillColor trait | input inline | none today | MERCHANT PRESENTATION | renderCss on input **or** var + sheet |
| (none) | — | — | — | — | — |

**Hostile-theme declarations audited: 0** (`!important` count on Checkbox export path)

**Unknown hostile-theme ownership: NONE**

**Internal cascade-repair !important: NO**

**Can Radio's hostile-theme pattern be reused directly?** **PARTIAL** — var-on-group + sheet-with-`!important` **pattern** applies; `radio.export.css` rules **do not** (square vs circle, checkmark vs dot).

Implementation must add hostile-theme tests (none exist today for Checkbox).

---

## 18. Group-label normalization decision

**Gate 2: A — Direct reuse**

`resolveStandardFieldStaticLabelCssRules` — same traits and DOM (`> label` before group) as Radio/dropdown.

---

## 19. Option-label normalization decision

**B — Extract shared choice-option-label resolver** (implementation-time naming; audit does not refactor)

Checkbox can call existing `resolveRadioOptionLabelCssRules` today — semantics match. Recommend neutral alias `resolveChoiceOptionLabelCssRules` during 6H implementation without blocking on rename.

---

## 20. Group/container normalization decision

**B — Extract shared choice-group resolver** (deferred rename)

Checkbox group traits match Radio: `getChoiceGroupShellStyle` + `buildCheckboxGroupStyle` ≡ `resolveRadioGroupPresentationCssRules` logic. Implementation should **call same resolver functions** without coupling to `radioPresentation.ts` compiler.

---

## 21. Wrapper normalization decision

**Existing shared resolver available: YES**

Mirror `resolveRadioWrapperCssRules` with `blockType: 'check-box'` (same `getExportWrapperStyleFromLayout` path; `check-box` ∈ `PERCENT_WIDTH_LAYOUT_BLOCK_TYPES`).

---

## 22. Compiler topology recommendation

**Compiler topology: A — Dedicated `checkboxPresentation.ts`**

**Do not** extend `radioPresentation.ts` (C) or shared `choicePresentation.ts` (B) in same phase — avoids reopening accepted 6G Radio.

---

## 23. Mobile strategy per owner

| Owner | Strategy | Why |
| ----- | -------- | --- |
| Wrapper | DIFF-ONLY | Same owner after migration |
| Group container | DIFF-ONLY | Unified radiogroup-equivalent owner |
| Option label | DIFF-ONLY | Unified label selector |
| Group field label | NO MOBILE RULE / shared desktop | `resolveStandardFieldStaticLabelCssRules` has no device pair today |
| Native control (accent) | NO MOBILE RULE | fillColor not device-paired in panel |

---

## 24. Multi-step/conflict recommendation

- **Presentation identity key:** normalized `normalizeCheckboxPresentation()` JSON
- **Conflict error:** `CheckboxPresentationConflictError` (mirror Radio)
- **Exclude from key:** `options[].label`, `options[].value`, `options[].selected`, `required`, validation state, submission values

---

## 25. Runtime behavior audit

| Behavior | Current owner | CSS migration risk |
| -------- | ------------- | ------------------ |
| Per-option toggle | native `input` + canvas handlers | **Low** — preserve `checked`, `onChange` |
| Multiple selection | independent checkboxes, shared `name` | **Low** — do not collapse to radio semantics |
| Label click | `label for` + canvas | **Low** |
| Keyboard toggle | canvas row handlers | **Low** (canvas only) |
| Required validation | `formValidation.ts` block-level | **Low** — preserve block attrs |
| Form submission | `name` + `value` per input | **Low** |
| Default checked state | `options[].selected` → `checked` attr | **Low** |

**Would proposed migration change runtime semantics?** **NO** (if native inputs and attrs preserved).

---

## 26. Radio reuse boundary matrix

| Concern | Reuse Radio directly | Extract shared abstraction | Checkbox-specific | Reason |
| ------- | -------------------- | -------------------------- | ----------------- | ------ |
| Wrapper normalization | Pattern only | `getExportWrapperStyleFromLayout` + keys | blockType `'check-box'` | Same layout rules |
| Group normalization | Resolver functions | `resolveRadioGroupPresentationCssRules` → rename later | scope class name | Same traits, different class |
| Option-label normalization | **Yes** (resolver) | Optional neutral rename | — | Identical traits |
| Group-label normalization | **Yes** | — | — | Standard static label |
| Collector/conflict pattern | Pattern only | — | `checkboxPresentation.ts` | Do not reopen Radio compiler |
| Mobile DIFF-ONLY pattern | **Yes** | — | — | Proven in 6G |
| Merchant token pattern | Pattern only | — | `--pbn-checkbox-*` vars | Different prefix |
| Native-control sheet | **No** | Defer shared choice sheet | `checkbox.export.css` | Square/checkmark vs circle/dot |
| Hostile-theme defense | Pattern only | — | checkbox sheet rules | No radio `::after` dot |
| Checked-state mechanics | **No** | — | native or checkbox `:checked` rules | Multi-select |

---

## 27. Pattern reuse vs code reuse decision

**Pattern reuse: YES** — mirror Phase 6G topology (renderCss + structure-only export + native sheet + runtime unchanged).

**Code reuse: PARTIAL** — reuse ElementStyles resolvers (`resolveRadioGroupPresentationCssRules`, `resolveRadioOptionLabelCssRules`, `resolveStandardFieldStaticLabelCssRules`); **do not** reuse `radioPresentation.ts` or `radio.export.css`.

**Shared abstraction extraction recommended now: NO** — defer choice-group sheet merge until after Checkbox ships.

---

## 28. Obsolete-code deletion forecast

| Symbol/path | Current role | Post-migration action | Confidence |
| ----------- | ------------ | --------------------- | ---------- |
| `check-box/serialize.ts` group `styleAttr` | merchant inline | DELETE | High |
| `check-box/serialize.ts` label `styleAttr` | merchant inline | DELETE | High |
| `check-box/serialize.ts` row `styleAttr` | structure inline | DELETE (sheet) | High |
| `check-box/serialize.ts` input `accent-color` | merchant inline | DELETE (renderCss/sheet) | High |
| `buildCheckboxGroupStyle` export usage | layout inline | RETAIN CANVAS-ONLY or delegate | Medium |
| `buildFormOptionRowStyle` export usage | row inline | DELETE export path | High |
| generic responsive `check-box` branch | mobile wrapper rules | DELETE | High |
| `resolveRadioGroupPresentationCssRules` name | Radio-named resolver | DEFER rename | Low |

---

## 29. renderCss property classification

| Property | Proposed owner | Classification | Allowed in renderCss? |
| -------- | -------------- | -------------- | --------------------- |
| wrapper margin/width | block wrapper | MERCHANT PRESENTATION | YES |
| group padding/border/radius/bg/shadow | group scope | MERCHANT PRESENTATION | YES |
| layout/gap | group scope | MERCHANT PRESENTATION | YES |
| option label typography | option label selector | MERCHANT PRESENTATION | YES |
| static group label | `> label` | MERCHANT PRESENTATION | YES |
| CSS variables accent/outline/bg | group scope | MERCHANT PRESENTATION | YES |
| accent-color on input | input selector | MERCHANT PRESENTATION | YES (if not var-only) |
| row flex 8px | sheet | REUSABLE STRUCTURE | **NO** |
| appearance/checkmark | sheet | NATIVE CONTROL MECHANIC | **NO** |

---

## 30. renderCss dumping-ground verdict

**Would proposed Checkbox migration turn renderCss into a dumping ground?** **NO**

Merchant presentation only on wrapper/group/labels/tokens; mechanics stay in `checkbox.export.css`.

---

## 31. Consumer completeness

| Consumer | HTML | renderCss | Native sheet | Runtime | Complete? |
| -------- | ---- | --------- | ------------ | ------- | --------- |
| Builder preview | Yes | via artifact mount | hoist embedded | preview interaction | **Yes** |
| Fullscreen preview | Yes | same | same | same | **Yes** |
| Thumbnails | Yes | `popupThumbnailIframe` | hoist | — | **Yes** |
| Storefront embed | Yes | `mountRenderArtifactCss` | hoist | `formValidation` | **Yes** |
| Multi-step | Yes | compile per artifact | same | same | **Yes** |
| Gamification | N/A | — | — | — | **N/A** (no check-box in gamification templates) |

**Consumer completeness: PASS** — no consumer bypass blocker.

---

## 32. Chromium acceptance test plan (for implementation)

**Compiler unit:** normalize, dedupe, conflict, DIFF-ONLY mobile, negative generic responsive.

**Serializer:** no merchant inline on group/labels/inputs; structural row class only.

**Browser computed-style:**

- unchecked/checked desktop + mobile
- vertical + horizontal layout
- group padding mobile on **group owner** (split-target regression)
- option label font-size mobile on **label owner**
- wrapper margin/width
- group field label
- artifact isolation
- hostile-theme (new — no test exists today)
- label click + keyboard + required validation
- multiple options independent checked states

---

## 33. Phase 0 implications

**Phase 0 expected effect:** **None on existing 18 cases** — Checkbox not in baseline matrix. Optional future Phase 0 case for checkbox mobile padding after migration.

---

## 34. G1 implications

**G1 required: YES** — new `checkbox.export.css` triggers raw-source webpack contract (same as Radio).

---

## 35. Existing unrelated debt status

| Item | Status |
| ---- | ------ |
| `popupEmbedRuntime.test.ts` (4 failures) | **STILL PRESENT** |
| `popupProductionCssRteOwnership.audit.test.ts` (2 failures) | **STILL PRESENT** |
| Phone flag-without-URL | **STILL OPEN** |

---

## 36. Implementation readiness verdict

All readiness gates satisfied.

**Implementation readiness: READY FOR IMPLEMENTATION**

**Pre-implementation decision (not blocking audit):** Wire `outlineColor` / `backgroundColor` via checkbox sheet + vars, or explicitly scope 6H to `fillColor` only and remove dead panel traits in a follow-up cleanup.

---

## 37. Exact recommended implementation boundary

### Model → renderCss

- Wrapper: margin, width, element position
- Group scope (new `.pbn-export-native-checkbox-scope`): padding, border, radius, background, shadow, min-height, flex direction, gap, `--pbn-checkbox-accent`, `--pbn-checkbox-outline`, `--pbn-checkbox-bg`
- Option labels: typography traits
- Group field label: static label rules
- Input: `accent-color` **or** sheet-only var consumption (pick one; prefer var + sheet for parity)

### Structure-only export HTML

- Wrapper: identity + validation attrs + structural `flex-shrink:0` only
- Group: scope class, no merchant inline
- Rows: `.pbn-display-checkbox-row` class (mirror radio row pattern)
- Inputs: type, name, value, id, checked — **no merchant inline**

### New `checkbox.export.css`

- Row flex structure
- `appearance:none` + square checkbox + checkmark (if custom parity)
- Hostile-theme `!important` on defended properties consuming vars
- **Do not** use `radio.export.css`

### Runtime unchanged

- `formValidation.ts` choice block logic
- Multi-select semantics
- `name={fieldName(element)}` shared across options

### Exclude from `MIGRATED_BLOCK_TRAIT_TYPES` addition

- Add `'check-box'` after renderCss proven

### Do not reopen

- `radioPresentation.ts`
- `radio.export.css`

---

## 38. Recommended next phase name

**Phase 6H — Checkbox Merchant Presentation renderCss**

---

## Appendix — Required final answer format

1. Audit-only phase respected: **YES**
2. Production code changed: **NO**
3. Tests changed: **NO**
4. Checkbox-related production block families found: **1**
5. Checkbox block types: **`check-box`**
6. Checkbox identity owner: **`[data-adflipr-block-id]` wrapper**
7. Checkbox wrapper presentation owner: **inline wrapper today → renderCss wrapper (recommended)**
8. Checkbox group/container owner: **inner group `<div>` (no scope class today) → `.pbn-export-native-checkbox-scope` renderCss (recommended)**
9. Checkbox group-label owner: **`> label` (HTML only today → renderCss static label)**
10. Checkbox option-row owner: **plain `div` inline → sheet `.pbn-display-checkbox-row` (recommended)**
11. Checkbox native-control owner: **native `input[type=checkbox]` + `accent-color` inline**
12. Checkbox option-label owner: **option `<label>` inline typography**
13. Checkbox validation owner: **block wrapper attrs + `formValidation.ts`**
14. Checkbox architecture same as Radio: **PARTIAL**
15. Radio pattern reuse: **YES**
16. Radio direct code reuse recommended: **PARTIAL** (resolvers yes; compiler/sheet no)
17. Checkbox merchant presentation fields: **wrapper margin/width, group padding/border/radius/bg/shadow/minH, layout, gap, option label typography, group label traits, fillColor (accent-color); outlineColor/backgroundColor in model but not exported**
18. Checkbox desktop merchant CSS source: **serializer inline (wrapper, group, labels, accent-color)**
19. Checkbox mobile merchant CSS source: **generic `buildBlockTraitResponsiveCss` on block wrapper**
20. Checkbox split-target defect: **YES**
21. Split-target properties: **padding, border-radius (when in model), font-size (option labels)**
22. Checkbox merchant inline debt: **high** (group shell + labels + accent)
23. Checkbox structural inline debt: **low** (row flex on each option)
24. Checkbox native-mechanic inline debt: **low** (`accent-color` only; no custom appearance)
25. Checkbox generic responsive merchant debt: **yes** (wrapper-targeted mobile rules)
26. Checkbox dedicated native-control sheet exists: **NO**
27. Current native-control CSS source: **browser default + inline `accent-color`**
28. Recommended sheet strategy: **C**
29. Merchant tokens can be owned once: **YES**
30. Proposed merchant token owner: **group scope `.pbn-export-native-checkbox-scope` in renderCss**
31. Proposed merchant CSS variables: **`--pbn-checkbox-accent`, `--pbn-checkbox-outline`, `--pbn-checkbox-bg`**
32. Hostile-theme declarations audited: **0**
33. Unknown hostile-theme ownership remaining: **NONE**
34. Internal cascade-repair !important found: **NO**
35. Radio hostile-theme pattern reusable: **PARTIAL**
36. Group-label normalization decision: **A**
37. Group-label canonical resolver: **`resolveStandardFieldStaticLabelCssRules`**
38. Option-label normalization decision: **B** (reuse resolver; neutral rename deferred)
39. Option-label resolver recommendation: **`resolveRadioOptionLabelCssRules` (alias to choice-option in 6H)**
40. Group/container normalization decision: **B**
41. Group resolver recommendation: **`resolveRadioGroupPresentationCssRules` (shared resolver; checkbox-specific compiler)**
42. Existing wrapper normalization reusable: **YES**
43. Compiler topology recommendation: **A**
44. Recommended compiler: **`checkboxPresentation.ts`**
45. Wrapper mobile strategy: **DIFF-ONLY**
46. Group mobile strategy: **DIFF-ONLY**
47. Option-label mobile strategy: **DIFF-ONLY**
48. Group-label mobile strategy: **NO MOBILE RULE** (no device-paired label traits)
49. Native-control mobile strategy: **NO MOBILE RULE**
50. Presentation identity key: **`normalizeCheckboxPresentation()` JSON**
51. Presentation conflict key: **same as identity key**
52. Recommended conflict error: **`CheckboxPresentationConflictError`**
53. Runtime semantics changed by proposed migration: **NO**
54. Single toggle behavior owner: **native input + canvas preview handlers**
55. Multiple selection behavior owner: **independent native inputs (shared `name`)**
56. Label-click behavior owner: **native `label for` + canvas**
57. Keyboard behavior owner: **canvas row handlers (builder/preview)**
58. Required validation owner: **`formValidation.ts` block-level choice validator**
59. Submission value owner: **native checkbox `name`/`value`/`checked`**
60. Radio reuse boundary summary: **Reuse pattern + ElementStyles resolvers; not Radio compiler or radio.export.css; new checkbox.export.css for native mechanics**
61. Pattern reuse vs code reuse verdict: **Pattern YES; code PARTIAL**
62. Inline paths expected to delete: **`serialize.ts` group/label/row/input merchant inline; wrapper merchant margin/width after renderCss**
63. Generic responsive paths expected to delete: **`buildBlockTraitResponsiveCss` rules for `check-box`**
64. Native/structural paths expected to retain: **new `checkbox.export.css` row flex + checkbox mechanics; wrapper structural flex-shrink**
65. Shared abstraction extraction recommended now: **NO**
66. renderCss dumping-ground risk: **NO**
67. Consumer completeness: **PASS**
68. Blocking consumer defects: **none**
69. Phase 0 expected effect: **none on existing 18 cases**
70. G1 required: **YES**
71. Existing embed failures status: **STILL PRESENT (4)**
72. Existing Text audit failures status: **STILL PRESENT (2)**
73. Phone flag-without-URL status: **STILL OPEN**
74. Important UNKNOWN ownership remaining: **NONE**
75. Implementation readiness: **READY FOR IMPLEMENTATION**
76. Exact recommended migration boundary: **§37 above**
77. Verdict: **ACCEPT**
78. Recommended next phase name: **Phase 6H — Checkbox Merchant Presentation renderCss**
