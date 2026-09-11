# Component & Trait Patterns

**Role:** Copy-paste **snippets** and short “how we do X.” Deep contracts live in **`docs/EMAIL_BUILDER_ARCHITECTURE.md`**, **`docs/EMAIL_BUILDER_TRAITS.md`**, and `.cursor/rules/*.mdc` pointers.

**KB workflow:** To add a pattern from a refactor, use `ai/prompts/kb-extraction.md` → `kb-update-suggestion.md` → `kb-verification.md` (no auto-write). Workbook: `ai/docs/kb-updater-workbook.md`.

**Related:** `guardrails.md` (must-not rules), `anti-patterns.md` (symptoms + fixes), `decisions.md` (ADR-lite).

**References:** `.cursor/rules/traits-contract-kb.mdc`, `grapesjs-lifecycle-events-kb.mdc`, `docs/EMAIL_BUILDER_ARCHITECTURE.md`.

## Pattern: Attribute-scoped hydration

Bind per-attribute listeners so hydration runs only for changed attributes:

```ts
// In component init (or block registration)
HYDRATION_ATTR_KEYS.forEach((attr) => {
  this.on(`change:attributes:${attr}`, () => {
    const runtime = getEditorRuntime();
    if (runtime?.templateLoadInProgress) return;
    selectiveHydrate(this, editorRef, { changes: { attributes: { [attr]: this.get(attr) } }, source: 'change:attributes' });
  });
});
```

Do **not** use a single `change:attributes` plus full attribute diff in hot paths.

## Pattern: Trait writes attribute only

```ts
// In trait createInput / handler
const handleChange = (value: string) => {
  const current = String(component.getAttributes()['data-foo'] ?? '');
  if (current === value) return;
  component.addAttributes({ 'data-foo': value });
  markTemplateAsChanged(editor);
};
// Never: component.hydrate() or component.setStyle()
```

## Pattern: React trait cleanup

Any trait that mounts React or adds listeners must register cleanup:

```ts
(trait as TraitWithCleanup).__cleanup = () => unmountReactComponent(container);
registerTraitCleanup(() => (trait as TraitWithCleanup).__cleanup?.());
```

## Pattern: Style guards before setStyle

In hydration, avoid redundant writes:

```ts
setStyleIfChanged(component, { 'padding-top': `${top}px` });
// Or: if (currentStyle !== newStyle) component.setStyle(...);
```

## Pattern: Editor runtime guard

When code may run without an editor (e.g. templates list):

```ts
const runtime = getEditorRuntime();
if (!runtime?.editor) return;
// Or: if (!editor?.Canvas?.getBody) return;
```

## Pattern: Section-table structure

Section: `section-table` (table) → `tbody` → `tr.columns-row` → `td.column` and `td.column-gap-cell`. Padding on section-table only; attributes `data-padding` / `data-padding-mobile`. Same selectors in canvas, html-cleaner, and preview.
