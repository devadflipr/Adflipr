# Phase 6H-L — Checkbox Branch Landing Report

**Date:** 2026-07-06  
**Verdict:** **ACCEPT**  
**Target branch:** `fix-0607`  
**Landing commit:** `e78e90ffc` (cherry-pick of `facd52c92`)

---

## 1. Executive summary

The accepted Phase 6H Checkbox migration is now landed on **`fix-0607`**. Cherry-pick of `facd52c92` (css-reft equivalent of audited commit `48f06470e`) applied cleanly with **zero conflicts**. All focused Checkbox suites pass; G1 production webpack contract passes; Phase 0 baseline remains **25/25 green**. Later `fix-0607` fixes (`b6fd19eed` teaser/RTE, `05e3b1599` thank-you/preview) are preserved.

---

## 2. Initial branch state

| Item | Value |
|---|---|
| Target branch | `fix-0607` |
| Initial HEAD | `afedc9983` |
| Phase 6H reference commit | `48f06470e` (orphaned; not on any branch) |
| css-reft equivalent | `facd52c92` |
| Workspace at audit start | `css-reft` @ `ac1a81b54` (Checkbox already present; Coupon also landed) |
| fix-0607 pre-landing | Pre-migration inline Checkbox export |

---

## 3. Git ancestry evidence

```
* e78e90ffc (HEAD -> fix-0607) feat: implement checkbox component with artifact-scoped renderCss
*   afedc9983 Merge branch 'dev-main' into fix-0607
|\  
| * b6fd19eed fix(popup-builder): wire teaser delays and fix RTE yellow highlight
| * 05e3b1599 fix(popup-builder): remove default thank-you step and polish preview/dropdown flow
* | 409e7ed67 Merge branch 'css-reft' into 'dev-main'
```

Parallel css-reft history (not merged into fix-0607):

```
* ac1a81b54 feat: migrate coupon block to artifact-scoped renderCss
* facd52c92 feat: implement checkbox component with artifact-scoped renderCss  ← cherry-picked
```

---

## 4. Merge-base evidence

| Pair | Merge base |
|---|---|
| `fix-0607` ↔ `48f06470e` | `d6463efee` |
| `fix-0607` ↔ `facd52c92` | `409e7ed67` |
| `HEAD` ↔ `48f06470e` | `48f06470e NOT ancestor of HEAD` (before landing) |

---

## 5. Branch reconciliation map

| Item | Result |
|---|---|
| Active branch | `fix-0607` |
| Active HEAD (after landing) | `e78e90ffc` |
| Phase 6H commit (audit reference) | `48f06470e` |
| Phase 6H commit (applied) | `facd52c92` → `e78e90ffc` |
| Merge base (fix-0607 ↔ facd52c92) | `409e7ed67` |
| Branch containing Phase 6H (pre-landing) | `css-reft` (`facd52c92`); `48f06470e` orphaned |
| Active-only commits (pre-landing) | `afedc9983`, `b6fd19eed`, `05e3b1599` |
| Phase-6H-only commits (not on fix-0607) | `facd52c92`, `6aae35896` |
| Partial migration already present | **NO** (on fix-0607) |
| Safe direct cherry-pick | **YES** (`facd52c92` onto `afedc9983`) |

---

## 6. Phase 6H delta classification (`facd52c92` / `48f06470e`)

| Category | Files |
|---|---|
| **CHECKBOX MIGRATION** | `checkboxPresentation.ts`, `checkbox.export.css`, `check-box/serialize.ts`, `ElementStyles.ts` (checkbox resolvers), `compilePopupRenderCss.ts`, `responsiveDeviceCss.ts`, `migratedWrapperLayoutOwnership.ts`, `serializeElements.ts`, `serializeLayout.ts`, `popupEmbedContract.ts`, `styles/sheets/index.ts`, `floatingLabelExportStyles.ts`, checkbox tests |
| **SHARED CSS INFRASTRUCTURE** | `migratedWrapperLayoutOwnership.ts`, `serializeElements.ts` (wrapper stripping helper) |
| **G1 PRODUCTION CONTRACT** | `export-css-production-fixture-entry.js`, `verify-export-css-production-build.js` |
| **TEST ONLY** | `checkboxPresentation*.test.ts`, `migratedWrapperLayoutExport.test.ts`, `popupHtmlSerializer.test.ts` updates |
| **DOCUMENTATION** | Phase 6H/7B/G2D/G2F/H3 KB reports |
| **UNRELATED (co-committed)** | Divider migration (`dividerPresentation.ts`, divider serialize/canvas) — accepted co-commit from `48f06470e`; not Coupon/Timer |

`48f06470e` is **not** Checkbox-only; it co-commits Divider. Checkbox file delta is self-contained within that commit.

---

## 7. Selected landing strategy

**A — DIRECT CHERRY-PICK** of `facd52c92`

### Reason

- `facd52c92` is byte-equivalent to `48f06470e` for all Checkbox migration files (same 40-file stat).
- Built on merge base `409e7ed67`, which is an ancestor of `fix-0607` — includes thank-you step fix lineage.
- No dependency commits required beyond ancestry already on `fix-0607`.
- Cherry-pick applied with **zero conflicts**.

### Commits/files applied

- Single cherry-pick: `git cherry-pick facd52c92` → `e78e90ffc`

### Known conflict surfaces

- `SerializedTeaserPreviewCanvas.tsx`, `formPreviewEmbedPresentation.ts`, `serializedTeaserPreviewMount.ts` — **no conflicts**; additive artifact-scope changes merged cleanly atop `b6fd19eed`.

---

## 8. Conflict list and resolution

| Conflicts | Resolution |
|---|---|
| **None** | N/A |

---

## 9. Active-branch behavior preserved

| fix-0607 fix | Preserved? | Evidence |
|---|---|---|
| `b6fd19eed` teaser delays | **YES** | `resolveTeaserInitialDelayMs`, `teaserRenderTimer` in `runtime.ts` |
| `b6fd19eed` RTE yellow highlight | **YES** | `RTE_HIGHLIGHT_PREVIEW_ATTR`, preview discard in BalloonEditor/InlineEditor |
| `05e3b1599` no default thank-you step | **YES** | `getInitialSteps` in `FormBuilder.tsx` |
| `05e3b1599` preview/dropdown polish | **YES** | `TeaserPreview.tsx` unchanged since fix |

**Later fix-0607 behavior lost:** **NONE**

---

## 10. Accepted Phase 6H behavior added

- `checkboxPresentation.ts` compiler pipeline
- `compileCheckboxPresentationRenderCss` in `compilePopupRenderCss.ts`
- Structure-only `check-box/serialize.ts`
- `checkbox.export.css` + `POPUP_ATTR_STYLE_CHECKBOX` embed
- G1 checkbox fixture marker
- `check-box` in both migration registries
- Checkbox unit + Chromium browser tests

---

## 11. Final Checkbox architecture trace

```
model → normalizeCheckboxPresentation → compileCheckboxPresentationRenderCss
  → compilePopupRenderCss → renderCss → save/load → mountRenderArtifactCss

checkbox.export.css → serializeLayout <style> → hoistExportedStepStyles

HTML attrs → runtime/validation (unchanged)
```

---

## 12. Final DOM ownership matrix

| Node | Merchant presentation | Native mechanics | Behavior |
|---|---|---|---|
| Wrapper | renderCss | — | identity attrs |
| Group field label | renderCss | — | `for` click |
| `.pbn-export-native-checkbox-scope` | renderCss + CSS vars | inherited vars | — |
| `.pbn-display-checkbox-row` | — | checkbox.export.css | — |
| `input.pbn-display-native-checkbox` | via vars | checkbox.export.css | toggle |
| Option label | renderCss | — | `for` click |

---

## 13. Old export CSS writer search

| Pattern | Export path | Classification |
|---|---|---|
| `accentColor` in check-box/serialize | **absent** | — |
| `getChoiceGroupShellStyle` in serialize | **absent** | CANVAS ONLY in CheckboxCanvas |
| `buildChoiceOptionLabelStyle` in serialize | **absent** | CANVAS ONLY |
| `buildCheckboxGroupStyle` in serialize | **absent** | CANVAS ONLY |
| Generic responsive for check-box | **excluded** | MIGRATED_BLOCK_TRAIT_TYPES |

**Old Checkbox export merchant CSS writers:** **NONE**

---

## 14. Saved artifact evidence

From `checkboxPresentationRenderCss.test.ts` + updated `popupHtmlSerializer.test.ts`:

- HTML: `pbn-export-native-checkbox-scope`, no merchant `style=` on scope/row/input/option labels
- Wrapper inline: `flex-shrink:0` only
- `renderCss`: wrapper margin, group padding, `--pbn-checkbox-*`, option `font-size:18px`
- Embedded: `data-adflipr-popup-checkbox-css="true"` with raw CSS

---

## 15. Remaining inline style classification

| Inline | Class |
|---|---|
| Wrapper `flex-shrink:0` | STRUCTURAL |
| Canvas `accentColor` in CheckboxCanvas | CANVAS ONLY |

---

## 16–20. Verification evidence

| Gate | Result |
|---|---|
| renderCss merchant rules | **PASS** (unit tests) |
| Embedded checkbox sheet | **PASS** |
| G1 webpack `.pbn-export-native-checkbox-scope` | **FOUND** |
| G1 stub markers | **NOT FOUND** |
| Chromium desktop/mobile padding/margin/tokens | **PASS** (8 browser tests) |
| Artifact isolation | **PASS** |
| Runtime validation | **PASS** (`formValidation.test.ts`) |
| Phase 0 | **25/25 PASS** |

---

## 21. Unrelated failure classification

| Suite | Result | Classification |
|---|---|---|
| `popupEmbedRuntime.test.ts` | 4 failed / 13 total | **CONFIRMED PRE-EXISTING** (telemetry, flyout, video autoplay, teaser chip) |
| `popupProductionCssRteOwnership.audit.test.ts` | 2 failed / 10 total | **CONFIRMED PRE-EXISTING** (Text/RTE); browser variant PASS |

---

## 22. Documentation sync

| Document | On fix-0607 |
|---|---|
| `popup-checkbox-merchant-presentation-phase6h-report.md` | **YES** (via cherry-pick) |
| `popup-checkbox-merchant-presentation-phase6h-acceptance-audit.md` | **YES** (restored from stash) |
| `popup-checkbox-phase6h-branch-landing-report.md` | **YES** (this report) |

---

## 23. Optional test debt retained

Not implemented (per narrow-cleanup policy):

- Checkbox hostile-theme browser test (Radio has equivalent)
- Checkbox multi-runtime isolation test (Divider has equivalent)
- Registry parity gate (`MIGRATED_WRAPPER` ↔ `MIGRATED_BLOCK_TRAIT`)

Recorded as **future test debt** — not blocking.

---

## 24. Obsolete production code removed

Export path inline merchant writers removed by cherry-pick. Canvas-only helpers retained with comments.

---

## 25. Final acceptance checklist

- [x] Active branch contains Phase 6H architecture
- [x] Accepted migration landed without overwriting later fixes
- [x] Checkbox merchant presentation renderCss-only
- [x] Native mechanics checkbox.export.css-only
- [x] Wrapper merchant inline collision absent
- [x] Generic responsive Checkbox merchant CSS absent
- [x] Merchant tokens artifact-scoped
- [x] outlineColor / backgroundColor / fillColor effective
- [x] G1 production contract passes
- [x] Chromium tests pass
- [x] Artifact isolation passes
- [x] Runtime unchanged
- [x] Phase 0 green
- [x] No compatibility branch
- [x] No obsolete export writers
- [x] Docs on active branch
- [x] No later fix-0607 behavior lost

---

## 26. Ready for next migration

**YES** — Checkbox is landed on `fix-0607`.

**Recommended next family:** **coupon** (on css-reft @ `ac1a81b54`; not yet on `fix-0607`).

---

## 27. css-reft stash note

Pre-landing WIP on `css-reft` (image/video migration + other KB) preserved in stash:

```
stash@{0}: On css-reft: phase6h-landing-wip-css-reft
```

Restore with `git checkout css-reft && git stash pop` when resuming css-reft work.
