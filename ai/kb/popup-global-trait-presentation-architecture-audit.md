# Popup Global Trait Presentation Architecture Audit

**Status:** Audit and decision only — **no production migration in this phase**  
**Date:** 2026-07-06  
**Scope:** Repository-wide merchant trait presentation pipeline through Phase 6D  
**Related:** [`popup-production-css-rte-ownership-audit.md`](./popup-production-css-rte-ownership-audit.md), [`popup-rte-command-ownership-phase-g2a-audit.md`](./popup-rte-command-ownership-phase-g2a-audit.md), [`popup-saved-artifact-storefront-css-parity-phase-g2e-audit.md`](./popup-saved-artifact-storefront-css-parity-phase-g2e-audit.md), [`popup-remaining-block-css-phase6a-audit.md`](./popup-remaining-block-css-phase6a-audit.md), [`popup-dropdown-css-phase6d-report.md`](./popup-dropdown-css-phase6d-report.md), [`popup-css-architecture-overall-summary-through-phase4.md`](./popup-css-architecture-overall-summary-through-phase4.md), [`popup-export-css-production-build-fix-phase-g1-report.md`](./popup-export-css-production-build-fix-phase-g1-report.md) (G1 fix — **implemented**)

---

## 1. Executive summary

The remaining popup CSS work is **not** thirteen independent presentation architectures and **not** a single undifferentiated block-family list. It is **one shared inline-first trait presentation engine** whose **emission paths** are being replaced family-by-family with artifact-scoped `renderCss`.

| Layer | Current state | Final target |
| ----- | ------------- | ------------ |
| Trait model | `properties.style` (+ some `properties.*` orphans) | Same model; orphans deleted |
| Canonical normalization | **Partial** — `ElementStyles.ts` device resolvers + family resolvers for migrated types | All merchant traits normalize once in `ElementStyles` (or colocated domain modules) |
| Canvas | Mix of canonical adapters + legacy `getElementStyle` / inline builders | Canonical resolver → thin React adapter |
| Serializer export | Mix of structure-only (migrated) + heavy `style=""` (13 families) | Structure + identity + behavior hooks only |
| Mobile responsive | `buildBlockTraitResponsiveCss` (wrapper-targeted) + family-specific embedded CSS | Artifact `renderCss` diff-only mobile on **proven owners** |
| Runtime embedded CSS | `.export.css` sheets + device visibility + product/timer/spinner behavior | Behavior/structure only; merchant traits in `renderCss` |

**Verdict:** **Hybrid migration** — extract **shared compiler mechanics**, keep **family-specific DOM ownership compilers**, retire the **legacy trait emission engine** (`getElementStyle` export path + `buildBlockTraitResponsiveCss` + serializer inline merchant CSS).

**Blockers before next family migration:**

1. **Production `.export.css` webpack stub** — **Fixed in Phase G1** — see [`popup-export-css-production-build-fix-phase-g1-report.md`](./popup-export-css-production-build-fix-phase-g1-report.md). Re-save popups saved before deploy.
2. **RTE / trait split ownership on Text** — BLOCKING for confident Text trait expansion; does not block non-Text families but must be contracted before Text trait changes. **Command-level contract:** [`popup-rte-command-ownership-phase-g2a-audit.md`](./popup-rte-command-ownership-phase-g2a-audit.md).

**Recommended next implementation phase:** **Infrastructure gate** (`.export.css` fix + shared presentation compiler mechanics extraction), **not** another block-family migration.

---

## 2. Current trait pipeline

### End-to-end path (merchant Save → storefront computed style)

```text
Trait UI (StyleSection, *Properties panels)
  ↓ onStyleChange / handleStyleChange
FormElement.properties.style  (+ occasional properties.align, properties.content)
  ↓
┌─────────────────────────────────────────────────────────────────────────┐
│ CANONICAL NORMALIZATION LAYER (partial)                                  │
│ ElementStyles.ts: resolveDevicePaddingCss, resolveCornerRadiusCss,     │
│ resolveDeviceMarginCss, resolveDeviceBoxShadowCss, resolveInputFontSizePx│
│ Family resolvers: resolveStandardField*, resolveTextarea*, resolveDropdown*│
│ Legacy monolith: getElementStyle(), getChoiceGroupShellStyle(), etc.    │
└─────────────────────────────────────────────────────────────────────────┘
  ↓                              ↓                              ↓
Canvas adapters          Serializer path                 (no direct path)
React inline styles      serializeElements.ts
                         ├─ getExportWrapperStyleFromLayout (wrapper)
                         └─ block serialize.ts
                              ├─ MIGRATED: structure-only HTML
                              └─ LEGACY: styleAttr(...) inline merchant CSS
  ↓
serializeLayout.ts::serializeStepHtml
  ├─ step <style> blocks: POPUP_*_EXPORT_CSS (4 sheets)
  ├─ buildEmbeddedStepResponsiveCss
  │    ├─ buildBlockDeviceVisibilityCss (runtime)
  │    ├─ buildBlockTraitResponsiveCss (LEGACY mobile traits → wrapper)
  │    ├─ buildPhoneInputCountrySelectResponsiveCss
  │    └─ buildProduct*ResponsiveCss
  └─ htmlByStep + metadata
  ↓
compilePopupRenderCss(snapshot, artifactScopeId)
  ├─ formSurface, outerLayout, columnsContainer, column
  ├─ button, text, standardField, textarea, dropdown
  ├─ positionedDecorative, teaser, gamification
  └─ CompiledPopupArtifact.renderCss
  ↓
Consumers: embed runtime, preview, thumbnails (mountRenderArtifactCss)
  ↓
Storefront: artifact renderCss stylesheet + hoisted step <style> + runtime behavior CSS
```

### Authoritative files

| Stage | Files |
| ----- | ----- |
| Trait UI | `property-panels/shared/StyleSection.tsx`, `*Properties.tsx` |
| Model | `types.ts` (`FormStyle`, block `style` bags) |
| Defaults | `ElementDefaults.ts`, `createInputStyle()` |
| Canonical resolvers | `ElementStyles.ts` |
| Wrapper layout | `blocks/shared/layoutStyles.ts` → `getExportWrapperStyleFromLayout` |
| Serializer orchestration | `serializeElements.ts`, `serializeLayout.ts`, `index.ts` |
| Inline builders (legacy) | `styles/inline/*.ts` |
| Generic responsive | `responsiveDeviceCss.ts` |
| renderCss composition | `compilePopupRenderCss.ts`, `*Presentation.ts` |
| Embedded sheets | `styles/sheets/*.export.css`, `styles/sheets/index.ts` |
| Runtime mount | `embed/mountRenderArtifactCss.ts`, `embed/runtime.ts` |

---

## 3. Complete trait ownership inventory

Legend: **M** = migrated to renderCss, **L** = legacy inline/embedded, **H** = hybrid, **N** = not applicable

| Trait / property | Block consumers | Device model | Canonical resolver | Canvas | Serializer inline | Generic responsive | renderCss | DOM owner (typical) | Architecture | Final recommendation |
| ---------------- | --------------- | ------------ | ------------------ | ------ | ----------------- | ------------------ | --------- | ------------------- | ------------ | ------------------ |
| `marginDesktop/Mobile` | Most blocks | paired | `resolveDeviceMarginCss`, `getExportWrapperStyleFromLayout` | wrapper | wrapper (legacy) / stripped (M) | wrapper (L) | wrapper (M) | wrapper | M/L/H | **Shared** wrapper normalization |
| `paddingDesktop/Mobile` | shell blocks | paired | `resolveDevicePaddingCss` | shell/div | inline (L) / renderCss (M) | **wrapper (L defect)** | shell (M) | shell — not wrapper | H | **Shared** resolver, **family** selector |
| `elementWidth`, `elementPosition` | width blocks | desktop | `applyElementWidthPercentPosition` | wrapper | wrapper via layout | — | wrapper | wrapper | M/L | **Shared** |
| `cornerRadius` / `cornerRadiusMobile` | bordered blocks | paired | `resolveCornerRadiusCss` | shell | inline (L) | wrapper (L defect) | shell (M) | shell | H | **Shared** |
| `boxShadowDesktop/Mobile` | many | paired | `resolveDeviceBoxShadowCss` | shell | inline (L) | — | shell (M) | shell | M/L | **Shared** |
| `backgroundColor` | many | — | via resolvers / `getElementStyle` | shell | inline (L) | — | shell (M) | shell | M/L | **Shared** |
| `borderStyle/Width/Color` | many | — | `resolveInputShellChromeRules` / `getElementStyle` | shell | inline (L) | — | shell (M) | shell | M/L | **Shared** |
| `height` | fields, coupon, timer | — | `applyInputHeightTraitToShell`, `getElementStyle` | outline or field | inline (L) | — | owner-specific (M) | family-specific | H | **Family** height owner |
| `fontSize` / `fontSizeMobile` | typography blocks | paired | `resolveInputFontSizePx`, `resolveResponsiveFontSize` | field/shell | inline (L) | **wrapper (L defect)** | field/shell (M) | field or shell | H | **Shared** value, **family** owner |
| `fontFamily`, `fontWeight`, `lineHeight`, `letterSpacing` | many | — | value resolvers / `getElementStyle` | field/shell | inline (L) | — | field (M) | field | M/L | **Shared** typography normalization |
| `fontColor` / `textColor` | many | — | same | field | inline (L) | — | field (M) | field | M/L | **Shared** |
| `textAlignment` | text, fields | — | `resolveTextPaintedCssRules`, value resolvers | block/field | content inline (RTE) + renderCss (M text) | — | painted (M) | text `> div`; fields | **H RTE conflict** | **Trait** for block-wide; **content** for partial only |
| `label`, `labelText`, `labelColor` | fields, choices | — | `resolveStandardFieldStaticLabelCssRules` | label | structure (M fields) / inline (L) | — | label (M) | `> label` | M/L | **Shared** label resolver |
| `floatingLabel` | text-input, email, phone, date | — | `resolveStandardFieldFloatingLabelCssRules` | outline structure | structure + `.export.css` behavior | — | renderCss (M std field) | outline + floating DOM | M + embedded behavior | **Shared** + embedded structure CSS |
| `focusBorderColor` | input family | — | outline resolvers | outline CSS var | inline var (L) / renderCss (M) | — | outline `:focus-within` | outline | M/L | **Shared** |
| `placeholderColor` | input family | — | `resolveInputPlaceholderColor` | CSS var | var on outline (M) | — | `::placeholder` (input/textarea) | field pseudo | M/L | **Shared**; **N/A** for native select |
| `hideOnDesktop/Mobile` | blocks, layouts | — | `resolveExportDeviceHideAttrs` | attrs | attrs | `buildBlockDeviceVisibilityCss` | — | wrapper attrs | **Runtime** | **Keep** embedded visibility |
| `fillColor`, `outlineColor` | checkbox, radio | — | choice builders | option input | inline (L) | — | — | native input | L | **Family** choice presentation |
| Product nested `*Style` | product | — | commerce builders | canvas | inline (L) | product layout CSS | — | card descendants | L | **Family** compound compiler |
| Timer unit/card traits | timer | mixed | local serialize | canvas | inline (L) | — | — | timer DOM | L | **Family** compound |
| Spinner wheel traits | spinner | — | config JSON + constants | canvas | inline structural (L) | — | gamification shell only | canvas runtime | Behavior | **Behavior** + partial renderCss |
| `properties.align` (Text) | text (orphan) | — | **none** | TextCanvas RTE | **not read** | — | uses `style.textAlignment` | — | **Defect** | **DELETE** |

---

## 4. Remaining family classification (13 verified)

Verified from `responsiveDeviceCss.ts` `MIGRATED_BLOCK_TRAIT_TYPES` skip set + `blockRegistry.ts`.

| Family | DOM topology (summary) | Classification | Primary CSS sources today |
| ------ | -------------------- | -------------- | ------------------------- |
| **divider** | wrapper → outer frame → inner line | **1** Pure shared trait consumer | `buildDividerOuterStyle`, `getElementStyle` path, generic responsive |
| **phone-input** | wrapper → label? → outline → row(country+tel) | **4** Compound + **5** behavior | deprecated std-field inline adapters, phone responsive select, floating `.export.css` |
| **check-box** | wrapper → label? → group → rows | **1** + choice semantics | `getChoiceGroupShellStyle`, `buildCheckboxGroupStyle`, generic responsive |
| **radio** | wrapper → label? → radiogroup → rows | **1** + native radio sheet | same + `POPUP_RADIO_EXPORT_CSS`, generic responsive |
| **date** | wrapper → label? → outline → input (classes only) | **2** Shared norm + **unmigrated renderCss** | structure-only serialize; **no** inline presentation; **no** renderCss compiler yet |
| **timer** | wrapper → container → units/cards OR expired CTA | **4** Compound + **5** behavior | all inline in serialize.ts, timer wrapper layout |
| **spinner** | wrapper → host → canvas + result overlay | **5** Behavior-heavy | `spinnerStyles.ts` structural inline + gamification renderCss shell |
| **image** | wrapper → label? → container → img | **1** Shared media consumer | `mediaAndFeedbackStyles`, generic responsive |
| **video** | wrapper → container → iframe/video | **4** Compound + **5** behavior | inline in serialize.ts, generic responsive |
| **product** | wrapper → shell → cards (horizontal/vertical) | **4** Compound | `commerceAndCrossStyles` + dedicated product responsive CSS |
| **coupon** | wrapper → coupon shell → code + copy btn | **1** + **5** copy behavior | `buildCouponShellStyle`, generic responsive |
| **feedback** | wrapper → question → radiogroup variants | **4** Compound + **5** behavior | `mediaAndFeedbackStyles`, generic responsive |
| **social** | wrapper → flex container → links → img | **1** Shared consumer | inline in serialize.ts, generic responsive |

---

## 5. `buildBlockTraitResponsiveCss` verdict

### Answers

| # | Question | Answer |
| - | -------- | ------ |
| 1 | Temporary generator for unmigrated families? | **Yes** — skip list proves intent |
| 2 | Legitimate cross-family semantics to survive? | **No** — semantics are trait-generic but **owner-wrong** |
| 3 | Reads same traits for unrelated DOM owners? | **Yes** — padding/font-size/border-radius applied uniformly |
| 4 | Assumes wrapper is painted owner? | **Yes** — selector is always `[data-adflipr-block-id]` wrapper |
| 5 | Split-target defects? | **Yes** — proven for field families (6B–6D); latent for all 13 remaining families using shell/field inline desktop + wrapper mobile |
| 6 | Rules for properties inline on descendants? | **Yes** — e.g. checkbox group shell padding desktop inline, mobile padding on wrapper |
| 7 | Properties that should remain generic? | **Only** `hideOnDesktop/Mobile` via `buildBlockDeviceVisibilityCss` — not this function |
| 8 | Deletable after migration? | **Yes** — when all 13 families emit mobile diffs in renderCss on correct owners |
| 9 | Shared artifact compiler replaces it? | **Yes** — per-family `diffMobilePresentation` on proven owners |
| 10 | Obsolete code if removed | `buildBlockTraitResponsiveCss` function body; tests asserting wrapper mobile rules for migrated types |

### Verdict

**DELETE** — after remaining families migrate. **Do not REFACTOR** in place (would preserve wrong ownership). Timing: **final cleanup phase** after last family compiler, not before.

---

## 6. Global inline serializer inventory

| Family | Inline merchant presentation | Structural/behavior inline | Notes |
| ------ | ---------------------------- | -------------------------- | ----- |
| text-input, email-input, textarea, dropdown, button, text, cross, floating-image | **Removed** (renderCss) | flex-shrink, pointer-events (floating-image) | Migrated |
| date | **None** | classes only | Presentation gap — needs renderCss |
| divider | **Heavy** — outer + inner | separator role | MERCHANT |
| phone-input | **Heavy** — full field chrome | row/country structure | MERCHANT + deprecated adapters |
| check-box, radio | **Moderate** — group + options | accentColor, native radio attrs | MERCHANT |
| image, video | **Heavy** — container + media | aspect ratio wrap | MERCHANT |
| product | **Heavy** — shell + card descendants | data attrs, flex layout | MERCHANT |
| coupon | **Moderate** — shell + code typography | copy button hook | MERCHANT |
| feedback | **Heavy** — all nodes | radiogroup role | MERCHANT |
| social | **Light** — container + img | link target | MERCHANT |
| timer | **Heavy** — all presentation | data attrs | MERCHANT |
| spinner | **Structural constants** — canvas/result | data-config JSON | STRUCTURE + runtime |

### Serializer contract feasibility

**PARTIAL** — merchant presentation can be removed from export HTML for **all block types** except:

| Exception | Classification | Keep inline? |
| --------- | -------------- | ------------ |
| Text `properties.content` partial RTE spans | LEGITIMATE CONTENT | In HTML content, not `style=""` on wrapper |
| Spinner canvas/result structural dimensions | ARTIFACT STRUCTURE | Minimal structural until runtime owns paint |
| Native `disabled`/`required`/validation attrs | NATIVE SEMANTICS | Attributes, not presentation |
| Device hide data attrs | RUNTIME BEHAVIOR | Attributes |
| Floating-label behavior structure classes | BEHAVIOR | Classes + embedded CSS until fully artifact-scoped |

---

## 7. Migrated compiler pattern comparison

| Compiler | Collector | Conflict | Normalized shape | Mobile strategy | Canvas sharing |
| -------- | --------- | -------- | ---------------- | --------------- | -------------- |
| formSurface | form-level | — | single | diff mobile | N/A |
| outerLayout | layout id | yes | per-layout | diff mobile | partial |
| columnsContainer | layout id | yes | mechanics | diff mobile | partial |
| column | layout+column id | yes | per-column | diff mobile | partial |
| button | block id | yes | wrapper+painted+align | diff mobile | yes (`resolveButtonPaintedCssRules`) |
| text | block id | yes | wrapper+painted+link | diff mobile | yes |
| standardField | block id | yes | wrapper+outline+value+label | diff mobile | yes |
| textarea | block id | yes | wrapper+outline+field+label | diff mobile | yes |
| dropdown | block id | yes | wrapper+outline+select+label | diff mobile | yes |
| positionedDecorative | block id | yes | shell (+img) | diff mobile | yes |
| teaser | style-only | — | painted | diff mobile | partial |
| gamification | surface | — | shell | — | partial |

### Repeated mechanics (duplicated ~12×)

```text
declarationsFromRules()
diffMobilePresentation()
collectArtifact*Presentations() — Map by blockId, stepIds merge
*PresentationConflictError
deterministic sort (localeCompare)
artifactScopeId + POPUP_ATTR_ARTIFACT + POPUP_ATTR_BLOCK_ID selectors
@media (max-width: 480px) diff-only wrap
joinCssChunks()
```

### Domain-specific (must stay family-owned)

```text
resolveStandardFieldOutlineCssRules vs resolveTextareaOutlineCssRules vs resolveDropdownOutlineCssRules
height owner (outline vs textarea field)
choice group / product card / timer unit topology
text painted + RTE content boundary
positioned decorative shell vs image
```

**Conclusion:** **Mechanically shared infrastructure is duplicated.** Domain normalization is correctly family-scoped.

---

## 8. Shared vs family-specific boundary

### Share (proven identical mechanics)

- Multi-step collection by `blockId`
- Presentation key JSON + conflict errors
- `diffMobilePresentation` base/mobile diff
- Artifact selector construction (`cssEscapeAttributeValue`)
- Media query wrapping at 480px
- CSS chunk joining
- Wrapper layout keys extraction from `getExportWrapperStyleFromLayout`

### Share (proven identical semantics — already in ElementStyles)

- `resolveDevicePaddingCss`, `resolveDeviceMarginCss`, `resolveCornerRadiusCss`, `resolveDeviceBoxShadowCss`
- `resolveInputFontSizePx`, `resolveInputPlaceholderColor`
- Standard field label/outline/value resolvers (extended per family owner)

### Family-specific (proven different)

- Painted owner selectors (`input` vs `textarea` vs `select` vs `button` vs `> div`)
- Height ownership (outline vs field)
- Choice native control presentation (radio/checkbox accent)
- Product nested style models
- Timer/spinner/feedback variant topologies
- Video iframe vs native branch
- Phone compound row

---

## 9. Candidate architecture comparison

### Option A — Family-by-family only

| Pro | Con |
| --- | --- |
| Proven correct ownership per phase | Duplicates collector/conflict/diff/mobile wrap ~10 more times |
| Low risk per PR | Does not retire `getElementStyle` export path centrally |
| | Slower deletion of `buildBlockTraitResponsiveCss` |

### Option B — Single shared trait compiler + adapters

| Pro | Con |
| --- | --- |
| One emission path | **Over-abstracts** DOM owners (6A–6D proved height/field differences) |
| | Risk mega-resolver with block-type flags (explicitly forbidden) |

### Option C — Hybrid (recommended)

```text
presentationCompilerKit (mechanics only)
  +
shared trait normalization (ElementStyles device + domain resolvers)
  +
family presentation compilers (owner selectors + structural constants)
```

| Pro | Con |
| --- | --- |
| Deletes legacy engine systematically | Requires one infrastructure PR before acceleration |
| Preserves ownership clarity from 6B–6D | Team must resist merging domain into kit |
| Matches repository evidence | |

### Option D — None better found

---

## 10. Final recommended architecture

**Option C — Hybrid domain architecture**

1. **Extract** `presentationCompilerKit.ts` (or equivalent) with: collector factory, conflict error base, diffMobile, declarationsFromRules, artifact block selector helpers, mobile wrap.
2. **Expand** `ElementStyles.ts` canonical resolvers; **delete** `getElementStyle` from export path as families migrate.
3. **Family compilers** remain separate files; thin wrappers over kit.
4. **Delete** `buildBlockTraitResponsiveCss` when skip list covers all content block types.
5. **Retire** `styles/inline/*` merchant builders as families migrate (keep phone until compound migration).

---

## 11. Trait/RTE ownership boundary

Contract (from [`popup-production-css-rte-ownership-audit.md`](./popup-production-css-rte-ownership-audit.md)):

| Concern | Owner |
| ------- | ----- |
| Block-wide color, bg, padding, margin, border, radius, shadow, height, font, alignment | `properties.style` → `renderCss` |
| Partial span color, font-size, bold, italic, underline, highlight | `properties.content` inline HTML |
| Block-wide alignment | **`style.textAlignment` only** — RTE must update trait, not `execCommand` on content |
| `properties.align` | **DELETE** — orphaned, unused by serializer/renderCss |
| Full-selection color/font-size | Update trait; clear conflicting inline on full selection (existing partial behavior) |
| Link color/decoration | Trait + descendant `a` rules in `textPresentation` (already migrated) |

Generic trait compiler must **not** sanitize or strip `properties.content` inline styles.

---

## 12. Production `.export.css` interaction

| Sheet | Depends on it (remaining) | Migrated families still need it? |
| ----- | ------------------------- | -------------------------------- |
| `floating-label.export.css` | phone-input, date (floating) | std field **no** (renderCss); structure/behavior **yes** for floating modes |
| `radio.export.css` | radio | **yes** — native appearance |
| `embed-responsive.export.css` | visibility, error shell, utilities | partial — error shell runtime |
| `popup-entrance.export.css` | entrance animation | runtime behavior |

**If production stub broken:** floating label behavior, radio custom appearance, responsive utilities, error shell — **broken on saved popups**.

**Trait architecture reduces `.export.css`?** Yes — as field/choice families migrate merchant paint to renderCss; sheets narrow to structure/behavior only (Phase 6B pattern).

**Gate:** **BLOCKING** — verify production `craco build` emits real CSS strings before next migration phase.

---

## 13–17. Final contracts

### Serializer (export HTML)

```text
structure + identity + semantic attributes + behavior hooks + RTE content HTML
```

No merchant `style=""` on block presentation nodes.

### Canvas

```text
canonical normalization (ElementStyles resolvers) → standardFieldRulesToCssProperties → React style
```

### renderCss

```text
properties.style → normalize*Presentation → artifact-scoped selectors → base + diff-only mobile
```

### Runtime / embedded CSS

```text
visibility toggles
validation/error activation
floating-label motion/structure
native radio appearance (until choice compiler)
entrance transitions
product/timer/spinner layout behavior where DOM topology requires it
```

### Embedded CSS end-state

```text
zero embedded MERCHANT trait presentation
```

Retain embedded **runtime/behavior** CSS until equivalent artifact or runtime modules exist. `buildEmbeddedStepResponsiveCss` shrinks to: visibility + unmigrated behavior modules → eventually **visibility + entrance only**.

---

## 18. Obsolete code deletion inventory

| Code | Consumers | Replacement | Safe deletion point |
| ---- | --------- | ----------- | ------------------- |
| `buildBlockTraitResponsiveCss` | 13 families via `buildEmbeddedStepResponsiveCss` | Family renderCss mobile diffs | After last family migrated |
| `getElementStyle` export usage | checkbox, radio, feedback, coupon, divider, image paths | Family canonical resolvers + renderCss | Per-family migration |
| `styles/inline/blockStyles.ts` merchant builders | checkbox, radio, divider | Family compilers | Per-family |
| `styles/inline/mediaAndFeedbackStyles.ts` | image, video, feedback | Family compilers | Per-family |
| `styles/inline/commerceAndCrossStyles.ts` | product, coupon | Family compilers | Per-family |
| `inputOutlineStyle`, `innerInputFieldStyle` (phone) | phone serialize | phone compound compiler | Phone phase |
| `buildFieldLabelStyle` | unused in export? phone legacy | shared label resolver | Phone/date migration |
| `properties.align` | TextCanvas RTE only | `style.textAlignment` | RTE fix phase |
| Global `input::placeholder` in floating-label.export.css | unmigrated input only | artifact placeholder rules | As families migrate |
| Duplicate `diffMobilePresentation` per compiler | 12 files | presentationCompilerKit | Infrastructure phase |

---

## 19. Recommended implementation sequence

### Phase G1 — Production `.export.css` gate (BLOCKING)

- **Scope:** Verify/fix webpack `asset/source` for `*.export.css` in production build; CI grep guard.
- **Files:** `craco.config.js`, `styles/sheets/index.ts`, CI script.
- **Delete:** nothing yet.
- **Tests:** `exportCssSheets.test.ts` + **new** production bundle string grep.
- **Gate:** Saved popup step `<style>` contains `.pbn-floating-field`, not `mini-css-extract-plugin`.

### Phase G2 — RTE ownership contract (BLOCKING for Text)

- **Scope:** RTE alignment → `style.textAlignment`; delete `properties.align` writes; document full-selection trait sync.
- **Files:** `TextCanvas.tsx`, `InlineEditor.tsx`, tests.
- **Gate:** No new `text-align` in content on block-wide alignment ops.

### Phase G3 — Shared presentation compiler kit (INFRASTRUCTURE)

- **Scope:** Extract duplicated collector/conflict/diff/selector/media mechanics; refactor migrated compilers to use kit (no semantic change).
- **Files:** new `presentationCompilerKit.ts`, refactor `*Presentation.ts`.
- **Tests:** All existing presentation compiler tests green.

### Phase G4 — Field cluster completion

- **Scope:** `date` → standard-field extension compiler (structure-only serialize already); then `phone-input` compound.
- **Delete:** phone deprecated inline adapters; narrow floating-label.export.css.

### Phase G5 — Choice cluster

- **Scope:** `check-box`, `radio` — shared choice-group normalization + family selectors; migrate `POPUP_RADIO_EXPORT_CSS` merchant rules into renderCss where possible.

### Phase G6 — Media / commerce / utility cluster

- **Scope:** `divider`, `image`, `social`, `coupon`, `video`, `product`, `feedback`, `timer`, `spinner` (ordered by complexity).

### Phase G7 — Final cleanup

- **Scope:** Delete `buildBlockTraitResponsiveCss`; delete obsolete `styles/inline/*`; shrink `buildEmbeddedStepResponsiveCss` to runtime-only; audit `.export.css` for deletion candidates.

---

## 20. Risks and unresolved decisions

| Risk | Status |
| ---- | ------ |
| Production `.export.css` stub on adcarts | Unresolved until G1 |
| Text RTE split ownership | Unresolved until G2 |
| Dropdown `placeholder` trait not serialized | Product/content gap — not CSS |
| Whether radio native appearance can fully leave `.export.css` | Needs choice compiler gate |
| Product responsive `!important` rules | May remain as **layout behavior** until product compiler |
| Spinner canvas paint vs gamification shell | Behavior-heavy — may never fully move to renderCss |

---

## 21. Test evidence

Ran existing suites (no production code changes):

```text
17 suites, 176 tests PASS
```

Including: `dropdownPresentation*`, `textareaPresentation*`, `standardFieldPresentation*`, `textPresentation*`, `responsiveCssBaseline.browser`, `popupHtmlSerializer`, `responsiveDeviceCss`, `popupProductionCssRte*`, `exportCssSheets`, `renderArtifactCssLifecycle`.

Phase 0 contract: **18 migrated / 0 red / 5 controls — unchanged**.

---

## 22. Exact next implementation phase

**Phase G1 — Production `.export.css` build verification and fix gate**

Not another block-family CSS migration.

---

## Final decisions (required format)

1. **Audit verdict:** **Hybrid** — one shared inline-first trait engine (normalization + inline serialize + wrapper-targeted generic responsive) being replaced by shared mechanics + family-specific renderCss compilers; not 13 independent architectures.

2. **Current trait pipeline:** Trait UI → `properties.style` → `ElementStyles` resolvers (partial) + legacy `getElementStyle`/inline builders → canvas inline OR serializer `style=""` OR `compilePopupRenderCss` → step embedded CSS + artifact `renderCss` → runtime mount.

3. **Remaining generic responsive consumers:** divider, phone-input, check-box, radio, date, timer, spinner, image, video, product, coupon, feedback, social — **count: 13** (verified).

4. **Shared trait engine exists:** **PARTIAL** — shared trait **normalization** and **wrapper layout** exist; shared **emission engine** is legacy inline + `buildBlockTraitResponsiveCss`; migrated families use family compilers.

5. **`buildBlockTraitResponsiveCss` final verdict:** **DELETE** after all families migrate; not refactor.

6. **Serializer inline merchant presentation:** Remaining on all **13** families (date: wrapper only via serializeElements; no field inline). Properties: padding, margin, radius, shadow, bg, border, typography, height, family-specific nested styles.

7. **Final serializer contract:** **PARTIAL** — merchant presentation removable for all blocks; exceptions: RTE content HTML, spinner structural constants, native attrs, behavior classes.

8. **Recommended final architecture:** **C** — shared compiler mechanics + shared trait normalization + family-specific DOM ownership compilers.

9. **Shared infrastructure boundary:** Collector/conflict/diff/mobile-wrap/artifact selectors; device trait resolvers; wrapper layout from `getExportWrapperStyleFromLayout`.

10. **Family-specific boundary:** Painted owner selectors, height ownership, compound DOM (phone/product/timer), native control presentation, behavior/runtime modules.

11. **Canvas normalization contract:** Canonical `ElementStyles` resolvers → thin React adapter; no duplicate trait interpretation in canvas.

12. **renderCss compiler contract:** `normalize*Presentation` per family → kit emission → `compilePopupRenderCss` composition.

13. **RTE ownership contract:** Block-wide traits → `style` → renderCss; partial formatting → `content`; RTE block-wide ops update traits; no `properties.align`.

14. **`properties.align`:** **DELETE**

15. **Production `.export.css` gate:** **BLOCKING** — floating label, radio, embed-responsive, error shell on saved popups.

16. **Embedded merchant CSS end-state:** **Zero merchant traits**; retain visibility, behavior, entrance, and unmigrated native/structure sheets until migrated.

17. **Obsolete production code:** `buildBlockTraitResponsiveCss`, `getElementStyle` export path, `styles/inline/*` merchant builders, duplicate per-compiler mechanics, `properties.align`, deprecated phone inline adapters.

18. **Next implementation phase:** **G1 — Production `.export.css` gate** (not family migration).

19. **Next phase must migrate another family immediately:** **NO**

20. **Ready to implement:** **NO** for family migration; **YES** for G1/G2/G3 infrastructure gates.
