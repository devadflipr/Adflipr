# Popup CSS architecture — Phase 5E implementation report

**Status:** Implemented (2026-07-05)  
**Type:** Cross + floating-image positioned decorative presentation → artifact-scoped `renderCss`  
**Related:** [`popup-text-presentation-css-phase5d-report.md`](./popup-text-presentation-css-phase5d-report.md), [`popup-block-family-migration-inventory-phase5d0.md`](./popup-block-family-migration-inventory-phase5d0.md), [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md)

---

## A. Executive summary

Phase 5E migrates **Cross** and **floating-image** merchant presentation from desktop inline CSS plus `buildCrossFloatingImageResponsiveCss()` embedded mobile rules into a dedicated positioned-decorative family compiler integrated only through `compilePopupRenderCss()`.

**Key outcomes:**

- Four Phase 0 red cases repaired: `cross-top`, `cross-right`, `floating-bottom`, `floating-left`.
- Scoreboard: **16 migrated green / 2 risk red / 5 controls green** (remaining red: `teaser-padding`, `teaser-border-radius`).
- `buildCrossFloatingImageResponsiveCss` and related helpers **deleted** from production.
- Cross and floating-image serializers emit structure/behavior only — no inline merchant presentation.
- Canonical normalization centralized in `positionedDecorativePresentation.ts`; floating-image canvas adapters delegate to shared resolvers.

---

## B. Pre-implementation ownership trace

```text
FormElement cross / floating-image model (properties.position, marginDesktop, marginMobile, …)
  → Traits (ButtonCrossProperties, floating-image style traits)
  → CrossCanvas: inline React styles on <button> (canvas-only duplicate path)
  → FloatingImageCanvas: resolveFloatingImageShellStyle + buildFloatingImageTagStyle
  → cross/serialize.ts: inline desktop shell + painted on <button>
  → floating-image/serialize.ts: inline desktop shell on <div>, inline img painted
  → buildCrossFloatingImageResponsiveCss: mobile margin offsets in embedded step CSS
  → buildEmbeddedStepResponsiveCss → per-step <style data-adflipr-popup-device-css>
  → compilePopupRenderCss (no positioned-decorative compiler before 5E)
```

---

## C. Exact Cross DOM contract

**Serialized export HTML:**

```html
<button
  type="button"
  aria-label="Close"
  data-adflipr-popup-close="true"
  data-adflipr-block-id="{blockId}"
  …hide attrs…
>&#215;</button>
```

| Concern | Owner node | Selector |
| ------- | ---------- | -------- |
| Identity | `<button>` | `[data-adflipr-popup-close="true"][data-adflipr-block-id="{id}"]` |
| Positioning (top/right/bottom/left) | same `<button>` | artifact-scoped close button selector |
| Margin → position offsets | same `<button>` | via `marginPositionCss(position, margin)` |
| Painted chrome (color, bg, padding, radius, fontSize) | same `<button>` | same selector |
| Close behavior | same `<button>` | `data-adflipr-popup-close="true"` + runtime listener |
| Content (× glyph) | same `<button>` | `&#215;` text node |

**Answers:**

- **top/right/bottom/left:** close `<button>` (not a wrapper).
- **margin:** close `<button>` (converted to offset px via position anchor).
- **visual button presentation:** close `<button>`.
- **close behavior:** close `<button>` (`data-adflipr-popup-close`).

---

## D. Exact Floating Image DOM contract

**Serialized export HTML:**

```html
<div data-block-type="floating-image" data-adflipr-block-id="{blockId}" aria-hidden="true|false" …>
  <img src="…" alt="…" />
  <!-- optional -->
  <a href="…" target="_blank" rel="noopener noreferrer" style="pointer-events:auto">
    <img … />
  </a>
</div>
```

| Concern | Owner node | Selector |
| ------- | ---------- | -------- |
| Identity | wrapper `<div>` | `[data-adflipr-block-id="{id}"][data-block-type="floating-image"]` |
| Positioning | wrapper `<div>` | artifact-scoped shell selector |
| Margin → position offsets | wrapper `<div>` | via `marginPositionCss` |
| Image presentation (width, height, object-fit, border-radius) | `<img>` descendant | `… img` painted selector |
| Optional link behavior | `<a>` wrapper | `href`, `target`, `rel`; `pointer-events:auto` inline (structural/behavior) |
| Media | `<img>` | `src`, `alt` attributes |

**Answers:**

- **top/right/bottom/left:** wrapper `<div>`.
- **margin:** wrapper `<div>`.
- **image presentation:** `<img>` (compiled via descendant selector).
- **optional link behavior:** `<a>` around img when `linkUrl` set.

---

## E. Trait/property ownership matrix

| Property | Cross | Floating image | Class | Phase 5E action |
| -------- | ----- | -------------- | ----- | --------------- |
| `position` | ✓ | ✓ | A | migrate (anchor; shared semantics) |
| `marginDesktop` / `marginMobile` | ✓ | ✓ | A | migrate → offset rules |
| `color`, `backgroundColor` | ✓ | — | A | migrate (cross painted) |
| `padding`, `cornerRadius` / `borderRadius` | ✓ | — | A | migrate (cross painted) |
| `size` / `customSize` → `fontSize` | ✓ | — | A | migrate (cross icon size) |
| `style.imageWidthPx`, `imageHeightPx`, `imageFullWidth`, `imageWidth` | — | ✓ | B | migrate on `<img>` |
| `style` corner radius on image | — | ✓ | B | migrate on `<img>` |
| `zIndex` | — | ✓ | A | migrate on shell |
| `imageUrl`, `imageAlt` | — | ✓ | C | structural attributes |
| `linkUrl` | — | ✓ | D | behavior (`<a>`) |
| `pointer-events:none` on shell | — | ✓ | C | structural (shell) |
| `pointer-events:auto` on link | — | ✓ | D | behavior inline |
| `data-adflipr-popup-close` | ✓ | — | D | behavior attribute |
| `position:absolute` | ✓ | ✓ | C | structural shell constant |
| `z-index:40` (cross) | ✓ | — | C | structural shell constant |
| `border:none`, `cursor:pointer` (cross) | ✓ | — | C | structural |
| `hoverColor` / `hoverBackgroundColor` | ✓ | — | D | canvas runtime only |

---

## F. Compiler topology decision

**Chosen: B — One module containing separate Cross and Floating Image owner compilers**

File: `utils/popupHtmlSerializer/positionedDecorativePresentation.ts`

**Rationale:**

- Both families share: `PositionedShellPosition`, `marginPositionCss`, desktop/mobile margin resolvers, artifact scoping, DIFF-ONLY mobile emission, multi-step union/conflict collectors.
- They differ in: DOM selector shape (close button vs shell div + img descendant), painted property sets (cross button chrome vs img dimensions), structural shell constants.
- Different HTML tags alone do not justify split; different painted targets do justify separate owner emission loops within one module.
- Not merged into a single generic “positioned block” compiler — explicit per-type normalizers and selectors remain.

---

## G. Canonical normalization decision

**Path:**

```text
model traits
  → resolveCrossShellCssRules / resolveFloatingImageShellCssRules + img rules
  → normalizeCrossPresentation / normalizeFloatingImagePresentation
  → canvas adapters (floating-image shell + img)
  → compilePositionedDecorativeRenderCss
```

**Resolvers (canonical):**

| Function | Role |
| -------- | ---- |
| `resolveCrossPositionOffsetRules` | position anchor + margin → top/right/bottom/left |
| `resolveCrossPaintedCssRules` | color, bg, padding, radius, fontSize |
| `resolveCrossShellCssRules` | structural + offsets + painted |
| `resolveFloatingImageShellCssRules` | structural + zIndex + offsets |
| `resolveFloatingImageImgCssRules` | img dimensions, object-fit, border-radius |

**Canvas delegation:**

- `resolveFloatingImageShellStyle.ts` → `resolveFloatingImageShellCssRules`
- `buildFloatingImageTagStyle.ts` → `resolveFloatingImageImgCssRules`

**Not yet delegated (canvas-only duplicate, export path canonical):**

- `CrossCanvas.tsx` — still inline React styles; same numeric semantics as resolvers; out of export scope.

**Deleted duplicate paths:**

- Inline desktop generation in `cross/serialize.ts`, `floating-image/serialize.ts`
- `buildCrossFloatingImageResponsiveCss` embedded mobile path

---

## H. Before architecture

```text
Desktop: inline style on cross <button> and floating <div>/<img>
Mobile:  buildCrossFloatingImageResponsiveCss → embedded step @media (margin offsets only)
Cross painted: inline on button (always desktop device)
Floating img painted: inline on <img> (always desktop device)
Cascade: PCL-008 — mobile @media loses to inline desktop
```

---

## I. After architecture

```text
Desktop + mobile: compilePositionedDecorativeRenderCss → artifact renderCss
  [data-adflipr-popup-artifact="{scope}"] [close button / shell / img selectors]
  base rules + @media (max-width: 480px) DIFF-ONLY mobile overrides
HTML: structure, identity, behavior attrs only (no merchant presentation inline)
Embedded step CSS: cross/floating-image excluded entirely
```

---

## J. Desktop/mobile cascade strategy

**Strategy: DIFF-ONLY** (same pattern as Text/Column families)

| Family | Unset mobile behavior | Inherit desktop? | Stale coordinate risk |
| ------ | --------------------- | ---------------- | --------------------- |
| Cross | `resolveCrossMargin` falls back to `CROSS_SHELL_MARGIN_DEFAULTS.mobile` | Explicit mobile defaults, not silent CSS inherit | Low — anchor side fixed per `position` trait; only offset values differ |
| Floating image | `resolveFloatingImageMargin` → `FLOATING_IMAGE_SHELL_MARGIN_DEFAULTS.mobile` | Same | Low — same anchor semantics |

Mobile block emits only declarations where normalized mobile ≠ desktop. When traits match across devices, no mobile rule is emitted (desktop base stands).

---

## K. Anchor-switch/reset semantics

**Model constraint:** Single `position` trait (`top-left` | `top-right` | `bottom-left` | `bottom-right`) — **no per-device position anchor**.

Anchor-side switching across desktop/mobile is **not supported** by the model. `marginPositionCss` always emits only the two sides relevant to the chosen anchor; opposite sides are never set, so no stale `right` when mobile uses `left` on a different anchor.

**Helper retained for future:** `buildMobilePositionOverrideRules` — full mobile offset emission if device-specific anchors are added later. **Not wired** in Phase 5E compiler.

---

## L. Multi-step union and conflicts

Collectors: `collectArtifactCrossPresentations`, `collectArtifactFloatingImagePresentations`

| Case | Behavior |
| ---- | -------- |
| Same `blockId` + identical normalized presentation | Emit once; `stepIds` unioned |
| Same `blockId` + conflicting presentation | `PositionedDecorativeConflictError` with `blockId`, `blockType`, `stepIds` |
| Different `blockId` | Separate rules |
| Same presentation, different non-presentation props (e.g. `closeOnEsc`) | No conflict — presentation key ignores behavior-only props |

Ordering: deterministic `localeCompare` on `blockId`.

---

## M. Inline CSS removed

| Location | Removed |
| -------- | ------- |
| `cross/serialize.ts` | All `style` — position, margin, color, bg, padding, radius, fontSize |
| `floating-image/serialize.ts` | Shell positioning + img painted inline |
| `responsiveDeviceCss.ts` | Cross/floating branches in embedded generator |

**Retained inline:**

| Location | Property | Classification |
| -------- | -------- | -------------- |
| `floating-image/serialize.ts` `<a>` | `pointer-events: auto` | BEHAVIOR |

---

## N. Embedded CSS removed

From `buildEmbeddedStepResponsiveCss()` composition:

- `buildCrossFloatingImageResponsiveCss()` — **deleted**
- `buildPositionedShellMobileMarginCss()` — **deleted**
- `hasCrossOrFloatingImageMarginDiff()` — **deleted** (was dead)

`buildBlockTraitResponsiveCss` already `continue`s for `cross` and `floating-image`.

---

## O. Old generator deletion

| Item | Status |
| ---- | ------ |
| `buildCrossFloatingImageResponsiveCss` | **DELETED** |
| Production references | **0** (verified grep) |
| KB/historical docs | References remain in audit docs only |

---

## P. Behavior preservation

| Behavior | Status |
| -------- | ------ |
| Cross close (`data-adflipr-popup-close`) | PASS — attribute preserved |
| Accessibility (`aria-label="Close"`, `type="button"`) | PASS |
| Floating image `src` / `alt` | PASS |
| Optional floating-image link (`href`, `target`, `rel`) | PASS |
| Canvas interaction / selection | PASS — no canvas structural changes |
| Embed runtime close handler | PASS — unchanged |

---

## Q. Phase 0 mapping before implementation

| ID | Owner | Desktop source | Mobile source | Expected fix |
| -- | ----- | -------------- | ------------- | ------------ |
| `cross-top` | close button | inline `top` | embedded @media | renderCss |
| `cross-right` | close button | inline `right` | embedded @media | renderCss |
| `floating-bottom` | shell div | inline `bottom` | embedded @media | renderCss |
| `floating-left` | shell div | inline `left` | embedded @media | renderCss |

---

## R. Phase 0 scoreboard before/after

| Metric | Before Phase 5E | After Phase 5E |
| ------ | --------------- | -------------- |
| Migrated risk green | 12 | **16** |
| Risk red | 6 | **2** |
| Controls green | 5 | **5** |

**Newly green:** `cross-top`, `cross-right`, `floating-bottom`, `floating-left`

**Still red:** `teaser-padding`, `teaser-border-radius`

---

## S. Consumer verification

All consumers use `serializePopupExport` → `compilePopupRenderCss` → `renderCss`:

| Consumer | Path | Status |
| -------- | ---- | ------ |
| Storefront embed | `embed/runtime.ts` + `mountRenderArtifactCss` | PASS |
| Builder preview | `FormPreview.tsx` → `formPreviewEmbedPresentation.ts` | PASS |
| Fullscreen preview | `previewArtifactExport.ts` | PASS |
| Popup detail thumbnail | `popupThumbnailIframe.test.ts` | PASS |
| Campaign list thumbnail | same export path | PASS |
| Live embed test | export contract unchanged | PASS |

No consumer-specific changes required — positioned decorative CSS rides existing `renderCss` hoisting.

---

## T. Deleted production code

| Item | Location |
| ---- | -------- |
| `buildCrossFloatingImageResponsiveCss` | `responsiveDeviceCss.ts` |
| `buildPositionedShellMobileMarginCss` | `responsiveDeviceCss.ts` |
| `hasCrossOrFloatingImageMarginDiff` | `responsiveDeviceCss.ts` |
| Cross inline merchant `style` generation | `cross/serialize.ts` |
| Floating shell + img inline styles | `floating-image/serialize.ts` |
| Unused margin imports in `responsiveDeviceCss.ts` | cleaned |

---

## U. Remaining `buildBlockTraitResponsiveCss` consumers

Function **retained** — generic wrapped block families still depend on it:

`divider`, `text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, `check-box`, `radio`, `date`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social`

**Excluded (migrated or dedicated):** `button`, `text`, `cross`, `floating-image`, layouts, columns (renderCss).

**Future deletion:** when last family migrates off generic trait builder.

---

## V. Test evidence

| Suite | File | Coverage |
| ----- | ---- | -------- |
| Unit compiler | `positionedDecorativePresentationRenderCss.test.ts` | Scoping, desktop/mobile, dedupe, conflict, export negative architecture, behavior attrs |
| Chromium computed | `positionedDecorativePresentation.browser.test.ts` | cross-top/right, floating-bottom/left, no inline on owners |
| Phase 0 matrix | `responsiveCssBaseline.browser.test.ts` | 16 green / 2 red scoreboard |
| Serializer | `popupHtmlSerializer.test.ts` | Cross/floating in renderCss not inline/embedded |
| DOM ownership | `blockDomOwnership.browser.test.ts` | Cross identity/painted/behavior same node |
| Consumer | `popupThumbnailIframe.test.ts`, `mountRenderArtifactCss.test.ts` | renderCss injection |

**Run:**

```bash
cd adflipr-app && npm test -- --watchAll=false \
  --testPathPattern="positionedDecorative|responsiveCssBaseline.browser|popupHtmlSerializer.test"
```

---

## W. Architecture verification

| Constraint | Status |
| ---------- | ------ |
| No `!important` on positioned-decorative rules | ✅ |
| Artifact scope `[data-adflipr-popup-artifact]` | ✅ |
| Stable block identity selectors | ✅ |
| No global type / nth-child selectors | ✅ |
| No legacy/new branches | ✅ |
| No feature flags | ✅ |
| Integrated only via `compilePopupRenderCss()` | ✅ |
| No presentation registry / universal compiler | ✅ |
| Old embedded generator deleted | ✅ |

---

## X. Abstraction threshold decision

**DEFER EXTRACTION**

**Evidence:**

- `buttonPresentation`, `textPresentation`, `positionedDecorativePresentation`, `columnPresentation` share mechanical patterns (collect → normalize → artifact scope → DIFF-ONLY mobile) but differ in DOM topology (single node vs split wrapper/content vs positioned shell + painted child).
- Positioned decorative adds anchor/margin semantics not present in button/text.
- Small duplication in `diffMobilePresentation`, conflict collector shape, and `@media` wrapper is acceptable until Teaser migration proves a third positioned/surface pattern.

**Do not extract:** universal block compiler, generic presentation registry, mega family abstraction.

---

## Y. Next recommended phase

**Teaser presentation migration** — only remaining Phase 0 red cases (`teaser-padding`, `teaser-border-radius`). Teaser uses form-surface-level traits and dedicated `buildTeaserTraitResponsiveCss`; treat as surface/sibling family, not positioned decorative.

**Optional follow-up (non-blocking):** delegate `CrossCanvas.tsx` to `resolveCrossShellCssRules` for builder WYSIWYG parity with export.

---

## Files touched (production)

| File | Change |
| ---- | ------ |
| `utils/popupHtmlSerializer/positionedDecorativePresentation.ts` | **New** family compiler + canonical resolvers |
| `utils/popupHtmlSerializer/compilePopupRenderCss.ts` | Wire positioned-decorative compiler |
| `components/form-builder/blocks/cross/serialize.ts` | Structure/behavior only |
| `components/form-builder/blocks/floating-image/serialize.ts` | Structure/behavior only; link `pointer-events` retained |
| `utils/popupHtmlSerializer/responsiveDeviceCss.ts` | Delete cross/floating embedded generator |
| `components/form-builder/blocks/shared/resolveFloatingImageShellStyle.ts` | Delegate to canonical resolver |
| `components/form-builder/blocks/shared/buildFloatingImageTagStyle.ts` | Delegate to canonical resolver |
| `utils/__tests__/positionedDecorativePresentationRenderCss.test.ts` | **New** unit tests |
| `utils/__tests__/positionedDecorativePresentation.browser.test.ts` | **New** Chromium tests |
| `utils/__tests__/responsiveCssBaseline.browser.test.ts` | Scoreboard 16/2/5 |
| `utils/__tests__/popupHtmlSerializer.test.ts` | Updated cross/floating expectations |
