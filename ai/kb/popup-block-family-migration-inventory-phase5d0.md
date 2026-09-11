# Popup Block Family and CSS Ownership Inventory — Phase 5D-0

**Status:** Complete (analysis only — 2026-07-05)  
**Type:** Principal Engineer audit before further block-family CSS migration  
**Scope:** Authoritative block inventory, CSS source map, migration topology, roadmap  
**Prerequisites:** Phase 5A–5C reports, Phase 4E acceptance, `popup-rendering-css-architecture.md`

**Hard constraints honored:** No Text implementation, no block migration, no production code changes, no new abstractions.

**Note on repository state:** Production code in the working tree already includes **Text** migration (`textPresentation.ts` wired in `compilePopupRenderCss.ts`). This audit documents that state factually but treats Text as **already migrated** for planning purposes — not as work to be done in a future “implement Text” slice.

---

## A. Executive answer

The popup block ecosystem has **25 canonical `BlockType` values** (21 content + 4 layout). **21 content blocks** are fully supported across builder, canvas, and serializer. **`section`** is a dead palette orphan — not a real migration target.

The Phase 5C claim of **“18 remaining `buildBlockTraitResponsiveCss` consumers”** was **never equal to 18 migration units**. It meant:

```text
19 wrapped content block types
− 1 (button, excluded at Phase 5C)
= 18 types still listed as generic-trait dependents
```

That count **still included `text`**, which the KB had not yet updated. **Current code excludes both `button` and `text`**, leaving **17 block types** in the generic trait loop.

**Real remaining migration units: 8** (coherent implementation phases), not 17–18 block types:

| # | Migration unit | Block types covered | Primary value |
|---|----------------|---------------------|---------------|
| 1 | Positioned decorative | `cross`, `floating-image` | Fixes **4/6** Phase 0 red cases |
| 2 | Teaser presentation | Teaser chip (non-block) | Fixes **2/6** red cases |
| 3 | Input shell | 6 input-like types | Largest split-target class |
| 4 | Choice group | `check-box`, `radio` | Shared shell + option chrome |
| 5 | Media | `image`, `video`, `divider` | Simple painted targets |
| 6 | Commerce composite | `product`, `coupon`, `feedback`, `social` | Multi-part painted owners |
| 7 | Timer chrome | `timer` | Wrapper + digit/unit nodes |
| 8 | Spinner cleanup | `spinner` | Mostly covered by gamification; inner wheel inline only |

**Already migrated:** form surface, outer layout, columns container, column, gamification shell, **button**, **text**.

**Highest-value next migration:** **Positioned decorative** (`cross` + `floating-image`) — not Text.

**Text is already migrated in code.** Text should **not** be “next.”

**End-state compiler count (artifact `renderCss`): 13 modules** (5 structural owners + 2 done block families + 6 remaining block-family compilers). Teaser is a **14th presentation compiler** on a separate export path.

**Shared infrastructure extraction:** **LATER** (after positioned decorative + input shell — 3rd family at earliest).

**Ready for next implementation phase:** **YES** — start with positioned decorative migration (Phase 5E recommended).

---

## B. Authoritative block-type inventory

**Source:** `components/form-builder/types.ts` L1–26

### Totals

| Metric | Count |
|--------|------:|
| **Total canonical `BlockType` values** | **25** |
| **Active user-creatable content blocks** | **21** |
| **Active user-creatable layout blocks** | **3** (`one-column`, `two-column`, `three-column`) |
| **Internal / system-only types** | **1** (`section` — palette-only orphan) |
| **Dead / obsolete registered types** | **1** (`section` — never created in model data) |

### Content blocks (21)

| Canonical type | Display (palette) | Builder | Canvas | Serializer | Traits | Runtime init | Popup | Gamification | Status |
|----------------|-------------------|---------|--------|------------|--------|--------------|-------|--------------|--------|
| `text` | Text | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | **Migrated renderCss (5D in tree)** |
| `button` | Button | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | **Migrated renderCss (5C)** |
| `divider` | Divider | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | Legacy inline + generic trait |
| `text-input` | Text input | ✅ | ✅ | ✅ wrapped | ✅ | floating-label | ✅ | ✅ | Legacy inline + generic trait |
| `email-input` | Email | ✅ | ✅ | ✅ wrapped | ✅ | floating-label | ✅ | ✅ | Legacy inline + generic trait |
| `phone-input` | Phone | ✅ | ✅ | ✅ wrapped | ✅ | floating-label | ✅ | ✅ | Legacy + trait + country select CSS |
| `textarea` | Textarea | ✅ | ✅ | ✅ wrapped | ✅ | floating-label | ✅ | ✅ | Legacy inline + generic trait |
| `dropdown` | Dropdown | ✅ | ✅ | ✅ wrapped | ✅ | floating-label | ✅ | ✅ | Legacy inline + generic trait |
| `check-box` | Checkbox | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | Legacy inline + generic trait |
| `radio` | Radio | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | Legacy inline + trait + `POPUP_RADIO_EXPORT_CSS` |
| `date` | Date | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | Legacy inline + generic trait |
| `timer` | Timer | ✅ | ✅ | ✅ wrapped | ✅ | ✅ timer | ✅ | ✅ | Heavy inline + trait + timer init |
| `spinner` | Spinner | ✅ | ✅ | ✅ wrapped | ✅ | ✅ spinner | ✅ | ✅ | Gamification shell migrated; wheel inline |
| `image` | Image | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | Legacy inline + generic trait |
| `video` | Video | ✅ | ✅ | ✅ wrapped | ✅ | ✅ video | ✅ | ✅ | Legacy inline + generic trait |
| `product` | Product | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | Composite inline + product embedded CSS |
| `coupon` | Coupon | ✅ | ✅ | ✅ wrapped | ✅ | ✅ coupon | ✅ | ✅ | Composite inline + generic trait |
| `feedback` | Feedback | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | Composite inline + generic trait |
| `social` | Social | ✅ | ✅ | ✅ wrapped | ✅ | ❌ | ✅ | ✅ | Legacy inline + generic trait |
| `cross` | Cross / close | ✅ | ✅ | ✅ **unwrapped** | ✅ | ❌ | ✅ | ✅ | Inline desktop + dedicated embedded CSS |
| `floating-image` | Floating image | ✅ | ✅ | ✅ **unwrapped** | ❌ (by design) | ❌ | ✅ | ✅ | Inline desktop + dedicated embedded CSS |

### Layout blocks (4) — not block-family migration scope

| Type | Builder palette | Canvas | Serializer | Status |
|------|-----------------|--------|------------|--------|
| `one-column` | ✅ | ✅ `LayoutRenderer` | ✅ `serializeLayout` | **Migrated** (outer layout + columns) |
| `two-column` | ✅ | ✅ | ✅ | **Migrated** |
| `three-column` | ✅ | ✅ | ✅ | **Migrated** |
| `section` | ✅ palette only | ❌ `return null` | ⚠️ falls through as 3-col | **Dead orphan** |

**Non-block presentation owners (separate workstreams):** form surface, columns container, column styles, gamification shell, **teaser chip**.

---

## C. Registry coverage matrix

| Block type | Builder | Canvas | Serializer | Traits | Responsive gen | Runtime init | Tests | Status |
|------------|---------|--------|------------|--------|----------------|--------------|-------|--------|
| All 21 content | ✅ | ✅ | ✅ | ✅* | varies | 5 blocks + floating-label | exhaustiveness + family tests | **A — fully supported** |
| `floating-image` | ✅ | ✅ | ✅ | ❌ | dedicated | ❌ | wave3 tests | **A** (intentional traits gap) |
| `section` | palette | ❌ | ⚠️ | — | layout path | — | — | **E — dead/obsolete** |
| `cross` | ✅ dual canvas | ✅ | unwrapped | ✅ | dedicated | ❌ | baseline red | **G — special architecture** |

\* `floating-image` has no properties panel by design (`builderBlockRegistry.exhaustiveness.test.ts`).

### Discrepancy summary

| Class | Items |
|-------|-------|
| **A — fully supported** | 20 content blocks + `floating-image` (traits optional) |
| **B — builder-only** | None |
| **C — serializer-only** | None |
| **D — runtime-only behavior** | `floating-label` (field-level, not block-type) |
| **E — dead/obsolete** | `section` |
| **F — alias** | None |
| **G — special architecture** | `cross` (root vs in-column canvas paths), `floating-image` (unwrapped), `spinner` (gamification path) |

**Registry drift among 21 content blocks:** **None** — enforced by `builderBlockRegistry.exhaustiveness.test.ts` and `blockRegistry.exhaustiveness.test.ts`.

---

## D. Exact `buildBlockTraitResponsiveCss` consumers

**Function:** private, `responsiveDeviceCss.ts` L378–421  
**Single caller:** `buildEmbeddedStepResponsiveCss()` L510

### Explicit skips (`continue` L381–383)

| Skip condition | Types |
|----------------|-------|
| `element.isLayout` | `one-column`, `two-column`, `three-column`, `section` (when `isLayout`) |
| `element.type === 'cross'` | `cross` |
| `element.type === 'floating-image'` | `floating-image` |
| `element.type === 'button'` | `button` (Phase 5C) |
| `element.type === 'text'` | `text` (Phase 5D in tree) |

### Exact remaining generic-trait consumers (17)

When mobile/desktop differs for padding, margin, border-radius, or font-size:

`divider`, `text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, `check-box`, `radio`, `date`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social`

### Per-consumer trait emission

| Property | Desktop source | Mobile source | Selector | Desktop painted owner | Mobile target today | Same owner? | Broken? | Phase 0 |
|----------|----------------|---------------|----------|----------------------|---------------------|-------------|---------|---------|
| padding | inline on inner/outline/`img`/etc. | generic `@media` | `[data-adflipr-block-id]` wrapper | inner painted node | **wrapper** | **NO** | split-target | — |
| margin | wrapper inline | generic `@media` | wrapper | wrapper | wrapper | YES | PCL-008 possible | — |
| border-radius | painted inline | generic `@media` | wrapper | painted node | **wrapper** | **NO** | split-target | — |
| font-size | painted inline | generic `@media` | wrapper | painted/content node | **wrapper** | **NO** | split-target | — |

**All 17 types share the same wrong mobile selector** — always the export wrapper, never the painted node.

### Why “18 remaining consumers”?

| Interpretation | Verdict |
|----------------|---------|
| 18 block types? | **Almost** — 19 wrapped − `button` = **18** at Phase 5C report time |
| 18 after current code? | **No** — **17** (also excludes `text`) |
| 18 runtime paths? | **No** — one function, one loop |
| 18 test fixtures? | **No** |
| 18 enum values? | **No** — 19 wrapped content types in `WRAPPED_CONTENT_BLOCK_TYPES` |

**Exact Phase 5C list (stale):**  
`text`, `divider`, `text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, `check-box`, `radio`, `date`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social`

**Current code list (authoritative):** same minus `text` → **17 types**.

---

## E. CSS source inventory by block

Legend: ✅ = active path · — = none · **M** = migrated to renderCss

| Block type | Wrapper inline | Inner/painted inline | Embedded responsive | Static embedded sheet | renderCss compiler | Runtime CSS | Canvas-only |
|------------|----------------|----------------------|---------------------|----------------------|-------------------|-------------|-------------|
| `text` | stripped | stripped | excluded | RTE links in step† | **M** `textPresentation` | — | `ElementStyles` |
| `button` | margin stripped | stripped | excluded | — | **M** `buttonPresentation` | — | `ElementStyles` |
| `divider` | layout wrapper | separator `div` | generic trait | — | — | — | canvas |
| `text-input`…`date` | layout wrapper | outline + input | generic trait + floating-label† | floating-label† | — | floating-label init | `inputFieldStyles` |
| `phone-input` | layout wrapper | outline + select + input | generic + country select† | floating-label† | — | floating-label | phone serialize |
| `check-box` | layout wrapper | group + rows | generic trait | — | — | — | `blockStyles` |
| `radio` | layout wrapper | group + rows | generic trait | radio sheet† | — | — | `blockStyles` |
| `image` | layout + align | `<img>` | generic trait | — | — | — | `mediaAndFeedbackStyles` |
| `video` | layout wrapper | shell/iframe | generic trait | — | — | video init | serialize inline |
| `product` | layout wrapper | many semantic nodes | generic + horizontal† + grid† | — | — | — | product serialize |
| `coupon` | layout wrapper | shell + code + button | generic trait | — | — | coupon init | `commerceAndCrossStyles` |
| `feedback` | layout wrapper | stars/options | generic trait | — | — | — | serialize inline |
| `social` | layout wrapper | icon links | generic trait | — | — | — | serialize inline |
| `timer` | layout + timer attrs | digit/unit spans | generic trait | — | — | timer init | heavy inline |
| `spinner` | layout wrapper | canvas wheel | generic trait | — | gamification **M** | spinner init | `spinnerStyles` |
| `cross` | — (unwrapped) | position/margin inline | dedicated† | — | — | close behavior | `cross/serialize` |
| `floating-image` | — (unwrapped) | position/margin + img | dedicated† | — | — | — | `floating-image/serialize` |

† Embedded in step `<style>` via `serializeLayout.ts` → `buildEmbeddedStepResponsiveCss()` or static sheets.

### Other embedded generators (parallel to generic trait loop)

| Generator | Types / scope | Location |
|-----------|---------------|----------|
| `buildCrossFloatingImageResponsiveCss` | `cross`, `floating-image` | `responsiveDeviceCss.ts` L317+ |
| `buildTeaserTraitResponsiveCss` | Teaser chip | `responsiveDeviceCss.ts` L427+ |
| `buildBlockDeviceVisibilityCss` | Any block/column with hide attrs | L114+ |
| `buildPhoneInputCountrySelectResponsiveCss` | `phone-input` presence gate | L350+ |
| `buildProductHorizontalResponsiveCss` | `product` horizontal layout | L183+ |
| `buildProductGridResponsiveCss` | `product` multi-column grid | L243+ |
| Static sheets | floating-label, radio, entrance, embed-responsive | `styles/sheets/*.export.css` via `serializeLayout.ts` |

---

## F. DOM ownership map by block

| Block type | Identity owner | Layout owner | Painted owner(s) | Content owner | Behavior owner |
|------------|----------------|--------------|------------------|---------------|----------------|
| `button` | wrapper `[data-adflipr-block-id]` | wrapper + align `div` | `<button>` | label HTML in button | `<button>` + action attrs |
| `text` | wrapper | wrapper | inner content `div` | same (RTE HTML) | — |
| `text-input`…`date` | wrapper | wrapper | outline `div` (`.pbn-canvas-input-shell`) | `<input>`/`<select>`/`<textarea>` | input + floating-label |
| `phone-input` | wrapper | wrapper | outline `div` + country `<select>` | tel `<input>` | select+input |
| `check-box` | wrapper | wrapper | group shell + item rows | labels | `<input type=checkbox>` |
| `radio` | wrapper | wrapper | group shell + option rows | labels | `<input type=radio>` + radio sheet |
| `image` | wrapper | wrapper + align `div` | `<img>` | — | optional link |
| `video` | wrapper | wrapper | video shell / iframe host | — | video init |
| `divider` | wrapper | wrapper | inner `[role=separator]` | — | — |
| `product` | wrapper | wrapper | `[data-adflipr-product-shell]`, card, image, button, … | name/price text | product links |
| `coupon` | wrapper | wrapper | `[data-adflipr-coupon]` shell, code span, copy btn | code text | copy button |
| `feedback` | wrapper | wrapper | rating icons / option labels | question text | radio inputs |
| `social` | wrapper | wrapper | icon link anchors | — | anchor clicks |
| `timer` | wrapper + timer attrs | wrapper | digit/unit `span`s | countdown text | timer init |
| `spinner` | wrapper | gamification shell (4D) | canvas wheel DOM | result overlay text | spinner init |
| `cross` | root `<button>` | same (absolute) | same | × glyph | close + `data-adflipr-popup-close` |
| `floating-image` | root `div` | same (absolute) | `<img>` | — | optional link |

**Universal invariant:** `identity owner ≠ painted owner` for most wrapped blocks.

---

## G. Block type vs migration family classification

| Block type | Current CSS arch | DOM shape | Responsive traits | Migration family | Compiler decision | Can share with | Reason |
|------------|------------------|-----------|-------------------|------------------|-------------------|----------------|--------|
| `button` | renderCss | wrapper + button | padding/margin/radius/font on painted | button | **C — done** | — | Phase 5C |
| `text` | renderCss | wrapper + inner div | padding/margin/radius/font on inner | text | **C — done** | — | Phase 5D in tree |
| `text-input` | inline + generic trait | input shell | shell traits | input shell | **B** | email, phone*, textarea, dropdown, date | Same outline shell semantics |
| `email-input` | inline + generic trait | input shell | same | input shell | **B** | text-input family | Same serializer helper |
| `phone-input` | inline + trait + country CSS | shell + select | same + country width | input shell | **B** | input family (+ country rule) | Extra painted part: country `select` |
| `textarea` | inline + generic trait | input shell | same | input shell | **B** | input family | Same outline pattern |
| `dropdown` | inline + generic trait | input shell | same | input shell | **B** | input family | Same outline pattern |
| `date` | inline + generic trait | input shell | same | input shell | **B** | input family | Same outline pattern |
| `check-box` | inline + generic trait | group + rows | group traits | choice group | **B** | radio | Group shell + options |
| `radio` | inline + trait + radio sheet | group + rows | same | choice group | **B** | check-box | + `POPUP_RADIO_EXPORT_CSS` migration |
| `divider` | inline only | wrapper + separator | limited width/position | media | **B** | image, video | Simple single painted node |
| `image` | inline + generic trait | wrapper + img | image traits | media | **B** | divider, video | `<img>` painted owner |
| `video` | inline + generic trait | wrapper + iframe | video traits | media | **B** | image, divider | Shell + iframe host |
| `product` | inline + product embedded | multi semantic attrs | grid/horizontal/slots | commerce composite | **A** | — | Multiple painted parts; unique embedded CSS |
| `coupon` | inline + generic trait | shell + code + btn | coupon traits | commerce composite | **A** (shared module) | product, feedback, social | Compound painted parts |
| `feedback` | inline + generic trait | rating UI | feedback traits | commerce composite | **A** (shared module) | product, coupon, social | Multiple option chrome nodes |
| `social` | inline + generic trait | icon row | social traits | commerce composite | **A** (shared module) | product, coupon, feedback | Icon link painted nodes |
| `timer` | heavy inline + trait | wrapper + units | width/margin | timer chrome | **A** | — | Unique digit/unit DOM + runtime coupling |
| `spinner` | gamification + inline wheel | shell + canvas | gamification path | spinner cleanup | **D/E** | gamification owner | Shell migrated; wheel is behavior/static |
| `cross` | inline + dedicated embedded | unwrapped button | position margins | positioned decorative | **A** (shared) | floating-image | Same PCL-008 pattern; fixes red cases |
| `floating-image` | inline + dedicated embedded | unwrapped div+img | position margins | positioned decorative | **A** (shared) | cross | Same embedded generator today |
| `section` | n/a | n/a | n/a | — | **F** | — | Dead type |

---

## H. Composite block analysis

| Block type | Outer owner | Inner painted owners | Responsive relationships | Runtime | Compiler boundary |
|------------|-------------|---------------------|--------------------------|---------|-------------------|
| **product** | wrapper | shell, card, image, body, name, price, button (`data-adflipr-product-*`) | horizontal stack + grid column collapse | static links | **One** `commercePresentation` compiler, multiple part selectors |
| **coupon** | wrapper | `[data-adflipr-coupon]` shell, code span, copy button | traits may wrongly target wrapper | copy init on button | Same commerce compiler |
| **feedback** | wrapper | question `p`, option labels, star icons | wrapper trait split-target | interactive stars | Same commerce compiler |
| **social** | wrapper | container + per-icon links | wrapper trait split-target | link clicks | Same commerce compiler |
| **phone-input** | wrapper | outline div, country select, tel input, divider | country select has correct dedicated rule | floating-label | **One** input-shell compiler with **sub-rules** for select |
| **radio** | wrapper | group shell, per-option row, native input | + global radio pseudo sheet | — | choice-group compiler absorbs sheet into renderCss |
| **check-box** | wrapper | group shell, item rows | wrapper trait split-target | — | choice-group compiler |
| **timer** | wrapper | container, unit boxes, digit/label spans | wrapper trait + fit-width helpers | countdown DOM updates | dedicated timer compiler (not commerce) |
| **spinner** | wrapper / gamification | canvas, result overlay | gamification shell done | wheel animation | behavior CSS stays separate; minimal presentation debt |

**Rule:** Prefer **one family compiler with multiple owner selectors** when parts share one domain model (product, coupon). Do not split into per-part top-level compilers unless ownership boundaries diverge (phone country select stays inside input-shell).

---

## I. Static CSS vs merchant presentation classification

| CSS source | Current location | Owner | Classification | → renderCss? | Remain embedded? | → runtime? | Delete? |
|------------|------------------|-------|----------------|-------------|------------------|------------|---------|
| Form/button/text/column/surface/gamification compilers | `compilePopupRenderCss` | artifact owners | **Merchant presentation** | ✅ done | — | — | — |
| `buildBlockTraitResponsiveCss` | embedded step | generic wrapper | **Merchant presentation (wrong target)** | migrate per family | until last family | — | **after last family** |
| `buildCrossFloatingImageResponsiveCss` | embedded step | cross/floating | **Merchant presentation** | **yes** (positioned decorative) | until migrated | — | with migration |
| `buildTeaserTraitResponsiveCss` | teaser export | teaser chip | **Merchant presentation** | **yes** (teaser compiler) | until migrated | — | with migration |
| `POPUP_FLOATING_LABEL_EXPORT_CSS` | step `<style>` | input fields | **Behavior-state CSS** | no (orthogonal) | **yes** | pseudo mechanics | — |
| `POPUP_RADIO_EXPORT_CSS` | step `<style>` | radio inputs | **Browser normalization + merchant chrome** | **yes** (with choice group) | until migrated | — | with choice migration |
| `POPUP_ENTRANCE_EXPORT_CSS` | step `<style>` | form entrance | **Behavior-state / animation** | optional later | **yes** for now | — | — |
| `POPUP_EMBED_RESPONSIVE_EXPORT_CSS` | step `<style>` | form shell infra | **Structural artifact CSS** | partial (surface migrated) | shrink over time | — | when fully redundant |
| `buildRichTextLinkExportCss` | step `<style>` | RTE links | **Merchant presentation (descendant)** | **yes** — now in `textPresentation` link rules | remove duplicate from step when text fully accepted | — | during text acceptance |
| `buildBlockDeviceVisibilityCss` | embedded step | hide attrs | **Layout responsive** (`!important`) | evaluate per phase | **yes** (works correctly) | — | maybe never |
| Product horizontal/grid CSS | embedded step | product attrs | **Layout mechanics** (`!important`) | move with commerce compiler | until migrated | — | with commerce |
| Phone country select CSS | embedded step | phone select | **Merchant presentation** | move with input-shell | until migrated | — | with input-shell |
| Spinner canvas inline constants | `spinner/serialize.ts` | wheel | **Behavior/static** | no | — | spinner init owns | — |
| Timer digit inline | `timer/serialize.ts` | timer nodes | **Merchant presentation** | yes (timer compiler) | until migrated | — | with timer |

**Key insight:** Not every embedded sheet becomes its own family compiler. Floating-label and entrance stay embedded; radio sheet folds into choice-group migration.

---

## J. Remaining Phase 0 defect mapping

**Current scoreboard (from `responsiveCssBaseline.browser.test.ts` L183–194):**

| Bucket | Count |
|--------|------:|
| Migrated risk green | **12** |
| Unmigrated risk red | **6** |
| Controls green | **5** |
| Total cases | **23** |

### Green (12) — `RENDER_CSS_MIGRATED_RISK_IDS`

| Risk ID | Block/surface | Owner |
|---------|---------------|-------|
| `form-surface-padding` | Form surface | form surface compiler |
| `form-surface-margin` | Form surface | form surface compiler |
| `form-surface-width` | Form surface | form surface compiler |
| `form-surface-border-radius` | Form surface | form surface compiler |
| `layout-padding` | Two-column layout | outer layout compiler |
| `layout-margin` | Two-column layout | outer layout compiler |
| `layout-border-radius` | Two-column layout | outer layout compiler |
| `layout-box-shadow` | Two-column layout | outer layout compiler |
| `layout-column-gap` | Columns container | columns container compiler |
| `block-border-radius` | Column | column compiler |
| `block-padding` | Button | button compiler |
| `block-margin` | Button | button compiler |

### Red (6) — unmigrated

| Risk ID | Block type | DOM owner | Desktop CSS | Mobile CSS | Root cause | Recommended family |
|---------|------------|-----------|-------------|------------|------------|-------------------|
| `cross-top` | `cross` | close `<button>` | inline `top` in `cross/serialize.ts` | `buildCrossFloatingImageResponsiveCss` | PCL-008 | positioned decorative |
| `cross-right` | `cross` | close `<button>` | inline `right` | same embedded builder | PCL-008 | positioned decorative |
| `floating-bottom` | `floating-image` | root `div` | inline in `floating-image/serialize.ts` | same embedded builder | PCL-008 | positioned decorative |
| `floating-left` | `floating-image` | root `div` | inline `left` | same embedded builder | PCL-008 | positioned decorative |
| `teaser-padding` | Teaser (non-block) | teaser root | inline desktop padding | `buildTeaserTraitResponsiveCss` | PCL-008 | teaser presentation |
| `teaser-border-radius` | Teaser | teaser root | inline desktop radius | same teaser builder | PCL-008 | teaser presentation |

**Note:** `block-border-radius` tests **column** presentation (two-column `columnStyles`), not a generic block trait — already green via column compiler.

**No Phase 0 red case exists for `text`** — Text split-target was real in 5A audit but never had a baseline risk fixture; migration was preventive/architectural.

---

## K. Special paths outside generic responsive generation

| Path | Why bypasses generic trait loop | Coherent owner? | Broken? | Needs renderCss? | Behavior vs presentation |
|------|--------------------------------|-----------------|---------|------------------|--------------------------|
| **Button** | Phase 5C migration | ✅ | No (green) | **Done** | presentation |
| **Text** | Phase 5D in tree | ✅ | No baseline red | **Done** | presentation + link descendants |
| **Cross** | Dedicated `buildCrossFloatingImageResponsiveCss` | ✅ same node | **Yes** (2 red) | **Yes** | presentation |
| **Floating image** | Same dedicated builder | ✅ | **Yes** (2 red) | **Yes** | presentation |
| **Product** | `buildProductHorizontal/GridResponsiveCss` — correct semantic selectors | ✅ | No (controls green) | Yes (commerce compiler) | layout mechanics + presentation |
| **Phone input** | `buildPhoneInputCountrySelectResponsiveCss` — correct `select` target | ✅ | split-target on shell traits | Yes (input-shell) | presentation |
| **Radio** | `POPUP_RADIO_EXPORT_CSS` static sheet | ✅ | split-target on wrapper traits | Yes (choice group) | pseudo + presentation |
| **Floating labels** | `POPUP_FLOATING_LABEL_EXPORT_CSS` | ✅ | No (behavior) | **No** | **behavior-state CSS** |
| **Gamification** | `compileGamificationRenderCss` | ✅ | No | **Done** | presentation |
| **Teaser** | `buildTeaserExportDeviceCss` separate export | ✅ | **Yes** (2 red) | **Yes** | presentation (non-block) |
| **Spinner** | Gamification shell migrated; generic trait may still hit wrapper | partial | low | cleanup only | mixed |
| **Timer** | Generic trait on wrapper; heavy inline on digits | partial | split-target likely | Yes (timer compiler) | presentation |

---

## L. Text next-step decision

### Classification (detailed)

| Question | Answer |
|----------|--------|
| Is Text truly the best **next** migration? | **No — already migrated** in `textPresentation.ts` + `compilePopupRenderCss` |
| Does Text own any Phase 0 red case? | **No** |
| Exact painted owner? | Inner content `div` (wrapper owns margin/width %) |
| Descendant link-color rules? | **Yes** — `linkDescendant` rules in `textPresentation.ts` |
| Share semantics with another type? | **No** — unique RTE content model; not shareable with button or input shell |
| Would Text now validate new architecture? | **Already validated** — same pattern as button (normalize → collect → dedupe → conflict → renderCss) |
| Higher defect reduction elsewhere? | **Yes** — positioned decorative fixes 4 red cases; Text fixes 0 |

### Verdict

```text
C — Text was correctly a shared-pattern family but is NOT “next”
    because implementation already exists in the working tree.
    Next work should NOT re-implement Text; it should accept/audit Text
    and proceed to positioned decorative (cross + floating-image).
```

**Formal choice for audit questionnaire:**

```text
Should Text still be next: NO
(Implementation already present; next = positioned decorative)
```

---

## M. Real migration counts

| Metric | Count |
|--------|------:|
| Total active block types (content) | **21** |
| Already migrated block types | **2** (`button`, `text`) |
| Remaining block types with merchant presentation debt | **19** (17 generic + cross + floating-image; spinner partial) |
| Remaining block types with responsive **defects** (Phase 0 red) | **2 types** (`cross`, `floating-image`) + **teaser** (non-block) |
| Dedicated family compilers required (total end-state) | **8 block-family modules** (2 done + 6 remaining) |
| Shared family compilers required | **5 shared modules** cover 15 of 17 generic-trait types |
| Existing owner compilers that absorb more types | gamification absorbs spinner **shell**; column absorbs layout column radius case |
| Static/behavior CSS workstreams | **3** (floating-label, entrance, embed-responsive shrink) |
| Special non-block workstreams | **1** (teaser presentation) |
| Dead/obsolete block types | **1** (`section`) |

### Total remaining migration units: **8**

1. Positioned decorative (`cross`, `floating-image`)
2. Teaser presentation
3. Input shell (6 types)
4. Choice group (2 types)
5. Media (3 types)
6. Commerce composite (4 types)
7. Timer chrome (1 type)
8. Spinner cleanup (1 type — low complexity)

---

## N. Recommended migration roadmap

Scoring: Defect severity / Phase 0 fixes / User visibility / Types covered / Architecture value / Complexity / Runtime coupling / Composite complexity

| Order | Migration unit | Types | Owners | Defects fixed | Why now | Validates | Dependencies |
|-------|----------------|-------|--------|---------------|---------|-----------|--------------|
| **1** | Positioned decorative | cross, floating-image | unwrapped root painted nodes | **4 red** | Highest defect ROI; same PCL-008 as button margin case | Unwrapped block pattern + artifact-scoped absolute positioning | None |
| **2** | Teaser presentation | teaser chip | teaser root | **2 red** | Clears entire Phase 0 red scoreboard | Non-form export path + renderCss | Can parallel with #1 |
| **3** | Input shell | 6 input types | outline `div` + inputs | split-target class (no baseline red) | Largest user-facing block category; validates compound sub-selectors (phone) | Shared family compiler at scale | Accept Text/Button patterns |
| **4** | Choice group | check-box, radio | group shell + options | split-target | Absorbs `POPUP_RADIO_EXPORT_CSS` into renderCss | Static sheet → renderCss migration | Input shell patterns |
| **5** | Media | image, video, divider | img / iframe / separator | split-target | Lower complexity; builds confidence before commerce | Simple painted owners | None hard |
| **6** | Commerce composite | product, coupon, feedback, social | multiple semantic attrs | split-target + controls already green for product layout | Most complex DOM; do after patterns proven | Multi-part compiler | Media + input patterns |
| **7** | Timer chrome | timer | wrapper + unit nodes | split-target on wrapper traits | Runtime coupling — needs care | Digit layout presentation | Commerce patterns helpful |
| **8** | Spinner cleanup | spinner | wheel canvas | minimal | Gamification shell done; mostly delete redundant trait wrapper rules | Behavior vs presentation boundary | Gamification (done) |

**Do not put Text on this roadmap** — mark as **acceptance/audit** only (`textPresentationRenderCss.test.ts`, `textPresentation.browser.test.ts` already exist).

---

## O. Cleanup inventory

| Item | Classification | When |
|------|----------------|------|
| `section` BlockType + palette entry | Dead type | Delete after team confirms no API imports |
| `buildBlockTraitResponsiveCss` entire function | Obsolete generator | **After last family migrates** |
| Per-type trait loop entries | Obsolete | During each family migration |
| `buildButtonContainerStyle` | Already deleted (5C) | — |
| `buildCrossFloatingImageResponsiveCss` | Obsolete | When positioned decorative migrates |
| `buildTeaserTraitResponsiveCss` | Obsolete | When teaser migrates |
| `buildRichTextLinkExportCss` in step `<style>` | Duplicate of text renderCss | When Text acceptance closes |
| `buildPhoneInputCountrySelectResponsiveCss` | Obsolete | Input-shell migration |
| Product horizontal/grid embedded CSS | Obsolete | Commerce migration |
| Stale KB “18 consumers includes text” | Documentation | Update now (this doc) |
| `responsiveDeviceCss.ts` comment (lines 495–497) | Stale — omits button/text | Update during next code phase |
| `floating-image` missing properties panel | Intentional | **Keep** |

---

## P. Expected end-state compiler topology

```text
compilePopupRenderCss(snapshot, artifactScopeId)
├── compileFormSurfaceRenderCss          [done]
├── compileOuterLayoutRenderCss          [done]
├── compileColumnsContainerRenderCss     [done]
├── compileColumnPresentationRenderCss   [done]
├── compileButtonPresentationRenderCss   [done — Phase 5C]
├── compileTextPresentationRenderCss     [done — Phase 5D in tree]
├── compileGamificationRenderCss         [done — conditional]
├── compilePositionedDecorativeRenderCss   [planned — cross, floating-image]
├── compileInputShellPresentationRenderCss   [planned — 6 input types]
├── compileChoiceGroupPresentationRenderCss  [planned — check-box, radio + radio pseudo]
├── compileMediaPresentationRenderCss        [planned — image, video, divider]
├── compileCommercePresentationRenderCss     [planned — product, coupon, feedback, social]
└── compileTimerPresentationRenderCss        [planned — timer]

Separate export path:
└── compileTeaserPresentationRenderCss       [planned — teaser chip]
```

### Embedded generators to disappear at end-state

- `buildBlockTraitResponsiveCss` (entire function)
- `buildCrossFloatingImageResponsiveCss`
- `buildTeaserTraitResponsiveCss`
- `buildPhoneInputCountrySelectResponsiveCss`
- `buildProductHorizontalResponsiveCss` / `buildProductGridResponsiveCss`
- Step-level `buildRichTextLinkExportCss` ( absorbed by text compiler)
- Step-level `POPUP_RADIO_EXPORT_CSS` ( absorbed by choice compiler)

### Embedded generators likely to remain

- `POPUP_FLOATING_LABEL_EXPORT_CSS` (behavior)
- `POPUP_ENTRANCE_EXPORT_CSS` (animation)
- `buildBlockDeviceVisibilityCss` (hide-on-device — works correctly)
- Residual `POPUP_EMBED_RESPONSIVE_EXPORT_CSS` only if structural infra still needed

**Final end-state compiler count: 13** in `compilePopupRenderCss` + **1** teaser compiler = **14 presentation modules**.

---

## Q. Abstraction threshold decision

Observed duplication across `buttonPresentation.ts`, `textPresentation.ts`, `columnPresentation.ts`:

| Concern | Present in | Decision |
|---------|------------|----------|
| Step collection + Map dedupe | button, text, column | **Extract after 3rd block family** (after positioned decorative) — **LATER** |
| Conflict error type | button, text | **LATER** — family-specific error names aid debugging |
| Deterministic sort by blockId | button, text, column | **LATER** — one helper in `artifactCssUtils.ts` |
| `declarationsFromRules` / `diffMobilePresentation` | button, text (copy-pasted) | **Extract after positioned decorative** — **LATER** |
| Base + `@media` emission | all compilers | **LATER** — optional `emitScopedPresentationBlock()` |
| Selector construction | per-family | **Never extract** — family-specific |
| Normalization rules | per-family in `ElementStyles.ts` | **Never universal** — keep `resolve*CssRules` per family |

**Never extract:** universal block compiler, presentation registry, mega block manifest.

**Preserve:** explicit family ownership and colocated selectors.

---

## R. Principal Engineer verdict

1. **The “18 consumers ≈ 18 migrations” assumption is false.** It was a wrapped-type count minus button, still counting text. Real generic-trait consumers today: **17 types**. Real migration units: **8**.

2. **Text is not next.** Text migration already exists in production code. Next highest-value work is **positioned decorative** (4 Phase 0 reds) then **teaser** (2 reds).

3. **Family compiler pattern (5C/5D) is validated.** Scale via **~6 shared family modules**, not 17 dedicated compilers.

4. **Not all embedded CSS becomes renderCss.** Floating-label and entrance remain behavior/static workstreams.

5. **Commerce composite is one migration unit, not four.** Product/coupon/feedback/social share multi-part selector strategy.

6. **Registry is healthy.** Only `section` is dead; no builder/serializer drift among 21 content blocks.

7. **Shared abstraction extraction: wait** until after positioned decorative migration (3rd block-family pattern in production).

8. **Ready for next implementation phase: YES** — begin **Phase 5E: positioned decorative** (`cross` + `floating-image`).

---

```text
Block migration inventory result:

1. Total canonical block types:
   25 (21 content + 4 layout)

2. Active user-creatable block types:
   24 (21 content + 3 layout: one/two/three-column)

3. Internal/system-only block types:
   1 (section — palette orphan, never in model)

4. Dead/obsolete block types:
   1 (section)

5. Exact remaining buildBlockTraitResponsiveCss consumers:
   17 block types (when device diffs exist)

6. Consumer list:
   divider, text-input, email-input, phone-input, textarea, dropdown,
   check-box, radio, date, timer, spinner, image, video, product, coupon,
   feedback, social

7. Already migrated block families:
   button (Phase 5C), text (Phase 5D in working tree)

8. Remaining block types with CSS migration debt:
   19 (17 generic-trait + cross + floating-image; spinner partial)

9. Dedicated family compilers required:
   8 total block-family modules (2 done + 6 remaining); timer and commerce
   are dedicated modules; others are shared-family compilers

10. Shared family compilers required:
    5 (positioned decorative, input shell, choice group, media, commerce)

11. Existing compilers that can absorb more types:
    gamification (spinner shell), column (layout column radius);
    text (RTE link descendants — done)

12. Static/behavior CSS workstreams:
    floating-label sheet, entrance animation sheet, embed-responsive infra
    shrink, device visibility CSS (likely permanent embedded)

13. Special non-block workstreams:
    teaser presentation (2 Phase 0 red cases)

14. Real remaining migration units:
    8

15. Remaining Phase 0 red cases:
    cross-top, cross-right, floating-bottom, floating-left,
    teaser-padding, teaser-border-radius (6 total)

16. Highest-value next migration:
    Positioned decorative (cross + floating-image) — fixes 4/6 red cases

17. Should Text still be next:
    NO (already implemented in working tree; accept/audit, do not re-implement)

18. Recommended migration order:
    1) Positioned decorative (cross, floating-image)
    2) Teaser presentation
    3) Input shell (6 types)
    4) Choice group (check-box, radio)
    5) Media (image, video, divider)
    6) Commerce composite (product, coupon, feedback, social)
    7) Timer chrome
    8) Spinner cleanup

19. Generic responsive generator can be deleted after:
    Migration unit #8 completes (last family leaves buildBlockTraitResponsiveCss)

20. Shared infrastructure extraction:
    LATER
    Exact boundary: extract declaration/diff/sort helpers into artifactCssUtils
    after positioned decorative (#3 family pattern); never extract selectors or
    normalization rules into a universal block compiler

21. Final end-state compiler count:
    14 (13 in compilePopupRenderCss + 1 teaser on separate export path)

22. Ready for next implementation phase:
    YES — Phase 5E positioned decorative recommended
```
