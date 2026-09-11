# Popup Render Artifact Contract — Phase 1A Decision

**Status:** Approved contract design (no production code in this phase)  
**Date:** 2026-07-04  
**Depends on:** Phase 0 baseline [`popup-responsive-css-phase0-baseline.md`](./popup-responsive-css-phase0-baseline.md)  
**Target architecture:** [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md)  
**Next:** Phase 1B — wire types, metadata keys, embed DTO (no CSS migration, no runtime mounting)

---

## A. Current contract map

```text
Popup Builder (FormBuilder.handleSave)
        ↓
serializePopupExport(snapshot)  →  PopupHtmlExport
        ↓
buildPopupSaveMetadata(snapshot, htmlExport)  →  Record<string, string>
        ↓
popupApi.updatePopup({ htmlContent, metadata })
        ↓
PopupService.updatePopup  →  Popup entity (MongoDB)
        ↓
GET /api/v1/popups/embed/{popupId}  →  PopupEmbedResponse.fromEntity(popup)
        ↓
Storefront embed JS: normalizeEmbedConfig(response)  →  PopupEmbedConfig
        ↓
embed/runtime.ts: resolveStepHtml → innerHTML → hoistExportedStepStyles()
```

### Serializer → frontend export type

| Item | Location |
|------|----------|
| Compile entry | `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/index.ts` — `serializePopupExport()` |
| Export type | `PopupHtmlExport` — same file |
| Per-step HTML | `serializePopupHtmlByStep()` → `Record<string, string>` |
| Primary step | `resolvePrimaryStepId()` |
| Metadata key constants | `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/exportMetadata.ts` |
| Save merge | `adflipr-app/src/layouts/PopupBuilder/utils/popupBuilderState.ts` — `buildPopupSaveMetadata()` |
| Builder state schema | `PopupBuilderSnapshot` + `POPUP_BUILDER_SCHEMA_VERSION = 1` — `popupBuilderState.ts` |

**Current `PopupHtmlExport` shape (code today):**

```typescript
interface PopupHtmlExport {
  htmlContent: string;           // primary step HTML (thumbnail + legacy fallback)
  htmlByStep: Record<string, string>;
  primaryStepId: string;
}
```

### Save shape → API request

| Field | Where stored | Type |
|-------|--------------|------|
| `htmlContent` | `Popup.htmlContent` | top-level string on entity |
| `metadata.htmlByStep` | `Popup.metadata["htmlByStep"]` | JSON string of `Record<stepId, html>` |
| `metadata.htmlPrimaryStepId` | `Popup.metadata["htmlPrimaryStepId"]` | string |
| `metadata.builderState` | `Popup.metadata["builderState"]` | JSON string of `PopupBuilderSnapshot` |
| `metadata.schemaVersion` | `Popup.metadata["schemaVersion"]` | string `"1"` (builder-state migration) |
| `metadata.formPosition` | `Popup.metadata["formPosition"]` | string (banner/flyout edge) |
| Targeting keys | via `mergePopupTargetingIntoSaveMetadata()` | strings |

**Save call site:** `FormBuilder.tsx` → `popupApi.updatePopup(popupId, { htmlContent, metadata })`.

**Frontend API types:** `adflipr-app/src/api/popupApi.types.ts` — `PopupMetadata` is `Record<string, string | undefined>` with documented keys; `htmlByStep` is typed as optional **string** (JSON), not a parsed map.

### Backend storage

| Item | Location |
|------|----------|
| Entity | `service/src/main/java/com/goveca/recruit/ad/model/Popup.java` |
| Metadata key constants | `service/src/main/java/com/goveca/recruit/ad/popup/PopupMetadataKeys.java` |
| Metadata merge on update | `PopupMetadataMerge.java` (preserves server-managed keys only) |
| Content version bump | `PopupVersioning.shouldBumpContentVersion()` — bumps when `htmlContent` or **any** metadata map change |
| Merchant `version` field | `Popup.version` — monotonic **content/design** version for analytics/submissions |

**`htmlByStep` is not a typed Mongo field.** It is an opaque string inside `Map<String, String> metadata`, parsed at embed-read time.

### Embed DTO → runtime config

| Item | Location |
|------|----------|
| Public embed response | `service/src/main/java/com/goveca/recruit/ad/response/PopupEmbedResponse.java` |
| Embed endpoint | `PopupService.getPopupEmbedConfig()` |
| Runtime config type | `adflipr-app/src/layouts/PopupBuilder/embed/types.ts` — `PopupEmbedConfig` |
| Normalizer | `adflipr-app/src/layouts/PopupBuilder/embed/utils.ts` — `normalizeEmbedConfig()` |

**`PopupEmbedResponse` exposes to storefront (no auth):**

```java
String popupId;
PopupType type;
String formPosition;          // banner / side message only
Map<String, String> positionOffset;
Integer version;              // Popup.version (content version)
String htmlPrimaryStepId;
Map<String, String> htmlByStep;
```

**Not exposed:** `builderState`, `schemaVersion`, `targetSettings`, `rules`, or any other metadata.

**Legacy fallback:** If `htmlByStep` is empty but `htmlContent` is set, `PopupEmbedResponse.fromEntity()` synthesizes `htmlByStep = { primaryStepId → htmlContent }`.

**Runtime `PopupEmbedConfig` today:**

```typescript
interface PopupEmbedConfig {
  popupId: string;
  version?: number;
  type?: string;
  formPosition?: string;
  positionOffset?: { top?; right?; bottom?; left? };
  htmlPrimaryStepId: string;
  htmlByStep: Record<string, string>;
}
```

### HTML-level responsive marker (not artifact metadata)

Exported `<form>` may carry `data-adflipr-popup-responsive="v1"` (`POPUP_ATTR_RESPONSIVE` / `POPUP_RESPONSIVE_EXPORT_VERSION` in `popupEmbedContract.ts`). This marks **single-HTML responsive export** inside step HTML. It is **not** stored as a popup metadata field and **not** returned on `PopupEmbedResponse`.

---

## B. Answers to trace questions

| # | Question | Answer (from code) |
|---|----------|-------------------|
| 1 | Canonical serialized output type? | **`PopupHtmlExport`** (`popupHtmlSerializer/index.ts`) — frontend compile result before save. |
| 2 | Where is `htmlByStep` defined? | As **`PopupHtmlExport.htmlByStep`** (in-memory map). Persisted as **`metadata["htmlByStep"]`** JSON string. |
| 3 | How is it saved? | `buildPopupSaveMetadata()` → `JSON.stringify(htmlExport.htmlByStep)` → `popupApi.updatePopup` → `Popup.metadata`. |
| 4 | Typed backend field or metadata? | **Metadata JSON string** — not a first-class Mongo/Java field. |
| 5 | Path to `PopupEmbedResponse`? | `Popup.metadata["htmlByStep"]` parsed in `PopupEmbedResponse.parseHtmlByStep()` → public `htmlByStep` map. |
| 6 | Existing artifact/version fields? | **`Popup.version`** (content version); **`metadata.schemaVersion`** (builder state); **`data-adflipr-popup-responsive="v1"`** in HTML. No `renderCss`, `artifactVersion`, `contractVersion`, or `serializerBuild` in storage/API today. |
| 7 | What does `version` represent? | **Merchant content/design version** — bumps when `htmlContent` or metadata changes (`PopupVersioning`). Used in analytics/submissions (`popupVersion`), **not** serializer build id or artifact format. |
| 8 | What does `schemaVersion` represent? | **Builder editor state shape** (`POPUP_BUILDER_SCHEMA_VERSION = 1` in `popupBuilderState.ts`). Written on save inside metadata; used for `parseBuilderState()` / migration. **Not** render-artifact format or embed runtime contract. |
| 9 | Does `serializerBuild` exist? | **No** anywhere in codebase. |
| 10 | Frontend/backend key duplication? | **Yes, manual** for known keys: e.g. `exportMetadata.ts` `POPUP_METADATA_HTML_BY_STEP = 'htmlByStep'` mirrors `PopupMetadataKeys.HTML_BY_STEP`. New keys must be added in **both** places (plus `popupApi.types.ts` docs, tests). |

---

## C. Version semantics matrix

| Field | Current meaning (proved) | Proposed meaning | Phase 1A decision |
|-------|--------------------------|------------------|-------------------|
| **`Popup.version`** | Monotonic merchant content version; bumps on HTML/metadata change; stamped on events/submissions | **Unchanged** — not artifact format | **Keep** — do not overload |
| **`metadata.schemaVersion`** | Builder snapshot shape for editor load/migrate (`"1"`) | **Unchanged** — rename mentally to `builderSchemaVersion` concept | **Keep** — do not rename in 1A/1B (avoid migration churn) |
| **`data-adflipr-popup-responsive="v1"`** | HTML declares single-HTML + device CSS export generation | **Unchanged** — runtime/render **contract marker in HTML** | **Keep** — not a stored artifact field |
| **`artifactVersion`** | Does not exist | Integer: **compiled render artifact field set version** (e.g. `1` = `htmlByStep` + optional `renderCss` shape) | **Reject required now** — defer until format evolution (Phase 2+). Optional metadata in 1B only if team wants explicit marker without `renderCss` content. |
| **`contractVersion`** | Does not exist as stored field; HTML has `responsive=v1` | Stored: embed HTML/data-attribute contract generation | **Reject now** — redundant with `POPUP_ATTR_RESPONSIVE` in HTML; add stored field only if HTML contract diverges from artifact container |
| **`serializerBuild`** | Does not exist | Opaque build id (git sha, package version) of serializer that compiled artifact | **Reject for 1A/1B** — add in Phase 2+ when `renderCss` is non-empty; useful for debugging stale artifacts, not for runtime branching yet |
| **`renderCss`** | Does not exist | Complete popup-owned presentation CSS for matching `htmlByStep` | **Add (optional)** — contract only in 1A; wire in 1B |

---

## D. Recommended artifact contract

### Decision: **Option A — extend `PopupHtmlExport`** (with documented alias)

**Rationale:** Smallest diff aligned with existing pipeline (`serializePopupExport` → `buildPopupSaveMetadata` → save). No rename of `htmlContent` / `htmlByStep`. Avoids parallel type hierarchies before Phase 2 migration.

**Conceptual name:** `PopupRenderArtifact`  
**Implementation name (Phase 1B):** extend **`PopupHtmlExport`**; add type alias optional:

```typescript
/** Compiled storefront render artifact (HTML + future presentation CSS). */
export interface PopupHtmlExport {
  htmlContent: string;
  htmlByStep: Record<string, string>;
  primaryStepId: string;
  /** Popup-owned presentation CSS; empty/missing = legacy embedded-style path. Phase 1B: always omit or "". */
  renderCss?: string;
}
```

**Do not introduce a separate required type until Phase 2** unless compile boundaries split (e.g. dedicated `compilePopupRenderArtifact()` wrapper).

### Backend / public API shape (Phase 1B target)

**Merchant-authenticated resource** (`PopupResponse` / `Popup.metadata`):

```text
htmlContent: string
metadata:
  htmlByStep: string          // JSON map (unchanged)
  htmlPrimaryStepId: string   // (unchanged)
  builderState: string        // editor-only (unchanged)
  schemaVersion: string       // builder-only (unchanged)
  renderCss: string           // NEW — optional; CSS text or absent
```

**Public embed** (`PopupEmbedResponse` — storefront):

```text
popupId, type, formPosition, positionOffset,
version,                    // content version (unchanged)
htmlPrimaryStepId,
htmlByStep,
renderCss                   // NEW — optional string; omitted when absent/empty
```

**Runtime config** (`PopupEmbedConfig`):

```typescript
interface PopupEmbedConfig {
  // ...existing fields...
  renderCss?: string;
}
```

**Pattern mirrors `htmlByStep`:** authored in metadata on save; **parsed and exposed as typed field** on embed response for storefront (builderState stays metadata-only).

---

## E. `renderCss` semantics

### Scope (target, verified against builder model)

| Question | Answer |
|----------|--------|
| Per popup or per step? | **One string per popup** — all steps share one stylesheet (matches multi-step duplicate `<style>` problem in audit). |
| Per device? | **No** — desktop + mobile in **one** sheet via `@media` inside `renderCss`. |
| Media queries / keyframes / custom CSS? | **Yes** when populated (Phase 2+) — fully popup-scoped; includes merchant custom CSS when migrated. |
| Runtime shell CSS? | **No** — overlay/scroll-lock stay in embed bundle (`embed/`). |
| Fully popup-scoped? | **Yes** — all rules under artifact root selector (target KB §13). |

### Runtime branching rule (simplest sufficient rule)

```text
renderCss is non-empty (after trim)
  → Phase 1C+: popup-level artifact CSS lifecycle (mount once per instance)

renderCss is missing OR renderCss is "" (or whitespace-only)
  → legacy lifecycle: per-step embedded <style> + hoistExportedStepStyles()
```

**Missing and empty: same semantics.** Do not branch on absence vs `""`.

**Phase 1B behavior:** Do **not** write `renderCss` on save (remains missing). Visual output unchanged.

**Phase 2+ behavior:** Write non-empty `renderCss` only when serializer actually moves presentation out of inline/embedded step HTML.

### Why not distinguish missing vs empty?

No code path today depends on the distinction. Empty placeholder would force every new save through artifact detection without providing CSS — no benefit until Phase 2. If a future format uses compression, introduce **`artifactVersion`** then — not empty-string overload.

---

## F. Backward compatibility rule

```text
if (typeof renderCss === 'string' && renderCss.trim().length > 0) {
  // Phase 1C+ artifact CSS lifecycle
} else {
  // Legacy: inline style="" + embedded <style> in htmlByStep + hoist
}
```

**Explicit `artifactVersion` not required for Phase 1B–1C** because:

- Legacy artifacts: field absent → legacy path.
- Phase 1B saves: field still absent → legacy path (no merchant impact).
- Phase 2+ saves: non-empty `renderCss` → unambiguous new path.

**When to add `artifactVersion`:** Before any breaking change to `renderCss` encoding (compression, chunking, per-step split) or if empty `renderCss` must mean “compiled under new serializer but presentation still legacy.”

**Republish:** Existing published popups unchanged until merchant saves again. No forced republish.

**`Popup.version`:** Continues to bump when metadata gains `renderCss` — analytics remain correct.

---

## G. Artifact ownership invariant

```text
One compilation (serializePopupExport)
        ↓
atomic PopupHtmlExport { htmlByStep, renderCss?, primaryStepId, htmlContent }
        ↓
one save payload (htmlContent + metadata)
```

| Question | Contract answer |
|----------|-----------------|
| Regenerate HTML without CSS? | **Forbidden** in production path — single compile function owns both. |
| Regenerate CSS without HTML? | **Forbidden** — same compile. |
| Publish update both atomically? | **Yes** — one `updatePopup` with `htmlContent` + metadata from same `htmlExport`. |
| Old `renderCss` + new HTML? | **Invalid** — prevented by atomic save; if detected (manual DB edit), runtime should prefer **legacy path** and log (Phase 1C). |
| Stale-artifact detection (future)? | Pair via single save + `Popup.version` bump; optional `serializerBuild` in Phase 2+ for support tooling. |

---

## H. Storage recommendation

| Horizon | Recommendation | Why |
|---------|----------------|-----|
| **Phase 1B (now)** | **`metadata.renderCss` string** + **`PopupEmbedResponse.renderCss`** + **`PopupEmbedConfig.renderCss`** | Matches `htmlByStep` precedent; smallest backend change; embed already strips metadata. |
| **Long-term** | Same public embed field; optional first-class Mongo field only if size/indexing/query needs arise | CSS may be large; metadata map already holds `builderState` + `htmlByStep`. Moving to nested `renderArtifact` object is **not** needed until multiple artifact subfields justify it. |

**Reject for now:**

- `sharedCss` — implies cross-popup sharing (KB rejects).
- Separate per-step `renderCss` map — contradicts target lifecycle.
- External CSS files / CDN per popup — out of scope.
- Nested `renderArtifact` object in Phase 1B — extra churn without Phase 1C consumer.

**Rejected Option C alone:** metadata-only without embed DTO field would force storefront to use authenticated API or parse metadata — breaks current public embed contract.

---

## I. Naming decisions

| Name | Decision |
|------|----------|
| **`renderCss`** | **Adopt** — matches target KB; clear vs `htmlByStep`; not `sharedCss`. |
| `artifactCss` | Acceptable alias in docs only; code uses `renderCss`. |
| `popupCss` / `compiledCss` | Reject — ambiguous (runtime vs artifact). |
| **`PopupHtmlExport`** | **Keep** type name in Phase 1B; document as compile output / render artifact. |
| `PopupRenderArtifact` | **Type alias** optional in Phase 1B docs/comments; rename deferred. |

**Metadata constant (Phase 1B):**

```text
Frontend: POPUP_METADATA_RENDER_CSS = 'renderCss'
Backend:  PopupMetadataKeys.RENDER_CSS = "renderCss"
```

---

## J. Phase 1C readiness (contract-only checks)

The contract **does not block** Phase 1C lifecycle:

```text
Mount once per popup instance
Keep across step transitions
Remove on destroy
```

**Requirements satisfied by:**

- One `renderCss` string per popup (not per step).
- Embed config carries `renderCss` alongside `htmlByStep` (same fetch).
- Legacy path unchanged when `renderCss` absent.

**Avoid in Phase 1B:** per-step CSS maps, global unscoped CSS, or putting presentation in runtime bundle.

---

## K. Phase 1B implementation scope (exact files — no more)

### Frontend

| File | Change |
|------|--------|
| `popupHtmlSerializer/index.ts` | Add optional `renderCss?: string` to `PopupHtmlExport`; **do not populate** (or always `undefined`) |
| `popupHtmlSerializer/exportMetadata.ts` | `POPUP_METADATA_RENDER_CSS`, `parseRenderCss(metadata)` |
| `popupBuilderState.ts` | `buildPopupSaveMetadata()` — **omit** `renderCss` key in 1B (or pass through only if already in `existingMetadata` via passthrough) |
| `api/popupApi.types.ts` | Document `renderCss?: string` on `PopupMetadata` |
| `embed/types.ts` | `renderCss?: string` on `PopupEmbedConfig` |
| `embed/utils.ts` | `normalizeEmbedConfig()` — pass through optional `renderCss` |
| Tests | `popupBuilderState.test.ts`, `popupHtmlSerializer.test.ts`, `popupEmbedUtils.test.ts`, `PopupEmbedResponseTest.java` mirror |

### Backend

| File | Change |
|------|--------|
| `popup/PopupMetadataKeys.java` | `RENDER_CSS = "renderCss"` |
| `response/PopupEmbedResponse.java` | Optional `String renderCss` from metadata; omit from JSON when blank |
| `response/PopupEmbedResponseTest.java` | Parse/pass-through tests |

### Explicitly out of Phase 1B

- Serializer CSS extraction / inline removal
- `hoistExportedStepStyles` / runtime mounting changes
- WordPress / Shopify PHP or loader changes
- `artifactVersion` / `serializerBuild` writes
- `!important` / presentation fixes

---

## L. Risks and open questions

| Risk / question | Blocks 1B? | Mitigation |
|-----------------|:------------:|------------|
| Large `renderCss` in metadata document size | No | Monitor; same as `htmlByStep` + `builderState` today |
| Manual metadata key drift (FE/BE) | No | Add constant in both + test |
| Merchant saves from old app without `renderCss` | No | Absent = legacy (rule above) |
| Partial deploy (new BE, old FE) | No | Field optional; old clients ignore |
| When to write non-empty `renderCss`? | No (Phase 2) | Phase 0 baseline gates migration |
| Need `artifactVersion` before Phase 2? | **No** | Revisit when first non-empty `renderCss` ships |

**No open questions block Phase 1B** if this document is accepted.

---

## M. Final decision standard — answers

| # | Question | Answer |
|---|----------|--------|
| 1 | Smallest contract change before Phase 1B? | Extend **`PopupHtmlExport`** + **`metadata.renderCss`** + **`PopupEmbedResponse.renderCss`** + **`PopupEmbedConfig.renderCss`**; constants in FE/BE; **no values written yet**. |
| 2 | Should `renderCss` be optional? | **Yes** — optional everywhere; absent for all current + Phase 1B saves. |
| 3 | Different meaning for missing vs empty? | **No** — both mean legacy embedded-style path. |
| 4 | Need `artifactVersion` now? | **No** — defer until format evolution or empty-placeholder semantics needed. |
| 5 | Need `contractVersion` now? | **No** — HTML `data-adflipr-popup-responsive="v1"` suffices; stored field redundant. |
| 6 | Need `serializerBuild` now? | **No** — defer to Phase 2+ when non-empty `renderCss` ships. |
| 7 | Where should `renderCss` eventually be stored? | **`metadata.renderCss`** on save; **typed field on `PopupEmbedResponse`** for storefront (mirror `htmlByStep`). |
| 8 | Backward-compatibility rule for runtime? | **Non-empty trimmed `renderCss` → artifact CSS lifecycle; else legacy hoist path.** |
| 9 | What should Phase 1B implement—and nothing more? | Types, constants, metadata/embed DTO pass-through, tests; **no CSS content, no visual change, no runtime mounting**. |

---

## N. Option comparison (why not B/C/D)

| Option | Verdict |
|--------|---------|
| **A — Extend `PopupHtmlExport`** | **Chosen** — minimal, matches save pipeline, same compile function. |
| B — New `PopupRenderArtifact` type only | Reject as primary — duplicate mapping without benefit in 1B. |
| C — Metadata only, no embed field | Reject — storefront cannot access metadata today. |
| D — Nested `renderArtifact` object now | Reject — premature; flatten matches existing `htmlByStep` pattern. |

---

**Phase 1A complete.** Phase 1B is mechanical: wire optional `renderCss` through save and embed DTO without changing compiled output or runtime behavior.
