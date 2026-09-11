# Code review checklist

Policy layer for the AI code reviewer. Use with `ai/verification/architecture-checklist.md` when reviewing a change. Tick what was verified.

## Architecture

- [ ] Follow **architecture.md** (pipeline, event ownership, runtime). See `ai/kb/architecture.md`.
- [ ] All items in **architecture-checklist.md** applied (model, traits, hydration, events, runtime, registries).
- [ ] No **direct DOM manipulation** in blocks; state in attributes, styling via hydration.
- [ ] Traits implemented via **TraitManager**; traits write attributes only, no `hydrate()` or `setStyle()` in trait handlers.

## Code quality

- [ ] Avoid unnecessary complexity; logic is readable and scoped.
- [ ] Avoid large functions; consider splitting when logic grows.
- [ ] Naming consistency (variables, props, files) matches project conventions.
- [ ] Files under ~1500 lines where practical; split by responsibility when larger.
- [ ] New blocks/traits follow existing patterns (see `ai/kb/patterns.md` and `block-anatomy.md`).

## Safety & correctness (Email Builder)

- [ ] No **direct DOM mutation** for persisted state (state in attributes, hydration to styles).
- [ ] **Trait panel lifecycle:** On component switch, previous panel cleanups run (`runPreviousPanelCleanups`, `clearTraitContainerContent`); React roots unmounted via `registerTraitCleanup`.
- [ ] **Hydration:** No `set('content')` or structure mutation inside hydrate; content projection only on attribute events (e.g. `data-rte-html`, `data-raw-html`).
- [ ] **Event domain separation:** High-frequency or structural events do not trigger expensive UI or RTE sync.
- [ ] No **breaking API changes** without migration path or versioning.
- [ ] **Backwards compatibility** preserved where callers or persisted data depend on current behavior.

## TypeScript & runtime

- [ ] No **`editor.__*`** or undocumented globals; runtime via `editorRuntime.ts` accessors.
- [ ] Typing: `Editor`, `Component`, or documented interfaces used; avoid `unknown` for editor/component where shape is known.
- [ ] Optional chaining / guards where editor or runtime may be absent (e.g. templates list, post-destroy).

## Testing

- [ ] **Tests updated** if behavior changed; new logic has or is covered by tests where appropriate.
- [ ] If behavior changed, consider whether **KB or docs** need an update.
- [ ] No new long-term duplication of rules; link to `.cursor/rules` or `docs/` where canonical.

## Automation Analytics UI

**Mandatory** when the change touches `adflipr-app/src/pages/automations/components/Analytics.tsx`, `LineChart.tsx`, or automation Analytics e2e.

**KB:** [`automation-analytics-ui-contract.md`](../kb/automation-analytics-ui-contract.md)  
**Cursor rule:** `.cursor/rules/automation-analytics-ui.mdc`

- [ ] Rate cards still show **`%`** (Unique/Total Open, Unique/Total Click, Click to Open)
- [ ] Performance **metric tabs** remain when multiple series exist (`setSelectedTab` wired)
- [ ] Chart does not stay stuck on an all-zero Active Contacts series when another series has data
- [ ] `AUTO-E-04*` e2e updated or still green

## Popup Builder — storefront export CSS (serializer + embed)

**Mandatory** when the change touches `popupHtmlSerializer/`, export artifact fields, `renderCss`, responsive `@media`, embed stylesheet lifecycle, or presentation in saved HTML.

**Checklist:** [`popup-rendering-css-architecture-checklist.md`](./popup-rendering-css-architecture-checklist.md)  
**Target KB:** [`popup-rendering-css-architecture.md`](../kb/popup-rendering-css-architecture.md)  
**As-is audit:** [`popup-css-lifecycle-audit.md`](../kb/popup-css-lifecycle-audit.md)

- [ ] Aligns with target invariants (§20) or is explicitly legacy/migration-only
- [ ] No new desktop-inline + mobile-`@media` same-property anti-pattern (PCL-008)
- [ ] Computed-style tests for responsive changes where applicable
- [ ] Legacy artifacts remain supported if runtime/serializer behavior changes

## Popup Builder (content blocks)

Use when the change adds or modifies a **Popup Builder content block** (`BlockType` in `components/form-builder/types.ts`). Full checklist: **`ai/kb/popup-block-registry.md` §9**. Phase plan: **`ai/kb/popup-block-registry-phases-0-2.md`**.

- [ ] **Palette** — `FormBlock.tsx` / `BLOCK_CONFIGS` (or future block registry).
- [ ] **Canvas** — `FormElementRenderer` (or extracted block module); preview behavior considered.
- [ ] **Properties** — `ElementPropertiesPanel` + `*Properties.tsx`; attributes-only mutations.
- [ ] **Serializer** — `serializeElements` + `blockRegistry.ts` map (+ `styles/inline/`); sanitization; golden test if new export shape.
- [ ] **Embed runtime** (if interactive) — `embed/*.ts` init + **`dispose` on step change**; attrs in `contract/`.
- [ ] **Contracts** — no duplicated `data-adflipr-*` strings; import from `contract/`.
- [ ] **i18n** — user-visible strings in all 7 locale files (`adflipr-app/src/locales/*.json`).
- [ ] **KB** — update [`popup-html-serialization.md`](popup-html-serialization.md) block table when export behavior changes.

---

**Output:** List passes, then list violations with file/line or snippet, severity, and suggested fix.
