# Phase H2 — Radio vs Date Next-Family Selection Audit

**Date:** 2026-07-06  
**Mode:** READ-ONLY AUDIT — no production code or test changes  
**Scope:** Compare exactly two candidates — `radio` vs `date` — and select one next implementation family  
**References:** Phase 6E Phone acceptance, Phase 6B standard-field report, Phase 0 baseline, G1 export CSS contract, `popup-rendering-css-architecture.md`, prior cross-block H2 inventory (superseded on Date where noted below)

---

## 1. Executive verdict

Production code inspection proves **Date is already migrated** through the Phase 6B/6F standard-field compiler (`standardFieldPresentation.ts` includes `'date'`). Export is structure-only; merchant presentation lives in artifact-scoped `renderCss`; Date is excluded from generic `buildBlockTraitResponsiveCss`; eight Chromium ownership tests pass in `dateInputPresentation.browser.test.ts`.

**Radio remains unmigrated:** heavy serializer inline on radiogroup, option rows, labels, and each native input; generic embedded mobile trait CSS still targets the block wrapper while desktop merchant chrome lives on the inner radiogroup — a classic split-target cascade risk; `radio.export.css` supplies native-control mechanics only.

**Selected next family: RADIO**

**Recommended implementation phase name: Phase 6G — Radio Merchant Presentation renderCss**

**Verdict: ACCEPT**

---

## 2. Audit-only statement

This audit derived all conclusions from **current repository state** (July 2026). No production code, tests, or KB recommendations were modified. Prior report claims (including the cross-block H2 inventory stating Date was “never added” to the standard-field compiler) were **re-verified in code**; Date migration status in that document is **stale**.

---

## 3. Current Date architecture

| Stage | Source | Evidence |
| ----- | ------ | -------- |
| Block registration | `registerBuilderBlocks.ts` → `id: 'date'`, `DateCanvas` | Registry map |
| Default model | `ElementDefaults.createInputDefaults('date')` | Shared input defaults |
| Traits | `DateProperties.tsx` — layout, padding/margin (device), typography, border, background, shadow, date format | `properties.style` |
| Canvas | `DateCanvas.tsx` → `buildCanvasInputShellStyles` + `DatePickerElement` (custom picker UI) | React inline mirror of canonical resolvers |
| Serializer | `blocks/date/serialize.ts` → `serializeTextInputLike(..., 'date', ...)` | Structure-only HTML |
| Inline styles (export) | **None** on shell/input/wrapper merchant keys (stripped) | `serializeElements.ts` L59–75; unit test L157–178 |
| Generic responsive CSS | **Excluded** — `'date'` in `MIGRATED_BLOCK_TRAIT_TYPES` | `responsiveDeviceCss.ts` L314 |
| Dedicated responsive CSS | **None** (renderCss owns mobile diff) | `standardFieldPresentation.ts` |
| Shared structural CSS | `floating-label.export.css` when floating mode | Same as text-input |
| renderCss | `compileStandardFieldPresentationRenderCss()` via `compilePopupRenderCss()` | `standardFieldPresentation.ts` L19–25, L262+ |
| Runtime behavior | Native `input[type=date]` min/max from `dateFieldRange.ts`; validation attrs | Embed `formValidation.ts` |

### Date concern matrix

| Date concern | Model source | Canvas source | Export source | DOM owner | Classification |
| ------------ | ------------ | ------------- | ------------- | --------- | -------------- |
| Wrapper margin/width | `properties.style` margin/width/position | `layoutStyles` selection container | renderCss `[data-adflipr-block-id]` | Block wrapper | MERCHANT PRESENTATION |
| Outline shell (bg, border, radius, padding, height) | `properties.style` | `buildCanvasInputShellStyles` | renderCss `.pbn-canvas-input-shell` | Outline shell | MERCHANT PRESENTATION |
| Input typography/color | `properties.style` | `getInputFieldTypographyStyle` | renderCss `.pbn-canvas-input-shell > input` | Native input | MERCHANT PRESENTATION |
| Static label typography | `properties.style` label* | `CanvasFieldLabel` | renderCss `> label` | Field label | MERCHANT PRESENTATION |
| Floating label mode | `properties.style.floatingLabel` | `DatePickerElement` / shared float pattern | renderCss floating selectors + `floating-label.export.css` | `.pbn-floating-label` / inner input | MERCHANT PRESENTATION + REUSABLE STRUCTURE |
| Focus border | `properties.style.focusBorderColor` | canvas shell CSS var | renderCss `:focus-within` on outline | Outline shell | MERCHANT PRESENTATION |
| Placeholder hint text | `dateOrder`, `dateSeparator`, `placeholder` | canvas picker | HTML attrs only (not CSS) | Content / attrs | CONTENT-LOCAL (format config) |
| min/max/required | traits + `getPbnDateFieldRangeBounds` | preview state | HTML attrs | Native input | RUNTIME BEHAVIOR |
| Date picker UI (builder) | `DatePickerElement` | React component | **Not exported** (native `type=date` on embed) | Canvas-only | RUNTIME BEHAVIOR (intentional) |

---

## 4. Date exact DOM topology

Serialized export (standard label, from `serializeTextInputLike` + `serializeElements` wrapper):

```html
<div data-block-type="date" data-adflipr-block-id="{id}" style="flex-shrink:0">
  <label for="{fieldName}">{labelText}</label>
  <div class="pbn-canvas-input-shell">
    <input type="date" name="{fieldName}" min="{iso}" max="{iso}" placeholder="{formatHint}" …validation attrs>
  </div>
</div>
```

Floating mode adds `--floating` shell class, `.pbn-floating-field` wrap, `.pbn-canvas-input-inner`, `.pbn-floating-label` (same contract as text-input).

| Date DOM node | Stable selector | Current inline CSS | Current embedded CSS | Role |
| ------------- | --------------- | ------------------ | -------------------- | ---- |
| Identity wrapper | `[data-adflipr-block-id="{id}"]` | Structural only (`flex-shrink:0`) | — | IDENTITY |
| Static label | `> label` | **None** | — (renderCss) | PAINTED |
| Outline shell | `.pbn-canvas-input-shell` | **None** | — (renderCss) | PAINTED |
| Native date input | `.pbn-canvas-input-shell > input[type=date]` | **None** | — (renderCss) | NATIVE CONTROL + PAINTED typography |
| Floating inner (if enabled) | `.pbn-canvas-input-inner` | **None** | float structure sheet | NATIVE CONTROL |
| Validation message | runtime-injected | — | embed behavior CSS | BEHAVIOR |

**Can Date reuse an existing standard-field presentation owner contract?** **YES**

Proof: `'date'` ∈ `StandardFieldBlockType`; `serializeTextInputLike` emits identical shell classes; `standardFieldValueSelector(..., 'standard')` resolves to `.pbn-canvas-input-shell > input` which matches `type=date`; wrapper margin stripped same as text-input/email; browser tests assert computed styles on `OUTLINE` and `FIELD` selectors.

---

## 5. Date ownership matrix (summary)

| Property | Merchant configurable? | Desktop source | Mobile source | Same owner? | Migration debt? |
| -------- | --------------------- | -------------- | ------------- | ----------- | ----------------- |
| Wrapper margin | Yes | renderCss wrapper rules | renderCss `@media` diff | Yes | **No** |
| Wrapper width/position | Yes | renderCss wrapper | renderCss `@media` diff | Yes | **No** |
| Outline background | Yes | renderCss outline | diff-only mobile | Yes | **No** |
| Border / radius / padding / height | Yes | renderCss outline | diff-only mobile | Yes | **No** |
| Input typography/color | Yes | renderCss value selector | diff-only mobile | Yes | **No** |
| Label presentation | Yes | renderCss static/floating label | shared | Yes | **No** |
| Focus presentation | Yes | renderCss `:focus-within` | shared | Yes | **No** |
| Placeholder color | Yes | renderCss `::placeholder` | shared | Yes | **No** |

**Split-target defects:** **None found** — generic trait CSS skips `date`; no duplicate merchant inline on export.

**Duplicate renderCss + inline / embedded + inline:** **None** for merchant presentation.

---

## 6. Date merchant-presentation debt

**Remaining debt: negligible (closure/verification only, not a migration phase).**

| Item | Status |
| ---- | ------ |
| Dedicated renderCss compiler | **Exists** (`standardFieldPresentation.ts`) |
| Export negative-inline | **Proven** (unit + browser) |
| Generic responsive exclusion | **Done** |
| Multi-step dedupe/conflict | **Proven** (unit tests) |
| Floating date browser matrix | **Not in browser suite** (compiler supports it; panel has no floating toggle — low product exposure) |
| One unit test assertion | `standardFieldPresentationRenderCss.test.ts` expects shorthand `border:2px solid #112233` but compiler emits `border-width`/`border-color` separately — **test assertion drift**, not architecture gap |

---

## 7. Current Radio architecture

| Stage | Source | Evidence |
| ----- | ------ | -------- |
| Block registration | `registerBuilderBlocks.ts` → `id: 'radio'`, `RadioCanvas` | Registry |
| Default model | `ElementDefaults` choice defaults | options[] + style |
| Traits | `RadioProperties.tsx` — layout (h/v), gap, colors, padding/margin, typography, required | `properties.style` |
| Canvas | `RadioCanvas.tsx` — inline `groupStyle`, `optionLabelStyle`; native inputs without per-input inline (canvas relies on sheet + CSS vars) | React inline |
| Serializer | `blocks/radio/serialize.ts` | Inline on radiogroup, rows, labels, **each input** |
| Inline styles (export) | `getChoiceGroupShellStyle`, `buildRadioGroupStyle`, `buildRadioChoiceCssVars`, `buildRadioInputExportStyleAttr`, `buildChoiceOptionLabelStyle`, `buildFormOptionRowStyle` | Heavy |
| Generic responsive CSS | **Still active** — `radio` ∉ `MIGRATED_BLOCK_TRAIT_TYPES` | `buildBlockTraitResponsiveCss` targets block wrapper |
| radio.export.css | Embedded per step via `serializeLayout.ts` | `POPUP_RADIO_EXPORT_CSS` |
| renderCss | **None** | Not in `compilePopupRenderCss()` |
| Runtime behavior | Native radio semantics, validation on radiogroup, hostile-theme tests | `radioExport.test.ts`, embed validation |

### Radio concern matrix

| Radio concern | Model source | Canvas source | Export source | DOM owner | Classification |
| ------------- | ------------ | ------------- | ------------- | --------- | -------------- |
| Wrapper margin/width | `properties.style` | selection container / layout | Inline on `[data-adflipr-block-id]` wrapper | Block wrapper | MERCHANT PRESENTATION |
| Group shell (padding, border, bg, shadow, minHeight) | `properties.style` | `getChoiceGroupShellStyle` inline | Inline on `.pbn-export-native-radio-scope` | Radiogroup container | MERCHANT PRESENTATION |
| Group layout (flex direction, gap) | `layout`, `optionGap` | `buildRadioGroupStyle` | Inline on radiogroup | Radiogroup container | MERCHANT PRESENTATION |
| Selected/unselected colors | `fillColor`, `outlineColor`, `backgroundColor` | CSS vars on group | Inline CSS vars on group **and** each input | Group + native input | MERCHANT PRESENTATION |
| Option label typography | `fontFamily`, `fontSize`, `textColor` | `buildChoiceOptionLabelStyle` | Inline on option `<label>` | Option label | MERCHANT PRESENTATION |
| Option row layout | Fixed 8px gap | `buildFormOptionRowStyle` | Inline on `.pbn-display-radio-row` | Option row | REUSABLE STRUCTURE |
| Native control size/appearance | Fixed 20×20, appearance:none | `radio.export.css` + canvas sheet | Inline on input + sheet | `.pbn-display-native-radio` | NATIVE CONTROL MECHANIC |
| Checked dot / focus ring | `fillColor` via `--pbn-radio-accent` | sheet `:checked::after` | `radio.export.css` | Native input pseudo | NATIVE CONTROL MECHANIC |
| Hostile theme defense | trait colors | — | `!important` on input border/background inline | Native input | HOSTILE THEME DEFENSE |
| Field label (group) | `labelText` | `CanvasFieldLabel` | `renderFieldLabel` HTML | Static label before group | CONTENT + label typography (inline via label renderer) |
| Selection state | `options[].selected` | canvas preview | `checked` attr | Native input | RUNTIME BEHAVIOR |

---

## 8. Radio exact DOM topology

Serialized export (vertical, two options — from `popupHtmlSerializer.test.ts` + `serialize.ts`):

```html
<div data-block-type="radio" data-adflipr-block-id="radio-1" style="{wrapper margin/width}">
  <label for="{fieldName}">Choose one</label>
  <div role="radiogroup" class="pbn-export-native-radio-scope"
       style="display:flex;flex-direction:column;{shell chrome};gap:{N}px;--pbn-radio-accent:…;--pbn-radio-outline:…;--pbn-radio-bg:…">
    <div class="pbn-display-radio-row" style="display:flex;align-items:center;gap:8px">
      <input type="radio" class="pbn-display-native-radio" id="radio-radio-1-a"
             style="{20×20 appearance reset + !important border/bg + CSS vars}" checked>
      <label for="radio-radio-1-a" style="{typography inline}">Option A</label>
    </div>
    <div class="pbn-display-radio-row" …>…</div>
  </div>
</div>
```

Horizontal layout: `flex-direction:row` (+ wrap) on radiogroup via `buildRadioGroupStyle`.

| Radio DOM node | Stable selector | Current inline CSS | Current embedded CSS | Role |
| -------------- | --------------- | ------------------ | -------------------- | ---- |
| Block wrapper | `[data-adflipr-block-id]` | margin, width % | generic `@media` on same selector | IDENTITY + LAYOUT |
| Field label | `> label` (from `renderFieldLabel`) | label markup styles | — | PAINTED |
| Radiogroup | `.pbn-export-native-radio-scope` | shell + layout + CSS vars | row flex defaults in sheet | LAYOUT + PAINTED |
| Option row | `.pbn-display-radio-row` | flex row gap 8px | sheet duplicates row flex | STRUCTURE |
| Native input | `.pbn-display-native-radio` | size, border, bg, vars, `!important` | appearance, `:checked::after`, `:focus-visible` | NATIVE CONTROL + HOSTILE THEME |
| Option label | `label[for^="radio-"]` | fontFamily, fontSize, color | — | PAINTED |

### Ownership answers

| Question | Owner |
| -------- | ----- |
| Group layout | **Radiogroup** `.pbn-export-native-radio-scope` (flex-direction, gap) |
| Merchant typography | **Option `<label>`** inline (group field label separate) |
| Option spacing | **Radiogroup gap** + row gap (duplicate 8px sources) |
| Radio size | **Native input** inline (20×20) + sheet |
| Selected color | **`--pbn-radio-accent`** (inline vars) → sheet `:checked` rules |
| Unselected chrome | **Input inline border** (`outlineColor`) + `--pbn-radio-outline` |
| Native-control-only CSS | appearance reset, `:checked::after`, `:focus-visible` in `radio.export.css` |
| Hostile-theme-only CSS | `!important` border/background on each input inline (`buildRadioInputExportStyleAttr`) |

---

## 9. Radio ownership matrix (split-target)

| Property | Desktop owner | Mobile owner | Same owner? | Defect? |
| -------- | ------------- | ------------ | ----------- | ------- |
| Wrapper margin | Inline wrapper | Generic `@media` on wrapper | Yes (wrapper) | Low risk |
| Wrapper width | Inline wrapper | — | Yes | No |
| Group padding | Inline radiogroup | Generic `@media` on **wrapper** (wrong node) | **No** | **YES** |
| Group border/radius/bg | Inline radiogroup | Generic `@media` on wrapper | **No** | **YES** |
| Option font-size | Inline option label | Generic `@media` font-size on wrapper | **No** | **YES** |
| fill/outline colors | Inline input + vars | — (no mobile trait path) | Partial | Medium |

**Radio split-target defect found: YES**

---

## 10. radio.export.css classification

File: `styles/sheets/radio.export.css` → `POPUP_RADIO_EXPORT_CSS`

| Selector | Declaration group | Classification | Merchant-derived? | Correct current owner? |
| -------- | ----------------- | -------------- | ----------------- | ---------------------- |
| `.pbn-display-radio-row` | flex, align-items, gap | REUSABLE STRUCTURE | No | Yes (sheet) |
| `.pbn-display-native-radio` | 20×20, appearance:none, border-radius, cursor | NATIVE CONTROL MECHANIC | Partial (size) | Yes (sheet + inline duplicate) |
| `.pbn-display-native-radio:checked` | border-color via `--pbn-radio-accent` | NATIVE CONTROL MECHANIC | Yes (var) | Yes (sheet reads vars) |
| `:checked::after` | dot geometry/color | NATIVE CONTROL MECHANIC | Yes (accent) | Yes |
| `:focus-visible` | outline | NATIVE CONTROL MECHANIC / BEHAVIOR | Yes (accent) | Yes |

**Does radio.export.css contain merchant-specific presentation that should belong in artifact-scoped renderCss?** **PARTIAL**

- Mechanics (appearance, dot, focus ring) **correctly remain** in shared export sheet.
- Merchant **color values** enter via CSS variables currently set **inline** on group/inputs — those vars belong in **renderCss**, not inline.
- Sheet hardcodes 20×20 and 8px gap — structural constants (acceptable in export sheet per Phone 6E precedent).

**radio.export.css native mechanics: YES**

---

## 11. Generic responsive comparison

| Family | Generic responsive consumer? | Properties emitted | Selector target | Desktop source | Mobile source |
| ------ | ---------------------------- | ------------------ | --------------- | -------------- | ------------- |
| **Date** | **No** (excluded) | — | — | renderCss | renderCss `@media` diff |
| **Radio** | **Yes** | padding, margin, border-radius, font-size (when mobile differs) | `[data-adflipr-block-id="{id}"]` **wrapper** | Inline on **radiogroup** + wrapper | Embedded `@media` on **wrapper** |

**Cascade risk rank:** Radio **high** (PCL-008 class — desktop inner inline beats/mobile wrapper `@media` may not reach inner nodes). Date **none**.

---

## 12. Inline-style comparison

| Family | Merchant inline declarations | Structural inline | Behavior inline | Unknown |
| ------ | ---------------------------- | ----------------- | --------------- | ------- |
| **Date** | **0** on export shell/input/wrapper merchant keys | `flex-shrink:0` on wrapper | validation attrs only | 0 |
| **Radio** | **High** — radiogroup shell (~15–25 props), each input (~12 props × N options), each option label (~3 props), wrapper margin/width | row flex (8px gap) | `checked` attr | 0 |

Merchant inline **paths (Radio):**

- `serialize.ts` → `styleAttr(groupStyle)`
- `serialize.ts` → `styleAttr(optionLabelStyle)` per option
- `blockStyles.ts` → `buildRadioInputExportStyleAttr()` per input
- `serializeElements.ts` → wrapper margin/width

---

## 13. Canonical normalization readiness

| Family | Existing canonical resolver | Canvas reuse | Export reuse | New normalization needed | Risk |
| ------ | ---------------------------- | ------------ | ------------ | ------------------------ | ---- |
| **Date** | **Yes** — `normalizeStandardFieldPresentation('date', …)` | Same resolvers as text-input | Same | **No** | **Low** |
| **Radio** | **No** dedicated compiler | Partial (`getChoiceGroupShellStyle`, `buildChoiceOptionLabelStyle`) | Inline-only | **Yes** — new `normalizeRadioPresentation` | **Medium** |

**Date:** Reuse standard-field resolvers **directly** — already wired.  
**Radio:** Requires new normalization; native mechanics currently mixed into merchant inline (`buildRadioInputExportStyleAttr`).

---

## 14. Mobile strategy comparison

| Family | Recommended strategy | Why |
| ------ | -------------------- | --- |
| **Date** | **DIFF-ONLY** (already implemented) | Same renderCss owner desktop/mobile; mobile omits inherited values |
| **Radio** | **DIFF-ONLY** (after migration) | Must first unify owners onto radiogroup + labels + vars scope; then diff-only `@media` in renderCss |

Date DIFF-ONLY is **already safe and shipped**. Radio DIFF-ONLY is **not safe until** inner/outer owner split is eliminated.

---

## 15. Multi-step / conflict comparison

| Family | Collector complexity | Conflict complexity | Runtime state contamination risk |
| ------ | -------------------- | ------------------- | -------------------------------- |
| **Date** | Low — reuses `collectArtifactStandardFieldPresentations` | `StandardFieldPresentationConflictError` on style key mismatch | **Low** — value/min/max excluded |
| **Radio** | Medium — must key on layout + colors + typography + gap; exclude `options[]` content unless label typography differs | Same block id across steps must share presentation; option text is content | **Low** if selected value excluded from presentation key |

---

## 16. Behavior regression comparison

| Family | Behavior surface | Current owner | Migration risk |
| ------ | ---------------- | ------------- | -------------- |
| **Date** | Native date picker, min/max, required, focus, floating (if enabled) | HTML attrs + embed validation + `:focus-within` renderCss | **Low** |
| **Radio** | Selection, keyboard, label click, required group validation, hostile themes, `:checked` visuals | Native input + inline `!important` + sheet | **Medium–High** |

---

## 17. Consumer completeness

| Family | Preview | Thumbnail | Embed | Multi-step | Complete? |
| ------ | ------- | --------- | ----- | ---------- | --------- |
| **Date** | Yes — `FormPreview` embed + renderCss | Yes — `popupThumbnailIframe` injects renderCss | Yes — artifact mount | Yes — dedupe proven | **Yes** |
| **Radio** | Yes — inline + hoisted sheet | Yes — same path | Yes — sheet + inline | Yes — no compile dedupe yet | **Yes** (presentation path works; architecture incomplete) |

No consumer parity **blocker** for either family; Radio’s defect is architectural (split-target), not missing consumer wiring.

---

## 18. User-visible parity defects

| Family | Builder-visible trait | Storefront output | Parity defect? |
| ------ | --------------------- | ----------------- | -------------- |
| **Date** | Shell colors, typography, padding, focus | renderCss computed styles match (browser tests) | **NO** (proven) |
| **Radio** | Colors, spacing, typography | Inline + sheet; mobile traits on wrong node | **YES** — mobile padding/font-size on group shell likely **fail** when desktop/mobile traits differ (PCL-008 pattern) |

**Date consumer parity defect: NO**  
**Radio consumer parity defect: YES** (split-target + generic responsive)

---

## 19. Obsolete-code deletion comparison

| Family | Symbol/path | Deletable after migration? | Why |
| ------ | ----------- | -------------------------- | --- |
| **Date** | — | **Nothing significant** | Already migrated |
| **Date** | `standardFieldPresentationRenderCss.test.ts` border shorthand assertion | Fix only | Assertion drift |
| **Radio** | `serialize.ts` inline `groupStyle` / `optionLabelStyle` | **Yes** | → renderCss |
| **Radio** | `buildRadioInputExportStyleAttr` merchant declarations | **Partial** | Move vars to renderCss; retain hostile `!important` in sheet or scoped renderCss |
| **Radio** | `buildRadioChoiceCssVars` | **Yes** | → renderCss |
| **Radio** | Generic responsive branch for `radio` | **Yes** | Add to `MIGRATED_BLOCK_TRAIT_TYPES` |
| **Radio** | Duplicate row rules (inline + sheet) | **Partial** | Consolidate to sheet |

---

## 20. Four-concern boundary check

| Concern | Date (current) | Radio (proposed migration) |
| ------- | -------------- | -------------------------- |
| MERCHANT PRESENTATION → renderCss | Shell, typography, label, focus | Group chrome, layout gap, label typography, CSS vars |
| REUSABLE STRUCTURE | floating-label sheet, shell classes | row flex, radiogroup role markup |
| RUNTIME BEHAVIOR | validation, native date | selection, required, keyboard |
| NATIVE CONTROL MECHANIC | native date input | appearance, checked dot, focus ring |
| HOSTILE THEME DEFENSE | not needed on date | retain on input (sheet or artifact renderCss with narrow `!important`) |

**renderCss dumping-ground risk:**  
- **Date: NO** — bounded standard-field compiler  
- **Radio: NO** — if migration keeps mechanics in `radio.export.css` and limits renderCss to merchant vars/chrome (same pattern as Phone 6E)

---

## 21. Candidate scoring table

Scale 0–5 (higher better except **Behavior regression risk** where lower is better).  
**Not chosen by arithmetic alone** — Date scores high on readiness because work is **already done**.

| Criterion | Date | Radio | Evidence |
| --------- | ---- | ----- | -------- |
| Architecture debt removed | 1 | **5** | Date migrated; Radio heavy inline + split-target |
| User-visible impact | 3 | **4** | Radio mobile parity defect active |
| Inline merchant debt | 1 | **5** | Radio per-option input inline |
| Embedded merchant debt | 1 | 3 | Radio generic `@media` on wrapper |
| Cascade/split-target risk removed | **5** | 1 | Date excluded from generic CSS |
| DOM ownership clarity | **5** | 2 | Standard-field contract vs split inner/outer |
| Canonical normalization readiness | **5** | 2 | Existing vs new compiler |
| Cleanup value | 1 | **5** | Radio deletes large inline paths |
| Behavior regression risk (↓) | **1** | 3 | Native radio + hostile themes |
| Testability | **4** | 3 | Date browser suite exists |
| Implementation scope coherence | 3 | **4** | Date = closure; Radio = coherent new phase |

**Weighted judgment:** Radio wins on **remaining debt removed**, **cleanup**, and **user-visible mobile defect**. Date wins on **readiness** because Phase 6F is **already production-complete**.

---

## 22. Selected next family

**RADIO**

---

## 23. Why the winner was selected

Radio is the only candidate with **substantial unmigrated merchant-presentation debt**, proven **split-target** responsive risk, and **no artifact-scoped renderCss compiler**. Migrating Radio removes inline merchant CSS across a compound DOM, eliminates generic trait CSS on the wrong node, and aligns with the four-concern architecture boundary (merchant → renderCss; mechanics → `radio.export.css`).

---

## 24. Why the loser was deferred

**Date is not deferred as “hard” — it is deferred as “complete.”** Production code already includes Date in `StandardFieldBlockType`, structure-only export, generic CSS exclusion, compiler + browser verification (Phase 6F). The next Date work is optional **closure** (floating-date browser case, one unit assertion fix), not a family migration phase. Selecting Date would duplicate completed 6B/6F work without removing meaningful debt.

---

## 25. Exact winner migration boundary

| Concern | Current owner | Target owner | Action |
| ------- | ------------- | ------------ | ------ |
| Wrapper margin/width | Inline block wrapper | renderCss `[data-adflipr-block-id]` | Move; strip from inline wrapper |
| Group padding/border/radius/bg/shadow/minHeight | Inline radiogroup | renderCss `.pbn-export-native-radio-scope` | Move |
| Layout direction + option gap | Inline radiogroup | renderCss radiogroup | Move |
| Option label typography | Inline `<label>` | renderCss option label selector | Move |
| Color vars (accent/outline/bg) | Inline group + each input | renderCss radiogroup scope | Move vars; dedupe per-input |
| Input 20×20, appearance, checked dot | `radio.export.css` + inline duplicate | **Retain sheet**; remove merchant duplicates from inline | Keep mechanics |
| Hostile `!important` border/bg on input | Inline per input | Narrow renderCss or sheet scoped to artifact | Classify as HOSTILE THEME DEFENSE |
| Option row flex | Inline + sheet | **Retain sheet** | Structure only |
| Field label (group) | `renderFieldLabel` | renderCss static label selector (new or shared choice-label) | Move typography |
| Generic `@media` block traits | Embedded on wrapper | **Remove** for radio | Add to migrated set |
| Selection/checked/required | HTML attrs + runtime | Unchanged | No CSS migration |

**Selected DOM owner(s):**

- Identity/layout shell: `[data-adflipr-block-id]` (margin/width only)
- Painted group chrome: `.pbn-export-native-radio-scope`
- Option label typography: `.pbn-export-native-radio-scope .pbn-display-radio-row > label`
- Native control mechanics: `.pbn-display-native-radio` via **`radio.export.css`**
- Merchant color tokens: CSS variables on radiogroup scope in **renderCss**

---

## 26. Compiler topology recommendation

**Merchant presentation compiler + retained native-control sheet**

- New module: `radioPresentation.ts` (or `choicePresentation.ts` scoped to radio first)
- Pattern: mirror `phoneInputPresentation.ts` / `dropdownPresentation.ts` — collect by block id, normalize, emit artifact-scoped rules, conflict on presentation key mismatch
- **Do not** fold native `:checked::after` into renderCss
- **Do not** delete `radio.export.css`

Date topology (for comparison): **standard-field compiler extension** — **already shipped** as Phase 6F.

---

## 27. Mobile strategy recommendation

**DIFF-ONLY** in renderCss after owner unification (same artifact scope for desktop/mobile presentation keys).

---

## 28. Multi-step / conflict recommendation

- Presentation identity key: `blockId` + normalized presentation JSON (style traits affecting CSS; **exclude** `options[].label` text and selected value)
- Conflict: throw on same `blockId` with differing presentation keys across steps (match standard-field pattern)
- Union: dedupe identical presentation across steps

---

## 29. Exact deletion forecast

**Inline merchant paths to delete:**

- `blocks/radio/serialize.ts` — `styleAttr(groupStyle)` merchant keys
- `blocks/radio/serialize.ts` — `styleAttr(optionLabelStyle)`
- `styles/inline/blockStyles.ts` — `buildRadioInputExportStyleAttr` merchant portion (retain hostile subset if not moved to sheet)
- `styles/inline/blockStyles.ts` — `buildRadioChoiceCssVars` (move to compiler)

**Generic responsive:**

- Remove `radio` from `buildBlockTraitResponsiveCss` consumer set via `MIGRATED_BLOCK_TRAIT_TYPES`

**Embedded merchant rules:**

- None in `radio.export.css` to delete (mechanics stay)

**Helpers to delete / shrink:**

- `buildRadioGroupStyle` inline usage (layout moves to renderCss; or keep structural flex in sheet only)

**Adapters to retain:**

- `POPUP_RADIO_EXPORT_CSS` embedding in `serializeLayout.ts`
- `mountExportedStepStyles` radio hoist marker
- `radioExport.test.ts` hostile-theme coverage (update assertions)

**Structural sheets to retain:**

- `radio.export.css`

**Behavior sheets to retain:**

- Same — native control mechanics

---

## 30. Acceptance test plan (Radio — Phase 6G)

| Category | Tests |
| -------- | ----- |
| Compiler unit | `normalizeRadioPresentation`, dedupe, conflict error, CSS var emission, DIFF-ONLY mobile |
| DOM ownership | Export HTML has no merchant inline on radiogroup/labels/inputs (except allowed hostile subset) |
| Chromium computed | Desktop/mobile padding, gap, colors, typography on correct nodes |
| Checked/unchecked | Selected fill, unselected outline, `:checked::after` still from sheet |
| Keyboard/native | Space/Enter selection, label `for` click, required validation |
| Layout | Horizontal + vertical flex-direction |
| Hostile theme | `radioExport.test.ts` equivalent with renderCss mount |
| Multi-step | Same block id dedupe + conflict |
| Artifact isolation | Scoped selectors |
| Negative assertions | No radio rules in `buildBlockTraitResponsiveCss` |
| Shared structure | `radio.export.css` still embedded; row flex preserved |
| Consumer parity | Builder preview + thumbnail + embed snapshot |
| Phase 0 | No regression to 18/18 baseline |
| G1 | `exportCssProductionBuildContract.test.ts` PASS |

---

## 31. Unrelated known-failure status

| Suite | Phone 6E status | Current status (2026-07-06) |
| ----- | --------------- | --------------------------- |
| `popupEmbedRuntime.test.ts` | 6 failures | **STILL PRESENT** — **4 failures** (VIEW telemetry, side message flyout, video autoplay, teaser chip) |
| `popupProductionCssRteOwnership.audit.test.ts` | 2 failures | **STILL PRESENT** — **2 failures** (Text G2B.2 style-panel renderCss, RTE inline preservation) |

Total unrelated failures: **6** (4 + 2). Not fixed in this audit.

---

## 32. Phone flag-without-URL observation status

From Phase 6E acceptance audit §413: export always renders 20×14 flag img even when no URL — minor visual edge case.

**Status: STILL OPEN / NOT ADDRESSED** (Phone closed; observation recorded only).

---

## 33. Final verdict

**ACCEPT** — Select **Radio** as the next implementation family. Date standard-field migration is production-complete (Phase 6F); further Date work is optional closure, not H2 scope. Radio migration removes meaningful architecture debt with a clear compiler + retained native-sheet boundary.

---

## 34. Recommended implementation phase name

**Phase 6G — Radio Merchant Presentation renderCss**

---

## Appendix — Required final answer format

1. Audit-only phase respected: **YES**
2. Production code changed: **NO**
3. Tests changed: **NO**
4. Date dedicated renderCss compiler exists: **YES** (`standardFieldPresentation.ts` / Phase 6F)
5. Radio dedicated renderCss compiler exists: **NO**
6. Date identity owner: `[data-adflipr-block-id]` wrapper
7. Date painted owner: `.pbn-canvas-input-shell` (+ value `input`, static/floating label)
8. Date native-control owner: `input[type=date]` (typography in renderCss; behavior via attrs)
9. Radio identity owner: `[data-adflipr-block-id]` wrapper
10. Radio group-layout owner: `.pbn-export-native-radio-scope[role=radiogroup]`
11. Radio native-control owner: `.pbn-display-native-radio` + `radio.export.css`
12. Radio label owner: option `label[for]` (group label: `> label` from `renderFieldLabel`)
13. Date desktop merchant CSS source: **artifact-scoped renderCss** (compiled)
14. Date mobile merchant CSS source: **renderCss `@media` diff-only**
15. Radio desktop merchant CSS source: **serializer inline** (radiogroup, labels, inputs)
16. Radio mobile merchant CSS source: **generic embedded `@media` on block wrapper** (split-target)
17. Date merchant inline debt: **none** (export)
18. Radio merchant inline debt: **high** (group + N×input + N×label)
19. Date embedded merchant debt: **none**
20. Radio embedded merchant debt: **partial** (generic trait `@media` on wrong node)
21. radio.export.css merchant presentation: **PARTIAL** (vars/mechanics; colors via inline vars)
22. radio.export.css native mechanics: **YES**
23. Date split-target defect found: **NO**
24. Radio split-target defect found: **YES**
25. Date consumer parity defect found: **NO**
26. Radio consumer parity defect found: **YES**
27. Date canonical normalization readiness: **ready — reuse standard-field resolver**
28. Radio canonical normalization readiness: **new normalization required**
29. Date recommended mobile strategy: **DIFF-ONLY** (implemented)
30. Radio recommended mobile strategy: **DIFF-ONLY after owner unification**
31. Date behavior regression risk: **low**
32. Radio behavior regression risk: **medium**
33. Date cleanup value: **low** (already migrated)
34. Radio cleanup value: **high**
35. Date renderCss dumping-ground risk: **NO**
36. Radio renderCss dumping-ground risk: **NO** (if mechanics stay in sheet)
37. Candidate scoring: see §21 — Radio wins remaining-debt dimensions; Date wins readiness because complete
38. Selected next family: **RADIO**
39. Selected exact DOM owner(s): wrapper (margin/width), `.pbn-export-native-radio-scope` (group chrome/layout/vars), option labels, native input mechanics in sheet
40. Selected compiler topology: **merchant presentation renderCss + retained `radio.export.css`**
41. Selected properties moving to renderCss: wrapper margin/width, group padding/border/radius/bg/shadow/minHeight, flex layout/gap, option label typography, `--pbn-radio-*` vars
42. Selected structure remaining outside renderCss: row flex markup/classes, radiogroup role, native input markup
43. Selected behavior/native mechanics remaining outside renderCss: appearance reset, `:checked::after`, `:focus-visible`, hostile `!important` (narrow), validation/selection attrs
44. Selected mobile strategy: **DIFF-ONLY**
45. Selected multi-step behavior: dedupe by block id; conflict on presentation key mismatch
46. Selected conflict behavior: fail compile on cross-step presentation drift (exclude option content/selection)
47. Selected inline paths to delete: `radio/serialize.ts` merchant `styleAttr`; `buildRadioChoiceCssVars`; most of `buildRadioInputExportStyleAttr`
48. Selected embedded merchant paths to delete: generic `buildBlockTraitResponsiveCss` rules for `radio`
49. Selected shared sheets to retain: `radio.export.css`, floating-label (N/A for radio)
50. Selected behavior/native sheets to retain: `radio.export.css`
51. Expected obsolete production code: inline group/label/input merchant builders in radio serialize path; generic responsive radio branch
52. Acceptance test plan: §30
53. Phase 0 requirement: 18/18 baseline must stay green (`responsiveCssBaseline.browser.test.ts`)
54. G1 requirement: `exportCssProductionBuildContract.test.ts` PASS after sheet retention
55. Existing 6 embed failures status: **STILL PRESENT** (4 failing today; 6 total with Text audit)
56. Existing 2 Text audit failures status: **STILL PRESENT**
57. Phone flag-without-URL observation status: **STILL OPEN** (not fixed; Phone closed)
58. Winner: **RADIO**
59. Loser deferred because: **Date Phase 6F standard-field migration already complete in production — no meaningful merchant debt remains**
60. Verdict: **ACCEPT**
61. Recommended implementation phase name: **Phase 6G — Radio Merchant Presentation renderCss**
