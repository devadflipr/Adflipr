# Popup CSS architecture — Teaser presentation + Phase 0 closure report

**Status:** Implemented (2026-07-05)  
**Type:** Style-only Teaser chip merchant presentation → artifact-scoped `renderCss`; Phase 0 baseline fully closed  
**Related:** [`popup-positioned-decorative-css-phase5e-report.md`](./popup-positioned-decorative-css-phase5e-report.md), [`popup-responsive-css-phase0-baseline.md`](./popup-responsive-css-phase0-baseline.md)

---

## A. Executive summary

This phase completes:

1. **Phase 5E cleanup** — deleted unused `buildMobilePositionOverrideRules`.
2. **Teaser migration** — style-only teaser chip merchant presentation moved from desktop inline + `buildTeaserTraitResponsiveCss()` to `compileTeaserPresentationRenderCss()` in artifact `renderCss`.
3. **Phase 0 closure** — all 18 original risk cases green; scoreboard **18 migrated regression green / 0 risk red / 5 controls green**.

---

## B. Phase 5E cleanup result

| Item | Action |
| ---- | ------ |
| `buildMobilePositionOverrideRules` | **DELETED** from `positionedDecorativePresentation.ts` |
| Unused `MarginBox` / `PositionedShellPosition` imports | Removed |
| Production references | **0** |

---

## C. Teaser pipeline trace

```text
formStyle teaser* traits (TeaserPanel)
  → TeaserPreview canvas (inline React — builder WYSIWYG)
  → serializeTeaserHtml (style-only chip when teaser step has no blocks)
  → buildTeaserExportDeviceCss (hide visibility only)
  → TEASER_EXPORT_CSS embedded <style> (runtime host shell + animations)
  → embed runtime mountTeaserHost (adflipr-teaser-host positioning)
  → compilePopupRenderCss (now includes teaser chip presentation)
```

---

## D. Exact production DOM topology

**Style-only export HTML:**

```html
<style data-adflipr-popup-device-css="true">
  /* TEASER_EXPORT_CSS — host positioning, animations */
  /* optional hide @media rules */
</style>
<div class="adflipr-teaser-anim-host adflipr-teaser-anim--{animation}"> <!-- optional -->
  <div
    data-adflipr-popup-artifact="{artifactScopeId}"
    data-adflipr-popup-teaser="true"
    data-adflipr-popup-step="teaser"
    data-adflipr-popup-teaser-position="..."
    style="pointer-events:auto"
  >
    <img ...> <!-- optional icon -->
    <span style="white-space:nowrap">text</span>
  </div>
</div>
```

**Runtime mount (embed):**

```html
<div data-adflipr-popup-teaser-host="true" class="adflipr-teaser-host adflipr-teaser-host--fixed ...">
  <!-- serialized HTML above -->
</div>
```

| Node | Role |
| ---- | ---- |
| `data-adflipr-popup-teaser-host` | **RUNTIME SHELL** — screen placement |
| `.adflipr-teaser-host--*` classes | **RUNTIME SHELL** — fixed/absolute + grid position |
| `TEASER_EXPORT_CSS` in embedded `<style>` | **RUNTIME SHELL** — host layout, z-index, offsets, animations |
| `[data-adflipr-popup-teaser="true"]` chip | **IDENTITY + PAINTED OWNER** |
| `img` / `span` children | **CONTENT** |
| Click on chip | **BEHAVIOR** — opens main step |

---

## E. DOM ownership decision

**Migration boundary: C — complete shell + painted owner on chip**

The painted merchant chip is a single node: `[data-adflipr-popup-artifact][data-adflipr-popup-teaser]`.

Runtime host positioning remains outside artifact renderCss.

---

## F. Complete property ownership matrix (chip owner)

| Property | Desktop | Mobile | Inline before | Embedded before | renderCss now |
| -------- | ------- | ------ | ------------- | --------------- | ------------- |
| padding | `teaserPadding` | `teaserMobilePadding` | chip inline desktop | mobile @media | ✅ |
| border-radius | `teaserCornerRadius` | `teaserCornerRadiusMobile` | chip inline desktop | mobile @media | ✅ |
| backgroundColor | `teaserBackgroundColor` | same | inline | — | ✅ |
| color | `teaserTextColor` | same | inline | — | ✅ |
| font-family/size/weight | teaser typography traits | same | inline | — | ✅ |
| border | teaser border traits | same | inline | — | ✅ |
| box-shadow | `teaserBoxShadow` | same | inline | — | ✅ |
| width/height | `teaserWidth/Height` | same | inline | — | ✅ |
| display/gap/align | structural flex | same | inline | — | ✅ (structural constants) |
| pointer-events | auto on chip | same | inline | — | inline **BEHAVIOR** |
| position (screen) | `teaserPosition` | same | attr + host CSS | TEASER_EXPORT_CSS | **RUNTIME SHELL** |
| offset attrs | `teaserPositionOffset` | same | data attrs | host CSS vars | **RUNTIME SHELL** |
| animation | `teaserAnimation` | same | class on anim host | keyframes in TEASER_EXPORT_CSS | **RUNTIME SHELL** |
| hide desktop/mobile | traits | same | data attrs | `!important` hide @media | **BEHAVIOR** (embedded) |
| img 20px icon | content | same | inline on img | — | **CONTENT** (unchanged) |

**No split-target defect** — padding and border-radius share the same chip owner for desktop and mobile.

---

## G. Runtime vs artifact CSS classification

| Class | Examples | Owner |
| ----- | -------- | ----- |
| A — MERCHANT PRESENTATION | padding, radius, colors, typography, border, shadow, dimensions | `renderCss` |
| B — ARTIFACT STRUCTURE | `display:inline-flex`, `gap`, `box-sizing`, `cursor` on chip | `renderCss` structural constants |
| C — RUNTIME SHELL | `.adflipr-teaser-host`, fixed/absolute, z-index, grid position, offsets | `TEASER_EXPORT_CSS` + runtime host |
| D — BEHAVIOR | `pointer-events:auto`, hide attrs, click handler | inline attr / embedded hide / runtime JS |
| E — CONTENT | teaser text, optional img | HTML |

---

## H. Artifact identity and selector contract

**Added:** `data-adflipr-popup-artifact="{artifactScopeId}"` on teaser chip when exported through `serializePopupExport`.

**Compiler selector:**

```text
[data-adflipr-popup-artifact="{scope}"][data-adflipr-popup-teaser="true"]
```

No `runtimeInstanceId` in compiled selectors.

---

## I. Compiler contract decision

**Integrated into `compilePopupRenderCss()`** via `compileTeaserPresentationRenderCss()`.

Teaser derives from same popup snapshot `formStyle`. One style-only teaser per popup when teaser step has no blocks. No separate CSS lifecycle.

---

## J. Canonical normalization decision

**`resolveTeaserPaintedCssRules(formStyle, device)`** in `teaserPresentation.ts` — single canonical interpretation for compiler.

Canvas `TeaserPreview` retains parallel inline React (builder-only; same as Button/Cross pattern).

Serializer no longer reconstructs merchant CSS inline.

---

## K. Migration boundary

**Complete painted owner** on style-only teaser chip — all merchant presentation on chip migrated together (no hybrid).

---

## L. Desktop/mobile emission strategy

**DIFF-ONLY**

Reason: same chip owner; mobile traits (`teaserMobilePadding`, `teaserCornerRadiusMobile`) differ only in values; no anchor-side switching.

---

## M. renderCss compiler implementation

File: `teaserPresentation.ts`

- `compileTeaserPresentationRenderCss({ formStyle, artifactScopeId, styleOnlyTeaser })`
- Emits base rule + `@media (max-width: 480px)` diff-only mobile overrides
- Skips when teaser step has builder blocks

---

## N. Multi-step / identity behavior

One style-only teaser per popup artifact (formStyle-level). No multi-step collector required. Compiler runs once per artifact when `styleOnlyTeaser === true`.

---

## O. Inline CSS removed

From `serializeTeaserHtml` chip `style` attribute:

- padding, border-radius, background, color, typography, border, box-shadow, width, height, flex chrome

**Retained inline on chip:** `pointer-events: auto` (BEHAVIOR)

**Retained inline on children:** img icon sizing, span `white-space: nowrap`

---

## P. Embedded CSS removed

**Deleted:** `buildTeaserTraitResponsiveCss()` (mobile padding + border-radius)

**Retained in `buildTeaserExportDeviceCss`:** `buildTeaserDeviceVisibilityCss()` only (hide on device with `!important`)

**Retained embedded:** `TEASER_EXPORT_CSS` (runtime host shell + animations — not merchant chip presentation)

---

## Q. Obsolete production code deleted

| Item | Location |
| ---- | -------- |
| `buildMobilePositionOverrideRules` | `positionedDecorativePresentation.ts` |
| `buildTeaserTraitResponsiveCss` | `responsiveDeviceCss.ts` |
| `teaserSurfaceStyle` / `teaserPadding` inline helpers | `serializeTeaser.ts` |
| Trait responsive branch in `shouldEmitResponsiveExport` | `responsiveDeviceCss.ts` |

---

## R. Teaser behavior regression evidence

| Behavior | Status |
| -------- | ------ |
| Chip click → open main (preview mount test) | PASS |
| `data-adflipr-popup-teaser` preserved | PASS |
| Position/animation/offset attrs preserved | PASS |
| Hide-on-device embedded rules preserved | PASS |
| Content text + optional img preserved | PASS |

---

## S. Consumer completeness

| Consumer | Teaser rendered? | CSS source after migration |
| -------- | ---------------- | -------------------------- |
| Storefront embed | Yes (teaser flow) | `htmlByStep.teaser` + `renderCss` |
| Builder preview (serialized) | Yes | `buildPreviewEmbedConfig` → `artifact.renderCss` |
| Builder preview (canvas) | Yes (style-only) | `TeaserPreview` React — canvas path |
| Fullscreen preview | Yes | same embed path |
| Thumbnails | When teaser in export | `serializePopupExport` |
| Live embed test | Yes | export contract |

---

## T. Artifact isolation evidence

`teaserPresentation.browser.test.ts` — scope-a vs scope-b no leakage.

---

## U. Phase 0 computed-style results

| ID | Desktop exp/act | Mobile exp/act | Pass |
| -- | --------------- | -------------- | :--: |
| `teaser-padding` | 12px / 12px | 6px / 6px | ✅ |
| `teaser-border-radius` | 20px / 20px | 6px / 6px | ✅ |

---

## V. Final Phase 0 scoreboard

| Metric | Count |
| ------ | ----: |
| Migrated regression green | **18** |
| Risk red | **0** |
| Controls green | **5** |

---

## W. Phase 0 regression-suite conversion

`responsiveCssBaseline.browser.test.ts` updated:

- All 18 original risk cases expect **mobilePass: true**
- Describe block renamed to **regression contract**
- Scoreboard: 18 green / 0 red / 5 controls

Historical `rootCause` strings in case fixtures unchanged (metadata only).

---

## X. Payload impact

Teaser presentation adds compact rules to existing `renderCss` string. Embedded teaser HTML loses inline merchant declarations and mobile padding/radius `@media` blocks — net neutral to slightly smaller teaser HTML; renderCss grows modestly.

---

## Y. Files changed

| File | Change |
| ---- | ------ |
| `teaserPresentation.ts` | **New** compiler + canonical resolvers |
| `compilePopupRenderCss.ts` | Wire teaser compiler |
| `serializeTeaser.ts` | Structure/behavior only; artifact attr |
| `responsiveDeviceCss.ts` | Delete `buildTeaserTraitResponsiveCss` |
| `positionedDecorativePresentation.ts` | Delete dead helper |
| `index.ts` | Pass artifactScopeId to teaser serialize |
| `serializedTeaserPreviewMount.ts` | Preview artifact scope |
| `responsiveCssBaselineHarness.ts` | Mount teaser with renderCss |
| `responsiveCssBaseline.browser.test.ts` | Phase 0 closure |
| `teaserPresentationRenderCss.test.ts` | **New** |
| `teaserPresentation.browser.test.ts` | **New** |
| `responsiveDeviceCss.test.ts` | Updated teaser expectations |
| `serializedTeaserPreviewMount.test.ts` | Artifact scope parity |

---

## Z. Test evidence

```bash
cd adflipr-app && npm test -- --watchAll=false \
  --testPathPattern="teaserPresentation|responsiveCssBaseline|responsiveDeviceCss|popupHtmlSerializer.test|positionedDecorative|renderArtifactCss|popupThumbnail|serializedTeaser"
```

**12 suites, 141 tests, 0 failed**

---

## AA. Architecture verification

| Gate | Status |
| ---- | ------ |
| No `!important` on teaser presentation rules | ✅ |
| Artifact scope on chip + selector | ✅ |
| No backward compatibility branches | ✅ |
| Old teaser trait responsive generator deleted | ✅ |
| Runtime shell CSS not migrated to renderCss | ✅ |
| Phase 0 fully closed | ✅ |

---

## AB. Remaining CSS architecture debt

| Debt | Notes |
| ---- | ----- |
| `buildBlockTraitResponsiveCss` | ~18 wrapped input/media block families |
| `TEASER_EXPORT_CSS` embedded in teaser HTML | Runtime shell — intentional |
| TeaserPreview canvas parallel path | Builder WYSIWYG only |
| CrossCanvas canvas parallel path | Builder WYSIWYG only |

---

## AC. Recommended next family

**Input shell family** (`text-input`, `email-input`, `phone-input`, etc.) — highest remaining volume on `buildBlockTraitResponsiveCss`; compound DOM (wrapper + outline + field) requires ownership audit before migration (Phase 5B pattern).

Do not start without a dedicated inventory gate.
