# Popup Builder — Block Registry Contract & Plan

**Product:** Adflipr Popup Builder  
**Status:** Phase 3 builder registry **complete** (Waves 3.0–3.9)  
**Last updated:** 2026-06-27  

**Related KB:**

- [`popup-html-serialization.md`](popup-html-serialization.md) — export pipeline, sanitization, multi-step HTML
- [`popup-runtime-v2-design.md`](popup-runtime-v2-design.md) §9 — embed **runtime** block init registry (M3)
- [`popup-embed-contract.md`](popup-embed-contract.md) — shared `data-adflipr-*` attrs
- [`popup-builder-structure-dnd.md`](popup-builder-structure-dnd.md) — layout/column DnD (separate from content blocks)
- [`popup-html-serializer-styles.md`](popup-html-serializer-styles.md) — export CSS layout rules

**Code anchors (today):**

| Layer | Primary file(s) |
|-------|-----------------|
| Block id | `components/form-builder/types.ts` → `BlockType` |
| Palette | `components/form-builder/blocks/palette.ts` + `registerBuilderBlocks.ts` |
| Canvas | `components/form-builder/canvas/FormElementRenderer.tsx` → `blocks/registry.ts` |
| Properties | `property-panels/ElementPropertiesPanel.tsx` → `blocks/registerBuilderBlockProperties.ts` |
| Serializer | `utils/popupHtmlSerializer/serializeElements.ts` |
| Embed runtime | `embed/runtime.ts`, `embed/spinner.ts`, `embed/timerFit.ts`, `embed/video.ts` |
| Contracts | `contract/popupEmbedContract.ts`, `contract/popupSpinnerContract.ts` |

---

## 1. Executive summary

Popup blocks today are implemented as **parallel `switch (type)` trees** in the builder, serializer, and embed runtime. That works at ~20 block types but does not scale cleanly to 40–80+.

**Decision:** adopt a **Block Registry** pattern — **one stable block id** (`BlockType`) with **layer-specific hooks**, not one monolithic switch statement.

**Not in scope:** replacing the Popup Builder data model, JSON schema renderer, or Email Builder’s GrapesJS block system. Popup pipeline remains:

```
FormElement model (attributes)
  → Builder canvas (React)
  → Serializer (HTML string)
  → Embed runtime (DOM behavior on storefront)
```

---

## 2. Problem statement

Adding or changing block `X` currently requires touching multiple places manually:

| Concern | Must update |
|---------|-------------|
| Palette / drag | `FormBlock.tsx` (`BLOCK_CONFIGS`) |
| Canvas render | `FormElementRenderer.tsx` |
| Property panel | `ElementPropertiesPanel.tsx` + `*Properties.tsx` |
| Export HTML | `serializeElements.ts` (+ often `styles/inline/*.ts`) |
| Live behavior | `embed/*.ts` init + `contract/*.ts` attrs |
| Tests | serializer tests, embed runtime tests, preview parity |

**Failure modes without registry discipline:**

- Block appears in builder but **dead on storefront** (missing embed init)
- **Preview ≠ export ≠ live** (canvas/serializer/runtime drift)
- **Merge conflicts** in 2k-line files
- New block shipped with palette + traits but **no serializer**

---

## 3. Design principles

1. **One block id, many hooks** — `BlockType` is canonical; each layer registers what it needs.
2. **Layers stay separate** — builder (React), serializer (string HTML), runtime (DOM + lifecycle) have different shapes; do not force one interface.
3. **Incremental migration** — registry grows block-by-block; no big-bang rewrite.
4. **Contracts before code** — new interactive blocks add `data-adflipr-*` attrs in `contract/` first.
5. **Dispose on step change** — runtime inits must teardown (see runtime v2 §9–10).
6. **Model is source of truth** — same as Email Builder KB: attributes → projection; DOM/export never write back to model.
7. **Layout ≠ content block** — section/column DnD stays in structure pipeline; registry focuses on **content blocks** (+ `cross`).

---

## 4. Block categories

### 4.1 Content blocks (registry scope)

Registered in the block registry:

`text`, `button`, `divider`, `text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, `check-box`, `radio`, `date`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `floating-image`, `cross`

### 4.2 Layout blocks (structure pipeline)

`section`, `one-column`, `two-column`, `three-column` — owned by **structure DnD** + `LayoutRenderer`, not the content block registry. New layout types follow [`popup-builder-structure-dnd.md`](popup-builder-structure-dnd.md).

### 4.3 Special surfaces (not BlockType)

| Surface | Owner |
|---------|--------|
| Teaser chip | `serializeTeaserHtml` (save/embed + fullscreen preview), `TeaserPreview.tsx` (editor canvas), `embed/teaserLayout.ts` + runtime teaser host |
| Form shell / surface | `serializeLayout.ts`, `FormCanvas` |
| Spinner wheel math | `spinnerWheelMath.ts`, `embed/spinner.ts` |

---

## 5. Block definition contract

Each content block SHOULD have a definition record (conceptual; file layout TBD during Phase 1–3):

```ts
/** Canonical block id — must match BlockType union in types.ts */
type PopupBlockId = BlockType;

interface PopupBlockDefinition {
  id: PopupBlockId;

  /** Palette + drag rules (extends today's BLOCK_CONFIGS) */
  palette: {
    label: string;
    category: 'standard' | 'layout' | 'chrome';
    icon?: ComponentType;
    emailBuilderIconSrc?: string;
    maxInstances?: number;
    /** Store-connected gating, form-type restrictions, etc. */
    allowedFormTypes?: FormType[];
    requiresStore?: boolean;
  };

  /** Default element factory when dropped from palette */
  createDefaultElement: (ctx: { stepId: string; parentId?: string }) => FormElement;

  /** Builder — lazy-loaded to keep bundle size down */
  builder?: {
    render: (props: CanvasBlockProps) => ReactNode;
    propertiesPanel: ComponentType<PropertiesPanelProps>;
    layerLabel?: string; // Layer panel display name
  };

  /** Export — pure projection from model */
  serializer: {
    serialize: (element: FormElement, ctx: SerializeContext) => string;
    /** Optional: styles in popupHtmlSerializer/styles/inline/ */
  };

  /** Storefront — only for interactive blocks; omit for static HTML blocks */
  runtime?: {
    selector: string; // e.g. [data-adflipr-popup-spinner="true"]
    init: (root: HTMLElement, ctx: EmbedBlockContext) => Disposable;
  };

  /** Contract attrs — import from contract/*.ts, never duplicate strings */
  contract?: {
    attrs: readonly string[];
  };

  /** Verification */
  tests?: {
    serializer?: string; // describe block or fixture id
    embed?: string;
  };
}
```

### 5.1 Layer minimum requirements

| Block kind | Builder | Serializer | Runtime | Contract |
|------------|---------|------------|---------|----------|
| Static (text, divider, image) | ✓ | ✓ | — | optional |
| Form field (email, dropdown) | ✓ | ✓ | floating-label CSS | optional |
| Interactive (timer, spinner, video) | ✓ | ✓ | **✓ init + dispose** | **✓ required** |
| Chrome (cross) | ✓ | ✓ | close handler | **✓ required** |
| Store (product, coupon) | ✓ | ✓ | optional | optional |

### 5.2 Exhaustiveness rule

When `BlockType` gains a new member:

1. TypeScript **`satisfies Record<BlockType, …>`** (or equivalent) on registry maps MUST fail compile until the block is registered in **palette + serializer** at minimum.
2. CI test: **`resolveExportableStepIds` + serialize** produces non-empty HTML for a snapshot containing the new block.

---

## 6. Layer specifications

### 6.1 Builder registry

**Today:** `BLOCK_CONFIGS` (palette) + giant `FormElementRenderer` switch + `ElementPropertiesPanel` switch.

**Target:**

```
blocks/
  registry.ts              # Map<BlockType, PopupBlockDefinition> (metadata + lazy imports)
  text/
    TextCanvas.tsx
    TextProperties.tsx       # or re-export existing *Properties.tsx
  spinner/
    SpinnerCanvas.tsx
    ...
```

**Dispatch:**

```ts
const def = blockRegistry.get(element.type);
return def.builder.render(props);
```

**Rules:**

- Canvas reads **attributes only**; no embed runtime imports in canvas.
- Preview mode flags (`isPreview`, `onPreviewCrossClick`) passed via shared `CanvasBlockProps`.
- Property panels **mutate `element.properties` / style attrs only** — no DOM-as-truth.

### 6.2 Serializer registry

**Today:** `serializeElement()` switch delegates to `serializeText`, `serializeSpinner`, … (functions already extracted).

**Target:**

```ts
const serializers: Record<BlockType, SerializeFn> = {
  text: serializeText,
  spinner: serializeSpinner,
  // ...
};

export function serializeElement(element, ctx) {
  const fn = serializers[element.type];
  if (!fn) return '';
  return wrapExportBlock(element, ctx, fn(element, ctx));
}
```

**Rules:**

- Inline CSS in `popupHtmlSerializer/styles/inline/` — **not** in `serializeElements.ts` body (see serializer-styles KB).
- Sanitization at serializer boundary (`popupHtmlSanitize`, `sanitizeRichTextHtml`, etc.).
- Device visibility gate stays in `serializeElement` wrapper (shared).

### 6.3 Runtime registry (embed)

**Today:** `initPopupSpinners`, timer fit, video bind — called ad hoc from `runtime.ts` / `bindStepHandlers`.

**Target** (aligns with [`popup-runtime-v2-design.md`](popup-runtime-v2-design.md) §9):

```ts
interface BlockInitializer {
  id: string;
  selector: string;
  init(container: HTMLElement, ctx: BlockContext): Disposable;
}

// StepRenderer.render(html):
//   1. container.innerHTML = html
//   2. disposableStack = registry.initAll(container, ctx)
//   3. on step change / dismiss: stack.dispose() BEFORE next innerHTML
```

**Initial registrars:** `timer`, `spinner`, `video`, `floating-label` (extract from `bindStepHandlers`).

**Rules:**

- Selectors and attr names **only** from `contract/`.
- Idempotent init (`dataset.adfliprPopupSpinnerBound` pattern) until DisposableStack lands.
- No React in embed bundle.

---

## 7. What already exists (do not duplicate)

| Artifact | Role | Registry phase |
|----------|------|----------------|
| `BLOCK_CONFIGS` | Palette metadata | Merge → `palette` section |
| `serialize*` functions | Per-block HTML | Merge → `serializer` map |
| `contract/popupEmbedContract.ts` | Shared attrs | Reference from `contract` section |
| `contract/popupSpinnerContract.ts` | Spinner attrs + tests | Template for new interactive blocks |
| `styles/inline/*.ts` | Export style builders | Colocate with block or keep shared by family |
| `popup-runtime-v2-design.md` §9 | Runtime init registry | **Phase 1** implementation spec |

---

## 8. Phased roadmap

### Phase 0 — Documentation & checklist (now)

- [x] This document
- [x] Detailed execution plan → [`popup-block-registry-phases-0-2.md`](popup-block-registry-phases-0-2.md)
- [x] Popup section in [`ai/verification/code-review-checklist.md`](../verification/code-review-checklist.md)
- [x] Link from [`popup-html-serialization.md`](popup-html-serialization.md) §Related
- [x] MR snippet in phases doc §0.4

**Effort:** hours · **Risk:** none · **Sign-off:** use checklist on next block PR

### Phase 1 — Runtime registry (highest ROI)

**Detailed plan:** [`popup-block-registry-phases-0-2.md`](popup-block-registry-phases-0-2.md) §Phase 1

**Goal:** New interactive blocks register embed init + guaranteed teardown.

**Deliverables:**

- `embed/disposable.ts` + `embed/blockRegistry.ts` + `embed/blockInitializers/*`
- Migrate `spinner`, `timer`, `video`, `floating-label`
- Regression tests for step-change leak (PEB-052, PEB-054, PEB-090)

**Effort:** ~2–3 weeks · **Risk:** medium (storefront) · **Ref:** runtime v2 M3

### Phase 2 — Serializer map

**Detailed plan:** [`popup-block-registry-phases-0-2.md`](popup-block-registry-phases-0-2.md) §Phase 2

**Goal:** Replace `serializeElement` switch with typed map; keep per-block files.

**Deliverables:**

- `popupHtmlSerializer/blockRegistry.ts`
- `Record<ContentBlockType, SerializeFn>` with `satisfies` exhaustiveness
- Exhaustiveness test + golden tests for any coverage gaps

**Effort:** ~1–2 weeks · **Risk:** low (save/export)

### Phase 3 — Builder modularization

**Detailed plan:** [`popup-block-registry-phase-3-plan.md`](popup-block-registry-phase-3-plan.md) — waves 3.0–3.9 (**complete**)

**Goal:** Extract `FormElementRenderer` cases into `blocks/<id>/`; wire property panels.

**Deliverables:**

- Lazy `builder.render` + `builder.propertiesPanel` in registry
- `ElementPropertiesPanel` becomes dispatch-only (~30 lines)
- Shrink `FormElementRenderer.tsx` to router + shared shell helpers

**Effort:** ~4–6 weeks · **Risk:** medium (editor UX) · **Ref:** runtime v2 M3

### Phase 4 — Unified registry file (optional consolidation)

**Goal:** Single `popupBlockRegistry.ts` exporting palette + lazy builder + serializer fn + runtime registrar refs.

**When:** After Phases 1–3 prove the hook shape.

**Effort:** ~1 week · **Risk:** low if incremental

---

## 9. New block checklist

Use this for **every** new content block PR:

### Model & types

- [ ] `BlockType` union updated in `types.ts`
- [ ] Default `FormElement` shape documented (properties + style attrs)
- [ ] `createDefaultElement` (or template snapshot) for palette drop

### Builder

- [ ] Entry in palette registry (`BLOCK_CONFIGS` → future `blockRegistry`)
- [ ] Canvas renderer (or extension of shared field primitive)
- [ ] Properties panel component
- [ ] Layer panel label
- [ ] Store / form-type gating if applicable (`isPbnStoreDropGatedBlockType`)
- [ ] Preview behavior defined (`isPreview`, readonly surface rules)

### Serializer

- [ ] `serializeX()` implemented
- [ ] Registered in serializer map / switch
- [ ] Styles in `styles/inline/` or markup helper — **not** inline in `serializeElements.ts`
- [ ] Sanitization applied (URLs, rich text, custom CSS policy)
- [ ] Mobile + desktop export slices if block is device-sensitive
- [ ] Golden test in `popupHtmlSerializer.test.ts`

### Runtime (if interactive)

- [ ] Contract attrs added to `contract/*.ts`
- [ ] Embed bundle rebuilt (`npm run build:popup-embed`)
- [ ] Registrar with `init` + `dispose`
- [ ] Test in `popupEmbedRuntime.test.ts`
- [ ] Step transition / submit side effects documented

### Cross-cutting

- [ ] i18n keys for user-visible palette/panel strings (all 7 locales)
- [ ] [`popup-html-serialization.md`](popup-html-serialization.md) block table row updated
- [ ] Preview ≈ export ≈ live verified manually or via test

---

## 10. Anti-patterns

| Do not | Do instead |
|--------|------------|
| Add case to switch without serializer | Checklist §9 — serializer required |
| Duplicate `data-adflipr-*` strings | Import from `contract/` |
| Import embed runtime in builder canvas | Serialize attrs; init only in embed |
| Grow `FormElementRenderer` with 200+ line inline case | Extract `blocks/<id>/` module |
| Read DOM in serializer | Read `element.properties` only |
| Init embed listeners without teardown plan | `Disposable` + registry Phase 1 |
| One 3000-line registry file with all implementations | Lazy imports per block folder |

---

## 11. Success metrics

| Metric | Target |
|--------|--------|
| Lines in `FormElementRenderer.tsx` | ↓ 50% after Phase 3 |
| `serializeElement` switch body | 0 lines (map dispatch only) after Phase 2 |
| New block touch count | ≤1 folder + registry entry + tests (not 5+ scattered switches) |
| Preview/export/live drift bugs | ↓; caught by per-block golden tests |
| Embed listener leaks on step change | 0 open PEBs for block init teardown |

---

## 12. Explicit non-goals

- Replacing HTML export with JSON schema rendering
- Unifying Popup and Email Builder block registries (different runtimes)
- Shadow DOM / iframe isolation (runtime v2 non-goal)
- Backend-driven block definitions
- Auto-generating property panels from JSON Schema (future research only)

---

## 13. Open questions (team decisions)

1. **Registry file location:** **`components/form-builder/blocks/`** — see [`popup-block-registry-phase-3-plan.md`](popup-block-registry-phase-3-plan.md) §2 D1 (sign-off before coding).
2. **Shared field primitives:** Should `text-input` / `email-input` share one `InputFieldCanvas` registry entry with variant prop?
3. **Exhaustiveness enforcement:** `satisfies Record<BlockType, …>` at build time vs CI script that diffs `BlockType` vs registry keys.
4. **Phase ordering:** Confirm runtime (Phase 1) before serializer map if gamification/spin blocks are top priority.

---

## 14. References

- Email Builder block anatomy (conceptual parallel): [`block-anatomy.md`](block-anatomy.md)
- Spin-to-win BRD: [`spin-to-win-brd.md`](spin-to-win-brd.md)
- Production embed bugs: [`popup-embed-production-bugs.md`](popup-embed-production-bugs.md)
- Cursor rule (serializer layout): `.cursor/rules/popup-html-serializer-styles.mdc`
- Cursor rule (builder architecture pointer): `.cursor/rules/popup-builder-architecture-kb.mdc`
