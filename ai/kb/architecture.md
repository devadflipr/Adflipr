# Email Builder — Architecture (KB)

**Canonical source:** `docs/EMAIL_BUILDER_ARCHITECTURE.md` and `.cursor/rules/email-builder-typescript-architecture-kb.mdc`.

## Pipeline (single source of truth)

```
Trait UI  →  Component attributes (data-*)  →  change:attributes  →  Hydration  →  Inline + media styles  →  Serialized HTML  →  Preview
```

- **Traits** write attributes only. No `setStyle`, `addStyle`, or `hydrate()` in trait handlers.
- **Hydration** reads attributes and applies styles. Never reads editor device; applies base + responsive overrides. No DOM reads, no timers.
- **Preview** consumes serialized HTML/CSS only. No editor access.

## Principles

1. **Model is the single source of truth.** DOM is never source of truth. Correct flow: attributes → hydrate → styles.
2. **Hydration is pure projection.** Read attributes only; write styles only. No trait/child mutation, no device read, no DOM read.
3. **Device is view concern only.** Device toggle affects canvas viewport only. Hydration does not branch on device.
4. **Registries are centralized.** Only HydrationRegistry, ResponsiveStylesRegistry, TraitRenderRegistry. No `editor.__*`, no `window.globalRegistry`.

## Event ownership

| Event / prefix   | Owner     | Where to register   |
|------------------|-----------|----------------------|
| `change:*`       | Component | `init()`             |
| `component:*`    | Editor    | Editor bootstrap     |
| `device:*`, `storage:*` | Editor | Editor bootstrap |

Do not register `component:*` or editor-level events inside component `init()`.

## Editor runtime state

Editor-scoped state lives in **EditorRuntime** (`utils/editorRuntime.ts`). Use `getEditorRuntime()` / `getOrCreateEditorRuntime(editor)`. Do **not** mutate `editor.__*`. See `.cursor/rules/editor-runtime-store-kb.mdc`.

## One-line rule

**Components react. Editors orchestrate. Attributes decide. Styles follow.**
