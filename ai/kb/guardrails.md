# Guardrails — What Not to Do

**Supporting:** `.cursor/rules/grapesjs-lifecycle-events-kb.mdc`, `traits-contract-kb.mdc`, `email-builder-typescript-architecture-kb.mdc`.

## Block / component

- Block model must **not** manipulate DOM directly.
- **No** direct `view.el.style`, no `getComputedStyle` for state. Styling only via GrapesJS APIs from persisted attributes.
- **Never** register editor events (`component:*`, `device:*`, `load`, `storage:*`) inside component `init()`.
- **init() must NOT:** trigger async work, bind editor-level events, mutate DOM, depend on timing, hydrate visual state.

## Traits

- Traits must be implemented through **TraitManager**. No ad-hoc DOM or style mutation from traits.
- Trait **writes attributes only**. Trait MUST NOT: call `hydrate()`, call `component.setStyle()`, call `component.set('content', …)`, call `applyMediaStyle`, bind editor-level events.
- Traits that create React roots or listeners **must** call `registerTraitCleanup()` so panel teardown unmounts them.
- **Trait writes attributes. Hydration reacts.** Never `component.addAttributes(...)` then `component.hydrate()` from the trait.

## Hydration

- Hydration must **never** read editor device or DOM. No `editor.getDevice()` in hydration.
- Hydration must **never** mutate component structure (no `set('content')`, `append`, `components().reset`, `replaceWith` inside hydrate).
- Content projection (HTML/text) runs only on attribute events (e.g. `change:attributes:data-rte-html`), not inside hydrate.
- Use **attribute-scoped** events (`change:attributes:<attr>`) and chunked hydration; avoid full-scan attribute diff in hot paths.

## Editor & runtime

- **No** `editor.__*` or global runtime objects. Use `getEditorRuntime()` / `getOrCreateEditorRuntime(editor)` only.
- **No** `editor: unknown` where GrapesJS types apply; use `Editor`, `Component`, etc. from grapesjs.
- UI logic separated from block logic; editor owns orchestration.

## Event domain separation

- Structural events (`component:update`, `component:style:update`, `component:add`/`remove`) must **never** trigger UI sync events (e.g. `rte:update`).
- High-frequency model events must not drive expensive UI recalculation.
