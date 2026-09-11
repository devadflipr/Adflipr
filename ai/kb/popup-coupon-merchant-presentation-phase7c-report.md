# Phase 7C — Coupon Merchant Presentation Migration Report

**Date:** 2026-07-06  
**Branch:** `fix-0607`  
**Verdict:** **ACCEPT**

---

## 1. Executive summary

Coupon (`coupon`) merchant presentation migrated from **serializer inline desktop CSS + generic `buildBlockTraitResponsiveCss` mobile overrides** to **artifact-scoped `renderCss`** with **`coupon.export.css`** for fixed inner-row and copy-control structure. Copy runtime behavior unchanged. Phase 0 and G1 pass.

---

## 2. H3 Coupon finding verification

| H3 claim | Current code (pre-change) | Match |
|---|---|---|
| Single family `coupon` | `registerBuilderBlocks.ts`, `serializeCoupon` | **YES** |
| Shared `buildCouponShellStyle` | `ElementStyles.ts` + `commerceAndCrossStyles.ts` | **YES** |
| Desktop inline shell + code | `coupon/serialize.ts` | **YES** |
| Generic mobile on wrapper | `buildBlockTraitResponsiveCss` | **YES** |
| Copy behavior via embed attrs | `embed/blockInitializers/coupon.ts` | **YES** |
| No merchant copy-button traits | `CouponProperties.tsx` — no copy styling UI | **YES** |

**H3 Coupon debt reproduced: YES**

---

## 3. Complete Coupon trait inventory

| Trait | UI writer | Persisted | Canvas | Desktop export (pre) | Mobile export (pre) | Class |
|---|---|---|---|---|---|---|
| `codeType` / `staticCode` / `selectedCoupon` | CouponProperties | `properties.*` | CouponCanvas | HTML content | — | CONTENT |
| `showCopyButton` | CouponProperties | `properties.showCopyButton` | CouponCanvas | DOM presence | — | BEHAVIOR |
| `elementPosition` | PositionWidthSection | `style.elementPosition` | layoutStyles | wrapper inline | generic wrapper | MERCHANT |
| `fullWidth` | Switch | `style.fullWidth` | layoutStyles | wrapper inline | generic wrapper | MERCHANT |
| `elementWidth` / `elementWidthPx` | PbnWidthPercentInput / Input | `style.*` | layoutStyles | wrapper inline | generic wrapper | MERCHANT |
| `height` | PbnDimensionNumberInput | `style.height` | shell | shell inline | generic (padding/radius/font) | MERCHANT |
| `paddingDesktop` / `paddingMobile` | DeviceSpacingSection | `style.*` | shell | shell inline | generic wrapper (split-target) | MERCHANT |
| `marginDesktop` / `marginMobile` | DeviceSpacingSection | `style.*` | wrapper | wrapper inline | generic wrapper | MERCHANT |
| `cornerRadius` / `cornerRadiusMobile` | DeviceCornerRadiusSection | `style.*` | shell | shell inline | generic wrapper radius | MERCHANT |
| `backgroundColor` | ColorPickerField | `style.backgroundColor` | shell | shell inline | — | MERCHANT |
| `borderStyle` / `borderWidth` / `borderColor` | Border controls | `style.*` | shell | shell inline | — | MERCHANT |
| `fontFamily` / `fontSize` / `fontSizeMobile` / `fontWeight` / `textColor` / `lineHeight` / `letterSpacing` | InputTypographyAccordion | `style.*` | code span | code inline | generic font-size on wrapper | MERCHANT |
| `hideOnDesktop` / `hideOnMobile` | Advanced switches | `style.*` | — | device attrs + visibility CSS | — | BEHAVIOR |
| `customClass` / `customCss` | Advanced | `style.*` | — | custom CSS block | — | DEFERRED |

**Orphan:** `boxShadow` — resolvable via `resolveDeviceBoxShadowCss` in shell helper; no dedicated Coupon panel writer (export if set programmatically).

---

## 4–5. UI writer and persistence evidence

- **UI:** `CouponProperties.tsx` — Content tab (code type, store/static/spin, copy toggle); Style tab (layout, typography, background, border); Advanced (hide, custom class/CSS).
- **Persistence:** `properties.style` + `properties.codeType`, `staticCode`, `selectedCoupon`, `showCopyButton`.
- **Canvas:** `CouponCanvas.tsx` — `buildCouponShellStyle` + typography on code span.
- **Serializer (pre):** `buildCouponContainerStyle`, `buildCouponCodeStyle`, fixed `COUPON_*_STYLE` constants.

---

## 6. Exact Coupon DOM topology (post-migration)

```html
<div data-block-type="coupon" data-adflipr-block-id="{id}" style="flex-shrink:0" …>
  <div data-adflipr-coupon="true" class="pbn-coupon-shell">
    <div class="pbn-coupon-inner-row">
      <span data-adflipr-coupon-code-text="true" class="pbn-coupon-code-text">{code}</span>
      <button type="button" class="pbn-coupon-copy-btn" data-adflipr-coupon-copy="true">Copy</button>
    </div>
  </div>
</div>
```

Embedded: `<style data-adflipr-popup-coupon-css="true">` with `coupon.export.css`.

---

## 7. DOM ownership matrix

| DOM node | Stable selector | Owner role | Presentation source (post) |
|---|---|---|---|
| Block wrapper | `[data-adflipr-block-id]` | BLOCK LAYOUT | renderCss |
| `[data-adflipr-coupon]` | shell attr + `.pbn-coupon-shell` | SHELL PRESENTATION | renderCss |
| `.pbn-coupon-inner-row` | class | STRUCTURE | coupon.export.css |
| `[data-adflipr-coupon-code-text]` | code attr | VALUE PRESENTATION | renderCss |
| `.pbn-coupon-copy-btn` | class + copy attr | COPY CONTROL (fixed) | coupon.export.css |
| Code text content | text node | CONTENT | HTML |

**Coupon is NOT a single-painted-owner family** — multiple merchant-painted descendants (wrapper, shell, code).

---

## 8. Content / presentation / structure / behavior

| Concern | Classification | Future owner |
|---|---|---|
| Coupon code/value | CONTENT | HTML / `resolvePbnCouponDisplayCode` |
| Copy click / clipboard | BEHAVIOR | `embed/blockInitializers/coupon.ts` |
| Copied feedback | NOT SUPPORTED | — |
| Inner row flex | STRUCTURE | coupon.export.css |
| Fixed copy button chrome | STRUCTURE (fixed) | coupon.export.css |
| Shell padding/bg/border/radius/shadow/height | MERCHANT | renderCss shell |
| Code typography | MERCHANT | renderCss code |
| Block margin/width/position | MERCHANT | renderCss wrapper |

---

## 9. Coupon interaction behavior trace

```
merchant config → serializePopupExport HTML
→ initAllBlocks → click [data-adflipr-coupon-copy]
→ readCouponCode from [data-adflipr-coupon-code-text] textContent
→ navigator.clipboard.writeText (or textarea fallback)
```

- Runtime does **not** depend on merchant inline styles.
- Runtime does **not** mutate merchant presentation.
- Copied state: **not supported** (no feedback UI).

**Copy behavior: PASS** (`embed/__tests__/coupon.test.ts`)

---

## 10. State-presentation inventory

| State | Merchant-configurable? | Notes |
|---|---|---|
| default | YES | All style traits |
| hover (copy btn) | NO | Canvas Tailwind only; export fixed sheet |
| active/focus/copied/disabled | NOT SUPPORTED | — |

---

## 11. Normalization inventory

| Concern | Canvas | Serializer (pre) | Mobile (pre) | Shared? |
|---|---|---|---|---|
| Shell | `buildCouponShellStyle` | `buildCouponContainerStyle` | generic wrapper | **PARTIALLY SHARED** |
| Code typography | `getInputFieldTypographyStyle` + overrides | `buildCouponCodeStyle` | generic font-size | **DUPLICATION** |
| Wrapper layout | `layoutStyles` | `getExportWrapperStyleFromLayout` | generic wrapper | **SHARED** |

---

## 12–13. Compiler topology

**Selected:** **A — Dedicated `couponPresentation.ts`**

**Canonical normalization reused:**
- `getExportWrapperStyleFromLayout` → `resolveCouponWrapperCssRules`
- `buildCouponShellStyle` logic → `resolveCouponShellPresentationCssRules`
- `getInputFieldTypographyStyle` semantics → `resolveCouponCodePresentationCssRules` (+ bold/tracking)

**Coupon-specific:** `resolveCouponShellHeightPx`, shell/code selectors, `CouponPresentationConflictError`

**Rejected:**
- B — Radio compiler (different DOM)
- C — Generic simple-block compiler (forbidden)
- D — Divider shared compiler (different topology/traits)

---

## 14–19. Presentation owners

| Trait | Desktop owner (post) | Mobile owner (post) |
|---|---|---|
| margin / width / position | wrapper | wrapper (DIFF-ONLY) |
| padding / bg / border / radius / shadow / height | shell | shell (DIFF-ONLY) |
| font-size / color / family / weight / line-height / letter-spacing | code span | code span (DIFF-ONLY) |
| copy button | fixed sheet | fixed sheet |

---

## 20–21. Mobile strategy

**Strategy: MIXED BY OWNER — DIFF-ONLY per owner**

- Wrapper mobile margin/width → wrapper selector
- Shell mobile padding/radius/shadow → shell selector (fixes pre-migration split-target where padding mobile hit wrapper via generic CSS)
- Code mobile font-size → code selector
- Unset mobile inherits desktop via `resolveInputFontSizePx` / device resolvers

**Mobile ownership mismatch reproduced: YES** (generic CSS targeted wrapper for padding/font-size)  
**Mobile ownership mismatch resolved: YES**

---

## 22–23. Generic responsive / inline removal

- `coupon` added to `MIGRATED_BLOCK_TRAIT_TYPES`
- Wrapper merchant layout stripped in `serializeElements.ts` for `coupon`
- Serializer structure-only; negative tests pass

**Coupon merchant inline CSS remaining: NONE**  
**Coupon generic merchant responsive CSS remaining: NONE**

---

## 24. Remaining legitimate inline CSS

| Declaration | Classification |
|---|---|
| Wrapper `flex-shrink:0` | STRUCTURE |

Copy button and inner row: **no inline** — `coupon.export.css`.

---

## 25–29. Union, conflict, isolation

- Multi-step union: **PASS**
- Conflict: **PASS** (`CouponPresentationConflictError`)
- Deterministic output: **PASS** (sorted block ids)
- Artifact isolation: **PASS** (browser test)
- Multi-runtime: **PASS** (distinct `artifactScopeId`)

---

## 30–34. Parity and Chromium

- Canvas/export parity: **PASS** (shared resolvers)
- Desktop computed-style: **PASS**
- Mobile computed-style: **PASS**
- Mobile unset fallback: **PASS**
- Hostile-theme: **NOT RUN** (no merchant `!important` added; fixed copy sheet only)

---

## 35–36. Consumer completeness / negative assertions

All consumers use `serializePopupExport` → shared `renderCss`. No consumer patch.

Serializer + generic responsive negative tests in `couponPresentationRenderCss.test.ts`: **PASS**

---

## 37. Obsolete production code deleted

- `buildCouponContainerStyle`, `buildCouponCodeStyle`, `COUPON_INNER_ROW_STYLE`, `COUPON_COPY_BUTTON_STYLE` from `commerceAndCrossStyles.ts`
- Merchant inline path in `coupon/serialize.ts`

---

## 38. Production-derived remaining-family inventory

| Family | Inline merchant CSS | Generic responsive merchant CSS | Dedicated renderCss compiler | Status |
|---|---|---|---|---|
| `coupon` | NO | NO | YES (`couponPresentation.ts`) | **MIGRATED** |
| `check-box` | YES | YES | NO | **UNMIGRATED** |
| `divider` | YES | YES | NO | **UNMIGRATED** |
| `timer` | YES | YES | NO | **UNMIGRATED** |
| `spinner` | YES | YES | NO | **UNMIGRATED** |
| `image` | YES | YES | NO | **UNMIGRATED** |
| `video` | YES | YES | NO | **UNMIGRATED** |
| `product` | YES | YES | NO | **UNMIGRATED** |
| `feedback` | YES | YES | NO | **UNMIGRATED** |
| `social` | YES | YES | NO | **UNMIGRATED** |

*Note: On branch `fix-0607`, Checkbox/Divider migrations from Phase 6H/7B are not present; counts reflect actual repository state.*

---

## 39. Deferred debt tracking

| Item | Status |
|---|---|
| Button linked-label hover defect | RECORDED — not modified |
| Checkbox orphan traits | RECORDED — not modified |
| Structural-inline cleanup | RECORDED — not modified |
| Coupon `boxShadow` without panel writer | RECORDED — exported via renderCss if set |
| Coupon `customCss` advanced trait | RECORDED — unchanged |

---

## 40–42. Regression gates

| Gate | Result |
|---|---|
| Phase 0 | **PASS** — 18 migrated green, 0 red, 5 controls green |
| G1 | **PASS** — `npm run test:export-css-production-contract` |
| Production build | **NOT REQUIRED** — G1 production webpack fixture passed |

---

## 43–44. Remaining debt

**Coupon-specific:** fixed copy-button styling not merchant-configurable; optional hostile-theme hardening deferred.

**All-block merchant CSS debt:** **9 families** (see §38, excluding migrated `coupon`).

---

## 45. Architecture gates

| Gate | Answer |
|---|---|
| Coupon merchant fully artifact-scoped? | **YES** |
| Merchant inline remaining? | **NO** |
| Merchant in generic embedded CSS? | **NO** |
| Desktop/mobile same canonical owner per concern? | **YES** |
| Copy behavior unchanged? | **YES** |
| Same-concern hybrid? | **NO** |
| `!important` for internal repair? | **NO** |

---

## 46. Final verdict

**ACCEPT** — Coupon migration complete on this branch; gates green.

---

## 47. Recommended next action

Per H3 order on this branch: **Phase 7B — Divider Merchant Presentation Migration** (Checkbox/Divider not yet migrated here), or merge `css-reft` reconciliation work first. If branch already contains 6H/7B elsewhere, next unmigrated H3 batch is **Phase 7D — Media (Image + Video)**.

---

## Files changed

| File | Change |
|---|---|
| `couponPresentation.ts` | **NEW** |
| `coupon.export.css` | **NEW** |
| `coupon/serialize.ts` | Structure-only export |
| `CouponCanvas.tsx` | Canonical resolvers |
| `ElementStyles.ts` | Coupon resolvers + refactored `buildCouponShellStyle` |
| `compilePopupRenderCss.ts` | Wire compiler |
| `responsiveDeviceCss.ts` | Add `coupon` to migrated set |
| `serializeElements.ts` | Strip wrapper merchant layout |
| `serializeLayout.ts` | Embed coupon sheet |
| `commerceAndCrossStyles.ts` | Remove obsolete coupon builders |
| `popupEmbedContract.ts` | `POPUP_ATTR_STYLE_COUPON` |
| G1 fixture + verify script | Coupon sheet gate |
| Tests | `couponPresentationRenderCss`, `couponPresentation.browser` |

---

*End of Phase 7C report.*
