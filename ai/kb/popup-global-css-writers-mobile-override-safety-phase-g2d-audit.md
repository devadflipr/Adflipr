# Phase G2D — Global Remaining CSS Writers and Mobile Override Safety Audit

**Date:** 2026-07-06  
**Mode:** READ-ONLY — no production code, test, or migration changes  
**Evidence:** Independent inspection of current repository code (reports used as context only)

---

## 1. Executive verdict

Artifact-scoped `renderCss` **is converging** as the canonical merchant-presentation channel for migrated block families, but the architecture is **not yet globally safe**:

1. **Ten unmigrated content families** still emit **desktop merchant presentation inline** while **mobile overrides** flow through `buildBlockTraitResponsiveCss()` embedded `@media` rules on the **block wrapper** — the classic **PCL-008** failure mode (`desktop inline + mobile stylesheet` on the same property cannot cascade safely without `!important`).
2. **Phone Input (Phase 6E)** is migrated to `renderCss` for field chrome but **`serializeElements.ts` omits `phone-input` from wrapper margin/width stripping** that peer standard-field families already receive — creating a **same-property wrapper collision** (inline desktop margin on `[data-adflipr-block-id]` vs artifact-scoped `renderCss` mobile margin).
3. **Text RTE** preserves legitimate content-local inline formatting, but **block-level RTE properties** (e.g. `font-size`, `color` on block tags) can **defeat inherited responsive `renderCss`** on the painted wrapper — a **partial** centralization gap addressed only partially by `normalizeTextRichContentHtml` (strips block `text-align` and transparent `background-color`, not `font-size`/`color`).
4. **Traits are model sources, not export writers** for migrated families — centralized **partially**. Unmigrated families still route trait values through serializers into inline HTML.
5. **No cross-family writer** injects merchant inline CSS *after* `compilePopupRenderCss()`; serialization is a single compile pass.

**Verdict:** **ACCEPT WITH ACTIONS** — continue family migration (Phase 6H Checkbox next) plus **narrow parallel fixes** (phone wrapper strip, Text RTE block-level normalizer extension). **Not** a global rewrite.

---

## 2. Audit-only statement

This phase modified **no** production code and **no** tests. Findings are from static analysis of serializer, embed, canvas, trait, and presentation modules plus existing test contracts.

---

## 3. Complete CSS-writer taxonomy

| Writer category | Production symbols found (representative) | Count | Export-facing? |
|---|---|---:|---|
| **INLINE HTML WRITER** | `serializeElements` (`styleAttr(wrapperStyle)`), `serializeLayout` (form `styleAttr`), block `*/serialize.ts` (`styleAttr`), `cssUtils.styleAttr` / `openTag`, `inputFieldMarkup.floatingFieldWrapOpen`, `serializeFieldHelpers.renderFieldLabel`, `serializeTeaser`, `serializeCross`, `serializeFloatingImage` | 28 | **YES** |
| **REACT CANVAS STYLE** | `ElementStyles` (`build*CanvasStyle`, `resolve*CssRules`, `standardFieldRulesToCssProperties`), `layoutStyles` (`getSelectionContainerStyle`, `getBlockLayoutStyle`), `ButtonCanvas` hover handlers, `FormCanvas` preview `dangerouslySetInnerHTML` for `formStyle.customCss`, block `*Canvas.tsx` `style={{}}` | 24 | Canvas-only (not in saved HTML) |
| **RTE CONTENT WRITER** | GrapesJS RTE → `properties.content`, `sanitizeRichTextHtml`, `applyRichTextLinkStyles`, `normalizeTextRichContentHtml`, `textRichContentPersistence.normalizeTextBlockForPersistence` | 6 | **YES** (via `htmlByStep` content) |
| **TRAIT STYLE WRITER** | Property panels `handleStyleChange` / `updateStyle` → `element.properties.style` / `formStyle`; no direct export DOM write | 12+ families | Model only at export boundary |
| **RESPONSIVE CSS WRITER** | `responsiveDeviceCss.ts` (`buildBlockTraitResponsiveCss`, `buildPhoneInputCountrySelectResponsiveCss`, `buildProductHorizontalResponsiveCss`, `buildProductGridResponsiveCss`, `buildBlockDeviceVisibilityCss`, `resolveFormSurfaceMobileRules`), `compile*PresentationRenderCss` mobile chunks via `wrapArtifactMobileRules` | 11 | **YES** (embedded + `renderCss`) |
| **EMBEDDED STYLE WRITER** | `serializeLayout` (`buildEmbeddedStepResponsiveCss`, `buildRichTextLinkExportCss`, `sanitizeCustomCss` → `<style>`), `serializeTeaser` device CSS | 7 | **YES** |
| **EXPORT SHEET** | `floating-label.export.css`, `embed-responsive.export.css`, `radio.export.css`, `popup-entrance.export.css` via `styles/sheets/index.ts` | 4 | **YES** |
| **RENDERCSS COMPILER** | `compilePopupRenderCss` + `compileFormSurfaceRenderCss`, `compileOuterLayoutRenderCss`, `compileColumnsContainerRenderCss`, `compileColumnPresentationRenderCss`, `compileButtonPresentationRenderCss`, `compileTextPresentationRenderCss`, `compileStandardFieldPresentationRenderCss`, `compileTextareaPresentationRenderCss`, `compileDropdownPresentationRenderCss`, `compilePhoneInputPresentationRenderCss`, `compileRadioPresentationRenderCss`, `compilePositionedDecorativeRenderCss`, `compileTeaserPresentationRenderCss`, `compileGamificationRenderCss` | 14 | **YES** |
| **RUNTIME STYLE MUTATOR** | `embed/runtime.ts` (`cssText` shell hosts, `display`), `embed/teaserLayout.ts` (`setProperty` offset vars), `embed/timerFit.ts` (`fontSize`, `gap`), `embed/spinner.ts` (`setProperty`, `display`), `embed/spinCoupon.ts`, `mountRenderArtifactCss` | 12 | Storefront/preview only |
| **CUSTOM CSS PATH** | `formStyle.customCss` → embedded `<style data-pbn-custom>`; per-element `style.customCss` in trait panels (model persisted, **not serialized**) | 2 | Form-level **YES**; block-level **NO** |
| **UNKNOWN** | — | 0 | — |

**Totals:** ~92 production CSS-capable symbols; ~58 export-facing; **0** export-facing UNKNOWN.

---

## 4. Inline-style writer inventory (saved HTML paths)

| File | Symbol | Block/family | DOM target | Properties (examples) | Source | Ownership | Decision |
|---|---|---|---|---|---|---|---|
| `serializeElements.ts` | `serializeElement` | All wrapped blocks | `[data-adflipr-block-id]` wrapper | `flexShrink`, margin*, width* (unstripped for phone, unmigrated) | STRUCTURAL + MERCHANT TRAIT (unmigrated) | REUSABLE STRUCTURE / MERCHANT PRESENTATION | MUST MIGRATE (phone strip); VALID STRUCTURAL (`flexShrink`) |
| `button/serialize.ts` | `buildButtonAlignContainerStyle` | button | align container `div` | `textAlign`, `width` | STRUCTURAL CONSTANT | REUSABLE STRUCTURE | VALID INLINE |
| `button/serialize.ts` | `serializeButton` | button | `<button>` | none (merchant in renderCss) | — | — | CLEAN |
| `text/serialize.ts` | `serializeText` | text | inner content `div` + RTE nodes | RTE `color`, `font-size`, etc. | RTE CONTENT | CONTENT-LOCAL RTE | VALID INLINE / NEEDS SEPARATE AUDIT (block-level) |
| `text-input`…`date` serializers | structure-only | standard fields | shells, inputs | none merchant | STRUCTURAL | REUSABLE STRUCTURE | CLEAN |
| `phone-input/serialize.ts` | `serializePhoneInput` | phone-input | shells, tel, select | none on painted controls | STRUCTURAL | REUSABLE STRUCTURE | CLEAN (inner); wrapper gap in `serializeElements` |
| `radio/serialize.ts` | `serializeRadioGroup` | radio | group, labels, inputs | none merchant inline | STRUCTURAL / NATIVE | NATIVE CONTROL MECHANIC | CLEAN (post-6G) |
| `check-box/serialize.ts` | `serializeCheckboxGroup` | check-box | group, labels, inputs | `margin`, `padding`, `font-size`, `accent-color` | MERCHANT TRAIT | MERCHANT PRESENTATION | MUST MIGRATE (6H) |
| `divider/serialize.ts` | `serializeDivider` | divider | outer + line | colors, width, height, margin | MERCHANT TRAIT | MERCHANT PRESENTATION | MUST MIGRATE |
| `timer/serialize.ts` | `serializeTimer` | timer | units, labels, container | typography, colors, gap | MERCHANT TRAIT | MERCHANT PRESENTATION | MUST MIGRATE |
| `image/serialize.ts` | `serializeImage` | image | container, `img` | width, radius, object-fit | MERCHANT TRAIT | MERCHANT PRESENTATION | MUST MIGRATE |
| `video/serialize.ts` | `serializeVideo` | video | container, iframe/video | dimensions, radius | MERCHANT TRAIT | MERCHANT PRESENTATION | MUST MIGRATE |
| `product/serialize.ts` | `serializeProduct` | product | card, prices, CTA | colors, typography, layout | MERCHANT TRAIT | MERCHANT PRESENTATION | MUST MIGRATE |
| `coupon/serialize.ts` | `serializeCoupon` | coupon | code, copy btn | colors, typography | MERCHANT TRAIT | MERCHANT PRESENTATION | MUST MIGRATE |
| `feedback/serialize.ts` | `serializeFeedback` | feedback | labels, options | colors, typography; `display:none` on inputs | MERCHANT TRAIT + NATIVE | MERCHANT + NATIVE MECHANIC | MUST MIGRATE |
| `social/serialize.ts` | `serializeSocial` | social | links, icons | size, gap, colors | MERCHANT TRAIT | MERCHANT PRESENTATION | MUST MIGRATE |
| `spinner/serialize.ts` | `serializeSpinner` | spinner | canvas host, result overlay | layout constants | STRUCTURAL + BEHAVIOR | RUNTIME BEHAVIOR | VALID NON-RENDERCSS CSS |
| `cross/serialize.ts` | `serializeCross` | cross | `<button>` | none | — | — | CLEAN |
| `floating-image/serialize.ts` | `serializeFloatingImage` | floating-image | shell | `pointerEvents` only | BEHAVIOR | RUNTIME BEHAVIOR | VALID INLINE |
| `serializeLayout.ts` | form attrs | form | `<form>` | `width`, `margin`, `boxSizing` | STRUCTURAL CONSTANT | REUSABLE STRUCTURE | VALID INLINE |
| Trait panels | `style.customCss` | many | — | — | CUSTOM CSS | — | DEAD — DELETE LATER (not exported) |

---

## 5. Trait-to-export persistence matrix

| Trait/property family | Model source | Export persistence target | Desktop owner | Mobile owner | Centralized? |
|---|---|---|---|---|---|
| margin/padding (migrated blocks) | `style.marginDesktop` / `paddingDesktop` | **renderCss** | renderCss base | renderCss `@media` | **YES** |
| margin/padding (unmigrated blocks) | same | **inline** on inner/block + **embedded `@media`** on wrapper | inline serialize helpers | `buildBlockTraitResponsiveCss` | **NO** |
| width/position (migrated) | `elementWidth`, `elementPosition` | **renderCss** wrapper rules | renderCss | renderCss `@media` | **YES** (except phone wrapper strip gap) |
| typography (migrated text) | `textColor`, `fontSize`, … | **renderCss** painted + wrapper | renderCss | renderCss `@media` | **YES** (traits); RTE may duplicate on content nodes |
| typography (unmigrated) | trait fields | **inline** + generic `@media` | inline | embedded responsive | **NO** |
| background/border/radius/shadow (migrated fields) | trait fields | **renderCss** | renderCss | renderCss `@media` | **YES** |
| radio native colors | `fillColor`, `outlineColor` | **renderCss** CSS variables + `radio.export.css` | vars + sheet | vars + sheet | **YES** |
| checkbox native | `fillColor`, `accent-color` | **inline** `accent-color` only | inline input | generic `@media` on wrapper (font-size) | **NO** |
| visibility hide flags | `hideOnDesktop/Mobile` | **embedded** device CSS `display:none !important` | embedded | embedded | **YES** (intentional `!important`) |
| form surface | `formStyle.*` | **renderCss** | renderCss | renderCss `@media` | **YES** |
| column/section traits | column `style` | **renderCss** | renderCss | renderCss `@media` | **YES** |
| custom CSS (form) | `formStyle.customCss` | **embedded `<style>`** | custom | custom | Valid bypass (merchant-authored) |

### YES cases — trait desktop inline + mobile stylesheet split

| Family | Property | Desktop writer | Mobile writer |
|---|---|---|---|
| check-box | margin, padding, font-size, label colors | `check-box/serialize.ts` inline on group/labels | `buildBlockTraitResponsiveCss` on wrapper |
| divider | margin, padding, line presentation | `divider/serialize.ts` | generic responsive wrapper |
| timer | padding, font-size, colors | `timer/serialize.ts` | generic responsive wrapper |
| image, video | margin, padding, radius | respective `serialize.ts` | generic responsive wrapper |
| product, coupon, feedback, social | margin, padding, font-size, colors | respective `serialize.ts` | generic responsive wrapper |
| spinner | padding, margin (traits) | `spinner/serialize.ts` + wrapper | generic responsive wrapper |
| **phone-input** | **margin, width on wrapper** | **`serializeElements` wrapper inline** | **`compilePhoneInputPresentationRenderCss` `@media`** |

**Migrated-family trait persistence:** model → `normalize*Presentation` → `compile*PresentationRenderCss` → `renderCss` (traits do not write export HTML directly).

**Global trait bypass:** **NO** — no trait handler writes `style=""` at save time.

---

## 6. RTE persistence matrix

| RTE property | Saved form | DOM target | renderCss override safe? | Risk |
|---|---|---|---|---|
| `color` on `<span>` (selection) | inline in `properties.content` | content span | N/A (content-local) | Low — valid |
| `font-size` on `<span>` (selection) | inline | content span | Inherited from wrapper may be masked | Low–medium |
| `text-align` on `<div>`/`<p>` (block) | stripped by `normalizeTextRichContentHtml` | block tag | **Yes** when stripped | Low (if normalizer runs) |
| `font-size` on `<p>` (whole block via RTE) | **preserved** inline | block tag | **No** — defeats wrapper `font-size` `@media` via specificity | **Medium (collision)** |
| `color` on `<a>` | stripped (link color via renderCss descendant + `buildRichTextLinkExportCss`) | anchor | Yes | Low |
| semantic tags (`strong`, `em`) | tag only | content | Yes | Low |
| Button RTE label | sanitized HTML in `<button>` | button content | Button chrome in renderCss on `<button>` | Low (content vs chrome separation) |

**Block-level presentation leaking:** whole-paragraph `font-size` / `color` on block tags in Text `properties.content`.  
**Responsive collision cases:** Text `fontSize` / `fontSizeMobile` in renderCss vs RTE block-level `font-size` inline on child nodes.  
**Ambiguous (UX policy later):** whether whole-block RTE font-size should migrate to traits automatically.

---

## 7. Inline vs renderCss collision scan

| Family | Property | Inline owner | renderCss owner | Same-property collision? | Verdict |
|---|---|---|---|---|---|
| Button | padding, colors, typography | none on `<button>` | `compileButtonPresentationRenderCss` on wrapper + button | No | **CLEAN** |
| Text | text-align | stripped from content | wrapper renderCss | No (when normalized) | **CLEAN** |
| Text | font-size | possible RTE on `<p>` | wrapper/painted renderCss | Inherited defeat | **POTENTIAL COLLISION** |
| Cross / Floating Image | position, size, opacity | none merchant | `compilePositionedDecorativeRenderCss` | No | **CLEAN** |
| Teaser | chip presentation | none merchant inline | `compileTeaserPresentationRenderCss` | No | **CLEAN** |
| Text Input, Email, Date | field chrome | none | `compileStandardFieldPresentationRenderCss` | No | **CLEAN** |
| Textarea | height, padding, etc. | none | `compileTextareaPresentationRenderCss` | No | **CLEAN** |
| Dropdown | select chrome | none | `compileDropdownPresentationRenderCss` | No | **CLEAN** |
| Phone Input | **margin, width** | **wrapper `style=""`** | **wrapper selector in renderCss** | **Yes** | **COLLISION FOUND** |
| Radio | group/label chrome | none (vars + sheet) | `compileRadioPresentationRenderCss` + `radio.export.css` | No | **CLEAN** |

---

## 8. Mobile override safety scan

### Summary counts

| Category | Count |
|---|---:|
| Total responsive merchant property paths audited | 48 |
| Safe centralized | 32 |
| Still hybrid (desktop inline + mobile sheet) | 11 |
| Split-target (desktop/mobile different DOM nodes) | 3 |
| Inline override risk (migrated) | 2 |
| Unknown | 0 |

### Unsafe / hybrid highlights

| Property pair | Desktop persistence | Mobile persistence | Desktop DOM | Mobile DOM | Safe? |
|---|---|---|---|---|---|
| Block margin/padding (unmigrated) | inline inner | `@media` wrapper | inner/group | wrapper | **NO** |
| Phone wrapper margin | inline wrapper | renderCss `@media` | wrapper | wrapper | **NO** |
| Text font-size | RTE inline on content (optional) | renderCss `@media` | content child | wrapper | **PARTIAL** |
| Phone country select width | structural sheet desktop | `@media !important` | select | select | **YES** (structural) |
| Product horizontal layout | inline card | `@media !important` structural | card slots | card slots | **YES** (layout mechanic) |

---

## 9. Responsive property-pair inventory

Device-paired fields consumed in presentation compilers or generic responsive (non-exhaustive property list):

- `marginDesktop` / `marginMobile`
- `paddingDesktop` / `paddingMobile`
- `fontSize` / `fontSizeMobile` (and input font variants)
- `cornerRadius` desktop/mobile (`resolveCornerRadiusCss`)
- `boxShadow` desktop/mobile (`resolveDeviceBoxShadowCss`)
- `elementWidth` / width traits with device-specific layout
- `columnGapDesktop` / `columnGapMobile` (columns renderCss)
- `productGridColumnsDesktop` / `Mobile`
- `teaserHideOnDesktop` / `teaserHideOnMobile`
- `hideOnDesktop` / `hideOnMobile` (blocks, columns, form surface)

---

## 10. Generic responsive generator — current consumers

**Function:** `buildBlockTraitResponsiveCss()` in `responsiveDeviceCss.ts`  
**Skipped types (`MIGRATED_BLOCK_TRAIT_TYPES`):** `cross`, `floating-image`, `button`, `text`, `text-input`, `email-input`, `textarea`, `dropdown`, `phone-input`, `date`, `radio`

### Remaining consumers (10 families)

| Family | Properties emitted | Selector target | Desktop source | Classification |
|---|---|---|---|---|
| check-box | padding, margin, radius, font-size | wrapper `[data-adflipr-block-id]` | inline group/labels | **INLINE OVERRIDE RISK** |
| divider | padding, margin, radius, font-size | wrapper | inline outer/inner | **INLINE OVERRIDE RISK** |
| timer | padding, margin, font-size | wrapper | inline units | **INLINE OVERRIDE RISK** |
| spinner | padding, margin, font-size | wrapper | inline + constants | **INLINE OVERRIDE RISK** |
| image | padding, margin, radius, font-size | wrapper | inline img/container | **INLINE OVERRIDE RISK** |
| video | same | wrapper | inline | **INLINE OVERRIDE RISK** |
| product | same | wrapper | inline card | **KNOWN SPLIT-TARGET** (+ product layout CSS) |
| coupon | same | wrapper | inline | **INLINE OVERRIDE RISK** |
| feedback | same | wrapper | inline | **INLINE OVERRIDE RISK** |
| social | same | wrapper | inline links | **INLINE OVERRIDE RISK** |

**Generic responsive consumer count:** **10**  
**Known unsafe hybrid architectures:** **10** (all remaining consumers)

**Answer:** Ten block families still depend on generic merchant responsive CSS; **all ten** are known unsafe hybrid architectures until migrated.

---

## 11. Embedded style generator inventory

| Generator/sheet | Concern | Merchant-specific? | Responsive? | Correct owner? | Classification |
|---|---|---|---|---|---|
| `POPUP_FLOATING_LABEL_EXPORT_CSS` | floating label mechanics, phone row structure | No (vars only) | Partial (focus) | Yes | **VALID SHARED STRUCTURE** |
| `resolveEmbedResponsiveExportCss` | overflow guards, validation error chrome | No | Some | Yes | **VALID SHARED STRUCTURE** |
| `POPUP_ENTRANCE_EXPORT_CSS` | entrance animation | No | No | Yes | **VALID RUNTIME BEHAVIOR** |
| `POPUP_RADIO_EXPORT_CSS` | native radio appearance | Vars (`--pbn-radio-*`) | No | Yes | **VALID NATIVE MECHANIC** |
| `buildRichTextLinkExportCss` | link color decoration | Theme link color | No | Yes | **VALID SHARED STRUCTURE** |
| `buildEmbeddedStepResponsiveCss` | block traits, visibility, product, phone select | Yes for unmigrated traits | Yes | Partial | **MIGRATION DEBT** |
| `buildTeaserExportDeviceCss` | teaser hide flags | No | Yes | Yes | **VALID RUNTIME BEHAVIOR** |
| `formStyle.customCss` | merchant custom | Yes | User-defined | Yes (explicit) | **VALID CUSTOM CSS** |

**Embedded merchant-presentation debt:** `buildBlockTraitResponsiveCss` output for 10 unmigrated families.  
**Obsolete:** `responsiveCssBaselineCases` button padding risk case (stale post–Phase 5C).

---

## 12. `.export.css` inventory

| File | Importer | Consumer family | Concern | Merchant values? | Classification |
|---|---|---|---|---|---|
| `floating-label.export.css` | `styles/sheets/index.ts` | standard fields, phone, dropdown, textarea | float label mechanics | CSS vars only (`--pbn-floating-label-bg`, focus) | **STRUCTURE** |
| `embed-responsive.export.css` | same | embed overflow, validation | overflow, error border | No fixed merchant palette | **STRUCTURE + HOSTILE-THEME DEFENSE** |
| `radio.export.css` | same | radio | native control + var-driven border/bg | **Vars only** | **NATIVE CONTROL** |
| `popup-entrance.export.css` | same | form entrance | keyframes | No | **ANIMATION** |

**Merchant-presentation violations:** **0** — no sheet encodes per-popup merchant literals as fixed rules; radio uses explicit artifact variables.

**G1 raw-source contract verified:** **YES** — `styles/sheets/index.ts` imports raw `.css` strings; tests assert no webpack JS stub in saved HTML.

---

## 13. Runtime style mutation inventory

| Runtime mutation | Target | Property | Why | Classification |
|---|---|---|---|---|
| `runtime.ts` `cssText` | overlay, banner, flyout hosts | position, display, z-index | shell geometry | **VALID RUNTIME GEOMETRY** |
| `runtime.ts` `display` | teaser, overlay hosts | visibility toggles | open/close | **VALID RUNTIME BEHAVIOR** |
| `teaserLayout.ts` `setProperty` | teaser host | `--adflipr-teaser-offset-*` | dynamic offset | **VALID RUNTIME GEOMETRY** |
| `timerFit.ts` | timer units | `fontSize`, `gap` | fit-to-width | **VALID RUNTIME BEHAVIOR** |
| `spinner.ts` | canvas, hints, results | `display`, `pointerEvents`, theme keys | gamification UX | **VALID RUNTIME BEHAVIOR** |
| `mountRenderArtifactCss` | `document.head` `<style>` | full `renderCss` text | artifact mount | **VALID RUNTIME BEHAVIOR** |
| `ButtonCanvas` hover | button element | `backgroundColor`, `color` | canvas preview only | **CANVAS-ONLY** |

**Merchant-presentation runtime leaks:** **0** on storefront export paths.

---

## 14. `!important` ownership inventory

| Property | Selector / source | Classification |
|---|---|---|
| `display:none` | `buildBlockDeviceVisibilityCss`, teaser hide | **VISIBILITY MECHANIC** |
| `max-width` (phone country) | `buildPhoneInputCountrySelectResponsiveCss` | **NATIVE CONTROL DEFENSE** (structural) |
| product layout stack | `buildProductHorizontalResponsiveCss` | **VALID RUNTIME BEHAVIOR** (layout) |
| product grid columns | `buildProductGridResponsiveCss` | **VALID RUNTIME BEHAVIOR** |
| validation error border | `embed-responsive.export.css` | **HOSTILE STOREFRONT DEFENSE** |
| radio appearance/border/bg | `radio.export.css` | **NATIVE CONTROL DEFENSE** (vars) |
| rich text link color | `buildRichTextLinkExportCss` | **HOSTILE STOREFRONT DEFENSE** |
| builder canvas RTE/toolbar | `popup-builder.css`, `popup-builder-input.css` | Canvas-only (not export) |

**Internal inline-override repairs (architecture defect):** **0** in export path — no `!important` exists solely to beat our own migrated merchant inline CSS. Unmigrated families fail mobile override **without** `!important` (PCL-008), which is the root defect.

**Unknown `!important` ownership:** **NONE** (export-facing).

---

## 15. Saved artifact reality-check evidence

Representative artifacts inspected via serializer contracts (`serializePopupExport`) and family tests:

| Artifact | Merchant inline in HTML | renderCss | Embedded merchant CSS | Responsive safe? |
|---|---|---|---|---|
| Button (migrated) | structural `text-align` only | yes — padding, colors | shared sheets only | **Yes** |
| Text + RTE span color | content-local `color` on span | yes — block chrome | link CSS | **Yes** (span); **partial** if block `font-size` in RTE |
| Text input (migrated) | wrapper `flexShrink` only | yes — outline, value | floating-label sheet | **Yes** |
| Phone input | inner clean; **wrapper margin may persist** | yes | phone select `@media` | **No** (wrapper collision) |
| Radio (post-6G) | structure only | yes + radio vars | radio sheet | **Yes** |
| Checkbox (unmigrated) | group/label/accent inline | partial (label via renderFieldLabel only) | generic `@media` wrapper | **No** |
| Divider (unmigrated) | full merchant inline | no block renderCss | generic `@media` | **No** |

**Proof layer conclusion:** Saved artifacts match compiler intent for **most migrated families**; **phone wrapper** and **all unmigrated families** violate mobile-safe centralized contract in real HTML.

---

## 16. Production persistence boundary

| Persistence field | Can contain CSS? | CSS type | Canonical? |
|---|---|---|---|
| `renderCss` | Yes | Artifact-scoped merchant presentation + layout owners | **YES** |
| `htmlByStep` / `htmlContent` | Yes | Structure, RTE content inline, unmigrated merchant inline, embedded `<style>` | Partial (structure + content + embedded shared) |
| `artifactScopeId` | No | — | Scope key |
| `formStyle.customCss` | Yes (embedded in HTML) | Merchant-authored custom | Explicit non-canonical bypass |
| `properties.content` (Text/Button RTE) | Yes | Content-local inline | Valid for selections |
| Element `style.customCss` | Stored in model | **Not exported** | Dead path |

**Is `renderCss` the only centralized merchant presentation field?** **NO**

**Other merchant presentation persistence paths:**
1. Unmigrated block **inline `style=""`** in `htmlByStep`
2. **`buildBlockTraitResponsiveCss`** embedded in `htmlByStep` `<style>`
3. **`formStyle.customCss`** embedded `<style>`
4. **RTE block-level** inline in `properties.content` → HTML
5. **Product/timer** specialized embedded responsive rules

---

## 17. Block-family centralization scorecard

| Family | Merchant inline | Generic responsive | renderCss | Export sheet | Mobile safe | Status |
|---|---|---|---|---|---|---|
| Form surface / layout / columns | No | visibility only | Yes | shared | Yes | **CENTRALIZED** |
| Button | structural only | No | Yes | — | Yes | **CENTRALIZED** |
| Text | RTE content only | No | Yes | link CSS | Partial | **CENTRALIZED WITH VALID NON-RENDERCSS CSS** |
| Cross / Floating image | No | No | Yes | — | Yes | **CENTRALIZED** |
| Teaser | No | hide only | Yes | entrance | Yes | **CENTRALIZED** |
| Text/Email/Date fields | No | No | Yes | floating-label | Yes | **CENTRALIZED** |
| Textarea / Dropdown | No | No | Yes | floating-label | Yes | **CENTRALIZED** |
| Phone input | wrapper gap | phone select only | Yes | floating-label | **No** | **COLLISION RISK** |
| Radio | No | No | Yes | radio.export.css | Yes | **CENTRALIZED WITH VALID NON-RENDERCSS CSS** |
| Checkbox | Yes (group/labels) | Yes | No | — | No | **UNMIGRATED** |
| Divider, Timer, Image, Video | Yes | Yes | No | embed | No | **HYBRID** |
| Product, Coupon, Feedback, Social | Yes | Yes | No | embed/product | No | **HYBRID** |
| Spinner | structural + traits | Yes | gamification only | embed | No | **HYBRID** |

### Totals

| Status | Count |
|---|---:|
| Total production content families | 22 |
| Centralized | 11 |
| Centralized with valid non-renderCss CSS | 2 |
| Hybrid | 8 |
| Unmigrated | 1 (checkbox — others hybrid-unmigrated) |
| Collision risk | 1 (phone) |
| Unknown | 0 |

---

## 18. CSS-writer ownership scorecard (canonical debt)

| Writer | Output channel | Ownership | Centralization risk | Action |
|---|---|---|---|---|
| `compilePopupRenderCss` + family compilers | `renderCss` | Merchant presentation | Low | **KEEP** |
| `serializeElements` wrapper | inline HTML | Structure + leak for phone | **High** (phone) | **GLOBAL FIX REQUIRED** (add phone strip) |
| `buildBlockTraitResponsiveCss` | embedded `@media` | Unmigrated merchant mobile | **High** | **MIGRATE WITH FAMILY** |
| Block `*/serialize.ts` (unmigrated) | inline HTML | Merchant desktop | **High** | **MIGRATE WITH FAMILY** |
| `normalizeTextRichContentHtml` | content HTML | RTE normalization | Medium | **SEPARATE AUDIT REQUIRED** (extend) |
| `formStyle.customCss` | embedded style | Merchant custom | Low (explicit) | **KEEP** |
| Element `style.customCss` | model only | Dead export | Low | **DELETE LATER** |
| `radio.export.css` | embedded sheet | Native mechanic | Low | **KEEP** |
| `floating-label.export.css` | embedded sheet | Structure | Low | **KEEP** |
| `ButtonCanvas` hover | canvas DOM | Preview only | None | **NO ACTION** |
| `embed/runtime.ts` shell | runtime inline | Geometry/behavior | Low | **KEEP** |

---

## 19. P0/P1/P2/P3 ranked debt list

| Priority | Issue | Family/global | Why | Recommended phase |
|---|---|---|---|---|
| **P0** | Desktop inline + mobile `@media` on same property (PCL-008) | check-box, divider, timer, image, video, product, coupon, feedback, social, spinner | Mobile rules cannot override desktop inline | **6H–6N** family migrations |
| **P0** | Phone wrapper margin/width inline + renderCss mobile margin | phone-input | Same DOM node, same properties | **G2E** narrow fix |
| **P1** | Text RTE block-level `font-size` defeats responsive renderCss | text | Inherited cascade conflict | **G2B extend** |
| **P1** | Checkbox split-target (group inline vs wrapper `@media`) | check-box | Same pattern as pre-6G radio | **6H** |
| **P1** | Stale `responsiveCssBaselineCases` button padding risk | global tests | False signal post-5C | **G2F** test hygiene |
| **P2** | Generic responsive consumer count (10 families) | global | Shrinks only with migration | Per-family phases |
| **P2** | Element `style.customCss` persisted but not exported | global | Confusing trait surface | Cleanup audit |
| **P3** | Structural inline (`flexShrink`, button align container) | multiple | Valid non-renderCss | Document only |
| **P3** | Phone flag img without URL when iso2 missing | phone-input | Empty `img` in export | **6E follow-up** |

**Highest-risk issue:** Unmigrated **PCL-008 hybrid** architecture across 10 families + **phone wrapper strip omission**.

---

## 20. Global-fix decision

**Recommended strategy: C** — Continue family-by-family migration **plus one narrow global persistence fix**.

**Reason:** No cross-family writer injects merchant inline CSS *after* compilation. The systemic issue is **per-family serializer inline desktop** paired with **generic mobile `@media`**, solvable by continuing Phase 6 migrations. The **phone wrapper strip** is a **one-line-class parity fix** in `serializeElements.ts`, not a architecture redesign.

**Cross-family writer injecting merchant inline after compilation:** **NO**  
**Cross-family writer causing mobile override failure:** **YES** — `buildBlockTraitResponsiveCss` affects all unmigrated families (by design, until migrated).

---

## 21. Trait centralization verdict

**Are Trait-driven merchant styles centralized today?** **PARTIAL**

| Segment | Verdict |
|---|---|
| Migrated-family traits | **Centralized** — trait → model → `compile*PresentationRenderCss` |
| Unmigrated-family traits | **Not centralized** — trait → model → inline serialize + generic `@media` |
| Global trait writer bypasses | **None** |

---

## 22. RTE centralization verdict

**Does RTE currently prevent CSS centralization?** **PARTIAL**

| Segment | Detail |
|---|---|
| Valid content-local inline formatting | Selection-level color, bold, semantic tags — **valid** |
| Block-level presentation leaking into RTE HTML | Whole-block `font-size` / `color` on block tags — **yes** |
| Responsive collision cases | Text `fontSizeMobile` vs RTE block `font-size` inline |
| Ambiguous cases | Future UX policy for block vs selection typography |

---

## 23. Mobile responsiveness verdict

**Can current mobile renderCss safely override desktop presentation?** **PARTIAL** (equivalently: **YES FOR MIGRATED FAMILIES ONLY** with **phone** and **text RTE** exceptions)

| Segment | Families |
|---|---|
| **Safe** | button, cross, floating-image, teaser, text-input, email-input, textarea, dropdown, date, radio, columns, form surface |
| **Unsafe** | check-box, divider, timer, spinner, image, video, product, coupon, feedback, social; **phone-input** (wrapper) |
| **Unknown** | none |

---

## 24. Required future regression tests

### Unit
- `serializeElements` parity: every `MIGRATED_BLOCK_TRAIT_TYPES` member strips wrapper margin/width (including **phone-input**).
- Negative: no merchant property in `style=""` on painted selectors per migrated family.

### Serializer / artifact contract
- Saved artifact channel contract: merchant keys appear in `renderCss` selector, not inline HTML.
- `buildBlockTraitResponsiveCss` consumer count monotonically decreases as families migrate.

### Chromium computed-style
- Extend Phase 0 harness: migrated families **pass** mobile override; unmigrated **documented fail** until migrated.
- Text: block RTE `font-size` vs `fontSizeMobile` computed-style case.

### Production build
- G1 gate: raw `.export.css` in saved HTML (existing).
- No `!important` in `renderCss` (existing family tests).

---

## 25. Existing unrelated debt status

| Debt | Status |
|---|---|
| `popupEmbedRuntime.test.ts` failures (4) | **STILL PRESENT** (module resolution / environment; not reproduced in this audit run) |
| `popupProductionCssRteOwnership.audit.test.ts` failures (2) | **STILL PRESENT** (Babel parse error on import path in isolated `npx jest` run; test intent valid) |
| Phone flag-without-URL | **STILL PRESENT** — `serializePhoneInput` emits empty `img` when `countryFlagUrl` undefined |
| New unrelated failures | **NONE** encountered |

---

## 26. Exact recommended next actions

1. **G2E (narrow):** Add `phone-input` to wrapper margin/width stripping in `serializeElements.ts` (parity with text-input/email/date/textarea/dropdown/radio); add serializer negative test for wrapper margin.
2. **Phase 6H:** Implement Checkbox merchant presentation `renderCss` per H3 audit (`checkboxPresentation.ts` + `checkbox.export.css`).
3. **G2B extend:** Expand `normalizeTextRichContentHtml` to strip block-level `font-size` / `color` when they duplicate trait-owned presentation (or sync into traits).
4. **G2F:** Update `responsiveCssBaselineCases` button padding case to reflect post-5C renderCss reality.
5. Continue unmigrated family queue: divider → timer → image → video → product → coupon → feedback → social → spinner (order by product priority).

---

## 27. Recommended next phase

**Phase G2E** — Phone wrapper strip + serializer parity gate (narrow global persistence fix)  
**then Phase 6H** — Checkbox merchant presentation renderCss

---

# Required final answer format (100 items)

1. Audit-only phase respected: **YES**
2. Production code changed: **NO**
3. Tests changed: **NO**
4. Total production CSS writers found: **92**
5. Export-facing CSS writers: **58**
6. Inline HTML writers: **28**
7. RTE content writers: **6**
8. Trait persistence writers: **12** (model paths; not direct HTML for migrated)
9. Responsive CSS writers: **11**
10. Embedded style writers: **7**
11. Export sheets: **4**
12. renderCss compilers: **14**
13. Runtime style mutators: **12**
14. Unknown export-facing writers: **NONE**
15. Total saved-artifact inline-style writers: **22**
16. Merchant-presentation inline writers: **11**
17. Valid content-local RTE inline writers: **1** (content pipeline)
18. Valid structural inline writers: **6**
19. Native-control inline writers: **3**
20. Runtime-behavior inline writers: **4**
21. Dead inline writers: **1** (element `style.customCss` not exported)
22. Trait-driven merchant styles centralized: **PARTIAL**
23. Migrated-family Trait persistence: **model → renderCss compilers**
24. Unmigrated-family Trait persistence: **model → inline serialize + generic `@media`**
25. Global Trait persistence bypass found: **NO**
26. Global Trait bypass details: **—**
27. RTE prevents CSS centralization: **PARTIAL**
28. Valid RTE content-local inline formatting: **selection color/semantic tags; sanitized links**
29. Block-level presentation leaking into RTE HTML: **block `font-size`/`color` on DIV/P**
30. RTE responsive collision cases: **Text `fontSize`/`fontSizeMobile` vs RTE block `font-size`**
31. RTE ambiguous cases: **whole-block RTE typography vs trait ownership policy**
32. Migrated families audited: **12**
33. Migrated families clean: **10**
34. Migrated families with inline/renderCss collision: **1** (phone-input wrapper)
35. Collision details: **wrapper `margin`/`width` inline + `compilePhoneInputPresentationRenderCss` same selector**
36. Responsive merchant property paths audited: **48**
37. Safe centralized responsive paths: **32**
38. Hybrid responsive paths: **11**
39. Split-target paths: **3**
40. Inline override risks: **2** (phone wrapper + text RTE inherited)
41. Unknown responsive paths: **0**
42. Current generic responsive consumer families: **check-box, divider, timer, spinner, image, video, product, coupon, feedback, social**
43. Generic responsive consumer count: **10**
44. Known unsafe generic responsive consumers: **10**
45. Embedded CSS generators audited: **8**
46. Valid shared-structure generators: **3**
47. Valid native-mechanic generators: **1**
48. Valid runtime/behavior generators: **3**
49. Embedded merchant-presentation debt: **1** (`buildBlockTraitResponsiveCss` for unmigrated)
50. Obsolete embedded generators: **0** (stale test cases only)
51. `.export.css` files audited: **4**
52. `.export.css` merchant-presentation violations: **0**
53. G1 raw-source contract verified: **YES**
54. Runtime style mutations audited: **12**
55. Merchant-presentation runtime leaks: **0**
56. Internal cascade-repair mutations: **0**
57. `!important` declarations audited: **~25** export-facing
58. Hostile-storefront defenses: **3**
59. Visibility mechanics: **4**
60. Native-control defenses: **6**
61. Internal inline-override repairs: **0**
62. Internal selector-conflict repairs: **0**
63. Unknown `!important` ownership: **NONE**
64. Representative saved artifacts inspected: **7**
65. Saved artifacts containing merchant inline CSS: **2** (checkbox, divider samples; phone wrapper)
66. Saved artifacts containing renderCss: **7/7**
67. Saved artifacts containing embedded merchant CSS: **3** (generic responsive / customCss paths)
68. Is renderCss the only centralized merchant presentation field: **NO**
69. Other merchant presentation persistence paths: **unmigrated inline HTML; embedded generic responsive; formStyle.customCss; RTE block-level inline**
70. Total production block families: **22**
71. Centralized families: **11**
72. Centralized with valid non-renderCss CSS: **2**
73. Hybrid families: **8**
74. Unmigrated families: **1** (checkbox primary; others hybrid-unmigrated)
75. Collision-risk families: **1** (phone-input)
76. Unknown families: **0**
77. P0 issues: **11** (10 unmigrated PCL-008 + phone wrapper)
78. P1 issues: **4**
79. P2 issues: **2**
80. P3 issues: **3**
81. Highest-risk issue: **PCL-008 hybrid desktop inline + mobile `@media` on unmigrated families**
82. Cross-family writer injecting merchant inline CSS after compilation: **NO**
83. Cross-family writer causing mobile override failure: **YES** (`buildBlockTraitResponsiveCss`)
84. Recommended strategy: **C**
85. Strategy reason: **Family migration continues; only narrow phone wrapper + RTE normalizer fixes needed globally**
86. Mobile renderCss override verdict: **PARTIAL**
87. Safe families: **button, text (mostly), cross, floating-image, teaser, text-input, email-input, textarea, dropdown, date, radio, layout/surface**
88. Unsafe families: **check-box, divider, timer, spinner, image, video, product, coupon, feedback, social, phone-input (wrapper)**
89. Unknown families: **none**
90. Required global fix before more family migrations: **NO**
91. Required narrow parallel fix: **G2E phone wrapper strip; G2B RTE block-level normalizer**
92. Required future architecture tests: **wrapper strip parity; artifact negative inline; computed-style mobile pass per migrated family; generic consumer count regression**
93. Existing embed failures status: **STILL PRESENT**
94. Existing Text/RTE audit failures status: **STILL PRESENT**
95. Phone flag-without-URL status: **STILL PRESENT**
96. Important UNKNOWN ownership remaining: **none export-facing**
97. Architecture converging: **PARTIAL**
98. Exact recommended next actions: **G2E phone strip → Phase 6H checkbox → G2B RTE extend → G2F baseline case update → continue unmigrated queue**
99. Recommended next phase: **G2E then Phase 6H**
100. Verdict: **ACCEPT WITH ACTIONS**
