# Popup CSS architecture — Phase 5C implementation report

**Status:** Implemented (2026-07-05)  
**Type:** First block-family presentation migration — Button → artifact-scoped `renderCss`  
**Related:** [`popup-block-ownership-contract-phase5b-audit.md`](./popup-block-ownership-contract-phase5b-audit.md), [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md)

---

## A. Executive summary

Phase 5C migrates **Button** merchant presentation from serialized inline CSS and generic embedded responsive trait rules into a dedicated family compiler integrated only through `compilePopupRenderCss()`.

**Fixed Phase 0 scoreboard cases:**

| Case | Before | After |
| ---- | ------ | ----- |
| `block-padding` | red (split-target: mobile padding on wrapper, desktop on `<button>`) | **green** |
| `block-margin` | red (PCL-008: desktop inline margin beats mobile `@media`) | **green** |

**Scoreboard:** 8 risk red → **6 risk red**; 10 migrated green → **12 migrated green**.

No feature flags, no `!important`, no runtime changes, no backward-compatibility branches.

---

## B. Exact Button DOM ownership

```text
Wrapper [data-adflipr-block-id]
├── identity
├── block margin (desktop base + mobile @media)
└── (no merchant painted chrome)

Alignment container (> div)
├── structural width: 100%
├── structural text-align (from align trait)
└── elementBackgroundColor (renderCss — not merchant button chrome)

<button>
├── padding (desktop base + mobile @media full painted rule)
├── border-radius
├── background
├── border
├── box-shadow
├── typography (font-family, font-size, font-weight, font-style, color, line-height, letter-spacing)
├── width mode (full / fit + fixedWidth)
├── fixed height (when fit + fixedHeight)
├── fit/alignment margins (0 auto / 0 0 0 auto / 0 auto 0 0)
└── structural flex shell (display, align-items, justify-content, box-sizing, …)
```

**Not migrated to CSS architecture:** content (label HTML), `type`, `data-adflipr-popup-action*`, RTE link behavior, runtime hooks.

---

## C. Before architecture

```text
FormElement model
  → getButtonStyle() → inline on <button> (padding, radius, bg, border, shadow, typography, width, fit margins)
  → serializeElements wrapper → inline block margin on wrapper
  → buildBlockTraitResponsiveCss → mobile padding/margin/radius/font on wrapper [data-adflipr-block-id]
  → embedded step CSS hoisted at runtime
```

**Failure modes (Phase 5A/0):**

- `block-padding`: desktop padding on `<button>`, mobile padding on **wrapper** → split-target.
- `block-margin`: desktop margin inline on wrapper → PCL-008 mobile override failure.

---

## D. After architecture

```text
FormElement model
  → serializeButton() → structure + behavior attrs only; alignment div structural styles only
  → serializeElements() → strips wrapper margin for button type
  → compileButtonPresentationRenderCss() via compilePopupRenderCss()
      ├── wrapper: block margin (base + mobile @media)
      ├── align container: elementBackgroundColor
      └── painted <button>: full merchant chrome (base + mobile @media when diffs exist)
  → artifact renderCss mounted by all consumers (embed, preview, thumbnails)
```

Button is **excluded** from `buildBlockTraitResponsiveCss()` (direct `continue` on `element.type === 'button'`).

---

## E. Normalization decision

**Decision:** Keep `getButtonStyle` as a thin canvas adapter; extract **`resolveButtonPaintedCssRules`** as the canonical shared normalizer.

| Function | Role |
| -------- | ---- |
| `resolveButtonPaintedCssRules(style, align, device)` | Canonical painted trait → CSS property map (compiler + tests) |
| `BUTTON_PAINTED_STRUCTURAL_STYLES` | Structural flex shell — emitted in renderCss, not merchant traits |
| `getButtonStyle(style, align, device)` | Canvas: spreads structural + painted rules into `React.CSSProperties` |
| `normalizeButtonPresentation(style, align)` | Compiler boundary: wrapper margin + align bg + painted base/mobile diffs |

**Not duplicated:** trait interpretation lives once in `resolveButtonPaintedCssRules`; canvas and compiler both consume it.

---

## F. Wrapper layout rules migrated

| Property | Desktop | Mobile | Selector |
| -------- | ------- | ------ | -------- |
| Block margin | `resolveDeviceMarginCss(style, 'desktop')` | `resolveDeviceMarginCss(style, 'mobile')` when differs | `[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"]` |

**Removed from inline:** all `margin*` on Button wrapper (`serializeElements` deletes margin for `type === 'button'`).

**Distinct from fit-width margins:** `margin: 0 auto` / `0 0 0 auto` remain on painted `<button>` via `resolveButtonPaintedCssRules`.

---

## G. Painted button rules migrated

| Property | Source trait(s) | Notes |
| -------- | --------------- | ----- |
| padding | `paddingDesktop` / `paddingMobile` | Mobile: full painted rule in `@media max-width: 480px` when diffs |
| border-radius | `cornerRadius` / device variants | `resolveCornerRadiusCss` |
| background | `backgroundColor` | |
| border | `borderStyle`, `borderWidth`, `borderColor` | |
| box-shadow | structured or legacy shadow traits | `mergeBoxShadowIntoCssRules` |
| font-family | `fontFamily` | |
| font-size | device font size | `resolveInputFontSizePx` |
| font-weight | `fontWeight` | |
| font-style | `fontStyle` | |
| color | `textColor` | |
| line-height | `lineHeight` | |
| letter-spacing | `letterSpacing` | when set |
| width | `buttonWidth`, `fixedWidth` | full → 100%; fit → fixed px or auto |
| height | `fixedHeight` | fit + fixedHeight only |
| fit alignment margin | `buttonPosition` / `elementPosition` | painted only |

**Align container (renderCss):** `elementBackgroundColor` on `> div`.

**Structural (renderCss, not traits):** `BUTTON_PAINTED_STRUCTURAL_STYLES` on `<button>`.

---

## H. Inline CSS removed

| Location | Removed |
| -------- | ------- |
| `<button style=…>` | All merchant painted presentation (no `style` attr on button) |
| Wrapper `[data-adflipr-block-id]` | Block margin (`margin`, `marginTop`, …) |
| `buildButtonContainerStyle` helper | **Deleted** — obsolete export-only helper |

**Retained inline (structural):**

- Alignment `div`: `width: 100%`, `text-align` from align trait.

---

## I. Embedded CSS removed

Button no longer emitted by `buildBlockTraitResponsiveCss()`:

- No wrapper-targeted mobile padding/margin/radius/font-size for `type === 'button'`.
- Other embedded responsive generators (cross, floating-image, product, visibility, …) unchanged.

---

## J. Multi-step union and conflicts

Pattern matches column/form-surface compilers:

1. `collectArtifactButtonPresentations()` scans **all steps**.
2. Same `blockId` + identical `normalizeButtonPresentationKey()` → single CSS emission; `stepIds` unioned.
3. Same `blockId` + conflicting presentation → `ButtonPresentationConflictError` with `blockId` and `stepIds`.
4. Deterministic ordering: sorted by `blockId` localeCompare.

---

## K. Behavior preservation

Serializer (`button/serialize.ts`) unchanged for behavior:

| Concern | Preserved |
| ------- | --------- |
| Label content / RTE | `serializeButtonLabel`, `sanitizeRichTextHtml` |
| `type` | `submit` vs `button` from `style.action` |
| Action attrs | `data-adflipr-popup-action`, step, URL |
| `id` on `<button>` | element.id |
| Close / links / analytics | Runtime-only — not touched |

Unit test: `preserves button action attrs in serialized HTML`.

---

## L. Test evidence

| Suite | File | Coverage |
| ----- | ---- | -------- |
| Compiler unit | `utils/__tests__/buttonPresentationRenderCss.test.ts` | Scoping, selectors, desktop/mobile decls, escaping, multi-step dedup, conflict error, deterministic order, empty artifact, negative architecture, behavior attrs |
| Browser computed | `utils/__tests__/buttonPresentation.browser.test.ts` | Desktop/mobile margin+padding, painted props, fit-width alignment, Phase 0 block-padding/block-margin |
| DOM ownership | `utils/__tests__/blockDomOwnership.browser.test.ts` | Wrapper vs painted padding/margin |
| Phase 0 baseline | `utils/__tests__/responsiveCssBaseline.browser.test.ts` | Full matrix; scoreboard 12 green / 6 red |
| Serializer | `utils/__tests__/popupHtmlSerializer.test.ts` | Button bg in renderCss not inline |
| Embedded CSS | `utils/__tests__/responsiveDeviceCss.test.ts` | Unchanged non-button families |

**Harness fix:** `assertResponsiveComputed` polls up to 3s after mobile viewport change so `@media` rules apply before assertion (Playwright timing).

---

## M. Phase 0 scoreboard before/after

| Metric | Before Phase 5C | After Phase 5C |
| ------ | --------------- | -------------- |
| Risk red | 8 | **6** |
| Migrated risk green | 10 | **12** |
| Controls green | 5 | 5 |

**Newly green:** `block-padding`, `block-margin`

**Still red (6):** `cross-top`, `cross-right`, `floating-bottom`, `floating-left`, `teaser-padding`, `teaser-border-radius`

---

## N. Deleted production code

| Item | Location | Reason |
| ---- | -------- | ------ |
| `buildButtonContainerStyle` | `styles/inline/inputFieldStyles.ts` | No consumers after button serialize refactor |
| Inline painted styles on `<button>` | `button/serialize.ts` | Migrated to renderCss |
| Wrapper margin inline for button | `serializeElements.ts` | Stripped + renderCss wrapper rules |
| Button branch in `buildBlockTraitResponsiveCss` | `responsiveDeviceCss.ts` | Direct `continue` for `type === 'button'` |

---

## O. Remaining `buildBlockTraitResponsiveCss` consumers

Function **retained** — 18 wrapped block families still depend on it:

`text`, `divider`, `text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, `check-box`, `radio`, `date`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social`

**Already excluded (own handlers or migrated):** `button` (Phase 5C), `cross`, `floating-image` (dedicated embedded generators).

**Future deletion:** when last family migrates off generic trait builder (per-family compilers).

---

## P. Consumer verification

All consumers use `serializePopupExport` → `compilePopupRenderCss` → `renderCss`:

| Consumer | Path | Status |
| -------- | ---- | ------ |
| Storefront embed | `embed/runtime.ts` + `mountRenderArtifactCss` | PASS — no consumer change required |
| Builder preview | `FormPreview.tsx` → `formPreviewEmbedPresentation.ts` | PASS |
| Fullscreen preview | `previewArtifactExport.ts`, `formPreviewEmbedPresentation.ts` | PASS |
| Popup detail thumbnail | via `serializePopupExport` | PASS |
| Campaign list thumbnail | via same export path | PASS |

---

## Q. Architecture verification

| Constraint | Status |
| ---------- | ------ |
| No `!important` on Button rules | ✅ |
| No wrapper-targeted painted rules | ✅ |
| No legacy/new branches | ✅ |
| No feature flags | ✅ |
| No runtime changes | ✅ |
| No presentation registry | ✅ |
| No universal block compiler | ✅ |
| Integrated only via `compilePopupRenderCss()` | ✅ |
| Artifact scope `[data-adflipr-popup-artifact]` | ✅ |
| Native `<button>` selector (no `data-adflipr-block-part`) | ✅ |

---

## R. Next recommended family

**`text`** (Phase 5B recommendation #2)

- Inner content `div` painted owner (split-target equivalent to button padding).
- Second-highest P0 impact after button.
- Compiler shape: `textPresentation.ts` — wrapper margin + inner `div` painted + RTE link rules.

**Do not start Phase 5D until Phase 5C review is approved.**

---

## Files touched (production)

| File | Change |
| ---- | ------ |
| `utils/popupHtmlSerializer/buttonPresentation.ts` | **New** family compiler |
| `utils/popupHtmlSerializer/compilePopupRenderCss.ts` | Wire button compiler |
| `components/form-builder/canvas/ElementStyles.ts` | `resolveButtonPaintedCssRules`, structural constants |
| `components/form-builder/blocks/button/serialize.ts` | Structure/behavior only |
| `utils/popupHtmlSerializer/serializeElements.ts` | Strip button wrapper margin |
| `utils/popupHtmlSerializer/responsiveDeviceCss.ts` | Exclude button from trait builder |
| `styles/inline/inputFieldStyles.ts` | Delete `buildButtonContainerStyle` |
| `test-utils/responsiveCssBaselineHarness.ts` | Mobile viewport wait |
| `utils/__tests__/responsiveCssBaseline.browser.test.ts` | Add block-padding/margin to migrated set |
