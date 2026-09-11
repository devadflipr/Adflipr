# Architecture checklist

Use this after refactors, new blocks, or trait/hydration changes. Fix violations before returning final code.

## Model & block

- [ ] Block model does **not** manipulate DOM directly (no `view.el.style`, no `getComputedStyle` for state).
- [ ] State lives in **attributes**; styling is applied via hydration, not in model or trait.
- [ ] Component `init()` does **not** register editor events (`component:*`, `device:*`, `storage:*`, `load`).
- [ ] Component `init()` does **not** trigger async work, DOM mutation, or timing-dependent correctness.

## Traits

- [ ] Traits use **TraitManager** (registered via `registerTraits` / `register*Trait`).
- [ ] Trait handlers only call **`component.addAttributes(...)`** (and optionally `markTemplateAsChanged`).
- [ ] Traits do **not** call `hydrate()`, `setStyle()`, `addStyle()`, `set('content')`, or `applyMediaStyle`.
- [ ] Traits that mount React or add listeners call **`registerTraitCleanup(...)`** so panel teardown unmounts them.
- [ ] Equality guard used where appropriate to avoid redundant attribute writes.

## Hydration

- [ ] Hydration **reads** attributes only and **writes** styles only (pure projection).
- [ ] Hydration does **not** read editor device or DOM.
- [ ] Hydration does **not** mutate component structure (no `set('content')`, `append`, `components().reset`, `replaceWith` in hydrate).
- [ ] Attribute-scoped or chunked hydration used where applicable; no full attribute scan in hot paths if avoidable.
- [ ] Style guards (e.g. `setStyleIfChanged`) used to avoid redundant `setStyle` writes where applicable.

## Events

- [ ] **Editor-level** events (`component:selected`, `component:add`, `device:change`, etc.) registered **once** at editor bootstrap (e.g. `emailBuilderEventHandlers.ts`), not in component `init()`.
- [ ] **Model-level** events (`change:attributes`, `change:attributes:<attr>`) registered in component `init()` or equivalent; used for hydration only.
- [ ] Structural events do **not** trigger UI sync (e.g. `component:update` does not trigger `rte:update`).

## Editor runtime

- [ ] No **`editor.__*`** or global runtime objects. Use **`getEditorRuntime()`** / **`getOrCreateEditorRuntime(editor)`** only.
- [ ] Editor/component typed appropriately (no `editor: unknown` where GrapesJS types apply).
- [ ] Code that may run without editor (e.g. templates list) guards on `getEditorRuntime()` or `editor?.Canvas` before use.

## Registries & style

- [ ] Only **three** registries used: HydrationRegistry, ResponsiveStylesRegistry, TraitRenderRegistry.
- [ ] Responsive styles use **ResponsiveStylesRegistry** from editor runtime; component class = `component-${component.getId()}`.
- [ ] Section-table structure and selectors consistent with `ai/kb` and existing canvas/html-cleaner/preview.

---

**One-line rule:** Components react. Editors orchestrate. Attributes decide. Styles follow.
