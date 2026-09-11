# Phase 5C — Button presentation acceptance audit

**Status:** Acceptance review (2026-07-05)  
**Reviewer stance:** Principal Engineer — pattern repeatability before Text migration  
**Related:** [`popup-button-presentation-css-phase5c-report.md`](./popup-button-presentation-css-phase5c-report.md), [`popup-block-ownership-contract-phase5b-audit.md`](./popup-block-ownership-contract-phase5b-audit.md)

---

## A. Executive verdict

**Phase 5C establishes a correct, repeatable family-compiler pattern.** The Button implementation is not a one-off architecture; it mirrors the proven column compiler shape (collect → normalize → conflict → deterministic emit → artifact scope → base + mobile `@media`).

**Principal Engineer classification: B — Approved after narrow cleanup (documentation-only; no blocking code change).**

No Button-specific abstractions block Text migration. Two non-blocking items should be documented before copying:

1. **Canvas/export drift:** alignment container `text-align` is inline on export but omitted on canvas.
2. **Painted mobile emission:** Button uses a **full painted rule block** inside `@media` when diffs exist; column uses **diff-only** mobile chunks. Both are cascade-safe; Text should choose explicitly.

**No corrections were made during this acceptance audit** — traced production code and tests satisfy acceptance gates.

---

## B. Report-vs-code verification

| Claim | Report says | Production code says | Match? | Evidence |
| ----- | ----------- | -------------------- | ------ | -------- |
| Wrapper block margin absent inline | Stripped from wrapper; in renderCss | `serializeElements.ts` deletes `margin*` when `type === 'button'`; `normalizeButtonPresentation` → `wrapper.base.margin` via `resolveDeviceMarginCss` | **YES** | `serializeElements.ts:38-44`, `buttonPresentation.ts:46-56` |
| `<button>` merchant presentation absent inline | No `style` on `<button>` | `serializeButton` emits `<button>` with attrs only; `openTag('button', buttonAttrs)` — no `style` | **YES** | `button/serialize.ts:55-58` |
| Button excluded from generic block responsive | `continue` on `type === 'button'` | `buildBlockTraitResponsiveCss` line 381 | **YES** | `responsiveDeviceCss.ts:381` |
| renderCss artifact-scoped | `[data-adflipr-popup-artifact="{scope}"]` | `buttonBlockSelector` uses `POPUP_ATTR_ARTIFACT` + escaped scope | **YES** | `buttonPresentation.ts:149-157` |
| Desktop/mobile painted rules target `<button>` | Painted selector ends with `button` | `buttonPaintedSelector` → `… button` | **YES** | `buttonPresentation.ts:155-157, 204-228` |
| Desktop/mobile block margin on wrapper | Wrapper selector without `button` | `wrapperSelector` + `wrapper.base` / `wrapper.mobile` margin | **YES** | `buttonPresentation.ts:199-214` |
| No `!important` on Button rules | Asserted in tests | `compileButtonPresentationRenderCss` emits plain declarations | **YES** | `buttonPresentationRenderCss.test.ts:96` |
| No compatibility branch | Forward-only | No feature flags or legacy paths in button compiler | **YES** | `buttonPresentation.ts`, `compilePopupRenderCss.ts` |
| No runtime behavior change | Presentation-only | `serializeButton` preserves `type`, action attrs, label; embed runtime untouched | **YES** | `button/serialize.ts`, `embed/runtime.ts` |
| Duplicate id dedup | Same presentation → emit once | `collectArtifactButtonPresentations` Map by blockId + `presentationKey` | **YES** | `buttonPresentation.ts:105-146` |
| Conflicting duplicate fails loudly | `ButtonPresentationConflictError` | Thrown with `blockId` + `stepIds` | **YES** | `buttonPresentation.ts:80-91, 121-122` |
| All popup steps included | Scan `formElementsByStep` | `for (const [stepId, elements] of Object.entries(formElementsByStep))` | **YES** | `buttonPresentation.ts:110` |
| Phase 0: 8→6 red, block-padding/margin green | Scoreboard updated | `RENDER_CSS_MIGRATED_RISK_IDS` includes both; browser tests pass | **YES** | `responsiveCssBaseline.browser.test.ts:133-146`; test run 61/61 pass |
| `elementBackgroundColor` on align `> div` | renderCss align container | `normalizeButtonPresentation.alignContainer.backgroundColor` | **YES** | `buttonPresentation.ts:62-64, 194-196` |
| `getButtonStyle` shared normalizer | Thin canvas wrapper over `resolveButtonPaintedCssRules` | `getButtonStyle` spreads structural + painted rules | **YES** | `ElementStyles.ts:403-407` |
| `buildButtonContainerStyle` deleted | Removed obsolete helper | Grep: no references; function absent from `inputFieldStyles.ts` | **YES** | repo search |
| Hover colors in export | Not mentioned as migrated | Canvas-only `onMouseEnter/Leave`; not in compiler | **PARTIAL** | Intentional — builder preview behavior, not storefront CSS (acceptable) |

**Authoritative source:** production code. Report matches with one intentional omission (hover = canvas-only).

---

## C. Button compiler responsibility classification

| Responsibility | Class | Notes |
| -------------- | ----- | ----- |
| `collectArtifactButtonPresentations` | **C** | Same semantics as column collector; defer shared extraction until 3+ families |
| `ButtonPresentationConflictError` | **C** | Family-named error; pattern repeats per family |
| Deterministic `localeCompare` sort | **C** | Identical to column |
| `buttonBlockSelector` / painted / align selectors | **A** | Button DOM contract |
| `normalizeButtonPresentation` | **A** | Button wrapper + align + painted partition |
| `resolveButtonPaintedCssRules` consumption | **A** | Button painted semantics (imported, not owned here) |
| `diffMobilePresentation` | **C** | Generic map diff; column inlines equivalent logic in `normalizeColumnPresentation` |
| `declarationsFromRules` | **B** | Duplicated in `columnPresentation.ts`, `columnsContainerPresentation.ts` — should use shared emitter when a third copy is added |
| `compileButtonPresentationRenderCss` emit loop | **A** | Button-specific owners (wrapper / align / painted) |
| Full painted mobile merge in `@media` | **A** | Button-specific defensive cascade choice |
| `BUTTON_PAINTED_STRUCTURAL_STYLES` in renderCss | **A** | Button flex shell |
| `elementBackgroundColor` on `> div` | **A** | Button-only trait today |
| Re-call `resolveButtonPaintedCssRules` in compile loop | **E** | Minor redundancy — `normalizeButtonPresentation` already computed mobile device rules; no drift risk (same inputs) |

No **D** (misplaced ownership) or **F** (test-only leakage) in production compiler.

---

## D. Repeatable family pattern verdict

**If Text copies `buttonPresentation.ts`, expected duplication:**

| Mechanism | Button | Text | Input | Image |
| --------- | ------ | ---- | ----- | ----- |
| Step scan + Map dedup | copy pattern | copy pattern | copy pattern | copy pattern |
| Conflict error class | Button-specific | Text-specific | Input-specific | Image-specific |
| Deterministic sort | identical | identical | identical | identical |
| `cssEscapeAttributeValue` | shared (`artifactCssUtils`) | shared | shared | shared |
| `declarationsFromRules` | local copy | local copy | local copy | local copy |
| Normalizer function | `normalizeButtonPresentation` | `normalizeTextPresentation` | `normalizeInputShellPresentation` | `normalizeImagePresentation` |
| Painted selector | `… button` | `… > div` (content) | outline `div` + `input` | `img` |
| Wrapper layout rules | block margin only | block margin + width? | block margin + width | block margin |
| Align intermediate | `> div` (Button-only) | unlikely | floating shell | N/A |
| Structural painted shell | flex button | text flow | input chrome | object-fit |

**Recommend extraction only when:** `declarationsFromRules` appears in a 4th compiler OR identical collector/conflict primitives are copy-pasted with no ownership variation. **Do not extract** painted selectors, normalizers, or DOM owner maps.

**Verdict:** Pattern is **repeatable** — Text should copy the **collector → normalize → conflict → compile** skeleton, not Button painted rules.

---

## E. elementBackgroundColor ownership decision

### Trace summary

| Question | Answer |
| -------- | ------ |
| Button-specific? | **Yes** — only `ButtonColors.tsx`, `ButtonCanvas.tsx`, `buttonPresentation.ts` reference it |
| Generic block trait? | **No** — not on text, input, image, or shared trait panels |
| Block types supporting it | **Button only** |
| Visual target | Paints the **alignment container** (`> div`) behind the `<button>`, not the wrapper shell |
| Stable semantic owner? | **Yes for Button** — stable `wrapper > div > button` export shape since pre-5C |
| Text/Image/Input need it? | **No evidence** — Text uses `backgroundColor` on inner content, not `elementBackgroundColor` |
| Per-family duplication risk? | **Low** — trait is Button-only |
| Generic block-layout compiler cleaner? | **Not yet** — only one consumer |

### Decision: **A — Keep in Button compiler**

`elementBackgroundColor` is a Button-specific merchant trait. Moving it to a generic compiler would invent ownership without a second proven consumer. Revisit if another family gains the same field.

---

## F. Alignment-container ownership review

### Current split

| Property | Owner | Source | Classification |
| -------- | ----- | ------ | -------------- |
| `width: 100%` | Align `> div` | HTML inline | Structural |
| `text-align` (align trait) | Align `> div` | HTML inline | Structural alignment (legacy) |
| `elementBackgroundColor` | Align `> div` | renderCss | Merchant presentation (Button-only) |
| Fit positioning | `<button>` | renderCss `margin: 0 auto` / etc. | Painted |
| Full width | `<button>` | renderCss `width: 100%` | Painted |

### Analysis

- **`width: 100%`** — structural; ensures align box spans column.
- **`text-align`** — export applies via `buildButtonAlignContainerStyle`; **canvas omits it** (`ButtonCanvas.tsx:46-49`). For **fit-width**, positioning uses painted `margin` auto rules; `text-align` is largely redundant. For **full-width**, button is 100% flex — redundant. No active conflict observed in tests.
- **Both systems** — fit margins (painted) + text-align (structural) can coexist; margins win for block positioning.
- **Historical wrapper** — yes, the inner `div` predates 5C; it exists to hold background behind the button. Not accidental, but Text likely **does not** need an equivalent.

**Recommendation:** Keep alignment container for Button. Document canvas `text-align` omission as low-priority WYSIWYG drift. Text migration should **not** copy Button alignment-container pattern unless Text audit proves an equivalent DOM shell.

---

## G. Normalization and drift analysis

### Trait matrix

| Trait | Canvas path | Compiler path | Shared? | Drift risk |
| ----- | ----------- | ------------- | ------- | ---------- |
| padding | `resolveButtonPaintedCssRules` via `getButtonStyle` | same | **YES** | Low |
| border-radius | same | same | **YES** | Low |
| background | same | same | **YES** | Low |
| border | same | same | **YES** | Low |
| shadow | `mergeBoxShadowIntoCssRules` | same | **YES** | Low |
| font-family | same | same | **YES** | Low |
| font-size | `resolveInputFontSizePx` | same | **YES** | Low |
| font-weight | same (`'medium'` string) | same | **YES** | Low — CSS weight naming pre-existing |
| font-style | same | same | **YES** | Low |
| color (text) | same | same | **YES** | Low |
| line-height | same | same | **YES** | Low |
| letter-spacing | same | same | **YES** | Low |
| width mode | same | same | **YES** | Low |
| fixed width | same | same | **YES** | Low |
| fixed height | same | same | **YES** | Low |
| button position | `buttonPosition \|\| elementPosition` | same | **YES** | Low |
| block margin | canvas wrapper via `getBlockLayoutStyle` | `resolveDeviceMarginCss` in compiler | **Split by design** | Low — export strips wrapper margin inline |
| elementBackgroundColor | canvas align div inline | renderCss align rule | **Split by design** | Low — intentional owner split |
| align `text-align` | **not on canvas** | serialize inline | **NO** | **Minor WYSIWYG drift** |
| hover colors | canvas JS only | not exported | N/A | Acceptable — preview-only |

**Canonical normalization: PASS** for painted traits (single `resolveButtonPaintedCssRules`).

**Note:** `buttonAlign` parameter is passed to `resolveButtonPaintedCssRules` but **unused** in the function body — align is handled on the container, not painted rules. Dead parameter; harmless drift risk.

---

## H. Base/mobile diff safety

### Strategy

1. **Normalize** desktop and mobile device maps via `resolveButtonPaintedCssRules(style, align, device)`.
2. **Diff** with `diffMobilePresentation(base, mobileDevice)` — property-level string equality on normalized maps.
3. **Wrapper mobile:** emit only when `mobileMargin !== desktopMargin`.
4. **Painted mobile:** when diff keys non-empty, emit **full** painted rule (structural + base + diffs) inside `@media max-width: 480px`.

### Edge cases

| Case | Behavior | Safe? |
| ---- | -------- | ----- |
| Desktop padding set, mobile unset | Mobile inherits desktop via `resolveDevicePaddingCss` (`paddingMobile \|\| paddingDesktop`) | **YES** — no false diff |
| Desktop padding = mobile padding | No painted mobile chunk | **YES** |
| Desktop/mobile margin differ | Wrapper mobile chunk only | **YES** |
| Zero values | `strictSpacingSidesBoxToCss` / parse rules | **YES** |
| Empty/undefined | Omitted from declarations (`filter value !== ''`) | **YES** |
| Shorthand/longhand | Single shorthand keys (`padding`, `margin`) — no longhand split | **YES** |
| Mobile must reset desktop | Full painted mobile block re-declares all painted props when any diff exists | **YES** — defensive vs column diff-only |

**Verdict: PASS** — safe to repeat for Text; Text may use column-style diff-only mobile if painted owner has no structural shell merge complexity.

---

## I. Multi-step and conflict semantics

| Rule | Implementation | Verdict |
| ---- | -------------- | ------- |
| Same id + same presentation → dedupe | `presentationKey = JSON.stringify(normalizeButtonPresentation(...))` | **PASS** |
| Same id + different presentation → fail | `ButtonPresentationConflictError(blockId, stepIds)` | **PASS** |
| Content/label differences | Not in `presentationKey` — same style + align → no conflict | **PASS** — CSS compiler is presentation-only |
| Error contains block id | `blockId` on error class + message | **PASS** |
| Error contains step ids | `[...existing.stepIds, stepId]` | **PASS** |
| Deterministic ordering | `sort((a,b) => a[0].localeCompare(b[0]))` | **PASS** |

Duplicate block ids with different labels but identical presentation are **valid** for CSS compilation (shared block across steps). Content validation is outside CSS compiler scope — correct boundary.

---

## J. Browser harness timing decision

### Why polling was needed

After `page.setViewportSize` to mobile, immediate `getComputedStyle` sometimes returned **desktop-era values** (~17–20px padding instead of 8px) even though correct `@media` rules were mounted. Triple `requestAnimationFrame` in `setViewportMode` was insufficient alone.

### Implementation

`assertResponsiveComputed` uses `page.waitForFunction` until normalized computed value **equals expected** (3s timeout cap). This is **not** blind sleep — it waits on a deterministic browser condition.

### Production relevance

Storefront embed loads with a fixed viewport; `@media` applies at first layout. No viewport-resize race in production. **Not a production defect.**

### Could it hide bugs?

- **CSS mount order:** unlikely — would fail to converge within 3s.
- **Duplicate stylesheets:** would show wrong stable value, not delayed convergence.
- **JS mutation:** Button export has no post-mount style mutation.

### Decision: **B — Replace with deterministic computed-style wait** (already implemented via `waitForFunction`)

The 3s value is a **timeout ceiling**, not a fixed delay. Acceptable. Optional future tightening: reduce timeout after measuring p99 convergence (<100ms in practice).

**Not D (production defect). Not raw polling loop.**

---

## K. Generic responsive generator cleanup verification

### `buildBlockTraitResponsiveCss`

```typescript
if (element.isLayout || element.type === 'cross' || element.type === 'floating-image' || element.type === 'button') {
  continue;
}
```

Clean type-check exclusion — no Button branches later in function.

### Active Button CSS paths

| Path | Status |
| ---- | ------ |
| `compileButtonPresentationRenderCss` | **Active** — renderCss |
| `serializeButton` align div inline (structural) | **Active** |
| `buildBlockTraitResponsiveCss` for button blocks | **Removed** |
| Product `[data-adflipr-product-button]` rules | **Active** — product family, not content button blocks |

### Dead / unexpected paths

| Path | Status |
| ---- | ------ |
| `buildButtonContainerStyle` | **Dead** — deleted |
| Inline `<button style>` | **Dead** |
| Wrapper margin inline for button | **Dead** — stripped |
| Duplicate button mobile in embedded CSS | **None found** — `buttonPresentationRenderCss.test.ts:284` asserts `deviceCss` lacks `btn-1` |

**Verdict: COMPLETE** for Button block family.

---

## L. Permanent-hybrid audit

| Style concern | Classification | Owner | Correct? |
| ------------- | -------------- | ----- | -------- |
| Wrapper block margin | renderCss wrapper layout | wrapper | **YES** |
| Wrapper padding | none | — | **YES** |
| Align `width: 100%` | HTML structural inline | align `> div` | **YES** — not merchant chrome |
| Align `text-align` | HTML structural inline | align `> div` | **YES** — structural; redundant with fit margins |
| `elementBackgroundColor` | renderCss alignment presentation | align `> div` | **YES** — Button-only trait |
| Painted padding/radius/bg/border/shadow/type | renderCss painted | `<button>` | **YES** |
| `display:flex`, `align-items`, `justify-content` | renderCss structural painted | `<button>` | **YES** — flex shell, not merchant trait |
| `box-sizing`, `max-width`, `min-width` | renderCss structural painted | `<button>` | **YES** |
| Fit `margin: auto` | renderCss painted | `<button>` | **YES** — not block margin |
| Hover bg/color | canvas JS only | — | **YES** — preview-only, not export |
| Embedded step Button trait CSS | none | — | **YES** |

**Permanent merchant hybrid on same DOM owner: NONE**

---

## M. Consumer verification

| Consumer | Path | HTML + renderCss | Evidence |
| -------- | ---- | ---------------- | -------- |
| Storefront embed | `embed/runtime.ts` → `mountRenderArtifactCss` | Both from API `renderCss` | `renderArtifactCssLifecycle.test.ts` |
| Builder preview | `formPreviewEmbedPresentation.ts` → `mountRenderArtifactCss` | `buildPreviewArtifactExport` → `serializePopupExport` | `FormPreview.tsx` |
| Fullscreen preview | `previewArtifactExport.ts` | Same export path | `buildPreviewArtifactExport` |
| Popup detail thumbnail | `popupThumbnailIframe.ts` | `buildPopupThumbnailIframeDocument(html, renderCss)` | `popupThumbnailArtifact.browser.test.ts` |
| Campaign list thumbnail | `PopupThumbnailView.tsx` | `normalizeCompiledPreviewArtifact({ html, renderCss })` | `popupThumbnailIframe.test.ts` |

All consumers use **`serializePopupExport` → `compilePopupRenderCss`** — Button CSS included automatically. No consumer-specific Button branch required.

**Button-specific computed-style:** `buttonPresentation.browser.test.ts`, `blockDomOwnership.browser.test.ts`, Phase 0 baseline.

**Verdict: PASS**

---

## N. Corrections made during acceptance

**None.** Audit found no defect requiring pre-Text code change.

Documented for follow-up (non-blocking):

1. Canvas alignment container missing `text-align` (export has it).
2. `declarationsFromRules` triplicated across compilers — defer shared extraction.
3. Unused `buttonAlign` param in `resolveButtonPaintedCssRules`.

---

## O. Remaining risks

| Risk | Severity | Mitigation |
| ---- | -------- | ---------- |
| Canvas/export `text-align` drift on align container | Low | Optional canvas sync; does not affect storefront |
| `fontWeight: 'medium'` as CSS value | Low | Pre-existing; browser resolves |
| Hover colors not in export CSS | Low | Product decision — preview-only today |
| Full painted mobile block vs diff-only (column pattern) | Low | Document choice per family |
| `declarationsFromRules` copy-paste across compilers | Medium (maintainability) | Extract after 4th family or explicit cleanup sprint |
| Duplicate block ids across steps with different content | Low | Valid for CSS; model validation is separate concern |

---

## P. Text migration reuse boundary

### Reuse conceptually

```text
familyCollector(formElementsByStep)
  → filter by type
  → Map by block id
  → presentationKey = JSON.stringify(normalizeFamilyPresentation(...))
  → conflict error with blockId + stepIds
  → sort by id
  → for each: artifact-scoped wrapper selector + painted selector
  → base declarations
  → @media max-width mobile (wrapper diffs + painted diffs)
  → integrate only via compilePopupRenderCss()
```

### Text must NOT copy

- `button` painted selector (`… button`)
- `BUTTON_PAINTED_STRUCTURAL_STYLES` (flex shell)
- Fit-width `margin: 0 auto` positioning model
- `buttonPosition` / `buttonWidth` / `fixedWidth` / `fixedHeight` traits
- Alignment `> div` + `elementBackgroundColor` (Button-only)
- `properties.align` on container (`text` uses `textAlignment` on inner content)
- Full painted mobile merge strategy **unless** Text painted owner needs structural shell in `@media`

### Text-specific expectations (from Phase 5B)

- Wrapper: block margin
- Painted: inner content `div` (padding, typography, radius, etc.)
- RTE link color rules → `textPresentation` compiler

---

## Q. Final Principal Engineer decision

**Phase 5C succeeded.** The Button family compiler is the correct first instance of the block-family pattern approved in Phase 5B. It does not introduce Button-specific architecture that would poison Text migration.

**Proceed to Text (Phase 5D) after review approval** — copy the collector/normalize/conflict/compile skeleton; implement Text-specific DOM owners and `normalizeTextPresentation`; do not extract a universal block compiler.

---

## Test evidence (acceptance run)

```
Test Suites: 6 passed (buttonPresentation*, blockDomOwnership, responsiveCssBaseline, popupThumbnail*)
Tests:       61 passed
```

Phase 0 scoreboard: **12 migrated green / 6 risk red / 5 controls green** — `block-padding`, `block-margin` green.
