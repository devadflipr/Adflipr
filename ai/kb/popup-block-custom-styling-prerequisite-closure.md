# Track 2B-P — Block Custom Styling Prerequisite Closure

**Date:** 2026-07-06  
**Branch:** `css-reft`  
**HEAD:** `291c68dae075aa1610d6c44e11790cafe58d2073` (+ Phase 8A / uncommitted working-tree changes possible)  
**Mode:** prerequisite closure only — **no production behavior changes**

---

## 1. Executive verdict

Four prerequisite questions are **resolved**. Track 2B architecture (C1 / S1 / Channel A) remains valid.

| Phase | Readiness |
|-------|-----------|
| **Track 2B.1** (customClass) | **READY** |
| **Track 2B.2** (customCss) | **READY AFTER MONGO AUDIT** |

**Immediate next action:** **IMPLEMENT TRACK 2B.1** (do not block on Mongo; 2B.2 requires audit first).

**Verdict:** **ACCEPT WITH FOLLOW-UP**

---

## 2. Repository state

```text
branch: css-reft
HEAD:   291c68dae075aa1610d6c44e11790cafe58d2073
working tree: NOT CLEAN (Phase 8A closure + prior migration edits possible)
production files modified in Track 2B-P: NO
```

---

## 3. Track 2B baseline revalidation

| Claim | Current code | Valid |
|-------|--------------|-------|
| Block customClass UI writers | 18 `*Properties.tsx` Advanced tabs + Column/Section (separate paths) | YES |
| Block customCss UI writers | Same | YES |
| Block customClass export readers | `grep popupHtmlSerializer/ customClass` → **0** | YES |
| Block customCss export readers | Only `formStyle.customCss` in `serializeLayout.ts` | YES |
| Block customClass canvas consumers | `CanvasBlockShell` does not read `style.customClass` | YES |
| Block customCss canvas consumers | None on blocks | YES |

```text
Track 2B baseline still valid: YES
```

No contradiction. Track 2A correction (block canvas unwired) still holds.

---

## 4. Repository persisted-data evidence

Searched: PopupBuilder tests, test-utils, G1 harness, templates, service seed data, builder-state fixtures, `popupTemplateImport`, metadata fixtures.

| Source | Block family | customClass | customCss | Syntax shape |
|--------|--------------|-------------|-----------|--------------|
| `popupBuilderState.test.ts` | — (form) | — | `.x{color:red}` | FORM RULE (not block) |
| `popupHtmlSerializer.test.ts` | — (form) | — | `.popup{color:red}…` | FORM RULE + markup |
| All PopupBuilder `__tests__` / fixtures | — | **none** | **none** | — |
| G1 / responsive baseline harness | — | **none** | **none** | — |
| Service Java / seed JSON | — | **none** | **none** | — |

```text
Repository block customClass examples found: NONE
Repository block customCss examples found: NONE
```

**Conclusion:** Repository cannot prove production prevalence or saved syntax shapes for content-block fields.

---

## 5. Production prevalence decision

Track 2B contradiction resolved:

| Rule | Application |
|------|-------------|
| Strict forward contract + **no reinterpretation risk** | Proceed without Mongo |
| Strict forward contract + **may drop/reject unknown saved shapes** | Mongo **required** before that phase |

| Field | Reinterpretation risk | Mongo before implement? |
|-------|----------------------|-------------------------|
| **customClass** | Low — NORMALIZE leading dot; reject invalid tokens; never exported today | **NO** |
| **customCss** | High — S1 **rejects entire rule blocks**; Spinner UI placeholder encourages `.class { }` syntax; silent data loss possible | **YES** |

```text
Repository evidence sufficient to implement strict forward contract: NO
  (for 2B.2 — unknown production rule-block prevalence)

Mongo audit required before Track 2B.1: NO
Mongo audit required before Track 2B.2: YES
```

---

## 6. Mongo audit requirement

**2B.1:** Not required — feature never consumed; normalization is additive; worst case benign class token cleanup.

**2B.2:** Required — implementation will **drop** rule-block / selector / at-rule shapes if merchants saved them.

---

## 7. Mongo audit specification (read-only)

**Not executed in this session.** Specification for ops/engineering:

### Connection

- Read-only Mongo user
- Collection: popups (or equivalent campaign/popup document store)
- Field: `metadata.builderState` (JSON string) per `PopupMetadataKeys.BUILDER_STATE`

### Algorithm

```javascript
// Pseudocode — READ ONLY, no updates
for each popup document with metadata.builderState:
  parse builderState JSON
  for each step in formElements:
    for each element where !isLayout && type not in {cross, floating-image, one-column, two-column, three-column, section}:
      cc = element.properties?.style?.customClass
      cx = element.properties?.style?.customCss
      classify and count (do NOT log full values)
```

**Exclude:** `formStyle.*`, `columnStyles.*`, layout section `style.*`.

### customClass classification (per non-empty value)

| Class | Rule |
|-------|------|
| `SINGLE_TOKEN` | One token after HTML whitespace split |
| `MULTIPLE_TOKENS` | 2+ tokens |
| `LEADING_DOT` | Any token starts with `.` |
| `SELECTOR_LIKE` | Token contains `:`, `#`, `[`, `]`, `>`, `+`, `~`, `*`, `{`, `}` |
| `RESERVED_PREFIX` | Token case-insensitive starts with `pbn-` |
| `INVALID_OR_UNKNOWN` | Empty after trim, or only invalid tokens |

### customCss classification (per non-empty value)

| Class | Rule |
|-------|------|
| `EMPTY` | Whitespace only |
| `DECLARATION_LIST` | No `{`, `}`, `@`; matches `prop:value` segments parseable without rule blocks |
| `RULE_BLOCK` | Contains `{` and `}` |
| `SELECTOR_BASED` | Contains `.`, `#`, `&`, combinator chars, or `:` before first `{` |
| `AT_RULE` | Trim starts with `@` |
| `MALFORMED_OR_UNKNOWN` | Else |

### Required aggregate output

```text
popups scanned
popups with block customClass
blocks with customClass
popups with block customCss
blocks with customCss
family counts (by element.type)
customClass syntax-shape counts
customCss syntax-shape counts
```

### Privacy

- No full `customCss` values
- No full `builderState`
- Optional exemplar rows: `{ popupId, blockId, blockType, classification, valueLength }` only

### Post-audit decision

- If `RULE_BLOCK` + `SELECTOR_BASED` + `AT_RULE` count = 0 → proceed 2B.2 without compatibility
- If > 0 → product decision: one-time migration message, builder warning badge, or scoped compatibility (requires new product gate — **not** automatic)

---

## 8. customClass leading-dot decision

```text
Leading-dot policy: NORMALIZE
```

| Input | Output |
|-------|--------|
| `.my-class` | `my-class` |

**Reason:** UI placeholders promise class name without dot (`e.g. my-input`). Feature never exported. No repository saved examples. Silent correction is merchant-friendly; `.foo` and `foo` are equivalent intent for `class` attribute.

---

## 9. customClass reserved-prefix decision

```text
Reserved-prefix policy: REJECT

Reserved prefixes: pbn- (case-insensitive)
```

**Evidence:** Internal painted/export selectors use `.pbn-*` (e.g. `standardFieldOutlineSelector` → `.pbn-canvas-input-shell`, `buttonPaintedSelector`, radio/checkbox scopes). Merchant `pbn-*` class on block wrapper can accidentally match artifact-scoped internal rules.

**Not** a neatness rule — **collision avoidance** (ARTIFACT ISOLATION).

`customClass` remains an integration escape hatch for **merchant** namespaces, not internal `pbn-*`.

---

## 10. customClass token grammar

```text
Canonical token grammar:
  HTML ASCII whitespace–separated non-empty class tokens (WHATWG / HTML class attribute semantics).
  Do NOT use a restrictive ASCII-only identifier regex.
  Unicode code points allowed inside tokens per HTML (excluding ASCII whitespace and NUL).
```

### Per-token rejection (drop token, keep siblings)

| Character/pattern | Action | Classification |
|-------------------|--------|----------------|
| Leading `.` on token | NORMALIZE strip then re-validate | — |
| Contains `: # [ ] { } @ > + ~ * = " '` | REJECT token | SELECTOR_LIKE |
| Contains `/` | REJECT token | INVALID |
| Starts with `pbn-` (case-insensitive) | REJECT token | RESERVED_PREFIX |
| ASCII whitespace inside token | REJECT token | INVALID |
| Empty token | SKIP | — |

### Field-level

| Condition | Result |
|-----------|--------|
| All tokens invalid | Omit `class` (empty canonical) |
| Duplicate tokens | Deduplicate (first wins) |
| Internal whitespace runs | Collapse to single space between tokens |

---

## 11. customClass normalization timing

```text
Selected normalization timing: NORMALIZE ON READ (compile / export / canvas preview)

Reason:
  — Single canonical function `normalizeBlockCustomClass(raw)` at consumption boundaries
  — Persisted builder state keeps merchant raw string (undo/redo, UI field display unchanged)
  — Export and preview always agree
  — No silent persistence rewrite on every keystroke
```

**Not** NORMALIZE ON WRITE — avoids undo stack / UI surprises.

---

## 12. Deterministic customClass examples

| Input | Canonical output | Accepted? | Reason |
|-------|------------------|-----------|--------|
| `my-class` | `my-class` | YES | Valid single token |
| `foo bar` | `foo bar` | YES | Multiple tokens |
| `foo   bar` | `foo bar` | YES | Whitespace collapsed |
| `.foo` | `foo` | YES | Leading dot normalized |
| `foo foo` | `foo` | YES | Deduped |
| `pbn-test` | *(empty)* | NO tokens | Reserved prefix rejected |
| `foo:hover` | `foo` | PARTIAL | `:hover` token rejected; `foo` kept if `foo` alone valid |
| `#foo` | *(empty)* | NO | Selector token rejected |
| `foo.bar` | *(empty)* | NO | `.` treated selector-like (product policy) |
| `a/b` | *(empty)* | NO | `/` invalid |
| Unicode token e.g. `クラス` | `クラス` | YES | HTML-legal token |
| empty / whitespace | *(empty)* | N/A | Omit attribute |

```text
Track 2B.1 customClass contract fully deterministic: YES
```

---

## 13. Declaration parser inventory

| Candidate | Installed? | Runtime-safe? | Build-safe? | Declarations correct? | Bundle impact | Verdict |
|-----------|------------|---------------|-------------|----------------------|---------------|---------|
| **P1 — `CSSStyleDeclaration`** (`HTMLElement.style`) | Built-in | YES (browser + jsdom) | YES (`compilePopupRenderCss` runs client-side on save; Jest uses jsdom) | YES — handles quoted strings, `url()`, functions; **rejects** rule blocks | **Zero** (embed receives precompiled `renderCss`) | **SELECT** |
| P2 — PostCSS | devDependency only | Needs promotion to `dependencies` | YES | YES | ~30KB+ if bundled; unnecessary for embed | Reject for runtime |
| P3 — Repository utility | None | — | — | — | — | None exists |

---

## 14. Selected parser strategy

```text
Selected declaration parser strategy: P1

Exact library/API:
  Browser / jsdom HTMLElement.style (CSSStyleDeclaration)
  Pattern:
    1. sanitizeMarkupBreakout(raw)  // reuse markup strip from sanitizeCustomCss
    2. Reject if contains '{', '}', or line-start '@'
    3. const el = document.createElement('div'); el.style.cssText = declarationsOnly
    4. Enumerate el.style (indexed + camelCase properties) for accepted declarations
    5. Apply property policy + URL scheme scan on each value
    6. Serialize back to declaration string for renderCss emission

Why:
  — No new dependency
  — Correct handling of complex values (data URLs, quotes, calc)
  — Rule-block input fails at step 2 or yields zero declarations
  — Same engine merchants' browsers use
  — Embed bundle unchanged (compile at save time)

New parser dependency required: NO
```

**Supplement:** Post-step URL scheme regex scan on declaration **values** (P1 does not block `javascript:` URLs by itself).

---

## 15. Property-policy options

| Model | Summary | Verdict |
|-------|---------|---------|
| A — Strict allowlist | Large maintenance surface | Reject |
| **B — Focused denylist** | Block security + layout-escape properties; allow merchant self-harm | **SELECT** |
| C — Minimal restrictions | Only markup/security | Reject — insufficient for `position:fixed` on wrapper |

---

## 16. Selected property policy

```text
Selected property policy: B

Reason:
  — Merchants may hide blocks, animate, use z-index (self-harm allowed)
  — Must block layout escape and script injection vectors
  — Avoid unbounded allowlist maintenance
```

### Denied properties (exact)

| Property | Deny when | Class |
|----------|-----------|-------|
| `position` | value is `fixed` or `sticky` | BUILDER STABILITY / ARTIFACT ISOLATION |
| `behavior` | any | SECURITY (legacy IE) |
| `-moz-binding` | any | SECURITY |

All other valid `CSSStyleDeclaration` properties **allowed** unless value fails URL policy.

---

## 17. Security vs product guardrails

| Property/value | Allow/deny | Classification | Reason |
|----------------|------------|----------------|--------|
| `position: fixed` | DENY | BUILDER STABILITY | Escapes popup positioning context |
| `position: sticky` | DENY | BUILDER STABILITY | Scrollport escape in embedded popup |
| `position: absolute/relative/static` | ALLOW | — | Normal block layout |
| `z-index` | ALLOW | MERCHANT SELF-HARM | May overlay siblings; merchant choice |
| `display: none` | ALLOW | MERCHANT SELF-HARM | Hide own block |
| `visibility: hidden` | ALLOW | MERCHANT SELF-HARM | — |
| `pointer-events: none` | ALLOW | MERCHANT SELF-HARM | — |
| `overflow` | ALLOW | MERCHANT SELF-HARM | — |
| `transform` / `filter` | ALLOW | MERCHANT SELF-HARM | — |
| `animation` / `transition` | ALLOW | MERCHANT SELF-HARM | — |
| `content` | ALLOW | PRODUCT POLICY | Inert on `div` wrapper |
| `cursor` / `user-select` | ALLOW | MERCHANT SELF-HARM | — |
| `background-image: url(...)` | ALLOW with scheme policy | — | See §18 |
| `@import` | N/A (rejected at parse gate) | SECURITY | Not declaration-list |
| CSS custom properties | ALLOW | PRODUCT POLICY | Scoped to wrapper |
| `!important` | ALLOW | PRODUCT POLICY | Merchant override vs hostile theme |

---

## 18. URL-bearing value policy

```text
URL-bearing CSS values: ALLOW WITH SCHEME POLICY

Allowed schemes:
  https:
  http:
  data:image/*   (data: URLs whose MIME is image/*)
  data:font/*    (optional — font data URLs)
  relative paths  (/path, ./path, ../path, bare filename)

Blocked schemes:
  javascript:
  vbscript:
  data:text/html
  data:application/javascript
  file:
```

`blob:` — **DENY** (not useful in saved artifact; opaque origin concerns).

**Parser limitation stated:** P1 accepts `url(javascript:…)` unless post-scan removes declaration. **URL scheme scan is mandatory** after P1 parse.

`image-set()` — scan each `url()` inside.

---

## 19. `!important` policy

```text
Block customCss !important policy: ALLOW

Reason:
  — Merchant override intent against host theme
  — Internal family renderCss does not use !important for merchant presentation
  — Distinct from architecture rule forbidding internal cascade repair via !important
  — Predictable: only competes within same wrapper rule specificity
```

---

## 20. CSS custom-property policy

```text
CSS custom properties: ALLOW

Reason:
  — `--token: value` and `var(--token)` scoped to block wrapper subtree
  — No artifact escape
  — Useful for merchant theming within block
```

---

## 21. Deterministic customCss examples

| Input | Accepted | Rejected | Whole field rejected? |
|-------|----------|----------|----------------------|
| `color:red;` | `color: red` | — | NO |
| `color:red; padding:10px;` | both | — | NO |
| `.foo { color:red }` | — | all | YES (rule block gate) |
| `&:hover { color:red }` | — | all | YES |
| `@media (...) { ... }` | — | all | YES |
| `background-image:url(https://x/y.png)` | yes | — | NO |
| `background-image:url(javascript:alert(1))` | — | declaration | YES if only decl |
| `display:none` | yes | — | NO |
| `position:fixed` | — | declaration | partial omit |
| `color:red !important` | yes | — | NO |
| `--x:10px; width:var(--x)` | both | — | NO |
| malformed `color:` | — | bad decl | NO — omit bad, keep good |
| empty / whitespace | — | — | YES (omit compile) |

```text
Track 2B.2 customCss contract fully deterministic: YES
```

---

## 22. Cascade-order revalidation (production code)

**Verified paths:**

- `presentConfig` → `ensureRenderArtifactCss()` **before** `renderStepInto` (`runtime.ts` L338–343, L800–810)
- `hoistExportedStepStyles` appends step `<style>` nodes to `document.head` **after** `renderCss` already mounted
- `serializeLayout` cssBlock order ends with `formStyle.customCss` (`data-adflipr-popup-custom-css`) last among step styles
- Within `renderCss`, block custom chunk will be **last chunk** in `compilePopupRenderCss` (Track 2B decision — not yet implemented)
- Family compilers emit wrapper rules + more-specific painted rules (e.g. `buttonPresentation.ts` wrapper vs `button` painted)

```text
Exact verified cascade order:
  1. Host page theme CSS (external)
  2. Artifact renderCss (family presentation compilers, then block customCss chunk last within string)
  3. Hoisted per-step embedded styles (structural export sheets → … → form customCss last)
  4. RTE inline formatting on text nodes
  5. Structural inline on export nodes (non-merchant)
```

### Override answers (precise)

| Question | Answer |
|----------|--------|
| Block customCss overrides wrapper-level normal trait presentation | **YES** — same wrapper selector, later chunk in renderCss |
| Block customCss can indirectly affect descendants via inheritance | **YES** — `color`, `font-*`, etc. inherit |
| Block customCss overrides family painted-owner traits | **NO** — painted selectors more specific (`[block-id] button` vs `[block-id]`) |
| Block customCss overrides family hover rules | **NO** — hover targets painted `:hover` |
| Form customCss can override block customCss | **YES** — hoisted later; may use higher-specificity selectors |
| RTE inline can override block customCss | **YES** — for matching properties on text nodes |

**Product flag:** Wrapper-only S1 **cannot** directly override button background, input border, image dimensions on painted nodes — only wrapper box + inherited properties. UI must state this.

---

## 23. Wrapper-target usefulness by family

| Family | Bg on wrapper | Color | Font | Padding | Border | Width | Display | Inherited to descendants |
|--------|---------------|-------|------|---------|--------|-------|---------|------------------------|
| button | Behind align container | To button text if not set on painted | Yes | On wrapper box | On wrapper | Wrapper width | Wrapper layout | Partial |
| text | Behind RTE host | Inherited to text | Inherited | Wrapper | Wrapper | Wrapper | Wrapper | **Strong** |
| image | Behind image shell | Low | Low | Wrapper margin box | Wrapper | Wrapper | Wrapper | Low |
| text-input | Behind field shell | Label/input if unset | Inherited | Wrapper | Wrapper | Wrapper | Wrapper | Partial |
| product | Behind grid | Partial | Partial | Wrapper | Wrapper | Wrapper | Wrapper | Partial |
| spinner | Behind widget | Partial | Partial | Wrapper | Wrapper | Wrapper | Wrapper | Partial |

```text
Wrapper-only customCss useful across all exposed families: PARTIALLY

Required UI copy change: YES
  — Block Advanced "CSS" field must state declarations apply to the block container,
    not the inner button/input/image styling surface.
  — Inherited properties (color, font-family) may affect children.
  — Use form-level CSS for full selector rules.
  — Replace Spinner placeholder `.my-custom-class { }` with declaration example:
    e.g. `margin-top: 8px; border: 1px solid #ccc;`
```

S1 **not** reopened — usefulness partial but legitimate for margins, wrapper borders, inheritance.

---

## 24. Implementation split decision

```text
Track 2B.1 can ship independently: YES
Track 2B.2 depends on 2B.1: NO (logical only — can parallel, but recommended order 2B.1 first)

Recommended phase count: 2
Recommended order: 2B.1 → Mongo audit → 2B.2
```

2B.1 does not require 2B.2. No shared persistence change beyond same `style` object.

---

## 25. Final Track 2B.1 contract

| Item | Specification |
|------|---------------|
| **Persisted path** | `element.properties.style.customClass` (raw string) |
| **Normalization** | `normalizeBlockCustomClass(raw: string): string` per §10–12 |
| **Timing** | ON READ at export + canvas preview only |
| **Export owner** | `serializeElements` wrapper `div` — merge into `class` alongside structural classes (space-separated) |
| **Canvas/preview owner** | `CanvasBlockShell` preview branch outer wrapper `className` via `cn(..., normalizeBlockCustomClass(style.customClass))` |
| **Class merge order** | Structural/layout classes first, then normalized custom tokens |
| **Duplicates** | Deduped in normalization |
| **Invalid input** | Drop bad tokens; empty → omit custom classes (structural classes remain) |
| **Tests** | Unit: normalization table §12; export HTML `class` on `[data-adflipr-block-id]` for button, text, image, product, spinner; canvas preview snapshot |
| **Cleanup** | None beyond wiring; no export reader existed |
| **i18n** | Add helper under popup builder Advanced tab clarifying class tokens |

---

## 26. Final Track 2B.2 contract

| Item | Specification |
|------|---------------|
| **Persisted path** | `element.properties.style.customCss` (raw string) |
| **Parser** | P1 `CSSStyleDeclaration` + pre-gates + URL scan (§14) |
| **Property policy** | Denylist §16 |
| **Value policy** | URL schemes §18 |
| **!important** | ALLOW §19 |
| **Custom properties** | ALLOW §20 |
| **Selector** | `[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"]` |
| **Compiler ordering** | Final chunk in `compilePopupRenderCss` after all family compilers |
| **Multi-step duplicates** | Same block id across steps — existing presentation conflict rules; customCss must match or last-wins per existing block id collision policy |
| **Conflict behavior** | Later declaration in same rule string wins; across properties P1 order |
| **Canvas/preview parity** | Inject compiled block-custom rules into preview `renderCss` path (same compile function as save) |
| **Tests** | Unit: acceptance table §21; isolation (2 blocks, 2 artifacts, 2 runtimes); hostile `javascript:` URL; rule-block rejection |
| **Cleanup** | Remove misleading Spinner placeholder; add UI helper text |
| **Prerequisite** | Mongo audit complete OR audit returns zero rule-block rows |

---

## 27. Track 2B.1 readiness

```text
Track 2B.1 implementation readiness: READY
```

All contract fields deterministic. No Mongo gate. No new dependency.

---

## 28. Track 2B.2 readiness

```text
Track 2B.2 implementation readiness: READY AFTER MONGO AUDIT
```

Contract deterministic, but production syntax prevalence unknown; S1 will drop rule-block saved values.

---

## 29. Immediate next action

```text
Immediate next action: IMPLEMENT TRACK 2B.1

Reason:
  — 2B.1 contract fully deterministic
  — Mongo not required for customClass
  — Does not reinterpret customCss
  — Ships visible parity (class on wrapper) while audit runs in parallel before 2B.2
```

**Do not** implement in this phase.

---

## 30. Final verdict

**ACCEPT WITH FOLLOW-UP** — Prerequisites closed; 2B.1 ready; 2B.2 ready after read-only Mongo audit; C1/S1/Channel A unchanged.

**Production files modified:** **NO**
