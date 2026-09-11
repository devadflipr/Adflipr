# Phase 7B — Divider Merchant Presentation Migration Report

**Date:** 2026-07-06  
**Scope:** `divider` block — merchant presentation → artifact-scoped `renderCss`  
**Compiler:** `dividerPresentation.ts`  
**References:** Phase H3 grouping audit, Phase 7A context (checkbox), Phase 0 baseline, G1 export CSS contract

---

## 1. Executive verdict

Phase 7B migrated **Divider merchant-configurable presentation** from serializer inline CSS + generic embedded responsive CSS to artifact-scoped `renderCss` via dedicated `dividerPresentation.ts`. Export HTML is structure-only (`.pbn-form-divider-root` / `.pbn-form-divider-line`). Canonical normalization reuses and extends `ElementStyles` (`resolveDividerShellCssRules`, `resolveDividerLineCssRules`). Mobile split-target defect (padding on shell desktop vs wrapper in generic mobile) **resolved** — shell owner receives mobile padding diff.

**Verdict: ACCEPT**

---

## 2. H3 Divider finding verification

| H3 claim | Verified |
|----------|----------|
| High DOM ownership certainty | **YES** — wrapper / shell / line |
| Low behavior risk | **YES** — separator only |
| Shared normalization exists | **YES** — `buildDividerOuterShellStyle` refactored to `resolveDividerShellCssRules` |
| DIFF-ONLY mobile strategy | **YES** — wrapper + shell diff; line device-agnostic |
| Serializer cleanup clear | **YES** |
| Generic responsive cleanup clear | **YES** — `divider` added to `MIGRATED_BLOCK_TRAIT_TYPES` |

**H3 Divider debt reproduced: YES**

---

## 3. Complete Divider trait inventory

| Trait | UI writer | Persisted | Canvas | Export (pre-7B) | Classification |
|-------|-----------|-----------|--------|-----------------|----------------|
| `elementPosition` | `DividerProperties` / `PositionWidthSection` | `style.elementPosition` | line align | inline line | CURRENT UI WRITER |
| `dividerWidth` | `DividerProperties` | `style.dividerWidth` | line width % | inline line | CURRENT UI WRITER |
| `dividerLineColor` | `DividerProperties` | `style.dividerLineColor` | line color | inline line | CURRENT UI WRITER |
| `dividerLineStyle` | `DividerProperties` | `style.dividerLineStyle` | line style | inline line | CURRENT UI WRITER |
| `dividerLineHeight` | `DividerProperties` | `style.dividerLineHeight` | line thickness | inline line | CURRENT UI WRITER |
| `paddingDesktop/Mobile` | `DeviceSpacingSection` | `style.padding*` | shell | inline shell / **generic wrapper mobile** | CURRENT UI WRITER — split-target fixed |
| `marginDesktop/Mobile` | `DeviceSpacingSection` | `style.margin*` | wrapper | inline wrapper + generic mobile | CURRENT UI WRITER |
| `backgroundColor` | not in Divider UI | `style.backgroundColor` | shell | inline shell | ORPHAN (model/canvas only) |
| `borderStyle/Width/Color` (frame) | not in Divider UI | `style.*` | shell frame | inline shell | ORPHAN (legacy frame) |
| `cornerRadius*` | not in Divider UI | `style.*` | shell | inline shell | ORPHAN |
| `boxShadow*` | not in Divider UI | `style.*` | shell | inline shell | ORPHAN |
| `hideOnDesktop/Mobile` | Advanced tab | `style.*` | behavior attrs | embedded visibility | BEHAVIOR |
| `customClass/customCss` | Advanced tab | `style.*` | — | — | OUT OF SCOPE |

---

## 4. Current UI writer evidence

`DividerProperties.tsx` — `handleStyleChange` for layout, line color/style/height, width %, padding/margin device pairs, hide flags.

---

## 5. Persistence evidence

All traits in `element.properties.style`. **New persistence fields: NO**

---

## 6. Exact Divider DOM topology

```html
<div data-block-type="divider" data-adflipr-block-id="{id}">
  <div class="pbn-form-divider-root">
    <div class="pbn-form-divider-line" role="separator" aria-hidden="true"></div>
  </div>
</div>
```

Block wrapper may retain structural `flex-shrink:0` inline only.

---

## 7. DOM ownership matrix

| DOM node | Selector | Role | Presentation source (post-7B) |
|----------|----------|------|-------------------------------|
| Block wrapper | `[data-adflipr-block-id]` | IDENTITY + BLOCK LAYOUT | renderCss wrapper (margin, width %) |
| Outer shell | `.pbn-form-divider-root` | OUTER SHELL | renderCss shell |
| Line | `.pbn-form-divider-line` | LINE PRESENTATION | renderCss line |
| `role=separator` | line node | BEHAVIOR | HTML attribute |

**Multiple merchant-painted owners: YES** (shell + line; layout on wrapper)

---

## 8. Trait ownership matrix

| Trait | Desktop owner | Mobile owner (pre-7B) | Canonical owner (post-7B) |
|-------|---------------|----------------------|---------------------------|
| margin | wrapper | wrapper (generic) | wrapper |
| dividerWidth % | line | line | line |
| elementPosition | line margins | line | line |
| line color/style/height | line | line | line |
| padding | shell | **wrapper (defect)** | **shell** |
| frame/bg/shadow/radius | shell | shell / generic radius on wrapper | shell |

---

## 9. Current normalization inventory

| Helper | Canvas | Serializer (pre) | Export compiler |
|--------|--------|------------------|-----------------|
| `buildDividerOuterShellStyle` | YES | YES | via `resolveDividerShellCssRules` |
| `resolveDividerLineCssRules` | YES | was inline builders | YES |
| `getExportWrapperStyleFromLayout` | layout | wrapper inline | `resolveDividerWrapperCssRules` |

**Classification: CANONICAL AND REUSABLE** (extended, not duplicated)

---

## 10. Compiler topology decision

**Selected: A — Dedicated `dividerPresentation.ts`**

---

## 11. Rejected alternatives

| Alternative | Reason |
|-------------|--------|
| Generic simple-block compiler | Divider has distinct shell + line owners |
| Extend radio/checkbox compiler | Different topology |
| Coupon batching | Different DOM |

---

## 12. Canonical normalization

```text
normalizeDividerPresentation()
├── wrapper (desktop/mobile diff) ← resolveDividerWrapperCssRules
├── shell (desktop/mobile diff)   ← resolveDividerShellCssRules
└── line (shared)                 ← resolveDividerLineCssRules
```

Canvas `DividerCanvas` consumes same resolvers.

---

## 13. Merchant/structure classification

| Declaration | Classification | Future owner |
|-------------|----------------|--------------|
| margin, width % | MERCHANT | renderCss wrapper |
| padding, bg, frame border, radius, shadow | MERCHANT | renderCss shell |
| line width, align margins, border-top | MERCHANT | renderCss line |
| display, height 0, pointer-events, flex-shrink shell | STRUCTURE | renderCss structural constants |
| role=separator | BEHAVIOR | HTML |

---

## 14–16. Block-layout / shell / line presentation

All merchant traits compile to artifact-scoped selectors:

```css
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"] { /* layout */ }
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"] .pbn-form-divider-root { /* shell */ }
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"] .pbn-form-divider-line { /* line */ }
```

---

## 17. Percentage-width semantics

`dividerWidth` → `width: {n}%` on **line owner** relative to shell content box. Verified in Chromium (50% ≈ half inner shell width).

---

## 18. Alignment semantics

`elementPosition` → `margin-left` / `margin-right` auto rules on line (`left` / `center` / `right`). Preserved from `resolveDividerLineAlignRules`.

---

## 19. Mobile ownership audit

| Trait | Pre-7B mobile target | Post-7B owner |
|-------|---------------------|---------------|
| padding | wrapper (wrong) | shell |
| margin | wrapper | wrapper |
| border-radius | wrapper | shell (if set) |

**Mismatch reproduced: YES — resolved: YES**

---

## 20. Mobile strategy

**MIXED BY OWNER — DIFF-ONLY** on wrapper and shell; line has no device-specific traits.

Unset mobile inherits desktop via `resolveDevicePaddingCss` / `resolveDeviceMarginCss` (no mobile chunk when equal).

---

## 21. Generic responsive CSS removed

`divider` added to `MIGRATED_BLOCK_TRAIT_TYPES`. Negative test confirms no `div-generic` in embedded output.

---

## 22. Inline merchant CSS removed

Serializer emits structure-only nodes. Wrapper merchant margin/width stripped via `MIGRATED_WRAPPER_LAYOUT_BLOCK_TYPES`.

---

## 23. Remaining legitimate inline CSS

Optional `flex-shrink:0` on block wrapper from `getExportWrapperStyleFromLayout` when not stripped — structural only.

---

## 24–26. Multi-step union / conflict / deterministic

`collectArtifactDividerPresentations` scans all steps; `DividerPresentationConflictError` on presentation key mismatch; sort by block id.

---

## 27–28. Artifact / multi-runtime isolation

Chromium tests PASS — distinct line colors per `artifactScopeId` on same page.

---

## 29. Canvas/export parity

Same canonical resolvers for canvas HB and renderCss export.

---

## 30. Chromium evidence

6 browser tests PASS — line color/thickness/style, width %, alignment, mobile shell padding, hostile theme, isolation.

---

## 31. Hostile-theme evidence

Global `hr` / `div` rules do not override artifact-scoped line; no `!important`.

---

## 32. Consumer completeness

All consumers use `serializePopupExport` → shared `renderCss`. No consumer patch.

---

## 33–34. Negative assertions

Unit tests: no merchant inline on divider nodes; divider excluded from `buildEmbeddedStepResponsiveCss`.

---

## 35. Obsolete production code deleted

- `buildDividerLineAlign`, `buildDividerOuterStyle`, `buildDividerInnerStyle` from `blockStyles.ts`
- Divider merchant inline path in `divider/serialize.ts`

---

## 36. Updated H3 debt inventory

| Status | Families |
|--------|----------|
| **MIGRATED (7B)** | `divider` |
| **Remaining inline + generic** | `check-box`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social` (**9** per actual repo) |

*Note: Task context assumes Phase 7A checkbox complete; repository still has `check-box` inline — count includes check-box until 7A lands.*

---

## 37. Deferred debt tracking

| Item | Status |
|------|--------|
| Button linked-label hover defect | RECORDED — not modified |
| Checkbox orphan traits (`outlineColor`, `backgroundColor`) | RECORDED — not modified |
| Structural inline cleanup (checkbox row flex, etc.) | RECORDED — not modified |

---

## 38. Phase 0 result

**PASS** — 20 migrated green, 0 red, 5 controls green

---

## 39. G1 result

**PASS**

---

## 40. Production build result

**PASS** (~109s)

---

## 41. Remaining Divider debt

- Orphan shell traits in model (`backgroundColor`, frame border, shadow) without UI writer — export via renderCss if ever set programmatically
- Optional future: move wrapper `flex-shrink` fully to renderCss

---

## 42. Remaining all-block CSS debt

9 families (see §36) + deferred Button/Checkbox items

---

## 43. Architecture gates

| Gate | Answer |
|------|--------|
| Divider merchant fully artifact-scoped? | **YES** |
| Merchant inline remaining? | **NO** |
| Merchant in generic embedded CSS? | **NO** |
| Desktop/mobile same canonical owner per concern? | **YES** (post-fix) |
| Line targets line owner? | **YES** |
| Shell targets shell owner? | **YES** |
| Structure separate? | **YES** |
| Behavior unchanged? | **YES** |
| Hybrid CSS remaining? | **NO** |

---

## 44. Final verdict

**ACCEPT**

---

## 45. Recommended next action

**Phase 7C — Coupon Merchant Presentation** (H3 batch 3) or complete **Phase 7A Checkbox** if not yet merged.

---

## Production changes

| File | Change |
|------|--------|
| `ElementStyles.ts` | `resolveDividerShellCssRules`, `resolveDividerLineCssRules`, structural constants |
| `dividerPresentation.ts` | **NEW** — compiler + collectors |
| `compilePopupRenderCss.ts` | dispatch divider compiler |
| `responsiveDeviceCss.ts` | `divider` in `MIGRATED_BLOCK_TRAIT_TYPES` |
| `migratedWrapperLayoutOwnership.ts` | `divider` in wrapper strip set |
| `divider/serialize.ts` | structure-only export |
| `DividerCanvas.tsx` | use canonical line resolver |
| `blockStyles.ts` | remove obsolete divider inline builders |
| `dividerPresentationRenderCss.test.ts` | **NEW** |
| `dividerPresentation.browser.test.ts` | **NEW** |

---

*End of Phase 7B report.*
