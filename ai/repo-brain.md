# Repo brain — Email Builder in one page

Compressed system overview so the AI can reason about refactors and fixes quickly.

## What the Email Builder is

- **Stack:** React app (adflipr-app) + GrapesJS editor for drag-and-drop email templates. Backend (service), Shopify (shopify), WordPress (wordpress) are separate.
- **Core idea:** The **model** (component attributes) is the single source of truth. **Traits** write attributes. **Hydration** projects attributes → styles. **Preview** and export use serialized HTML/CSS. No DOM or device as source of truth.

## Pipeline (canonical)

```
Trait UI → attributes (data-*) → change:attributes → Hydration → styles → HTML/CSS → Preview
```

## Who owns what

| Thing            | Owner     | Where                         |
|------------------|-----------|-------------------------------|
| Attributes       | Model     | component.get/set attributes  |
| change:*         | Component | init()                        |
| component:*, device:*, storage:* | Editor | Bootstrap (e.g. emailBuilderEventHandlers) |
| Hydration        | Style layer | *Style.ts, selectiveHydrate |
| Runtime state    | EditorRuntime | getEditorRuntime(), editorRuntime.ts |

**Rule:** Never register editor events in component `init()`. Never mutate DOM for persisted state. Never use `editor.__*`.

## Key files (adflipr-app)

- **Editor init / events:** `EmailBuilder.tsx` (init), `emailBuilderEventHandlers.ts` (component:selected, storage, device, etc.), `emailBuilderConfig.ts` (GrapesJS config).
- **Runtime:** `utils/editorRuntime.ts` — singleton runtime (editor, responsiveRegistry, RTE state, etc.). Use getEditorRuntime() / getOrCreateEditorRuntime(editor).
- **Traits:** `components/GrapesJSComponents/traits/registerTraits*.ts(x)` — TraitManager registration. Each trait type: createInput, optional __cleanup; registerTraitCleanup() for React/listeners.
- **Hydration:** `utils/hydrationManager.ts`, `utils/*Style.ts` (e.g. textBlockStyles, columnStyle, sectionStyle), `utils/responsiveStylesUtils.ts`. selectiveHydrate(component, editor, options).
- **Blocks:** `components/GrapesJSComponents/customBlocks/*.ts` — block definitions, model defaults, init() with change:attributes or attribute-scoped listeners.
- **Preview:** `PreviewModal.tsx`, `utils/html-cleaner.ts`, `buildFullEmailDocument` — serialized HTML/CSS only; no editor in preview path.

## Guardrails (must not)

- Model or trait: no DOM mutation, no hydrate(), no setStyle()/addStyle() in trait.
- Hydration: no DOM read, no editor.getDevice(), no set('content') or structure mutation.
- Events: no component:* in init(); no structural events triggering rte:update.
- Runtime: no editor.__*; use EditorRuntime accessors only. Only three registries (Hydration, ResponsiveStyles, TraitRender).

## When refactoring

1. Read `ai/kb/architecture.md` and `guardrails.md`.
2. Apply `ai/skills/refactor-block.md` (or the right skill).
3. Verify with `ai/verification/architecture-checklist.md`.
4. Fix violations, then ship.

## One line

**Components react. Editors orchestrate. Attributes decide. Styles follow.**
