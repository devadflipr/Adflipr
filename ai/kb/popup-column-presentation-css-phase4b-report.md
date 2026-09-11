# Popup column presentation CSS — Phase 4B implementation report

**Status:** Implemented (2026-07-05)  
**Type:** Forward-only production migration — `[data-column-id]` → `renderCss`  
**Related:** [`popup-column-presentation-css-phase4a-inventory.md`](./popup-column-presentation-css-phase4a-inventory.md), [`popup-columns-container-css-phase3c-report.md`](./popup-columns-container-css-phase3c-report.md), [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md)

**Architecture mode:** Forward-only — no dual-path column presentation; obsolete embedded column responsive CSS removed.

---

## 1. Architecture decision

### Chosen strategy: **Option A — artifact-scoped attribute selectors (simplified)**

```css
[data-adflipr-popup-artifact="{artifactScopeId}"]
[data-column-id="{columnId}"] {
  /* base presentation */
}

@media only screen and (max-width: 480px) {
  [data-adflipr-popup-artifact="{artifactScopeId}"]
  [data-column-id="{columnId}"] {
    /* mobile overrides — identical selector specificity */
  }
}
```

### Why not Option B (compiled CSS classes)?

| Criterion | Attribute selectors | Generated classes |
|-----------|--------------------|--------------------|
| HTML complexity | Semantic attrs only | Extra `class` per column |
| Compiler complexity | Direct selector from model id | Class registry + HTML mutation |
| Debugging storefronts | `data-column-id` visible in DOM | Opaque `af-c-N` |
| gzip/Brotli | Repetitive tokens compress well | Slightly smaller raw, marginal gzip gain |
| Measured gzip (40 columns) | 51,356 raw → **1,968 gzip** | Not worth added machinery |

**Measured:** 40-column fixture — raw `renderCss` 51,356 bytes, gzip **1,968 bytes**. Selector repetition is a delivery concern only for uncompressed storage; compression reduces it dramatically.

### Why omit `layoutId` from selector?

`columnId` embeds layout identity (`{layoutId}-left|right|middle|single`). Adding `[data-adflipr-layout-id]` duplicates information and adds ~40 bytes per rule. **Artifact + `columnId` is sufficient** for isolation within a popup artifact.

### Why not reuse `buildColumnLayoutStyle()` in the compiler?

Forward-only principle: one canonical `normalizeColumnPresentation()` at the compiler boundary. Canvas still uses `buildColumnLayoutStyle()` for editor preview only. Export path uses the normalizer exclusively.

### Multi-step identity

`columnId` is the deduplication key. Same `columnId` across steps with identical normalized presentation → one CSS unit. Conflicting presentation → `ColumnPresentationConflictError`.

Repeated IDs across steps represent **intentionally shared logical columns** (same layout id/content duplicated across steps), not accidental collisions.

### Visibility

`hideOnDesktop` / `hideOnMobile` compiled into artifact-scoped `renderCss` visibility rules (no `!important`). Semantic data attrs remain on HTML.

---

## 2. Implementation

### New module

`adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/columnPresentation.ts`

```text
Builder columnStyles[columnId]
        ↓
normalizeColumnPresentation()
        ↓
NormalizedColumnPresentation { base, mobile }
        ↓
compileColumnPresentationRenderCss()
        ↓
renderCss (artifact-scoped)
```

### Composition

```text
serializePopupExport() → renderCss
├── compileFormSurfaceRenderCss()        (Phase 2B)
├── compileOuterLayoutRenderCss()        (Phase 3B)
├── compileColumnsContainerRenderCss()   (Phase 3C)
└── compileColumnPresentationRenderCss() (Phase 4B)
```

### HTML serializer

- Artifact path: `[data-column-id]` divs emit **no** presentation `style=""` (semantic attrs + hide flags only)
- Non-artifact path (gamification deferred): retains inline `buildColumnLayoutStyle()` until gamification surface migrates

### Deleted obsolete generation

- **Removed** entire column loop from `buildLayoutSectionResponsiveCss()` in `responsiveDeviceCss.ts`
- No `skipColumnPresentationResponsive` flag (never added)
- Embedded step HTML no longer contains duplicate column `@media` rules on artifact path

### Canvas

`buildColumnLayoutStyle()` in `ElementStyles.ts` — **KEEP** for `LayoutRenderer.tsx` editor canvas only.

---

## 3. Test evidence

### Commands

```bash
cd adflipr-app
npx craco test --watchAll=false --testPathPattern="columnPresentationRenderCss|columnPresentation.browser"
npx craco test --watchAll=false --testPathPattern="responsiveCssBaseline.browser"
npx craco test --watchAll=false --testPathPattern="columnsContainer|outerLayout|formSurface"
npx craco test --watchAll=false --testPathPattern="phase4bPayloadAccounting"
```

### Results (2026-07-05)

| Suite | Result |
|-------|--------|
| `columnPresentationRenderCss.test.ts` | 8/8 pass |
| `columnPresentation.browser.test.ts` | 12/12 pass |
| `responsiveCssBaseline.browser.test.ts` | 24/24 pass |
| Prior phase regression (form/outer/columns) | 78/78 pass |
| `phase4bPayloadAccounting.test.ts` | 5/5 pass |

---

## 4. Phase 0 scoreboard

| Metric | Before 4B | After 4B |
|--------|-----------|----------|
| Risk red | 9 | **8** |
| Controls green | 5 | **5** |
| Migrated risk green | 9 | **10** |

**Turned green:** `block-border-radius` (column-owned; same-target PCL-008 fixed)

**Remaining red:** `block-padding`, `block-margin`, cross×2, floating×2, teaser×2

---

## 5. Payload evidence (unified fixture)

| Layouts | Columns | Form | Outer | Columns container | Column presentation | **Total raw** | **Total gzip** |
|--------:|--------:|-----:|------:|------------------:|--------------------:|--------------:|---------------:|
| 1 | 2 | 713 | 221 | 1,294 | 1,091 | **3,322** | **675** |
| 5 | 10 | 713 | 1,109 | 6,298 | 5,283 | **13,406** | **984** |
| 10 | 20 | 713 | 2,220 | 12,563 | 10,527 | **26,026** | **1,338** |
| 20 | 40 | 713 | 4,450 | 25,143 | 21,047 | **51,356** | **1,968** |

~**526 bytes raw per column cell** for column presentation; gzip reduces total artifact CSS substantially.

---

## 6. Final architecture map

```text
Popup Builder Model (columnStyles per layout)
        ↓
normalizeColumnPresentation()
        ↓
compileColumnPresentationRenderCss()
        ↓
renderCss (hoisted once per artifact)
        ↓
Storefront runtime stylesheet
```

```text
HTML serializer
        ↓
[data-column-id] semantic DOM
        ↓
no presentation inline (artifact path)
```

---

## 7. Remaining CSS owners (post Phase 4B)

Ranked by architectural leverage:

| Priority | Owner | Phase 0 impact |
|----------|-------|----------------|
| 1 | **Block traits** (`[data-adflipr-block-id]`, inner button/input) | `block-padding`, `block-margin` |
| 2 | **Cross button** (`[data-adflipr-popup-close]`) | `cross-top`, `cross-right` |
| 3 | **Floating image** | `floating-bottom`, `floating-left` |
| 4 | **Teaser chip** | `teaser-padding`, `teaser-border-radius` |
| 5 | **Gamification surface** | Deferred from artifact `renderCss` |
| 6 | **Form custom CSS** | Merchant-authored |
| 7 | **Runtime shell CSS** | Overlay/banner/flyout (not artifact) |

---

## 8. Cleanup audit

| Symbol / path | Classification |
|---------------|----------------|
| `buildColumnLayoutStyle()` in `ElementStyles.ts` | **KEEP** — canvas editor |
| `buildColumnLayoutStyle()` in `serializeLayout.ts` | **KEEP** — gamification non-artifact export only |
| Column loop in `buildLayoutSectionResponsiveCss` | **DELETE** — done |
| `columnPresentation.ts` normalizer/compiler | **KEEP** — canonical export path |
| Legacy dual-path tests (no `artifactScopeId`) | **KEEP** — documents gamification/deferred path |
| Phase 4A `skipColumnPresentationResponsive` recommendation | **DELETE** — never implemented |

---

## 9. Stop conditions — none triggered

1. `[data-column-id]` is stable (`{layoutId}-{suffix}`)
2. Duplicate column IDs across steps = shared presentation (dedupe valid)
3. Builder model compiles deterministically via normalizer
4. Phase 3C slot mechanics cleanly separated (no overlap)
5. Payload acceptable after gzip (1,968 bytes for 40 columns total renderCss)
6. Owner-by-owner migration remains appropriate; each compiler is independent normalizer + emitter
