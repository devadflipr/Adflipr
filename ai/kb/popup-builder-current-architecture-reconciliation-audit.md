# Popup Builder — Current-Architecture Reconciliation Audit

**Phase:** Current-State Reconciliation & Next-Phase Decision  
**Mode:** READ-ONLY  
**Audience:** Engineering (Sam / principal)  
**Date:** 2026-07-19  
**Prior audit reconciled:** `docs/POPUP_BUILDER_END_TO_END_ARCHITECTURE_REVIEW.md` (2026-07-19)  
**Authority:** Executable code at HEAD — not historical KB phase reports

---

## 1. Executive verdict

**Newly saved popups today use a closed merchant-presentation architecture:**

```text
PopupBuilderSnapshot
  → serializePopupExport
  → CompiledPopupArtifact { htmlByStep, htmlContent, primaryStepId, renderCss, artifactScopeId }
  → PUT /api/v1/popups/{id} metadata + htmlContent
  → GET /embed/{id} (if PUBLISHED)
  → mount renderCss + hoist structural/behavior <style> + innerHTML
```

| Question | Verdict |
|----------|---------|
| Merchant presentation SoT for **new** saves | **SINGLE** — artifact-scoped `renderCss` via `compilePopupRenderCss` |
| `buildBlockTraitResponsiveCss` | **DELETED** — not in production sources; guardrail forbids reintroduction |
| Unmigrated block families | **NONE** |
| Dual CSS (`renderCss` + embedded `<style>`) | **NOT** incomplete merchant migration — structural/behavior sheets + device visibility + freeform `customCss` |
| Single responsive DOM | **SINGLE DOM** + `@media` (no `htmlByStepMobile`) |
| Highest-value next phase | **A — Draft vs Published Artifact lifecycle separation** |

**Previous end-to-end audit conclusions about “migration incomplete / presentation SoT split / unmigrated blocks / buildBlockTraitResponsiveCss active” are STALE relative to HEAD.**

---

## 2. Repository state

```text
Repository state audited:
  branch:       dev-main
  HEAD:         7affddb62f3fcec49e49455e1b430851a9ef3dcd
  working-tree: clean (tracking origin/dev-main; no local modifications observed)
```

**Relevant recent commits (popup paths):** embed form type, frequency/teaser, UI fixes, timer, color picker/actions — not a reverse of CSS migration.

**Documentation used only as supporting context:** Phase 8A closure, preview P1 closure, steady-state tests. Conflicts deferred to code.

---

## 3. End-to-end current architecture trace

| Stage | File/function | Input | Output | Owner |
|-------|---------------|-------|--------|-------|
| Builder edit | `FormBuilder.tsx` + block `*Canvas.tsx` | Merchant interactions | React state (`formElements`, `formStyle`, steps) + history | Builder UI |
| Snapshot | `buildCurrentSnapshot` / `PopupBuilderSnapshot` | React state | Normalized snapshot (`schemaVersion: 1`) | Builder state |
| Save serialize | `FormBuilder.handleSave` → `serializePopupExport` | Snapshot + `artifactScopeId=popupId` | `CompiledPopupArtifact` | Serializer |
| Presentation compile | `compilePopupRenderCss` | Snapshot + scope | Scoped CSS string | Presentation compilers |
| HTML per step | `serializePopupHtmlByStep` → `serializeStepHtml` | Snapshot | `htmlByStep` (+ structural `<style>` embeds) | Serializer |
| Backend payload | `buildPopupSaveMetadata` + `popupApi.updatePopup` | Artifact + targeting | `htmlContent` + metadata map | FE API mapping |
| Persistence | `PopupService.updatePopup` | `UpdatePopupRequest` | Mongo `popup` doc (single write) | Backend |
| Public embed API | `PopupService.getPopupEmbedConfig` → `PopupEmbedResponse.fromEntity` | popupId | `htmlByStep`, `renderCss`, layout fields, `version` | Backend (PUBLISHED only) |
| Embed config | `embed/api.ts` `fetchPopupEmbedConfig` | popupId | `PopupEmbedConfig` | Embed client |
| CSS mount | `runtime.ensureRenderArtifactCss` → `mountRenderArtifactCss` | `renderCss` | `<style>` in `document.head` | Embed runtime |
| Step mount | `runtime.renderStepInto` | step HTML | `innerHTML` + `hoistExportedStepStyles` | Embed runtime |
| Behavior | `embed/blockInitializers/*`, form submit, triggers | DOM attrs | Interactions / telemetry | Embed runtime |

**Path categories (do not mix):**

| Path | Role |
|------|------|
| **CURRENT GENERATION** | Snapshot → `serializePopupExport` → metadata/`htmlContent` |
| **CURRENT RUNTIME** | Candidates → config → `renderCss` mount + hoist + `innerHTML` |
| **LEGACY COMPATIBILITY** | Empty/`null` `renderCss`; older embedded merchant CSS in saved HTML; `htmlContent` fallback when `htmlByStep` empty |
| **DEAD / OBSOLETE** | `buildBlockTraitResponsiveCss`, `MIGRATED_BLOCK_TRAIT_TYPES`, `migratedWrapperLayoutOwnership`, `htmlByStepMobile` |
| **FUTURE / PLANNED** | Stored `contractVersion` / `serializerBuild`; draft vs published artifact split (not implemented) |

---

## 4. Current artifact contract

```text
Current compiled artifact contract:
```

| Field | Classification | Evidence |
|-------|----------------|----------|
| `artifactScopeId` | **CURRENT** | `CompiledPopupArtifact`; required by `serializePopupExport` |
| `htmlContent` | **CURRENT** | Primary step HTML; also top-level Popup field |
| `htmlByStep` | **CURRENT** | `Record<stepId, string>` |
| `primaryStepId` | **CURRENT** | Export + metadata `htmlPrimaryStepId` |
| `renderCss` | **CURRENT** | Artifact-scoped presentation CSS |
| `contractVersion` | **ABSENT** | Not on artifact or metadata keys |
| `serializerBuild` | **ABSENT** | Not on artifact or metadata keys |
| `artifactVersion` | **ABSENT** as named field | Closest: Mongo `Popup.version` (content bump) |
| `schemaVersion` | **CURRENT** (snapshot / metadata) | `POPUP_BUILDER_SCHEMA_VERSION = 1` |
| HTML `data-adflipr-popup-responsive="v1"` | **CURRENT** | Responsive export marker on `<form>` |

---

## 5. Responsive DOM model

Searches for `htmlByStepMobile`, `mobileHtml`, dual desktop/mobile HTML maps: **no production dual-DOM generation**. Test locals named `desktopHtml` are single-step HTML variable names only (**TEST ONLY**).

```text
Current responsive DOM model:
SINGLE DOM
```

One HTML tree per step; mobile via `@media (max-width: 480px)` in `renderCss` (+ device-hide attrs / visibility CSS). Viewport change after load: CSS reflows — no HTML swap.

---

## 6. Block-family presentation inventory

**Proof method:** compiler wired in `compilePopupRenderCss.ts` + steady-state / family tests asserting no merchant wrapper inline + `buildEmbeddedStepDeviceCss` emits only visibility/phone-select (not block trait presentation).

| Family | Serializer merchant inline CSS | renderCss compiler | Generic responsive merchant CSS | Structural export sheet | Status |
|--------|--------------------------------|--------------------|---------------------------------|-------------------------|--------|
| form-surface | No (structural form attrs only) | `compileFormSurfaceRenderCss` | No | embed-responsive (structure) | **MIGRATED** |
| outer-layout | No | `compileOuterLayoutRenderCss` | No | — | **MIGRATED** |
| columns-container | No | `compileColumnsContainerRenderCss` | No | — | **MIGRATED** |
| column | No | `compileColumnPresentationRenderCss` | No | — | **MIGRATED** |
| button | RTE `<a>` color/underline only | `compileButtonPresentationRenderCss` | No | — (+ rich-text link `<style>`) | **MIGRATED** (RTE exception) |
| text | No | `compileTextPresentationRenderCss` | No | — | **MIGRATED** |
| text-input / email-input / date | No | `compileStandardFieldPresentationRenderCss` | No | floating-label | **MIGRATED** |
| textarea | No | `compileTextareaPresentationRenderCss` | No | floating-label | **MIGRATED** |
| dropdown | No | `compileDropdownPresentationRenderCss` | No | — | **MIGRATED** |
| phone-input | No | `compilePhoneInputPresentationRenderCss` | No | embed-responsive phone select width | **MIGRATED** |
| radio | No | `compileRadioPresentationRenderCss` | No | radio.export.css | **MIGRATED** |
| check-box | No | `compileCheckboxPresentationRenderCss` | No | checkbox.export.css | **MIGRATED** |
| divider | No | `compileDividerPresentationRenderCss` | No | — | **MIGRATED** |
| coupon | No | `compileCouponPresentationRenderCss` | No | coupon.export.css | **MIGRATED** |
| image | No | `compileImagePresentationRenderCss` | No | image.export.css | **MIGRATED** |
| video | No | `compileVideoPresentationRenderCss` | No | video.export.css | **MIGRATED** |
| social | No | `compileSocialPresentationRenderCss` | No | social.export.css | **MIGRATED** |
| feedback | No | `compileFeedbackPresentationRenderCss` | No | feedback.export.css | **MIGRATED** |
| timer | No | `compileTimerPresentationRenderCss` | No | timer.export.css | **MIGRATED** |
| spinner | No (SVG pin geometry structural) | `compileSpinnerPresentationRenderCss` | No | spinner.export.css | **MIGRATED** |
| product | No | `compileProductPresentationRenderCss` | No | product.export.css | **MIGRATED** |
| cross / floating-image | No (`pointer-events` behavior) | `compilePositionedDecorativeRenderCss` | No | — | **MIGRATED** |
| teaser | Structural/behavior chip inline | `compileTeaserPresentationRenderCss` | Hide-only device CSS | — | **MIGRATED** |
| gamification | No | `compileGamificationRenderCss` | No | — | **MIGRATED** |
| block customCss | No (declarations → renderCss) | `compileBlockCustomCssRenderCss` | No | — | **MIGRATED** |
| form-level `formStyle.customCss` | N/A (stylesheet embed) | Not in renderCss | N/A | embedded `data-adflipr-popup-custom-css` | **CURRENT freeform** (not block-trait debt) |

```text
Fully migrated families:
  form-surface, outer-layout, columns-container, column, button, text,
  text-input, email-input, date, textarea, dropdown, phone-input, radio,
  check-box, divider, coupon, image, video, social, feedback, timer,
  spinner, product, cross, floating-image, teaser, gamification

Partially migrated families:
  NONE (button RTE hostile-theme link styling is an intentional residual exception, not a partial family)

Unmigrated families:
  NONE
```

---

## 7. `buildBlockTraitResponsiveCss` status

| Occurrence class | Status |
|------------------|--------|
| Production TS definition | **DEAD** — `rg` finds **NO_DEF** under `adflipr-app/src` |
| `MIGRATED_BLOCK_TRAIT_TYPES` / `migratedWrapperLayoutOwnership` | **DEAD** — deleted; guardrail test forbids strings in production sources |
| `buildEmbeddedStepResponsiveCss` | **CURRENT** deprecated alias → `buildEmbeddedStepDeviceCss` (visibility + phone select only) |
| KB / phase reports mentioning active consumers | **DOCUMENTATION ONLY** (historical) |
| Exclusion tests | Replaced by steady-state / embedded-device steady-state tests |

```text
Does current newly saved popup generation depend on buildBlockTraitResponsiveCss?
NO

previous audit statement that generic responsive merchant CSS remains active:
STALE
```

---

## 8. Inline style ownership inventory

| Location | Classification |
|----------|----------------|
| `exportBlockWrapperLayout.ts` → wrapper `flex-shrink:0` | **STRUCTURAL** |
| `serializeLayout.ts` form `width/margin/boxSizing` (+ flyout height) | **STRUCTURAL** |
| `button/serialize.ts` → `applyRichTextLinkStyles` on `<a>` | **MERCHANT PRESENTATION** (hostile-theme defense; also mirrored in embedded rich-text CSS) |
| `floating-image/serialize.ts` `pointer-events:auto` | **BEHAVIOR** |
| `serializeTeaser.ts` chip dimensions / nowrap / pointer-events | **STRUCTURAL / BEHAVIOR** |
| Spinner SVG pin `style=` geometry | **DATA-DEPENDENT GEOMETRY** |
| Runtime timer/spinner/coupon temporary styles | **RUNTIME MECHANIC** (not saved merchant paint) |

```text
Current merchant presentation inline CSS remaining:
  button RTE <a> color + text-decoration via applyRichTextLinkStyles
  (+ paired form[data-adflipr-popup-export] … button a { … !important } style block)
```

Structural/runtime inlines are **not** merchant CSS debt.

---

## 9. `renderCss` SoT verdict

`compilePopupRenderCss` owns all known merchant trait presentation domains listed in §6.

**Independent current-generation systems compiling the same merchant traits?**  
- Generic `buildBlockTraitResponsiveCss`: **no** (deleted).  
- Embedded `*.export.css`: structural/behavior only (see §10).  
- Form-level `customCss`: freeform merchant CSS in step `<style>` — **orthogonal** to trait compilers (not a second trait SoT).

```text
Merchant presentation source of truth:
SINGLE
```

(Trait presentation = `renderCss`. Freeform form `customCss` is a separate authoring channel.)

---

## 10. `.export.css` ownership

| Sheet | Consumer | Role | Merchant presentation? | Current/legacy |
|-------|----------|------|------------------------|----------------|
| floating-label.export.css | serializeLayout | Label float mechanics | No | CURRENT |
| embed-responsive.export.css | serializeLayout | Overflow / max-width / surface border reset | No | CURRENT |
| popup-entrance.export.css | serializeLayout | Entrance animation keyframes | No | CURRENT |
| radio / checkbox / coupon / image / video / social / feedback / timer / spinner / product `.export.css` | serializeLayout | Fixed structure / control chrome | No (coupon copy-btn has fixed chrome colors, not trait-driven) | CURRENT |

G1 raw-source contract: enforced via CRA/`applyExportCssRawSourceContract` + `test:export-css-production-contract` / guardrails (not re-run in this audit; prior closure + wiring still present).

```text
Do current export sheets contain merchant-configurable presentation?
NO
```

---

## 11. Dual CSS delivery explanation

**For newly generated artifacts:**

| Bucket | What | Why |
|--------|------|-----|
| `renderCss` | All trait-driven merchant paint + mobile diffs + block customCss | Presentation SoT |
| Embedded step `<style>` | `*.export.css` sheets, entrance, device visibility, phone select width, rich-text link shield, form `customCss` | Structure/behavior/host safety/freeform |
| `hoistExportedStepStyles` | Moves those `<style>` nodes to `document.head` | Browsers often ignore styles left inside `innerHTML` subtrees |

Classification of hoist consumers:

| Consumer | Class |
|----------|-------|
| `*.export.css` markers | **CURRENT STRUCTURAL / BEHAVIOR** |
| Device visibility / phone select | **CURRENT BEHAVIOR** |
| Form `customCss` | **CURRENT** freeform merchant (not migration leftover) |
| Rich-text link CSS | **CURRENT** hostile-theme merchant exception |
| Pre-migration merchant trait CSS in old DB rows | **LEGACY COMPATIBILITY** (runtime still hoists whatever is in saved HTML) |

```text
Is dual CSS delivery evidence of incomplete merchant-presentation migration?
NO
```

`renderCss` + structural sheets is intentional steady-state layering, not a split merchant SoT.

---

## 12. Legacy compatibility inventory

```text
Legacy compatibility surface:
```

| Legacy mechanism | Why retained | Current saves produce it? | Runtime still consumes it? | Safe to remove now? |
|------------------|--------------|---------------------------|----------------------------|---------------------|
| Artifacts with empty/missing `renderCss` | Pre-migration / early saves | No (non-empty when presentation exists; key deleted if empty) | Yes — mount empty string OK; HTML may carry old styles | No — need old popups |
| Merchant presentation inside old embedded `<style>` | Historical generation | No | Yes via hoist | No until forced republish |
| `htmlContent` alone when `htmlByStep` empty | `PopupEmbedResponse` fallback | Rare (saves write both) | Yes | Keep |
| `buildEmbeddedStepResponsiveCss` name | Alias | Calls device CSS | Yes | Rename-only OK |
| `data-adflipr-popup-responsive=v1` | Marks single-DOM contract | Yes | Soft | Keep |

**Do not confuse** current structural embeds with legacy merchant embeds.

```text
Legacy compatibility CSS/runtime path exists: YES
Current saves generate legacy merchant CSS path: NO
```

---

## 13. Preview architecture status

Trace: `FormPreview` → `mountFormPreviewEmbedPresentation` → `buildPreviewEmbedConfig` → `buildPreviewArtifactExport` / `serializePopupExport` → `transformCssForPreviewDevice` + `transformExportHtmlForPreviewDevice` → `createPopupEmbedRuntime` (`previewMode`).

Confirmed from code:

- Preview builds a **local** artifact (`PREVIEW_SPIN_OUTCOME_POPUP_ID` scope) — does not write Mongo.
- Transform applies to preview config CSS/HTML only — **not** live storefront.
- Selected preview device is authoritative via transform (P1 closure still matches code).

```text
Preview architecture status:
  CLOSED (steady-state: transform-with-controlled-contract)
```

---

## 14. Canvas / export resolver parity

Representative check:

| Family | Classification |
|--------|----------------|
| Image / Video / Social / Coupon | **SHARED CANONICAL RESOLVER** (canvas imports presentation resolvers) |
| Product / Spinner | **SHARED** merchant presentation modules |
| Button | Export presentation in renderCss; canvas may use ElementStyles — residual chrome differences possible |
| Text / inputs | Largely shared presentation compilers; canvas still React-native for editing chrome |

```text
Known Canvas/Export semantic duplication remaining:
  Low residual risk — editing chrome / RTE editing UI may differ from export paint;
  no proven systematic duplicated merchant trait compilers for migrated families.
  Intentional: preview device transform (preview-only).
```

---

## 15. Runtime ownership

| Concern | Serializer | Runtime | Presentation |
|---------|------------|---------|--------------|
| Form submit | attrs / field names | validate, POST events, step/url | renderCss |
| Button / nav | action attrs | click handlers | renderCss |
| Coupon | markup + copy hooks | clipboard / display toggles | renderCss + structural sheet |
| Timer | markup | fit font-size/gap (**mechanic**) | renderCss |
| Spinner | markup + config JSON | spin API, result display, pointerEvents | renderCss |
| Video | iframe/src sanitized | init | renderCss + sheet |
| Feedback | markup | init | renderCss |
| Product | markup | init | renderCss |
| Teaser | HTML + delays attrs | teaser host / delays | renderCss |
| Multi-step | `htmlByStep` | `renderStepInto` swap | per-step HTML |

```text
Does runtime mutate merchant presentation unexpectedly?
NO
```

Mutations observed (`timerFit` font-size, spinner result `display`, coupon clipboard textarea positioning) are **necessary runtime mechanics**, not architecture violations of merchant paint ownership.

---

## 16. Registry architecture

| Registry | Role | Completeness signal |
|----------|------|---------------------|
| Builder blocks | Canvas / palette / panels | `builderBlockRegistry.exhaustiveness.test.ts` |
| Serializer | HTML per block | `blockRegistry.exhaustiveness.test.ts` |
| Presentation | Hardwired list in `compilePopupRenderCss` | Steady-state + family tests |
| Embed initializers | Behavior | `embed/__tests__/blockRegistry.test.ts` (present in tree) |
| Structural sheets | Imported constants in serializeLayout | Wired per marker |

```text
Registry inconsistencies:
  NONE proven for content-block coverage.
  Note: presentation compilers are a fixed composition function (not a pluggable registry) —
  drift risk is “forget to add compiler call,” mitigated by steady-state tests, not a missing exhaustiveness registry.
```

---

## 17. Persistence model

Stored on `Popup`:

| Field | Stored? |
|-------|---------|
| `builderState` | Yes (`metadata`) |
| `htmlContent` | Yes (column) |
| `htmlByStep` | Yes (`metadata` JSON string) |
| `renderCss` | Yes (`metadata`) |
| `status` | Yes (`DRAFT` / `PUBLISHED` / …) |
| Triggers / targeting | Yes (`metadata` targetSettings/rules + inside builderState) |
| `version` | Yes (monotonic content version) |
| `serializerBuild` / `contractVersion` | **No** |

```text
Does editing/saving a currently PUBLISHED popup mutate the artifact that storefront consumers will subsequently receive?
YES
```

Evidence: `FormBuilder.handleSave` writes `htmlContent` + full metadata including `htmlByStep`/`renderCss` **without** changing status; `getPopupEmbedConfig` reads live metadata when `status == PUBLISHED`. Cache-Control `max-age=60` may delay visibility ≤60s (**CONDITIONAL** only for CDN/browser cache timing, not for “whether mutation occurs”).

---

## 18. Save vs Publish semantics

| Action | Draft changes | Published artifact changes | Storefront changes immediately? |
|--------|---------------|----------------------------|---------------------------------|
| **Save** (editor) | Always writes builderState + compiled artifact | **Yes if already PUBLISHED** | After write (+ ≤60s cache) |
| **Publish** (detail/list status → live) | Status only (no recompile) | Status gate opens; uses **already saved** artifact | Yes for candidates/config of that id (+ cache) |
| **Unpublish** | Status → DRAFT | Config endpoint rejects non-PUBLISHED | Disappears (after cache) |
| **Republish** | Status → PUBLISHED | Same as publish | Yes |

```text
Are Draft and Published Artifact lifecycles explicitly separated?
NO
```

---

## 19. Public embed API contract

`PopupEmbedResponse` returns: `popupId`, `type`, `formPosition`, `positionOffset`, `fullHeight`, `version`, `htmlPrimaryStepId`, `htmlByStep`, `renderCss`.

**Does not return** full `builderState`.

**Does peek** `builderState` when resolving layout fallbacks:

- `formPosition` (banner/side message)
- `positionOffset`
- `fullHeight`

```text
Public runtime dependency on editor-internal builderState:
  CONDITIONAL FALLBACKS for layout fields only (not full snapshot exposure)
```

For the binary final answer expected by the checklist: treat as **YES** (dependency exists), with scope limited to layout extraction — not merchant HTML compile.

---

## 20. Artifact metadata status

| Field | Status | Operational gap if absent |
|-------|--------|---------------------------|
| `contractVersion` | **ABSENT** | Harder to branch runtime on contract breaks |
| `serializerBuild` | **ABSENT** | Harder to detect stale artifacts after serializer deploy |
| `artifactVersion` | **ABSENT** (use `Popup.version`) | Partial: content version exists, not serializer identity |
| `compiledAt` | **ABSENT** | Support timeline unclear |
| `publishedAt` | **ABSENT** | No publish timestamp distinct from `updated` |

---

## 21. Security boundaries

| Ingress | Client handling | Backend | Runtime sink |
|---------|-----------------|---------|--------------|
| HTML / RTE | `sanitizeRichTextHtml`, URL/media sanitize | Pass-through store | `innerHTML` |
| CSS traits | Compilers emit scoped CSS | Pass-through | `<style>.textContent` |
| `customCss` / block customCss | `sanitizeCustomCss` / declaration parse | Pass-through | style tag / renderCss |
| Navigation URLs | `sanitizeNavigationUrl` | Pass-through | `location.assign` (re-check at runtime not fully proven) |
| Media / iframe | `sanitizeMediaSrc` / embed URL helpers | Pass-through | `src` |

```text
Highest-risk unresolved security boundaries:
  1) Authenticated merchant HTML/CSS stored and served publicly with innerHTML mount — no server-side artifact validation/rejection
  2) Host-page CSS bleed (no Shadow DOM) — isolation, not XSS
  3) Runtime navigation sink may not re-apply scheme allow-list (MEDIUM)
```

Prefer **artifact validation/rejection on write** over silent re-sanitization that mutates compiled CSS unexpectedly.

---

## 22. Integration-test coverage

```text
Full golden-path integration test exists:
PARTIAL
```

Present: serializer unit/browser, embed unit, backend unit, limited Playwright block E2E, metadata key contract tests.  

**Missing:** Builder snapshot → HTTP save → Mongo → public embed GET → embed runtime → Chromium computed style in one automated chain.

---

## 23. Previous-audit reconciliation table

| Previous finding | Current classification | Evidence |
|------------------|------------------------|----------|
| migration incomplete | **STALE** | All families in `compilePopupRenderCss`; Phase 8A + steady-state |
| presentation SoT split | **STALE** (for merchant traits) | Single `renderCss`; structural sheets ≠ second trait SoT |
| dual CSS delivery is migration seam | **PARTIALLY STALE** | Dual delivery remains but is structural/behavior + freeform; not merchant-trait migration seam |
| unmigrated blocks remain | **STALE** | Unmigrated families: NONE |
| `buildBlockTraitResponsiveCss` still active | **STALE** | Deleted; guardrail test |
| inline merchant CSS remains | **PARTIALLY STALE** | Only intentional button RTE `<a>` exception |
| hoisted styles imply migration debt | **STALE** as migration debt | Hoist required for structural embeds + browser quirk |
| Draft vs Published lifecycle risk | **VALID CURRENT FINDING** | Save mutates live PUBLISHED artifact |
| missing contractVersion/serializerBuild | **VALID CURRENT FINDING** | Still ABSENT |
| builderState runtime coupling | **VALID CURRENT FINDING** (narrowed) | Layout fallbacks only |
| Shadow DOM deferment | **VALID CURRENT FINDING** | Still no isolation; defer without theme-break evidence |
| registry drift risk | **UNPROVEN / LOW** | Exhaustiveness tests; presentation composition risk residual |
| lack of full-chain integration test | **VALID CURRENT FINDING** | PARTIAL only |

---

## 24. Current architecture scorecard

| Area | Score /10 |
|------|-----------|
| Builder state architecture | 8 |
| Serialization/compiler architecture | 9 |
| CSS/presentation ownership | 9 |
| Responsive architecture | 9 |
| Preview architecture | 8 |
| Runtime architecture | 8 |
| Persistence/domain model | 7 |
| Draft/publish lifecycle | 4 |
| Security boundaries | 5 |
| Testability | 7 |
| Maintainability | 6 |
| Operational observability | 5 |

```text
Overall architecture score: 7.5 / 10
```

**Top 3 factors preventing a higher score:**
1. No draft vs published artifact separation (live overwrite risk).  
2. Weak operational metadata (`serializerBuild` / contract versioning).  
3. Security defense-in-depth + missing full Save→DB→Embed→Browser golden path.

---

## 25. Top architectural risks (current)

1. **P0** — Saving a PUBLISHED popup overwrites storefront artifact immediately (post-cache).  
2. **P1** — No `serializerBuild`/`contractVersion` for stale-artifact ops after deploys.  
3. **P1** — Public HTML/CSS trust boundary without server artifact validation.  
4. **P2** — `builderState` layout fallbacks on public path.  
5. **P2** — Builder CSS monolith / FormBuilder size (maintainability).

---

## 26. Recommended next-phase ranking

| Candidate | Impact | Risk | Dependency | Order |
|-----------|--------|------|------------|-------|
| **A. Draft vs Published Artifact separation** | Highest correctness | Medium product/eng | None | **1** |
| **B. contractVersion + serializerBuild** | High ops | Low | Independent of A | 2 |
| **C. Golden Save→DB→Embed→Browser** | High confidence | Medium | Helps lock A/B | 3 |
| **D. Security boundary hardening** | High security | Medium (must not silently mutate CSS) | Prefer validation | 4 |
| **E. Remove builderState from embed** | Medium cleanup | Low | Promote layout fields on save | 5 |
| **F. Registry completeness contract** | Low | Low | Optional | 6 |
| **G. Builder maintainability** | Medium DX | Medium | Parallel | 7 |
| **H. Remaining CSS cleanup** | Low | Low | Only RTE link stream | 8 |

```text
Recommended NEXT phase:
A. Draft vs Published Artifact lifecycle separation
```

---

## 27. Final recommendation

Treat the CSS/presentation migration as **architecturally closed** for newly saved popups. Do **not** open another block-family migration phase unless a specific family regression is proven.

**Next architecture investment:** introduce an explicit **published artifact snapshot** (or equivalent) so editor Save on a live popup cannot silently change storefront HTML/CSS until Publish. Follow with serializer/build metadata and a golden-path integration contract.

**Do not** implement that phase in this audit.

---

## Evidence appendix (high-signal)

| Claim | Evidence |
|-------|----------|
| `compilePopupRenderCss` wires all families | `compilePopupRenderCss.ts` |
| `buildBlockTraitResponsiveCss` gone | Guardrail test + `NO_DEF` search |
| Device CSS = visibility + phone only | `buildEmbeddedStepDeviceCss` |
| Save rewrites artifact | `FormBuilder.handleSave` |
| Publish is status-only | `PopupDetail.handleStatusChange` |
| Embed PUBLISHED gate | `PopupService.getPopupEmbedConfig` |
| builderState peek | `PopupEmbedResponse` extract* methods |
| Preview device transform | `formPreviewEmbedPresentation.ts` |
| Steady-state no merchant wrapper inline | `popupCssSteadyStateArchitecture.test.ts` |

*End of reconciliation audit. Production code unmodified.*
