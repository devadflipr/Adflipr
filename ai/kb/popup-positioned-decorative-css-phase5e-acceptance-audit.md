# Phase 5E — Positioned decorative presentation acceptance audit

**Status:** Acceptance review (2026-07-05)  
**Reviewer stance:** Principal Engineer — formal acceptance before Teaser migration  
**Related:** [`popup-positioned-decorative-css-phase5e-report.md`](./popup-positioned-decorative-css-phase5e-report.md), [`popup-button-presentation-css-phase5c-acceptance-audit.md`](./popup-button-presentation-css-phase5c-acceptance-audit.md)

---

## A. Executive verdict

**Phase 5E is architecturally correct on the export artifact path and satisfies Phase 0 computed-style evidence for all four positioned-decorative risk cases.**

**Principal Engineer classification: ACCEPT WITH CLEANUP**

No blocking correctness defects were found. Two narrow cleanup items should be addressed before or alongside Teaser migration:

1. **Delete `buildMobilePositionOverrideRules`** — exported production helper with zero consumers; speculative dead code.
2. **Delegate `CrossCanvas.tsx` to canonical resolvers** — canvas-only parallel trait interpretation (same class of drift accepted for Button in Phase 5C); does not affect storefront export.

**No production code was modified during this audit.**

---

## B. Production DOM ownership verification

### Cross — verified against `cross/serialize.ts`

**Serialized HTML shape:**

```html
<button type="button" aria-label="Close" data-adflipr-popup-close="true" data-adflipr-block-id="{id}">&#215;</button>
```

| Concern | Owner | Compiler selector | Match? |
| ------- | ----- | ----------------- | :----: |
| Identity | `<button>` | `[data-adflipr-popup-close="true"][data-adflipr-block-id="{id}"]` | ✅ |
| Positioning | same `<button>` | same selector + `top/right/bottom/left` | ✅ |
| Painted chrome | same `<button>` | same selector + color/bg/padding/radius/fontSize | ✅ |
| Close behavior | same `<button>` | `data-adflipr-popup-close` attr preserved | ✅ |

**Evidence:** `cross/serialize.ts` emits no `style` attribute. `crossShellSelector()` targets the close button with artifact scope.

### Floating Image — verified against `floating-image/serialize.ts`

**Serialized HTML shape:**

```html
<div data-block-type="floating-image" data-adflipr-block-id="{id}" …>
  <img src="…" alt="…" />
  <!-- or -->
  <a href="…" style="pointer-events:auto"><img … /></a>
</div>
```

| Concern | Owner | Compiler selector | Match? |
| ------- | ----- | ----------------- | :----: |
| Identity | wrapper `<div>` | `[data-adflipr-block-id="{id}"][data-block-type="floating-image"]` | ✅ |
| Positioning | wrapper `<div>` | shell selector + offsets | ✅ |
| Painted media | `<img>` | `… img` descendant selector | ✅ |
| Link behavior | optional `<a>` | `href`, `target`, `rel`; inline `pointer-events:auto` only | ✅ |

**Gate 1: PASS**

---

## C. Canonical normalization verification

### Export / compiler / preview / responsive (authoritative storefront path)

| Path | Uses canonical resolvers? | Independent merchant CSS? |
| ---- | ------------------------- | ------------------------- |
| `cross/serialize.ts` | N/A (structure only) | **No** |
| `floating-image/serialize.ts` | N/A (structure only) | **No** (except link `pointer-events`) |
| `compilePositionedDecorativeRenderCss` | `resolveCrossShellCssRules`, `resolveFloatingImageShellCssRules`, `resolveFloatingImageImgCssRules` | **No** |
| `compilePopupRenderCss` | wires positioned-decorative compiler | **No** |
| `buildEmbeddedStepResponsiveCss` | cross/floating **excluded** (`continue`) | **No** |
| `buildCrossFloatingImageResponsiveCss` | **deleted** | **No** |
| Builder preview / thumbnails | `serializePopupExport` → `compilePopupRenderCss` | **No** |
| `resolveFloatingImageShellStyle.ts` | delegates to `resolveFloatingImageShellCssRules` | Canvas only |
| `buildFloatingImageTagStyle.ts` | delegates to `resolveFloatingImageImgCssRules` | Canvas only |

### Canvas-only parallel path (non-export)

| Path | Status |
| ---- | ------ |
| `CrossCanvas.tsx` | **Parallel** — inline React styles reconstruct position, margin, color, padding, radius independently; semantics align with `CROSS_SHELL_MARGIN_DEFAULTS` and trait panel defaults but **does not import** `resolveCrossShellCssRules` |

**Answers:**

- **Canvas (floating-image):** same trait interpretation via canonical resolvers ✅
- **Canvas (cross):** parallel but semantically aligned ⚠️ (SAFE FOLLOW-UP)
- **renderCss:** canonical ✅
- **Serializer:** does not reconstruct migrated merchant CSS ✅
- **Old responsive helper:** deleted; no parallel trait interpretation ✅

**Gate 2: PASS** (export artifact path canonical; Cross canvas drift documented — same acceptance class as Phase 5C Button canvas/export split)

---

## D. Mobile anchor-model proof

### Inspection scope

| Source | Finding |
| ------ | ------- |
| Model types (`blocks/types.ts`) | Single `position` on element properties; **no `positionMobile`** |
| Trait UI (`ButtonCrossProperties.tsx`) | One `position` select (top-right / top-left / bottom-right / bottom-left); device toggle applies only to **margin**, **padding**, **corner radius** |
| Floating-image traits | Same single `position`; device-paired margins only |
| Grep `positionMobile` | **Zero matches** in `adflipr-app` |
| `marginPositionCss` | Emits only two sides per anchor; never sets opposite sides |
| Saved state / templates | No schema field for per-device anchor |

### Classification: **A — Impossible by model and UI contract**

Desktop anchor = right, mobile anchor = left **cannot be represented** in current traits, serializer, compiler, or UI.

**DIFF-ONLY mobile emission: ACCEPTED** — anchor sides are invariant; only margin offset magnitudes differ per device.

### `buildMobilePositionOverrideRules`

| Criterion | Result |
| --------- | ------ |
| Production importers | **Zero** (only defined in `positionedDecorativePresentation.ts`) |
| Wired into compiler | **No** |
| Tests reference it | **No** |
| Model needs it today | **No** (anchor not device-specific) |

**Classification: B — dead speculative production code**

**Recommendation: DELETE** (not a blocking defect; no reachable correctness failure)

---

## E. Inline CSS audit

### Cross owner (`<button>`)

| Remaining inline | Classification |
| ---------------- | -------------- |
| *(none)* | — |

Verified properties **not inline:** `top`, `right`, `bottom`, `left`, margins, `color`, `background`, `padding`, `border-radius`, `font-size`.

### Floating Image

| Node | Remaining inline | Classification |
| ---- | ---------------- | -------------- |
| wrapper `<div>` | *(none)* | — |
| `<img>` | *(none)* | — |
| optional `<a>` | `pointer-events: auto` | **BEHAVIOR** — restores click target inside `pointer-events:none` shell |

`pointer-events:none` on shell is emitted in **renderCss** (`FLOATING_IMAGE_SHELL_STRUCTURAL_STYLES`), not inline — correct.

**Gate 4: PASS**

---

## F. Hybrid CSS audit

### Export path — migrated property source count

| Property family | Cross owner | Floating owner | Production sources |
| --------------- | ----------- | -------------- | ------------------ |
| Position offsets | close `<button>` | shell `<div>` | **1** — `compilePositionedDecorativeRenderCss` |
| Cross painted | close `<button>` | — | **1** — renderCss |
| Floating img painted | — | `<img>` | **1** — renderCss |
| Floating z-index | — | shell `<div>` | **1** — renderCss |

**Excluded paths verified:**

- `buildEmbeddedStepResponsiveCss` — no cross/floating emission (`responsiveDeviceCss.ts:307-308` `continue`)
- Serializer inline — stripped
- `buildBlockTraitResponsiveCss` — skips cross/floating

### Builder canvas (non-storefront)

`CrossCanvas.tsx` applies inline React presentation for builder WYSIWYG only. This is **not** an export hybrid — storefront HTML + renderCss remain single-source.

**Gate 5: PASS** (export artifact invariant satisfied)

---

## G. Old generator deletion evidence

| Symbol | Production `.ts/.tsx` refs | KB/historical refs |
| ------ | -------------------------- | ------------------ |
| `buildCrossFloatingImageResponsiveCss` | **0** | Yes (audit docs) |
| `buildPositionedShellMobileMarginCss` | **0** | Yes (Phase 5E report) |
| `hasCrossOrFloatingImageMarginDiff` | **0** | Yes (Phase 5E report) |

No compatibility wrapper, deprecated alias, or copied margin-only embedded generator found in `responsiveDeviceCss.ts`.

**Gate 6: PASS**

---

## H. Collector and conflict audit

### Verified behavior (`collectArtifactCrossPresentations`, `collectArtifactFloatingImagePresentations`)

| Requirement | Status | Evidence |
| ----------- | ------ | -------- |
| All steps scanned | ✅ | `Object.entries(formElementsByStep)` |
| Same blockId + same presentation → one emission | ✅ | Map dedup by `presentationKey` |
| Same blockId + conflict → explicit error | ✅ | `PositionedDecorativeConflictError` with `blockId`, `blockType`, `stepIds` |
| Different blockIds → independent rules | ✅ | Separate map entries |
| Deterministic ordering | ✅ | `localeCompare` on blockId |
| Conflict identity includes block type | ✅ | Error carries `blockType: 'cross' \| 'floating-image'` |

### Edge case: same block id, different block types

```text
Cross block id = decorative-1
Floating Image block id = decorative-1
```

| Question | Answer |
| -------- | ------ |
| Can this occur in builder? | **Unlikely** — element ids are unique per form element; no shared-id enforcement across types was found, but types differ |
| If it occurs? | **Independent treatment** — separate collectors, separate selectors (`[close]` vs `[data-block-type=floating-image]`), both rules emit; **no merge, no overwrite, no cross-type conflict** |
| Safe? | **Yes** — deterministic; selectors do not collide |

**Gate 7: PASS**

---

## I. Artifact scope and isolation audit

### Selector shape (verified in compiler)

```text
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-popup-close="true"][data-adflipr-block-id="{id}"]
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"][data-block-type="floating-image"]
[data-adflipr-popup-artifact="{scope}"] … img
```

- No `nth-child` on positioned-decorative selectors ✅
- No step-global selectors ✅
- No unscoped global block-type selectors ✅
- `cssEscapeAttributeValue` used ✅

### Isolation tests (existing infrastructure)

| Test | Proves |
| ---- | ------ |
| `popupThumbnailArtifact.browser.test.ts` — mismatched scope | Foreign renderCss does not apply ✅ |
| `formSurfacePresentation.browser.test.ts` — scope isolation | Pattern reused across families ✅ |
| Unit test `emits artifact-scoped cross and floating selectors` | Selector strings include artifact attr ✅ |

**Gate 8: PASS**

---

## J. Runtime lifecycle compatibility

Phase 5E did not change embed lifecycle. Existing tests prove renderCss mounting remains compatible:

| Case | Test | Result |
| ---- | ---- | ------ |
| Runtime A + B independent | `renderArtifactCssLifecycle.test.ts` Case F | ✅ |
| Destroy A, B remains | Case F | ✅ |
| Same popupId, two instances | Case G | ✅ |
| Step transitions — single stylesheet | Case C | ✅ |
| Destroy removes owned stylesheet | Case E | ✅ |

Positioned-decorative CSS rides the same `renderCss` mount path as prior phases; no new lifecycle branch introduced.

**Gate 9: PASS**

---

## K. Computed-style evidence

**Run:** `npm test -- --watchAll=false --testPathPattern=responsiveCssBaseline.browser`

### Newly green (verified individually)

| ID | Property | Desktop expected | Desktop actual | Mobile expected | Mobile actual | Pass |
| -- | -------- | ---------------- | -------------- | --------------- | ------------- | :--: |
| `cross-top` | top | 20px | 20px | 4px | 4px | ✅ |
| `cross-right` | right | 12px | 12px | 4px | 4px | ✅ |
| `floating-bottom` | bottom | 18px | 18px | 5px | 5px | ✅ |
| `floating-left` | left | 10px | 10px | 5px | 5px | ✅ |

### Remaining red (verified individually)

| ID | Property | Desktop expected | Desktop actual | Mobile expected | Mobile actual | Pass |
| -- | -------- | ---------------- | -------------- | --------------- | ------------- | :--: |
| `teaser-padding` | padding | 12px | 12px | 6px | 12px | mobile ✗ |
| `teaser-border-radius` | border-radius | 20px | 20px | 6px | 20px | mobile ✗ |

### Scoreboard (from test assertion + matrix)

| Metric | Count |
| ------ | ----: |
| Migrated risk green | **16** |
| Risk red | **2** |
| Controls green | **5** |

**Gate 10: PASS**

---

## L. Behavior regression evidence

### Cross

| Behavior | Test evidence | Status |
| -------- | ------------- | ------ |
| `data-adflipr-popup-close` preserved | `positionedDecorativePresentationRenderCss.test.ts` | ✅ |
| `aria-label="Close"` preserved | same | ✅ |
| `type="button"` preserved | same | ✅ |
| Close button DOM ownership | `blockDomOwnership.browser.test.ts` | ✅ |
| Computed position from renderCss | baseline + browser tests | ✅ |

### Floating Image

| Behavior | Test evidence | Status |
| -------- | ------------- | ------ |
| `src` / `alt` preserved | unit test | ✅ |
| Optional `href` preserved | unit test | ✅ |
| Shell no inline position | browser test | ✅ |
| Computed bottom/left from renderCss | baseline | ✅ |

**Gate 11: PASS**

---

## M. Consumer completeness

| Consumer | Path | Phase 5E-specific branch? | Status |
| -------- | ---- | --------------------------- | ------ |
| Storefront embed | `embed/runtime.ts` + `mountRenderArtifactCss` | No | ✅ |
| Builder preview | `serializePopupExport` / preview mount | No | ✅ |
| Fullscreen preview | `previewArtifactExport.ts` | No | ✅ |
| Campaign list thumbnail | shared export | No | ✅ |
| Popup detail thumbnail | `popupThumbnailIframe.test.ts` injects renderCss | No | ✅ |
| Live embed test | export contract | No | ✅ |

**Invariant preserved:**

```text
same compiled artifact = same HTML + same renderCss
```

**Gate 12: PASS**

---

## N. Structural constants classification

| Constant | Family | Classification | renderCss owner correct? |
| -------- | ------ | -------------- | ------------------------ |
| `position:absolute` | both | **ARTIFACT STRUCTURE** | Yes — required to interpret positioned shell HTML |
| `z-index:40` | cross | **ARTIFACT STRUCTURE** | Yes — fixed stacking contract for close control |
| `border:none` | cross | **ARTIFACT STRUCTURE** | Yes — button reset for export |
| `cursor:pointer` | cross | **BEHAVIOR** | Yes in renderCss — storefront close affordance |
| `line-height:1` | cross | **ARTIFACT STRUCTURE** | Yes |
| `pointer-events:none` | floating shell | **BEHAVIOR** | Yes — click-through shell; link uses `pointer-events:auto` |
| `z-index` (trait-driven) | floating | **MERCHANT PRESENTATION** | Yes — migrated via `resolveFloatingImageShellCssRules` |

**Future-phase note:** Cross `z-index:40` is not trait-driven today; if merchants need configurable cross stacking, that would be a Teaser-adjacent or cross-trait extension — not a Phase 5E defect.

---

## O. Abstraction review

`positionedDecorativePresentation.ts` contains:

| Pattern | Verdict |
| ------- | ------- |
| Shared `marginPositionCss` / margin resolvers | ✅ genuinely shared semantics |
| Separate `normalizeCrossPresentation` / `normalizeFloatingImagePresentation` | ✅ explicit per family |
| Separate selectors (`crossShellSelector`, `floatingImageShellSelector`, `floatingImagePaintedSelector`) | ✅ family-specific DOM |
| Separate collector functions | ✅ understandable; not mega-abstraction |
| `diffMobilePresentation` local helper | ✅ acceptable mechanical duplication (matches Text/Column) |

**Not introduced:** universal block compiler, generic property bag, type-switch mega abstraction, hidden DOM registry.

**Gate 14: PASS**

---

## P. Cleanup findings

| Finding | Classification | Action |
| ------- | -------------- | ------ |
| `buildMobilePositionOverrideRules` exported, zero consumers | **CLEANUP BEFORE ACCEPTANCE** | Delete exported helper |
| `CrossCanvas.tsx` parallel trait interpretation | **SAFE FOLLOW-UP** | Delegate to `resolveCrossShellCssRules` for WYSIWYG parity |
| KB docs still reference deleted `buildCrossFloatingImageResponsiveCss` | **NO ISSUE** | Historical audit trail |
| `rootCause` string in baseline cases still says "inline" for green cases | **NO ISSUE** | Stale metadata only; tests pass on computed values |
| Comment on `resolveCrossPositionOffsetRules` says "shared by canvas, serializer, compiler" | **SAFE FOLLOW-UP** | Cross canvas does not yet import it — comment slightly ahead of code |

**No BLOCKER findings.**

---

## Q. Test evidence

**Command:**

```bash
cd adflipr-app && npm test -- --watchAll=false \
  --testPathPattern="positionedDecorative|responsiveCssBaseline|popupHtmlSerializer.test|responsiveDeviceCss|popupThumbnail|renderArtifactCss|mountRenderArtifactCss|blockDomOwnership"
```

| Suite | Tests |
| ----- | ----: |
| `positionedDecorativePresentationRenderCss.test.ts` | 10 |
| `positionedDecorativePresentation.browser.test.ts` | 4 |
| `responsiveCssBaseline.browser.test.ts` | 25 |
| `popupHtmlSerializer.test.ts` | 52 |
| `responsiveDeviceCss.test.ts` | 12 |
| `popupThumbnailIframe.test.ts` | 9 |
| `popupThumbnailArtifact.browser.test.ts` | 3 |
| `renderArtifactCssLifecycle.test.ts` | 10 |
| `mountRenderArtifactCss.test.ts` | 4 |
| `blockDomOwnership.browser.test.ts` | 7 |
| **Total** | **136 passed, 0 failed** |

---

## R. Acceptance decision

| Criterion | Result |
| --------- | ------ |
| DOM ownership proven | ✅ |
| Canonical normalization proven (export path) | ✅ |
| DIFF-ONLY safe (anchor model A) | ✅ |
| No migrated inline merchant presentation (export) | ✅ |
| No permanent export hybrid CSS | ✅ |
| Old embedded generator deleted | ✅ |
| Artifact scoping proven | ✅ |
| Multi-step conflicts deterministic | ✅ |
| Runtime isolation preserved | ✅ |
| Four Phase 0 cases green | ✅ |
| Only two Teaser reds remain | ✅ |
| Behavior preserved | ✅ |
| Consumer completeness preserved | ✅ |
| No `!important` introduced | ✅ |
| No backward compatibility branch | ✅ |

**Phase 5E formally accepted: YES** (with narrow cleanup: delete `buildMobilePositionOverrideRules`)

---

## S. Teaser readiness

**Ready for Teaser migration: YES**

Remaining Phase 0 red cases are exclusively Teaser-owned (`teaser-padding`, `teaser-border-radius`). Positioned-decorative migration does not leave hybrid export paths or scoreboard ambiguity for those cases.

**Recommended Teaser approach:** surface-level compiler (not positioned-decorative family); replace `buildTeaserTraitResponsiveCss` embedded path; do not reuse cross/floating collectors.

---
