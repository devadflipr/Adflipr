# Popup Builder — Reviewer Module Map

**Purpose:** Index every Popup Builder–related module for PR reviews. The reviewer agent loads this first, then deep-dives linked KB per changed area.

**Canonical root:** `adflipr-app/src/layouts/PopupBuilder/` (`@pb/*`)

---

## 1. Lifecycle (merchant → customer)

```
Merchant edits (FormBuilder React state)
    ↓
PopupBuilderSnapshot (historyReducer + useState)
    ↓
serializePopupExport / buildPopupSaveMetadata
    ↓
API PUT /api/v1/popups/{id}  (metadata.builderState, htmlContent, htmlByStep, …)
    ↓
Embed bundle fetch (candidates API)
    ↓
embed/ runtime mount (DOM injection + renderCss lifecycle)
    ↓
Customer browser (Shopify / WordPress / script tag)
```

**Deep dive:** `ai/kb/popup-builder-rendering-pipeline.md`

---

## 2. Module inventory

### 2.1 Builder (editor)

| Module | Path | Purpose | KB |
|--------|------|---------|-----|
| Editor shell | `pages/EditorPage.tsx` | Load popup, route, DnD provider | `popup-builder-frontend-kt.md` |
| Form builder | `components/form-builder/FormBuilder.tsx` | State, toolbar, save, preview | pipeline §1, §10 |
| Layers | `components/form-builder/FormBuilderWithLayers.tsx`, layers panel | Structure tree, reorder | `popup-builder-structure-dnd.md` |
| Block canvas | `components/form-builder/blocks/**` | Per-block React canvas | `popup-block-registry.md` |
| Property panels | `components/form-builder/property-panels/**` | Traits / merchant styling | block phase reports |
| Types | `components/form-builder/types.ts` | Canonical element/style types | — |
| History | `utils/formBuilderHistory.ts` | Undo/redo | pipeline §10 |
| Builder state | `utils/popupBuilderState.ts` | Snapshot parse/serialize/fingerprint | pipeline §1 |
| DnD structure | `utils/formStructure.ts`, `sectionColumnMutations.ts` | canDrop, layout limits | `popup-builder-rules-inventory.md` |
| Device visibility | `components/form-builder/shared/pbnDeviceVisibility.ts` | Desktop/mobile hide | responsive KB |
| Preview tab | `utils/popupEditorPreview.ts` | sessionStorage draft preview | pipeline §4 |
| API mapping | `utils/popupApiMapping.ts` | Save payload, trigger rules | `popups-api.md` |

### 2.2 Serializer & HTML generation

| Module | Path | Purpose | KB |
|--------|------|---------|-----|
| Serializer entry | `utils/popupHtmlSerializer/index.ts` | Export bundle, htmlByStep | `popup-html-serialization.md` |
| Block registry | `utils/popupHtmlSerializer/blockRegistry.ts` | Block → serializer dispatch | `popup-block-registry.md` |
| Block serializers | `utils/popupHtmlSerializer/blocks/**` | Per-block HTML + hooks | block phase reports |
| Register serializers | `utils/popupHtmlSerializer/registerPopupBlockSerializers.ts` | Registration | — |
| Types | `utils/popupHtmlSerializer/types.ts` | Export types only | — |
| Render CSS builders | `utils/*RenderCss.ts`, `*Presentation*.ts` | Presentation CSS fragments | `popup-rendering-css-architecture.md` |
| CSS architecture tests | `utils/__tests__/popupCssArchitectureGuardrails.test.ts` | Invariant guards | verification checklist |

### 2.3 Contract (serializer ↔ runtime)

| Module | Path | Purpose | KB |
|--------|------|---------|-----|
| Embed contract | `contract/popupEmbedContract.ts` | `data-adflipr-*` attrs, actions | `popup-embed-contract.md` |
| Spinner contract | `contract/popupSpinnerContract.ts` | Spinner JSON shape | `spin-to-win-brd.md` |
| Contract tests | `contract/__tests__/` | Attr parity | — |

### 2.4 Embed runtime (storefront)

| Module | Path | Purpose | KB |
|--------|------|---------|-----|
| Embed entry | `embed/` | Mount, dismiss, step transitions | `popup-runtime-v2-design.md` |
| CSS lifecycle | `embed/mountRenderArtifactCss.ts` | renderCss mount/destroy | `popup-css-lifecycle-audit.md` |
| Block initializers | `embed/blockInitializers/` | Runtime behavior per block | embed contract |
| Constants | `embed/constants.ts` | Shell ids/classes (not data attrs) | `popup-embed-contract.md` |
| Fetch/retry | `embed/fetchRetry.ts` | Candidate loading | — |

### 2.5 Preview & rendering surfaces

| Surface | Where | HTML source | CSS source |
|---------|-------|-------------|------------|
| Builder canvas | Block `*Canvas.tsx` | React components | Tailwind `.pbn-root` + block CSS |
| In-editor preview | Preview mode / preview tab | Serializer or mirror | Same as export target |
| Serializer output | `htmlContent`, `htmlByStep` | `popupHtmlSerializer` | `renderCss` artifact |
| Embed runtime | Injected into host page | Saved HTML | `renderCss` + shell CSS |

**Target contract:** `ai/kb/popup-rendering-css-architecture.md`  
**As-is lifecycle:** `ai/kb/popup-css-lifecycle-audit.md`

### 2.6 Backend

| Module | Path | Purpose | KB |
|--------|------|---------|-----|
| Popup API | `service/.../PopupController.java`, `PopupService.java` | CRUD, publish | `popups-api.md` |
| Embed triggers | `PopupEmbedTriggerSupport.java` | Candidates, frequency, device | `popup-builder-rules-inventory.md` §A.2 |
| Metadata keys | `PopupMetadataKeys.java` | builderState, htmlByStep, rules | — |
| Event ingest | `PopupEventIngestService.java` | Submissions, analytics | `popup-analytics-and-submissions-spec.md` |

### 2.7 Storefront integrations

| Platform | Location | Notes |
|----------|----------|-------|
| Shopify | `shopify/` extensions, app embed | Loads embed script |
| WordPress | `wordpress/` plugin | Popup runtime injection |
| Audit | `ai/kb/wordpress-popup-rendering-audit.md` | WP-specific parity |

### 2.8 Tests & E2E

| Area | Path |
|------|------|
| Serializer unit | `PopupBuilder/utils/__tests__/`, `popupHtmlSerializer/__tests__/` |
| Embed lifecycle | `embed/__tests__/` |
| Browser/computed style | `*.browser.test.ts` |
| E2E | `adflipr-app/e2e/popup-builder-*.spec.ts` |
| Architecture guards | `phase4dArchitecture.test.ts`, `popupCssArchitectureGuardrails.test.ts` |

---

## 3. Rendering environment differences

| Concern | Builder | Preview | Storefront embed |
|---------|---------|---------|------------------|
| React tree | Full | Reduced / serialized | None (HTML injection) |
| Tailwind `.pbn-root` | Yes | Partial | No |
| `renderCss` artifact | Target parity | Should match embed | Primary presentation |
| Host page CSS | Isolated | Isolated iframe/tab | **Conflict risk** — scoping critical |
| Device toggle | Editor UI | Should match @media | Real viewport only |
| Step transitions | React state | Serialized HTML swap | Runtime HTML swap |

---

## 4. CSS ownership (quick reference)

| Owner | What | Where |
|-------|------|-------|
| Block presentation | Colors, spacing, typography, responsive | `renderCss` (target) / block RenderCss builders |
| Embed shell | Overlay, scroll-lock, positioning | `embed/` infrastructure CSS |
| Builder chrome | Panels, toolbar | `PopupBuilder/styles/`, Tailwind |
| Merchant custom CSS | Scoped or rejected | Never raw `document.head` concat |
| Legacy path | Inline / per-step `<style>` | Migration only — flag expansions |

**Review checklist:** `ai/verification/popup-rendering-css-architecture-checklist.md`

---

## 5. Key invariants (review gates)

1. **One HTML structure** for desktop + mobile; responsive via `@media` in artifact.
2. **`renderCss` once per popup**, not duplicated per step.
3. **Scoped selectors** — `[data-adflipr-popup-artifact]` or export root; no bare `input`, `button`, `*`.
4. **Stable `data-adflipr-*` ids** on styled elements.
5. **Runtime destroys** instance-owned stylesheet on dismiss.
6. **Contract parity** — serializer emits attrs embed reads (same constants).
7. **Legacy artifacts** must still render without forced republish.

---

## 6. Cursor / repo rules (pointers)

- `.cursor/rules/popup-html-serializer-styles.mdc`
- `.cursor/rules/popup-builder-architecture-kb.mdc` (if present)
- `docs/POPUP_BUILDER.md`
- `docs/POPUP_BUILDER_ARCHITECTURE_AND_REVIEW.md`

---

## 7. KB index (popup-*)

Use `ai/kb/popup-*` for block-family phase reports, audits, and migration status. Search by block name or phase id when reviewing a specific block change.

**Overall CSS architecture summary:** `ai/kb/popup-css-architecture-overall-summary-through-phase4.md`
