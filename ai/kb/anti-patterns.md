# Anti-patterns — Email Builder & editor

**Purpose:** Common mistakes, symptoms, and fixes. **Rules** live in `guardrails.md`; **snippets** in `patterns.md`. **Full narrative** in `docs/EMAIL_BUILDER_ARCHITECTURE.md`, `docs/EMAIL_BUILDER_TRAITS.md`.

**KB workflow:** When you learn a new anti-pattern from a refactor, use `ai/prompts/kb-extraction.md` → `kb-update-suggestion.md` → `kb-verification.md` before editing this file. See `ai/docs/kb-updater-workbook.md`.

---

## Anti-pattern: Trait calls `hydrate()` after `addAttributes`

- **Symptom:** Double style application, CPU spikes on trait change, flicker; logs show hydration twice per click.
- **Bad:** `component.addAttributes({ … }); component.hydrate();` in the same trait handler.
- **Instead:** One `addAttributes` (with equality guard); let `change:attributes` / key-scoped listeners run hydration. See `patterns.md` (trait writes attribute only) and `docs/EMAIL_BUILDER_TRAITS.md`.

---

## Anti-pattern: Global `change:attributes` → full hydrate

- **Symptom:** Any attribute tweak rebuilds the whole block; Trait Flow RCA-style CPU cost.
- **Bad:** `this.on('change:attributes', () => fullHydrate(this))` without narrowing.
- **Instead:** `change:attributes:data-<key>` per hot attribute; `selectiveHydrate` with `changes.attributes`. See `patterns.md` (attribute-scoped hydration).

---

## Anti-pattern: Manual trait panel on `component:selected`

- **Symptom:** Duplicate trait DOM, missing traits on reselect, fighting GrapesJS TraitManager.
- **Bad:** Clearing trait container, `handleTraitManager` + `TraitManager.render()` in the main selection path for normal blocks.
- **Instead:** Traits on the model (`defaults.traits` or `set('traits', …)` in `init()` / `change:components`). See `docs/EMAIL_BUILDER_TRAITS.md` (*Trait panel and model*).
