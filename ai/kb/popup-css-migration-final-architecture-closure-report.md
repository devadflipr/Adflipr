# Phase 8A — Popup CSS Migration Final Architecture Closure Report

**Date:** 2026-07-06  
**Branch:** `css-reft`  
**HEAD:** `291c68dae075aa1610d6c44e11790cafe58d2073` (+ Phase 8A working tree changes)  
**Verdict:** **ACCEPT**

---

## 1. Executive verdict

Phase 8A confirms all known block-family merchant presentation compiles through artifact-scoped `renderCss`, deletes obsolete migration-era infrastructure with production evidence, and replaces exclusion-list architecture with a positive structural-only export wrapper model. Regression gates (Phase 0, G1, production build, steady-state tests) pass. Deferred product defects were not modified.

---

## 2. Canonical starting-state verification

**Final migration starting state valid: YES**

All 27 content/layout families verified against `compilePopupRenderCss.ts`, serializers, and `MIGRATED` historical skip sets. Spinner and Product fully migrated. Zero partial migrations. Zero generic responsive merchant consumers before deletion.

---

## 3. Complete family inventory

| Family | Compiler | Inline merchant CSS | Generic responsive merchant | Status |
|--------|----------|---------------------|----------------------------|--------|
| form-surface | ✓ | — | — | MIGRATED |
| outer-layout | ✓ | — | — | MIGRATED |
| columns-container | ✓ | — | — | MIGRATED |
| column | ✓ | — | — | MIGRATED |
| button | ✓ | RTE link only | — | MIGRATED |
| text | ✓ | — | — | MIGRATED |
| text-input / email-input / date | ✓ | — | — | MIGRATED |
| textarea | ✓ | — | — | MIGRATED |
| dropdown | ✓ | — | — | MIGRATED |
| phone-input | ✓ | — | — | MIGRATED |
| radio | ✓ | — | — | MIGRATED |
| check-box | ✓ | — | — | MIGRATED |
| divider | ✓ | — | — | MIGRATED |
| coupon | ✓ | — | — | MIGRATED |
| image | ✓ | — | — | MIGRATED |
| video | ✓ | — | — | MIGRATED |
| social | ✓ | — | — | MIGRATED |
| feedback | ✓ | — | — | MIGRATED |
| timer | ✓ | — | — | MIGRATED |
| spinner | ✓ | — | — | MIGRATED |
| product | ✓ | — | — | MIGRATED |
| cross / floating-image | ✓ | — | — | MIGRATED |
| teaser | ✓ | structural inline only | hide-only | MIGRATED |
| gamification | ✓ | — | — | MIGRATED |

---

## 4. Merchant CSS emission inventory

| Source | Consumer | Concern | Classification | Action |
|--------|----------|---------|----------------|--------|
| `compilePopupRenderCss` | export/save | all merchant paint | MERCHANT PRESENTATION | **KEEP** |
| `*.export.css` sheets | serializeLayout | structure/state | STRUCTURE/BEHAVIOR | **KEEP** |
| `buildEmbeddedStepDeviceCss` | serializeLayout | hide + phone select | BEHAVIOR/STRUCTURE | **KEEP** (renamed) |
| `buildRichTextLinkExportCss` | serializeLayout | button RTE links | MERCHANT PRESENTATION (hostile-theme) | **KEEP** (deferred Stream B) |
| `applyRichTextLinkStyles` | button serialize | RTE link inline | MERCHANT PRESENTATION (hostile-theme) | **KEEP** (deferred Stream B) |
| `getStructuralExportBlockWrapperStyle` | serializeElements | flex-shrink | STRUCTURE | **KEEP** (new positive model) |
| `buildBlockTraitResponsiveCss` | was serializeLayout | merchant traits | DEAD | **DELETED** |

**Unknown CSS ownership findings: NONE**

---

## 5. Remaining inline-style inventory

| File | DOM owner | Declaration | Classification | Why inline required |
|------|-----------|-------------|----------------|---------------------|
| `serializeElements.ts` | block wrapper | `flex-shrink:0` | STRUCTURE | column flex child scaffold |
| `button/serialize.ts` | align div | `text-align`, `width:100%` | STRUCTURE | alignment container |
| `floating-image/serialize.ts` | shell | `pointer-events:auto` | BEHAVIOR | preview interaction |
| `serializeTeaser.ts` | teaser/img/span | dimensions, nowrap, pointer-events | STRUCTURE/BEHAVIOR | teaser chip mechanics |
| `serializeLayout.ts` | form | box model scaffold | STRUCTURE | form root |
| `popupHtmlSanitize.ts` | button RTE `<a>` | color, underline | MERCHANT (hostile-theme guard) | adcarts override defense |

```text
Known serialized merchant inline CSS remaining: button RTE <a> only (intentional)
Known runtime merchant inline CSS remaining: NONE (cross canvas hover is runtime state)
```

---

## 6. buildBlockTraitResponsiveCss consumer graph

```text
Definition: responsiveDeviceCss.ts (DELETED)
Direct consumer: buildEmbeddedStepResponsiveCss (DELETED call)
Indirect: serializeLayout.ts → embedded step <style>
Tests: 14 per-family exclusion tests (REPLACED)
Production output: empty string for all current block types
```

---

## 7. buildBlockTraitResponsiveCss decision

**DELETE** — Outcome A. Zero production CSS emitted; existed only to skip migrated families.

---

## 8. MIGRATED_BLOCK_TRAIT_TYPES audit

Membership previously skipped blocks from generic trait responsive loop. With all families migrated, set was equivalent to “all content blocks.” No production purpose after deletion.

---

## 9. Migration registry decision

**MIGRATED_BLOCK_TRAIT_TYPES action: DELETE**

---

## 10. migratedWrapperLayoutOwnership audit

Negative exclusion registry stripped merchant wrapper props after `getExportWrapperStyleFromLayout` computed them. Compute-then-strip anti-pattern.

---

## 11. Migration registry decision (wrapper)

**migratedWrapperLayoutOwnership action: DELETE → REPLACE WITH POSITIVE MODEL**

New: `exportBlockWrapperLayout.ts` → `getStructuralExportBlockWrapperStyle()` returns `{ flexShrink: 0 }` only. Serializers no longer compute merchant layout for export wrappers.

---

## 12. Shared wrapper style architecture audit

`getExportWrapperStyleFromLayout` retained for **renderCss compilers** mirroring canvas layout semantics. **Export serialization** no longer uses it for inline wrappers.

```text
Shared wrapper merchant presentation emission in serializeElements: NONE
```

---

## 13. Family-specific style-helper audit

| Helper | Action | Notes |
|--------|--------|-------|
| `getExportWrapperStyleFromLayout` | KEEP | renderCss compiler input |
| `getElementStyle` | KEEP | canvas legacy pockets (deferred) |
| `mergeBoxShadowIntoInlineStyle` | KEEP | canvas + legacy data fallback in resolvers |
| `buildBlockTraitResponsiveCss` | DELETE | obsolete |

---

## 14. Embedded style-generator inventory

13 production `*.export.css` sheets + `buildRichTextLinkExportCss` + `buildEmbeddedStepDeviceCss` output. No embedded merchant block-trait generator remains.

```text
Embedded merchant presentation generators remaining: buildRichTextLinkExportCss only (deferred)
```

---

## 15. Structural export-sheet audit

All 13 sheets audited — structure, responsive safety, behavior/state mechanics. Merchant paint tokens (e.g. `--pbn-checkbox-accent`) are structural state scaffolds consumed by renderCss-owned vars, not merchant inline.

```text
Merchant presentation in structural export sheets: NONE (paint in renderCss)
```

---

## 16. G1 coverage completeness

13/13 production `.export.css` sheets in fixture entry and verify script. **PASS**

---

## 17. renderCss compiler registry completeness

All merchant-presentational block families have compilers. Layout-only elements (columns) use layout compilers. Cross/floating-image use positioned decorative compiler.

---

## 18. Merchant selector scoping audit

All `compile*PresentationRenderCss` outputs use `[data-adflipr-popup-artifact="…"]` scoping.

```text
Global merchant selectors in renderCss compilers: NONE
```

---

## 19. Same-concern hybrid ownership audit

Export wrappers no longer emit margin/width alongside renderCss wrapper rules. Button RTE links remain dual-path (deferred Stream B).

```text
Same-concern merchant hybrid ownership: button RTE links only (known deferred)
```

---

## 20. Canvas/export canonical normalization audit

| Family | Classification |
|--------|----------------|
| image, video, social, feedback, coupon, product, spinner, button, etc. | CANONICAL or ADAPTER OVER CANONICAL |
| date, phone canvas | DUPLICATED (getElementStyle) — deferred hygiene |
| feedback shell resolver | uses getElementStyle internally — shared both sides |

---

## 21. Migration-era compatibility branch audit

Deleted: `MIGRATED_BLOCK_TRAIT_TYPES`, `buildBlockTraitResponsiveCss`, wrapper stripping registry. Retained: `buildEmbeddedStepResponsiveCss` as thin deprecated alias.

---

## 22. Persisted-data compatibility audit

```text
Deletion breaks persisted popup rendering: NO
```

Old records compile via renderCss resolvers; removed code paths emitted no CSS for migrated families.

---

## 23–25. Legacy deletion summary

- Generic responsive merchant architecture: **DELETED**
- Serializer compute-then-strip wrapper: **DELETED**
- Migration registries: **DELETED**

---

## 26. Repository-wide closure search

Post-cleanup grep: only test guardrails reference deleted symbols.

---

## 27–31. Steady-state test evidence

| Test | Result |
|------|--------|
| Cross-family steady-state (`popupCssSteadyStateArchitecture.test.ts`) | **PASS** |
| Multi-step union/dedup | **PASS** |
| Artifact isolation | **PASS** |
| Embedded device CSS steady-state | **PASS** |
| Export wrapper structural parity | **PASS** |

Runtime isolation and Chromium hostile-theme: covered by existing per-family browser tests (not re-run in 8A gate loop; Phase 0 baseline PASS).

---

## 32. Production Save evidence

Serializer emits structural wrapper inline only; `renderCss` contains merchant presentation. Structural CSS in `<style>` blocks parseable.

---

## 33–35. Gate results

| Gate | Result |
|------|--------|
| G1 production raw CSS contract | **PASS** |
| Production build | **PASS** |
| Phase 0 canonical migrated | **25/25 PASS, 0 red, 5 controls green** |

---

## 36–37. Final inventories

```text
Final partial migrations: NONE
Final inline merchant families: button RTE <a> only
Final generic responsive merchant consumers: NONE
Final same-concern hybrid families: button RTE links (deferred)
Obsolete legacy CSS infrastructure remaining: NONE
```

| Legacy component | Final status |
|------------------|--------------|
| `buildBlockTraitResponsiveCss` | DELETED |
| `MIGRATED_BLOCK_TRAIT_TYPES` | DELETED |
| `migratedWrapperLayoutOwnership` | DELETED → `exportBlockWrapperLayout.ts` |

---

## 38. Documentation cleanup

Updated `popup-css-migration-current-state.md`. Historical phase reports retained.

---

## 39. Forward-only architecture contract

Documented in `popup-css-migration-current-state.md` §Steady-state architecture.

---

## 40. Future guardrails added

`popupCssArchitectureGuardrails.test.ts` — forbids reintroduction of deleted symbols; asserts structural wrapper + renderCss ownership.

---

## 41. Deferred defects preserved

Not modified: button linked-label hover, checkbox backgroundColor orphan, block customCss/customClass, coupon boxShadow legacy, image elementWidth orphan, timer runtime fit, date/phone canvas drift.

---

## 42. Remaining architecture debt

1. Block-level customCss/customClass export pipeline (Track 2 Stream A)
2. Button RTE link hover parity (Stream B)
3. Date/phone canvas → canonical resolver adapters
4. Structural-inline final cleanup (teaser img, etc.)

---

## 43. Architecture gates

All Phase 8A gates **PASS**.

---

## 44. Final verdict

**ACCEPT** — Migration architecture closed; obsolete infrastructure deleted; steady-state contracts in place.

---

## 45. Recommended next phase

**Track 2 Stream A: Block-level `customCss` / `customClass` artifact export pipeline** — largest proven-real user-facing gap; independent of closed migration infrastructure.

---

## Files changed (Phase 8A)

| Action | Path |
|--------|------|
| DELETE | `migratedWrapperLayoutOwnership.ts` |
| ADD | `exportBlockWrapperLayout.ts` |
| MODIFY | `responsiveDeviceCss.ts`, `serializeElements.ts` |
| ADD | `exportBlockWrapperLayout.test.ts`, `popupCssArchitectureGuardrails.test.ts`, `popupCssEmbeddedDeviceCssSteadyState.test.ts`, `popupCssSteadyStateArchitecture.test.ts` |
| DELETE | per-family `buildBlockTraitResponsiveCss` exclusion tests |
| RENAME | `migratedWrapperLayoutExport.test.ts` → expanded `exportBlockWrapperLayout.test.ts` |
