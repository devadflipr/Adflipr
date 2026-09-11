# Popup CSS architecture — Phase 4E acceptance verification and closure audit

**Status:** Complete (2026-07-05)  
**Type:** Acceptance audit — verify Phase 4D claims, fix confirmed defects only  
**Related:** [`popup-column-presentation-css-phase4d-report.md`](./popup-column-presentation-css-phase4d-report.md), [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md), [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md)

---

## A. Acceptance verdict

**ACCEPT WITH FIXES** — Phase 4D forward-only architecture is substantively complete. Phase 4E corrected confirmed contract defects and added gamification browser evidence. Phases 1–4 may close; Phase 5 (block CSS migration) was not started.

---

## B. Six-question scoreboard

| # | Question | Pass/Fail | Evidence | Fix (Phase 4E) |
|---|----------|-----------|----------|----------------|
| 1 | Internal runtime contract still allows missing `renderCss`? | **Pass** (after fix) | `PopupEmbedConfig.renderCss: string` required in `embed/types.ts`; `normalizeEmbedConfig()` always sets `renderCss` (`''` when absent/malformed). `phase4eAcceptance.test.ts` | Made internal contract required; normalization coerces to `''` |
| 2 | `artifactScopeId` mandatory at compile time and non-blank at runtime? | **Pass** | `PopupHtmlSerializeOptions.artifactScopeId: string`; `requireArtifactScopeId()` throws on missing/blank. `phase4dArchitecture.test.ts` | None required |
| 3 | Gamification compiler preserves legacy properties? | **Pass** | Property matrix §E; `compileGamificationRenderCss()` uses `buildStandardFormSurfacePresentationStyle` + slot rules matching deleted inline/embedded values; browser tests green | Adjusted browser test width expectations for border-box (238px / 371px) |
| 4 | Production can still construct mismatched HTML/CSS fragments? | **Pass** (with documented read boundaries) | Save/preview compile atomically via `CompiledPopupArtifact`. List/thumbnail/API still transport separate fields (storage model); consumers pair via `normalizeCompiledPreviewArtifact`. No production compile path splits artifact fields | Restored `CompiledPopupArtifact` interface (was circular self-import); preview paths always pass `renderCss` |
| 5 | Canonical KB still describes removed dual/legacy architecture? | **Pass** (after fix) | Updated `popup-rendering-css-architecture.md` §3, §16; `popup-html-serialization.md` embed API; 4C audit marked historical | KB corrections in Phase 4E |
| 6 | Tests prove gamification desktop/mobile computed-style correctness? | **Pass** (after fix) | New `gamificationPresentation.browser.test.ts` — Playwright/Chromium, production serializer + mount lifecycle, `getComputedStyle`, desktop + mobile | Added browser test suite |

---

## C. API trust-boundary map

```text
Untrusted external payload
    ↓
GET /api/v1/popups/embed/{popupId}  (Java PopupEmbedResponse)
    ↓
fetchPopupEmbedConfig() → Promise<unknown>
    ↓
normalizeEmbedConfig(raw)  ← TRUST BOUNDARY ENDS HERE FOR EMBED
    ↓
PopupEmbedConfig { renderCss: string }  ← TRUSTED INTERNAL RUNTIME CONFIG
    ↓
PopupEmbedRuntime.config
    ↓
ensureRenderArtifactCss() → mountRenderArtifactCss()
```

| Layer | Type/function | `renderCss` type | Trusted? | Can be missing? | Correct final contract? |
| ----- | ------------- | ---------------- | -------- | --------------- | ----------------------- |
| Backend DTO | `PopupEmbedResponse` (`popupApi.types.ts`, Java) | `renderCss?: string` | No | Yes (omitted when blank in metadata) | Yes — transport |
| HTTP JSON | Raw response body | `unknown` / optional string | No | Yes | Yes |
| Fetch | `fetchPopupEmbedConfig()` | `Promise<unknown>` | No | Yes | Yes |
| Normalization | `normalizeEmbedConfig()` | output always `string` | **Yes** | No (coerced to `''`) | **Yes** |
| Internal config | `PopupEmbedConfig` | `renderCss: string` | **Yes** | No | **Yes** |
| Preview simulation | `buildPreviewEmbedConfig()` | always sets `artifact.renderCss` | **Yes** | No | **Yes** |
| Runtime mount | `ensureRenderArtifactCss()` | reads `config.renderCss` | **Yes** | Empty → skip mount, **no legacy inline fallback** | **Yes** |
| Thumbnail read | `normalizeCompiledPreviewArtifact()` | input optional; output `string` | Partial | Input may omit; output `''` | Yes for read boundary |
| Save compile | `CompiledPopupArtifact` | `renderCss: string` | **Yes** | No (always emitted) | **Yes** |

### Explicit answers (Part 1)

1. **Where does untrusted input end?** After `normalizeEmbedConfig()` (embed) or after `serializePopupExport()` (builder compile).
2. **Where does trusted internal contract begin?** `PopupEmbedConfig` and `CompiledPopupArtifact`.
3. **Is `renderCss` required after that boundary?** Yes — always `string` (may be `''`).
4. **Server omits `renderCss`?** Normalized to `''`; runtime skips artifact mount; unmigrated embedded step CSS still hoists.
5. **`null`, whitespace, number, malformed?** Coerced to `''` via `normalizeRenderCssValue` + `?? ''`.
6. **Legacy “use old HTML styling” branch for missing `renderCss`?** **No** for migrated owners (form surface, layout, columns, column, gamification). Unmigrated block/trait CSS still in embedded step sheets by design (Phase 5 scope).

---

## D. `artifactScopeId` caller matrix

| Caller | Prod/test | How scope is obtained | Compile-time required? | Runtime validated? |
| ------ | --------- | --------------------- | ---------------------- | ------------------ |
| `FormBuilder.tsx` (save/update) | Production | `popupId` from editor route | Yes (`PopupHtmlSerializeOptions`) | Yes (`requireArtifactScopeId` throws if blank) |
| `popupTemplateImport.ts` | Production | `created.popupId` after API create | Yes | Yes |
| `previewArtifactExport.ts` | Production | `options.artifactScopeId` or `PREVIEW_SPIN_OUTCOME_POPUP_ID` | Yes (via `serializePopupExport`) | Yes |
| `formPreviewEmbedPresentation.ts` | Production | `PREVIEW_SPIN_OUTCOME_POPUP_ID` | Yes | Yes |
| `serializedTeaserPreviewMount.ts` | Production | `'pbn-teaser-preview'` constant | Yes | Yes |
| `responsiveCssBaselineHarness.ts` | Test | Parameter / default scope | Yes | Yes |
| All `*.test.ts` / `*.browser.test.ts` | Test | `testSerializeOptions()` / explicit id | Yes | Yes |

**Preview note:** `buildPreviewArtifactExport` accepts optional `artifactScopeId` in options but always resolves to a non-blank constant before calling `serializePopupExport` — intentional stable preview identity, not unscoped compilation.

---

## E. Gamification parity matrix

Audited against Phase 4C legacy path (`buildEmbeddedFormSurfaceStyle` + embedded `buildGamificationResponsiveCss`) vs `compileGamificationRenderCss()` (`gamificationPresentation.ts`).

### Outer gamification surface

| Property | Old source | New source | Desktop | Mobile | Computed test? |
| -------- | ---------- | ---------- | ------- | ------ | ------------ |
| background-color | Inline / embedded surface | `buildStandardFormSurfacePresentationStyle` in renderCss | ✓ | ✓ | ✓ browser |
| background-image family | formStyle | same compiler path | ✓ | ✓ | — |
| border / radius / shadow | formStyle | same | ✓ | ✓ | — |
| width / max-width | formStyle | same | ✓ | ✓ | — |
| box-sizing | structural prelude | same | ✓ | ✓ | — |
| position | formType rules | same | ✓ | ✓ | — |
| padding (surface) | `0` on gamification shell | explicit `padding:0` in compiler | ✓ | ✓ | ✓ browser (`0px`) |
| display / flex-direction | inline row | renderCss row; mobile `@media` column | ✓ | ✓ | ✓ browser |
| overflow / font-family / min-height | formStyle | via standard surface builder | ✓ | ✓ | — |

### Spinner slot

| Property | Old | New | Desktop | Mobile | Test? |
| -------- | --- | --- | ------- | ------ | ----- |
| width | 50% inline | 50% in renderCss | ✓ (238px content box) | 100% | ✓ |
| flex basis | implicit 50% | width 50% | ✓ | 100% | ✓ |
| padding | 32px 24px | same | ✓ | 24px 16px mobile | ✓ |
| box-sizing | border-box | same | ✓ | ✓ | — |
| alignment | flex center | same | ✓ | ✓ | — |

### Form slot

| Property | Old | New | Desktop | Mobile | Test? |
| -------- | --- | --- | ------- | ------ | ----- |
| width | 50% | 50% | ✓ (238px) | 100% (371px) | ✓ |
| padding | 32px 40px | same | ✓ | 16px 20px 24px | ✓ |
| box-sizing | border-box | same | ✓ | ✓ | — |

### Responsive behavior

| Behavior | Old | New | Preserved? | Test? |
| -------- | --- | --- | ---------- | ----- |
| Desktop row | inline flex row | renderCss | ✓ | ✓ |
| Mobile column | embedded `@media` | `wrapArtifactMobileRules` | ✓ | ✓ |
| Full-width slots mobile | embedded | `width:100% !important` | ✓ | ✓ |
| No inline desktop defeating mobile | N/A on artifact path | migrated owners identity-only HTML | ✓ | ✓ |
| `!important` on mobile stack | yes (legacy) | yes (mobile rules) | ✓ intentional | — |

**Verdict:** No accidental property loss identified. Inner layout/column/columns compilers run unchanged for gamification form column content.

---

## F. Artifact atomicity matrix

| Boundary | HTML source | CSS source | Scope source | Can mismatch? | Action |
| -------- | ----------- | ---------- | ------------ | ------------- | ------ |
| `serializePopupExport()` return | Same compile | Same compile | Same compile | **No** | — |
| `buildPopupSaveMetadata()` | `htmlExport.*` | `htmlExport.renderCss` | implicit in HTML attrs | **No** on save | Clears stale `metadata.renderCss` when empty |
| API persist (`htmlContent` + metadata) | Save payload | Same export | `popupId` on save | **No** on new save | Storage is separate fields (backend model) |
| Embed API response | Stored metadata | Stored metadata | In HTML `data-adflipr-popup-artifact` | Theoretically if DB corrupt | Out of compile scope |
| `buildPreviewArtifactExport()` | Single export | Same export | Same export | **No** | Phase 4E: always passes `renderCss` |
| `normalizeCompiledPreviewArtifact()` | Caller HTML | Caller CSS (defaults `''`) | Not validated here | **Yes** if caller passes wrong CSS | Read-only pairing helper |
| Thumbnail iframe | `html` + `renderCss` props | Paired by caller | Scope in HTML | **Yes** if UI passes wrong pair | Document contract |
| List/detail DTO | `htmlContent` / thumbnail | `metadata.renderCss` | From saved artifact | Only if stale DB | Re-save fixes |

**Required invariant satisfied for compilation:** One `serializePopupExport` → matching HTML + `renderCss` + scope.

---

## G. KB classification

| Document | Classification | Phase 4E action |
| -------- | -------------- | --------------- |
| `popup-rendering-css-architecture.md` | **CURRENT CANONICAL** | Updated §3 (4D architecture), §16 (runtime read boundary) |
| `popup-builder-rendering-pipeline.md` | **CURRENT CANONICAL** | Accurate for pipeline; legacy mentions are non-CSS (customJs) |
| `popup-html-serialization.md` | **CURRENT CANONICAL** | Updated embed API to include `renderCss` |
| `popup-css-lifecycle-audit.md` | **HISTORICAL EVIDENCE** (as-is audit) | No change — motivates migration |
| `popup-render-artifact-contract-phase1a.md` | **HISTORICAL EVIDENCE** | Phase 1 decision record |
| `popup-column-presentation-css-phase4d-report.md` | **IMPLEMENTATION REPORT** | Accurate |
| `popup-block-css-phase5a-ownership-audit.md` | **CURRENT (Phase 5A)** | Block ownership audit |
| `popup-column-presentation-css-phase4b-report.md` | **IMPLEMENTATION REPORT** | Historical dual-path notes — superseded |
| `popup-columns-container-css-phase3c-report.md` | **IMPLEMENTATION REPORT** | Legacy path sections — historical |
| `popup-responsive-css-phase0-baseline.md` | **HISTORICAL EVIDENCE** | Pre-migration baseline |
| `popup-block-css-phase5a-ownership-audit.md` | **CURRENT (Phase 5A)** | Block ownership |

---

## H. Browser test evidence

**File:** `adflipr-app/src/layouts/PopupBuilder/utils/__tests__/gamificationPresentation.browser.test.ts`

| Owner | Desktop assertion | Mobile assertion |
| ----- | ----------------- | ---------------- |
| Outer surface | `backgroundColor: rgb(255, 238, 204)`; `display: flex`; `flexDirection: row`; `paddingTop: 0px` | `flexDirection: column` |
| Spinner slot | `width: 238px` (50% of 480px − 4px border); `paddingTop: 32px`; `paddingRight: 24px` | `width: 371px`; `paddingTop: 24px`; `paddingRight: 16px` |
| Form slot | `width: 238px`; `paddingTop: 32px`; `paddingRight: 40px` | `width: 371px`; `paddingTop: 16px`; `paddingBottom: 24px` |

Harness: Playwright Chromium, `mountPopupSnapshot()` (production serializer + `mountRenderArtifactCss` lifecycle), `getComputedStyle` via `readComputed()`.

---

## I. Production fixes made (Phase 4E only)

| Fix | Files |
| ----- | ----- |
| Required internal `renderCss: string` on `PopupEmbedConfig` | `embed/types.ts` |
| Normalization always emits `renderCss` (`''` when absent/malformed) | `embed/utils.ts` |
| Preview paths always pass `renderCss` (no conditional spread) | `formPreviewEmbedPresentation.ts` (orphan `SerializedFormPreviewCanvas` / `serializedFormPreviewMount` since removed) |
| Restored `CompiledPopupArtifact` interface (removed circular self-import) | `popupCompiledArtifact.ts` |
| Test fixture helper `embedConfigFixture()` | `embed/testFixtures.ts` |
| Gamification computed-style browser tests | `gamificationPresentation.browser.test.ts` |
| Embed trust-boundary tests | `phase4eAcceptance.test.ts` |
| KB canonical updates | `popup-rendering-css-architecture.md`, `popup-html-serialization.md`, `README.md` |
| Test infra: syntax fix + flush helper in `popupEmbedRuntime.test.ts` | (test-only) |

**Not changed:** Block CSS, cross button, teaser migration, runtime shell CSS, Phase 5 work.

---

## J. Test commands and exact results

```bash
cd adflipr-app

# Phase 4E acceptance + architecture-negative
npx craco test --watchAll=false --testPathPattern="phase4e|phase4d|gamificationPresentation.browser|phase4eAcceptance|renderArtifactCss"
# → 5 suites, 29 tests passed

# Migrated-owner browser + compiler suites
npx craco test --watchAll=false --testPathPattern="phase4e|phase4d|gamification|formSurface|outerLayout|columnsContainer|columnPresentation|popupCompiled|popupCssInline|renderArtifactCss"
# → 15 suites, 138 tests passed
```

Architecture-negative coverage (`phase4dArchitecture.test.ts`):

- Unscoped export → throws
- Blank scope → throws
- No migrated-owner inline presentation
- No migrated-owner embedded responsive rules in step HTML
- Gamification uses renderCss without legacy inline migrated owners

---

## K. Repository search evidence

Post-4D/4E grep (production symbols):

| Pattern | Result |
| ------- | ------ |
| `useStandardSurfaceRenderCss` | **0** |
| `skipStandardFormSurfaceResponsive` / skip flags | **0** |
| `buildEmbeddedFormSurfaceStyle` / `buildResponsiveDeviceCss` | **0** |
| `buildGamificationResponsiveCss` | **0** |
| `gamification legacy` (code) | **0** |

Remaining **intentional** optional `renderCss` at **read/transport** boundaries (not internal runtime contract):

- `PopupEmbedResponse.renderCss?: string` (raw API)
- `normalizeCompiledPreviewArtifact({ renderCss?: ... })` (thumbnail/list input)
- `buildPopupThumbnailIframeDocument(html, renderCss?: string)` (must receive paired args)
- `CampaignList` / `PopupDetail` thumbnail props (DTO fields)

---

## L. Closure decision

**Phases 1–4 are closed** for the forward-only artifact architecture scope (form surface, outer layout, columns container, column presentation, gamification shell, embed trust boundary, compile-time scope).

No additional Phase 4 correction is required before Phase 5 planning.

---

## M. Next-step recommendation

**Next audit boundary (Phase 5 planning — do not implement yet):**

> What are the actual painted DOM owners for block padding, margin, border radius, and responsive traits?

Derive from:

1. `buildEmbeddedStepResponsiveCss()` owner list in Phase 4D report §6  
2. `popup-css-lifecycle-audit.md` §11 inline mix for unmigrated blocks  
3. Computed-style browser baselines per block family (same harness as Phase 2B–4E)

First migration candidate should be chosen by cascade risk + test coverage, not by serializer convenience.

---

## Definition of done checklist

| # | Criterion | Met? |
|---|-----------|------|
| 1 | Raw API optionality separated from trusted runtime | ✓ |
| 2 | Internal `renderCss` semantics explicit | ✓ |
| 3 | Artifact scope compile-time mandatory + runtime non-blank | ✓ |
| 4 | Gamification property parity proven | ✓ |
| 5 | Compile boundaries atomic | ✓ |
| 6 | Canonical KB updated | ✓ |
| 7 | Gamification desktop/mobile browser proof | ✓ |
| 8 | Phase 5 not started | ✓ |
| 9 | Test evidence recorded | ✓ |
| 10 | Closure decision documented | ✓ |
