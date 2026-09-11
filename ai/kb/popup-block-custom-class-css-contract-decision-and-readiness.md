# Track 2B — Block `customClass` + `customCss` Contract Decision and Implementation Readiness

**Date:** 2026-07-06 (S2 amendment 2026-07-31)  
**Branch:** `css-reft` (baseline per Track 2A; session may include uncommitted Phase 7G/7H edits)  
**HEAD:** `291c68dae075aa1610d6c44e11790cafe58d2073` (Track 2A snapshot; re-verify before implementation)  
**Working tree:** **not clean** at Track 2B start (Phase 7G/7H popup serializer work)  
**Mode:** evidence + contract decision + architecture + plan — **implemented (S2)**

---

## 1. Executive verdict

Block-level `customClass` and `customCss` are implemented end-to-end for content blocks.

**Forward contract (S2 — implemented):**

| Field | Contract |
|-------|----------|
| `block.style.customClass` | Space-separated HTML `class` tokens (no leading dot). **Painted CSS root (descendant binding):** on the family’s painted host (button control, field/checkbox/radio/**image `<img>`** / video/product/coupon/timer/social/divider/text shell) — not the `[data-adflipr-block-id]` wrapper. **Wrapper (compound):** spinner only. Merchants author `.myClass` / `.myClass input` / `.myClass label` — no internal `pbn-*` required. |
| `block.style.customCss` | **Dual-path:** brace-free declaration-list **or** class-rooted stylesheet (selectors, `:hover`, `::before`). Compiled into artifact `renderCss` under `[artifact] [block-id]…`. Stylesheet path requires matching `customClass`. At-rules (`@media`) dropped in v1. |
| Live canvas | Same compiler injected on edit canvas under scope `pbn-edit-canvas` (distinct from preview `pbn-preview`). |

**Implementation readiness:** **IMPLEMENTED** — Track 2B.1 customClass + Track 2B.2 S2 customCss + live canvas.

**Follow-up (canvas trait paint):** Edit canvas still paints family traits via React inline styles; live customCss uses canvas-only `!important` as a bridge. Long-term removal of that bridge is tracked in [`popup-canvas-presentation-rendercss-migration-decision-and-readiness.md`](./popup-canvas-presentation-rendercss-migration-decision-and-readiness.md).

**Verdict:** **ACCEPT**

---

## 2. Repository state

| Item | Value |
|------|-------|
| Track 2A complete | YES — all merchant-CSS block families migrated; generic responsive merchant CSS closed |
| Block customClass export/canvas | YES — painted CSS root (descendant) for most content families; spinner wrapper (compound) |
| Block customCss export | YES — `compileBlockCustomCssRenderCss` last chunk of `compilePopupRenderCss` |
| Block customCss live canvas | YES — `BlockCustomCssCanvasStyle` + `pbn-edit-canvas` |
| Form customCss export | YES — `serializeLayout.ts` → `sanitizeCustomCss` → `<style data-adflipr-popup-custom-css>` |
| Form customClass export | **NONE** |
| Production files modified in Track 2B | YES (implementation) |

---

## 3. Complete block UI inventory

### Content block families (wrapped blocks)

| Family | customClass UI | customCss UI | Persisted path | Canvas consumer | Export consumer | Storefront effect |
|--------|----------------|--------------|----------------|-----------------|-----------------|-------------------|
| text | YES (Advanced) | YES | `properties.style.*` | — | — | — |
| button | YES | YES | `properties.style.*` | — | — | — |
| divider | YES | YES | `properties.style.*` | — | — | — |
| text-input | YES | YES | `properties.style.*` | — | — | — |
| email-input | YES | YES | `properties.style.*` | — | — | — |
| phone-input | YES | YES | `properties.style.*` | — | — | — |
| textarea | YES | YES | `properties.style.*` | — | — | — |
| date | YES | YES | `properties.style.*` | — | — | — |
| dropdown | YES | YES | `properties.style.*` | — | — | — |
| check-box | YES | YES | `properties.style.*` | — | — | — |
| radio | YES | YES | `properties.style.*` | — | — | — |
| image | YES | YES | `properties.style.*` | — | — | — |
| video | YES | YES | `properties.style.*` | — | — | — |
| product | YES | YES | `properties.style.*` | — | — | — |
| coupon | YES | YES | `properties.style.*` | — | — | — |
| timer | YES | YES | `properties.style.*` | — | — | — |
| spinner | YES | YES | `properties.style.*` | — | — | — |
| social | YES | YES | `properties.style.*` | — | — | — |
| feedback | **NO** | **NO** | — | — | — | — |
| cross | **NO** | **NO** | `properties.*` (style traits only) | — | — | — |
| floating-image | **NO** | **NO** | — (no Properties panel) | — | — | — |

### Layout families (related persisted paths — **out of block `style` scope** for Track 2B implementation, recorded for truth)

| Family | customClass UI | customCss UI | Persisted path | Canvas | Export |
|--------|----------------|--------------|----------------|--------|--------|
| section (one/two/three-column layout) | YES | YES | `layoutElement.properties.style.*` | — | — |
| column (per-column Advanced) | YES | YES | `layoutElement.properties.style.columnStyles[columnId].*` | — | — |

### Form-level (intentionally different channel)

| Surface | customClass UI | customCss UI | Persisted path | Canvas | Export |
|---------|----------------|--------------|----------------|--------|--------|
| Form theme Advanced + CustomCodePanel | YES | YES | `formStyle.*` | Preview only (`PbnCanvasRootZone` / `<style>`) | `customCss` only |

### Required lists

```text
Families exposing customClass:
  text, button, divider, text-input, email-input, phone-input, textarea, date,
  dropdown, check-box, radio, image, video, product, coupon, timer, spinner, social
  (+ layout: section, column — separate persistence paths)

Families exposing customCss:
  (same as customClass list)

Families exposing both:
  (same as customClass list)

Families exposing neither:
  feedback, cross, floating-image
```

```text
UI contract globally shared: PARTIALLY
Family-specific implementations: YES
  — Shared Advanced-tab pattern across 18 content families
  — Column uses columnStyles[columnId] path
  — Section uses layout element style path
  — Form uses formStyle path with explicit preview-only helper text
  — Spinner customCss placeholder differs (rule syntax hint)
  — floating-image has no properties panel at all
```

---

## 4. Persistence trace

### Block content path (canonical for Track 2B)

| Stage | Mechanism |
|-------|-----------|
| Panel writer | `*Properties.tsx` Advanced tab → `handleStyleChange('customClass' \| 'customCss', value)` |
| Update callback | `onUpdate({ ...properties, style: { ...style, [key]: value } })` |
| Redux/state | Popup builder snapshot `formElements[stepId][]` → `element.properties.style` |
| Saved block model | `builderState` JSON in popup metadata |
| Clone/duplicate | Standard element clone copies `properties` wholesale |
| Undo/redo | Native undo on `onUpdate` / form element mutations |
| Template save/load | `popupTemplateImport` / builder state round-trip preserves `properties.style` |
| Popup save/load | `buildPopupSaveMetadata` embeds `builderState`; HTML export separate |
| Serializer input | `element.properties.style` read by family compilers — **custom fields not read today** |

```text
Canonical persisted customClass path (content block):
  element.properties.style.customClass

Canonical persisted customCss path (content block):
  element.properties.style.customCss

Multiple persisted representations: YES
```

| Representation | Path | Writers | Readers (today) |
|----------------|------|---------|-----------------|
| Content block | `properties.style.customClass/customCss` | 18 `*Properties.tsx` Advanced tabs | **none** (export/canvas) |
| Column | `properties.style.columnStyles[columnId].*` | `ColumnProperties.tsx` | **none** |
| Section layout | `properties.style.*` on layout element | `SectionProperties.tsx` | **none** |
| Form | `formStyle.customClass/customCss` | `FormLayoutTabs.tsx`, `CustomCodePanel.tsx` | Canvas preview + export (`customCss` only) |

**No compatibility layer** — record only; forward implementation targets **content block `properties.style`** first.

---

## 5. Canvas behavior

### `customClass`

| Question | Answer |
|----------|--------|
| React component reading **block** customClass | **None** |
| DOM node receiving block customClass | **None** |
| Form customClass | `FormCanvas.tsx` → `PbnCanvasRootZone` `className` when `isPreview` |
| Multiple classes | No normalization; raw string concatenated to `className` (form only) |
| Whitespace preserved | YES (form) |
| Invalid token filtering | **None** |
| Built-in class duplication | Not prevented |
| Live update | Form preview: YES on re-render; edit mode: explicitly skipped (comment L736–739) |
| Per-family block behavior | N/A — uniformly unwired for blocks |

```text
Current canvas customClass owner (content blocks): NONE (unwired)

Current canvas customClass owner (form-level, preview only):
  PbnCanvasRootZone — not block wrapper
```

`CanvasBlockShell.tsx` applies layout styles only; does not read `style.customClass`.

### `customCss`

| Question | Answer |
|----------|--------|
| Block customCss on canvas | **Entirely unwired** |
| Form customCss | `<style dangerouslySetInnerHTML>` in `FormCanvas` when `isPreview && formStyle.customCss` |
| Style injection | Form preview only |
| Selectors interpreted | Form: merchant rules as authored (global document risk in preview — acknowledged in UI copy) |
| Edit mode | Skipped for form to protect builder chrome |

```text
Current canvas customCss effect (blocks): NONE

Current canvas customCss effect (form): Preview-only global <style> injection
```

---

## 6. Export behavior

### Pipeline

```text
block model
  → serializeElements (wrapper div + data-adflipr-block-id)
  → serializeLayout (step HTML + embedded <style> blocks)
  → compilePopupRenderCss (artifact renderCss)
  → buildPopupSaveMetadata / POPUP_METADATA_RENDER_CSS
  → embed runtime mountRenderArtifactCss + hoistExportedStepStyles
```

### Readers

```text
Block customClass export readers: NONE
  — grep popupHtmlSerializer/: zero matches for customClass

Block customCss export readers: NONE
  — only formStyle.customCss in serializeLayout.ts L234–291
```

### Tests / KB corroboration

- `popupHtmlSerializer.test.ts` — form `customCss` sanitization only
- `popup-divider-merchant-presentation-phase7b-report.md` — customClass/customCss OUT OF SCOPE
- `popup-coupon-merchant-presentation-phase7c-report.md` — DEFERRED
- `popup-remaining-family-triage-phase7e-report.md` — ORPHAN / LEGACY
- `popup-embed-production-bugs.md` **PEB-118** — element customCss not serialized (open)

**Track 2A expectation confirmed by proof, with correction:** block `customClass` is **not** applied on canvas either.

---

## 7. UI-promised contract

### Labels and placeholders (representative)

| Field | UI copy | Implied contract |
|-------|---------|------------------|
| customClass | Label: "Custom Class"; placeholders: `e.g. my-input`, `my-custom-class`, `e.g. my-product` | **Class name(s) without dot** — not a CSS selector |
| customCss | Label: "CSS"; placeholder: `Custom CSS...` or `Type here...` | **Ambiguous** — freeform CSS |
| Spinner customCss | Placeholder: `.my-custom-class { }` | **Rule syntax** — conflicts with other families |
| Form customCss | Helper: "Applied in Preview only. Global rules here can break the whole app..." | **Full CSS** including selectors; preview/global risk acknowledged |
| Form customClass | Helper: "Shown on the form in Preview; not applied to the edit canvas" | **Form root class** in preview only |

```text
UI-promised customClass contract:
  Class name token(s) without leading dot (weakly explicit; no validation)

UI-promised customCss contract:
  INCONSISTENT — mostly "custom CSS" freeform; Spinner hints at rule blocks;
  form-level explicitly allows global rules in preview

Contract explicit enough for implementation: NO
```

**Decision:** Do not infer full CSS for block `customCss` from field name alone. Product contract for blocks is **declaration-list (S1)** with updated UI copy in implementation phase.

---

## 8. Historical intent

| Evidence | Date/commit | Intended behavior | Still relevant? |
|----------|-------------|-------------------|-----------------|
| PEB-118 open bug | KB | Serialize element customCss or hide UI | YES |
| PEB-029 customCss unscoped | KB | Scope merchant CSS | YES — form still global |
| Phase 7B–7H reports | 2026-07 | customClass/customCss deferred per family | YES |
| `popup-deferred-debt-parallel-work-discussion-pack.md` Stream A | 2026-07 | compile scoped vs remove UI vs strip on save | YES |
| `popup-builder-rendering-pipeline.md` | KB | form customCss path documented; block orphan noted | YES |
| `sanitizeCustomCss` | code | Markup breakout only | YES — insufficient for block contract |

```text
Historical classification: NEVER COMPLETED FEATURE (with PARTIAL FEATURE form-level customCss)
```

Not a regression — export never consumed block fields. Canvas block customClass was never wired (Track 2A label was stale).

---

## 9. customClass ownership models (evaluation)

| Model | Summary | Verdict |
|-------|---------|---------|
| **C1** Export wrapper `[data-adflipr-block-id]` | Stable per-block owner; exists in `serializeElements.ts` for all wrapped blocks | **SELECTED** |
| C2 Family-painted owner | Different DOM per family; breaks cross-family contract | Rejected |
| C3 Dedicated custom-style owner attr | Adds topology without proven need | Rejected (C1 sufficient) |
| C4 Preserve canvas owner | Canvas has **no** block owner today | Rejected |

```text
Selected customClass model: C1

Exact DOM owner:
  Export/canvas preview wrapper div with attributes:
    data-adflipr-block-id="{element.id}"
    data-block-type="{element.type}"
  (same node serializeElements already emits)

Why:
  — Stable identity for every wrapped content block
  — Matches artifact scoping prefix used by all family compilers
  — Cross-family consistent
  — Minimal topology coupling
  — Canvas parity achievable in CanvasBlockShell preview branch

Rejected models: C2, C3, C4
```

---

## 10. Selected customClass contract

**Painted CSS root (descendant binding)** for mismatch / composite families: apply normalized `customClass` on the family’s painted host (same node traits visually paint on), not the `[data-adflipr-block-id]` wrapper.

| Family | Painted CSS root |
|--------|------------------|
| button | `<button>` |
| text | content `<div>` |
| divider | `.pbn-form-divider-root` |
| text/email/phone/textarea/date/dropdown | `.pbn-canvas-input-shell` |
| check-box / radio | group scope (export `pbn-export-native-*-scope` / canvas `pbn-canvas-native-*-scope`) |
| image | `.pbn-image-painted` (`<img>` / empty placeholder) — same node as trait border-radius |
| video / product / coupon / timer / social | respective `pbn-*-shell` / container |
| spinner | **wrapper** (compound) — export chrome already on block-id |

Merchants author under their class with normal HTML (`input`, `label`, `:hover`) — they do **not** need internal `pbn-*` names.

CSS binding: painted roots → descendant `[artifact] [block-id] .foo`; spinner → compound `[artifact] [block-id].foo`.

---

## 11. customClass grammar

```text
Canonical customClass grammar:
  Zero or more HTML class tokens, space-separated (HTML5 class attribute semantics)
  — Tokens: [-_a-zA-Z0-9] and ISO 10646 characters per HTML class rules
  — No leading dot
  — No selectors, combinators, or pseudo-classes

Normalization rules:
  1. Trim leading/trailing whitespace
  2. Collapse internal whitespace runs to single space
  3. Strip leading '.' from each token if merchant pasted selector-style
  4. Deduplicate tokens (first occurrence wins)
  5. Reject or strip tokens matching reserved prefixes: pbn-, data- attribute-like patterns
  6. Empty after normalization → omit attribute

Rejected input behavior:
  — Invalid characters within a token: drop token (or entire field if no valid tokens remain)
  — Do not throw; do not persist pre-normalization raw string in export path
```

---

## 12. customCss syntax models (evaluation)

| Model | Summary | Verdict |
|-------|---------|---------|
| S1 Declaration-list | `color: red; font-size: 18px;` | **Still supported** (brace-free path) |
| **S2** Scoped full CSS | Parse rules, rewrite selectors under artifact + block-id | **SELECTED (2026-07-31)** |
| S3 Selector-relative `&` | Requires CSS parser + scoping | Rejected — no repo precedent |
| S4 Remove block customCss | Form-only escape hatch | Rejected — UI exists; persisted data possible |

```text
Selected customCss model: S2 (dual-path with S1 declarations retained)

Exact syntax contract:
  — Path A (no braces): semicolon-separated declarations → scoped under
    [artifact] [block-id]                (compound / spinner)
    [artifact] [block-id] .merchantClass (painted root when customClass set)
    [artifact] [block-id] button         (button painted-owner, no customClass)
  — Path B (braces): top-level style rules only; at-rules dropped
    Root selectors must include a Custom class token
    Rewritten as:
      compound:    [artifact] [block-id].foo:hover     (spinner)
      descendant:  [artifact] [block-id] .foo:hover    (painted CSS roots)
  — Markup sanitize via sanitizeCustomCss; declaration denylist
    (position fixed/sticky, behavior, dangerous URL schemes)
  — Parser: extractTopLevelStyleRules + postcss-selector-parser
    (no postcss.parse in browser)

Rejected models: S3, S4
```

---

## 13. customClass / customCss relationship

```text
Does customCss require customClass? NO

Selected relationship: Independent fields; stylesheet path requires matching customClass
  — customClass: merchant identity on the painted CSS root (or spinner wrapper)
  — customCss Path B: class-rooted rules under that class
  — Merchants write `.myClass input` / `.myClass label` — no internal selector knowledge
  — S1 applies to canonical block owner automatically
```

---

## 14. Form-level customCss boundary (unchanged in Track 2B)

| Concern | Block customCss (decided) | Form customCss (current) |
|---------|---------------------------|--------------------------|
| Scope | Per block via artifact+block-id | Per popup step — global within artifact root |
| Syntax | Declaration-list only | Full CSS text (rules, selectors, @media possible) |
| Target owner | `[data-adflipr-popup-artifact] [data-adflipr-block-id]` | Unscoped merchant selectors inside `<style data-adflipr-popup-custom-css>` |
| Selector support | **NO** | **YES** |
| Pseudo-class | **NO** | **YES** (merchant-authored) |
| Media support | **NO** | **YES** (merchant-authored) |
| At-rule support | **NO** | **YES** (merchant-authored; `@import` not blocked today) |
| Sanitization | Declaration allowlist + markup strip (planned) | `sanitizeCustomCss` — markup breakout only |
| Artifact isolation | Strong (compiler-enforced prefix) | Weak — PEB-029 open |
| Cascade position | Inside `renderCss` | Hoisted step `<style>` after structural sheets |

```text
Block and form customCss share one implementation: PARTIALLY
  — Shared: artifact scope id, save pipeline awareness, optional shared markup sanitizer
  — Separate: block declaration compiler vs form raw CSS channel

Share sanitization primitives: YES (markup breakout layer)
Share cascade channel: NO (block → renderCss; form → embedded step style)
```

---

## 15. Artifact CSS channel decision

| Channel | Verdict |
|---------|---------|
| **A** Merge into `renderCss` | **SELECTED** |
| B Separate artifact `customCss` field | Rejected — schema churn |
| C Serialized step `<style data-pbn-block-custom>` | Rejected — duplicates G1 hoisting complexity |

```text
Selected artifact CSS channel: A

Implementation:
  New compileBlockCustomPresentationRenderCss(snapshot, artifactScopeId)
  appended as final chunk in compilePopupRenderCss (after all family compilers)

Why:
  — One artifact stylesheet; survives step transitions (mountRenderArtifactCss)
  — Consistent with Phase 1–7 migration
  — Deterministic cascade vs per-family rules

Rejected channels: B, C
```

---

## 16. Exact cascade order

**Observed mount order (storefront/embed):**

1. Host page theme CSS (external)
2. **`renderCss`** — `mountRenderArtifactCss` → `document.head` (`data-adflipr-popup-render-css`, per runtime `ownerId`)
3. **Hoisted step styles** — `hoistExportedStepStyles` in DOM order from `serializeLayout` cssBlock:
   - floating-label, responsive export, entrance, radio, checkbox, coupon, image, video, social, product, rich-text-links, device visibility, **form customCss**
4. **RTE inline** formatting on text nodes
5. **Structural inline** on export nodes (intentional, not merchant)

**With block customCss in renderCss (decided position):**

```text
1. Host theme CSS
2. renderCss — family presentation compilers (form-surface → … → product)
3. renderCss — block customCss compiler chunk (last within renderCss)
4. Hoisted step embedded styles (structural sheets + form customCss)
5. RTE inline formatting on content nodes
```

### Override semantics (product)

| Question | Answer | Rationale |
|----------|--------|-----------|
| Block customCss override normal traits? | **YES** | Merchant override intent; same specificity on wrapper declarations after family rules in renderCss |
| Block customCss override hover traits? | **NO** (default) | S1 targets wrapper; family `:hover` rules target painted descendants with pseudo — declarations on wrapper do not replace child hover rules without S2 |
| Form customCss override block customCss? | **YES** | Form channel is later in mount order and may use higher-specificity selectors |
| RTE inline override block customCss? | **YES** | Inline on text spans wins for those properties |

---

## 17. Artifact isolation model

```text
Exact selector prefix:
  [data-adflipr-popup-artifact="{artifactScopeId}"] [data-adflipr-block-id="{blockId}"]

Multi-artifact isolation model:
  — artifactScopeId unique per saved popup artifact / compile invocation
  — renderCss rules never omit artifact prefix
  — runtime mountRenderArtifactCss keyed by runtime ownerId (not popupId alone)

Multi-runtime isolation model:
  — Each PopupEmbedRuntime instance: distinct ownerId on stylesheet
  — Teardown removes owned stylesheet on dismiss

Duplicate block-id handling:
  — Block ids unique within builder state per popup
  — If duplicate ids ever exist, last compile wins — builder should enforce uniqueness (existing invariant)
```

`customClass` does **not** participate in isolation — merchant classes are unscoped HTML classes on the wrapper; isolation is via `data-adflipr-*` attributes.

---

## 18. CSS safety analysis

### Current `sanitizeCustomCss` (`popupHtmlSanitize.ts`)

Strips: `<script>…</script>`, HTML tags — **markup breakout only**.

Does **not** block: `@import`, `url(javascript:)`, `position:fixed`, `body`, `*`, `@font-face`, `@media`, custom properties.

```text
Current sanitizer capability: Markup-breakout sanitizer only (form customCss pass-through)

Safe for selected block customCss contract (S1): NO — needs declaration allowlist/denylist

Additional parser required: YES
  — Declaration-list parser (property:value splitting + validation)
  — postcss ^8.5.14 already in adflipr-app package.json (devDependency) — evaluate promotion vs lightweight tokenizer

Additional selector rewriter required: NO (S1)
```

---

## 19. Persisted-data evidence

| Source | customClass | customCss |
|--------|-------------|-----------|
| `popupBuilderState.test.ts` | — | form-level `.x{color:red}` |
| `popupHtmlSerializer.test.ts` | — | form-level with script breakout |
| Family fixtures / G1 | — | — |
| Templates / seed | — | — |

```text
Repository customClass persisted examples: NONE in tests/fixtures (UI writes only)
Repository customCss persisted examples: form-level only in tests

Production prevalence known: NO
Production-data audit required before implementation: YES (recommended, read-only Mongo)
  — Low risk for customClass grammar enforcement
  — Medium risk if merchants saved Spinner-style rule blocks in block customCss
```

---

## 20. Canvas/export parity architecture

| Surface | customClass owner | customCss source | Scoping |
|---------|-------------------|------------------|---------|
| Canvas (edit) | — | — | — |
| Canvas (preview) | Block wrapper shell (`CanvasBlockShell` preview branch) | Compiled preview stylesheet hook mirroring `compileBlockCustomPresentationRenderCss` | artifact scope + block id |
| Builder preview (`FormPreview` embed) | Same as canvas preview (via serialized export) | Same | Same |
| Saved artifact export DOM | `serializeElements` wrapper `class` attr | In `renderCss` string | artifact + block id |
| Storefront | Same export DOM + mounted `renderCss` | Same | Same |

**Form-level** remains on `PbnCanvasRootZone` / step `<style>` — unchanged.

---

## 21. Canonical helper boundaries (illustrative names)

```text
normalizeBlockCustomClass(raw: string): string
  — Grammar §11; shared by canvas + export + compile input

normalizeBlockCustomCssDeclarations(raw: string): string
  — Parse declaration-list; strip invalid; no selectors

compileBlockCustomPresentationRenderCss(
  formElementsByStep, artifactScopeId
): string
  — Emit artifact-scoped rules for all blocks with non-empty normalized declarations

applyBlockCustomClassToExportWrapperAttrs(style, attrs): Record<string, string>
  — serializeElements integration

resolveCanvasBlockCustomClass(style): string | undefined
  — CanvasBlockShell preview className merge
```

Single module recommended: `utils/popupHtmlSerializer/blockCustomPresentation.ts` (+ shared `blockCustomContract.ts` for grammar constants).

---

## 22. Implementation phase plan

```text
Recommended implementation phase count: 2

Phase names:
  Track 2B.1 — customClass contract + canvas/export DOM parity
  Track 2B.2 — block customCss declaration compiler + renderCss integration + UI copy alignment

Why split:
  — customClass is low-risk (attribute only, no parser)
  — customCss requires declaration parser, allowlist, tests, UI contract clarification
  — Enables shipping class parity before CSS compiler

Optional Track 2B.3 — production-data audit + orphan UI cleanup for column/section paths (separate persistence)
```

### Track 2B.1 scope

- `normalizeBlockCustomClass`
- `CanvasBlockShell` preview + export wrapper `class`
- Tests: button, text, image, product, spinner DOM snapshots
- i18n: clarify customClass helper text

### Track 2B.2 scope

- `normalizeBlockCustomCssDeclarations` + property allowlist
- `compileBlockCustomPresentationRenderCss` wired last in `compilePopupRenderCss`
- Browser tests: isolation (two blocks, two artifacts, two runtimes)
- Spinner placeholder fix to declaration example
- Extend `sanitizeCustomCss` markup layer reuse

---

## 23. Acceptance criteria (implementation)

- [ ] UI writer persists canonical normalized values (or raw with normalize-at-read — pick one, document)
- [ ] Canvas and export use same `normalizeBlockCustomClass`
- [ ] Export DOM receives `customClass` on `[data-adflipr-block-id]` wrapper
- [ ] Block `customCss` follows declaration-list grammar
- [ ] Block `customCss` artifact-scoped in `renderCss`
- [ ] Two blocks cannot leak styles
- [ ] Two popup artifacts cannot leak
- [ ] Multiple runtime mounts isolated (`ownerId`)
- [ ] Cascade order deterministic per §16
- [ ] Form-level `customCss` unchanged
- [ ] Family compilers unchanged except `compilePopupRenderCss` chunk list
- [ ] No serializer merchant chrome reintroduced
- [ ] No generic responsive merchant CSS reintroduced
- [ ] No compatibility layer without evidence
- [ ] Orphan block custom UI behavior replaced (export/canvas wired)
- [ ] Phase 0 + G1 pass; production build passes
- [ ] Model-specific browser tests per family samples

---

## 24. Expected blast radius

### Production files likely touched

| File | Expected action | Why |
|------|-----------------|-----|
| `utils/popupHtmlSerializer/blockCustomPresentation.ts` | **ADD** | Grammar + compile |
| `utils/popupHtmlSerializer/compilePopupRenderCss.ts` | MODIFY | Append block custom chunk |
| `utils/popupHtmlSerializer/serializeElements.ts` | MODIFY | `class` on wrapper |
| `components/form-builder/blocks/shared/CanvasBlockShell.tsx` | MODIFY | Preview customClass |
| `utils/popupHtmlSerializer/popupHtmlSanitize.ts` | MODIFY | Shared declaration safety helpers |
| `utils/formPreviewEmbedPresentation.ts` / `previewArtifactExport.ts` | MODIFY | Preview CSS parity via embed path |
| `locales/*.json` | MODIFY | UI contract copy (i18n) |
| Advanced `*Properties.tsx` (optional) | MODIFY | Helper text / placeholder alignment |

### Tests likely touched

| File | Why |
|------|-----|
| `utils/__tests__/blockCustomPresentation*.test.ts` | **ADD** |
| `utils/__tests__/popupHtmlSerializer.test.ts` | Export assertions |
| `embed/__tests__/renderArtifactCssLifecycle.test.ts` | Isolation |
| `test-utils/responsiveCssBaselineHarness.ts` | G1 fixture extension |
| Browser tests per Gate J1 families | DOM parity |

### KB/docs likely touched

| File | Why |
|------|-----|
| `ai/kb/popup-css-migration-current-state.md` | Close deferred item |
| `ai/kb/popup-embed-production-bugs.md` | Close PEB-118 (block path) |
| Phase report for Track 2B | Implementation record |

---

## 25. Files explicitly out of scope

- Button linked-label defect
- Checkbox `backgroundColor` orphan
- Coupon `boxShadowEnabled`
- Structural-inline cleanup
- Date/Phone canvas cleanup
- Form-level `customCss` redesign / scoping (PEB-029)
- Form-level `customClass` export
- RTE command semantics
- Completed family migration redesign (Button, Text, …, Product, Spinner)
- Column/section `columnStyles` custom fields (separate track unless product expands scope)
- Feedback Advanced custom fields (none today)
- cross / floating-image custom fields

---

## 26. Open product decisions

1. **Column/section customClass/customCss** — same contract as content blocks or defer?
2. **Block customCss hover** — accept NO override for S1, or invest in S2 later?
3. **Invalid persisted rule-syntax block customCss** — silent drop vs builder warning badge?
4. **Mongo prevalence audit** — run before 2B.2?
5. **Form customClass export** — separate product track?

---

## 27. Final implementation readiness

| Criterion | Status |
|-----------|--------|
| Evidence complete | YES |
| Product contract decided | YES (S1 + C1) |
| Architecture decided | YES (renderCss channel A) |
| Blast radius identified | YES |
| Production code changed in 2B | NO |
| Blockers | Optional Mongo audit; declaration allowlist product sign-off |

```text
Implementation readiness: READY WITH PREREQUISITE
```

---

## 28. Final verdict

**ACCEPT WITH FOLLOW-UP** — Forward-only block contract is defined and implementation-ready. Prerequisites: optional production-data read-only audit; i18n/UI copy update to resolve Spinner ambiguity; declaration property allowlist sign-off before 2B.2.

**Production files modified in Track 2B:** **NO**
