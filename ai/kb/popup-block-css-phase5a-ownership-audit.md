# Popup block CSS — Phase 5A DOM ownership audit

**Status:** Complete (2026-07-05)  
**Type:** Audit only — no production CSS migration  
**Scope:** Block-family DOM ownership, split-target defects, migration boundary recommendation  
**Related:** [`popup-css-phase4e-acceptance-report.md`](./popup-css-phase4e-acceptance-report.md), [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md), [`popup-responsive-css-phase0-baseline.md`](./popup-responsive-css-phase0-baseline.md)

**Superseded KB removed:** Phase 4C audit, Phase 3A/3C inventories (historical detail folded into implementation reports + this audit).

---

## A. Executive verdict

**Is “block CSS” one migration owner?**

```text
NO
```

Block presentation is not one coherent DOM surface. Wrapped blocks share a common export wrapper (`[data-adflipr-block-id]`), but **painted**, **content**, and **behavior** owners differ by block family. `buildBlockTraitResponsiveCss()` assumes the wrapper is the presentation target — that assumption is false for most merchant-controlled traits.

---

## B. Complete block inventory

### Registries

| Registry | File | Role |
| -------- | ---- | ---- |
| `BlockType` | `components/form-builder/types.ts` | Canonical type union (content + layout) |
| Builder blocks | `blocks/registerBuilderBlocks.ts` + `blocks/registry.ts` | Canvas renderer + palette |
| Serializer map | `utils/popupHtmlSerializer/blockRegistry.ts` + `registerPopupBlockSerializers.ts` | Export HTML per type |
| Embed initializers | `embed/blockRegistry.ts` + `embed/blockInitializers/*` | Runtime JS on matched nodes |

### Counts

| Metric | Count |
| ------ | ----: |
| Total `BlockType` values | **24** |
| Layout types (structure pipeline) | **4** (`one-column`, `two-column`, `three-column`, `section`) |
| Builder content blocks | **20** |
| Serializer wrapped content blocks | **19** |
| Serializer unwrapped content blocks | **2** (`cross`, `floating-image`) |
| Runtime-interactive initializer blocks | **5** (`spinner`, `timer`, `video`, `coupon`, `floating-label` on inputs) |
| Blocks with responsive trait fields (`paddingMobile`, etc.) | **19 wrapped** (+ column styles on layouts — out of 5A block scope) |

### Registry drift

| Issue | Detail |
| ----- | ------ |
| Builder vs serializer | **Aligned** — same 20 content types; `BUILDER_CONTENT_BLOCK_TYPES` matches `WRAPPED` + unwrapped |
| Serializer vs runtime | **Drift** — 15 content types have no embed initializer (static HTML only) |
| `cross` / `floating-image` | In builder + serializer; **excluded** from `buildBlockTraitResponsiveCss` (separate generators) |

### Per-type inventory

| Block type | Registry | Builder canvas | Serializer | Runtime init | Interactive | Responsive traits |
| ---------- | -------- | -------------- | ---------- | ------------ | ----------- | ----------------- |
| `text` | ✓ | TextCanvas | `serializeText` | — | preview only | padding/margin/radius/font via style |
| `button` | ✓ | ButtonCanvas | `serializeButton` | — | click/submit attrs | padding/margin/radius/font |
| `divider` | ✓ | DividerCanvas | `serializeDivider` | — | — | width/position (limited) |
| `text-input` | ✓ | TextInputCanvas | `serializeTextInputLike` | floating-label | input | padding/margin/radius/font on shell |
| `email-input` | ✓ | EmailInputCanvas | `serializeTextInputLike` | floating-label | input | same |
| `phone-input` | ✓ | PhoneInputCanvas | `serializePhoneInput` | floating-label | select+input | same + country select CSS |
| `textarea` | ✓ | TextareaCanvas | `serializeTextarea` | floating-label | textarea | same |
| `dropdown` | ✓ | DropdownCanvas | `serializeDropdown` | floating-label | select | same |
| `check-box` | ✓ | CheckboxCanvas | `serializeCheckboxGroup` | — | inputs | group/shell traits |
| `radio` | ✓ | RadioCanvas | `serializeRadioGroup` | — | inputs | same + `POPUP_RADIO_EXPORT_CSS` |
| `date` | ✓ | DateCanvas | `serializeDate` | — | date input | shell traits |
| `timer` | ✓ | TimerCanvas | `serializeTimer` | timer.ts | countdown DOM | width/margin traits |
| `spinner` | ✓ | SpinnerCanvas | `serializeSpinner` | spinner.ts | canvas wheel | gamification path (Phase 4D) |
| `image` | ✓ | ImageCanvas | `serializeImage` | — | link optional | image size/position traits |
| `video` | ✓ | VideoCanvas | `serializeVideo` | video.ts | autoplay | video traits |
| `product` | ✓ | ProductCanvas | `serializeProduct` | — | links | grid/horizontal + slot counts |
| `coupon` | ✓ | CouponCanvas | `serializeCoupon` | coupon.ts | copy btn | coupon traits |
| `feedback` | ✓ | FeedbackCanvas | `serializeFeedback` | — | rating UI | feedback traits |
| `social` | ✓ | SocialCanvas | `serializeSocial` | — | links | social traits |
| `cross` | ✓ | CrossCanvasBlock | `serializeCross` (unwrapped) | — | close btn | margin via positioned shell |
| `floating-image` | ✓ | FloatingImageCanvas | `serializeFloatingImage` (unwrapped) | — | link optional | margin via positioned shell |
| `one-column` … `section` | structure | layout renderer | `serializeLayout` | — | — | column/layout traits (migrated Phase 3–4) |

---

## C. DOM ownership vocabulary

| Role | Definition | Serialized anchor |
| ---- | ---------- | ----------------- |
| **Identity owner** | Stable instance id for CSS/JS | `[data-adflipr-block-id="{element.id}"]` on export wrapper (or on unwrapped root for cross/floating-image) |
| **Layout owner** | Column placement: margin, width %, alignment, display | Export wrapper from `getExportWrapperStyleFromLayout()` / `serializeElements.ts` |
| **Painted owner** | Element whose box shows padding, background, border, radius, shadow | **Varies** — often inner native/control node, not wrapper |
| **Content owner** | Typography: font, color, line-height, text-align | Inner RTE/input/text node |
| **Behavior owner** | Runtime interaction target | `<button>`, `<input>`, `<select>`, canvas, embed selectors |

**Critical invariant:** `identity owner ≠ painted owner` for button, text, inputs, image, and most wrapped blocks.

---

## D. Per-block DOM map (representative production HTML)

Export shell from `serializeElements.ts`:

```html
<div data-block-type="{type}" data-adflipr-block-id="{id}" style="/* layout/margin/width */">
  <!-- inner from block serializer -->
</div>
```

### `button`

```text
[data-adflipr-block-id]          ← identity + layout (margin, width)
  └─ div (align container)
       └─ button                 ← PAINTED (padding, bg, border-radius, typography)
            └─ label HTML
```

### `text`

```text
[data-adflipr-block-id]          ← identity + layout (margin, width %)
  └─ div                         ← PAINTED + CONTENT (padding, bg, border, typography via getElementStyle)
       └─ sanitized RTE HTML
```

### `text-input` / `email-input` / `textarea` / `dropdown`

```text
[data-adflipr-block-id]          ← identity + layout (margin, max-width)
  └─ label? + outline div        ← PAINTED shell (border, bg, radius, height; getElementStyle minus typography)
       └─ input/select/textarea  ← CONTENT + fixed inner padding
```

### `image`

```text
[data-adflipr-block-id]          ← identity + layout
  └─ div (alignment)             ← container
       └─ img / a>img            ← PAINTED (dimensions, object-fit, radius on img)
```

### `divider`

```text
[data-adflipr-block-id]          ← identity + layout
  └─ div (outer frame)           ← optional border frame
       └─ div[role=separator]    ← PAINTED line stroke
```

### `cross` (unwrapped)

```text
button[data-adflipr-popup-close][data-adflipr-block-id]  ← identity + painted + behavior (same element)
```

### `floating-image` (unwrapped)

```text
div[data-adflipr-block-id][data-block-type=floating-image]  ← identity + positioned layout shell
  └─ img / a>img                                            ← PAINTED media box
```

### `product`

```text
[data-adflipr-block-id]          ← identity + layout
  └─ product shell/grid/card/... ← multiple PAINTED sub-owners (card, image, button)
```

---

## E. Property ownership matrix (responsive traits)

Legend: **Same owner?** — desktop vs mobile emission target.

| Block family | Property | Desktop source | Desktop target | Mobile source | Mobile target | Same? | Mobile result today |
| ------------ | -------- | -------------- | -------------- | ------------- | ------------- | ----- | ------------------- |
| **button** | padding | `getButtonStyle` inline | `<button>` | `buildBlockTraitResponsiveCss` | wrapper | **NO — split** | Painted stays desktop (P0) |
| **button** | margin | `getExportWrapperStyleFromLayout` inline | wrapper | `buildBlockTraitResponsiveCss` | wrapper | YES | Inline beats @media (P0) |
| **button** | border-radius | `getButtonStyle` inline | `<button>` | `buildBlockTraitResponsiveCss` | wrapper | **NO — split** | Painted stays desktop |
| **button** | font-size | `getButtonStyle` inline | `<button>` | `buildBlockTraitResponsiveCss` | wrapper | **NO — split** | Painted stays desktop |
| **text** | padding | `getTextBlockContentStyle` inline | inner `div` | `buildBlockTraitResponsiveCss` | wrapper | **NO — split** | Inner keeps desktop |
| **text** | margin | wrapper inline | wrapper | `buildBlockTraitResponsiveCss` | wrapper | YES | Inline beats @media |
| **input*** | padding/border/radius | `inputOutlineStyle` inline | outline `div` | `buildBlockTraitResponsiveCss` | wrapper | **NO — split** | Shell keeps desktop |
| **input*** | font-size | inner input inline | `input`/`select` | `buildBlockTraitResponsiveCss` | wrapper | **NO — split** | Inner keeps desktop |
| **image** | size/radius | `buildImageBlockStyle` inline | `img` | (no mobile trait path for img) | — | N/A | — |
| **cross** | top/right margin | `serializeCross` inline always desktop | `<button>` | `buildCrossFloatingImageResponsiveCss` | same button | YES | Inline beats @media (P0) |
| **floating-image** | bottom/left margin | wrap inline desktop | positioned `div` | `buildCrossFloatingImageResponsiveCss` | same div | YES | Inline beats @media (P0) |
| **product** | card layout | inline + product CSS | card/shell nodes | `buildProduct*ResponsiveCss` | product attrs | PARTIAL | Controls pass (!important) |
| **hide-on-device** | display | data attrs | wrapper | `buildBlockDeviceVisibilityCss` | wrapper | YES | Passes (!important) |

\*All field blocks sharing `serializeTextInputLike` / outline pattern.

---

## F. Split-target defect matrix

| Block | Property | Desktop element | Mobile element | Paints UI? | Severity |
| ----- | -------- | --------------- | -------------- | ---------- | -------- |
| **button** | padding | `<button>` | wrapper `[data-adflipr-block-id]` | **button** | **P0** |
| **button** | border-radius | `<button>` | wrapper | **button** | **P0** |
| **button** | font-size | `<button>` | wrapper | **button** | **P0** |
| **text** | padding | inner content `div` | wrapper | **inner div** | **P0** |
| **text-input** (+ email, textarea, dropdown, date) | padding, border, radius | outline shell `div` | wrapper | **outline div** | **P0** |
| **text-input** (+…) | font-size | `input`/`select` | wrapper | **input** | **P0** |

### Same-element cascade failures (not split-target, still P0)

| Block | Property | Element | Cause | Severity |
| ----- | -------- | ------- | ----- | -------- |
| button | margin | wrapper | desktop inline beats mobile `@media` | P0 |
| text | margin | wrapper | same | P0 |
| cross | top/right | close button | `serializeCross` always emits desktop margin inline | P0 |
| floating-image | bottom/left | positioned div | desktop inline always | P0 |
| teaser | padding/radius | teaser root | inline + embedded (Phase 5+ scope) | P0 |

### Known `block-padding` dissection

1. **Desktop `padding:20px` on:** `[data-adflipr-block-id="btn-1"] button` (`getButtonStyle` → `serializeButton`).
2. **Mobile `padding:8px` selector:** `form[…] [data-adflipr-block-id="btn-1"]` (`buildBlockTraitResponsiveCss`).
3. **Visible button box:** inner `<button>` background/border/padding — not the wrapper.
4. **Why mobile fails:** rule applies to wrapper; painted padding is on descendant; even if same element, inline beats non-`!important` `@media`.
5. **Margin/border-radius on button:** margin same-element PCL-008; border-radius **split-target** like padding.
6. **Shared path:** all types using `buildBlockTraitResponsiveCss` + inner inline presentation (`button`, `text`, inputs, etc.).

**Confirmed split-target count:** **6 property×family pairs** above (P0); more families share the input shell pattern.

---

## G. Block-family classification

| Family | Block types | Identity | Layout | Painted | Content | Behavior |
| ------ | ----------- | -------- | ------ | ------- | ------- | -------- |
| **B — wrapper layout, inner native painted** | `button` | wrapper | wrapper | `<button>` | button label | `<button>` |
| **B2 — wrapper layout, inner div painted** | `text` | wrapper | wrapper | inner `div` | same | — |
| **B3 — wrapper layout, outline shell painted** | inputs, textarea, dropdown, date | wrapper | wrapper | outline `div` | input/select | input/select |
| **C — multi painted children** | `product`, `feedback`, `coupon`, `social` | wrapper | wrapper | cards/buttons/icons | mixed | mixed |
| **D — media on replaced element** | `image`, `video` | wrapper | wrapper | `img` / video shell | — | video init |
| **D2 — positioned decorative shell** | `floating-image` | root div | root (absolute) | `img` | — | link |
| **E — identity = painted = behavior** | `cross` | close `button` | same (absolute) | same | glyph | same |
| **F — runtime-mutated** | `spinner`, `timer` | wrapper / attrs | wrapper | canvas/DOM | — | initializer |
| **G — line geometry** | `divider` | wrapper | wrapper | inner separator | — | — |

### Can one block CSS compiler target all types?

```text
NO
```

Evidence: at least four distinct painted-target strategies (native button, content div, input outline, replaced media) plus unwrapped positioned blocks. A single `[data-adflipr-block-id]` selector block in `renderCss` cannot address the painted element without per-family descendant selectors.

---

## H. Stable identity audit

| Block family | Stable id? | Serialized attr | Unique across steps? | Runtime uses? | Safe artifact CSS namespace? |
| ------------ | ---------- | --------------- | -------------------- | ------------- | ---------------------------- |
| Wrapped content | Yes | `data-adflipr-block-id="{element.id}"` | Yes per popup model | spinners, coupons, etc. | **Yes** with `[data-adflipr-popup-artifact="{scope}"]` prefix |
| cross / floating | Yes | on root element | Yes | close / decorative | Yes |
| spinner | Yes | block id + spinner attrs | Yes | spinner.ts | Yes |
| product | Yes | block id + product attrs | Yes | — | Yes |

**Challenges (acceptable):**

- Duplicate block ids across steps: model uses same `FormElement.id` namespace; steps are separate HTML mounts — scope by step form, not cross-step collision in one DOM.
- Template import / duplication: new popup ids assigned on import; block ids preserved in builderState — **stable within artifact**.
- Identity on wrapper while paint on inner: **must compile descendant selectors** — artifact scope on form root is insufficient alone.

---

## I. `buildBlockTraitResponsiveCss()` verdict

| Generated rule | Selector | Families | Properties | Correct owner? | Split risk |
| -------------- | -------- | -------- | ---------- | -------------- | ---------- |
| Block traits | `form[…] [data-adflipr-block-id="…"]` | 19 wrapped | padding, margin, radius, font-size | **No** for padding/radius/font on most | **High** |
| Cross/floating margins | close btn / floating root | 2 | top/right/bottom/left | Yes (same element) | Low (PCL-008 only) |
| Device visibility | hide attrs on wrapper | all | display | Yes | Low (`!important`) |
| Product horizontal/grid | product data attrs | product | layout | Yes | Low (`!important`) |
| Phone country select | phone block select | phone | max-width | Yes | Low (`!important`) |

### Answers

1. **Mixes unrelated DOM owners?** Yes — one selector for wrapper; inner painted targets differ.
2. **Assumes wrapper is painted?** Yes — implicit in selector choice.
3. **Rules for properties declared elsewhere?** Yes — padding/radius/font on inner elements.
4. **Relies on `!important`?** No (block traits); product/visibility do.
5. **Passing accidentally?** hide-on-mobile / product layout pass via `!important` or layout mechanics — not block traits.
6. **Migrate as one unit?** **No.**

```text
DELETE AND REBUILD FROM DOMAIN OWNERSHIP
```

Split into per-family (or per painted-target-role) compilers; do not hoist `buildBlockTraitResponsiveCss` into `renderCss` as-is.

---

## J. Inline presentation inventory (block scope)

Excluded migrated owners: form surface, outer layout, columns, column presentation, gamification shell.

| Block family | Inline properties | Target element | Merchant? | Responsive counterpart? | renderCss candidate? |
| ------------ | ----------------- | -------------- | --------- | ----------------------- | -------------------- |
| button | padding, bg, border, radius, font, shadow | `<button>` | Yes | embedded wrapper rules (wrong target) | **A** (after owner fix) |
| text | padding, bg, border, radius, typography | inner `div` | Yes | embedded wrapper rules | **A** |
| inputs | shell border/bg/radius/height; input typography | outline + input | Yes | embedded wrapper + floating label sheet | **A** / **D** for native |
| image | width/height/radius | `img` | Yes | limited | **A** |
| divider | frame + line stroke | outer + inner | Yes | — | **A** |
| product | card/shell/button styles | multiple | Yes | product embedded CSS | **A** (family compiler) |
| cross | margin, padding, colors | close `button` | Yes | cross floating CSS | **A** |
| floating-image | position margins | root div | Yes | cross floating CSS | **A** |
| spinner/timer/video | structural + config attrs | mixed | Partial | gamification/timer paths | **B/C/E** |

### Classification

| Class | Meaning | Examples |
| ----- | ------- | -------- |
| **A** | Merchant presentation → future artifact `renderCss` on correct painted owner | button padding, text padding |
| **B** | Structural DOM requirement | flex wrappers, phone row layout |
| **C** | Runtime state | spinner canvas, timer digits |
| **D** | Native control requirement | input fixed inner padding, select appearance |
| **E** | Unclear / compound | product grid mechanics |

---

## K. Embedded block CSS inventory

| Generator | Block families | DOM owner | Type | Future phase |
| --------- | -------------- | --------- | ---- | ------------ |
| `buildBlockTraitResponsiveCss` | 19 wrapped | **wrapper (wrong for many)** | presentation | **Rebuild** — Phase 5B+ |
| `buildBlockDeviceVisibilityCss` | all with hide attrs | wrapper | responsive layout | Phase 5+ or keep embedded |
| `buildCrossFloatingImageResponsiveCss` | cross, floating-image | root/close node | presentation | Phase 5D (positioned shells) |
| `buildPhoneInputCountrySelectResponsiveCss` | phone-input | country `<select>` | native behavior | Keep separate |
| `buildProductHorizontalResponsiveCss` | product | product card attrs | responsive layout | Phase 5 product slice |
| `buildProductGridResponsiveCss` | product | product shell | responsive layout | same |
| `POPUP_FLOATING_LABEL_EXPORT_CSS` | inputs | label/input pseudo | behavior + presentation | Keep separate |
| `POPUP_RADIO_EXPORT_CSS` | radio | radio controls | presentation/behavior | Phase 5 radio slice |
| `POPUP_ENTRANCE_EXPORT_CSS` | form | animation | behavior | Keep separate |
| `buildRichTextLinkExportCss` | RTE blocks | `a` in rich text | content | Phase 5 text slice |
| custom CSS / teaser | form / teaser | form/teaser root | merchant custom | Later phases |

---

## L. Browser evidence

**Tests:**

```bash
cd adflipr-app
npx craco test --watchAll=false --testPathPattern="blockDomOwnership.browser|responsiveCssBaseline.browser"
# → 2 suites, 29 tests passed
```

### Phase 5A ownership (`blockDomOwnership.browser.test.ts`)

| Case | Wrapper desktop | Painted desktop | Wrapper mobile | Painted mobile | Verdict |
| ---- | --------------- | --------------- | -------------- | -------------- | ------- |
| button padding | `0px` | `20px` | `8px` | **`20px`** (unchanged) | **Split-target P0** — mobile rule hits wrapper, UI on button |
| button margin | `24px` | — | **`24px`** | — | **Same-element P0** — inline beats @media |
| text padding | `0px` | `16px` | `4px` | **`16px`** | **Split-target P0** |

### Phase 0 scoreboard (verified live)

| Metric | Value |
| ------ | ----: |
| Risk cases total | 18 |
| Risk green (migrated layout/surface/column) | 10 |
| Risk red (unmigrated) | **8** |
| Control green | 5 |

**Still red:** `block-padding`, `block-margin`, `cross-top`, `cross-right`, `floating-bottom`, `floating-left`, `teaser-padding`, `teaser-border-radius`.

(`block-border-radius` baseline case targets **column** presentation — green after Phase 4B; not a content block trait.)

---

## M. Migration architecture recommendation

| Option | Correctness | Responsive safety | Maintainability @20 blocks | @50 blocks | Runtime coupling |
| ------ | ----------- | ----------------- | -------------------------- | ---------- | ---------------- |
| **A — one universal `compileBlockRenderCss`** | Poor | Poor | Low (false abstraction) | Worse | Low |
| **B — block-family compilers** | **High** | **High** | **Good** | **Good** | Medium |
| **C — owner-role compilers** | High | High | Medium (indirection) | Good | Medium |
| **D — keep embedded patch** | Poor | Poor | Poor | Poor | Low |

**Recommendation:** **Option B — block-family compilers** (with shared CSS utils, not shared selectors).

Each compiler targets the **actual painted owner** for that family:

```text
compileButtonPresentationRenderCss()
compileTextPresentationRenderCss()
compileInputShellPresentationRenderCss()
compileMediaPresentationRenderCss()
compilePositionedDecorativeRenderCss()  // cross, floating-image
compileProductPresentationRenderCss()
```

Owner-role utilities (`artifactCssUtils`) remain shared; selector targets are not.

---

## N. First implementation slice — Phase 5B

**Recommended:** `Phase 5B — Button painted-element presentation`

| Candidate | Owner clarity | Responsive failure | Runtime coupling | Shared path size | Recommended? |
| --------- | ------------- | ------------------ | ---------------- | ---------------- | ------------ |
| **button** | **High** — single `<button>` painted | **block-padding** split + margin PCL-008 | **None** (no embed init) | Medium | **Yes** |
| text | Medium — inner div | split padding | low | shares trait builder | Second |
| image | High — `img` | no baseline red | low | separate inline builders | Later |
| divider | Medium — line inner | no trait red | none | small | Later |
| text-input | Low — shell + input | split + floating labels | floating-label init | large shared input path | Later |

**Phase 5B scope (exact):**

```text
Block type: button only
Properties: padding, margin, border-radius, font-size, background, border, box-shadow, width/fit
Owners: compile rules onto [artifact] [data-adflipr-block-id] button (and align container if needed)
Remove: button inner inline presentation from serializeButton / getButtonStyle export path
Remove: button rows from buildBlockTraitResponsiveCss
Tests: block-padding + block-margin green; blockDomOwnership button cases green
```

---

## O. Cleanup map (future — not executed in 5A)

| Code | Current owner | Replacement | Deletion phase |
| ---- | ------------- | ----------- | -------------- |
| `getButtonStyle` export inline | button serializer | `compileButtonPresentationRenderCss` | **Phase 5B** |
| `buildBlockTraitResponsiveCss` button branches | embedded | removed per family | **DELETE WITH FIRST OWNER** → fully after all families |
| `buildCrossFloatingImageResponsiveCss` | embedded | positioned compiler | Phase 5D |
| `inputOutlineStyle` inline traits | input serializers | input shell compiler | Phase 5C+ |
| Baseline tests expecting red for button | tests | update to green | Phase 5B |
| `RENDER_CSS_MIGRATED_RISK_IDS` in baseline | test harness | add block ids when migrated | per slice |

---

## P. Phase 0 impact forecast

| After migration slice | Cases green | Still red |
| --------------------- | ----------- | --------- |
| **Current** | 10 + 5 controls | 8 risk |
| **Phase 5B (button)** | +2 (`block-padding`, `block-margin`) | 6 |
| **+ text** | +0 baseline* | — |
| **+ cross/floating compilers** | +4 | 2 (teaser) |
| **+ teaser compiler** | +2 | 0 block/teaser risk |

\*Text has no dedicated Phase 0 risk row today; would need new baseline case.

---

## Q. Test evidence

```bash
cd adflipr-app
npx craco test --watchAll=false --testPathPattern="blockDomOwnership.browser|responsiveCssBaseline.browser"
```

| Suite | Result |
| ----- | ------ |
| `blockDomOwnership.browser.test.ts` | 4 passed |
| `responsiveCssBaseline.browser.test.ts` | 25 passed (8 risk red by design, 10 migrated green, 5 controls) |

---

## R. No-change verification

| Requirement | Status |
| ----------- | ------ |
| No production CSS migrated | ✓ |
| No renderCss block compiler added | ✓ |
| No inline block styles removed | ✓ |
| No embedded block CSS removed | ✓ |
| No `!important` added | ✓ |
| No Phase 5B implementation started | ✓ |

---

## Required final recommendation

```text
Phase 5A verdict:
NO — block CSS is one migration owner.

Primary architectural finding:
Wrapped blocks share a common identity wrapper, but merchant presentation is emitted on inner painted
elements (button, text div, input outline, img) while buildBlockTraitResponsiveCss targets the wrapper.
Moving those embedded rules into renderCss without retargeting would preserve the defect.

Confirmed split-target defects:
6 confirmed P0 pairs (button padding/radius/font-size; text padding; input shell padding/radius and input font-size) plus same-element PCL-008 failures on wrapper margin and positioned shells.

Recommended compiler architecture:
Option B — block-family compilers with painted-owner selectors (shared artifactCssUtils only).

Recommended first implementation slice:
Phase 5B — Button painted-element presentation (padding, margin, border-radius, typography, background, border, shadow on <button>).

Code to delete during that slice:
getButtonStyle inline export usage; button-specific rules in buildBlockTraitResponsiveCss; red baseline expectations for block-padding and block-margin.

Expected Phase 0 scoreboard change:
8 risk red → 6 risk red (block-padding, block-margin green).

Ready for Phase 5B:
YES
```
