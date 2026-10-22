# Popup Builder — rules inventory (DnD + backend)

**As of:** 2026-07-02  
**Scope:** All enforceable rules around **draggable / structure** editing in Popup Builder, plus what the **Java backend** validates on save, publish, and embed.  
**Canonical DnD how-to:** [`popup-builder-structure-dnd.md`](./popup-builder-structure-dnd.md) (pipeline + file map).  
**This doc:** full rule catalog + backend gap analysis.

---

## Executive summary

| Layer | Rule count (approx.) | Enforced where |
|-------|----------------------|----------------|
| **Structure DnD matrix** (`canDrop`) | **5 drag kinds × 4 target kinds** → **10 allowed pairs** | Frontend `formStructure.ts` |
| **Layout numeric limits** | **2** (`MAX_LAYOUT_COLUMNS = 3`, `MIN_LAYOUT_SECTIONS = 1`) | Frontend structure + section mutations |
| **Column/section mutation guards** | **4 rejection reasons** | Frontend `sectionColumnMutations.ts` |
| **DnD UI gates** | **3** (preview mode, store-connected product, palette `maxInstances`) | Frontend DnD + palette |
| **Parallel reorder paths** | **2** (layers HTML5 drag, section trait SortableJS) | Frontend panels (same mutations) |
| **Backend save/publish structure** | **0** DnD/tree rules | `builderState` stored as opaque JSON |
| **Backend embed/display rules** | **Separate product** (triggers, URL, device, frequency) | `PopupEmbedTriggerSupport`, metadata keys |

**Bottom line:** Every rule about **what can be dragged where** and **how many columns/sections** lives in the **React editor**. The backend does **not** validate `formElements` tree shape, drag legality, or block counts on save or publish.

---

## Part A — Backend perspective

### A.1 What the backend stores (popup save)

`PUT /api/v1/popups/{id}` → `PopupService.updatePopup` persists:

| Field | Validation on save | Used for structure/DnD? |
|-------|-------------------|-------------------------|
| `name` | Unique per account **and type** (if provided). Landing page vs popup vs embed may share a name. | No |
| `type` | **Required** (`@NotNull` on `UpdatePopupRequest`) | Campaign type only |
| `status` | Optional (DRAFT / PUBLISHED) | No tree validation on publish |
| `htmlContent` | Stored as-is | Serialized HTML output, not live tree |
| `metadata` | Merged via `PopupMetadataMerge` | See keys below |

**Metadata keys** (`PopupMetadataKeys.java`):

| Key | Content | Backend validates shape? |
|-----|---------|--------------------------|
| `builderState` | Full editor snapshot (`formElements`, `formStyle`, steps, …) | **No** — JSON string only |
| `htmlByStep` | stepId → serialized HTML | **No** — not checked for non-empty on save |
| `htmlPrimaryStepId` | Primary step id | **No** |
| `targetSettings` | URL show/hide lists (Target panel) | **No** on save; parsed for embed |
| `rules` | Advanced Rules panel rows (device type, etc.) | **No** on save; parsed for embed |
| `formPosition` | Banner/flyout edge placement | **No** on save; read for embed response |
| `submissionTagId` | Server-managed; preserved on merge | N/A |

**Create popup** (`createPopup`): requires `name` + `type`; duplicate name rejected **within the same `PopupType`**.

**Conclusion:** **Zero** backend rules enforce draggable structure, column counts, or `canDrop` semantics.

---

### A.2 Backend rules that *are* real (not DnD — embed / runtime)

These read **saved** `metadata` / `builderState` at **embed candidate** or **telemetry** time. They mirror frontend display settings but do not block invalid editor trees.

| Area | Class / entry | What it enforces |
|------|---------------|------------------|
| **Display triggers** | `PopupEmbedTriggerSupport.resolveTriggers` | exit intent, delay, scroll %, inactivity, custom click, URL match, frequency cap |
| **Device filter** | `PopupEmbedTriggerSupport` + `metadata.rules` | Device Type rows (desktop/mobile) on candidate list |
| **Frequency cap** | `PopupEmbedTriggerSupport.isFrequencyCapped` | VIEW/SUBMIT history per `visitorId` |
| **Embed HTML presence** | `PopupEmbedTriggerSupport.hasEmbedHtml` | Candidate skipped if no exportable HTML |
| **Spinner config** | `PopupSpinnerConfigSupport` | Parse spinner JSON from `builderState`; invalid → logged |
| **Flyout position / offset** | `PopupEmbedResponse` | Read `formPosition`, `positionOffset` from `builderState` |
| **Telemetry rate limits** | `PopupSpinService`, public endpoint registry | Per-IP / per-popup spin & event limits |
| **Publish gate (soft)** | `PopupSpinService`, embed fetch | Unpublished/deleted popup → 404 on spin/embed |

Frontend source of truth for trigger **shape** when saving: `buildEmbedTriggerRulesFromFormStyle` in `popupApiMapping.ts` (mirrored server-side in `PopupEmbedTriggerSupport`).

---

### A.3 Backend vs frontend rule split (diagram)

```
Editor drag/drop
  └─► formStructure.ts (canDrop, applyStructureDrop)     ← ALL structure rules
  └─► save → serializePopupExport → htmlContent + metadata.builderState
        └─► PopupService.updatePopup                       ← NO tree validation
              └─► MongoDB
                    └─► embed API / candidates             ← trigger + device + frequency only
```

---

## Part B — Frontend structure DnD rules (draggables)

### B.1 Drag kinds (5)

| # | `PbnDragKind` | Source | Disabled when |
|---|---------------|--------|---------------|
| 1 | `palette-layout` | Blocks panel — layout items (`category === 'layout'`) | Preview mode; palette `draggable=false` |
| 2 | `palette-block` | Blocks panel — content blocks | Preview; `maxInstances` reached; product without store (on **drop**, not drag) |
| 3 | `canvas-block` | Grip on block chrome | Preview mode |
| 4 | `canvas-section` | Grip on section row | Preview mode |
| 5 | `canvas-column` | Grip on column chrome | Preview mode |

**Code:** `formStructure.ts` (`PbnDragKind`), `FormBlock.tsx`, `pbnStructureDnd.tsx` (`usePbnDragSource`).

---

### B.2 Drop target kinds (4)

| # | `PbnDropTargetKind` | UI component | `allowInside` |
|---|---------------------|--------------|---------------|
| 1 | `root` | `PbnCanvasRootZone` — **no drop wired today** | — |
| 2 | `section-edge` | `SectionDropShell` | `false` (before/after only) |
| 3 | `column` | `ColumnDropZone` | `true` (before/after/inside) |
| 4 | `block` | `CanvasBlockShell` drop zone | `false` (before/after only) |

**Code:** `PbnDnDZones.tsx`, `CanvasBlockShell.tsx`.

---

### B.3 Authoritative `canDrop` matrix (10 allowed pairs)

Defined in `formStructure.ts` → `canDrop(dragKind, targetKind)`.

| Drag ↓ / Target → | `root` | `section-edge` | `column` | `block` |
|-------------------|:------:|:--------------:|:--------:|:-------:|
| **`palette-layout`** | ✅ | ✅ | ❌ | ❌ |
| **`palette-block`** | ❌ | ❌ | ✅ | ✅ |
| **`canvas-block`** | ✅* | ❌ | ✅ | ✅ |
| **`canvas-section`** | ✅* | ✅ | ❌ | ❌ |
| **`canvas-column`** | ❌ | ❌ | ✅ | ❌ |

\* `root` pairs are in the matrix but **no canvas drop zone** exposes `{ kind: 'root' }` — effectively **unreachable** via UI today.

**All other combinations:** drop rejected (`canDrop` → `false`, `applyStructureDrop` → `null`).

---

### B.4 Drop position rules (pointer geometry)

| Rule | Value | Code |
|------|-------|------|
| Top 25% of target | `before` | `pbnStructureDnd.tsx` → `getDropPositionFromPointer` |
| Bottom 25% | `after` | same |
| Middle 50% | `inside` **only if** `allowInside` | Columns only |
| Middle on block/section-edge | Falls back to `before`/`after` logic | `allowInside: false` |

**Nested zones / zero padding:** `section-edge` wraps `column` wraps `block`. With padding `0`, children fill the section and win `isOver({ shallow: true })`. Structure drags stay restricted to `section-edge`/`root` in the matrix; nested column/block nodes use `pointer-events: none` during structure drag (`useStructureDragPassThroughStyle`) so the section shell remains hittable. Do not widen the matrix to “fix” overlap.

---

### B.5 Single mutation entry point

| Rule | Description |
|------|-------------|
| **All canvas/palette drops** | Must go through `applyStructureDrop()` only |
| **No per-block `onDrop`** | Block components must not implement structure drop handlers |
| **On failure** | `canDrop` false or mutation returns `null` → no state change |

**Routing inside `applyStructureDrop`:**

| Drag kind | Mutation function |
|-----------|-------------------|
| `palette-layout` | `addLayoutFromPalette` |
| `palette-block` | `addBlockFromPalette` |
| `canvas-block` | `moveCanvasBlock` |
| `canvas-section` | `moveCanvasSection` |
| `canvas-column` | `moveColumnOntoColumn` |

---

## Part C — Layout & column numeric rules

### C.1 Global constants

| Constant | Value | Effect |
|----------|-------|--------|
| `MAX_LAYOUT_COLUMNS` | **3** | Cannot add/duplicate column past 3 per section |
| `MIN_LAYOUT_SECTIONS` | **1** | Cannot delete last layout section |
| `LAYOUT_BLOCK_TYPES` | `one-column`, `two-column`, `three-column` | Only these from palette layout drag |

**Code:** `formStructure.ts`.

### C.2 Column ID suffix rules

| Column count | Virtual suffixes | Example |
|--------------|------------------|---------|
| 1 | `-single` | `section-abc-single` |
| 2 | `-left`, `-right` | `section-abc-left` |
| 3 | `-left`, `-middle`, `-right` | `section-abc-middle` |

Blocks use `parentId = columnId`. **No block-in-block nesting.**

### C.3 Section/column mutation rejection codes

From `sectionColumnMutations.ts`:

| Code | When |
|------|------|
| `max_columns` | Add/duplicate column when section already has 3 |
| `min_columns` | Remove column when only 1 remains (clears content instead of deleting section) |
| `min_sections` | Delete section when only 1 layout section remains |
| `invalid_section` | Target section missing or not a layout element |

### C.4 Cross-section column drag (implicit layout morphing)

When `canvas-column` drops on another section’s `column` (`moveColumnOntoColumn` / `moveColumnAcrossSections`):

| Target section columns | Behavior |
|------------------------|----------|
| 1 column | Becomes **2-column**; dragged content merged |
| 2 columns | Becomes **3-column** |
| 3 columns | **Replaces** target column content |

Source section **downgrades** column count (3→2, 2→1) after move.

---

## Part D — Palette & block instance rules

### D.1 Palette visibility

| Rule | Detail |
|------|--------|
| Blocks panel lists | Only types with `hasPaletteConfig()` in block registry |
| Layout in panel | **Only `one-column`** shown in basic palette (two/three-column exist in registry but not in default panel list) |
| `section` layout type | In `layoutBlockPalette.ts` but **`addLayoutFromPalette` ignores** it (`isLayoutBlockType` excludes `section`) |

### D.2 `maxInstances` (palette drag disabled only)

| Block type | Max | Enforced on duplicate? |
|------------|-----|------------------------|
| `email-input` | 1 | **No** — duplicate canvas action not gated |
| `spinner` | 1 | **No** |
| `cross` | 1 | **No** |

**Code:** `registerBuilderBlocks.ts`, `FormBlock.tsx`. **Not** checked in `applyStructureDrop`.

### D.3 Store-connected gates

| Block type | Rule |
|------------|------|
| `product` | Palette **drop** blocked if store not connected (`pbnStoreBlocks.ts`, `commitDrop`) |
| `coupon` | Store block type but **not** drop-gated |

---

## Part E — Non–react-dnd reorder rules (same mutations)

### E.1 Layers panel (HTML5 drag)

| Draggable | Mutation | Limits |
|-----------|----------|--------|
| Section rows | `reorderRootElements` | Any section on any section |
| Block rows (in column) | `reorderBlockInColumn` | Same `parentColumnId` only |
| Column rows | **Not draggable** | Column moves only via canvas `canvas-column` |

### E.2 Section properties panel

| Action | Mutation | UI guard |
|--------|----------|----------|
| Reorder columns (SortableJS) | `reorderColumnsInSection` | Only when `columnCount > 1` |
| Add column | `addColumnToSection` | Disabled at `columnCount >= 3` |
| Remove column | `removeColumnFromSection` | Disabled at `columnCount <= 1` |
| Column width trait | min **10%** per column | Not a DnD rule |

---

## Part F — Data model invariants (not drag rules, but affect moves)

| # | Invariant |
|---|-----------|
| 1 | Single flat `formElements[]`; order in array = render order |
| 2 | Sections: `isLayout: true`, live at root of array |
| 3 | Blocks: `parentId` = virtual column id (or cleared for root specials) |
| 4 | Selection hierarchy: block → column → section (`resolveCanvasParentSelectionId`) |
| 5 | Special root blocks: `cross`, root-level `spinner` rendered outside column flow in `FormCanvas` |

---

## Part G — Known gaps & inconsistencies

| # | Issue | Risk |
|---|-------|------|
| 1 | `root` drop allowed in `canDrop` but **no UI drop zone** | Dead matrix rows |
| 2 | `maxInstances` only on palette drag | Duplicate / paste can exceed limit |
| 3 | `section` layout type in palette data but not in `addLayoutFromPalette` | Silent no-op if ever shown |
| 4 | No unit tests for `canDrop` / `applyStructureDrop` | Regressions undetected |
| 5 | **No backend validation** of `builderState` | Crafted API payload could save invalid tree + empty HTML |
| 6 | `.cursor/rules/drag-drop-orchestration-kb.mdc` is **Email Builder** only | Do not use for Popup Builder |

---

## Part H — File index

| Concern | Path |
|---------|------|
| **DnD matrix + mutations** | `adflipr-app/.../structure/formStructure.ts` |
| Column/section CRUD | `adflipr-app/.../structure/sectionColumnMutations.ts` |
| react-dnd | `adflipr-app/.../dnd/pbnStructureDnd.tsx` |
| Drop zones | `adflipr-app/.../canvas/PbnDnDZones.tsx` |
| Block drop shell | `adflipr-app/.../blocks/shared/CanvasBlockShell.tsx` |
| Palette drag | `adflipr-app/.../FormBlock.tsx` |
| Store gate | `adflipr-app/.../shared/pbnStoreBlocks.ts` |
| Block registry / maxInstances | `adflipr-app/.../blocks/registerBuilderBlocks.ts` |
| Layers reorder | `adflipr-app/.../LayerPanel.tsx` |
| **Backend save** | `service/.../PopupService.java`, `UpdatePopupRequest.java` |
| **Metadata keys** | `service/.../popup/PopupMetadataKeys.java` |
| **Embed trigger rules** | `service/.../popup/PopupEmbedTriggerSupport.java` |
| **Metadata merge** | `service/.../popup/PopupMetadataMerge.java` |

---

## Related docs

- [`popup-builder-structure-dnd.md`](./popup-builder-structure-dnd.md) — how to change DnD safely  
- [`popup-builder-frontend-kt.md`](./popup-builder-frontend-kt.md) — editor architecture  
- [`popups-api.md`](./popups-api.md) — REST API  
- [`popup-embed-production-bugs.md`](./popup-embed-production-bugs.md) — open embed/runtime issues (PEB-068 empty html, etc.)
