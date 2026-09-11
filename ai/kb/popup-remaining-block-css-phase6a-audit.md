# Popup Remaining Block CSS — Phase 6A Audit

**Status:** Audit only — no production changes  
**Date:** 2026-07-05  
**Scope:** Remaining Popup Builder block families after Phase 0 responsive baseline closure  
**Authoritative source:** Production code + Chromium diagnostics (not historical KB)

---

## A. Executive summary

Phase 0 responsive baseline is **closed** (18/0/5). Ten presentation families are migrated to artifact-scoped `renderCss`. **Seventeen content block types** still emit merchant presentation inline and depend on embedded `buildBlockTraitResponsiveCss` (plus family-specific embedded generators for phone, product, choice controls).

The proposed “single Input family” spanning `text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, and `date` is **PARTIALLY VALID** only:

| Group | Types | Verdict |
| ----- | ----- | ------- |
| Standard text-like | `text-input`, `email-input` | **Same DOM topology** — one compiler |
| Textarea | `textarea` | **Related** — shared outline helpers; different height/inner owner |
| Select | `dropdown` | **Separate** — native `<select>` painted owner |
| Date | `date` | **Standard topology** via `serializeTextInputLike`; **separate** native-control semantics |
| Phone | `phone-input` | **Separate compound family** — country selector, flag, divider, dedicated responsive CSS |

**Critical finding:** All input-like blocks exhibit **split-target responsive defects** identical to pre-5D Text: desktop padding/border-radius/font on **outline or field**; mobile `buildBlockTraitResponsiveCss` targets **wrapper** `[data-adflipr-block-id]`. Chromium proves mobile traits do not reach painted elements.

**Teaser:** **TEASER FULLY CLOSED** for style-only chip. Builder-block teaser is normal step export — no hidden second Teaser CSS architecture.

**Phase 6B recommendation:** Migrate **Standard Label Field** family first — `text-input` + `email-input` (non-floating topology only). Highest architecture value, clearest ownership, smallest blast radius.

---

## B. Current architecture verification

### B.1 Deleted generators — zero production references

| Symbol | Production refs |
| ------ | ----------------- |
| `buildMobilePositionOverrideRules` | **0** |
| `buildTeaserTraitResponsiveCss` | **0** |

Verified via repo-wide grep (2026-07-05).

### B.2 Phase 0 regression contract

```bash
cd adflipr-app && npm run test:popup-responsive-baseline
```

**Result: PASS**

| Metric | Count |
| ------ | ----: |
| Migrated regression green | **18** |
| Original risk red | **0** |
| Controls green | **5** |
| Total Jest tests | **25** |

### B.3 `buildBlockTraitResponsiveCss` — authoritative consumer list

**Definition:** `responsiveDeviceCss.ts` — internal function; emitted via `buildEmbeddedStepResponsiveCss()`.

**Skipped inside loop:** `isLayout`, `cross`, `floating-image`, `button`, `text`.

**Current consumers (17 content block types):**

```text
divider
text-input
email-input
phone-input
textarea
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

**Difference from prior report:** **None** — list matches exactly.

**Additional embedded responsive generators (not `buildBlockTraitResponsiveCss`):**

| Generator | Trigger | `!important` |
| --------- | ------- | ------------ |
| `buildPhoneInputCountrySelectResponsiveCss` | any `phone-input` block | **Yes** — `max-width` on `select` |
| `buildProductHorizontalResponsiveCss` | horizontal product layout | **Yes** |
| `buildProductGridResponsiveCss` | product grid column diff | **Yes** |
| `buildBlockDeviceVisibilityCss` | hide-on-device flags | **Yes** — `display:none` |
| `buildTeaserExportDeviceCss` | teaser hide flags only | via visibility helper |

---

## C. Teaser variant closure audit

### C.1 Trace

```text
Teaser model (formStyle + optional formElements['teaser'])
    ↓
teaser type/mode decision
    ├─ styleOnlyTeaser: teaser step has 0 blocks AND hasTeaserStyleContent(formStyle)
    └─ builder-block: teaser step has ≥1 FormElement blocks
    ↓
style-only serialization → serializeTeaserHtml(formStyle) — chip div + TEASER_EXPORT_CSS
builder-block serialization → serializeStepHtml(teaser elements) — normal block wrappers
    ↓
runtime mounting → .adflipr-teaser-host (same host both modes)
    ↓
CSS source
    ├─ style-only chip paint → compileTeaserPresentationRenderCss (renderCss) when styleOnlyTeaser
    ├─ style-only shell → TEASER_EXPORT_CSS embedded in teaser HTML (runtime infrastructure)
    └─ builder-block → per-block inline + embedded step CSS; NO teaser chip compiler
```

### C.2 Answers

| # | Question | Answer |
| - | -------- | ------ |
| 1 | What is a "style-only teaser"? | Teaser step empty; chip HTML from `formStyle` (image + text); `serializeTeaserHtml()` |
| 2 | What is a "builder-block teaser"? | Teaser step contains normal builder blocks; serialized via `serializeStepHtml()` |
| 3 | Can both exist in production? | **Yes** — mutually exclusive per popup (empty vs populated teaser step) |
| 4 | Builder-block uses normal serializers? | **Yes** — `registerPopupBlockSerializers` + migrated compilers (button, text, etc.) |
| 5 | Teaser-owned presentation outside renderCss? | **Style-only only** — chip was inline; now renderCss. Builder-block has no formStyle chip |
| 6 | Same runtime host? | **Yes** — `.adflipr-teaser-host` |
| 7 | Old embedded responsive CSS? | **No** for chip traits. `buildTeaserExportDeviceCss` = hide rules only |
| 8 | Hidden second Teaser CSS architecture? | **NONE** — `TEASER_EXPORT_CSS` is runtime shell/animation infrastructure |
| 9 | `compileTeaserPresentationRenderCss` skip correct? | **Yes** — `snapshotUsesStyleOnlyTeaser()` gates compiler |
| 10 | Future Teaser migration required? | **No** for CSS architecture. Canvas duplicate paths are builder-only |

### C.3 Verdict

**TEASER FULLY CLOSED**

Builder-block teaser is not a separate CSS family — it is a normal export step. No follow-up migration unless product adds overlay chip + blocks simultaneously (not current behavior).

---

## D. Authoritative block-type inventory

### D.1 Totals

| Metric | Count |
| ------ | ----: |
| Layout block types | 4 (`one-column`, `two-column`, `three-column`, `section`) |
| Content block types | 21 |
| **Total palette blocks** | **25** |
| Fully migrated (renderCss presentation) | **10 families** covering **4 content types + layouts/surface** |
| Remaining inline merchant CSS (content) | **17** |
| Remaining embedded responsive (`buildBlockTraitResponsiveCss`) | **17** |
| Runtime-special content types | **cross** (migrated), **spinner** (gamification surface migrated; block inline remains), **phone-input** (runtime country change) |

### D.2 Full inventory table

| Block type | Serializer | Canvas | Trait UI | Inline merchant CSS | Embedded responsive | renderCss compiler | Runtime CSS | DOM complexity | Recommended family |
| ---------- | ---------- | ------ | -------- | ------------------- | ------------------- | ------------------ | ----------- | -------------- | ------------------ |
| one-column / two-column / three-column / section | `serializeLayout` | layout components | layout traits | No (structure) | No | **MIGRATED** columns/outer | minimal | High | Layout (done) |
| text | `serializeText` | TextCanvas | text panel | No | No | **MIGRATED** | minimal | Medium | Text (done) |
| button | `serializeButton` | ButtonCanvas | button panel | No | No | **MIGRATED** | minimal | Low | Button (done) |
| cross | `serializeCross` | CrossCanvasBlock | cross panel | No | No | **MIGRATED** positioned-decorative | close behavior | Low | Positioned (done) |
| floating-image | `serializeFloatingImage` | FloatingImageCanvas | floating panel | No | No | **MIGRATED** positioned-decorative | minimal | Low | Positioned (done) |
| teaser chip | `serializeTeaserHtml` | N/A (formStyle) | teaser settings | No (chip) | hide only | **MIGRATED** teaserPresentation | TEASER_EXPORT_CSS shell | Low | Teaser (done) |
| divider | `serializeDivider` | DividerCanvas | divider panel | **Yes** outer+line | **Yes** wrapper | No | minimal | Medium | Divider |
| text-input | `serializeTextInputLike` | TextInputCanvas | input traits | **Yes** outline+field+label | **Yes** wrapper | No | validation | Medium | Standard Field |
| email-input | `serializeTextInputLike` | EmailInputCanvas | input traits | **Yes** | **Yes** wrapper | No | validation | Medium | Standard Field |
| phone-input | `serializePhoneInput` | PhoneInputCanvas | phone traits | **Yes** compound | **Yes** + phone select | No | validation+country | **High** | Phone Compound |
| textarea | `serializeTextarea` | TextareaCanvas | textarea traits | **Yes** outline+textarea | **Yes** wrapper | No | validation | Medium | Textarea Field |
| dropdown | `serializeDropdown` | DropdownCanvas | dropdown traits | **Yes** outline+select | **Yes** wrapper | No | validation | Medium | Select Field |
| check-box | `serializeCheckboxGroup` | CheckboxCanvas | choice traits | **Yes** group+options | **Yes** wrapper | No | validation | Medium | Choice Control |
| radio | `serializeRadioGroup` | RadioCanvas | choice traits | **Yes** + radio.export.css | **Yes** wrapper | No | validation | Medium | Choice Control |
| date | `serializeDate` → `serializeTextInputLike` | DateCanvas | date traits | **Yes** | **Yes** wrapper | No | validation+native picker | Medium | Date Field |
| timer | `serializeTimer` | TimerCanvas | timer traits | **Yes** wrapper+inner | **Yes** wrapper | No | countdown JS | High | Timer |
| spinner | `serializeSpinner` | SpinnerCanvas | spinner traits | **Yes** | **Yes** wrapper | gamification surface | spin JS | High | Spinner/Gamification |
| image | `serializeImage` | ImageCanvas | image traits | **Yes** container+img | **Yes** wrapper | No | minimal | Medium | Media |
| video | `serializeVideo` | VideoCanvas | video traits | **Yes** | **Yes** wrapper | No | embed attrs | Medium | Media |
| product | `serializeProduct` | ProductCanvas | product traits | **Yes** compound | **Yes** + product CSS | No | minimal | **High** | Commerce Product |
| coupon | `serializeCoupon` | CouponCanvas | coupon traits | **Yes** | **Yes** wrapper | No | copy JS | Medium | Commerce Coupon |
| feedback | `serializeFeedback` | FeedbackCanvas | feedback traits | **Yes** | **Yes** wrapper | No | rating JS | Medium | Feedback |
| social | `serializeSocial` | SocialCanvas | social traits | **Yes** | **Yes** wrapper | No | link behavior | Low | Social |

**Block type count ≠ CSS family count.** Example: `text-input` and `email-input` share a family; `phone-input` does not.

---

## E. Remaining `buildBlockTraitResponsiveCss` consumers

See §B.3. Properties emitted per block:

| Property | Selector target | Desktop inline owner | `!important` |
| -------- | --------------- | -------------------- | ------------ |
| padding | `[data-adflipr-block-id]` | **outline** (`.pbn-canvas-input-shell`) for inputs | No |
| margin | `[data-adflipr-block-id]` | **wrapper** | No |
| border-radius | `[data-adflipr-block-id]` | **outline** for inputs | No |
| font-size | `[data-adflipr-block-id]` | **field** (`input`/`textarea`/`select`) for inputs | No |

**Architectural status for input-like blocks:** **SPLIT TARGET** on padding, border-radius, font-size. Margin is **SAFE** (same wrapper owner).

---

## F. Input candidate pipeline trace

Common pipeline for all candidates:

```text
FormElement model (properties.style)
    ↓
trait configuration (shared input trait panels)
    ↓
canvas component (per-type Canvas; shared helpers)
    ↓
shared style helpers (ElementStyles, inputFieldStyles, layoutStyles)
    ↓
serializer (per-type or serializeTextInputLike)
    ↓
serializeElement wrapper (getExportWrapperStyleFromLayout)
    ↓
serialized HTML (inline styles on wrapper / outline / field)
    ↓
embedded responsive CSS (buildBlockTraitResponsiveCss + specials)
    ↓
runtime behavior (formValidation, floatingLabel init, phone country)
```

---

## G. Exact DOM topology — text-input (standard label mode)

```text
[data-block-type="text-input"][data-adflipr-block-id="{id}"]     ← wrapper (layout margin/width)
├── <label for="{name}>                                           ← label owner (static label mode)
└── <div class="pbn-canvas-input-shell" style="outline...">        ← outline/painted shell owner
    └── <input type="text" style="field typography...">           ← field owner
```

**Floating label mode** (when `floatingLabel` trait):

```text
[data-block-type="text-input"][data-adflipr-block-id="{id}"]
└── <div class="pbn-canvas-input-shell pbn-canvas-input-shell--floating">
    └── <div class="pbn-floating-field" data-pbn-floating-field="true">
        ├── <input class="pbn-canvas-input-inner" id="{name}">
        └── <label class="pbn-floating-label">
```

---

## H. Exact DOM topology — email-input

**Identical to text-input** except:

- `<input type="email">`
- `data-adflipr-field-invalid-text` attr for email validation

Serializer: same `serializeTextInputLike(element, ctx, 'email', placeholder)`.

---

## I. Exact DOM topology — phone-input

### Standard label + country selector

```text
[data-block-type="phone-input"][data-adflipr-block-id="{id}"]
├── <label>                                                        ← label owner
└── <div class="pbn-canvas-input-shell" style="outline...">
    └── <div style="row flex">                                     ← row owner
        ├── <div style="country wrap">                             ← country cluster owner
        │   ├── <img data-pbn-phone-flag>
        │   ├── <select name="{name}_country" style="opacity:0; absolute...">
        │   └── (no close — divider follows)
        ├── <div style="divider 1px">                            ← divider owner
        └── <input type="tel" name="{name}">                       ← phone field owner
```

### Floating label mode

```text
[data-block-type="phone-input"][data-adflipr-block-id="{id}"]
└── <div class="pbn-canvas-input-shell pbn-canvas-input-shell--floating">
    └── <div class="pbn-floating-field pbn-floating-field--phone">
        ├── <div class="pbn-floating-field__row">
        │   ├── (country cluster)
        │   └── <input class="pbn-canvas-input-inner" id="{name}">
        └── <label class="pbn-floating-label">
```

**Compound:** country selector, flag, divider, tel field — **not** standard single-outline family.

---

## J. Exact DOM topology — textarea

```text
[data-block-type="textarea"][data-adflipr-block-id="{id}"]
├── <label for="{name}>                                           ← label owner
└── <div class="pbn-canvas-input-shell pbn-canvas-input-shell--textarea">
    └── <textarea style="min-height/height + typography">          ← painted field owner (height here)
```

**No floating label path.** Height trait on **textarea**, not wrapper (proven by `popupHtmlSerializer.test.ts`).

---

## K. Exact DOM topology — dropdown

```text
[data-block-type="dropdown"][data-adflipr-block-id="{id}"]
├── <label>                                                        ← label owner
└── <div class="pbn-canvas-input-shell">
    └── <select name="{name}" style="select typography...">       ← native select painted owner
        └── <option>...
```

Uses `buildDropdownFieldStyles()` — shared outline helper, **select-specific** inner owner.

---

## L. Exact DOM topology — date

**Same tree as text-input standard mode** via `serializeDate` → `serializeTextInputLike(..., 'date', placeholder, { min, max })`.

```text
[data-block-type="date"][data-adflipr-block-id="{id}"]
├── <label>
└── <div class="pbn-canvas-input-shell">
    └── <input type="date" min="..." max="...">
```

Native `type="date"` — browser picker indicator, locale formatting, mobile OS picker behavior.

---

## M. Input property ownership matrices

### M.1 Standard label field (`text-input` / `email-input`)

| Property | Model field | Desktop emission | Mobile emission | DOM owner | Same D/M owner? | Merchant? | renderCss candidate? |
| -------- | ----------- | ---------------- | --------------- | --------- | --------------- | --------- | -------------------- |
| margin | marginDesktop/Mobile | wrapper inline | wrapper `@media` | wrapper | **Yes** | Yes | Yes |
| padding | paddingDesktop/Mobile | outline inline | wrapper `@media` | **split** | **No** | Yes | Yes — outline |
| border-radius | cornerRadius* | outline inline | wrapper `@media` | **split** | **No** | Yes | Yes — outline |
| background | backgroundColor | outline inline | (none) | outline | Yes | Yes | Yes |
| border | borderStyle/Width/Color | outline inline | (none) | outline | Yes | Yes | Yes |
| box-shadow | boxShadow* | outline inline | (none) | outline | Yes | Yes | Yes |
| font-size (value) | fontSize / input size | field inline | wrapper `@media` | **split** | **No** | Yes | Yes — field |
| font-family, color, etc. | typography traits | field inline | (none) | field | Yes | Yes | Yes — field |
| label color/size | labelColor, labelFontSize | label inline | (none) | label | Yes | Yes | Yes — label |
| placeholder color | placeholderColor | CSS var on outline | (none) | outline | Yes | Yes | Yes — outline vars |
| height | height | outline (applyInputHeightTraitToShell) | (none) | outline | Yes | Yes | Yes |
| width/fullWidth | fullWidth, elementWidthPx | wrapper inline | (none) | wrapper | Yes | Yes | Yes — wrapper |

### M.2 Textarea — deltas from standard

| Property | DOM owner | Notes |
| -------- | --------- | ----- |
| height / min-height | **textarea** | Not on wrapper |
| padding (shell) | outline | Same split-target defect |
| rows | textarea attr | structural |

### M.3 Dropdown — deltas

| Property | DOM owner | Notes |
| -------- | --------- | ----- |
| value typography | **select** | Native appearance |
| min-height | select | via `buildDropdownSelectStyle` |

### M.4 Date — deltas

Same as standard + `min`/`max` attrs (structural, not CSS). Native picker chrome **not** styleable consistently cross-browser.

### M.5 Phone — additional properties

| Property | DOM owner | Notes |
| -------- | --------- | ----- |
| country select max-width | hidden `select` | desktop 45% inline; mobile `buildPhoneInputCountrySelectResponsiveCss` |
| flag | `img[data-pbn-phone-flag]` | structural src |
| divider | divider div | hard-coded `#DEDFEA` |
| country cluster width | country wrap div | fixed `2.75rem` |

---

## N. Hidden split-target analysis

| Property | Desktop owner | Mobile owner | Classification |
| -------- | ------------- | ------------ | -------------- |
| padding | outline (12px) | wrapper (4px) | **SPLIT TARGET — OWNERSHIP DEFECT** |
| margin | wrapper | wrapper | **ALREADY SAFE** |
| border-radius | outline (8px) | wrapper (4px) | **SPLIT TARGET — OWNERSHIP DEFECT** |
| font-size | field inline (16px) | wrapper (12px) | **SPLIT TARGET — OWNERSHIP DEFECT** (field unchanged) |
| box-shadow | outline | (not in buildBlockTraitResponsiveCss) | **NOT RESPONSIVE** via generic generator |
| width/height | wrapper / field | (not generic) | **NOT RESPONSIVE** / type-specific |

Applies to: `text-input`, `email-input`, `textarea`, `dropdown`, `date`, `phone-input` (font-size defect on tel field).

---

## O. Generic responsive generator analysis

| Block family | Responsive property | Desktop owner | Mobile owner | `!important` | Current result | Status |
| ------------ | ------------------- | ------------- | ------------ | ------------ | -------------- | ------ |
| Input-like | padding | outline | wrapper | No | outline keeps desktop padding on mobile | **SPLIT TARGET** |
| Input-like | margin | wrapper | wrapper | No | mobile margin applies | **SAFE** |
| Input-like | border-radius | outline | wrapper | No | outline keeps desktop radius | **SPLIT TARGET** |
| Input-like | font-size | field | wrapper | No | field keeps desktop size | **SPLIT TARGET** |
| divider/timer/image/... | same four props | varies | wrapper | No | likely split for any inner-painted block | **SPLIT TARGET** (medium risk) |

---

## P. Input-family grouping decision

**Decision: B — Standard input family + Phone family + Select family (+ Textarea sub-family)**

| Type | DOM topology group | Painted owner group | Responsive semantics | Behavior/state | Compiler family |
| ---- | ------------------ | ------------------- | -------------------- | -------------- | --------------- |
| text-input | A: wrapper→label→outline→input | outline+field | shell padding/radius; field font | validation attrs | **Standard Field** |
| email-input | A (identical) | outline+field | same | email invalid | **Standard Field** |
| textarea | B: wrapper→label→outline→textarea | outline+textarea | shell padding; field font/height | validation | **Textarea Field** |
| dropdown | C: wrapper→label→outline→select | outline+select | shell padding; select font | validation | **Select Field** |
| date | A (via text-like) | outline+input[type=date] | same as standard + native | date bounds | **Date Field** (extends standard compiler) |
| phone-input | D: compound row | outline+row+country+field | + country max-width | tel validation | **Phone Compound** |

Shared model helpers (`getElementStyle`, `inputOutlineStyle`) are **necessary but not sufficient** for one compiler.

---

## Q. Interactive state ownership

| State | Merchant presentation? | Artifact structure? | Behavior/runtime? | Current location |
| ----- | ---------------------- | ------------------- | ------------------- | ---------------- |
| default | Yes | — | — | inline + outline |
| hover | Partial | — | browser | browser default |
| focus / focus-visible | Yes (focusBorderColor) | — | — | `floating-label.export.css` `:focus-within` on shell (**`!important`**) |
| valid | — | — | runtime | — |
| invalid / error | Partial (merchant messages) | — | **runtime** | `formValidation.ts` + `embed-responsive.export.css` |
| disabled | — | — | not implemented | — |
| readonly | — | — | — | — |
| placeholder | Yes (placeholderColor) | — | — | CSS var `--pbn-canvas-input-placeholder` on outline |
| autofill | — | — | browser | browser |

**Eventually in renderCss:** default merchant paint (outline, field, label), focus presentation (move from global sheet to artifact-scoped).  
**Remain behavior CSS:** error shell class toggle, error message visibility, floating-label JS float state.  
**Hard-coded today:** error color `#ef4444`, divider `#DEDFEA`, focus default `#4f46e5`.

---

## R. Phone-input special architecture

### `buildPhoneInputCountrySelectResponsiveCss`

| Question | Answer |
| -------- | ------ |
| Merchant presentation? | **Partial** — max-width is layout constraint on country control |
| Artifact structure? | No |
| Runtime infrastructure? | No |
| Belongs in phone compiler? | **Yes** — scoped `[data-block-type="phone-input"] select` inside artifact |
| Separate owner compiler? | Part of **Phone Compound** family |
| Global selector leak? | **Yes today** — `form[data-adflipr-popup-export] [data-block-type="phone-input"] select` not artifact-scoped |
| Multi-artifact safe? | **Risk** — form-scoped selector can affect multiple exports on same page |

---

## S. Dropdown/date native-control architecture

### Dropdown (`<select>`)

- Styles: **wrapper** (layout) + **outline** (chrome) + **select** (typography, transparent bg)
- Native: dropdown arrow, option list — **OS/browser controlled**
- `appearance` not reset on select (unlike radio custom CSS)
- Mobile: native picker sheet on iOS/Android

### Date (`<input type="date">`)

- Same chrome stack as text input
- Native: calendar indicator, `min`/`max` ISO bounds (`getPbnDateFieldRangeBounds`)
- Placeholder is synthetic text hint (browser may ignore on type=date)
- Cross-browser: WebKit indicator styling differs; mobile uses OS date wheel

**Portability:** Compiler must not assume Chromium desktop picker alone.

---

## T. Validation/error ownership

```text
Model (required, requiredText, invalidText)
    ↓
Serializer — attrs on input/select/textarea + block attrs for choice groups
    ↓
Export HTML — no error element pre-rendered
    ↓
Runtime formValidation.ts on submit
    ↓
Mutations:
  - .pbn-export-field-error-shell on .pbn-canvas-input-shell
  - <p data-adflipr-field-error class="pbn-export-field-error"> appended to [data-block-type] wrapper
```

| Aspect | Owner |
| ------ | ----- |
| Error border/shadow presentation | **outline shell** via `.pbn-export-field-error-shell` |
| Error text | **block wrapper** child `[data-adflipr-field-error]` |
| Error CSS source | `embed-responsive.export.css` (global form scope, **`!important`**) |
| Merchant-configurable? | Message text yes; error color **no** (hard-coded) |
| renderCss candidate | Merchant focus/error **colors** eventually; state toggle stays runtime |

---

## U. Canonical normalization duplication map

| Trait / concern | Canvas resolver | Serializer resolver | Responsive resolver | Duplicate? | Drift risk | Classification |
| --------------- | --------------- | ------------------- | ------------------- | ---------- | ---------- | -------------- |
| Shell chrome (bg, border, radius, shadow) | `getElementStyle` + `buildCanvasInputShellStyles` | `inputOutlineStyle` / `getInputShellTraitStyle` | `buildBlockTraitResponsiveCss` on **wrong node** | **Yes** | **High** | CANONICAL: `getElementStyle`; responsive is **WRONG OWNERSHIP** |
| Field typography | `getInputFieldTypographyStyle` | `innerInputFieldStyle` | wrapper font-size rule | Partial | **High** | CANONICAL: `getInputFieldTypographyStyle` |
| Dropdown shell+select | `buildDropdownFieldStyles` | `buildDropdownFieldStyles` | generic wrapper | Shared | Medium | **CANONICAL DOMAIN NORMALIZER** (good pattern) |
| Wrapper layout margin/width | `getBlockLayoutStyle` | `getExportWrapperStyleFromLayout` | wrapper margin in generic | Shared | Low | CANONICAL |
| Floating label structure | `CanvasFloatingLabelField` | `inputFieldMarkup` + `floating-label.export.css` | n/a | Split canvas/CSS sheet | Medium | CANVAS ADAPTER + export sheet |
| Phone country layout | `PhoneInputCanvas` | `serializePhoneInput` (duplicated inline styles) | `buildPhoneInputCountrySelectResponsiveCss` | **Yes** | **High** | Needs phone canonical normalizer |
| Label static | `CanvasFieldLabel` | `renderFieldLabel` / `buildFieldLabelStyle` | n/a | Shared helpers | Low | CANONICAL |

**Future canonical boundary:** One normalizer module per family → `resolveXPresentation(formStyle, device)` → canvas adapter + `compileXRenderCss` + serializer structural HTML only.

---

## V. Remaining family medium-depth inventory

| Family | Topology summary | Painted owner | Responsive props (generic) | Inline CSS | Embedded CSS | Runtime complexity | Split-target risk | Priority |
| ------ | ---------------- | ------------- | -------------------------- | ---------- | ------------ | ------------------ | ----------------- | -------- |
| **Divider** | wrapper→outer→line | line + outer | padding/margin/radius/font | Yes | generic | Low | Medium (inner line) | Medium |
| **Choice** (checkbox/radio) | wrapper→label→group→rows | group div + inputs | generic + radio.export.css | Yes | generic + `!important` radio | validation | Medium | Medium |
| **Timer** | wrapper→flex shell→units | inner units container | generic | Yes heavy | generic + embed guards | countdown | Medium | Low |
| **Spinner** | gamification col + spinner | spinner slices | generic | Yes | generic | spin + email gate | Medium | Low |
| **Image** | wrapper→container→img[/a] | img | generic | Yes | generic | link | Medium | Medium |
| **Video** | wrapper→embed/video | video/iframe | generic | Yes | generic | autoplay attrs | Medium | Medium |
| **Product** | wrapper→shell grid→cards | shell, card, button, image | generic + horizontal/grid `!important` | Yes heavy | multiple | Low | High (compound) | High (after inputs) |
| **Coupon** | wrapper→code display | inner text/button | generic | Yes | generic | copy | Medium | Medium |
| **Feedback** | wrapper→radiogroup stars | star buttons | generic | Yes | generic | rating JS | Medium | Low |
| **Social** | wrapper→icon links | anchors/svgs | generic | Yes | generic | links | Low | Low |

---

## W. Final CSS family taxonomy

| # | Family | Types | Shared topology | One compiler rationale |
| - | ------ | ----- | --------------- | ---------------------- |
| 1 | Standard Label Field | text-input, email-input (non-floating) | wrapper→label→outline→input | Identical serializers; same owners |
| 2 | Floating Label Field | text-input, email-input, phone (floating mode) | outline→floating-field→input | Shared floating sheet + vars; phone adds row |
| 3 | Textarea Field | textarea | wrapper→label→outline→textarea | Height on textarea; no floating |
| 4 | Select Field | dropdown | wrapper→label→outline→select | Native select owner |
| 5 | Date Field | date | same as standard + type=date | Reuse standard compiler + native notes |
| 6 | Phone Compound | phone-input | country+divider+tel | Compound DOM + country responsive |
| 7 | Choice Control | check-box, radio | wrapper→label→group | Shared choice semantics |
| 8 | Divider | divider | wrapper→outer→line | Unique line owner |
| 9 | Media | image, video | wrapper→container→media | Shared media helpers |
| 10 | Commerce Product | product | wrapper→shell→slots | Compound + product CSS |
| 11 | Commerce Coupon | coupon | wrapper→code | Simpler commerce |
| 12 | Timer | timer | wrapper→timer shell | Fixed-width fit logic |
| 13 | Spinner | spinner | gamification surface | Tied to gamification |
| 14 | Feedback | feedback | wrapper→radiogroup | Rating behavior |
| 15 | Social | social | wrapper→links | Simple icon row |

**Migrated (done):** Form Surface, Outer Layout, Columns Container, Column Presentation, Gamification surface, Button, Text, Cross, Floating Image, Teaser chip.

---

## X. Migration priority ranking

| Rank | Family | Rationale (weighted) |
| ---- | ------ | -------------------- |
| **1** | Standard Label Field (text-input, email-input) | Proven split-target; high visibility; clear owners; shared topology; removes generic consumer for 2 types |
| 2 | Textarea Field | Same defect pattern; height ownership already documented |
| 3 | Select Field (dropdown) | Native control boundary lesson before date |
| 4 | Date Field | Reuse standard compiler; native portability |
| 5 | Phone Compound | Highest DOM complexity; global selector leak; `!important` |
| 6 | Floating Label mode | Depends on moving `floating-label.export.css` to artifact scope |
| 7 | Choice Control | radio `!important` theme resets |
| 8 | Divider | Medium complexity; visible |
| 9 | Media (image, video) | Moderate inline volume |
| 10 | Commerce Product | High compound value but large blast radius |
| 11 | Commerce Coupon | Smaller commerce |
| 12 | Timer / Spinner | Specialized layout math |
| 13 | Feedback / Social | Lower responsive risk |

---

## Y. Exact Phase 6B recommendation

### Phase 6B scope

**Types included:**
- `text-input`
- `email-input`

**Topology included:**
- Standard label mode only (`floatingLabel` false or absent)
- wrapper → static `<label>` → `.pbn-canvas-input-shell` → `<input>`

**DOM owners included:**
- **Wrapper** `[data-adflipr-block-id]` — margin, width/maxWidth, element position
- **Outline** `.pbn-canvas-input-shell` — padding, background, border, border-radius, box-shadow, min-height, placeholder CSS vars
- **Field** `input` — font-size, font-family, color, line-height, letter-spacing, font-weight
- **Label** `label[for]` — label color, font-size, font-family, font-weight

**Properties included:**
- margin (wrapper)
- padding (outline)
- border-radius (outline)
- background, border, box-shadow (outline)
- height / min-height (outline via `applyInputHeightTraitToShell`)
- field typography (field)
- label typography (label)
- placeholder color (CSS variables on outline)
- width / fullWidth / elementPosition (wrapper)

**Responsive strategy:**
- Artifact-scoped `renderCss` with base rules on correct owners + `@media (max-width: 480px)` diff-only mobile overrides on **same owners**
- Delete wrapper-targeted padding/radius/font rules for these types from `buildBlockTraitResponsiveCss`

**State CSS included/excluded:**
- **Excluded:** focus (`:focus-within` — stays in sheet until Phase 6C/6D)
- **Excluded:** error/invalid presentation
- **Excluded:** floating label mode
- **Excluded:** disabled/readonly

**Old generator cleanup:**
- Remove `text-input` and `email-input` from `buildBlockTraitResponsiveCss` loop (or skip via migrated set)
- Strip inline merchant presentation from serializers/helpers for included owners

**Inline CSS to delete:**
- Outline shell merchant inline from `inputOutlineStyle` emission
- Field typography inline from `innerInputFieldStyle`
- Label inline from `buildFieldLabelStyle` / `renderFieldLabel`
- Wrapper margin retained in HTML only if structural; else move margin to renderCss

**Expected tests:**
- `inputFieldPresentationRenderCss.test.ts` (unit)
- `inputFieldPresentation.browser.test.ts` (Chromium computed — padding, radius, font-size on correct owners)
- Extend regression contract with 2–4 standard-field cases (optional Phase 6B closure)

**Explicit exclusions:**
- `phone-input`, `textarea`, `dropdown`, `date`
- Floating label topology
- Validation/error state CSS
- `buildPhoneInputCountrySelectResponsiveCss`
- Focus presentation migration

---

## Z. Risks and unresolved decisions

1. **Floating label global sheet** — artifact-scoping `floating-label.export.css` may be Phase 6C or parallel; blocks 6B if merchants use floating labels heavily.
2. **Focus `!important` in export sheet** — merchant `focusBorderColor` may conflict when moving to renderCss; needs explicit focus owner decision.
3. **Error presentation hard-coded** — merchant cannot brand error colors today; scope for later phase.
4. **Phone global selector** — must be artifact-scoped during phone migration.
5. **Canvas preview parity** — canvas must delegate to canonical normalizers (pattern from 5C/5D).
6. **block-padding baseline case** — uses button/text migrated blocks; does not guard input split-target (gap in regression contract).

---

## AA. Diagnostic browser evidence

Chromium diagnostic (2026-07-05) — HTML mirroring production serializer + `buildBlockTraitResponsiveCss` pattern:

### text-input

| Trait | Viewport | Owner | Expected mobile | Actual | Result |
| ----- | -------- | ----- | --------------- | ------ | ------ |
| padding | desktop | outline | 12px | 12px | baseline |
| padding | mobile | outline | 4px (trait) | **12px** | **FAIL — split target** |
| padding | mobile | wrapper | 4px | 4px | rule applies wrong node |
| font-size | mobile | field | 12px | **16px** | **FAIL — split target** |
| border-radius | mobile | outline | 4px | **8px** | **FAIL — split target** |

### textarea / dropdown / date / phone-input

Same padding and font-size split-target pattern on field/outline vs wrapper. Phone country `max-width`: desktop 45%, mobile 36% (`!important`) — **works** on select element.

---

## AB. Repository search evidence

| Pattern | Ownership finding |
| ------- | ----------------- |
| `buildBlockTraitResponsiveCss` | Single generator; 17 consumers; skips migrated text/button/cross/floating-image |
| `buildPhoneInputCountrySelectResponsiveCss` | Phone-only; form-scoped; `!important` |
| `!important` | Device hide, product layout, phone select, error shell, radio export, gamification mobile, teaser shell visibility |
| `data-block-type` | Identity on wrapper div (except cross/floating-image special serializers) |
| `data-adflipr-block-id` | Wrapper scope for responsive CSS |
| `pbn-canvas-input-shell` | Outline/painted shell for all input-like blocks |
| `aria-invalid` | Not set by export; runtime validation uses classes |
| `data-adflipr-field-error` | Runtime-injected error text |
| `field-error` / `pbn-export-field-error` | Error presentation classes |
| `placeholder` | input/textarea attr + CSS var `--pbn-canvas-input-placeholder` |
| `focus` | `:focus-within` on `.pbn-canvas-input-shell--floating` and export sheet |

---

## AC. Architecture gates

| Gate | Status | Notes |
| ---- | ------ | ----- |
| G0 — Phase 0 closed | **PASS** | 18/0/5 |
| G1 — DOM topology proven | **PASS** | Per-type trees in §G–L |
| G2 — Property ownership matrix | **PASS** | §M |
| G3 — Split-target defects | **PASS** | Found; §N, §AA |
| G4 — Generic responsive trace | **PASS** | §E, §O |
| G5 — Input family grouping | **PASS** | Decision B — §P |
| G6 — State separation | **PASS** | §Q |
| G7 — Phone special | **PASS** | §R |
| G8 — Native controls | **PASS** | §S |
| G9 — Validation ownership | **PASS** | §T |
| G10 — Canonical duplication | **PASS** | §U |
| G11 — Family taxonomy | **PASS** | §W |
| G12 — Priority ranking | **PASS** | §X |
| G13 — Phase 6B scope | **PASS** | §Y |
| Teaser closure | **PASS** | §C |

**Ready for Phase 6B implementation:** **YES** (Standard Label Field scope only)

---

## References

- `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/responsiveDeviceCss.ts`
- `adflipr-app/src/layouts/PopupBuilder/components/form-builder/blocks/shared/serializeFieldHelpers.ts`
- `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/styles/inline/inputFieldStyles.ts`
- `adflipr-app/src/layouts/PopupBuilder/utils/__tests__/blockDomOwnership.browser.test.ts`
- `ai/kb/popup-responsive-css-phase0-baseline.md`
