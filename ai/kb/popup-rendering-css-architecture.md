# Popup Rendering CSS Architecture

**Status:** **Current** — forward-only artifact architecture (Phases 2B–4D implemented)  
**Scope:** Popup Builder serializer, compiled popup artifacts, storefront runtime (Shopify + WordPress)  
**Decision type:** Rendering architecture  
**Primary goal:** Responsive rendering that is correct, predictable, isolated — single scoped compilation path  

**Implementation reports:** [`popup-column-presentation-css-phase4d-report.md`](./popup-column-presentation-css-phase4d-report.md) (cleanup), Phases 2B–4B owner reports  
**Historical audit:** Phase 4C recommendations were implemented in Phase 4D — see [`popup-column-presentation-css-phase4d-report.md`](./popup-column-presentation-css-phase4d-report.md). Block ownership: [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md).  

**Current implementation (as-is):** [`popup-css-lifecycle-audit.md`](./popup-css-lifecycle-audit.md), [`popup-builder-rendering-pipeline.md`](./popup-builder-rendering-pipeline.md)  
**Review checklist:** [`ai/verification/popup-rendering-css-architecture-checklist.md`](../verification/popup-rendering-css-architecture-checklist.md)  
**As of:** 2026-07-04

---

## 1. Executive decision

Adflipr popup artifacts should use the following rendering contract:

```text
HTML = structure, content, identity, runtime hooks

CSS  = all visual presentation

JS   = behavior
```

The target compiled artifact should be:

```text
Popup Artifact
├── htmlByStep
├── renderCss
├── serializerBuild
├── contractVersion
└── builderSchemaVersion
```

The storefront runtime should own only:

```text
Runtime
├── JavaScript behavior
└── Minimal shell infrastructure CSS
```

The popup artifact should own:

```text
renderCss
├── Merchant-selected design values
├── Layout styles
├── Responsive desktop/mobile styles
├── Block rendering mechanics
├── Pseudo-state styles
├── Animations
├── Popup-specific custom CSS
└── CSS required to interpret the artifact HTML
```

The final target is:

```text
No rendering style="" attributes in newly compiled popup HTML.
```

Temporary inline styles may remain only during backward-compatible migration.

---

## 2. Why this decision exists

Adflipr has made an explicit product decision:

> Desktop and mobile always share the same content.

Responsive editing changes presentation, not content.

Therefore the rendering model should be:

```text
One content model
        ↓
One HTML structure
        ↓
Responsive CSS presentation
```

It should **not** be:

```text
Desktop HTML + Mobile HTML
```

It should **not** depend on:

```text
Desktop presentation inline
+
Mobile presentation in stylesheet
+
!important to force the two systems to cooperate
```

Responsive design is fundamentally a CSS responsibility. The compiled artifact should contain one stable HTML structure and one complete stylesheet capable of rendering that structure at every supported viewport.

**Evidence for the cascade problem today:** [`popup-css-lifecycle-audit.md`](./popup-css-lifecycle-audit.md) §12 (PCL-008) — ~55–65% of `@media` trait rules may not override desktop inline values.

---

## 3. Current architecture (Phase 4D — forward-only compilation)

Production compilation uses a single scoped artifact path:

```text
serializePopupExport(snapshot, { artifactScopeId })  // required, non-blank
        ↓
HTML — structure + identity only (migrated owners)
        ↓
compilePopupRenderCss()
    ├── compileFormSurfaceRenderCss
    ├── compileOuterLayoutRenderCss
    ├── compileColumnsContainerRenderCss
    ├── compileColumnPresentationRenderCss
    └── compileGamificationRenderCss (when spinner present)
        ↓
Embedded step CSS — buildEmbeddedStepResponsiveCss() (unmigrated owners only)
        ↓
Runtime — mountRenderArtifactCss + hoistExportedStepStyles(unmigrated)
```

Migrated owners (form surface, outer layout, columns container, column presentation, gamification shell) emit **identity attributes only** in HTML; presentation lives in scoped `renderCss`.

Unmigrated owners (block traits, cross/floating, product, phone, visibility, custom CSS, teaser, etc.) still emit embedded per-step `<style>` blocks hoisted at runtime.

**Historical evidence** for the pre-4D hybrid inline + embedded model is summarized in Phase 4D/4E reports (not repeated here).

Example of the **old** problem (still true for unmigrated block traits):

```html
<div
  data-adflipr-block-id="title-1"
  style="padding: 24px; border-radius: 12px;"
>
  Sale
</div>
```

with embedded `@media` rules that may lose to inline desktop values — see §4.

---

## 4. The current cascade correctness problem

CSS stylesheet declarations do **not** override inline styles merely because the stylesheet selector has higher specificity.

Given:

```html
<div style="padding: 24px;">
```

and:

```css
@media (max-width: 480px) {
  form[data-adflipr-popup-export="true"] div {
    padding: 12px;
  }
}
```

the computed result remains `padding: 24px`. The mobile declaration loses to the inline desktop declaration.

The current implementation compensates **inconsistently**:

| Mechanism | Share (approx.) | Reliable vs inline? |
|-----------|-----------------|---------------------|
| `@media` + `!important` | 35–45% | Yes |
| Specificity only | 55–65% | No (when same property inlined) |

**Known reliable categories:** layout column stack, product horizontal/grid, gamification, hide-on-device, phone select `max-width`.

**Known risk categories:** form surface padding/margin/radius/width; layout/column/block padding/margin/radius/shadow/gap; cross/floating-image offsets; teaser padding/radius.

Current tests often verify that responsive **CSS strings exist**, not that **computed mobile styles** win the cascade.

```text
Generated CSS exists ≠ Responsive CSS is applied
```

This is the core architectural reason for changing the CSS model.

---

## 5. Why not fix everything with `!important`?

A tactical patch:

```text
Desktop → inline
Mobile  → @media + !important
```

would make many current rules work but is **not** the preferred long-term architecture.

Permanent `!important` escalation creates:

```text
Inline desktop → Mobile !important → Custom CSS !important → Theme wars → Future features harder
```

The cleaner solution: base and responsive values in the **same** cascade system (stylesheet only, no presentation inline).

---

## 6. Target architecture

The serializer compiles presentation into one popup-owned stylesheet.

**Example HTML (structure only):**

```html
<form
  data-adflipr-popup-export="true"
  data-adflipr-popup-artifact="popup-123"
>
  <section data-adflipr-layout-id="layout-1">
    <div data-adflipr-block-id="title-1">Sale Today</div>
    <button data-adflipr-block-id="button-1" data-adflipr-action="submit">
      Shop Now
    </button>
  </section>
</form>
```

**Matching `renderCss`:**

```css
[data-adflipr-popup-artifact="popup-123"]
[data-adflipr-layout-id="layout-1"] {
  padding: 24px;
  border-radius: 16px;
}

[data-adflipr-popup-artifact="popup-123"]
[data-adflipr-block-id="title-1"] {
  font-size: 40px;
  margin-bottom: 16px;
}

@media only screen and (max-width: 480px) {
  [data-adflipr-popup-artifact="popup-123"]
  [data-adflipr-layout-id="layout-1"] {
    padding: 12px;
    border-radius: 8px;
  }

  [data-adflipr-popup-artifact="popup-123"]
  [data-adflipr-block-id="title-1"] {
    font-size: 24px;
    margin-bottom: 10px;
  }
}
```

Desktop and mobile participate in one stylesheet cascade. No inline presentation conflict. No general `!important` strategy required.

---

## 7. CSS ownership model

### 7.1 Runtime-owned CSS

Small infrastructure only:

```text
Overlay host mechanics
Viewport positioning
Scroll-lock
Loading state
Shell lifecycle
Accessibility infrastructure
```

**Test:** Can this CSS change without changing the visual meaning of an **old** compiled artifact? If yes → runtime. If no → artifact.

### 7.2 Artifact-owned CSS (`renderCss`)

Everything required to render generated HTML correctly:

```text
Merchant colors/typography/spacing/dimensions/borders/shadows
Layout + responsive rules
Floating-label, product, spinner, radio mechanics
Pseudo-states, animations
Block structural rendering
Artifact-compatible custom CSS
```

**Rule:** If HTML generated today depends on this CSS, freeze it with the HTML.

```text
2026 HTML + 2026 matching CSS   (not 2026 HTML + 2028 shared rendering CSS)
```

---

## 8. Field naming: `renderCss` (not `sharedCss`)

```text
renderCss / artifactCss
```

= complete CSS required to visually interpret **this** compiled popup artifact.

Stored **once per popup**, not per step, not globally shared across popups.

```json
{
  "artifact": {
    "htmlByStep": { "step-1": "<form>...</form>" },
    "renderCss": "...",
    "serializerBuild": "...",
    "contractVersion": 1
  }
}
```

---

## 9. CSS lifecycle (target)

### Initialization

```text
Fetch artifact → Mount renderCss once → Mount step HTML → Attach behavior
```

### Step transition

```text
Replace step HTML only — renderCss remains mounted
```

### Temporary hide

```text
hide() — DOM may remain; renderCss remains mounted
```

### Destroy

```text
destroy() → Remove popup DOM → Remove exactly this instance's renderCss → Remove listeners
```

**Invariant:**

```text
Mount artifact  → Mount one owned stylesheet
Destroy artifact → Remove exactly that owned stylesheet
```

---

## 10. Multi-step popups

**Target:**

```text
Popup Artifact
├── renderCss     ← once
├── Step 1 HTML
├── Step 2 HTML
└── Step 3 HTML
```

**Not:** duplicate structural `<style>` / CSS inside every `htmlByStep` entry (see PCL-005 in lifecycle audit).

---

## 11. Responsive rendering contract

```text
Same HTML + base CSS + @media overrides
```

For every responsive-capable property:

```text
Desktop/base → base rule in renderCss
Mobile       → @media rule in renderCss
```

No serializer decision: “inline or internal?” — **all presentation → renderCss**.

---

## 12. Stable element identity

Required stable hooks for selectors:

```text
Popup artifact root
Step
Layout
Column
Block
Important internal elements (labels, inputs, etc.)
```

Example identity model:

```html
<form data-adflipr-popup-artifact="popup-123">
<section data-adflipr-layout-id="layout-1">
<div data-adflipr-column-id="column-1">
<div data-adflipr-block-id="block-1">
```

Selectors must be deterministic, popup-scoped, stable across serialization, independent of DOM position.

**Avoid** styling contracts based primarily on `:nth-child(...)` or deep positional selectors.

Existing `data-adflipr-*` contract: [`popup-embed-contract.md`](./popup-embed-contract.md).

---

## 13. CSS isolation contract

Every generated rule scoped beneath artifact root:

```css
[data-adflipr-popup-artifact="..."] ...
```

**Forbidden unscoped:**

```css
input {}  textarea {}  button {}  img {}  form {}
input::placeholder {}  textarea::placeholder {}  * {}
```

Bidirectional isolation:

```text
Adflipr CSS must not leak into merchant DOM
Merchant CSS should have minimal ability to change Adflipr rendering
```

Namespace where relevant: classes, CSS variables, `@keyframes` names, animation names.

---

## 14. Custom CSS

Merchant custom CSS must not be blindly concatenated into `document.head`.

Requires safe scoping (prefer real CSS parser over regex):

```css
/* Merchant input */
.title { color: red; }

/* Compiled */
[data-adflipr-popup-artifact="popup-123"] .title { color: red; }
```

`@keyframes` need namespacing, not root-prefix-only. Dedicated compatibility workstream (Phase 5).

---

## 15. Why HTML and CSS are separate fields

| | HTML | CSS |
|---|------|-----|
| Lifecycle | Per-step mount/replace | Mount once per artifact |
| Fields | `htmlByStep` | `renderCss` |

**Invariant:** One compilation → matching HTML + CSS → one artifact version.

---

## 16. Backward compatibility (runtime read boundary)

**Compilation (Phase 4D forward-only):** All new saves compile via `serializePopupExport(..., { artifactScopeId })`. No dual-path serializer, skip flags, or gamification legacy branch.

**Runtime consumption:**

```text
Untrusted embed API payload
    ↓ normalizeEmbedConfig()
PopupEmbedConfig.renderCss: string   // always present; '' when absent/malformed
    ↓
ensureRenderArtifactCss() mounts CSS when non-empty; no migrated-owner inline fallback
```

**Stored artifacts:** Backend may omit `metadata.renderCss` for pre-migration popups. Thumbnail/list consumers use `normalizeCompiledPreviewArtifact()` which pairs HTML with `renderCss: ''` when CSS is missing — presentation then relies on unmigrated embedded step CSS only.

Republishing recompiles the full artifact atomically (HTML + renderCss from one `serializePopupExport` call).

---

## 17. Migration strategy

| Phase | Goal |
|-------|------|
| **0** | Computed-style browser tests (desktop + mobile viewport) — baseline — **done:** [`popup-responsive-css-phase0-baseline.md`](./popup-responsive-css-phase0-baseline.md) |
| **1** | Introduce `renderCss`, metadata, ownership id; runtime mount/teardown without removing inline — **contract:** [`popup-render-artifact-contract-phase1a.md`](./popup-render-artifact-contract-phase1a.md) |
| **2** | Migrate one low-risk block to renderCss-only presentation |
| **3** | Surface, layout, column, responsive layout rules → renderCss — **3C report:** [`popup-columns-container-css-phase3c-report.md`](./popup-columns-container-css-phase3c-report.md); **4B report:** [`popup-column-presentation-css-phase4b-report.md`](./popup-column-presentation-css-phase4b-report.md) |
| **4** | Block families incrementally (text, button, image, inputs, product, gamification, teaser, …) |
| **5** | Safe custom CSS scoping |
| **6** | New artifacts: no rendering `style=""`; legacy still supported |

---

## 18. Testing requirements

1. **Serializer** — stable identities in HTML; expected rules in `renderCss`
2. **Computed-style** — real browser cascade (not string contains)
3. **Visual parity** — builder preview vs serialized runtime at desktop + mobile
4. **Lifecycle** — one stylesheet per artifact; step changes; hide/reopen; destroy; multi-popup independence

---

## 19. Rejected alternatives

| Alternative | Verdict |
|-------------|---------|
| Keep hybrid inline + `@media` permanently | Rejected as target — cascade conflicts |
| `!important` on every mobile rule | Tactical patch only — not final architecture |
| Two HTML trees (desktop/mobile) | Rejected — product shares content |
| All CSS in live runtime | Rejected — breaks old artifact compatibility |
| `sharedCss` per popup | Rejected naming — use `renderCss` |
| Shadow DOM | Not required |
| iframe rendering | Not required |

---

## 20. Architecture invariants (non-negotiable for new work)

1. Desktop and mobile share one content model and one HTML structure.
2. New artifact HTML does not own visual presentation.
3. All popup rendering presentation compiles into `renderCss`.
4. `renderCss` is generated and versioned with matching HTML.
5. Every artifact CSS rule is popup-scoped.
6. Runtime owns behavior, not merchant-specific rendering.
7. Runtime shared CSS remains minimal and backward-compatible.
8. Responsive correctness verified with computed browser styles.
9. Popup mounts artifact stylesheet once; destroy removes exactly its own stylesheet.
10. Legacy artifacts remain renderable throughout migration.

---

## 21. Final architecture diagram

```text
BUILDER STATE (source of truth)
        │ compile
        ▼
POPUP ARTIFACT
├── htmlByStep      (structure, content, identity, hooks)
├── renderCss       (all presentation)
├── serializerBuild
├── contractVersion
└── builderSchemaVersion
        +
EMBED RUNTIME
├── behavior, lifecycle, triggers, analytics
└── minimal shell infrastructure CSS

Browser:
  Fetch → Mount renderCss once → Mount step HTML → Behavior
  Step change → replace HTML only
  Destroy → remove HTML + owned renderCss
```

---

## 22. Final decision

Migrate toward:

```text
One responsive HTML structure
+ One complete popup-scoped renderCss artifact
+ One shared backward-compatible JavaScript runtime
```

**Phase 4D (2026-07-05):** Migrated owners compile exclusively via scoped `renderCss`. Unmigrated owners remain in embedded step `<style>` blocks until their migration phases. Legacy dual-path compilation has been removed from production code.

**Primary driver:** correctness — desktop inline + mobile `@media` without a unified cascade.

**Goal:**

> One HTML rendering contract, one CSS cascade, one artifact lifecycle.

---

## Related KB

| Document | Relationship |
|----------|----------------|
| [`popup-css-lifecycle-audit.md`](./popup-css-lifecycle-audit.md) | As-is audit; motivates this target |
| [`popup-html-serializer-styles.md`](./popup-html-serializer-styles.md) | Current serializer CSS layout rules |
| [`popup-html-serialization.md`](./popup-html-serialization.md) | Export security + current artifact |
| [`popup-embed-contract.md`](./popup-embed-contract.md) | `data-adflipr-*` hooks |
| [`popup-runtime-v2-design.md`](./popup-runtime-v2-design.md) | Runtime modules; update as `renderCss` lands |
| [`wordpress-popup-rendering-audit.md`](./wordpress-popup-rendering-audit.md) | WP loader only; unchanged |
