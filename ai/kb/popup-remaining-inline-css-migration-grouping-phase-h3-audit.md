# Phase H3 — Remaining Inline Merchant CSS Migration Grouping Audit

**Date:** 2026-07-06  
**Mode:** READ-ONLY AUDIT — no production code or test changes  
**Scope:** Post–Phase 6G inventory, DOM topology grouping, migration batch design for all remaining inline/embedded merchant CSS debt  
**References:** Phase H2 audit, Phase 6F (Date), Phase 6G (Button/Cross hover + Radio renderCss), `popup-rendering-css-architecture.md`, `popup-checkbox-architecture-migration-readiness-phase-h3-audit.md` (checkbox deep-dive)

---

## 1. Executive verdict

After Phase 6F (Date) and Phase 6G (Radio merchant presentation + Button/Cross hover), **10 block families** still export merchant presentation via **serializer inline CSS** and/or **generic `buildBlockTraitResponsiveCss`**. They do **not** share one DOM topology or one normalization domain. Forcing a single mega-compiler would hide ownership and increase regression risk.

**Recommended strategy:** **8 implementation batches** (10 families; Image+Video share one phase with separate compilers). **First batch: `check-box`** — highest architecture-progress-to-risk ratio (Radio template, shared choice-group resolvers in `ElementStyles`, dedicated native-control sheet pattern proven).

**Verdict: ACCEPT**

---

## 2. Audit-only compliance statement

- No production code modified  
- No tests modified  
- No family migrated  
- Inventory rebuilt from current `compilePopupRenderCss`, `MIGRATED_BLOCK_TRAIT_TYPES`, serializers, and `buildEmbeddedStepResponsiveCss` (July 2026 HEAD)

---

## 3. Current remaining-family inventory

| Family | Desktop merchant source | Mobile merchant source | State merchant source | Dedicated renderCss compiler | Remaining debt class |
|--------|-------------------------|------------------------|----------------------|------------------------------|----------------------|
| `divider` | Serializer inline (`buildDividerOuterStyle` / inner line) + wrapper inline margin/width | Generic `buildBlockTraitResponsiveCss` → wrapper | None merchant | **No** | **INLINE + GENERIC MOBILE** |
| `check-box` | Serializer inline (group, labels, `accent-color`) + wrapper inline | Generic → wrapper (**split-target defect**) | `accent-color` / checked (native) | **No** | **INLINE + GENERIC MOBILE** |
| `timer` | Serializer inline (container + units + typography) + wrapper inline | Generic → wrapper | Expired/active display (runtime HTML) | **No** | **INLINE + GENERIC MOBILE** |
| `spinner` | Serializer inline (layout + canvas host) + wrapper inline | Generic → wrapper | Win/result overlay (fixed product styles) | **No** | **INLINE + GENERIC MOBILE** (+ fixed inline mechanics) |
| `image` | Serializer inline (container + `img`) + wrapper inline | Generic → wrapper (**split-target**) | None | **No** | **INLINE + GENERIC MOBILE** |
| `video` | Serializer inline (container + `video`/`iframe`) + wrapper inline | Generic → wrapper (**split-target**) | None | **No** | **INLINE + GENERIC MOBILE** |
| `product` | Serializer inline (per-card descendants) + wrapper inline | Generic → wrapper + **family embedded** (`buildProductHorizontalResponsiveCss`, `buildProductGridResponsiveCss`) | None merchant (sale styling fixed constants) | **No** | **INLINE + GENERIC MOBILE + FAMILY EMBEDDED** |
| `coupon` | Serializer inline (`buildCouponContainerStyle`, code span) + wrapper inline | Generic → wrapper (**split-target**) | None | **No** | **INLINE + GENERIC MOBILE** |
| `feedback` | Serializer inline (container, options, `accent-color` on NPS) + wrapper inline | Generic → wrapper (**split-target**) | `activeColor` / `inactiveColor` (partial export) | **No** | **INLINE + GENERIC MOBILE** |
| `social` | Serializer inline (container + per-icon `img`) + wrapper inline | Generic → wrapper (**split-target** for padding) | None | **No** | **INLINE + GENERIC MOBILE** |

**Families with NO remaining merchant CSS debt (post-6G):**  
`cross`, `floating-image`, `button`, `text`, `text-input`, `email-input`, `textarea`, `dropdown`, `phone-input`, `date`, `radio`, plus layout/surface/teaser/gamification shell compilers.

---

## 4. Corrected differences from H2

| H2 claim | Current truth |
|----------|---------------|
| ~12 unmigrated inline families | **10** remain (`date` and `radio` migrated) |
| `date` critical renderCss gap | **Closed** — Phase 6F (`standardFieldPresentation` + `MIGRATED_BLOCK_TRAIT_TYPES`) |
| `radio` inline + generic mobile | **Closed** — Phase 6G Radio (`radioPresentation.ts` + `radio.export.css` retained) |
| Button/Cross hover not in renderCss | **Closed** — Phase 6G Button/Cross state (`buttonPresentation` / `positionedDecorativePresentation` `:hover`) |
| `MIGRATED_BLOCK_TRAIT_TYPES` size | **11 types** (added `date`, `radio` since H2) |
| Checkbox grouped with radio as next option family | Still valid; **radio done** → checkbox is natural follow-on |

---

## 5. Per-family current CSS source matrix

See §3. Additional notes:

- **Wrapper inline** for all 10: `serializeElements` → `getExportWrapperStyleFromLayout` (margin, width, align-self) except families with special timer wrapper handling.  
- **Generic mobile** scope: ``form[data-adflipr-popup-export="true"] [data-adflipr-block-id="{id}"]`` — emits `padding`, `margin`, `border-radius`, `font-size` when desktop/mobile traits differ.  
- **Product-only embedded merchant/layout:** horizontal card stack + grid column collapse (not generic block trait).

---

## 6. Exact DOM topology inventory

Representative serialized shapes (from serializers + `serializeElements` wrapper):

### `divider`

```html
<div data-block-type="divider" data-adflipr-block-id="{id}" style="{wrapper margin/width}">
  <div style="{outer shell: padding,bg,frame border,shadow}">
    <div role="separator" style="{line: border-top width/color}"></div>
  </div>
</div>
```

### `check-box`

```html
<div data-block-type="check-box" data-adflipr-block-id="{id}" style="{wrapper}">
  <label for="{name}">{labelText}</label>
  <div style="{group shell + flex layout}">
    <div style="{row flex}">
      <input type="checkbox" style="accent-color:{fill}" />
      <label style="{option typography}">{text}</label>
    </div> × N
  </div>
</div>
```

### `timer`

```html
<div data-block-type="timer" data-adflipr-block-id="{id}" style="{wrapper}">
  <div data-timer-full-width="…" style="{container chrome}">
    <div data-timer-units="true" style="{flex row}">
      <!-- card or simple units: nested spans/divs with digit/label colors -->
    </div>
  </div>
</div>
```

### `spinner`

```html
<div data-block-type="spinner" data-adflipr-block-id="{id}" style="{wrapper}">
  <div style="{center}">
    <div data-adflipr-popup-spinner="true" data-adflipr-popup-spinner-config="…" style="{wheel layout}">
      <canvas … style="{fixed canvas box}"/>
      <div data-adflipr-popup-spinner-result="true" style="{overlay}">…</div>
    </div>
  </div>
</div>
```

### `image`

```html
<div data-block-type="image" data-adflipr-block-id="{id}" style="{wrapper}">
  <label>…</label>
  <div style="{container: bg,padding,shadow}">
    <a?><img style="{border,radius,dimensions,margin}"></a?>
  </div>
</div>
```

### `video`

```html
<div data-block-type="video" data-adflipr-block-id="{id}" style="{wrapper}">
  <div style="{container}">
    <div style="{border box / aspect box}"><iframe|video style="{…}"></div>
  </div>
</div>
```

### `product`

```html
<div data-block-type="product" data-adflipr-block-id="{id}" style="{wrapper}">
  <div data-adflipr-product-shell="true" style="{grid shell}">
    <div data-adflipr-product-card="true" data-adflipr-product-slot="…" style="{card}">
      <img data-adflipr-product-image="true" …>
      <div data-adflipr-product-body="true">
        <p data-adflipr-product-name="true" …>
        <div data-adflipr-product-price="true" …>
        <button data-adflipr-product-button="true" …>
      </div>
    </div> × slots
  </div>
</div>
```

### `coupon`

```html
<div data-block-type="coupon" data-adflipr-block-id="{id}" style="{wrapper}">
  <div data-adflipr-popup-coupon="true" style="{shell}">
    <div style="{row}">
      <span data-adflipr-popup-coupon-code-text="true" style="{code typography}">…</span>
      <button data-adflipr-popup-coupon-copy="true" style="{fixed copy btn}">Copy</button>
    </div>
  </div>
</div>
```

### `feedback`

```html
<div data-block-type="feedback" style="{container padding,bg,…}">
  <p style="{question}">…</p>
  <div role="radiogroup" style="{options row}">
    <label style="{option}"><input type="radio" style="accent-color|display:none">…</label> × N
  </div>
  …
</div>
```

### `social`

```html
<div data-block-type="social" data-adflipr-block-id="{id}" style="{wrapper}">
  <div class="pbn-social-container" style="{flex,gap,padding,margin}">
    <a><img style="{icon size,radius}"></a> × active links
  </div>
</div>
```

---

## 7. DOM ownership matrix

| Family | Identity owner | Layout owner | Painted owner | Content/value | State owner | Behavior |
|--------|----------------|--------------|---------------|---------------|-------------|----------|
| divider | `[block-id]` wrapper | wrapper + outer shell | `role=separator` line | — | — | separator semantics |
| check-box | wrapper | group `div` | option `label`, `input` accent | option text | native `:checked` | `name`/`value`, validation |
| timer | wrapper | container | unit digits/labels/cards | countdown values (export snapshot) | expired branch HTML | `data-timer-*`, embed runtime |
| spinner | wrapper | spinner root | canvas (runtime draw) | slice config JSON | result overlay | spin runtime, attrs |
| image | wrapper | container `div` | `img` (+ optional `a`) | `src`/`alt` | — | link `href` |
| video | wrapper | container + aspect box | `video`/`iframe` | `src` | — | playback attrs |
| product | wrapper | product shell grid | card descendants (img, name, price, btn) | product data | — | slot attrs, layout attrs |
| coupon | wrapper | coupon shell | code `span` | code string | — | copy button attrs |
| feedback | container (also `data-block-type`) | radiogroup | option labels/inputs | question text | selection (runtime) | `name`/`value` |
| social | wrapper | `.pbn-social-container` | per-link `img` | URLs | — | `href`/`target` |

---

## 8. Merchant trait ownership matrix (summary)

| Family | Key traits | Persisted | Desktop owner | Mobile owner | State |
|--------|------------|-----------|---------------|--------------|-------|
| divider | padding, frame border, line color/width/style, dividerWidth, elementPosition, margin, shadow | `style.*` | outer shell + line | wrapper (generic) — **split** | — |
| check-box | group chrome, layout, gap, fill/outline/bg colors, label, option typography, margin | `style.*` | group + labels + input | wrapper (generic) — **split** | `fillColor` → accent |
| timer | bg, border, radius, shadow, padding, typography colors, card style, unit layout, fit scale | `style.*` | container + descendants | wrapper (generic) — **split** | expired UI |
| spinner | wheel size, pin, colors (in config) | properties + `style` | root + canvas box | wrapper | result overlay |
| image | dimensions, radius, border, shadow, padding, bg, position, margin | `style.*` + `imageUrl` | container + `img` | wrapper — **split** | — |
| video | same media traits + dimensions | `style.*` + `videoUrl` | container + media node | wrapper — **split** | — |
| product | grid, typography substyles, button style, image size, padding, margin | `style.*` + product data | many descendants | wrapper + embedded grid rules | — |
| coupon | shell chrome, code typography, height, margin | `style.*` | shell + code span | wrapper — **split** | — |
| feedback | active/inactive colors, padding, typography | `style.*` + content props | container + options | wrapper — **split** | accent/hidden radios |
| social | icon size, gap, line spacing, icon style, padding, margin, align | `style.*` + links | container + `img` | wrapper — **split** | — |

---

## 9. Canonical normalizer inventory

| Family | Canvas normalizer | Serializer normalizer | Mobile normalizer | Shared today? |
|--------|-------------------|----------------------|-------------------|---------------|
| divider | `buildDividerOuterShellStyle` (ElementStyles) | same via `buildDividerOuterStyle` | generic wrapper | **CANONICAL SHARED** (outer shell) |
| check-box | `getChoiceGroupShellStyle`, `buildChoiceOptionLabelStyle` | same + inline assembly | generic wrapper | **CANONICAL SHARED** (group/label); serializer duplicates assembly |
| timer | `timerFixedWidthFit` + inline CountdownTimer styles | serializer-local rules | generic wrapper | **SERIALIZER-ONLY** + partial shared padding/radius helpers |
| spinner | canvas + contract config | `buildSpinnerRootStyle` etc. | generic wrapper | **SERIALIZER-ONLY** |
| image | `getElementStyle` + canvas | `buildImageBlockStyle` / `buildImageContainerStyle` | generic wrapper | **CANVAS/SERIALIZER DUPLICATION** via shared ElementStyles helpers |
| video | canvas inline | serializer + `traitBorderProps` | generic wrapper | **CANVAS/SERIALIZER DUPLICATION** |
| product | canvas + `buildPbnProduct*` helpers | `commerceAndCrossStyles` | generic + product embedded | **SERIALIZER-ONLY** heavy |
| coupon | `buildCouponShellStyle` (ElementStyles) | `buildCouponContainerStyle` | generic wrapper | **CANONICAL SHARED** (shell) |
| feedback | FeedbackCanvas inline builders | `mediaAndFeedbackStyles` | generic wrapper | **SERIALIZER-ONLY** |
| social | SocialCanvas inline | serializer-local | generic wrapper | **SERIALIZER-ONLY** |

---

## 10. Serializer architecture matrix

| Family | Serializer entry | Inline helper location | Structure mixed with presentation? | Cleanup complexity |
|--------|------------------|------------------------|--------------------------------------|-------------------|
| divider | `divider/serialize.ts` | `blockStyles.ts` + ElementStyles | Yes — outer+line in one pass | **LOW** |
| check-box | `check-box/serialize.ts` | `blockStyles` + ElementStyles | Yes — group+rows+labels | **MEDIUM** (native sheet) |
| timer | `timer/serialize.ts` | serializer-local (~300 LOC) | Yes — data-dependent unit HTML | **HIGH** |
| spinner | `spinner/serialize.ts` | `spinnerStyles.ts` | Yes — config + fixed mechanics | **HIGH** (runtime) |
| image | `image/serialize.ts` | `mediaAndFeedbackStyles.ts` | Yes — container+img | **MEDIUM** |
| video | `video/serialize.ts` | serializer + ElementStyles | Yes — embed branch | **MEDIUM** |
| product | `product/serialize.ts` | `commerceAndCrossStyles.ts` | Yes — many descendants | **HIGH** |
| coupon | `coupon/serialize.ts` | `commerceAndCrossStyles.ts` | Partial — copy btn fixed | **LOW–MEDIUM** |
| feedback | `feedback/serialize.ts` | `mediaAndFeedbackStyles.ts` | Yes — type branches | **MEDIUM–HIGH** |
| social | `social/serialize.ts` | serializer-local | Yes — per-link loop | **MEDIUM** |

---

## 11. Generic responsive CSS consumer inventory

`buildBlockTraitResponsiveCss` iterates elements where `!MIGRATED_BLOCK_TRAIT_TYPES.has(type)`:

**Current consumers (10 families):**  
`divider`, `check-box`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social`

**Properties emitted:** `padding`, `margin`, `border-radius`, `font-size` (when desktop/mobile differ)

**Selector:** ``form[data-adflipr-popup-export="true"] [data-adflipr-block-id="{id}"]``

---

## 12. Mobile target analysis

| Family | Mobile props | Generic target | Desktop painted target | Classification |
|--------|--------------|----------------|------------------------|----------------|
| divider | padding, margin, radius | wrapper | outer shell (padding) | **SPLIT TARGET** |
| check-box | padding, margin, radius, font-size | wrapper | group `div` | **SPLIT TARGET** |
| timer | padding, margin, radius, font-size | wrapper | container `div` | **SPLIT TARGET** |
| spinner | margin, padding, … | wrapper | inner spinner root | **SPLIT TARGET** |
| image | padding, margin, radius, font-size | wrapper | container `div` | **SPLIT TARGET** |
| video | same | wrapper | container `div` | **SPLIT TARGET** |
| product | padding, margin, radius, font-size | wrapper | shell/cards | **SPLIT TARGET** + **DESCENDANT-SPECIFIC** embedded |
| coupon | padding, margin, radius | wrapper | coupon shell | **SPLIT TARGET** |
| feedback | padding, margin, radius, font-size | wrapper | root container | **SPLIT TARGET** |
| social | padding, margin | wrapper | `.pbn-social-container` | **SPLIT TARGET** |

**None** of the 10 should keep generic mobile rules after migration — each needs family `renderCss` with correct owners.

---

## 13. Responsive strategy matrix

| Family | Strategy | Reason |
|--------|----------|--------|
| divider | **DIFF-ONLY** (post-migration) | Same owners once shell/line rules in renderCss; mobile padding must move to shell owner |
| check-box | **DIFF-ONLY** + **DESCENDANT-SPECIFIC** | Wrapper/group/label/input owners stable; follow radio pattern |
| timer | **FULL MOBILE RECONSTRUCTION** | `resolveTimerExportFitScale` rescales typography/spacing per device |
| spinner | **REQUIRES FURTHER PROOF** | Mostly fixed mechanics; merchant traits in JSON — may be presentation-only on root |
| image | **DIFF-ONLY** + **DESCENDANT-SPECIFIC** | Container vs `img` owners |
| video | **DIFF-ONLY** + **DESCENDANT-SPECIFIC** | Container vs media/iframe; embed aspect box |
| product | **FULL MOBILE RECONSTRUCTION** + **DESCENDANT-SPECIFIC** | Grid columns, horizontal stack embedded rules |
| coupon | **DIFF-ONLY** | Single shell owner (`buildCouponShellStyle` shared) |
| feedback | **DIFF-ONLY** + **STATE-SPECIFIC** | Option colors; type-specific branches |
| social | **DIFF-ONLY** | Container + per-icon rules |

---

## 14. Stateful / stateless classification

| Family | States | Merchant-configurable? | Current export | Future owner |
|--------|--------|------------------------|----------------|--------------|
| divider | — | No | — | — |
| check-box | checked | Yes (`fillColor`, dead outline/bg traits) | `accent-color`, `checked` attr | renderCss vars + `checkbox.export.css` |
| timer | expired | Partial (endMessage/CTA colors) | inline expired branch | renderCss base; runtime updates embed |
| spinner | spin result | Fixed overlay styles | inline constants | retain structure sheet; merchant wheel colors in renderCss if exposed |
| image | — | No | — | — |
| video | — | No | — | — |
| product | — | No | — | — |
| coupon | — | No | — | — |
| feedback | selected/hover (canvas) | Yes (`activeColor`, `inactiveColor`) | partial (`accent-color` NPS only) | renderCss `:checked` / custom classes per type |
| social | — | No | — | — |

**State-specific compilers warranted:** `check-box`, `feedback` (not grouped together).

---

## 15. Repeated-item classification

| Family | Repeated items? | Per-item style? | Block-level style? | Stable item identity? |
|--------|-----------------|-----------------|--------------------|-----------------------|
| check-box | Yes (options) | Shared typography | Group chrome | `option.id` in `id` attr |
| product | Yes (slots/cards) | Per-slot product data | Grid/shell traits | `data-adflipr-product-slot` |
| feedback | Yes (rating options) | Shared option style | Container traits | generated `id` per option |
| social | Yes (links) | Shared icon size/radius | Container gap/align | social network key |
| timer | Yes (units) | Shared digit/label styles | Container traits | unit keys in data attrs |
| Others | No / N/A | — | — | — |

---

## 16. Data-dependent presentation analysis

| Family | Presentation-only normalization? | Data-dependent CSS? | Conflict-key complexity |
|--------|----------------------------------|---------------------|-------------------------|
| divider | Yes | Line width % only | Low |
| check-box | Yes | Option count → HTML only | Medium (options are content) |
| timer | Partial | Visible units, expired branch, fit scale | High |
| spinner | No | Slice count/colors in JSON config | High |
| image | Yes | URL presence | Low |
| video | Yes | embed vs file branch | Medium |
| product | No | Slot count, products, layout mode | High |
| coupon | Yes | code string content | Low |
| feedback | Partial | `feedbackType`, `steps` | Medium |
| social | Partial | active link set | Medium |

---

## 17. Conflict semantics matrix

| Family | Presentation conflict key | Content excluded? | State included? |
|--------|---------------------------|-------------------|-----------------|
| divider | `normalizeDividerPresentation(style)` JSON | Yes | N/A |
| check-box | group+wrapper+label rules (like radio) | options[] excluded | fill/outline tokens yes |
| timer | full style + timerStyle branch | countdown target time excluded | expired styles yes |
| spinner | config.style subset | slice labels/content excluded | overlay fixed |
| image | container+img rules | src excluded | N/A |
| video | container+media rules | url excluded | N/A |
| product | shell+card typography rules | product records excluded | N/A |
| coupon | shell+code typography | code string excluded | N/A |
| feedback | container+option colors per type | question text excluded | active/inactive yes |
| social | container+icon rules | URLs excluded | N/A |

---

## 18. Legitimate embedded CSS inventory

| Source | Merchant part | Legitimate retained part |
|--------|---------------|--------------------------|
| `buildBlockTraitResponsiveCss` | mobile padding/margin/radius/font on wrapper | **Remove** when families migrated |
| `buildProductHorizontalResponsiveCss` | card layout presentation on mobile | **STRUCTURE/LAYOUT MECHANICS** until product compiler owns mobile |
| `buildProductGridResponsiveCss` | grid columns collapse | **LAYOUT MECHANICS** |
| `buildPhoneInputCountrySelectResponsiveCss` | select width | **DESCENDANT-SPECIFIC** (phone migrated; keep until moved) |
| `buildBlockDeviceVisibilityCss` | — | **BEHAVIOR** (hide flags) |
| `radio.export.css` | uses CSS vars from renderCss | **NATIVE CONTROL MECHANICS** |
| `POPUP_FLOATING_LABEL_EXPORT_CSS` | — | **STRUCTURE** |
| `POPUP_ENTRANCE_EXPORT_CSS` | — | **ANIMATION** |
| `buildRichTextLinkExportCss` | link color for button RTE | **CONTENT/RTE** (not block-family migration) |
| `resolveEmbedResponsiveExportCss` | — | **RUNTIME MECHANICS** |
| Spinner result/canvas fixed styles | product chrome | **RUNTIME/GAMIFICATION MECHANICS** |

---

## 19. Inline CSS classification (future action summary)

| Family | Merchant inline today | Structural inline OK | Future action |
|--------|----------------------|----------------------|---------------|
| divider | outer shell + line | wrapper width mechanics | **MOVE TO renderCss** |
| check-box | group, labels, accent | row flex | **MOVE TO renderCss** + sheet |
| timer | container + units | data-timer attrs | **MOVE TO renderCss** (complex) |
| spinner | wheel layout box | canvas dimensions | **PARTIAL** — merchant to renderCss; mechanics keep |
| image | container + img | link wrapper | **MOVE TO renderCss** |
| video | container + media | iframe aspect hack | **MOVE TO renderCss** |
| product | card descendants | slot attrs | **MOVE TO renderCss** |
| coupon | shell + code | copy btn defaults | **MOVE TO renderCss** (shell+code) |
| feedback | container + options | hidden inputs | **MOVE TO renderCss** |
| social | container + icons | — | **MOVE TO renderCss** |

---

## 20. Candidate migration groups

| Group | Families | Shared topology | Shared normalization | Mobile strategy | Confidence |
|-------|----------|-----------------|----------------------|-----------------|------------|
| **G1 Checkbox** | `check-box` | wrapper + option group | `getChoiceGroupShellStyle`, option label resolvers (radio-aligned) | DIFF + descendant | **HIGH** |
| **G2 Divider** | `divider` | wrapper + shell + line | `buildDividerOuterShellStyle` | DIFF | **HIGH** |
| **G3 Coupon** | `coupon` | wrapper + shell + text | `buildCouponShellStyle` | DIFF | **HIGH** |
| **G4 Media** | `image`, `video` | wrapper + container + media child | partial (`resolveDevicePaddingCss`, borders) | DIFF + descendant | **MEDIUM** |
| **G5 Social** | `social` | wrapper + flex icon list | serializer-local only | DIFF | **MEDIUM** |
| **G6 Feedback** | `feedback` | wrapper + radiogroup options | serializer-local | DIFF + state | **MEDIUM** |
| **G7 Timer** | `timer` | compound units | `timerFixedWidthFit` partial | FULL mobile | **MEDIUM** |
| **G8 Product** | `product` | repeated cards | commerce helpers | FULL + embedded | **LOW** |
| **G9 Spinner** | `spinner` | gamification canvas | config JSON | PROOF NEEDED | **LOW** |

---

## 21. Candidate 1 verdict — divider + timer + coupon

**INVALID** as one compiler / one normalization domain.

- Divider: single line painted owner  
- Coupon: shell + code typography  
- Timer: compound unit tree + device fit scale + runtime countdown  

**Verdict: INVALID**

---

## 22. Candidate 2 verdict — check-box + radio + feedback

- **Radio:** already migrated (Phase 6G)  
- **check-box + feedback:** both have options, but native mechanics and feedback rating types differ materially  

**Verdict: PARTIALLY VALID** — only **`check-box` alone** as radio-pattern follow-on; do not batch with feedback.

---

## 23. Candidate 3 verdict — image + video

Shared: container traits (padding, bg, shadow, border radius), wrapper layout, media dimensions.

Different: iframe embed aspect box, playback attrs, `object-fit` vs absolute iframe.

**Verdict: PARTIALLY VALID** — **one implementation phase**, **`imagePresentation.ts` + `videoPresentation.ts`** (topology B).

---

## 24. Candidate 4 verdict — spinner + product + social

No shared owner model; complexity ranges from icons to commerce grid to gamification canvas.

**Verdict: INVALID** as one compiler.

---

## 25. Better groups discovered from repository evidence

1. **Checkbox follows Radio** — reuse `resolveRadioGroupPresentationCssRules` / `resolveRadioOptionLabelCssRules` / static label resolver; new `checkbox.export.css` (see checkbox H3 deep-dive).  
2. **Divider + Coupon are both “single painted shell” blocks** but **different DOM** — migrate sequentially, not merged.  
3. **Image + Video** — only batch justified by shared media-container helpers in `mediaAndFeedbackStyles.ts`.  
4. **Product** must absorb its **existing embedded horizontal/grid CSS** into `productPresentation` before `buildBlockTraitResponsiveCss` removal.

---

## 26. Compiler topology decision per valid group

| Group | Topology choice |
|-------|-----------------|
| G1 Checkbox | **B** — `checkboxPresentation.ts` + `checkbox.export.css`; shared utilities from ElementStyles |
| G2 Divider | **D** — standalone `dividerPresentation.ts` |
| G3 Coupon | **D** — standalone `couponPresentation.ts` |
| G4 Media | **C** — same phase, separate compilers |
| G5 Social | **D** — standalone `socialPresentation.ts` |
| G6 Feedback | **D** — standalone `feedbackPresentation.ts` |
| G7 Timer | **D** — standalone `timerPresentation.ts` |
| G8 Product | **D** — standalone `productPresentation.ts` |
| G9 Spinner | **D** — standalone `spinnerPresentation.ts` (after proof) |

**No generic mega-compiler (A) justified.**

---

## 27. Expected deletion matrix

| Group | Expected deleted paths | Cleanup value |
|-------|------------------------|---------------|
| G1 Checkbox | `check-box/serialize.ts` inline styles; generic trait branch; canvas duplicate inline | **HIGH** |
| G2 Divider | `blockStyles` divider inline; generic trait branch | **MEDIUM** |
| G3 Coupon | `buildCouponContainerStyle` inline path; generic trait branch | **MEDIUM** |
| G4 Media | `mediaAndFeedbackStyles` image/video inline; generic branches | **HIGH** |
| G5 Social | serializer inline presentation | **MEDIUM** |
| G6 Feedback | `mediaAndFeedbackStyles` feedback builders inline export | **MEDIUM** |
| G7 Timer | large `timer/serialize.ts` style blocks | **HIGH** |
| G8 Product | `commerceAndCrossStyles` export paths; product embedded responsive | **HIGH** |
| G9 Spinner | partial `spinnerStyles` merchant paths | **MEDIUM** |

---

## 28. Risk scoring

| Group | DOM | Norm reuse | Responsive | Behavior | Repeated | Native | Tests | Deletion | Risk summary |
|-------|-----|------------|------------|----------|----------|--------|-------|----------|--------------|
| G1 Checkbox | 5 | 5 | 4 | 3 | 3 | 4 | 5 | 5 | **Low–medium** — template exists |
| G2 Divider | 5 | 5 | 4 | 1 | 1 | 1 | 4 | 3 | **Low** |
| G3 Coupon | 4 | 5 | 4 | 2 | 1 | 1 | 4 | 3 | **Low** |
| G4 Media | 4 | 3 | 3 | 2 | 1 | 2 | 3 | 4 | **Medium** |
| G5 Social | 4 | 2 | 4 | 1 | 3 | 1 | 3 | 3 | **Medium** |
| G6 Feedback | 3 | 2 | 3 | 3 | 4 | 3 | 2 | 3 | **Medium–high** |
| G7 Timer | 2 | 2 | 2 | 4 | 3 | 1 | 3 | 4 | **High** |
| G8 Product | 2 | 2 | 2 | 3 | 5 | 1 | 3 | 5 | **High** |
| G9 Spinner | 2 | 1 | 2 | 5 | 2 | 2 | 2 | 3 | **High** |

(Scores 0–5 per rubric in task spec; higher behavior/repeated/native = more risk.)

---

## 29. Selected first migration batch

**Selected batch: G1 — `check-box`**

**Families:** `check-box` only

**Why these families belong together:** N/A (single family) — selected because Radio Phase 6G proved the choice-group renderCss pattern; Checkbox shares group/label/wrapper resolvers but requires distinct native-control export sheet.

**Compiler topology:** `checkboxPresentation.ts` + new `checkbox.export.css` (not `radio.export.css`)

**Shared infrastructure:** `resolveRadioGroupPresentationCssRules`, `resolveRadioOptionLabelCssRules`, `resolveStandardFieldStaticLabelCssRules`, `getExportWrapperStyleFromLayout` mirror, `MIGRATED_BLOCK_TRAIT_TYPES` entry

**Family-specific infrastructure:** checkbox scope classes, `accent-color` vs CSS-var strategy, multi-check semantics

**Merchant CSS to migrate:** wrapper margin/width, group shell, option labels, fill/outline/background tokens, static field label

**Legitimate CSS to retain:** row flex structure (sheet), native appearance mechanics, validation attrs

**Inline code expected deleted:** `check-box/serialize.ts` presentation `styleAttr` blocks; generic mobile branch for type

**Generic responsive code expected deleted:** `check-box` iteration in `buildBlockTraitResponsiveCss`

**Main regression risks:** native checkbox appearance across browsers; hostile-theme parity; split-target mobile padding fix; dead `outlineColor`/`backgroundColor` traits need explicit product decision

---

## 30. Why the selected families belong together

Single-family batch intentional: Checkbox is the **only** remaining family with **proven parallel implementation** (Radio 6G), shared normalizers, and existing deep audit (`popup-checkbox-architecture-migration-readiness-phase-h3-audit.md`). Batching with Divider or Feedback would dilute the template and mix native-control concerns.

---

## 31. Complete remaining migration order

| Order | Batch | Families | Reason |
|-------|-------|----------|--------|
| 1 | **Checkbox** | `check-box` | Radio template, shared resolvers, HIGH confidence |
| 2 | **Divider** | `divider` | Simple topology, shared `buildDividerOuterShellStyle`, LOW risk |
| 3 | **Coupon** | `coupon` | Shared `buildCouponShellStyle`, bounded shell |
| 4 | **Media** | `image`, `video` | Related helpers; separate compilers |
| 5 | **Social** | `social` | Repeated icons but isolated semantics |
| 6 | **Feedback** | `feedback` | State colors + type branches |
| 7 | **Timer** | `timer` | High complexity; fit-scale mobile |
| 8 | **Product** | `product` | Embedded product CSS absorption |
| 9 | **Spinner** | `spinner` | Gamification runtime + config coupling |
| 10 | **Final cleanup** | — | Remove empty `buildBlockTraitResponsiveCss`; evaluate phone country select move |

**Every remaining family assigned exactly once: YES**

---

## 32. `buildBlockTraitResponsiveCss` endgame

| Stage | Consumers |
|-------|-----------|
| **Current** | Generic mobile merchant rules for 10 families; plus called from `buildEmbeddedStepResponsiveCss` |
| **After Batch 1 (checkbox)** | 9 families |
| **After Batch 2–3** | 7 families |
| **After Batch 4 (media)** | 5 families |
| **After Batch 5–6** | 3 families (`timer`, `product`, `spinner`) |
| **After Batch 7–9** | **0 families** |
| **Final expected consumers of `buildEmbeddedStepResponsiveCss`** | `buildBlockDeviceVisibilityCss`, `buildPhoneInputCountrySelectResponsiveCss`, `buildProductHorizontalResponsiveCss` / grid (**until product batch**), then visibility + phone select only |
| **Final helper fate** | **`buildBlockTraitResponsiveCss` → DELETE** when set empty; `buildEmbeddedStepResponsiveCss` remains for non-merchant mechanics |

---

## 33. First-batch acceptance test plan (checkbox — do not implement in H3)

- Serializer negative-inline tests (no group/label/wrapper merchant `style=`)  
- `checkboxPresentationRenderCss` unit tests (wrapper, group, label, CSS vars)  
- Desktop + mobile Chromium computed-style (padding split-target regression)  
- Native control `:checked` / `accent` or custom appearance parity  
- Artifact + multi-runtime isolation  
- Multi-step union + `CheckboxPresentationConflictError`  
- Generic responsive negative test (`check-box` absent from embedded trait CSS)  
- `radio.export.css` unchanged; no checkbox rules in radio sheet  
- Behavior: validation, multi-select, `name`/`value`  
- **Phase 0:** 18 migrated green, 0 red, 5 controls green  
- **G1:** `npm run test:export-css-production-contract` PASS  

---

## 34. Button linked-label current-writer classification

**Can current production Button UI create linked label content?** **YES**

- `ButtonText.tsx` → `InlineEditor` with `rteEnabled={true}` and `elementType="button"`  
- `InlineEditor` supports link insertion when `rteConfig.link` enabled  
- Export applies `applyRichTextLinkStyles` + `buildRichTextLinkExportCss` (`button a { color !important }`)

**Classification: CURRENT SUPPORTED WRITER**

Button linked-label work **not** included in any remaining-family batch.

---

## 35. Phase 0 requirement

All future implementation batches must keep Phase 0 baseline: **18 migrated green, 0 red, 5 controls green** (`npm run test:popup-responsive-baseline`).

---

## 36. G1 requirement

All future batches must keep **G1 PASS** (`npm run test:export-css-production-contract`).

---

## 37. Final verdict

**ACCEPT** — Remaining debt is bounded (10 families), groupings are evidence-based, and first batch (`check-box`) maximizes progress per unit risk.

---

## 38. Recommended next phase name

**Phase 7A — Checkbox Merchant Presentation renderCss Migration**

---

## Mandatory architecture questions (answers)

1. **Families still exporting merchant presentation inline?** All 10 in §3.  
2. **Families still using generic responsive merchant CSS?** Same 10 via `buildBlockTraitResponsiveCss`.  
3. **Share DOM topology?** Only within proposed groups (§20); not globally.  
4. **Share normalization semantics?** Checkbox↔Radio partial; Image↔Video partial; Coupon/Divider shell helpers only.  
5. **Look similar but must stay separate?** Timer, Product, Spinner, Feedback vs Checkbox.  
6. **State-specific compilers?** Checkbox, Feedback.  
7. **Repeated-item presentation?** Checkbox, Product, Feedback, Social, Timer units.  
8. **Native/browser control mechanics?** Checkbox, Feedback (radio inputs), Video (playback).  
9. **Legitimate inline remaining?** Structural flex rows, aspect-ratio boxes, spinner canvas box, copy button scaffold.  
10. **Legitimate embedded remaining?** `radio.export.css`, floating-label, entrance, device visibility, product layout until migrated.  
11. **Safest first batch?** `check-box`.  
12. **Implementation batches needed?** **9 family batches + 1 final cleanup** (10 ordered items in §31).  
13. **`buildBlockTraitResponsiveCss` deletable?** **YES** after batch 9.  
14. **Deletable code per batch?** §27.  
15. **New shared mega-compiler justified?** **NO**.

---

*End of Phase H3 grouping audit.*
