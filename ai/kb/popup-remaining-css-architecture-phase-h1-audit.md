# Phase H1 — Remaining Popup CSS Architecture Inventory + Next Owner Selection Audit

**Date:** 2026-07-06  
**Scope:** Audit-only — inventory remaining CSS outside artifact-scoped `renderCss`; select one next migration target  
**References:** G2B/G2B.1/G2B.2, Phase 6c/6d reports, G1 export CSS, Phase 0 baseline (closed), production code inspection

---

## 1. Executive verdict

Phase H1 reconstructed the current popup CSS architecture from production code (not report recommendations). **13 dedicated `renderCss` compilers** are active under `compilePopupRenderCss()`. Major layout, form-surface, input-shell (text-input/email/textarea/dropdown), Text, Button, positioned decorative, and Teaser families are **fully or substantially migrated**.

**Significant merchant presentation debt remains** in serializers for: **product, timer, divider, radio, check-box, image, video, social, coupon, feedback, spinner**, and **phone-input** (input-shell outlier). Embedded step CSS still carries **visibility mechanics, product responsive overrides, phone select width, and generic block-trait mobile rules** for non-migrated families.

**Consumer completeness defect:** **NO**

**Architecture boundary (mandatory):** `renderCss` is **only** for **merchant-specific presentation**. It is **not** the destination for all CSS. See **§H1-B** below.

**renderCss dumping-ground assessment:** **NO** (with moderate drift risk — see §H1-B.4). Next-family selection **not stopped**, but **reframed** (§H1-B.6).

**Selected next action:** **Phase 6E — Phone Input *merchant presentation* migration** (merchant traits only; structure/behavior stay in shared sheets)

**Verdict: ACCEPT WITH ARCHITECTURE CLARIFICATION**

---

## H1-B — Mandatory architecture boundary (four-concern model)

### H1-B.1 Target architecture (correct)

```text
Popup CSS
├── Merchant-specific presentation
│   └── artifact-scoped renderCss
│
├── Reusable structure
│   └── shared/runtime/export structural CSS (.export.css, embed guards)
│
├── Runtime behavior
│   └── shared/runtime/export behavior CSS (visibility, validation, entrance, float mechanics)
│
└── Content-local rich formatting
    └── properties.content where semantically required
```

**Only `MERCHANT PRESENTATION` is a default candidate for `renderCss`.**

Moving CSS to `renderCss` requires proving all six gates:

1. Merchant-configurable or derived from merchant presentation state  
2. Belongs to a specific artifact owner  
3. Different artifacts may legitimately differ  
4. Not reusable structure  
5. Not runtime behavior  
6. Not content-local rich formatting  

### H1-B.2 Remaining CSS sources — four-concern classification

| CSS source / rule | Primary concern | Merchant-configurable? | Artifact-specific? | Correct destination | Why |
|-------------------|-----------------|------------------------|--------------------|-----------------------|-----|
| `compilePopupRenderCss` compilers (surface, layout, text, button, fields…) | **MERCHANT PRESENTATION** | YES | YES | **renderCss** | Trait-driven per popup artifact |
| `TEXT_PAINTED_STRUCTURAL_STYLES` (`white-space:pre-wrap`) | **REUSABLE STRUCTURE** | NO | NO | **renderCss co-located** (owner-local) | Needed on painted owner; documented structural constant |
| `BUTTON_PAINTED_STRUCTURAL_STYLES` (flex shell, box-sizing) | **REUSABLE STRUCTURE** | NO | NO | **renderCss co-located** (owner-local) | Same — necessary for button painted owner |
| `STANDARD_FIELD_*_STRUCTURAL_RULES` | **REUSABLE STRUCTURE** | NO | NO | **renderCss co-located** (owner-local) | Input shell geometry; shared across all standard fields |
| Form surface `maxWidth/boxSizing/position:relative` in renderCss | **REUSABLE STRUCTURE** | NO | NO | **SHOULD MOVE TO shared structural CSS** (future) | Not merchant-configurable; currently mixed into presentation compiler |
| Text RTE partial spans (`color`, `font-size`, highlight) | **CONTENT-LOCAL FORMATTING** | partial | per block content | **properties.content** | G2A.1 / G2B contract |
| Text link color in renderCss (`> div a`) | **MERCHANT PRESENTATION** | YES | YES | **renderCss** | `style.linkColor` / form default |
| `floating-label.export.css` | **REUSABLE STRUCTURE** + **RUNTIME BEHAVIOR** | NO | NO | **embedded/shared structural CSS** | Float transform, input geometry — not per-artifact merchant chrome |
| Phone compound rules in floating-label sheet | **REUSABLE STRUCTURE** | NO | NO | **shared structural CSS** | Row/select layout mechanics |
| `embed-responsive.export.css` overflow guards | **REUSABLE STRUCTURE** | NO | NO | **shared structural CSS** | Cross-block embed safety |
| Field validation error rules in embed-responsive | **RUNTIME BEHAVIOR** | NO | NO | **runtime behavior CSS** | Toggled by embed runtime after submit |
| `popup-entrance.export.css` | **RUNTIME BEHAVIOR** | NO | NO | **runtime behavior CSS** | Transition animation class |
| `radio.export.css` checked/focus mechanics | **RUNTIME BEHAVIOR** | NO | NO | **runtime behavior CSS** | Native control behavior |
| Radio input inline `--pbn-radio-*` + border/bg | **MERCHANT PRESENTATION** | YES | YES | **renderCss** (target) | fillColor/outlineColor from traits; inline today + theme defense |
| `buildBlockDeviceVisibilityCss` | **RUNTIME BEHAVIOR** | YES (hide flags) | YES (per block attrs) | **embedded behavior CSS** | Visibility mechanic, not presentation |
| `buildProductHorizontalResponsiveCss` | **REUSABLE STRUCTURE** | NO (layout mode) | NO | **shared structural CSS** (or block-family structural module) | Responsive layout pattern for horizontal product; not merchant color/size |
| `buildProductGridResponsiveCss` | **REUSABLE STRUCTURE** | NO | NO | **shared structural CSS** | Grid collapse pattern |
| `buildPhoneInputCountrySelectResponsiveCss` | **REUSABLE STRUCTURE** | NO | NO | **shared structural CSS** | Embed overflow constraint, not merchant trait |
| `buildBlockTraitResponsiveCss` (non-migrated families) | **MERCHANT PRESENTATION** | YES | YES | **renderCss** (target) | Mobile diff of padding/margin/radius/font traits |
| Product/timer/divider/image serializer inline styles | **MERCHANT PRESENTATION** | YES | YES | **renderCss** (target) | Trait-driven colors, typography, spacing |
| Phone serializer inline via `inputFieldStyles` | **MERCHANT PRESENTATION** | YES | YES | **renderCss** (target) | Border, colors, fonts from `properties.style` |
| `buildRichTextLinkExportCss` (button label links) | **MERCHANT PRESENTATION** | YES (form linkColor) | partial | **renderCss** (button compiler extension) | Merchant theme link color |
| `formStyle.customCss` | **MERCHANT PRESENTATION** | YES (author) | per popup | **embedded custom CSS** | Intentional override escape hatch |
| Button align container `textAlign` inline | **REUSABLE STRUCTURE** | NO | NO | **serializer inline** or shared | Structural alignment container |
| Canvas `applyLinkStyles` / InlineEditor | **RUNTIME BEHAVIOR** / canvas UX | — | — | **canvas-only CSS** | Stripped at export |
| Spinner canvas inline styles | **RUNTIME BEHAVIOR** + structure | partial | YES | **mixed** — audit before migration | Gamification mechanics |

### H1-B.3 Structural constants currently inside renderCss compilers

| Structural constant | Compiler | Classification | Action in H1 |
|---------------------|----------|----------------|--------------|
| `TEXT_PAINTED_STRUCTURAL_STYLES` | text | **NECESSARY OWNER-LOCAL STRUCTURE** | Report only — do not refactor |
| `BUTTON_PAINTED_STRUCTURAL_STYLES` | button | **NECESSARY OWNER-LOCAL STRUCTURE** | Report only |
| `STANDARD_FIELD_OUTLINE/VALUE/FLOATING_*` | standard field | **NECESSARY OWNER-LOCAL STRUCTURE** | Report only |
| `TEXTAREA_OUTLINE_STRUCTURAL_RULES` | textarea | **NECESSARY OWNER-LOCAL STRUCTURE** | Report only |
| `DROPDOWN_SELECT_STRUCTURAL_RULES` | dropdown | **NECESSARY OWNER-LOCAL STRUCTURE** | Report only |
| Form surface `maxWidth`, `boxSizing`, `position` | form surface | **ACCIDENTALLY MIXED INTO PRESENTATION COMPILER** | Flag for future shared structural extraction — not H1 refactor |
| Button `transition: all 0.2s` in structural | button | **ACCIDENTALLY MIXED** (behavior-adjacent) | Flag — low priority |

Co-locating **owner-local structure** in the same artifact `renderCss` string is **accepted** when it is not merchant-configurable and is required for the painted owner to receive merchant rules correctly. That is **not** the same as migrating reusable cross-popup structure into presentation compilers.

### H1-B.4 Is renderCss becoming a dumping ground?

**NO** — with **moderate drift risk**.

Evidence **against** dumping ground:

- Compilers are **split by block family**, driven by **merchant traits** (`properties.style`), artifact-scoped selectors  
- **Structural constants are explicitly named and documented** separate from merchant rules in `ElementStyles.ts`  
- **Reusable structure and behavior remain in `.export.css` sheets** (floating-label, embed-responsive, entrance, radio)  
- **Content-local RTE formatting stays in `properties.content`** (G2B)  
- **Runtime visibility/validation** stays in embedded behavior CSS  
- `customCss` remains a separate merchant override channel  

Evidence **for** drift risk (not yet dumping ground):

- Physical output is **one concatenated artifact stylesheet** (`joinCssChunks`) — logical separation depends on discipline  
- Form surface compiler mixes **non-merchant structural properties** (`maxWidth:100%`, `boxSizing`) with presentation  
- Product responsive **`!important` layout overrides** live in embedded CSS but look like presentation migration targets — they are **structure**, not renderCss candidates  
- Prior H1 wording over-suggested “move inline → renderCss” without four-concern gate — **corrected in this section**

**Conclusion:** Architecture is **not** collapsed into one undifferentiated generated sheet. **Do not STOP** next-family selection; **do reframe** it under four-concern gates.

### H1-B.5 Corrected “remaining debt” framing

| Debt type | Examples | Destination (not “renderCss by default”) |
|-----------|----------|---------------------------------------------|
| Merchant presentation still inline | product, timer, divider, phone traits, image, etc. | **renderCss** (after six-gate proof) |
| Merchant presentation in generic embedded mobile | `buildBlockTraitResponsiveCss` for non-migrated blocks | **renderCss** mobile diff |
| Reusable responsive layout | product horizontal/grid collapse | **shared structural CSS** |
| Reusable embed guards | overflow, max-width on media | **shared structural CSS** (embed-responsive) |
| Runtime behavior | hide-on-device, validation, entrance, float animation | **runtime behavior CSS** |
| Content-local | Text partial spans | **properties.content** |

### H1-B.6 Revised next architecture action

**Phase 6E — Phone Input merchant presentation migration** (selection **reframed**, not withdrawn):

| Move to renderCss | Keep in shared structural / behavior CSS |
|-------------------|------------------------------------------|
| Outline border/color/background from traits | Floating-label float transform mechanics |
| Value typography from traits | Input `border:none; background:transparent` geometry |
| Merchant padding/height on shell | Phone row flex scaffolding in floating-label sheet |
| Label static/floating **presentation** (color, font) | Country select overflow/min-width guards (embed-responsive) |
| Wrapper margin/width traits | Validation error visibility (embed-responsive) |

**Do NOT** move floating-label animation, embed overflow guards, or validation mechanics to renderCss.

**Deferred with corrected classification:**

- **Product horizontal/grid responsive** → shared structural module, **not** renderCss-first  
- **Date field** → extend standard-field **merchant presentation** compiler; structure already class-based  

---

## 2. Current CSS architecture call graph

```text
PopupBuilderSnapshot
  ↓
serializePopupExport(snapshot, { artifactScopeId })
  ├─ normalizePopupSnapshotTextBlocks()          [Text content hygiene only]
  ├─ serializePopupHtmlByStep()
  │    └─ serializeStepHtml() per step
  │         ├─ Embedded <style> blocks (see Part E)
  │         └─ <form> → serializeFormBody()
  │              ├─ serializeLayout() / serializeColumn() / serializeElement()
  │              └─ block serializers → HTML (structure + optional inline)
  └─ compilePopupRenderCss(snapshot, artifactScopeId)
       └─ 13 presentation compilers → renderCss string

Saved artifact metadata
  ├─ htmlByStep / htmlContent
  └─ renderCss

Consumers (preview, thumbnail, embed, save)
  ├─ HTML mount (innerHTML / iframe)
  ├─ mountRenderArtifactCss({ renderCss, artifactScopeId })   [document.head scoped sheet]
  └─ hoistExportedStepStyles(container)                        [embedded <style> → document.head]
```

```text
Current popup CSS architecture
├── Artifact renderCss (compilePopupRenderCss)
│   ├── form surface
│   ├── outer layout
│   ├── columns container
│   ├── column presentation
│   ├── button presentation
│   ├── text presentation (+ link descendant rules)
│   ├── standard field (text-input, email-input)
│   ├── textarea presentation
│   ├── dropdown presentation
│   ├── positioned decorative (cross, floating-image)
│   ├── teaser presentation
│   └── gamification shell (conditional)
│
├── Embedded step CSS (inside htmlByStep, hoisted at runtime)
│   ├── floating-label.export.css        [structure + phone compound chrome]
│   ├── embed-responsive.export.css      [overflow guards + field errors]
│   ├── popup-entrance.export.css        [transition keyframes]
│   ├── radio.export.css                 [checked-state mechanics]
│   ├── data-pbn-rich-text-links         [button rich-text link color]
│   ├── buildEmbeddedStepResponsiveCss   [visibility, generic block mobile, product, phone]
│   └── formStyle.customCss              [merchant override sheet]
│
├── Runtime/behavior CSS
│   ├── mountRenderArtifactCss           [artifact-scoped renderCss]
│   ├── hoistExportedStepStyles          [step-embedded styles → head]
│   ├── embed runtime validation toggles [.pbn-export-field-error--visible]
│   └── FormCanvas preview customCss inject (builder preview only)
│
├── Serializer inline CSS
│   ├── Block wrapper (getExportWrapperStyleFromLayout) — non-migrated families
│   ├── Product, timer, divider, radio, checkbox, image, video, social, coupon, feedback, spinner
│   ├── Phone-input compound inline (deprecated inputFieldStyles adapters)
│   ├── Button align container (structural text-align only)
│   └── Rich-content inline (Text RTE partial spans — legitimate)
│
├── Rich-content inline CSS
│   └── Text properties.content (partial color/font-size/highlight, semantic tags)
│
└── Canvas-only CSS
    ├── InlineEditor / TextCanvas contenteditable styles
    ├── applyLinkStyles (canvas UX; stripped at export)
    └── Builder trait panel / FormCanvas simulation
```

---

## 3. Dedicated renderCss compiler inventory

| Compiler | DOM owner | Block/family | Base CSS | Mobile CSS | Multi-step | Conflict handling |
|----------|-----------|--------------|----------|------------|------------|-------------------|
| `compileFormSurfaceRenderCss` | `[data-adflipr-popup-surface]` | Form surface | desktop traits inline-equivalent rules | `@media max-width 480px` diff | per snapshot union | style-only surface key |
| `compileOuterLayoutRenderCss` | `[data-adflipr-layout-id]` | Layout rows | desktop padding/margin/radius/shadow/gap | mobile diff | union across steps | per layout-id conflict error |
| `compileColumnsContainerRenderCss` | `[data-adflipr-columns]` | Multi-column shell | flex/grid desktop | mobile stack diff | union | per layout-id |
| `compileColumnPresentationRenderCss` | `[data-column-id]` | Column cells | desktop presentation | mobile diff | union | per column-id conflict |
| `compileButtonPresentationRenderCss` | wrapper + align container + `button` | Button | desktop painted + margin | mobile diff | union per block-id | `ButtonPresentationConflictError` |
| `compileTextPresentationRenderCss` | wrapper + painted `> div` + link `a` | Text | desktop typography | mobile diff | union per block-id | `TextPresentationConflictError` |
| `compileStandardFieldPresentationRenderCss` | block + `.pbn-canvas-input-shell` + input/label | text-input, email-input | outline/value/label desktop | mobile diff | union per block-id | conflict error |
| `compileTextareaPresentationRenderCss` | block + shell + textarea | textarea | same pattern | mobile diff | union | conflict error |
| `compileDropdownPresentationRenderCss` | block + shell + select | dropdown | same pattern | mobile diff | union | conflict error |
| `compilePositionedDecorativeRenderCss` | cross button / floating-image img | cross, floating-image | position desktop | mobile diff | union | conflict error |
| `compileTeaserPresentationRenderCss` | `[data-adflipr-popup-teaser]` | Teaser chip | desktop | mobile diff | single formStyle | N/A |
| `compileGamificationRenderCss` | gamification surface shell | gamification popup | desktop | mobile diff | conditional | surface-level |

**Regression flags:** None observed for migrated Phase 0 families. Button still reads `properties.align` for renderCss compile input (not Text `align` — Button family).

---

## 4. Remaining generic responsive consumer inventory

**Source:** `buildEmbeddedStepResponsiveCss()` → `buildBlockTraitResponsiveCss()` + product/phone/visibility helpers.

**Migrated types skipped by generic consumer** (`MIGRATED_BLOCK_TRAIT_TYPES`):

```text
cross, floating-image, button, text, text-input, email-input, textarea, dropdown
```

| Block/family | Generic responsive consumer? | Properties emitted (mobile @media) | Selector target | Desktop source | Mobile source |
|--------------|------------------------------|-----------------------------------|-----------------|----------------|---------------|
| divider | **YES** | padding, margin, border-radius, font-size | `[data-adflipr-block-id]` | serializer inline | embedded @media |
| phone-input | **YES** (+ dedicated phone rule) | block traits + select max-width | block-id + `select` | serializer inline | embedded @media |
| check-box | **YES** | padding, margin, radius, font-size | block-id | serializer inline | embedded @media |
| radio | **YES** | same | block-id | serializer inline + input inline | embedded @media |
| date | **YES** | same | block-id wrapper | wrapper inline | embedded @media |
| timer | **YES** | same | block-id wrapper | heavy serializer inline | embedded @media |
| spinner | **YES** | same | block-id wrapper | serializer inline | embedded @media |
| image | **YES** | same | block-id | serializer inline | embedded @media |
| video | **YES** | same | block-id | serializer inline | embedded @media |
| product | **YES** (+ product-specific) | block traits + horizontal/grid overrides | product attrs | serializer inline (extensive) | embedded @media !important |
| coupon | **YES** | block traits | block-id | serializer inline | embedded @media |
| feedback | **YES** | block traits | block-id | serializer inline | embedded @media |
| social | **YES** | block traits | block-id | serializer inline | embedded @media |
| layout/section/column | **NO** (dedicated renderCss) | — | — | renderCss | renderCss |
| form surface | **NO** | — | — | renderCss | renderCss |

**Highest-risk pattern (desktop inline + mobile @media):** product, timer, divider, radio, checkbox, image, video, social, coupon, feedback, phone-input, date (wrapper), spinner.

**Split-target example (Phase 0 block-padding control):** button inner `<button>` had desktop padding inline while mobile @media targeted wrapper — **migrated** to renderCss. Phone compound: desktop traits on row/select/input inline; mobile select width in embedded only.

---

## 5. Serializer inline-style inventory (merchant presentation highlights)

| Family | DOM node | Inline property (examples) | Classification | Current source | Should remain inline? |
|--------|----------|------------------------------|----------------|-------------------|----------------------|
| Text | content spans | color, font-size, highlight | RICH-CONTENT | RTE | **YES** |
| Text | painted `> div` | — | — | renderCss | **NO** (migrated) |
| Button | align container | textAlign | STRUCTURE | serializer | **YES** (layout) |
| Button | `button` | — | — | renderCss | **NO** (migrated) |
| Product | card/shell/image/body/button/spans | colors, fonts, flex, borders | MERCHANT | serializer | **NO** (candidate) |
| Timer | units/container/spans | colors, fonts, sizing | MERCHANT | serializer | **NO** |
| Divider | outer + separator | padding, border-top line | MERCHANT | serializer | **NO** |
| Radio | input + rows | border, bg, appearance | MERCHANT | serializer + !important | **PARTIAL** (theme defense) |
| Checkbox | input | accentColor | MERCHANT/BEHAVIOR | serializer | **DEBATABLE** |
| Image/Video | img/iframe/container | width, radius, object-fit | MERCHANT | serializer | **NO** |
| Social | links/icons | size, gap, colors | MERCHANT | serializer | **NO** |
| Coupon | code row | typography, borders | MERCHANT | serializer | **NO** |
| Feedback | options/stars | colors, layout | MERCHANT | serializer | **NO** |
| Spinner | canvas/result | layout, colors | MERCHANT + BEHAVIOR | serializer | **PARTIAL** |
| Phone | shell/row/select/input | borders, fonts, padding | MERCHANT | serializer (deprecated adapters) | **NO** |
| Date | — (structure classes) | — | — | **GAP: no renderCss compiler** | renderCss needed |
| text-input/email/textarea/dropdown | shells | — | STRUCTURE | class-only HTML | **YES** |
| Cross/floating-image | button/img | pointer-events only | BEHAVIOR | minimal | **YES** |
| Block wrapper (non-migrated) | `[data-adflipr-block-id]` | margin, width | MERCHANT/LAYOUT | getExportWrapperStyleFromLayout | **NO** (migrate with family) |

---

## 6. Embedded step CSS inventory

| Generator/sheet | Consumer | Purpose | Merchant? | Structural? | Behavior? | Migration candidate? |
|-----------------|----------|---------|-----------|-------------|-----------|------------------------|
| `POPUP_FLOATING_LABEL_EXPORT_CSS` | all steps with inputs | floating label mechanics, phone compound shell | partial | **YES** | **YES** | partial (phone merchant → renderCss) |
| `POPUP_EMBED_RESPONSIVE_EXPORT_CSS` | all exported steps | overflow guards, field error visibility | no | **YES** | **YES** | retain |
| `POPUP_ENTRANCE_EXPORT_CSS` | steps with transition | entrance animation | no | no | **YES** | retain |
| `POPUP_RADIO_EXPORT_CSS` | steps with radio | checked dot, focus | partial | no | **YES** | partial |
| `buildRichTextLinkExportCss` | steps | button RTE link color in label | **YES** | no | no | **YES** (could move to button renderCss) |
| `buildEmbeddedStepResponsiveCss` | per step | visibility, generic mobile traits, product, phone | **YES** (partial) | no | **YES** (visibility) | partial |
| `formStyle.customCss` | per popup | merchant override | **YES** | no | no | intentional escape hatch |

### Why `hoistExportedStepStyles()` still exists

Browsers **do not reliably apply `<style>` nodes left inside subtrees inserted via `innerHTML`**. Exported step HTML intentionally embeds multiple `<style>` blocks (floating-label, responsive guards, radio, device CSS, custom CSS). Preview (`FormPreview` → `mountFormPreviewEmbedPresentation` → `PopupEmbedRuntime`), embed runtime, and teaser paths call `hoistExportedStepStyles(container)` to **clone those blocks to `document.head`** with dedupe markers, then remove them from the subtree. **`renderCss` is mounted separately** via `mountRenderArtifactCss()` and is **not** hoisted from HTML.

Hoist remains required until all embedded sheets are either (a) moved to artifact `renderCss`, or (b) proven unnecessary, **or** (c) replaced by a single head injection strategy — without breaking multi-step teardown.

---

## 7. `.export.css` inventory

| Sheet | Importer | Runtime consumer | Purpose | Merchant presentation? | Still necessary? |
|-------|----------|------------------|---------|------------------------|------------------|
| `floating-label.export.css` | `styles/sheets/index.ts` | embedded step HTML | label float mechanics, phone outline | partial | **YES** (until phone migrated) |
| `embed-responsive.export.css` | same | embedded step HTML | overflow + validation error UI | no | **YES** |
| `popup-entrance.export.css` | same | embedded step HTML | transition keyframes | no | **YES** |
| `radio.export.css` | same | embedded step HTML | native radio appearance | partial | **YES** (until radio migrated) |

**G1 contract:** `npm run test:export-css-production-contract` — **PASS** (raw CSS strings, no webpack stub).

No `.export.css` sheet should move wholesale to renderCss; sheets are **structural/behavior**. Merchant portions inside them (phone compound chrome) are migration candidates.

---

## 8. Complete block-family matrix

| Family | Canvas | Serializer | Dedicated renderCss | Generic embedded CSS | Merchant inline CSS | Status |
|--------|--------|------------|---------------------|----------------------|---------------------|--------|
| form surface | yes | serializeFormBody attrs | **YES** | visibility only | no | **FULLY MIGRATED** |
| section/one/two/three-column | yes | serializeLayout | **YES** (outer + columns + column) | visibility | no | **FULLY MIGRATED** |
| gamification shell | yes | serializeFormBody | **YES** (conditional) | visibility | no | **FULLY MIGRATED** |
| text | yes | structure-only inner | **YES** | no | content RTE only | **FULLY MIGRATED** |
| button | yes | structure + align container | **YES** | no | structural textAlign | **PARTIALLY MIGRATED** |
| cross | yes | structure-only button | **YES** | no | no | **FULLY MIGRATED** |
| floating-image | yes | structure + pointer-events | **YES** | no | pointer-events | **FULLY MIGRATED** |
| text-input | yes | class-only shell | **YES** | no | no | **FULLY MIGRATED** |
| email-input | yes | class-only shell | **YES** | no | no | **FULLY MIGRATED** |
| textarea | yes | class-only shell | **YES** | no | no | **FULLY MIGRATED** |
| dropdown | yes | class-only shell | **YES** | no | no | **FULLY MIGRATED** |
| teaser | yes | style-only export path | **YES** | hide rules only | no | **FULLY MIGRATED** |
| phone-input | yes | **heavy inline** | **NO** | **YES** (+ phone width) | **YES** | **NOT MIGRATED** |
| date | yes | class-only (like text-input) | **NO** | **YES** | wrapper inline | **PARTIALLY MIGRATED / GAP** |
| divider | yes | outer + line inline | **NO** | **YES** | **YES** | **NOT MIGRATED** |
| radio | yes | group + input inline | **NO** | radio sheet + **YES** | **YES** | **NOT MIGRATED** |
| check-box | yes | group + input inline | **NO** | **YES** | **YES** | **NOT MIGRATED** |
| timer | yes | extensive inline | **NO** | **YES** | **YES** | **NOT MIGRATED** |
| spinner | yes | canvas/result inline | **NO** | **YES** | mixed | **NOT MIGRATED** |
| image | yes | container + img inline | **NO** | **YES** | **YES** | **NOT MIGRATED** |
| video | yes | container + iframe inline | **NO** | **YES** | **YES** | **NOT MIGRATED** |
| product | yes | extensive inline | **NO** | **YES** + product rules | **YES** | **NOT MIGRATED** |
| coupon | yes | inline | **NO** | **YES** | **YES** | **NOT MIGRATED** |
| feedback | yes | inline | **NO** | **YES** | **YES** | **NOT MIGRATED** |
| social | yes | inline | **NO** | **YES** | **YES** | **NOT MIGRATED** |

**Total supported block families:** **25** (`BlockType` enum)

---

## 9. Remaining candidate DOM ownership matrix

| Family | Identity owner | Layout owner | Painted owner | Descendant owner | Behavior owner |
|--------|----------------|--------------|---------------|------------------|----------------|
| phone-input | `[data-adflipr-block-id]` | wrapper + `.pbn-canvas-input-shell` | compound row / select / input | flag, divider | validation attrs |
| product | `[data-adflipr-block-id]` | wrapper | `[data-adflipr-product-shell]` | card, image, body, button, price spans | CTA attrs |
| timer | `[data-adflipr-block-id]` | wrapper | timer container + units | digit/label spans | countdown attrs |
| divider | `[data-adflipr-block-id]` | wrapper | outer div | separator line | role=separator |
| radio/checkbox | `[data-adflipr-block-id]` | wrapper | radiogroup/checkbox group | native inputs + labels | validation |
| image/video/social/coupon/feedback | `[data-adflipr-block-id]` | wrapper | inner container | media/link nodes | various |

---

## 10. Hybrid CSS matrix

| Family | Inline merchant | renderCss | Embedded merchant | Classification |
|--------|-----------------|-----------|-------------------|----------------|
| phone-input | **YES** | no | **YES** (select width) + floating-label phone rules | **TEMPORARY MIGRATION DEBT** |
| product | **YES** | no | **YES** (!important responsive) | **TEMPORARY MIGRATION DEBT** |
| timer | **YES** | no | **YES** (generic traits) | **TEMPORARY MIGRATION DEBT** |
| divider | **YES** | no | **YES** | **TEMPORARY MIGRATION DEBT** |
| radio | **YES** | no | radio sheet + generic | **DUPLICATE** (inline + sheet) |
| date | wrapper inline | **MISSING** | generic | **UNKNOWN GAP** |
| text | content RTE only | **YES** | no | **CLEAN** |
| button | structural align | **YES** | rich-text link sheet | **CLEAN** (minor sheet debt) |

---

## 11. `!important` inventory (popup export CSS generation)

| Selector / source | Property | Source | Reason | Merchant? | Necessary? |
|-------------------|----------|--------|--------|-----------|------------|
| hide desktop/mobile | display | `buildBlockDeviceVisibilityCss` | visibility mechanic | no | **YES** |
| product horizontal mobile | flex, width, text-align | `buildProductHorizontalResponsiveCss` | responsive layout override | **YES** | **LEGACY CASCADE REPAIR** until renderCss |
| product grid mobile | grid-template-columns | `buildProductGridResponsiveCss` | responsive layout | **YES** | **LEGACY CASCADE REPAIR** |
| phone select mobile | max-width | `buildPhoneInputCountrySelectResponsiveCss` | responsive override | partial | **LEGACY CASCADE REPAIR** |
| radio input inline | border, appearance, bg | `buildRadioInputExportStyleAttr` | hostile theme defense | partial | **HOSTILE THEME DEFENSE** |
| radio.export.css | border-color checked | sheet | behavior | partial | **YES** |
| floating-label phone focus | border, box-shadow | sheet | focus behavior | no | **YES** |
| embed-responsive errors | border, box-shadow | sheet | validation UX | no | **YES** |
| button rich-text links | color, text-decoration | `buildRichTextLinkExportCss` | presentation | **YES** | migrate to renderCss |

---

## 12. Custom CSS contract

| Question | Answer |
|----------|--------|
| Storage | `formStyle.customCss` (popup-level); per-element `style.customCss` in trait panels (block-level — serialized only if wired through export; popup-level is primary in `serializeStepHtml`) |
| Serialization | Sanitized via `sanitizeCustomCss()` → embedded `<style data-adflipr-popup-custom-css>` in step HTML |
| Artifact-scoped? | **NO** — global custom sheet per step hoisted to head |
| Overrides renderCss? | **YES** — loads after embedded sheets; merchant-authored specificity/`!important` can win |
| renderCss overrides custom? | **NO** — custom is intentional override escape hatch |
| Preview vs embed ordering | Same hoist path; builder preview also injects `formStyle.customCss` in FormCanvas for live preview |
| `!important` allowed? | **YES** (sanitizer does not strip) |
| Hoist participation? | **YES** — marker `data-adflipr-popup-custom-css` |

Custom CSS does **not** block next-family selection; migrations must not assume custom CSS absent.

---

## 13. Consumer completeness matrix

| CSS source | Preview | Thumbnail | Embed | Multi-step | Complete? |
|------------|---------|-----------|-------|------------|-------------|
| renderCss | mountRenderArtifactCss | same | same | same scope per popup | **YES** |
| embedded step styles | hoist | hoist | hoist | per-step HTML | **YES** |
| customCss | hoist + FormCanvas inject | hoist | hoist | per-step | **YES** |
| Text RTE content inline | same export path | same | same | same | **YES** |

**Consumer completeness defect:** **NO**

---

## 14. Candidate scoring table

| Candidate | Risk | Impact | Inline | Embedded | Split | !important | Ownership | Cleanup | Test | **Total** |
|-----------|------|--------|--------|----------|-------|------------|-----------|---------|------|-----------|
| **Phone Input** | 4 | 4 | 4 | 4 | 5 | 3 | 4 | 4 | 4 | **36** |
| **Product** | 5 | 4 | 5 | 5 | 4 | 5 | 4 | 5 | 3 | **40** |
| **Timer** | 4 | 3 | 5 | 3 | 3 | 2 | 3 | 4 | 3 | **30** |
| **Divider** | 3 | 2 | 3 | 3 | 2 | 1 | 4 | 3 | 4 | **25** |
| **Radio/Checkbox** | 3 | 3 | 4 | 4 | 3 | 4 | 3 | 3 | 3 | **30** |
| **Image/Video** | 3 | 3 | 4 | 3 | 2 | 1 | 4 | 3 | 4 | **27** |
| **Date (extend standard field)** | 2 | 3 | 2 | 3 | 2 | 1 | 4 | 4 | 4 | **25** |

---

## 15. Selected next owner/family (revised under §H1-B)

**Phone Input — merchant presentation only (Phase 6E)**

---

## 16. Why it was selected (revised)

1. **Six-gate proof passes for merchant traits** — phone `properties.style` (border, colors, fonts, padding, heights) is merchant-configurable, artifact-specific, not structure/behavior/content  
2. **Completes input-shell merchant presentation arc** — text-input, email, textarea, dropdown already in renderCss; phone merchant chrome is the outlier still inline  
3. **Does not require moving structure/behavior** — floating-label and embed-responsive sheets **stay** shared CSS  
4. **Deletes dual merchant path** — deprecated `inputFieldStyles` export adapters for presentation only  
5. **Coherent with four-concern model** — migration boundary explicitly excludes float mechanics and overflow guards  

Product remains highest **volume** debt but product **responsive layout rules** are **REUSABLE STRUCTURE**, not renderCss — better as a later phase with structure/presentation split.

---

## 17. Why other candidates were deferred

| Candidate | Reason deferred |
|-----------|-----------------|
| Product | Highest inline merchant volume; **responsive horizontal/grid rules are STRUCTURE** — needs split phase, not renderCss-first |
| Timer | Very high inline volume; digit/unit DOM complexity |
| Divider | Smaller user impact; good quick win but lower cleanup leverage |
| Radio/Checkbox | Theme-defense !important on inputs complicates “no !important repair” goal |
| Date | Small extension of standard-field compiler — **bundle with Phase 6E** as follow-on, not separate selection |
| Image/Video/Social | Independent media blocks; no input-shell synergy |

---

## 18. Exact migration boundary (Phase 6E — merchant presentation only)

| Concern | Primary concern | Current owner | Target owner | Action |
|---------|-----------------|---------------|--------------|--------|
| Wrapper margin/width | MERCHANT PRESENTATION | inline on block wrapper | renderCss wrapper rules | MOVE |
| Outline/shell border/bg/padding | MERCHANT PRESENTATION | inline via inputFieldStyles | renderCss `.pbn-canvas-input-shell` | MOVE |
| Input value typography | MERCHANT PRESENTATION | inline inner styles | renderCss input/select rules | MOVE |
| Floating label **presentation** (color, font) | MERCHANT PRESENTATION | partial sheet + gap | renderCss label rules | MOVE merchant only |
| Country select **merchant** chrome (border, color) | MERCHANT PRESENTATION | inline | renderCss select descendant | MOVE |
| Phone row **merchant** spacing | MERCHANT PRESENTATION | inline flex | renderCss compound row | MOVE |
| Flag/divider **merchant** chrome | MERCHANT PRESENTATION | inline | renderCss descendants | MOVE |
| Generic block-trait mobile for phone **traits** | MERCHANT PRESENTATION | embedded | renderCss mobile diff | MOVE merchant only |
| Country select width mobile | REUSABLE STRUCTURE | embedded @media | **shared structural CSS** | **RETAIN** — not renderCss |
| Phone row flex scaffolding | REUSABLE STRUCTURE | floating-label sheet | **shared structural CSS** | **RETAIN** |
| Validation/error states | RUNTIME BEHAVIOR | embed-responsive sheet | runtime behavior CSS | **RETAIN** |
| Floating-label float animation | RUNTIME BEHAVIOR | floating-label.export.css | runtime behavior CSS | **RETAIN** |

**Model source:** `element.properties.style`, `element.properties` (country selector flags)  
**DOM owners:** block wrapper → `.pbn-canvas-input-shell` → compound row → select/input descendants  
**Properties moving to renderCss:** outline, value typography, padding, borders, colors, heights, select max-width, row layout, label static/floating presentation  
**Properties remaining outside renderCss:** validation error visibility, float animation mechanics, native input `type=tel` behavior attrs  

---

## 19. Mobile emission strategy recommendation

**Desktop base rules inline-equivalent in renderCss + mobile `@media (max-width: 480px)` diff only** — same pattern as `compileStandardFieldPresentationRenderCss` / textarea / dropdown. **No desktop inline merchant styles on painted owners.**

---

## 20. Compiler topology recommendation

Add **`compilePhoneInputPresentationRenderCss`** (or extend standard-field module with `phone-input` discriminant and compound selectors) invoked from `compilePopupRenderCss()`.

Selectors:

```text
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"]
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"] .pbn-canvas-input-shell
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"] [data-block-type="phone-input"] …
```

Reuse `normalize*Presentation` + conflict error pattern from Phase 6 modules.

---

## 21. Multi-step/conflict recommendation

Union presentation keys per `block-id` across steps; throw conflict error if same phone block-id diverges across steps (match standard-field policy).

---

## 22. Test plan (implementation phase)

- Compiler unit tests (desktop/mobile declarations, floating vs static, country selector on/off)
- Serializer negative-inline assertions (no merchant props on shell/input/select)
- Exclude phone **merchant traits** from `buildBlockTraitResponsiveCss`; **retain** `buildPhoneInputCountrySelectResponsiveCss` as shared structural CSS
- Chromium computed-style desktop/mobile (country select width, outline, label)
- Multi-step union + conflict tests
- Artifact isolation (two popups)
- Consumer parity: preview, thumbnail, embed
- Phase 0 baseline + G1 contract regression
- Optional: bundle **date** block into standard-field compiler extension tests

---

## 23. Cleanup forecast

| Symbol/path | Why obsolete | Action |
|-------------|--------------|--------|
| `phone-input/serialize.ts` inline `styleAttr` merchant paths | renderCss owns presentation | DELETE inline merchant |
| `inputFieldStyles.ts` phone export adapters | replaced by renderCss | DELETE or canvas-only |
| `buildPhoneInputCountrySelectResponsiveCss` | structural embed guard | **RETAIN** in shared structural CSS |
| `phone-input` from generic `buildBlockTraitResponsiveCss` | dedicated compiler for merchant traits | EXCLUDE type from generic consumer |
| Merchant phone rules in `floating-label.export.css` | structural vs presentation split | RETAIN structural; move **merchant chrome only** to renderCss |
| `@deprecated` comments on standard field adapters | cleanup | UPDATE docs |

---

## 24. Architecture risks

- Compound row split-target regression if desktop/mobile target different nodes
- Hostile theme on native inputs may still need minimal defense (evaluate vs radio pattern)
- Floating-label sheet interaction must remain behavior-stable
- Date block presentation gap should be closed in same phase family to avoid invisible styling

---

## 25. Final verdict

**ACCEPT WITH ARCHITECTURE CLARIFICATION** — four-concern model applied; renderCss is **not** a dumping ground (**NO**); next action reframed to **merchant presentation only**.

---

## 26. Recommended implementation phase name

**Phase 6E — Phone Input Merchant Presentation CSS** (renderCss for traits only; shared sheets unchanged for structure/behavior)

Optional: **Phase 6E.1 — Date standard-field merchant presentation extension**

Future: **Phase 7A — Product merchant presentation renderCss** + **Phase 7B — Product responsive structure** (shared CSS, separate from renderCss)
