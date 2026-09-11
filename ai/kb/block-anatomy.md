# Block Anatomy — GrapesJS Email Builder

When refactoring or adding blocks, the AI should understand this structure.

## Conceptual structure

```
Block
 ├ model      — attributes (data-*), traits list, no DOM
 ├ view       — rendering; avoid direct DOM mutation from model
 ├ traits     — registered in TraitManager; write attributes only
 └ events     — change:* on model; component:* on editor (bootstrap only)
```

## Model

- Holds **attributes** (e.g. `data-padding`, `data-rte-html`). These are the single source of truth.
- May define default `traits` (trait names). TraitManager resolves them to UI.
- **Must not** manipulate DOM. No `view.el`, no `getComputedStyle` in model logic.

## View

- Renders the component in the canvas. GrapesJS manages creation/destruction.
- **Avoid** direct DOM mutation for state that should persist; that belongs in model attributes + hydration.

## Traits

- Registered per block type via TraitManager (`registerTraits` → `register*Trait`).
- Each trait type has `createInput({ trait })` returning a DOM node (or React root mounted in a node).
- **Trait responsibility:** read current value, write via `component.addAttributes(...)`, optionally call `markTemplateAsChanged(editor)`.
- **Cleanup:** If the trait mounts React or adds listeners, it must call `registerTraitCleanup(...)` so the panel can tear down correctly.

## Events

- **Model-scoped (`change:*`):** Registered in component `init()`. Used for hydration (e.g. `change:attributes:data-padding` → run padding chunk of selectiveHydrate).
- **Editor-scoped (`component:*`, `device:*`, `storage:*`):** Registered **once** at editor bootstrap (e.g. in `emailBuilderEventHandlers.ts`). Never in `init()`.

## Hydration

- **Owned by** the style/hydration layer (e.g. `*Style.ts` or block-specific style modules).
- **Triggered by** attribute changes (and optionally clone/load/device at editor level).
- **Does:** read attributes → compute styles → `component.setStyle()` / `applyMediaStyle()`.
- **Does not:** read DOM, read device, mutate traits, mutate children, or call `set('content')`.

## Flow summary

1. User changes trait UI → trait calls `component.addAttributes({ ... })`.
2. Model emits `change:attributes` (or attribute-scoped `change:attributes:<attr>`).
3. Hydration listener runs → `selectiveHydrate(component, editor, { changes: { attributes } })`.
4. Hydration reads attributes, applies styles. No DOM read, no device read.

Understanding this anatomy keeps refactors consistent: model = state, traits = attribute writers, hydration = attribute → style projection, events = model vs editor ownership.
