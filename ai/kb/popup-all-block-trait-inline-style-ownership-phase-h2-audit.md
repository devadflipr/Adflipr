# Phase H2 — All-Block Trait, Inline Style, Content Ownership, and Export Parity Audit

**Date:** 2026-07-06  
**Mode:** READ-ONLY AUDIT — no production code or test changes  
**Scope:** Every supported popup block family, all presentation writers, export paths, renderCss compilers, embedded CSS consumers  
**References:** Phase 0 baseline, G1 export CSS contract, Phases 5C–6E migration reports, `popup-production-css-rte-ownership-audit.md`

---

## 1. Executive verdict

Cross-block inspection of current production code proves the renderCss migration is **real but incomplete**. Nine input-shell and decorative families are substantially migrated; **twelve content families** still emit merchant presentation through serializer inline `style=""` and/or generic `buildBlockTraitResponsiveCss`. **Date** is a critical outlier: it shares the migrated input-shell DOM and canvas resolvers but was **never added** to `compileStandardFieldPresentationRenderCss`. **Button** and **Cross** hover traits persist canonically and work on canvas via DOM mutation but **do not compile to renderCss**. **Text** correctly supports content-local RTE formatting with export dedupe. Structure/behavior separation remains sound; renderCss is **not** a dumping ground.

**Verdict: ACCEPT WITH CLEANUP**

---

## 2. Audit independence statement

This audit derived all conclusions from **current repository state** (July 2026):

- `registerBuilderBlocks.ts`, `registerBuilderBlockProperties.ts`, `blockRegistry.ts`
- All `blocks/*/serialize.ts` and `*Presentation.ts` compilers
- `compilePopupRenderCss.ts`, `responsiveDeviceCss.ts`, `styles/inline/*.ts`
- Property panels, canvas components, `ElementStyles.ts`, `ElementDefaults.ts`
- Existing tests: `responsiveCssBaseline.browser.test.ts`, `popupProductionCssRteOwnership.audit.test.ts`, `exportCssProductionBuildContract.test.ts`

No assumptions from prior phase reports were accepted without re-verification. No code was modified.

---

## 3. Complete block-family inventory (Part A)

**Canonical type union:** `BlockType` in `components/form-builder/types.ts` — **26 values**.

| Family | Default factory | Canvas | Trait UI | Serializer | Dedicated renderCss | Generic CSS consumer |
|--------|-----------------|--------|----------|------------|----------------------|----------------------|
| `text` | `ElementDefaults.getDefaultPropertiesForType` | `TextCanvas` via registry | `TextElementProperties` | `text/serialize.ts` | `textPresentation.ts` | Rich-text link CSS in step (theme) |
| `button` | `ElementDefaults` | `ButtonCanvas` | `ButtonProperties` | `button/serialize.ts` | `buttonPresentation.ts` | Align wrapper structural inline only |
| `divider` | `ElementDefaults` | `DividerCanvas` | `DividerProperties` | `divider/serialize.ts` | — | `buildBlockTraitResponsiveCss` + `blockStyles.ts` inline |
| `text-input` | `ElementDefaults.createInputDefaults` | `TextInputCanvas` | `TextInputProperties` | `serializeTextInputLike` | `standardFieldPresentation.ts` | `floating-label.export.css` (structure) |
| `email-input` | `ElementDefaults` | `EmailInputCanvas` | `EmailInputProperties` | `serializeTextInputLike` | `standardFieldPresentation.ts` | `floating-label.export.css` |
| `phone-input` | `ElementDefaults` | `PhoneInputCanvas` | `PhoneInputProperties` | `phone-input/serialize.ts` | `phoneInputPresentation.ts` | Country-select max-width in embedded |
| `textarea` | `ElementDefaults` | `TextareaCanvas` | `TextAreaProperties` | `textarea/serialize.ts` | `textareaPresentation.ts` | `floating-label.export.css` |
| `dropdown` | `ElementDefaults` | `DropdownCanvas` | `DropdownProperties` | `dropdown/serialize.ts` | `dropdownPresentation.ts` | — |
| `check-box` | `ElementDefaults` | `CheckboxCanvas` | `CheckBoxProperties` | `check-box/serialize.ts` | — | Generic trait CSS + `blockStyles.ts` inline |
| `radio` | `ElementDefaults` | `RadioCanvas` | `RadioProperties` | `radio/serialize.ts` | — | Generic trait CSS + inline + `radio.export.css` |
| `date` | `ElementDefaults` | `DateCanvas` | `DateProperties` | `date/serialize.ts` → `serializeTextInputLike` | **— (GAP)** | Generic trait CSS + `floating-label.export.css` |
| `timer` | `ElementDefaults` | `TimerCanvas` | `TimerProperties` | `timer/serialize.ts` | — | Generic trait CSS + heavy inline |
| `spinner` | `ElementDefaults` / gamification templates | `SpinnerCanvas` | `SpinnerProperties` | `spinner/serialize.ts` | `gamificationPresentation.ts` (shell only) | Generic trait CSS + `spinnerStyles.ts` inline |
| `image` | `ElementDefaults` | `ImageCanvas` | `ImageProperties` | `image/serialize.ts` | — | Generic trait CSS + `mediaAndFeedbackStyles.ts` inline |
| `video` | `ElementDefaults` | `VideoCanvas` | `VideoProperties` | `video/serialize.ts` | — | Generic trait CSS + inline |
| `product` | `ElementDefaults` | `ProductCanvas` | `ProductProperties` | `product/serialize.ts` | — | Generic + product-specific embedded + inline |
| `coupon` | `ElementDefaults` | `CouponCanvas` | `CouponProperties` | `coupon/serialize.ts` | — | Generic trait CSS + `commerceAndCrossStyles.ts` inline |
| `feedback` | Canvas fallbacks / templates | `FeedbackCanvas` | `FeedbackProperties` | `feedback/serialize.ts` | — | Generic trait CSS + heavy inline |
| `social` | `ElementDefaults` | `SocialCanvas` | `SocialProperties` | `social/serialize.ts` | — | Generic trait CSS + `blockStyles.ts` inline |
| `cross` | `FormBuilder` hardcoded / templates | `CrossCanvasBlock` (also `FormCanvas`) | `ButtonCrossProperties` | `cross/serialize.ts` (unwrapped) | `positionedDecorativePresentation.ts` | — |
| `floating-image` | Templates | `FloatingImageCanvas` | **None** (intentional) | `floating-image/serialize.ts` | `positionedDecorativePresentation.ts` | `pointerEvents` on link wrap only |
| `one-column` | `formStructure.createLayoutSection` | Structure DnD | `SectionProperties` | `serializeLayout.ts` | `outerLayoutPresentation.ts` | Column visibility rules |
| `two-column` | Structure DnD | Structure DnD | `SectionProperties` | `serializeLayout.ts` | `outerLayoutPresentation.ts` + `columnsContainerPresentation.ts` | Column visibility |
| `three-column` | Structure DnD | Structure DnD | `SectionProperties` | `serializeLayout.ts` | Same as two-column | Column visibility |
| `section` | Structure DnD | Structure DnD | `SectionProperties` | `serializeLayout.ts` | `outerLayoutPresentation.ts` | — |
| Column (virtual) | Per layout | Column slots | `ColumnProperties` | Attr-only in layout HTML | `columnPresentation.ts` | Hide-on-device in visibility CSS |
| Teaser (non-block) | `formStyle` | `TeaserPreview` | `TeaserPanel` | `serializeTeaser.ts` | `teaserPresentation.ts` | Hide-on-device in `buildTeaserExportDeviceCss` |
| Gamification shell | `formStyle` + spinner presence | `SpinnerCanvas` layout | Theme + spinner props | `serializeLayout.ts` | `gamificationPresentation.ts` | Generic trait CSS for spinner block traits |
| Form surface | `formStyle` | Canvas shell | `FormLayoutTabs` / theme | `serializeLayout.ts` | `formSurfacePresentation.ts` | Hide-on-device visibility |

**Registry exhaustiveness tests:** `builderBlockRegistry.exhaustiveness.test.ts`, `blockRegistry.exhaustiveness.test.ts`.

---

## 4. Per-family concern model (Part B)

| Family | Content | Merchant presentation | Structure | Behavior | Local formatting supported? |
|--------|---------|----------------------|-----------|----------|----------------------------|
| `text` | `properties.content` (RTE HTML) | `properties.style` (wrapper/block chrome) | Block wrapper div, `pre-wrap` | Selection, placeholder | **YES** — color, bold, lists, alignment spans |
| `button` | `properties.content` (label) | `properties.style` (whole button) | Align container, `<button>` | `action`, `actionUrl`, submit/spin | **Partial** — RTE bold/italic/size in label only |
| `divider` | — | `properties.style` (line geometry/color) | Separator role markup | — | NO |
| `text-input` | label, placeholder, required copy | `properties.style` (shell/typography) | `.pbn-canvas-input-shell` classes | Validation attrs, floating label | NO |
| `email-input` | Same + field mapping | Same | Same | Email validation | NO |
| `phone-input` | label, placeholder, country config | `properties.style` + `showCountrySelector` | Compound row, country wrap | Validation, country select | NO |
| `textarea` | label, placeholder | `properties.style` | Textarea shell classes | Validation | NO |
| `dropdown` | label, `options[]` | `properties.style` | Select shell | Validation | NO |
| `check-box` | `options[]` labels | `properties.style` (group chrome, colors) | Flex group, native inputs | Required group validation | NO (plain option labels) |
| `radio` | `options[]` labels | `properties.style` | Flex group, native radios | Required group validation | NO |
| `date` | label, placeholder format config | `properties.style` | Input shell (`type=date`) | Date range attrs, picker preview | NO |
| `timer` | countdown config, unit labels | `properties.style` (digits, labels, container) | Unit grid markup | Countdown runtime (embed) | NO |
| `spinner` | `slices[]`, probabilities | `properties.style` (wheel chrome) + slice colors | Wheel SVG/canvas, gamification columns | Spin runtime | NO |
| `image` | `imageUrl`, `imageAlt`, `linkUrl` | `properties.style` (dimensions, radius, shadow) | `img`, optional `<a>` wrap | Link navigation | NO |
| `video` | `videoUrl`, playback flags | `properties.style` (dimensions, radius) | iframe/video/thumbnail | Autoplay/embed runtime | NO |
| `product` | `selectedProducts`, display config | `properties.style` + nested `buttonStyle`/`imageStyle` | Product card grid/slots | CTA URLs from store data | NO |
| `coupon` | `codeType`, `staticCode`, display text | `properties.style` | Code row, copy button | Copy runtime | NO |
| `feedback` | question, scale config, labels | `properties.style` (active/inactive colors) | NPS/star/thumb inputs | Selection runtime | NO |
| `social` | `socialLinks` map | `properties.style` (icon layout) | Icon row, `<a>` per network | External links | NO |
| `cross` | — | **Top-level** `properties` (not `.style`) | Close button attrs | Close on click/Esc | NO |
| `floating-image` | `imageUrl`, `linkUrl` | `properties` + `properties.style` | Positioned shell | Link wrap | NO |
| Layout/column | — | `layout.properties.style` | Table/flex column structure | DnD, hide-on-device | NO |
| Teaser | — | `formStyle.teaser*` fields | Teaser chip attrs | Show/hide teaser | NO |
| Form surface | — | `formStyle` | Surface attrs | Entrance, positioning | NO |

---

## 5. Complete merchant trait inventory (Part C) — summary

Full per-trait tables are abbreviated here; every family was inspected via property panels (`registerBuilderBlockProperties.ts`), shared trait sections (`PositionWidthSection`, `DeviceSpacingSection`, `InputTypographyAccordion`, `ButtonColors`, etc.), and `ElementDefaults.ts`.

### Representative high-signal traits by family

| Family | Trait (sample) | UI writer | Canonical field | Desktop/mobile | Export effect | Storage class |
|--------|----------------|-----------|-----------------|----------------|---------------|---------------|
| `text` | `fontFamily`, `lineHeight`, `letterSpacing` | `TextElementProperties` | `style.*` | Both spacing traits | renderCss | CANONICAL PRESENTATION |
| `text` | `textColor`, `fontSize` (RTE) | `InlineEditor` / RTE toolbar | Often inline in `content` | Per-span | Content inline + partial renderCss block | CONTENT-LOCAL + possible DUPLICATE |
| `text` | `textAlignment` | RTE + orphan `properties.align` | `style.textAlignment` / `align` | Block | renderCss (align migrates at export) | DUPLICATE STATE (align orphan) |
| `button` | `backgroundColor`, `textColor` | `ButtonColors` | `style.*` | Shared | renderCss | CANONICAL |
| `button` | `hoverBackgroundColor`, `hoverTextColor` | `ButtonColors`, `ButtonCanvas` HB | `style.*` | Shared | **Not exported** | SAVED BUT NOT EXPORTED |
| `button` | `fontWeight` | `ButtonFontSettings` | `style.fontWeight` (`'medium'`) | Shared | renderCss as invalid `font-weight:medium` | CANONICAL + VOCAB DRIFT |
| `date` | `backgroundColor`, `borderStyle`, `focusBorderColor` | `DateProperties` | `style.*` | Both | **Not in renderCss** | EXPORT PARITY DEFECT |
| `date` | `fontSize`, `fontColor` | `InputTypographyAccordion` | `style.*` | Both | Generic mobile only | SAVED BUT NOT EXPORTED (base) |
| `check-box` | `fillColor`, `outlineColor` | `CheckBoxProperties` | `style.*` | Shared | Inline `accentColor` | EXPORTED INLINE |
| `radio` | Same | `RadioProperties` | `style.*` | Shared | Inline + `radio.export.css` | DUPLICATE EXPORT |
| `timer` | `countColor`, `fontSize`, `separatorColor` | `TimerProperties` | `style.*` | Both | Inline on every span | EXPORTED INLINE |
| `image` | `imageWidthPx`, `cornerRadius` | `ImageProperties` | `style.*` | Both | Inline on container/img | EXPORTED INLINE |
| `product` | `priceColor`, `buttonBackgroundColor` | `ProductProperties` | `style.*` | Both | Inline + product embedded | DUPLICATE EXPORT |
| `cross` | `hoverColor`, `hoverBackgroundColor` | `ButtonCrossProperties`, HB | **top-level** `properties.*` | Margin/padding device-paired | **Not exported** | SAVED BUT NOT EXPORTED |
| `floating-image` | `position`, `imageWidthPx` | Canvas/context only | `properties` / `style` | Both | renderCss | CANONICAL (no panel) |

**Aggregate counts (audited):**

| Metric | Count |
|--------|-------|
| Merchant traits audited (unique trait keys × families) | **~192** |
| Traits without canonical persisted owner | **~4** (orphan `align` on text; legacy `borderRadius` object shapes; floating-image has model but no panel — still persisted) |
| Traits exported inline (merchant presentation) | **~118** across 12 unmigrated families |
| Traits saved but not exported | **~24** (date base shell ~18 traits; button hover 2; cross hover 2; teaser hide uses embedded not renderCss — intentional split) |

---

## 6. Current writer inventory (Part D) — summary

| Family | Concern | Writer | Writes to | Whole/local | Canonical? |
|--------|---------|--------|-----------|-------------|------------|
| All migrated input shells | Shell chrome | `*Properties.tsx` | `properties.style` | Whole-block | YES |
| All migrated input shells | Shell chrome | `buildCanvasInputShellStyles` | Canvas React style | Whole-block | YES (mirror) |
| `text` | Block chrome | `TextElementProperties` | `style.*` | Whole-block | YES |
| `text` | Content formatting | `InlineEditor` / RTE | `content` HTML | Local | YES (content model) |
| `button` | Button chrome | `ButtonProperties` | `style.*` | Whole-block | YES |
| `button` | Label formatting | RTE (limited) | `content` | Local | YES |
| `button` | Hover preview | `ButtonCanvas` `onMouseEnter` | DOM `style` | Whole-block | CANVAS-ONLY (preview) |
| `cross` | Hover preview | `CrossCanvas` HB | DOM `style` | Whole-block | CANVAS-ONLY (preview) |
| `date` | Shell chrome | `DateProperties` | `style.*` | Whole-block | YES (but no export compiler) |
| Unmigrated families | All presentation | Property panels | `style.*` | Whole-block | YES |
| Unmigrated families | Export paint | `blocks/*/serialize.ts` | Inline `style=""` | Whole-block | **MULTIPLE PERSISTENCE REPRESENTATIONS** |
| Save boundary | Text dedupe | `normalizeTextRichContentHtml` | Strips dupes from `content` | Local | Normalizer (not writer) |

**Same-concern multiple persistence paths flagged:**

1. `text`: `properties.align` vs `style.textAlignment`
2. Input family: `fontColor` vs `textColor` keys (both resolve in `ElementStyles`)
3. `cross`: top-level presentation props vs other blocks' `properties.style`
4. Unmigrated families: `properties.style` (canonical) + serializer inline (export duplicate)

---

## 7. Current production-flow evidence (Part E)

Evidence from existing audit/serialization tests (no manual legacy seeding):

| Family | User action | Field changed | Content? | Style? | Other? |
|--------|-------------|---------------|----------|--------|--------|
| `text` | Style panel traits | `textColor`, `textAlignment` | No | Yes (`style`) | `renderCss` updated; `content` unchanged (`popupProductionCssRteOwnership.audit.test.ts`) |
| `text` | RTE color span | inline `color` in HTML | Yes | No | Preserved in export HTML; block align stripped |
| `text` | Orphan `align` on save | `align` → `textAlignment` | No | Yes | Export uses `align` over `style.textAlignment` |
| `button` | Default create | — | Yes (`content`) | Yes (`style`) | Hover defaults saved; export has base renderCss, no `:hover` |
| `date` | Trait panel change | `backgroundColor`, `borderColor` | No | Yes | Canvas shows via `buildCanvasInputShellStyles`; export HTML class-only; **no matching renderCss rules** |
| `phone-input` | Trait change | `focusBorderColor` | No | Yes | renderCss `:focus-within` + CSS vars (Phase 6E tests green) |
| `divider` | Trait change | `dividerLineColor` | No | Yes | Inline export on separator div |
| `product` | Trait change | `priceColor` | No | Yes | Inline on price spans + generic mobile font-size |

---

## 8. Content vs presentation matrix (Part F)

| Family | Content field | Presentation HTML possible? | Current writer? | Supported semantics? | Verdict |
|--------|---------------|----------------------------|-----------------|---------------------|---------|
| `text` | `content` | YES — spans, alignment divs | RTE (`InlineEditor`) | YES — local formatting | LEGITIMATE CONTENT-LOCAL FORMATTING |
| `button` | `content` | YES — `<strong>`, spans when `rteEnabled` | RTE (limited) | YES — label-local only | LEGITIMATE CONTENT-LOCAL FORMATTING |
| `button` | `content` | `<a>` possible via RTE | RTE | Navigation uses `actionUrl` primarily | WHOLE-BLOCK PRESENTATION LEAK (minor; links in label) |
| Field labels | `labelText` | Plain text only | `CanvasFieldLabel` (`rteEnabled=false`) | NO formatting | CLEAN |
| Radio/checkbox options | `option.label` | Plain text | `CanvasOptionEditor` | NO formatting | CLEAN |
| All other families | Structured strings | NO RTE | — | NO local formatting | WHOLE-BLOCK PRESENTATION ONLY |

**Critical rule applied:** RTE-created markup in Text/Button is **actively created by current UI** — fixing writers/normalizers is the primary issue; stripping alone is insufficient.

---

## 9. Exported inline-style inventory (Part G) — summary

| Family | DOM node | Inline declaration (examples) | Concern | Model source | Should remain inline? |
|--------|----------|------------------------------|---------|--------------|----------------------|
| `button` | Align wrapper `div` | `text-align`, `width:100%` | REUSABLE STRUCTURE | `align` | YES |
| `text` | Content spans | `color`, `background` (RTE) | CONTENT-LOCAL | RTE | YES (legitimate) |
| `divider` | Outer + separator | margin, border-top color/width | MERCHANT PRESENTATION | `style.*` | NO — should be renderCss |
| `image` | `img`, container | width, height, border-radius | MERCHANT PRESENTATION | `style.*` | NO |
| `timer` | Count/label spans | font-size, color, font-family | MERCHANT PRESENTATION | `style.*` | NO |
| `product` | Card, price, button | extensive paint | MERCHANT PRESENTATION | `style.*` | NO |
| `radio` | `input` | accent, border, background `!important` | MERCHANT + STRUCTURE | `style.fillColor` | PARTIAL — theme-reset justification |
| `feedback` | labels, inputs | active/inactive colors | MERCHANT PRESENTATION | `style.*` | NO |
| `floating-image` | Link wrap | `pointer-events:auto` | RUNTIME BEHAVIOR | link presence | YES |
| Input shells (migrated) | Shell nodes | **None** | — | — | CLEAN |

---

## 10. Trait → save → renderCss completeness (Part H)

| Family | Trait | Saved? | renderCss? | Selector correct? | Storefront? | Status |
|--------|-------|--------|------------|-------------------|-------------|--------|
| `text-input` | `backgroundColor` | YES | YES | `.pbn-canvas-input-shell` | YES | COMPLETE |
| `email-input` | `focusBorderColor` | YES | YES | `:focus-within` + vars | YES | COMPLETE |
| `phone-input` | `fontSize` / mobile | YES | YES | `input[type=tel]` + `@media` | YES | COMPLETE |
| `dropdown` | Shell border | YES | YES | `.pbn-canvas-input-shell` | YES | COMPLETE |
| `textarea` | `rows` / height | YES | YES | textarea rules | YES | COMPLETE |
| `button` | `backgroundColor` | YES | YES | `button` descendant | YES | COMPLETE |
| `button` | `hoverBackgroundColor` | YES | **NO** | — | **NO** | SAVED BUT NOT EXPORTED |
| `cross` | `hoverColor` | YES | **NO** | — | **NO** | SAVED BUT NOT EXPORTED |
| `date` | `backgroundColor` | YES | **NO** | — | **NO** | SAVED BUT NOT EXPORTED |
| `date` | `paddingMobile` | YES | **NO** (base) | — | Partial via embedded only | EXPORTED THROUGH EMBEDDED CSS (mobile only) |
| `divider` | `dividerLineColor` | YES | NO | — | Inline only | EXPORTED THROUGH INLINE CSS |
| `timer` | `countColor` | YES | NO | — | Inline only | EXPORTED THROUGH INLINE CSS |
| `image` | `cornerRadius` | YES | NO | — | Inline only | EXPORTED THROUGH INLINE CSS |
| `text` | `textAlignment` | YES | YES | content wrapper | YES | COMPLETE |
| `text` | RTE `color` span | In content | N/A (content-local) | — | YES | LEGITIMATE SPLIT |

---

## 11. State presentation parity (Part I)

| Family | State | Merchant? | Canvas | Saved | renderCss | Storefront | Verdict |
|--------|-------|-----------|--------|-------|-----------|------------|---------|
| `button` | hover | YES | HB DOM mutation | YES | NO | NO | **CONSUMER PARITY DEFECT** |
| `cross` | hover | YES | HB DOM mutation | YES | NO | NO | **CONSUMER PARITY DEFECT** |
| Input shells | focus | YES | CSS vars | YES | YES `:focus-within` | YES | COMPLETE |
| `radio` / `check-box` | checked | YES | Native + accent | YES | NO (inline) | YES (inline) | EXPORTED THROUGH INLINE CSS |
| `feedback` | selected | YES | Canvas | YES | NO (inline) | YES (inline) | EXPORTED THROUGH INLINE CSS |

**State parity defects: 2** (button hover, cross hover).

---

## 12. Responsive parity matrix (Part J)

| Family | Trait | Desktop source | Mobile source | Same owner? | Inline conflict? | Storefront parity? |
|--------|-------|----------------|----------------|-------------|------------------|-------------------|
| Migrated blocks | padding/margin/radius | `style.paddingDesktop` | `style.paddingMobile` | renderCss base + `@media` | NO | YES (Phase 0 green) |
| `date` | padding | Canvas `ElementStyles` | `style.paddingMobile` | Canvas vs embedded only | **YES** — no desktop export owner | **NO** |
| `divider` | padding | Inline desktop | `buildBlockTraitResponsiveCss` | style model vs inline+embedded | **YES** PCL-008 pattern | Partial |
| `image` | fontSize | Inline desktop | Generic embedded | style vs inline+embedded | **YES** | Partial |
| `product` | grid columns | Inline layout | `buildProductGridResponsiveCss` | Split (intentional mechanics) | NO for grid | YES (control case green) |
| `phone-input` | country select width | Shared CSS | Embedded structural | Structural only | NO | YES |

**Responsive desktop-inline + mobile-stylesheet defects: 11 unmigrated content families** (all except `radio` which also has static sheet).

---

## 13. Generic embedded CSS consumer inventory (Part K)

### `buildEmbeddedStepResponsiveCss` composition (`responsiveDeviceCss.ts`)

| Consumer | Families affected | Merchant? | Structure? | Behavior? |
|----------|-------------------|-----------|------------|-----------|
| `buildBlockDeviceVisibilityCss` | All with hide attrs | NO | NO | YES (viewport hide) |
| `buildBlockTraitResponsiveCss` | **12 types** (see below) | **YES** (padding, margin, radius, font-size) | NO | NO |
| `buildPhoneInputCountrySelectResponsiveCss` | `phone-input` | NO | YES | NO |
| `buildProductHorizontalResponsiveCss` | `product` horizontal | NO | YES (layout) | NO |
| `buildProductGridResponsiveCss` | `product` grid | NO | YES (layout) | NO |
| `buildTeaserExportDeviceCss` | Teaser | NO | NO | YES (hide) |

### `MIGRATED_BLOCK_TRAIT_TYPES` (excluded from generic trait CSS)

`cross`, `floating-image`, `button`, `text`, `text-input`, `email-input`, `textarea`, `dropdown`, `phone-input`

### Remaining generic merchant responsive consumers (12)

`divider`, `check-box`, `radio`, `date`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social`

### Legitimate structural embedded consumers

| Sheet / helper | Role |
|----------------|------|
| `floating-label.export.css` | Float mechanics, compound row structure |
| `embed-responsive.export.css` | Validation, responsive shell behavior |
| `radio.export.css` | Native radio `:checked` mechanics |
| `popup-entrance.export.css` | Entrance animation |
| `buildPhoneInputCountrySelectResponsiveCss` | Country select max-width |
| Product grid/horizontal helpers | Card layout mechanics |

---

## 14. Dedicated compiler completeness (Part L)

| Family | Compiler | Base | Mobile | State | Inline dup? | Embedded dup? |
|--------|----------|------|--------|-------|-------------|---------------|
| Form surface | `formSurfacePresentation` | YES | YES | hide in visibility | NO | NO |
| Layout | `outerLayoutPresentation` | YES | YES | — | NO | NO |
| Columns | `columnsContainerPresentation` | YES | YES | — | NO | NO |
| Column | `columnPresentation` | YES | YES | hide | NO | NO |
| `button` | `buttonPresentation` | YES | YES | **hover NO** | Structural only | NO |
| `text` | `textPresentation` | YES | YES | — | NO | Link theme in step |
| `text-input`/`email` | `standardFieldPresentation` | YES | YES | focus YES | NO | Floating-label structure |
| `textarea` | `textareaPresentation` | YES | YES | focus YES | NO | Floating-label |
| `dropdown` | `dropdownPresentation` | YES | YES | focus YES | NO | NO |
| `phone-input` | `phoneInputPresentation` | YES | YES | focus YES | NO | Country width |
| `cross`/`floating-image` | `positionedDecorativePresentation` | YES | YES | **hover NO** | pointerEvents only | NO |
| Teaser | `teaserPresentation` | YES | YES | hide embedded | NO | Hide only |
| Gamification | `gamificationPresentation` | YES | YES | — | NO | NO |
| **`date`** | **—** | **NO** | **NO** | **NO** | NO | Generic mobile only |

---

## 15. Canvas-only presentation (Part M)

| Family | Trait | Canvas source | Saved? | Export | Verdict |
|--------|-------|---------------|--------|--------|---------|
| `button` | hover colors | `ButtonCanvas` HB | YES | NO renderCss | **SAVED BUT NOT EXPORTED** (not canvas-only) |
| `cross` | hover colors | `CrossCanvas` HB | YES | NO | **SAVED BUT NOT EXPORTED** |
| All | Selection chrome | `CanvasBlockShell` | NO | NO | Editor-only (OK) |
| `floating-image` | position | Canvas drag | YES | renderCss | COMPLETE |

**True canvas-only merchant traits (not saved):** None found for merchant-configurable presentation. Editor focus rings are not merchant traits.

---

## 16. Serializer-only presentation (Part N)

| Family | Presentation | Serializer | Canvas | Model | Verdict |
|--------|--------------|------------|--------|-------|---------|
| Unmigrated families | Full inline paint | `serialize.ts` builders | `ElementStyles` canvas helpers | `style.*` | **EXPORT-ONLY DRIFT RISK** — same model, different CSS path; generally consistent values but dual pipeline |
| `date` | None in renderCss | Class-only HTML | Full shell styles | `style.*` | **EXPORT-ONLY DRIFT** — canvas rich, storefront bare |

---

## 17. Link ownership (Part O)

| Family | UI writer? | Structure | Presentation | Duplicate? | Verdict |
|--------|------------|-----------|--------------|------------|---------|
| `text` | RTE link tool | `<a href>` in `content` | Theme `linkColor` in renderCss + step CSS | Possible dual | CURRENT — content links + theme |
| `button` | `actionUrl` (nav) | No `<a>` in export (button type=button) | renderCss | NO | CURRENT |
| `image` | `ImageProperties.linkUrl` | `<a>` wrap | Inline on img | Inline img styles | CURRENT |
| `floating-image` | Context/linkUrl | `<a>` wrap | renderCss on img | NO | CURRENT |
| `social` | `socialLinks.*` | `<a>` per icon | Inline on icons | NO | CURRENT |
| `product` | Store CTA | `<a>` on button | Inline button styles | NO | CURRENT |
| `coupon` | — | — | — | — | NO LINK |

**Unsupported link candidates:** Button RTE `<a>` in label (rare; `applyRichTextLinkStyles` at sanitize) — **FORWARD-ONLY DELETION CANDIDATE** if product disclaims label links.

---

## 18. Typography ownership (Part P)

| Family | Trait | Writers | Canonical | Content repr. | renderCss | Duplicate? |
|--------|-------|---------|-----------|---------------|-----------|------------|
| `text` | `fontFamily` | Style panel | `style.fontFamily` | — | YES | NO |
| `text` | color/size | RTE | `content` spans | YES | Block `textColor` in renderCss if set | SPLIT (legitimate) |
| `button` | `fontWeight` | Panel | `style.fontWeight` = `'medium'` | RTE bold in content | YES — invalid CSS value | VOCAB DRIFT |
| Input family | `fontSize`/`fontColor` | Typography accordion | `style.*` | — | renderCss (except date) | `fontColor`/`textColor` alias |
| `timer` | `fontSize`, `labelSize` | Panel | `style.*` | — | Inline only | DUPLICATE EXPORT |

**Typography vocabulary drift:** `fontWeight: 'medium' | 'semibold' | 'bold'` stored as strings; `resolveButtonPaintedCssRules` emits `font-weight:medium` (invalid CSS — browsers ignore). `bold` vs `700` mixed in timer serializer. **Creates actual export drift** for button weight.

---

## 19. Interaction-state export matrix (Part Q)

| Family | State trait | Writer | Saved | Exported | Browser verified |
|--------|-------------|--------|-------|----------|------------------|
| `button` | `hoverBackgroundColor` | `ButtonColors` | YES | NO | NO |
| `button` | `hoverTextColor` | `ButtonColors` | YES | NO | NO |
| `cross` | `hoverColor` | `ButtonCrossProperties` | YES | NO | NO |
| `cross` | `hoverBackgroundColor` | `ButtonCrossProperties` | YES | NO | NO |
| Inputs | `focusBorderColor` | Style sections | YES | YES renderCss | YES (Phase 6 tests) |
| `feedback` | `activeColor` | `FeedbackProperties` | YES | YES inline | Not in Phase 0 matrix |

---

## 20. Dead helper / component inventory (Part R)

| Symbol | Consumers | Role | Delete candidate? |
|--------|-----------|------|-------------------|
| `WysiwygEditor.tsx` | **0** imports | Legacy RTE | **YES** |
| `inputFieldStyles.ts` (most exports) | Canvas adapters, tests, markup | Deprecated canvas bridge | Partial — after canvas uses `ElementStyles` directly |
| `innerInputFieldStyle`, `inputOutlineStyle`, etc. | Canvas/markup only | Deprecated | YES when callers migrated |
| `timerFixedWidthFitCore.applyTimerBlockWrapperLayout` deprecated | Narrow call sites | Legacy layout | Review |
| `ElementStyles.isPercentWidthLayoutBlock` deprecated alias | Unknown | Legacy | Review |
| `BorderWidthInput` `coerceMinZeroNumericInput` deprecated | Panel | Legacy | Review |
| `popupHtmlSerializer/index` `CompiledPopupArtifact` deprecated alias | Save/embed callers | Alias | NO — still used |

---

## 21. All-block architecture status matrix (Part S)

| Family | Content | Trait owner | Inline merchant CSS | renderCss | Responsive | State | Status |
|--------|---------|-------------|---------------------|-----------|------------|-------|--------|
| `text` | `content` | `style` | RTE only (local) | Complete | Complete | N/A | CLEAN WITH LEGITIMATE SPLIT |
| `button` | `content` | `style` | Structural only | Base complete | Complete | **Hover defect** | PARTIAL MIGRATION |
| `text-input` | field data | `style` | None | Complete | Complete | Complete | CLEAN |
| `email-input` | field data | `style` | None | Complete | Complete | Complete | CLEAN |
| `phone-input` | field data | `style` | None | Complete | Complete | Complete | CLEAN WITH LEGITIMATE SPLIT |
| `textarea` | field data | `style` | None | Complete | Complete | Complete | CLEAN |
| `dropdown` | field data | `style` | None | Complete | Complete | Complete | CLEAN |
| **`date`** | field data | `style` | None | **Missing** | Embedded mobile only | Focus not exported | **EXPORT PARITY DEFECT** |
| `divider` | — | `style` | Full | None | Generic embedded | N/A | DUPLICATE OWNERSHIP |
| `check-box` | options | `style` | Full | None | Generic | Inline checked | DUPLICATE OWNERSHIP |
| `radio` | options | `style` | Full + sheet | None | Generic | Inline checked | CLEAN WITH LEGITIMATE SPLIT |
| `timer` | config | `style` | Full | None | Generic | N/A | DUPLICATE OWNERSHIP |
| `spinner` | slices | `style` | Wheel inline | Shell only | Generic | N/A | PARTIAL MIGRATION |
| `image` | media+link | `style` | Full | None | Generic | N/A | DUPLICATE OWNERSHIP |
| `video` | media | `style` | Full | None | Generic | N/A | DUPLICATE OWNERSHIP |
| `product` | catalog | `style` | Full | None | Generic+product | N/A | DUPLICATE OWNERSHIP |
| `coupon` | code | `style` | Full | None | Generic | N/A | DUPLICATE OWNERSHIP |
| `feedback` | question | `style` | Full | None | Generic | Inline | DUPLICATE OWNERSHIP |
| `social` | links | `style` | Full | None | Generic | N/A | DUPLICATE OWNERSHIP |
| `cross` | — | top-level props | None | Base complete | Complete | **Hover defect** | PARTIAL MIGRATION |
| `floating-image` | media+link | props/style | pointerEvents | Complete | Complete | N/A | CLEAN WITH LEGITIMATE SPLIT |
| Layout/column | — | `style` | None | Complete | Complete | Hide | CLEAN |
| Teaser | — | `formStyle` | None | Complete | Hide embedded | N/A | CLEAN WITH LEGITIMATE SPLIT |
| Form surface | — | `formStyle` | None | Complete | Complete | N/A | CLEAN |

**Summary:** 7 CLEAN, 5 CLEAN WITH SPLIT, 3 PARTIAL, 1 EXPORT PARITY DEFECT (`date`), 8 DUPLICATE OWNERSHIP, 0 UNKNOWN.

---

## 22. Ranked defect table (Part T)

Scoring dimensions 0–5 each (max 45).

| Defect | Families | P | S | R | D | I | W | U | C | T | Total | Blocking? |
|--------|----------|---|---|---|---|---|---|---|---|---|-------|-----------|
| **D1: Date field has no renderCss compiler** | `date` | 3 | **5** | 4 | 4 | 5 | **5** | **5** | **5** | **5** | **41** | **YES** |
| D2: Button/Cross hover not exported | `button`, `cross` | 2 | 4 | 2 | 3 | 1 | 5 | 4 | 4 | 5 | 30 | NO |
| D3: 12 families merchant inline export | divider…social | 2 | 4 | 4 | **5** | **5** | 5 | 4 | 3 | 4 | 36 | NO |
| D4: Button fontWeight vocabulary drift | `button` | 2 | 3 | 1 | 2 | 3 | 4 | 3 | 4 | 5 | 27 | NO |
| D5: Text orphan `align` dual persistence | `text` | 3 | 2 | 2 | 3 | 0 | 3 | 2 | 4 | 4 | 23 | NO |
| D6: Cross top-level props vs `style` shape | `cross` | 2 | 2 | 1 | 3 | 0 | 3 | 2 | 3 | 3 | 19 | NO |
| D7: `WysiwygEditor` dead component | — | 0 | 0 | 0 | 1 | 0 | 0 | 0 | 5 | 4 | 10 | NO |

**Highest-ranked defect: D1 — Date field missing renderCss compiler.**

---

## 23. Selected next implementation boundary (Part U)

### Selected defect

**Date input merchant presentation is saved in `properties.style` and rendered on canvas via shared input-shell resolvers, but `compileStandardFieldPresentationRenderCss` excludes `date`, leaving storefront export with structure-only HTML and at best generic mobile trait overrides.**

### Affected families

`date` (single family; reuses Phase 6B input-shell compiler)

### Current writer(s)

- `DateProperties.tsx` → `onUpdate({ style: … })`
- `DateCanvas.tsx` → `buildCanvasInputShellStyles` / `getInputFieldTypographyStyle`
- `ElementStyles.resolveStandardField*` (shared resolvers already used by canvas)

### Current persisted representation

`element.properties.style` — same shape as `text-input` / `email-input` (paddingDesktop/Mobile, borderStyle, focusBorderColor, typography accordion fields, etc.)

### Current export representation

- HTML: `serializeTextInputLike(…, 'date', …)` — class-only shells
- CSS: `floating-label.export.css` (structure/behavior) + `buildBlockTraitResponsiveCss` (mobile padding/margin/radius/font-size only)
- **No** artifact-scoped renderCss for outline/value/label/focus/placeholder

### Target representation

- Extend `StandardFieldBlockType` to include `'date'`
- `compileStandardFieldPresentationRenderCss` collects and compiles date blocks using existing selectors (`.pbn-canvas-input-shell > input[type=date]` matches current selector pattern)
- Add `'date'` to `MIGRATED_BLOCK_TRAIT_TYPES`
- CSS vars for focus/placeholder on export mount (same as 6B)

### Code expected to be deleted

- Date entries from generic `buildBlockTraitResponsiveCss` emission (after migration)
- Any date-specific inline style builders if discovered (none today)
- Residual deprecated `inputFieldStyles` canvas bridges once date canvas reads `ElementStyles` directly (optional follow-up)

### Why this boundary is coherent

1. **Proven defect** — date is the only input-shell family excluded from an existing compiler
2. **DOM parity** — `serializeTextInputLike` already emits identical shell topology to text-input
3. **Resolver parity** — `ElementStyles.resolveStandardField*` already powers date canvas
4. **Test template exists** — `standardFieldPresentationRenderCss.test.ts` + browser harness
5. **Bounded risk** — no RTE semantics change, no new compiler file, no content model change
6. **Deletion opportunity** — removes date from generic embedded merchant trait consumer list

**Alternative considered:** Button/Cross hover export (D2) — coherent but lower severity than date total presentation absence.

---

## 24. Acceptance test plan (Part V)

### Phase 0 requirement

```
18 migrated regression cases GREEN
0 unmigrated risk RED
5 control cases GREEN
```

Verified 2026-07-06: `responsiveCssBaseline.browser.test.ts` — **PASS**.

### G1 requirement

```
G1 production raw CSS contract: PASS
```

Verified 2026-07-06: `exportCssProductionBuildContract.test.ts` — **PASS**.

### Phase 6F — Date input tests (proposed)

| Test | Purpose |
|------|---------|
| Current UI writer | `DateProperties` trait change updates `properties.style` |
| Save model | Snapshot round-trip preserves style fields |
| Reload | Reloaded canvas matches saved traits |
| renderCss unit | `compileStandardFieldPresentationRenderCss` emits outline/value/label/focus for `date` |
| Serializer negative-inline | `serializeDate` output has no merchant `style=""` on shell |
| Embedded CSS negative | `buildBlockTraitResponsiveCss` skips `date` |
| Chromium computed-style desktop | Border, background, font-size, padding match traits |
| Chromium computed-style mobile | Mobile padding/font-size via `@media` |
| Focus state | `:focus-within` border uses `focusBorderColor` |
| Placeholder | `::placeholder` color when configured |
| Artifact isolation | `[data-adflipr-popup-artifact]` scoping |
| Consumer parity | Canvas trait → save → renderCss → mounted embed computed style |

---

## 25. Mandatory architecture questions

| # | Question | Answer |
|---|----------|--------|
| 1 | Are all merchant traits saved in canonical presentation state? | **NO** — most are; cross uses top-level props; text RTE color can exist only in content |
| 2 | Does any merchant trait persist only as inline HTML? | **NO** for panel traits; **YES** for RTE-local color/size in text/button content |
| 3 | Does any whole-block trait leak into content HTML? | **Rare** — text-align divs stripped at export; button label RTE bold is label-local (legitimate) |
| 4 | Does any current UI actively create noncanonical presentation HTML? | **YES** — Text RTE color/alignment spans (supported); not whole-block leaks |
| 5 | Does any merchant trait work in canvas but fail on storefront? | **YES** — **date** (all base shell traits); button/cross hover |
| 6 | Does any merchant state trait work in canvas but fail on storefront? | **YES** — button hover, cross hover |
| 7 | Does any responsive trait use desktop inline + mobile stylesheet? | **YES** — 11 unmigrated families + date (canvas desktop vs embedded mobile) |
| 8 | Same concern, multiple persistence paths? | **YES** — text `align`; fontColor/textColor; cross prop shape |
| 9 | Same concern, multiple export paths? | **YES** — unmigrated inline + embedded; radio inline + sheet |
| 10 | Is renderCss becoming a dumping ground? | **NO** — per-family compilers with shared resolvers |
| 11 | Structure/behavior separation intact? | **YES** |
| 12 | Which defect should be fixed next? | **D1 — Date field renderCss compiler extension** |

---

## 26. Final verdict

The popup CSS architecture direction is correct and Phase 0/G1 guardrails are green. Family-by-family migration is insufficient without cross-block audits — this audit confirms **date** as the highest-severity gap in the input-shell cluster, plus **hover state export** debt on button/cross, and **12 families** still on inline+embedded merchant presentation.

**Verdict: ACCEPT WITH CLEANUP**

---

## 27. Recommended next phase name

**Phase 6F — Date Input Merchant Presentation Migration (standardFieldPresentation extension)**

---

*End of Phase H2 audit. No production code or tests were modified.*
