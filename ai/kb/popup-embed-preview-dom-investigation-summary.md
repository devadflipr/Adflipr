# Popup embed & preview — DOM investigation summary

**Date:** 2026-07-05  
**Context:** Debugging popup CSS migration (Phases 2B–5E) via builder Preview, DevTools on `<head>` / embed HTML, and live WordPress storefront (adcarts.com).  
**Related:** [`popup-css-architecture-overall-summary-through-phase4.md`](./popup-css-architecture-overall-summary-through-phase4.md), [`popup-text-presentation-css-phase5d-report.md`](./popup-text-presentation-css-phase5d-report.md), [`popup-positioned-decorative-css-phase5e-acceptance-audit.md`](./popup-positioned-decorative-css-phase5e-acceptance-audit.md)

---

## Executive summary

| Environment | Migrated HTML | `renderCss` in `<head>` | Step export CSS | Overall |
|-------------|---------------|-------------------------|-------------------|---------|
| **Builder Preview** (stale bundle) | ❌ Old inline serializers | ❌ Count = 0 | ✅ Hoisted (5 tags) | **Broken for migration verification** |
| **Live embed** (adcarts.com, after Save) | ✅ Cross/text/button clean | ✅ Mounted (2 tags) | ❌ Webpack JS in `<style>` | **Migration OK; embed build bug remains** |

The save → API → storefront embed path is working. Builder Preview can mislead if the dev bundle is stale. Production embed script still leaks invalid CSS for `.export.css` step sheets.

---

## Architecture reference (what “correct” looks like)

### Two CSS channels

| Channel | Marker in `<head>` | Scope | Contents |
|---------|-------------------|-------|----------|
| **Artifact presentation** | `data-adflipr-popup-render-css="true"` | Popup-scoped, survives step changes | Surface, layout, column, button, text, cross, teaser, gamification |
| **Step export sheets** | `data-adflipr-popup-runtime-style="…"` | Per step mount; hoisted from step HTML | Floating-label, responsive, radio, device-css, custom-css, rich-text links (button) |

Mounting: `mountRenderArtifactCss()` (artifact) + `hoistExportedStepStyles()` (step sheets). See `embed/mountRenderArtifactCss.ts`, `embed/mountExportedStepStyles.ts`.

### Expected HTML shape (migrated blocks)

```text
[data-adflipr-popup-artifact="{popupId}"]
└── [data-adflipr-block-id="text-…"]          ← wrapper: structural inline only (flex-shrink, box-sizing)
    └── div                                     ← no font-size / white-space inline
        └── RTE HTML (may have inline spans)    ← properties.content
```

- **Cross:** bare `<button data-adflipr-popup-close>` — no inline position/color.
- **Button:** bare `<button>` — paint in `renderCss`; align wrapper may keep `width:100%; text-align:*` (structural).
- **Text block traits** (font-size, padding, alignment, etc.): `renderCss` on `[data-adflipr-block-id="…"]` and `> div`.

### Preview vs saved embed

| Path | Artifact scope id | `renderCss` source |
|------|-------------------|-------------------|
| Builder Preview | `pbn-preview` | Live `buildPreviewArtifactExport()` → embed runtime |
| Save / storefront | Real `popupId` | `metadata.renderCss` via embed API → runtime |

Creating a **new form** does not fix stale preview until **Save** (for API/embed) and **dev server restart / hard refresh** (for live preview compile).

---

## Problems identified

### P1 — Builder Preview: no `renderCss` in `<head>` (resolved by refresh)

**Symptoms (console on Preview open):**

```javascript
document.querySelectorAll('style[data-adflipr-popup-render-css]').length  // → 0
document.querySelectorAll('style[data-adflipr-popup-runtime-style]').length  // → 5
```

**Observed DOM (stale bundle):**

- Cross: full inline `position/top/right/…`
- Text inner div: `font-size:…; white-space:pre-wrap` inline
- Artifact: `pbn-preview`

**Root cause:** Browser running **stale JS bundle** (pre–5D/5E serializers; preview path sometimes omitted `renderCss` on embed config in older builds). Hoist still worked → step styles present, artifact CSS absent.

**Fix:** Restart `adflipr-app` dev server, hard refresh Preview tab, re-run console checks. Expect `render-css` count ≥ 1 and migrated HTML.

**Severity:** High for **local verification**; not a production save/embed defect.

---

### P2 — Production embed: `.export.css` sheets contain webpack JS, not CSS

**Symptoms (adcarts.com `<head>`):**

```html
<style data-adflipr-popup-runtime-style="data-adflipr-popup-floating-label-css" …>
// extracted by mini-css-extract-plugin
export default {};
</style>
```

Same pattern for `data-adflipr-popup-responsive-css`, `data-adflipr-popup-radio-css`.

**Root cause:** Production embed bundle (`https://adflipr.com/app/adflipr-popup-embed.js`) does not load `*.export.css` as raw strings. App dev build uses `craco.config.js` rule `type: 'asset/source'` for `.export.css`; embed production webpack build does not mirror that.

**Impact today:** Low for simple popups (text + button only). **High** when forms use floating labels, radio/checkbox groups, or shared embed responsive utilities.

**Fix needed:** Align embed webpack config with app — import `.export.css` as `asset/source` (or equivalent) so serialized step `<style>` blocks contain real CSS before hoist.

**Severity:** Medium (latent until input/choice blocks ship).

---

### P3 — Duplicate `renderCss` stylesheets on one page

**Symptoms:** Two identical `<style data-adflipr-popup-render-css>` tags with owners `popup-runtime-1` and `popup-runtime-2`.

**Root cause:** Same popup mounted by **two embed runtime instances** on one page:

1. **Inline embed** in page content (Elementor HTML widget, `.ad-embedded-campaign`)
2. **Overlay popup** (`#adflipr-popup-embed-root`, trigger e.g. “Click here”)

Each runtime calls `ensureRenderArtifactCss()` once. Rules are identical → redundant, not visually wrong.

**Fix (optional):** Dedupe by artifact id + css hash, or single shared stylesheet per document. Lifecycle/teardown hygiene only.

**Severity:** Low (cosmetic / minor perf).

---

### P4 — RTE / trait styling appears inline inside text content (by design, with duplication risk)

**Symptoms (live embed, correct migration):**

```html
<div>  <!-- no block-level font-size — good -->
  <div style="text-align: center;">
    <span style="background-color: transparent; color: rgb(2, 8, 23);">Hello World</span>
  </div>
</div>
```

**Explanation:**

| Layer | Source | Export |
|-------|--------|--------|
| Block traits (Style panel) | `properties.style` | `renderCss` (Phase 5D) |
| Rich text body | `properties.content` from `InlineEditor` (`innerHTML`) | Sanitized HTML **unchanged** — inline spans/divs preserved |

RTE inline styles exist because **mixed formatting** (per-word color, paragraph alignment in HTML) cannot be expressed as one block-level CSS rule without losing granularity. Phase 5D KB: *“RTE content → HTML (unchanged)”*.

**Duplication:** Block `textAlignment` / `textColor` may also appear in RTE HTML if the editor baked them into `content` while traits update the model. Usually harmless (cascade).

**Optional future work:** Export normalization to strip redundant inline from `content` when it matches block traits (not implemented).

**Severity:** Informational — not a migration bug.

---

### P5 — Inspecting wrong document for styles

**Symptom:** “No style in head” while popup visible.

**Causes:**

- Popup **Detail thumbnail** uses an **iframe** `srcDoc` — `renderCss` is in **iframe** `<head>`, not main app document.
- Preview closed → teardown removes mounted styles.

**Checklist:** Confirm Preview is open; search `data-adflipr-popup-render-css` in the **same document** as `[data-adflipr-popup-embed-content]`.

**Severity:** Diagnostic confusion only.

---

### P6 — Unsaved popup / old API payload

**Symptom:** Storefront or test-live shows old inline HTML, no `metadata.renderCss`.

**Cause:** Embed reads **saved** `htmlByStep` + `metadata.renderCss`. New editor state without **Save** does not update API.

**Fix:** Save form in builder; confirm PATCH metadata includes `renderCss` key.

**Severity:** Medium for QA workflow.

---

## Things verified working (adcarts.com, popup `6a4a9eeef1e9894c49471738`)

- ✅ Saved HTML: cross without inline paint; text inner shell without block `font-size`; button without inline paint.
- ✅ `<head>` contains real `renderCss` scoped to artifact id (surface, text `font-size:31px`, button, cross, mobile `@media`).
- ✅ Embed script loads from `adflipr.com/app/adflipr-popup-embed.js`; API `renderCss` pass-through from metadata.
- ✅ Structural wrapper inline only (`flex-shrink`, `box-sizing`, button align `width/text-align`).
- ✅ One hoisted step sheet with valid CSS (button RTE link rule).

---

## DevTools quick checks

Run while popup is visible in the **same document**:

```javascript
// Artifact presentation CSS (expect >= 1 on live embed)
document.querySelectorAll('style[data-adflipr-popup-render-css]').length

// Hoisted step CSS (expect >= 1 if step exports styles)
document.querySelectorAll('style[data-adflipr-popup-runtime-style]').length

// Embed shell
document.querySelector('[data-adflipr-popup-embed-content="true"]')

// Audit all Adflipr styles in head
[...document.head.querySelectorAll('style')].filter(s =>
  s.hasAttribute('data-adflipr-popup-render-css') ||
  s.hasAttribute('data-adflipr-popup-runtime-style')
).map(s => ({
  renderCss: s.hasAttribute('data-adflipr-popup-render-css'),
  runtimeKey: s.getAttribute('data-adflipr-popup-runtime-style'),
  owner: s.getAttribute('data-adflipr-popup-style-owner'),
  preview: (s.textContent || '').slice(0, 80),
}))
```

**Red flags:**

- `render-css` count = 0 on open Preview (after refresh) → bundle or config bug.
- Step style `textContent` starts with `// extracted by mini-css-extract-plugin` → P2 embed build bug.
- Text `> div` has `font-size` inline → old serializer / stale bundle.
- Cross has inline `top/right` → pre–5E export.

---

## Recommended follow-ups

| Priority | Item | Owner area |
|----------|------|------------|
| **P0** | Fix embed webpack `.export.css` → raw CSS in production `adflipr-popup-embed.js` | Embed build / deploy |
| **P1** | Document “Preview verification” = restart dev + hard refresh + Save for embed | KB / QA |
| **P2** | Optional: dedupe `renderCss` when multiple runtimes share same artifact | `embed/runtime.ts` |
| **P3** | Optional: RTE export normalization (strip trait-duplicative inline from `content`) | Serializer / 5D follow-up |
| **P4** | Phase 6+ block migrations (inputs, radio, etc.) — blocked on P2 for step sheets | Popup CSS phases |

---

## Key files

| Topic | Path |
|-------|------|
| Mount artifact CSS | `adflipr-app/src/layouts/PopupBuilder/embed/mountRenderArtifactCss.ts` |
| Hoist step styles | `adflipr-app/src/layouts/PopupBuilder/embed/mountExportedStepStyles.ts` |
| Embed runtime | `adflipr-app/src/layouts/PopupBuilder/embed/runtime.ts` |
| Preview config | `adflipr-app/src/layouts/PopupBuilder/utils/formPreviewEmbedPresentation.ts` |
| Save metadata | `adflipr-app/src/layouts/PopupBuilder/utils/popupBuilderState.ts` |
| Text serialize | `adflipr-app/src/layouts/PopupBuilder/components/form-builder/blocks/text/serialize.ts` |
| Text renderCss | `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/textPresentation.ts` |
| `.export.css` app rule | `adflipr-app/craco.config.js` |
| WP embed loader | `wordpress/includes/class-adflipr-popup-embed.php` |

---

## Conversation timeline (condensed)

1. Initial DOM inspection showed legacy inline text/cross + webpack JS inside `<style>` — looked like migration failure.
2. Console on builder Preview: `render-css = 0`, `runtime-style = 5`, artifact `pbn-preview` → stale preview bundle diagnosed.
3. Live adcarts.com after Save: migrated HTML + valid `renderCss` in `<head>` → save/embed pipeline confirmed.
4. RTE inline spans/divs in content clarified as intentional (`properties.content`), separate from block traits in `renderCss`.
5. Duplicate `renderCss` traced to inline + overlay dual runtime on same page.
