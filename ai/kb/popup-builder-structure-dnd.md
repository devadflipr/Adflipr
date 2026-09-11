# Popup Builder — structure drag & drop

**Role:** How moving blocks, sections, and columns works in the form builder canvas. Read this before changing drag/drop or tree order.

**Code (3 files):**

| File | What it does |
|------|----------------|
| `adflipr-app/src/layouts/PopupBuilder/components/form-builder/structure/formStructure.ts` | Rules + all tree changes (add, move, reorder) |
| `adflipr-app/src/layouts/PopupBuilder/components/form-builder/dnd/pbnStructureDnd.tsx` | react-dnd hooks + provider (drag UI → calls structure layer) |
| `adflipr-app/src/layouts/PopupBuilder/components/form-builder/canvas/PbnDnDZones.tsx` | Canvas drop zones (column, section, root) — must be real React components (hooks) |

**Page-level:** `DndProvider` from react-dnd already wraps Popup Builder in `EditorPage` / `Index`. Canvas edits also use `PbnStructureDndProvider` inside `FormCanvas`.

---

## Simple mental model

1. User drags something (palette block, canvas block, section, or column).
2. Drop zone asks: **“Is this allowed?”** → `canDrop()` in `formStructure.ts`.
3. On drop: **one function** runs → `applyStructureDrop()`.
4. That function calls a **pure mutation** (move block, add section, reorder columns, etc.).
5. Result goes to `onFormElementsChange` (undo snapshots stay on that path).

**Rule:** UI components do **not** implement their own drop logic. They only declare **what** is being dragged and **where** it can land.

---

## What you can drag (drag kinds)

| Kind | Example |
|------|---------|
| `palette-block` | Text input from blocks panel |
| `palette-layout` | One / two / three column section from blocks panel |
| `canvas-block` | Any block already on the canvas (grip on selection chrome) |
| `canvas-section` | Whole section row (grip on section chrome) |
| `canvas-column` | Column (grip on column chrome) |

---

## Where you can drop (target kinds)

| Target | Used for |
|--------|----------|
| `root` | Empty canvas / between root-level items |
| `section-edge` | Before/after a section (layout row) |
| `column` | Into a column (empty or with blocks); also column-on-column for reorder |
| `block` | Before/after another block in a column (or root block) |

**Rule matrix:** `canDrop(dragKind, targetKind)` in `formStructure.ts`. Change rules there only — not in block components.

### Nested hit targets (zero padding)

DOM nests `section-edge` → column → block. When section padding is `0`, the column fills the section, so `monitor.isOver({ shallow: true })` prefers the child. Structure drags (`palette-layout`, `canvas-section`) are only allowed on `section-edge` / `root`, so they fail if the column wins.

**Fix (geometry, not matrix):** during a structure drag, column and block drop nodes set `pointer-events: none` (`useStructureDragPassThroughStyle`) so hit-testing reaches `SectionDropShell`. Do **not** work around this by allowing layout/section drops on `column`/`block` and remapping to section edges.

---

## Drop position

Pointer position on the target gives:

- `before` — insert above
- `after` — insert below
- `inside` — append into column (middle of column zone)

Computed in `pbnStructureDnd.tsx` (top/bottom 25% of target height).

---

## Data model (short)

- Form is a flat `formElements[]` array.
- Sections are layout elements (`isLayout: true`).
- Blocks live in columns via `parentId` = column id (e.g. `section-123-left`).
- Column ids are **virtual** suffixes: `-single`, `-left`, `-middle`, `-right`.
- Section order = order of layout elements in the root array.
- Block order within a column = order of elements with the same `parentId` in the array.

---

## Who uses what

| Surface | How it moves things |
|---------|---------------------|
| **Canvas** | react-dnd → `applyStructureDrop` |
| **Blocks panel** | react-dnd drag only (`FormBlock` + `usePbnDragSource`) |
| **Layers panel** | HTML5 drag for reorder; calls `reorderRootElements` / `reorderBlockInColumn` (same mutations, no react-dnd) |
| **Section trait panel** | SortableJS on column list → `reorderColumnsInSection` |

Layers / traits skip react-dnd but still use **structure mutations** — not local splice logic in the panel.

---

## Main exports (when editing)

From `formStructure.ts`:

- `applyStructureDrop` — only entry for canvas/palette drops
- `canDrop` — validation
- `reorderColumnsInSection` — column order (canvas + trait panel)
- `reorderRootElements` / `reorderBlockInColumn` — layers panel
- `getColumnIds`, `parseColumnId`, `isColumnId` — column helpers

From `pbnStructureDnd.tsx`:

- `PbnStructureDndProvider` — wrap editable canvas
- `usePbnDragSource` / `usePbnDropTarget` — attach to DOM
- `usePbnStructureDndOptional` — read drop indicator / preview flag
- `useStructureDragPassThroughStyle` — `pointer-events: none` on nested zones during structure drag
- `isStructureDragKind` — `palette-layout` | `canvas-section`

From `PbnDnDZones.tsx`:

- `ColumnDropZone`, `SectionDropShell`, `PbnCanvasRootZone`

---

## Do / don’t

**Do**

- Add new move behavior in `formStructure.ts` + wire through `applyStructureDrop`.
- Add a new allowed drag/target pair in `canDrop` only.
- Use separate components (`PbnDnDZones`) when you need hooks inside a list/map.
- Keep section-edge hit-testing reachable when merchant padding is zero (pass-through nested zones during structure drag).

**Don’t**

- Add `onDrop` / `dataTransfer` handlers on individual block types for structure moves.
- Duplicate column reorder logic (trait, canvas, layers should share mutations).
- Call `useDrag` / `useDrop` inside a plain helper function — use a component.
- Allow structure drops on `column`/`block` just to work around nested shallow hover — fix hit geometry instead.

---

## If something breaks

1. Check `canDrop` — is this drag/target pair allowed?
2. Check `applyStructureDrop` — does it route to the right mutation?
3. Check `parentId` / column id after move — especially cross-section column moves.
4. Confirm editable canvas is inside `PbnStructureDndProvider` (preview mode skips it).

---

## Related

- [`popup-builder-rules-inventory.md`](./popup-builder-rules-inventory.md) — full rule catalog (DnD matrix, limits, backend gap analysis)
- `.cursor/rules/popup-builder-architecture-kb.mdc` — general Popup Builder architecture
- `ai/kb/popups-api.md` — popup API (not canvas DnD)
