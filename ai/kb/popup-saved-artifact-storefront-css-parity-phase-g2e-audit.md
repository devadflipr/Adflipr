# Phase G2E — Saved Artifact and Production Storefront CSS Parity Audit

**Date:** 2026-07-06  
**Phase:** Read-only consumer parity audit — **no** production code changes, **no** test changes, **no** block-family migration.  
**Parallel with:** Phase G2D (CSS writer / trait / RTE / mobile override inventory — not duplicated here).  
**Related:** [`popup-render-artifact-contract-phase1a.md`](./popup-render-artifact-contract-phase1a.md), [`popup-export-css-g1-closure-and-contamination-audit.md`](./popup-export-css-g1-closure-and-contamination-audit.md), [`popup-production-css-rte-ownership-audit.md`](./popup-production-css-rte-ownership-audit.md)

---

## 1. Executive verdict

**Verdict: ACCEPT WITH ACTIONS**

The **canonical compile contract is sound**: `serializePopupExport` produces one atomic `CompiledPopupArtifact` (HTML + `renderCss` + scope) on Save, and preview recompiles through the same entry point (`buildPreviewArtifactExport` → `serializePopupExport`). Storefront embed consumes persisted `metadata.htmlByStep` + `metadata.renderCss` via `PopupEmbedResponse` and mounts them in the correct order (`renderCss` before step HTML).

**Consumer parity is not fully uniform.** Gaps are **narrow and classifiable** — not a redesign of the artifact model:

| Gap class | Severity | Summary |
|-----------|----------|---------|
| Editor canvas (`FormCanvas`) | Valid difference | React trait projection — not an artifact consumer |
| Preview scope id (`pbn-preview` vs saved `popupId`) | Valid difference | HTML + `renderCss` stay paired per compile |
| Style-only teaser preview overlay | Partial | `TeaserPreview` React vs serialized teaser HTML + `renderCss` |
| Block-teaser preview HTML scope | Stale-risk | `resolvePreviewTeaserHtml` uses `pbn-teaser-preview` while main artifact uses `pbn-preview` |
| Thumbnails | Partial | Primary-step `htmlContent` only; paired `renderCss` when saved |
| Legacy / pre-save records | Stale-risk | Missing `renderCss` / `htmlByStep`; pre-G1 embedded stub contamination |
| Unmigrated block families | G2D-owned | Inline merchant CSS in step HTML — not a consumer drop |

**Recommended strategy: B** — continue block-family migration **plus** narrow consumer parity cleanup (preview teaser scope, legacy load guards documentation, operator re-save for pre-G1).

---

## 2. Audit-only statement

- No production code modified in this phase.
- No tests added or modified.
- No G2D CSS writer inventory duplicated.
- Findings reference file/symbol evidence only.

---

## 3. Part A — Canonical save path trace

### Flow

```text
FormBuilder.handleSave
  → buildCurrentSnapshot()
  → serializePopupExport(snapshot, { artifactScopeId: popupId, locale })
  → CompiledPopupArtifact
  → popupApi.updatePopup({ htmlContent, metadata: buildPopupSaveMetadata(...) })
  → PUT /api/v1/popups/{id}
  → Popup entity (MongoDB)
```

### Field matrix

| Stage | Symbol / file | artifactScopeId | htmlContent | htmlByStep | primaryStepId | renderCss |
|-------|---------------|-----------------|-------------|------------|---------------|-----------|
| Compile | `serializePopupExport` (`popupHtmlSerializer/index.ts`) | `options.artifactScopeId` (= `popupId`) | `htmlByStep[primary]` or fallback `serializePopupHtml` | `serializePopupHtmlByStep` | `resolvePrimaryStepId` | `compilePopupRenderCss(snapshot, scope)` |
| Save payload | `FormBuilder.handleSave` | implicit in HTML attrs + CSS selectors | `htmlExport.htmlContent` | `metadata.htmlByStep` JSON | `metadata.htmlPrimaryStepId` | `metadata.renderCss` (if non-empty) |
| API model | `UpdatePopupRequestBody` (`popupApi.types.ts`) | — | `htmlContent?` | via `metadata` map | via `metadata` | via `metadata.renderCss?` |
| Persistence | `Popup` entity | in HTML + CSS | `htmlContent` column | `metadata.htmlByStep` | `metadata.htmlPrimaryStepId` | `metadata.renderCss` |
| Embed read | `PopupEmbedResponse.fromEntity` | in HTML + CSS | fallback into `htmlByStep` if map empty | parsed map | `htmlPrimaryStepId` | pass-through `renderCss` |

### Coherent artifact on Save?

**YES** — single `serializePopupExport` call; `buildPopupSaveMetadata` writes `htmlByStep`, `htmlPrimaryStepId`, and `renderCss` from the same `htmlExport` object. Empty `renderCss` explicitly deletes stale `metadata.renderCss` (Phase 1B).

### Parity risks on save path

| Risk | Present? | Evidence |
|------|----------|----------|
| HTML compiled separately from `renderCss` | **No** on save | Same function returns both |
| `renderCss` from older state | **No** on save | Same snapshot object |
| `artifactScopeId` regenerated mid-save | **No** | Fixed `popupId` per save |
| `htmlContent` vs `htmlByStep` different passes | **Rare edge** | Fallback second `serializePopupHtml` only if primary missing from `htmlByStep` — same snapshot |
| `primaryStepId` inferred independently | **No** | Same `resolvePrimaryStepId` in export |
| Fields optionalized on save | **renderCss optional when empty** | Intentional — key omitted when blank |

---

## 4. Part B — Compile-once vs compile-multiple-times

### Call inventory

| Consumer | Entry | Classification |
|----------|-------|----------------|
| **Save** | `serializePopupExport` | CANONICAL ARTIFACT CONSUMER |
| **Preview (FormPreview)** | `buildPreviewArtifactExport` → `serializePopupExport` | CANONICAL (recompile from live state) |
| **Template import** | `serializePopupExport` | CANONICAL |
| **Teaser preview HTML inject** | `resolvePreviewTeaserHtml` → `serializePopupHtmlFromParts` / `serializeTeaserHtml` | PARTIAL RECOMPILATION (teaser slice only) |
| **Storefront** | Load persisted artifact — no compile | CANONICAL ARTIFACT CONSUMER |
| **Thumbnails** | Load `htmlContent` + `metadata.renderCss` | CANONICAL ARTIFACT CONSUMER (persisted) |
| **Editor canvas** | `FormCanvas` + `ElementStyles` React | LEGACY HTML-ONLY PATH (not artifact — intentional) |
| **Tests / harness** | Direct `serializePopupExport` / `compilePopupRenderCss` | CANONICAL (verification) |

### Count

**Independent popup compilation paths: 3** (canonical export, teaser partial `fromParts`, editor React canvas — third is not export).

**Legacy HTML-only paths: 2** (embed `htmlContent` fallback when `htmlByStep` empty; thumbnails use `htmlContent` not full `htmlByStep`).

---

## 5. Part C — Builder Preview trace

Two distinct “preview” surfaces exist:

### C1 — In-editor canvas (`FormCanvas`)

| Concern | Source |
|---------|--------|
| HTML | **None** — live React components |
| renderCss | **None** — `getElementStyle` / `ElementStyles` resolvers → inline React styles |
| artifactScopeId | **N/A** |
| Embedded CSS | **None** |
| Freshness | Current unsaved builder state |

**Classification:** PARITY WITH VALID RUNTIME DIFFERENCE — editing surface, not artifact consumer.

### C2 — Modal / tab preview (`FormPreview` → `mountFormPreviewEmbedPresentation`)

| Concern | Source |
|---------|--------|
| HTML | `buildPreviewArtifactExport` → `serializePopupExport` → `htmlByStep` |
| renderCss | Same export → `artifact.renderCss` |
| artifactScopeId | `PREVIEW_SPIN_OUTCOME_POPUP_ID` (`'pbn-preview'`) — **not** `popupId` |
| Embedded CSS | In step HTML strings; hoisted by embed `hoistExportedStepStyles` |
| Mount order | `createPopupEmbedRuntime` → `ensureRenderArtifactCss()` **then** `renderStepInto` (HTML + hoist) |
| Teardown | `runtime.destroy()` → `clearRenderArtifactCss()` + shell removal |
| Freshness | **Current builder state** (modal); **sessionStorage draft** or **saved API** (preview tab) |

| Check | Result |
|-------|--------|
| renderCss before visual mount | **Yes** (`presentConfig` → `ensureRenderArtifactCss` before `renderStepInto`) |
| Old renderCss after popup change | **No** — effect teardown destroys runtime |
| Two preview artifacts collide | **Low** — owner id = `runtimeInstanceId` UUID per runtime |
| Unsaved vs saved | Modal uses live state; tab may use draft snapshot |
| Scope stable per preview session | **Yes** — `pbn-preview` for paired HTML + CSS |

**Classification:** PARTIAL — compiler parity YES; teaser overlay + scope id differ from saved artifact.

### Dead path note (resolved)

`SerializedFormPreviewCanvas` / `serializedFormPreviewMount` were orphan preview paths and have been **deleted**. Builder preview uses only `mountFormPreviewEmbedPresentation` → `PopupEmbedRuntime`.

---

## 6. Part D — Fullscreen Preview trace

| Path | Implementation |
|------|----------------|
| Editor “Preview” button (with `popupId`) | New tab `?preview=1&previewDraft=1` + optional `sessionStorage` snapshot (`openPopupEditorPreviewInNewTab`) |
| Editor without save | In-tab `FormPreview` modal (`showFullscreenPreview`) |
| Popup Detail “Preview” | New tab without draft — loads saved popup from API |

**Fullscreen tab and modal both render `FormPreview`** — same embed presentation stack.

| Question | Answer |
|----------|--------|
| Same effective CSS as Builder Preview modal? | **YES** — same `mountFormPreviewEmbedPresentation` |
| vs saved storefront? | **PARTIAL** — draft tab matches unsaved state; detail preview matches saved; scope id `pbn-preview` vs `popupId` |
| iframe boundary | Preview tab is full document; presentation mounts in `presentationRoot` div (not iframe) |
| Style cleanup | `runtime.destroy()` on unmount |

**Verdict: PARTIAL** (draft vs saved semantics; teaser overlay; scope constant).

---

## 7. Part E — Thumbnail traces

### Popup detail thumbnail (`PopupDetail.tsx`)

| Field | Source |
|-------|--------|
| HTML | `popup.htmlContent` (primary step at save) |
| renderCss | `parseRenderCss(popup.metadata)` |
| Mount | `PopupThumbnailView` → `buildPopupThumbnailIframeDocument` — `<style data-adflipr-popup-render-css>` in iframe `<head>`, then body HTML |

### Campaign / list thumbnail (`CampaignList.tsx`)

| Field | Source |
|-------|--------|
| HTML | `PopupListRow.thumbnailHtml` ← backend `popup.getHtmlContent()` |
| renderCss | `PopupListRow.renderCss` ← `metadata.renderCss` |
| Mount | Same `PopupThumbnailView` |

### Thumbnail matrix

| Consumer | HTML | renderCss | Embedded CSS in HTML | Same saved artifact? |
|----------|------|-----------|----------------------|----------------------|
| Detail thumbnail | `htmlContent` | `metadata.renderCss` | Hoisted sheets inside `htmlContent` string | **Yes** (primary step pair) |
| List thumbnail | `htmlContent` | `metadata.renderCss` | Same | **Yes** |
| Multi-step content | **Not shown** | Full-popup `renderCss` (all steps) | N/A | **Partial** — CSS union may include other steps; HTML is primary only |

### Risks found

| Risk | Present? |
|------|----------|
| HTML-only thumbnail | **Only if** `renderCss` missing in metadata — iframe still renders HTML |
| Stale saved renderCss | **Possible** on pre-migration records until re-save |
| Recompiled HTML + persisted CSS | **No** — both from last save |
| Missing scope in HTML | **No** on current saves — `POPUP_ATTR_ARTIFACT` on form (`serializeLayout.ts`) |

**Verdict: PARTIAL** (primary-step HTML vs full-artifact CSS; legacy missing `renderCss`).

---

## 8. Part F — Storefront Embed trace

```text
fetchPopupEmbedConfig(api, popupId)
  → PopupEmbedResponse (Java) / normalizeEmbedConfig (TS)
  → PopupEmbedRuntime.loadAndShowPopup
  → presentConfig(stepId)
      1. ensureRenderArtifactCss()     // document.head, owner=runtimeInstanceId
      2. renderStepInto(container, step)
           a. container.innerHTML = html
           b. hoistExportedStepStyles(container)
           c. initAllBlocks / handlers
```

| Check | Result |
|-------|--------|
| renderCss in API payload | **Yes** when saved (`PopupEmbedResponse.renderCss`) |
| htmlByStep | **Yes**; fallback synthesizes from `htmlContent` |
| artifactScopeId in HTML | **`popupId`** from save |
| Order | **CSS before HTML** |
| Step transition | **renderCss retained**; per-step hoist torn down on `disposeStepBlockInits` |
| FOUC risk | **Low** when `renderCss` present; **higher** when missing |
| Duplicate style mount | **Guarded** — `mountedRenderCss === css` short-circuit |
| Scope mismatch | **No** — HTML attrs match CSS selectors when saved coherently |

**Verdict: FULL** for post-migration saved popups; **PARTIAL** for legacy records.

---

## 9. Part G — Multi-step parity

`compilePopupRenderCss` collects presentations **across all steps** in `snapshot.formElements` (buttons, text, fields, columns, etc.). `htmlByStep` holds per-step HTML; each step’s embedded export sheets hoist on step entry.

| Question | Answer |
|----------|--------|
| All-step CSS union in `renderCss`? | **YES** |
| Same `artifactScopeId` in all step HTML? | **YES** on save (`popupId`) |
| Step switch replaces artifact CSS? | **NO** — artifact CSS persists |
| Removed step hoist leak? | **Teardown** on `disposeStepBlockInits` removes hoisted step styles |
| Later step missing required CSS? | **NO** for current architecture — union compiled up front |

**Multi-step parity: PASS**

---

## 10. Part H — Gamification parity

`compilePopupRenderCss` adds `compileGamificationRenderCss` when `snapshotUsesGamificationSurface(snapshot)`.

| Consumer | Shell renderCss | Column renderCss | Gamification renderCss | Embedded behavior CSS |
|----------|-----------------|------------------|------------------------|-------------------------|
| Save | Yes | Yes | Yes (if gamification) | In step HTML |
| FormPreview | Yes (recompile) | Yes | Yes | Hoisted |
| Thumbnail | Partial (primary HTML) | In `renderCss` if on other steps | In `renderCss` | In `htmlContent` string |
| Storefront | Yes (persisted) | Yes | Yes | Hoisted |

**Gamification artifact parity: PARTIAL** (thumbnail primary-step HTML only).

---

## 11. Part I — Teaser parity

Split model (by design):

| Layer | Owner |
|-------|--------|
| Teaser chip merchant presentation | `compileTeaserPresentationRenderCss` → artifact `renderCss` (style-only teaser) |
| Teaser host positioning / animation | `teaserExportCss` + `ensureTeaserExportStyles` |
| Teaser step blocks | Full `serializeStepHtml` in `htmlByStep['teaser']` |

| Consumer | Chip renderCss | Runtime host CSS | Serialized teaser HTML | Complete? |
|----------|----------------|------------------|----------------------|-----------|
| Save | Yes (style-only) | In teaser HTML / export CSS | `htmlByStep.teaser` | **Yes** |
| Storefront | Yes | `ensureTeaserExportStyles` + hoist | From `htmlByStep` | **Yes** |
| FormPreview (serialized flow) | Yes | Embed runtime | Injected `resolvePreviewTeaserHtml` | **Partial** |
| FormPreview (`useCanvasTeaser`) | renderCss mounted but chip is **React `TeaserPreview`** | Overlay | **Skipped** | **PARTIAL** |
| Editor teaser canvas | React `TeaserPreview` | N/A | N/A | Valid difference |

**Preview block-teaser scope bug (stale-risk):** `resolvePreviewTeaserHtml` uses `artifactScopeId: 'pbn-teaser-preview'` while main preview export uses `'pbn-preview'` (`serializedTeaserPreviewMount.ts` vs `previewArtifactExport.ts`).

**Teaser parity: PARTIAL**

---

## 12. Part J — Native-control sheet parity

Current `*.export.css` sheets (via `serializeLayout` / step HTML):

| Sheet | Marker attr | Merchant vars |
|-------|-------------|---------------|
| `floating-label.export.css` | `data-adflipr-popup-floating-label-css` | `--pbn-*` from renderCss (standard field) |
| `radio.export.css` | `data-adflipr-popup-radio-css` | `--pbn-radio-*` from renderCss |
| `embed-responsive.export.css` | `data-adflipr-popup-responsive-css` | structural |
| `popup-entrance.export.css` | entrance animation | structural |

**G1 status:** Production webpack contract gate enforced in `build-production.sh` (`popup-export-css-g1-closure-and-contamination-audit.md`). **New saves** embed real CSS strings.

| Question | Answer |
|----------|--------|
| Merchant vars without consuming sheet? | **YES** on hostile hosts without hoist — sheet provides mechanics |
| Sheet without merchant vars? | **YES visually degraded** — e.g. radio outline defaults without `--pbn-radio-outline` |
| Pre-G1 saved popups | **Stub sheet text** — contamination, not missing pairing |

**Checkbox:** Not migrated — no `checkbox.export.css` yet (H3 audit).

**Native-control sheet parity: PASS** for current saves; **FAIL** for pre-G1 contaminated persisted HTML until re-save.

---

## 13. Part K — CSS mount order

| Consumer | Effective order (first → last) |
|----------|--------------------------------|
| **Storefront embed** | Host theme → app CSS → **artifact renderCss** (head) → hoisted step sheets (floating-label, radio, responsive, custom, device) → step HTML |
| **FormPreview embed** | Same pattern inside `presentationRoot` (`mountFormPreviewEmbedPresentation` → runtime) |
| **Thumbnail iframe** | iframe reset CSS → **renderCss** (head) → body HTML (includes embedded `<style>` blocks) |
| **Editor canvas** | App CSS → React inline styles (no artifact renderCss) |

### Harmful order differences

| Difference | Harmful? |
|------------|----------|
| Custom CSS in step HTML hoisted after renderCss | **No** — intentional override capability |
| Thumbnail: renderCss before body HTML | **No** |
| Editor canvas lacks renderCss | **Expected** — not artifact preview |

**Harmful cascade-order differences: None proven** for artifact consumers.

---

## 14. Part L — Style lifecycle

| Style source | Created by | Identity key | Updated by | Removed by |
|--------------|------------|--------------|------------|------------|
| Artifact renderCss | `mountRenderArtifactCss` | `data-adflipr-popup-style-owner` = runtime instance UUID | Replace on config/css change | `clearRenderArtifactCss` / `destroy` |
| Hoisted step sheets | `hoistExportedStepStyles` | `data-adflipr-popup-runtime-style` = sheet key | Re-hoist on step change (dedupe by key) | Step `disposeStepBlockInits` teardown |
| Teaser export CSS | `ensureTeaserExportStyles` | document-level teaser sheet | Once per document | Runtime destroy (implicit) |
| Custom CSS | In HTML → hoist | `data-adflipr-popup-custom-css` | Re-serialize on save | Step teardown |

| Risk | Present? |
|------|----------|
| Orphan artifact nodes | **Low** — owner-scoped cleanup tested (`renderArtifactCssLifecycle.test.ts`) |
| Stale artifact CSS after edit | **Preview: no** (recompile); **Storefront: yes until re-save** |
| Cross-popup teardown collision | **No** — owner is runtime instance id, not `popupId` |
| Duplicate mounts | **Guarded** for identical CSS string |

---

## 15. Part M — Artifact scope identity

| Consumer | HTML `data-adflipr-popup-artifact` | renderCss selectors | Match? |
|----------|-----------------------------------|---------------------|--------|
| Save / storefront / thumbnail | `popupId` | `[data-adflipr-popup-artifact="<popupId>"]` | **YES** |
| Builder preview | `pbn-preview` | same scope in CSS | **YES** (internal) |
| Preview block-teaser HTML | `pbn-teaser-preview` | main CSS uses `pbn-preview` | **NO** — stale-risk |
| Preview style-only teaser (serialized) | `pbn-teaser-preview` in `serializeTeaserHtml` inject | `pbn-preview` in renderCss | **NO** for chip rules |

**Who creates scope:** `serializePopupExport` / `serializeStepHtml` / family compilers.

**Regenerated:** Every compile pass (preview recompiles each mount).

**Same popup twice:** Safe — separate runtime instance ids (`renderArtifactCssLifecycle` Case G).

---

## 16. Part N — Saved vs unsaved preview semantics

| Consumer | Freshness model |
|----------|-----------------|
| Editor canvas | Live state |
| FormPreview modal | Live state |
| Preview tab with `previewDraft=1` | `sessionStorage` snapshot (unsaved) |
| Preview tab without draft | Saved `builderState` from API |
| Save | Live state → persist |
| Thumbnail / embed | Last saved artifact |
| Popup Detail preview button | Saved (no draft write) |

### Mixed-version artifacts?

**YES** — bounded cases:

| Path | Mixed? |
|------|--------|
| Preview draft snapshot + old `htmlContent` in DB | **No** — preview does not read DB HTML |
| Thumbnail `htmlContent` from save N + stale `renderCss` if save partial failure | **Theoretically** — metadata merge deletes empty renderCss; unlikely |
| Legacy popup: `htmlContent` without `renderCss` | **YES** — HTML-only fallback |
| User views thumbnail (saved) while editing unsaved in editor | **Expected** — different surfaces |

---

## 17. Part O — Pre-G1 contamination

Per [`popup-export-css-g1-closure-and-contamination-audit.md`](./popup-export-css-g1-closure-and-contamination-audit.md):

| Question | Answer |
|----------|--------|
| Load detects contamination? | **No** automatic sanitizer |
| Preview renders contaminated HTML? | **Yes** if loaded from saved `htmlContent` / `htmlByStep` |
| Edit-and-save regenerates clean artifact? | **Yes** when `builderState` exists |
| Published popup remains contaminated indefinitely? | **Yes** until re-save on G1+ frontend |
| Automatic recompilation? | **No** |

**Recommended operational action: Operational re-save only** (+ optional read-only contamination report via `service/scripts/audit-popup-export-css-contamination.js`).

---

## 18. Part P — API optionality

| Field | Type optional? | Why | Runtime fallback | Classification |
|-------|----------------|-----|------------------|----------------|
| `renderCss` | Yes (`PopupMetadata`, embed response) | Pre-migration records | `''` in `normalizeEmbedConfig` | LEGACY COMPATIBILITY |
| `htmlByStep` | Yes (metadata string) | Pre-migration | Synthesize from `htmlContent` | LEGACY COMPATIBILITY |
| `htmlContent` | Yes | Empty popup | No thumbnail | VALID |
| `artifactScopeId` | Not a separate API field | Encoded in HTML/CSS | N/A | — |
| `PopupEmbedConfig.renderCss` | Required in TS type; normalized to `''` | Parsing guard | Empty string | VALID PARSING GUARD |

**Unsafe fallbacks:** None that silently recompile — embed does not regenerate `renderCss` client-side.

---

## 19. Part Q — Consumer fallback inventory

| Fallback | Consumer | Trigger | Result | Classification |
|----------|----------|---------|--------|----------------|
| `htmlByStep` from `htmlContent` | `PopupEmbedResponse` | Empty map | Single-step embed | LEGACY COMPATIBILITY |
| `htmlContent` fallback in export | `serializePopupExport` | Primary missing from map | Second serialize same snapshot | VALID PARSING GUARD |
| `normalizeRenderCssValue` → `''` | `normalizeEmbedConfig` | Missing CSS | No artifact stylesheet | LEGACY COMPATIBILITY |
| `normalizeCompiledPreviewArtifact` | Thumbnail | Missing renderCss | HTML-only thumb | PARITY RISK (legacy) |
| `PREVIEW_SPIN_OUTCOME_POPUP_ID` default scope | `buildPreviewArtifactExport` | No scope passed | Preview pairing | VALID RUNTIME DIFFERENCE |
| `useCanvasTeaser` | FormPreview | Style-only teaser | React chip vs HTML | PARITY RISK |

---

## 20. Part R — Representative parity test plan (future)

### Artifacts

Simple migrated block, Text+RTE, standard input, phone, radio, multi-step, gamification, teaser, one unmigrated family (e.g. timer).

### Consumers

Save payload, FormPreview, fullscreen tab, detail thumbnail, list thumbnail, storefront mount.

### Assertions per pair

`artifactScopeId` in HTML matches CSS selectors; embedded sheet markers present; computed desktop/mobile styles on known selectors.

### Test types

| Layer | Scope |
|-------|--------|
| Unit | `buildPopupSaveMetadata` pairing; `normalizeEmbedConfig` |
| Integration | `serializePopupExport` → mock API → embed normalize |
| Chromium | Thumbnail iframe + `mountFormPreviewEmbedPresentation` + embed runtime |
| Production contract | G1 export-css gate (existing) |

---

## 21. Part S — Parity scorecard

| Consumer | Canonical HTML | Canonical renderCss | Scope match | Embedded CSS | Lifecycle | Status |
|----------|----------------|---------------------|-------------|--------------|-----------|--------|
| Save persistence | Yes | Yes | Yes | In HTML | N/A | FULL PARITY |
| Storefront embed | Yes* | Yes* | Yes* | Hoisted | Correct | FULL PARITY* |
| FormPreview / fullscreen | Recompiled | Recompiled | Internal match | Hoisted | Correct | PARTIAL |
| Editor FormCanvas | No | No | N/A | No | N/A | VALID RUNTIME DIFFERENCE |
| Detail thumbnail | Primary | Yes* | Yes* | In string | iframe | PARTIAL |
| List thumbnail | Primary | Yes* | Yes* | In string | iframe | PARTIAL |
| Preview tab (draft) | Recompiled draft | Recompiled | pbn-preview | Hoisted | Correct | PARTIAL |
| Legacy / pre-G1 records | Yes | Often missing / stub | Yes | Contaminated | N/A | STALE-RISK |

\*When last saved on current compiler + G1 frontend.

### Totals

| Status | Count |
|--------|-------|
| Consumers audited | **8** |
| Full parity | **2** (save, storefront — current saves) |
| Valid runtime difference | **1** (editor canvas) |
| Partial | **4** |
| Stale-risk | **1** (legacy/pre-G1) |
| Broken | **0** |
| Unknown | **0** |

---

## 22. Part T — Risk prioritization

| Priority | Issue | Consumer | Evidence | Recommended action |
|----------|-------|----------|----------|-------------------|
| **P1** | Legacy records without `renderCss` | Thumbnail, embed | Optional metadata; normalize to `''` | Document; optional UI “republish” prompt |
| **P1** | Pre-G1 stub contamination in saved HTML | Storefront, thumbnail | G1 contamination audit | Operator re-save campaign |
| **P1** | Preview block-teaser scope `pbn-teaser-preview` vs `pbn-preview` | FormPreview | `serializedTeaserPreviewMount.ts` | G2E-fix: unify preview scope |
| **P2** | Style-only teaser uses React overlay in preview | FormPreview | `useCanvasTeaser` + `TeaserPreview.tsx` | Optional: always use serialized teaser in preview |
| **P2** | Thumbnail shows primary HTML only | List/detail | `PopupListRowResponse.thumbnailHtml = htmlContent` | Document; optional multi-step thumb |
| **P2** | Preview scope constant ≠ saved `popupId` | Preview | `pbn-preview` vs `popupId` | Document as valid (paired per compile) |
| **P3** | Dead `SerializedFormPreviewCanvas` / `serializedFormPreviewMount` | — | No importers | **Done** — deleted; preview is embed-runtime only |
| **G2D ref** | Unmigrated inline merchant CSS in step HTML | All consumers | Phase 6A/H3 audits | Continue family migration |

**P0 issues: NONE** (no proven HTML/renderCss scope mismatch on save or storefront for current saves).

---

## 23. Part U — G2D / G2E boundary

| Finding | Owner |
|---------|-------|
| Inline merchant CSS in unmigrated blocks | **G2D** |
| RTE/trait duplicate storage | **G2D** |
| `buildBlockTraitResponsiveCss` debt | **G2D** |
| Consumer drops / order / stale artifact | **G2E** (this doc) |
| Preview teaser scope mismatch | **G2E** |
| Pre-G1 persisted contamination | **G2E** (operational) + G1 (gate closed) |

---

## 24. Part V — Strategy decision

**Recommended strategy: B — Continue block migration + narrow consumer parity cleanup**

Rationale:

- Canonical `serializePopupExport` contract is **consumed correctly** by save and embed.
- No P0 scope mismatch on production save path.
- Remaining gaps are **preview teaser scope**, **legacy records**, and **thumbnail primary-step** — fixable without redesign.
- Do **not** pause all migration (C/D) for cosmetic preview differences.

---

## 25. Exact recommended next actions

1. **G2E-narrow:** Unify preview teaser `artifactScopeId` with main preview export (`pbn-preview` or `popupId` when available).
2. **Operational:** Run contamination audit script; re-save affected published popups after G1 deploy.
3. **Documentation:** Mark editor canvas as non-artifact surface in preview KB.
4. **Future tests:** Chromium parity matrix (Part R) before declaring embed parity closed.
5. **Continue:** Block-family migration (G2D/H3) — not blocked by consumer architecture.

---

## 26. Recommended next phase

**Phase G2E-narrow implementation** (preview teaser scope + optional serialized teaser parity) **in parallel with** next block-family migration (e.g. Checkbox per H3), **not** a full artifact redesign.

---

## 27. Required final answers (64-point checklist)

1. Audit-only phase respected: **YES**
2. Production code changed: **NO**
3. Tests changed: **NO**
4. Canonical save compiler: **`serializePopupExport`**
5. Save compiles one coherent artifact: **YES**
6. artifactScopeId persisted: **In HTML + CSS selectors (`popupId`)**
7. htmlContent persisted: **YES**
8. htmlByStep persisted: **YES** (`metadata.htmlByStep`)
9. primaryStepId persisted: **YES** (`metadata.htmlPrimaryStepId`)
10. renderCss persisted: **YES** when non-empty (`metadata.renderCss`)
11. Independent popup compilation paths: **3** (canonical export, teaser `fromParts`, editor React)
12. Legacy HTML-only compilation paths: **2** (embed htmlContent fallback, thumbnail primary HTML)
13. Builder Preview parity: **PARTIAL**
14. Fullscreen Preview parity: **PARTIAL**
15. Popup detail thumbnail parity: **PARTIAL**
16. Campaign/list thumbnail parity: **PARTIAL**
17. Storefront parity: **FULL** (current saves)
18. Multi-step parity: **PASS**
19. Later step can miss required CSS: **NO**
20. Gamification parity: **PARTIAL**
21. Teaser parity: **PARTIAL**
22. Native-control sheet parity: **PASS** (current saves) / **FAIL** (pre-G1 contaminated records)
23. Consumer can receive merchant variables without consuming sheet: **YES** (degraded mechanics)
24. Consumer can receive sheet without merchant values: **YES** (degraded appearance)
25. CSS mount-order differences found: **YES** (editor vs embed — valid)
26. Harmful cascade-order differences: **NO**
27. Stale renderCss risk: **YES** (legacy until re-save)
28. Duplicate style-mount risk: **LOW** (guarded)
29. Orphan style-node risk: **LOW**
30. Cross-popup teardown collision risk: **NO**
31. artifactScopeId mismatches (save/storefront): **NONE**
32. Same popup can mount twice safely: **YES**
33. Mixed-version HTML/renderCss possible: **YES**
34. Mixed-version paths: Legacy missing `renderCss`; pre-G1 contamination; preview block-teaser scope
35. Pre-G1 contaminated records detected at load: **NO** (no auto-detect)
36. Edit-and-save regenerates clean artifact: **YES**
37. Published contaminated popup can remain stale: **YES**
38. Recommended pre-G1 operational action: **Operational re-save only**
39. Optional compiled-artifact fields found: **renderCss, htmlByStep, htmlPrimaryStepId**
40. Hidden HTML-only fallbacks: **Embed htmlContent → htmlByStep synthesis**
41. Legacy compatibility fallbacks: **Empty renderCss → `''`**
42. Unsafe consumer fallbacks: **NONE**
43. Consumers audited: **8**
44. Full-parity consumers: **2**
45. Valid-runtime-difference consumers: **1**
46. Partial consumers: **4**
47. Stale-risk consumers: **1**
48. Broken consumers: **0**
49. Unknown consumers: **0**
50. P0 issues: **0**
51. P1 issues: **3** (legacy renderCss, pre-G1 contamination, preview teaser scope)
52. P2 issues: **3**
53. P3 issues: **1**
54. Highest-risk consumer issue: Preview block-teaser `artifactScopeId` split (`pbn-teaser-preview` vs `pbn-preview`)
55. G2D-owned findings encountered: Unmigrated inline merchant CSS; RTE/trait overlap (reference only)
56. G2E-owned findings: Preview teaser scope, legacy/stale artifacts, thumbnail primary-step, pre-G1 contamination ops
57. Recommended strategy: **B**
58. Required fix before more family migrations: **NO** (full pause); **YES** for narrow G2E + operational re-save
59. Required narrow parallel cleanup: Preview teaser scope unification; contamination re-save campaign
60. Required future parity tests: Part R matrix (Chromium + save→embed round-trip)
61. Architecture artifact contract consumed consistently: **PARTIAL**
62. Exact recommended next actions: See §25
63. Recommended next phase: **G2E-narrow** + continue block migration
64. Verdict: **ACCEPT WITH ACTIONS**
