# Track 2A — Post-Migration Deferred CSS Debt Truth Audit

**Date:** 2026-07-06  
**Branch:** `css-reft`  
**HEAD:** `291c68dae075aa1610d6c44e11790cafe58d2073`  
**Working tree:** clean  
**Mode:** read-only audit (no fixes implemented)

---

## Executive verdict

| Gate | Result |
|------|--------|
| **A — Migration closure** | **YES** — all popup content block families have dedicated `renderCss` compilers and are excluded from generic block-trait responsive merchant CSS |
| **A1 — Generic responsive merchant closure** | **YES** — `buildBlockTraitResponsiveCss` has **no merchant-presentation consumers** in production |
| **A2 — Serializer merchant-inline closure** | **YES** — no remaining serializer paths emit merchant presentation inline; residual inline is structure/behavior/RTE-hostile-theme only |
| **B — Deferred debt truth** | See §Gate B — several historical labels are **stale**; block-level `customCss`/`customClass` and button RTE links remain **real** |
| **C — Next Track 2 pick** | **Stream A — block-level `customCss` / `customClass` export pipeline** (§Gate C) |

---

## Gate A — Canonical repository state

### Repository snapshot

```text
branch: css-reft
HEAD:   291c68dae075aa1610d6c44e11790cafe58d2073
status: clean
```

### Required answers

```text
All merchant-CSS block families migrated: YES
Partial migration families:               NONE
```

Expectation match: **YES / NONE** — no contradiction recorded.

### Migration family table

| Family | Dedicated compiler | Inline merchant CSS (serializer) | Generic responsive merchant CSS | Partial migration | Status |
|--------|-------------------|----------------------------------|--------------------------------|-------------------|--------|
| form-surface | `formSurfacePresentation.ts` | — | — | — | **MIGRATED** |
| outer-layout | `outerLayoutPresentation.ts` | — | — | — | **MIGRATED** |
| columns-container | `columnsContainerPresentation.ts` | — | — | — | **MIGRATED** |
| column | `columnPresentation.ts` | — | — | — | **MIGRATED** |
| button | `buttonPresentation.ts` | — (align wrapper structural only) | skipped (`MIGRATED_BLOCK_TRAIT_TYPES`) | — | **MIGRATED** |
| text | `textPresentation.ts` | — | skipped | — | **MIGRATED** |
| text-input | `standardFieldPresentation.ts` | — | skipped | — | **MIGRATED** |
| email-input | `standardFieldPresentation.ts` | — | skipped | — | **MIGRATED** |
| date | `standardFieldPresentation.ts` | — | skipped | — | **MIGRATED** |
| textarea | `textareaPresentation.ts` | — | skipped | — | **MIGRATED** |
| dropdown | `dropdownPresentation.ts` | — | skipped | — | **MIGRATED** |
| phone-input | `phoneInputPresentation.ts` | — | skipped | — | **MIGRATED** |
| radio | `radioPresentation.ts` | — | skipped | — | **MIGRATED** |
| check-box | `checkboxPresentation.ts` | — | skipped | — | **MIGRATED** |
| divider | `dividerPresentation.ts` | — | skipped | — | **MIGRATED** |
| coupon | `couponPresentation.ts` | — | skipped | — | **MIGRATED** |
| image | `mediaPresentation.ts` | — | skipped | — | **MIGRATED** |
| video | `mediaPresentation.ts` | — | skipped | — | **MIGRATED** |
| social | `socialPresentation.ts` | — | skipped | — | **MIGRATED** |
| feedback | `feedbackPresentation.ts` | — | skipped | — | **MIGRATED** |
| timer | `timerPresentation.ts` | — | skipped | — | **MIGRATED** |
| spinner | `spinnerPresentation.ts` | — | skipped | — | **MIGRATED** |
| product | `productPresentation.ts` | — | skipped | — | **MIGRATED** |
| cross | `positionedDecorativePresentation.ts` | — | skipped | — | **MIGRATED** |
| floating-image | `positionedDecorativePresentation.ts` | — | skipped | — | **MIGRATED** |
| teaser | `teaserPresentation.ts` | teaser img/text inline (structural) | hide-only device CSS | — | **MIGRATED** |
| gamification | `gamificationPresentation.ts` | — | — | — | **MIGRATED** |

**Compiler registry:** `compilePopupRenderCss.ts` registers all rows above through `compileProductPresentationRenderCss`, then positioned decorative, teaser, and conditional gamification.

**`MIGRATED_BLOCK_TRAIT_TYPES`:** matches all content block types in the table (including `spinner`, `product`, `timer`, `feedback`, `social`).

**`*.export.css` sheets (13):** coupon, image, video, social, feedback, timer, spinner, product, radio, checkbox, floating-label, embed-responsive, popup-entrance — all structural/behavior/state-scaffold; merchant paint is in artifact `renderCss`.

**`popup-css-migration-current-state.md`:** accurate on migration closure; deferred list needs refresh (§Gate B).

---

## Gate A1 — Generic responsive closure

### Current `buildBlockTraitResponsiveCss` consumers

Production call chain:

```text
serializeLayout.ts
  → buildEmbeddedStepResponsiveCss()
      → buildBlockTraitResponsiveCss(formElements)   // internal only
```

No other production importers. Tests assert exclusion per migrated family.

### `buildEmbeddedStepResponsiveCss` output classification

| Output | Source | Classification |
|--------|--------|----------------|
| `buildBlockDeviceVisibilityCss()` | `hideOnDesktop` / `hideOnMobile` on blocks, columns, surface; product slot count diff | **BEHAVIOR** (viewport visibility) |
| `buildBlockTraitResponsiveCss()` | Loop over non-layout elements **not** in `MIGRATED_BLOCK_TRAIT_TYPES` | **DEAD** for current block catalog (always empty) |
| `buildPhoneInputCountrySelectResponsiveCss()` | Mobile max-width on country `<select>` | **STRUCTURE** |

### Per-consumer verdict

```text
Current buildBlockTraitResponsiveCss consumers:
  1. buildEmbeddedStepResponsiveCss (serializeLayout.ts) — DEAD merchant path; generator body never emits rules for any current block type

Generic responsive merchant-CSS migration closed: YES
```

### `buildBlockTraitResponsiveCss` disposition

```text
STILL REQUIRED:        NO (for merchant presentation)
STRUCTURE/BEHAVIOR ONLY: sibling functions in same module remain required (visibility + phone select)
DEAD PRODUCTION CODE:  the buildBlockTraitResponsiveCss function body is PARTIALLY DEAD — safe final-cleanup candidate after explicit deletion review
PARTIALLY DEAD:        YES — keep module; trait loop is dead for migrated catalog
```

---

## Gate A2 — Serializer inline inventory

Search scope: all `serialize*.ts`, block `serialize.ts`, `styleAttr`, inline `style=` builders.

### Block serializers (`blocks/*/serialize.ts`)

| Family | DOM owner | Inline declaration | Source | Classification | Still necessary? |
|--------|-----------|-------------------|--------|----------------|------------------|
| button | align `<div>` | `width:100%`, `text-align` | `buildButtonAlignContainerStyle` | **STRUCTURE** | YES — flex alignment scaffold; merchant chrome in `renderCss` |
| floating-image | positioned shell | `pointer-events:auto` | `serialize.ts` | **BEHAVIOR** | YES — preview interaction |
| All other block serializers | — | none | structure-only DOM + attrs | — | — |

### Cross-cutting serializers

| Family | DOM owner | Inline declaration | Source | Classification | Still necessary? |
|--------|-----------|-------------------|--------|----------------|------------------|
| all blocks (wrapper) | `[data-block-type]` wrapper | post-strip: mostly `flex-shrink:0` (+ rare structural) | `getExportWrapperStyleFromLayout` + `applyMigratedWrapperLayoutExportStripping` | **STRUCTURE** | YES today — Stream D cleanup candidate |
| button label RTE | `<a>` inside `<button>` | `color`, `text-decoration` | `applyRichTextLinkStyles` | **MERCHANT PRESENT** (hostile-theme guard) | YES until link rules move to scoped export CSS |
| layout form | `<form>` | `width`, `margin`, `max-width`, `box-sizing` | `serializeLayout.ts` | **STRUCTURE** | YES |
| teaser | img / span / root | img dimensions, `white-space`, `pointer-events` | `serializeTeaser.ts` | **STRUCTURE / BEHAVIOR** | YES — presentation in `teaserPresentation.ts` |
| embedded step | `<style>` blocks | global sheets + `buildRichTextLinkExportCss` | `serializeLayout.ts` | mixed | see §Gate B |

### Required serializer merchant-inline closure answer

```text
Remaining serializer merchant-inline merchant presentation:
  → Button RTE <a> inline color/underline only (intentional hostile-theme guard)
  → NOT block chrome (padding, colors, shadows, widths) on any migrated family

Serializer merchant-inline closure for block chrome: YES
```

---

## Gate B — Deferred debt truth audit

| # | Item | Verdict | Evidence summary |
|---|------|---------|------------------|
| 1 | Button linked-label color and hover ownership | **REAL DEFECT** (narrow) + **PRODUCT DECISION** (hostile-theme) | `applyRichTextLinkStyles` sets inline link color on export; `buildRichTextLinkExportCss` adds `!important` rules for `button a`. Button `:hover` in `buttonPresentation.ts` / `resolveButtonHoverCssRules` does **not** target inner `<a>` — linked labels keep static link color on hover. Button shell hover is migrated. |
| 2 | Checkbox `outlineColor` | **STALE DEBT LABEL** (fixed) | `CheckBoxProperties.tsx` writes `outlineColor`; `resolveCheckboxColorTokenCssRules` → `--pbn-checkbox-outline`; tests in `checkboxPresentationRenderCss.test.ts`. |
| 3 | Checkbox `backgroundColor` | **REAL ORPHAN / DEAD-MODEL** (low severity) | Token `--pbn-checkbox-bg` reads `style.backgroundColor` with default `#FFFFFF`; **no panel writer** for `backgroundColor` (only `fillColor`, `outlineColor`). Persists only via defaults/clone. |
| 4 | Coupon `boxShadowEnabled` legacy path | **REAL LEGACY DATA GAP** (low active UI) | `resolveCouponShellPresentationCssRules` uses `resolveDeviceBoxShadowCss` only — **no** `mergeBoxShadowIntoCssRules`. Coupon panel has **no shadow trait UI**. Legacy flat `boxShadowEnabled` on old snapshots silently drops shadow on export. Button/timer compilers **do** use `mergeBoxShadowIntoCssRules` fallback. |
| 5 | Coupon `customCss` / `customClass` | **REAL ARCHITECTURE DEBT** (block-level orphan) | Panel writers persist; **zero** export consumers under `popupHtmlSerializer/`. Same pattern as all blocks — not coupon-specific. |
| 6 | Image `elementWidth` legacy fallback | **STALE for image family** | Image defaults use `imageWidth` / `imageWidthPx` only (`ElementDefaults.ts`). `resolveImagePaintedPresentationCssRules` reads `imageWidth`, not `elementWidth`. `elementWidth` remains valid for other block types via wrapper layout helpers. |
| 7 | Block-level `customCss` / `customClass` architecture | **REAL ARCHITECTURE DEBT** | 18+ property panels write `style.customCss` / `style.customClass`; export path only reads **`formStyle.customCss`** (`serializeLayout.ts` + `sanitizeCustomCss`). Block `customClass` applied on canvas preview (`FormCanvas.tsx`) but **not** on export DOM. |
| 8 | Form-level `customCss` architecture | **WORKING AS DESIGNED** (policy debt only) | `FormLayoutTabs.tsx` / `CustomCodePanel.tsx` → `formStyle.customCss` → sanitized `<style data-pbn-custom>` in export. Not orphaned. Policy question: scope/safety vs block-level — separate from orphan bug. |
| 9 | Remaining inline CSS across migrated serializers | **FINAL CLEANUP CANDIDATE** (not defect) | Residual structural/behavior inline only (§Gate A2). No merchant chrome inline on migrated families. |
| 10 | Remaining generic responsive merchant CSS | **STALE DEBT LABEL** (closed) | `buildBlockTraitResponsiveCss` emits nothing for current catalog. |
| 11 | Remaining embedded/global merchant presentation CSS | **MOSTLY STRUCTURE**; one merchant exception | Per-family `*.export.css` sheets are structural/state. **Exception:** `buildRichTextLinkExportCss` in embedded `<style>` is merchant presentation for button RTE links (pairs with item 1). `embed-responsive.export.css` is structure/overflow. |
| 12 | Duplicate canvas/export merchant interpretation | **MOSTLY CLOSED**; residual drift pockets | Migrated families use shared `ElementStyles` resolvers on canvas (e.g. image, social, feedback, coupon code, product). **Drift pockets:** `DateCanvas` / `PhoneInputCanvas` still call `getElementStyle()` for shell chrome while export uses presentation compilers; `resolveFeedbackShellPresentationCssRules` still spreads `getElementStyle()` internally (shared by both sides, but legacy path). Cross hover: canvas uses `onMouseEnter` DOM mutation; export uses `positionedDecorativePresentation` `:hover` — intentional split for cross only. |

### Stale labels to retire from KB deferred list

- Checkbox `outlineColor` orphan
- Image `elementWidth` orphan (image-specific)
- Generic responsive merchant CSS consumers
- “Remaining unmigrated block families” (spinner/product now migrated)

### Labels to keep / refine

- Block-level `customCss` / `customClass` (real, cross-cutting)
- Button RTE linked-label hover parity (real defect)
- Checkbox `backgroundColor` (minor orphan)
- Coupon legacy `boxShadowEnabled` (legacy snapshots only)
- Structural-inline final cleanup (Stream D)
- Date/phone canvas `getElementStyle` drift (architecture hygiene)
- Timer runtime fit (behavior — out of merchant-CSS scope)

---

## Gate C — Rank proven-real work; select one next implementation

### Ranked (proven real only)

| Rank | Workstream | Why real | Risk | User impact |
|------|------------|----------|------|-------------|
| **1** | **Block-level `customCss` / `customClass` export pipeline (Stream A)** | UI persists on 18+ blocks; export never reads block-level fields; merchants may believe Advanced panel works | Medium — sanitization + scoping | **High** — silent feature gap |
| 2 | Button RTE linked-label color + hover parity (Stream B) | Inline + embedded CSS for links; hover rules skip `<a>` | Medium — RTE + specificity | Medium — affects RTE buttons with links |
| 3 | Structural-inline final cleanup (Stream D) | `flexShrink`, wrapper rebuild, teaser img inline → renderCss/teaser compiler | Medium — touches serializers | Low — hygiene |
| 4 | Date/phone canvas → canonical resolver adapters | `getElementStyle` vs presentation compilers | Low–medium | Low — drift prevention |
| 5 | Coupon legacy `boxShadowEnabled` fallback in shell compiler | `mergeBoxShadowIntoCssRules` parity with button | Low | Low — old data only |
| 6 | Checkbox `backgroundColor` panel or strip token | Dead-model field | Low | Very low |

**Not ranked (stale / out of scope):** spinner/product migration, generic responsive merchant CSS, image `elementWidth`, checkbox `outlineColor`.

### Selected next Track 2 implementation (exactly one)

```text
Track 2 — Stream A: Block-level customCss / customClass artifact export pipeline

Scope sketch (do not implement in 2A):
  1. Product decision: inject per-block sanitized CSS into artifact renderCss vs dedicated <style> chunk
  2. Apply customClass to export DOM (block root or compiler-selected owner) matching canvas preview contract
  3. Reuse sanitizeCustomCss; scope selectors under artifact + block id
  4. Regression: popupHtmlSerializer.test customCss sanitization pattern + one block fixture
  5. Update Advanced panel copy if form-level vs block-level distinction needs clarity
```

**Rationale:** Only ranked item that is both **provably broken today** (writers without readers) and **cross-cutting architecture completion** after merchant-CSS migration. Button link hover is a real defect but narrower; customCss orphan undermines the entire Advanced panel contract.

---

## Appendix — Evidence pointers

| Concern | Primary files |
|---------|---------------|
| Compiler registry | `compilePopupRenderCss.ts` |
| Migration skip set | `responsiveDeviceCss.ts` (`MIGRATED_BLOCK_TRAIT_TYPES`) |
| Wrapper strip list | `migratedWrapperLayoutOwnership.ts` |
| Block customCss writers only | `*Properties.tsx` panels; no reads in `popupHtmlSerializer/` |
| Form customCss export | `serializeLayout.ts` L234–291 |
| Button links | `popupHtmlSanitize.ts` (`applyRichTextLinkStyles`), `serializeLayout.ts` (`buildRichTextLinkExportCss`) |
| Checkbox tokens | `ElementStyles.ts` (`resolveCheckboxColorTokenCssRules`), `CheckBoxProperties.tsx` |
| Coupon shadow | `ElementStyles.ts` (`resolveCouponShellPresentationCssRules`) |
| Image width traits | `ElementDefaults.ts` (`image`), `resolveImagePaintedPresentationCssRules` |

---

## Audit constraints honored

- No deferred fixes implemented
- No block-family migrations reopened
- `popup-css-migration-current-state.md` not edited (report-first); recommend updating deferred bullets after review
