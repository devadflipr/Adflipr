# Skill: Refactor GrapesJS block to match project architecture

**Goal:** Refactor a GrapesJS block (or trait) so it follows Email Builder KB: no DOM mutation from model, traits write attributes only, hydration is pure projection, events owned by editor or model correctly.

## Steps

1. **Identify block model and view**
   - Locate the block definition (e.g. in `customBlocks/`). Identify model defaults, `init()`, and any view or DOM access.

2. **Remove DOM mutation from model**
   - Ensure the block model does not call `view.el`, `getComputedStyle`, or mutate DOM. State must live in attributes; styling via hydration.

3. **Move trait behavior to attribute-only**
   - Traits must only call `component.addAttributes(...)` (and optionally `markTemplateAsChanged`). Remove any `component.hydrate()`, `setStyle()`, `set('content')`, or `applyMediaStyle` from trait handlers.

4. **Bind attribute-scoped hydration**
   - Prefer `change:attributes:<attr>` listeners that call `selectiveHydrate` with the changed attribute. Do not register `component:*` or editor events in `init()`.

5. **Register traits with TraitManager**
   - Ensure block traits are registered via `registerTraits` (or the appropriate `register*Trait`). React-based traits must call `registerTraitCleanup()`.

6. **Editor event compatibility**
   - Confirm no editor-level events are registered inside the block’s `init()`. Use `getEditorRuntime()` (not `editor.__*`) for any runtime state.

7. **Verify**
   - Run verification against `ai/verification/architecture-checklist.md` and fix violations before returning final code.

## References

- `ai/kb/architecture.md`
- `ai/kb/guardrails.md`
- `ai/kb/patterns.md`
- `ai/kb/block-anatomy.md`
