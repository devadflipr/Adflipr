# Popup block ownership contract — Phase 5B architecture audit

**Status:** Complete (2026-07-05)  
**Type:** Architecture audit and decision only — no production CSS migration  
**Scope:** Block Ownership Contract viability, selector strategy, compiler architecture, deletion map, first implementation slice  
**Prerequisites:** [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md), [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md), [`popup-css-phase4e-acceptance-report.md`](./popup-css-phase4e-acceptance-report.md)

**Hard constraints honored:** No Button migration, no `renderCss` block compilers in production, no inline removal, no `buildBlockTraitResponsiveCss` hoist, no `!important`, no feature flags, no runtime changes.

---

## A. Executive verdict

Phase 5A proved block CSS is **not one migration owner**. Phase 5B asks whether a formal **Block Ownership Contract** should become production architecture.

**Verdict:**

```text
PARTIAL contract (Option C hybrid)
```

| Question | Answer |
| -------- | ------ |
| Should ownership become a formal production contract? | **PARTIAL** — identity + layout are shared production invariants; painted targets are family-specific compiler knowledge |
| Should the five-role vocabulary be a universal contract? | **NO** — only Identity and Layout merit shared enforcement; Painted is per-family; Content and Behavior do not belong in CSS architecture |
| Should `buildBlockTraitResponsiveCss` migrate into `renderCss`? | **NO** — delete and rebuild per painted owner |
| Is Button still the correct first slice? | **YES** — clearest painted owner, P0 split-target + PCL-008, no runtime initializer |
| Ready for Phase 5C implementation? | **YES** (pending Principal Engineer review of this document) |

Repository evidence supports **Option C (hybrid)** over a universal contract (Option A), documentation-only ownership (Option B), or owner-role compilers (Option D). A mega block manifest (Candidate 4) would duplicate three existing registries without solving selector drift.

---

## B. Ownership model decision

### Options evaluated

| Option | Description | Verdict |
| ------ | ----------- | ------- |
| **A** | Formal five-role production contract on every block | **Reject** — painted/content/behavior roles are not stable across families; would become a universal block DSL |
| **B** | Ownership = audit/docs only; each family owns selectors ad hoc | **Reject** — loses shared identity/layout guarantees that Phases 3–4 already depend on |
| **C** | **Hybrid:** shared identity/layout contract + family-specific painted selectors in compilers | **Accept** |
| **D** | Another architecture: family compilers + shared wrapper contract (same as C, explicit naming) | **Accept** (equivalent to C) |

### Production contract (partial)

**Enforced in production:**

1. **Identity invariant** — every content block instance exposes `[data-adflipr-block-id="{element.id}"]` scoped under `[data-adflipr-popup-artifact="{scope}"]` (or on unwrapped root for `cross` / `floating-image`).
2. **Layout invariant** — wrapped blocks receive export wrapper layout from `getExportWrapperStyleFromLayout()` in `serializeElements.ts`: block margin, width %, alignment container hooks, device-hide attrs.
3. **Painted invariant (compiler-local)** — each block-family compiler declares its own painted selector(s); no shared painted selector constant.

**Not enforced as a universal contract:**

- Content owner (typography on inner nodes) — folded into painted compiler rules where relevant.
- Behavior owner — remains embed initializer selectors in `embed/blockRegistry.ts`; not part of `renderCss` compilation.

### Why not a full five-role contract?

Tracing 20 content block families end-to-end shows **four distinct painted strategies** and **two unwrapped exceptions**. A single descriptor type listing all five roles for every block would:

- Encode false universals (e.g. “painted owner = wrapper”),
- Couple CSS compilation to runtime initializer targets,
- Duplicate `popupEmbedContract.ts` semantic attrs without adding safety for simple families.

Phases 1–4 succeeded because each migration named **one explicit DOM owner**. Block migration must repeat that pattern per family, not abstract it away.

---

## C. Five-role vocabulary verdict

| Role | Classification | Rationale |
| ---- | -------------- | --------- |
| **Identity** | **A — required production contract** | `[data-adflipr-block-id]` is universal for wrapped blocks; `cross` / `floating-image` carry it on root. Required for artifact-scoped CSS and embed hooks. Evidence: `serializeElements.ts`, `popupEmbedContract.ts`. |
| **Layout** | **A — required production contract** | Export wrapper owns block margin, width %, column placement, hide-on-device attrs. Exceptions: `cross` / `floating-image` use positioned root as layout shell. Evidence: `layoutStyles.ts`, `getExportWrapperStyleFromLayout()`. |
| **Painted** | **B — useful internal compiler concept** | Must be declared per family (or per compound part). Not one selector globally. Evidence: button `<button>`, text inner `div`, input outline `div`, product shell/button attrs. |
| **Content** | **C — documentation/test concept only** | Typography and RTE HTML live on painted or native nodes; CSS compiler targets the same nodes as painted rules. Separate “content owner” adds no selector distinct from painted for compilation. |
| **Behavior** | **D — unnecessary in CSS architecture** | Runtime uses orthogonal selectors (`POPUP_ATTR_COUPON_COPY`, `POPUP_FLOATING_FIELD_SELECTOR`, spinner/timer attrs). Combining behavior into ownership contract couples presentation migration to embed lifecycle. |

### Challenged questions (answers)

| Question | Answer |
| -------- | ------ |
| Does CSS compilation need Behavior Owner? | **No.** Behavior stays in `embed/blockInitializers/*`; CSS must not depend on initializer selectors except where presentation sheet is shared (e.g. floating-label pseudo rules stay separate). |
| Does every block need a single Painted Owner? | **No.** Compound blocks need **multiple painted parts** (product, coupon, feedback, radio). Simple blocks have one painted target. |
| Should compound blocks expose painted parts instead? | **Yes.** Use existing `data-adflipr-product-*`, `data-adflipr-coupon-*` attrs — already in `popupEmbedContract.ts`. Do not add universal `data-adflipr-block-part`. |
| Is Content Owner distinct from Painted Owner? | **Not for CSS.** Content is a documentation label for “where text/html lives”; compiler rules target painted boxes. |
| Is Layout Owner solved by wrapper architecture? | **Mostly yes** for wrapped blocks. `cross` / `floating-image` / `spinner` / `timer` need family exceptions documented in compilers. |
| Would selector descriptors create fragile coupling? | **Yes, if centralized.** Storing all selectors in one registry invites drift from serializers. Selectors belong **next to** family compilers and serializer markup. |
| Are semantic DOM attributes safer than structural descendants? | **Situational.** Structural descendants (`… [data-adflipr-block-id] button`) are stable for native-element families. Semantic attrs are **already justified** for product/coupon/radio where structure is compound. |

---

## D. Complete family ownership matrix

**Pipeline:** Builder model (`FormElement`) → canvas (`*Canvas.tsx`) → serializer (`blocks/*/serialize.ts` via `registerPopupBlockSerializers.ts`) → `serializeElements.ts` wrapper → inline styles (`ElementStyles.ts`, `styles/inline/*`) → embedded CSS (`responsiveDeviceCss.ts`, sheets) → runtime (`embed/blockInitializers/*`).

| Block type | Identity owner | Layout owner | Painted owner(s) | Content owner | Behavior owner | Key files |
| ---------- | -------------- | ------------ | ---------------- | ------------- | -------------- | --------- |
| `button` | wrapper `[data-adflipr-block-id]` | wrapper (+ align `div`) | `<button>` | button label HTML | `<button>` + action attrs | `button/serialize.ts`, `ElementStyles.getButtonStyle` |
| `text` | wrapper | wrapper | inner content `div` | same (RTE HTML) | — | `text/serialize.ts`, `getTextBlockContentStyle` |
| `text-input` | wrapper | wrapper | outline `div` (`.pbn-floating-field` if floating) | `<input>` | `<input>` + floating-label init | `serializeFieldHelpers.ts`, `inputFieldStyles.inputOutlineStyle` |
| `email-input` | wrapper | wrapper | outline `div` | `<input type=email>` | same + floating-label | same |
| `phone-input` | wrapper | wrapper | outline `div` + country `<select>` | `<input>` | select+input + floating-label | `phone-input/serialize.ts`, `buildPhoneInputCountrySelectResponsiveCss` |
| `textarea` | wrapper | wrapper | outline `div` | `<textarea>` | textarea | `textarea/serialize.ts` |
| `dropdown` | wrapper | wrapper | outline `div` | `<select>` | select | `dropdown/serialize.ts` |
| `date` | wrapper | wrapper | outline `div` | `<input type=date>` | date input | `date/serialize.ts` |
| `check-box` | wrapper | wrapper | group shell + item rows | labels + inputs | `<input type=checkbox>` | `check-box/serialize.ts` |
| `radio` | wrapper | wrapper | group shell + per-option chrome | labels | `<input type=radio>` | `radio/serialize.ts`, `POPUP_RADIO_EXPORT_CSS` |
| `image` | wrapper | wrapper + align `div` | `<img>` (or `a>img`) | — | optional link | `image/serialize.ts`, `buildImageBlockStyle` |
| `video` | wrapper | wrapper | video shell / iframe host | — | video initializer | `video/serialize.ts`, `embed/blockInitializers/video.ts` |
| `divider` | wrapper | wrapper | inner `div[role=separator]` (+ optional frame) | — | — | `divider/serialize.ts` |
| `product` | wrapper | wrapper | `[data-adflipr-product-shell]`, `[data-adflipr-product-card]`, `[data-adflipr-product-image]`, `[data-adflipr-product-button]`, … | name/price text nodes | product links | `product/serialize.ts`, `popupEmbedContract.ts` |
| `coupon` | wrapper | wrapper | `[data-adflipr-coupon]` shell, code span, copy button | code text | `[data-adflipr-coupon-copy]` button | `coupon/serialize.ts`, `coupon.ts` initializer |
| `feedback` | wrapper | wrapper | rating icons / shell | — | interactive stars | `feedback/serialize.ts` |
| `social` | wrapper | wrapper | icon links / container | — | anchor clicks | `social/serialize.ts` |
| `timer` | wrapper (+ timer attrs) | wrapper (fixed-width fit rules) | digit/unit nodes | countdown text | `POPUP_TIMER_UNITS_SELECTOR` | `timer/serialize.ts`, `timerFixedWidthFit.ts` |
| `spinner` | wrapper (gamification path) | gamification shell (Phase 4D) | canvas wheel DOM | — | spinner initializer | `spinner/serialize.ts`, gamification presentation |
| `cross` | **root** `<button>` | same (absolute position) | **same** | glyph/× | same + `data-adflipr-popup-close` | `cross/serialize.ts` (unwrapped) |
| `floating-image` | **root** `div` | same (absolute) | `<img>` | — | optional link | `floating-image/serialize.ts` (unwrapped) |

### Identity exceptions

| Case | Detail |
| ---- | ------ |
| Wrapped blocks (19) | Identity on export wrapper — `serializeElements.ts` lines 54–57 |
| `cross` | Identity on close `<button>` — bypasses wrapper |
| `floating-image` | Identity on positioned root `div` |
| `spinner` in gamification | Still has block id; presentation split across gamification shell (migrated) and wheel (runtime) |

**Requirement:** Every block should expose `data-adflipr-block-id` — **yes**, already true in production.

---

## E. Selector strategy decision

### Artifact scoping (unchanged)

```text
[data-adflipr-popup-artifact="{artifactScopeId}"]
  form[data-adflipr-popup-export="true"]
    … block selectors …
```

Proven pattern from Phases 1–4 (`compilePopupRenderCss.ts`, `artifactCssUtils.ts`).

### Painted targeting strategy

| Family class | Strategy | Example |
| ------------ | -------- | ------- |
| Native painted (`button`) | Structural descendant | `[artifact] form … [data-adflipr-block-id="btn-1"] button` |
| Inner div painted (`text`) | Structural descendant | `… [data-adflipr-block-id="t-1"] > div` (first painted child) |
| Input shell | Structural descendant | `… [data-adflipr-block-id="in-1"] .pbn-floating-field` or `> div` outline |
| Media | Structural on replaced element | `… img` |
| Compound commerce | **Existing semantic attrs** | `[data-adflipr-product-button="true"]`, `[data-adflipr-coupon]` |
| Unwrapped positioned | Root id + type attr | `[data-adflipr-block-id][data-adflipr-popup-close]` |

### Rejected strategies

| Strategy | Why rejected |
| -------- | ------------ |
| Universal `data-adflipr-block-part="painted"` | Adds payload and serializer churn for all blocks; no benefit for button/text where native tag is stable |
| Block-specific attrs for every family (`data-adflipr-button`) | Duplicates structural knowledge; only justified where compound (product/coupon already have attrs) |
| Wrapper-only selectors | **Root cause of P0 defects** — proven in Phase 5A/0 baseline |

### Evaluation matrix

| Criterion | Structural descendants | Semantic part attrs | Block-specific attrs |
| --------- | -------------------- | ------------------- | -------------------- |
| DOM refactor safety | Good when tag role fixed | Best for compound | Best for compound |
| CSS readability | High | Medium | High |
| Runtime compatibility | No impact | Must match init selectors | Already used |
| Compound blocks | Weak alone | **Strong** | **Strong** (existing) |
| Testability | Easy in browser tests | Easy | Easy |
| Payload size | Lowest | Higher | Medium |
| 50–100 block scale | **Recommended default** | Use sparingly | Already in contract |

**Decision:** **Hybrid** — structural descendants for simple families; **retain and extend** existing `popupEmbedContract.ts` semantic attrs for compound blocks only. No new universal part vocabulary.

---

## F. Registry / integration-point decision

### Candidates evaluated

| Candidate | Verdict |
| --------- | ------- |
| **1 — Extend serializer registry** (`registerBlockSerializer({ serialize, presentation })`) | **Reject** — couples HTML emission to CSS compilation lifecycle; serializers run per-device inline today |
| **2 — Separate presentation registry** | **Reject** — third registry parallel to builder/serializer/embed; drift risk |
| **3 — Block-family compiler modules** colocated under `popupHtmlSerializer/` | **Accept** |
| **4 — Mega manifest (builder+serializer+presentation+runtime+tests)** | **Reject** — no evidence of cross-cutting drift that one file solves; violates “no premature universal DSL” |

### Integration point (production)

```text
compilePopupRenderCss()
  ├── … existing surface/layout/column/gamification compilers …
  └── compileButtonPresentationRenderCss()   // Phase 5C first
      compileTextPresentationRenderCss()     // later
      compileInputShellPresentationRenderCss()
      …
```

**Composition root:** `compilePopupRenderCss.ts` — same pattern as `compileColumnPresentationRenderCss`.

**Colocation:** Presentation compilers live beside serializers conceptually (`buttonPresentation.ts` next to `blocks/button/`) but **imported only by** `compilePopupRenderCss` — not by embed runtime.

**Shared utilities:** `artifactCssUtils.ts`, device normalizers extracted from `ElementStyles.ts` / `responsiveDeviceCss.ts` (Phase 4 pattern).

---

## G. Compiler architecture comparison

| Option | Description | Correctness | Responsive safety | Duplication | @20 blocks | @50 blocks | @100 blocks | Verdict |
| ------ | ----------- | ----------- | ----------------- | ----------- | ---------- | ---------- | ----------- | ------- |
| **A** | One `compileBlockRenderCss()` | Poor — wrong selectors | Poor | Low surface, high bugs | Poor | Worse | Worse | **Reject** |
| **B** | One compiler per block type (20+) | High | High | Some duplication | Good | Heavy file count | Unmaintainable | **Reject** |
| **C** | **Compiler per family** (~8 modules) | **High** | **High** | Shared normalizers | **Good** | **Good** | **Good** | **Accept** |
| **D** | Owner-role compilers (layout/painted/content) | Medium — indirection | Medium | Splits compound blocks awkwardly | Medium | Good | Confusing | **Reject** |
| **E** | Hybrid: C + shared identity/layout helpers | **High** | **High** | Optimal | **Good** | **Good** | **Good** | **Accept** (= C) |

### Recommended family compilers

```text
buttonPresentation.ts          // button
textPresentation.ts            // text + RTE link color rules
inputShellPresentation.ts      // text-input, email, phone, textarea, dropdown, date
choiceGroupPresentation.ts     // check-box, radio (+ radio sheet integration)
mediaPresentation.ts           // image, video, divider
positionedDecorativePresentation.ts  // cross, floating-image
commercePresentation.ts        // product, coupon, feedback, social
runtimeChromePresentation.ts   // timer, spinner (structural only; not wheel logic)
```

---

## H. Style normalization audit

| Function / module | Canvas | Export inline | Shared | DOM mixed? | Future |
| ----------------- | ------ | ------------- | ------ | ---------- | ------ |
| `getButtonStyle` | `ButtonCanvas.tsx` | `button/serialize.ts` | **Yes** | No — pure style | **Split:** export painted rules → `buttonPresentation`; canvas keeps calling shared normalizer |
| `getElementStyle` | Many canvases | `inputFieldStyles`, `mediaAndFeedbackStyles`, commerce | Partial | Comment warns wrapper misuse | **Canonical normalizer** for input shell + text inner; export inline **delete** after migration |
| `getTextBlockContentStyle` | `TextCanvas` | `text/serialize.ts` | Yes | No | Becomes `textPresentation` source |
| `inputOutlineStyle` / `floatingOutlineStyle` | `buildCanvasInputShellStyles` | field serializers | **Yes** | No | `inputShellPresentation` |
| `buildImageBlockStyle` | `ImageCanvas` | `image/serialize.ts` | Yes | No | `mediaPresentation` |
| `getExportWrapperStyleFromLayout` | — | `serializeElements.ts` | Export only | Layout only | **Keep** — layout owner inline until block layout migration; margin moves to renderCss per family |
| `buildButtonContainerStyle` | — | `button/serialize.ts` | Export | Alignment structural | **Keep structural** inline (flex alignment); not merchant presentation |
| `buildCouponContainerStyle` / `buildCouponCodeStyle` | — | coupon serialize | Export | No | `commercePresentation` |
| Product style helpers | `ProductCanvas` | `product/serialize.ts` | Partial | No | `commercePresentation` |
| `buildCrossFloatingImageResponsiveCss` | — | embedded only | Export | Uses margin resolvers | **Replace** with `positionedDecorativePresentation` in renderCss |
| `traitBorderProps` | traits | — | Canvas | No | Keep — model only |

### Functions marked for deletion (not preservation)

| Item | When |
| ---- | ---- |
| `buildBlockTraitResponsiveCss` (wrapper trait loop) | **DELETE WITH FIRST FAMILY MIGRATION** — replace with family compilers |
| Export inline painted styles (`styleAttr(btnStyle)`, etc.) | **DELETE PER FAMILY** as renderCss absorbs painted rules |
| Duplicate mobile rules on wrong selector | Gone when trait builder deleted |

### Keep (non-presentation)

| Item | Reason |
| ---- | ------ |
| `POPUP_FLOATING_LABEL_EXPORT_CSS` | Behavior + pseudo presentation on inputs; orthogonal to shell migration |
| `POPUP_RADIO_EXPORT_CSS` | Native control pseudo; migrate with radio family |
| `POPUP_ENTRANCE_EXPORT_CSS` | Animation behavior |
| `buildBlockDeviceVisibilityCss` | Layout responsive; `!important` — evaluate per phase |
| `buildPhoneInputCountrySelectResponsiveCss` | Correct target (select); move with phone family |
| `buildProductHorizontalResponsiveCss` / `buildProductGridResponsiveCss` | Correct product attrs; move with commerce family |
| `buildRichTextLinkExportCss` | Content color; move with text family |

---

## I. Responsive ownership matrix

Legend: **Correct future owner** = target for `renderCss` desktop + mobile rules.

| Family | Property | Desktop source | Mobile source | Desktop DOM target | Mobile DOM target today | Same? | Correct future owner |
| ------ | -------- | -------------- | ------------- | ------------------ | ----------------------- | ----- | -------------------- |
| button | padding | `getButtonStyle` | `buildBlockTraitResponsiveCss` | `<button>` | wrapper | **NO** | `<button>` |
| button | margin | `getExportWrapperStyleFromLayout` | `buildBlockTraitResponsiveCss` | wrapper | wrapper | YES | **wrapper** (remove desktop inline) |
| button | border-radius | `getButtonStyle` | trait builder | `<button>` | wrapper | **NO** | `<button>` |
| button | font-size | `getButtonStyle` | trait builder | `<button>` | wrapper | **NO** | `<button>` |
| button | background/border/shadow | `getButtonStyle` | — (no mobile path) | `<button>` | — | N/A | `<button>` |
| text | padding | `getTextBlockContentStyle` | trait builder | inner `div` | wrapper | **NO** | inner `div` |
| text | margin | wrapper inline | trait builder | wrapper | wrapper | YES | wrapper |
| text | font-size | inner inline | trait builder | inner | wrapper | **NO** | inner `div` |
| input* | padding/border/radius | `inputOutlineStyle` | trait builder | outline `div` | wrapper | **NO** | outline `div` |
| input* | font-size | input inline | trait builder | input | wrapper | **NO** | input (or shell per trait) |
| image | dimensions/radius | `buildImageBlockStyle` | no trait path | `img` | — | N/A | `img` |
| divider | line width/color | serialize inline | — | separator `div` | — | N/A | separator |
| cross | top/right | `serializeCross` desktop inline | `buildCrossFloatingImageResponsiveCss` | close `button` | same | YES | close `button` (drop inline desktop) |
| floating-image | bottom/left | serialize desktop inline | cross/floating builder | root `div` | same | YES | root `div` |
| product | grid/flex | inline + embedded | `buildProduct*ResponsiveCss` | product attrs | same | YES | product semantic attrs |
| phone | country select width | embedded | same | `select` | same | YES | country `select` |
| all | hide-on-device | data attrs | `buildBlockDeviceVisibilityCss` | wrapper | wrapper | YES | wrapper |
| radio | control chrome | `POPUP_RADIO_EXPORT_CSS` | sheet | radio inputs | same | YES | radio sheet → renderCss later |
| timer | width/margin | wrapper + timer helpers | trait builder | wrapper / timer nodes | wrapper | PARTIAL | wrapper + timer attrs |
| spinner | gamification | Phase 4D shell | gamification CSS | shell | shell | YES | already migrated shell |
| coupon/feedback/social | family traits | inline + sheets | trait builder (wrapper) | family painted nodes | wrapper | **NO** | per-part selectors |

### Defect classes

| Class | Families affected | Count (minimum) |
| ----- | ----------------- | --------------- |
| **Split-target** | button, text, all input-like, coupon (if traits on wrapper) | **6+ P0 pairs** (Phase 5A) |
| **Same-element PCL-008** | button margin, text margin, cross, floating-image | **4+ P0** |
| **Passing controls** | product layout, hide-on-device, column stack | 5 green |

### Canvas-only traits

Most Style tab traits are in model for both canvas and export; export always uses `ctx.device === 'desktop'` for inline painted styles today — **mobile traits exist only in embedded/trait builder**, never applied to painted element.

---

## J. Compound-block analysis

| Block | Painted parts | Selector strategy | Migration unit |
| ----- | ------------- | ----------------- | -------------- |
| **product** | shell, card, image, body, name, price, button | Existing `data-adflipr-product-*` | `commercePresentation` — one compiler, multiple part rules |
| **coupon** | shell (`data-adflipr-coupon`), code text, copy button | `data-adflipr-coupon-*` | Same — copy button painted separately from shell |
| **feedback** | star icons / container | Structural + classes | Commerce/feedback subcompiler |
| **social** | icon links | Structural | Commerce/social subcompiler |
| **radio** | group shell + per-option label/control | `POPUP_RADIO_EXPORT_CSS` + wrapper | `choiceGroupPresentation` |
| **check-box** | group + items | Structural descendants | `choiceGroupPresentation` |

**Browser evidence (Phase 5B):** Product shell `display: grid` vs button `backgroundColor` differ — `blockDomOwnership.browser.test.ts` § Phase 5B.

**Rule:** Compound blocks expose **painted parts**, not a single Painted Owner constant.

---

## K. Runtime coupling analysis

| Block | Runtime initializer | Selector | CSS must avoid |
| ----- | ------------------- | -------- | -------------- |
| spinner | `spinner.ts` | `POPUP_SPINNER_SELECTOR` | Mutating wheel canvas presentation via wrapper rules |
| timer | `timer.ts` | `POPUP_TIMER_UNITS_SELECTOR` | Digit layout — structural CSS only |
| video | `video.ts` | `POPUP_VIDEO_BLOCK_SELECTOR` | iframe sizing conflicts |
| coupon | `coupon.ts` | `[data-adflipr-coupon-copy]` | Copy button must remain clickable; presentation on button ok |
| floating-label | `floatingLabel.ts` | `POPUP_FLOATING_FIELD_SELECTOR` | Label float mechanics — keep `POPUP_FLOATING_LABEL_EXPORT_CSS` separate |

**15 content types** have no embed initializer — **button is decoupled**.

**Conclusion:** Behavior ownership must **not** enter `renderCss` contract. Presentation compilers may target the same nodes as initializers (coupon copy button) but must not import initializer modules.

---

## L. Deletion map

| Code | Action | Timing |
| ---- | ------ | ------ |
| `buildBlockTraitResponsiveCss` | **DELETE AND REBUILD** | With first family migration (button) — remove button from loop |
| `getButtonStyle` export usage (`styleAttr(btnStyle)` in serialize) | **DELETE** painted inline | Phase 5C button slice |
| `getButtonStyle` canvas usage | **KEEP** until canvas reads renderCss preview (later) | After embed path proven |
| `getElementStyle` export usage | **DELETE** per family | Text/input slices |
| `inputOutlineStyle` inline in export | **DELETE** | Input shell slice |
| `buildCrossFloatingImageResponsiveCss` | **REWRITE** into `positionedDecorativePresentation` | Cross/floating slice |
| `buildPhoneInputCountrySelectResponsiveCss` | **MOVE** to renderCss | Phone slice |
| `buildProductHorizontalResponsiveCss` / `buildProductGridResponsiveCss` | **MOVE** to renderCss | Product slice |
| `POPUP_FLOATING_LABEL_EXPORT_CSS` | **KEEP** (separate concern) | Input behavior phase |
| `POPUP_RADIO_EXPORT_CSS` | **MOVE** to renderCss | Radio slice |
| `buildRichTextLinkExportCss` | **MOVE** to `textPresentation` | Text slice |
| Wrapper margin inline (`getExportWrapperStyleFromLayout`) | **REWRITE** — renderCss for margin per block | Phase 5C for button margin |
| `responsiveDeviceCss` monolith | **SHRINK** as families migrate | Incremental |

---

## M. First implementation slice decision

### Candidates challenged

| Candidate | Prove architecture? | P0 coverage | Runtime risk | Verdict |
| --------- | ------------------- | ----------- | ------------ | ------- |
| **button** | **Yes** — split painted vs layout | block-padding, block-margin | None | **Best** |
| text | Yes — inner div painted | block-padding equivalent | Low | Second |
| image | Painted = `img` but no P0 red | None | Low | Does not prove responsive fix |
| divider | Simple geometry | None | None | Too narrow |
| cross | identity=painted | cross-top/right P0 | Low | Does not prove wrapper≠painted pattern |
| floating-image | positioned shell | floating P0 | Low | Same as cross |

**Button remains the correct first slice** because it is the canonical proof of **wrapper identity/layout vs descendant painted** — the defect class that affects the majority of wrapped blocks.

---

## N. Exact Phase 5C implementation boundary

### Block type

```text
button only
```

### DOM owners

| Concern | Owner | Selector |
| ------- | ----- | -------- |
| Identity | Export wrapper | `[data-adflipr-popup-artifact] form … [data-adflipr-block-id="{id}"]` |
| Layout — block margin | **Wrapper** | same |
| Layout — width % / column placement | Wrapper | same (already `getExportWrapperStyleFromLayout`) |
| Layout — alignment container | Inner `div` (structural flex) | `… [data-adflipr-block-id] > div` — **keep structural inline** |
| Painted — padding | **`<button>`** | `… [data-adflipr-block-id] button` |
| Painted — border-radius | `<button>` | same |
| Painted — background, border, shadow | `<button>` | same |
| Painted — typography (font-size, weight, color) | `<button>` | same |
| Painted — width/fit (`buttonWidth`, `fixedWidth`, `fixedHeight`) | `<button>` | same |
| Painted — fit-width auto margins (`buttonPosition`) | `<button>` | same (not block margin) |

### Explicit exclusions (Phase 5C)

- No text/input/product families
- No removal of `buildBlockTraitResponsiveCss` for other types until their slice
- No canvas preview migration (export + embed first)
- No `!important`
- No runtime / action attr changes

### Margin distinction (critical)

```text
Block margin (marginDesktop / marginMobile)     → layout owner (wrapper)
Button fit alignment margin (0 auto / 0 0 0 auto) → painted owner (<button>)
```

Migrating block margin to `renderCss` on wrapper **fixes PCL-008** for `block-margin`. Migrating padding to `<button>` **fixes split-target** for `block-padding`.

---

## O. Required tests

### Existing (passing)

```bash
cd adflipr-app
npx craco test --watchAll=false --testPathPattern="blockDomOwnership.browser"
# 7 tests — Phase 5A (4) + Phase 5B (3)
```

| Test | Proves |
| ---- | ------ |
| button padding desktop/mobile | identity ≠ painted; split-target |
| button margin | wrapper owner; PCL-008 |
| text padding | inner div painted |
| cross same element | identity = painted = behavior |
| email-input outline bg | wrapper ≠ painted shell |
| product shell vs button | compound multiple painted parts |

### Required for Phase 5C (not implemented in 5B)

| Test | Purpose |
| ---- | ------- |
| `buttonPresentationRenderCss.test.ts` | Unit: CSS chunks scoped, correct selectors, desktop+mobile |
| `buttonPresentation.browser.test.ts` | `getComputedStyle` at 375px: padding 8px on **button**, margin 6px on **wrapper** |
| `responsiveCssBaseline.browser` — `block-padding`, `block-margin` | Scoreboard flips to green |
| Regression: action attrs, submit type | HTML unchanged except style removal |

---

## P. Repository evidence

| Evidence | Location |
| -------- | -------- |
| Phase 5A DOM ownership audit | `ai/kb/popup-block-css-phase5a-ownership-audit.md` |
| Wrapper export shell | `utils/popupHtmlSerializer/serializeElements.ts` |
| Wrong mobile selector | `responsiveDeviceCss.ts` → `buildBlockTraitResponsiveCss` L378–420 |
| Button inline painted | `blocks/button/serialize.ts` L30–37 |
| Button style normalizer | `canvas/ElementStyles.ts` → `getButtonStyle` L320–371 |
| renderCss composition (no blocks yet) | `compilePopupRenderCss.ts` |
| Semantic product/coupon attrs | `contract/popupEmbedContract.ts` |
| Serializer registry | `registerPopupBlockSerializers.ts` |
| Runtime registry (5 init) | `embed/blockRegistry.ts`, `embed/blockInitializers/*` |
| Phase 0 scoreboard | `popup-responsive-css-phase0-baseline.md` |
| Browser ownership tests | `utils/__tests__/blockDomOwnership.browser.test.ts` |
| Successful migration pattern | `columnPresentation.ts`, `gamificationPresentation.ts` |

---

## Q. Final Principal Engineer recommendation

1. **Adopt Option C hybrid** — shared identity/layout invariants; family-specific painted compilers.
2. **Do not** introduce a universal five-role production contract or block DSL.
3. **Delete** `buildBlockTraitResponsiveCss` wrapper assumption; rebuild per family starting with button `<button>` target.
4. **Integrate** block presentation at `compilePopupRenderCss` composition layer; colocate family modules; do not extend serializer registry with presentation hooks.
5. **Selectors:** artifact scope + block id + structural descendants; semantic attrs only for compound blocks (already in contract).
6. **Phase 5C:** Button only — margin on wrapper, painted traits on `<button>`, keep alignment `div` structural inline.
7. **Tests:** Extend browser baseline for button; unit-test presentation compiler output before removing inline.

**Stop here.** Await review before Phase 5C implementation.

---

## Phase 5B verdict

```text
Phase 5B verdict:

1. Should Block Ownership become a formal production contract?
   PARTIAL

2. Required production ownership roles:
   Identity (data-adflipr-block-id under artifact scope)
   Layout (export wrapper / positioned root for unwrapped blocks)
   Painted (family-specific — declared in per-family presentation compilers, not global)

3. Roles that remain documentation/compiler concepts only:
   Content (documentation label; same nodes as painted for CSS)
   Behavior (embed initializer selectors only — outside renderCss contract)
   Single universal Painted Owner (false for compound blocks)

4. Selector strategy:
   [data-adflipr-popup-artifact] + [data-adflipr-block-id] + structural descendants
   for simple families; existing data-adflipr-product-* / coupon-* / radio sheet
   for compound blocks; no universal data-adflipr-block-part DSL

5. Compiler architecture:
   Option C/E — hybrid family compilers (~8 modules) composed by compilePopupRenderCss;
   shared artifactCssUtils + style normalizers; not one universal compileBlockRenderCss

6. Registry integration point:
   compilePopupRenderCss composition (Candidate 3); no presentation hook on serializer
   registry; no mega manifest

7. Code to delete before or during first migration:
   buildBlockTraitResponsiveCss (button entries) — DELETE WITH FIRST MIGRATION
   getButtonStyle inline on export <button> — DELETE IN PHASE 5C
   wrapper margin inline for button — REWRITE TO renderCss IN PHASE 5C
   (other families' trait builder entries — DELETE IN LATER SLICES)

8. First implementation slice:
   button

9. Exact DOM owners for that slice:
   Identity + block margin + width % → wrapper [data-adflipr-block-id]
   Painted padding, radius, bg, border, shadow, typography, buttonWidth/fit
     → descendant button
   Structural alignment flex div → keep minimal inline (non-trait layout)
   Fit-width auto margins on button → button painted owner (not block margin)

10. Expected Phase 0 scoreboard impact:
   block-padding → green (mobile padding on button at 375px)
   block-margin → green (mobile margin on wrapper without inline PCL-008)
   8 red → 6 red; 10 migrated green unchanged

11. Ready for implementation:
   YES
```
