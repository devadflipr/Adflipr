# Popup HTML serialization (export security)

**Code:** `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/`

Popup Builder saves a serialized HTML fragment as `htmlContent` on the popup record. Thumbnails, detail preview, and future embed runtime consume this stored markup — **not** the live canvas DOM.

## Pipeline

```
PopupBuilderSnapshot (save)
  ↓
serializePopupHtml()
  ↓
serializeStepHtml → serializeElements + serializeLayout
  ↓
serializeElement → POPUP_BLOCK_SERIALIZERS map (`blockRegistry.ts`) + wrapper
  ↓
popupHtmlSanitize (URLs, rich text, custom CSS)
  ↓
htmlContent (API) → thumbnailHtml (list) / embed
```

## Security model

Sanitization runs **on save** (serializer), not when rendering thumbnails in the app.

| Surface | Rule |
|---------|------|
| Rich text / labels | `sanitizeRichTextHtml` — allow basic formatting tags; strip scripts, iframes, event handlers, `javascript:` hrefs |
| `href` / navigation | `sanitizeNavigationUrl` — http(s), mailto, tel, relative, `#`; block `javascript:` / `vbscript:` |
| `src` (img, video) | `sanitizeMediaSrc` — http(s), relative, `data:image/*`; block script schemes and non-image `data:` |
| YouTube embed | Only constructed embed URLs from validated video IDs (no passthrough iframe src) |
| `formStyle.customCss` | `sanitizeCustomCss` — neutralize `</style>`, `<script`, HTML comments before wrapping in `<style>` |
| `formStyle.customJs` | **Not supported** — CSS-only embed policy; legacy values stripped on load/save; no JS tab in builder |
| Thumbnail iframe | `srcDoc` + `sandbox="allow-same-origin"` (no `allow-scripts`) |

## Phase 3 — canvas parity (export blocks)

Serializer coverage aligned with canvas where blocks exist or have property panels:

| Block | Export behavior |
|-------|-----------------|
| `phone-input` | Country `<select>` + divider + `tel` input (matches canvas shell) |
| `button` | RTE HTML via `sanitizeRichTextHtml` when `rteEnabled !== false` |
| `feedback` | Question, rating/NPS/emoji/thumbs options, min/max labels, additional text |
| `floating-image` | Absolutely positioned decorative image (`position`, margins, optional link) |
| `image` | Flex alignment from `elementPosition`; box shadow from traits |
| `check-box` | `accent-color` from `fillColor` / background |
| `coupon` | Copy button when `showCopyButton !== false` |
| `spinner` | Canvas wheel + `data-adflipr-popup-spinner-config` JSON (slices, probabilities, styles); embed runtime initializes click-to-spin |

Blocks without a canvas renderer yet (`feedback`, `floating-image`) still export static HTML from property defaults so thumbnails/embed are ready when canvas catches up.

## Phase 4 — multi-step export

On save, `serializePopupExport()` produces:

| Field | Storage | Purpose |
|-------|---------|---------|
| `htmlContent` | Popup document root | Primary step HTML — **list thumbnail** (desktop) |
| `metadata.htmlByStep` | JSON string `Record<stepId, html>` | Responsive HTML per step (`@media` device CSS in export) |
| `metadata.htmlPrimaryStepId` | Step id string | Which step backs `htmlContent` |

**Step selection:** `resolveExportableStepIds()` walks `snapshot.steps` (visible only), then any extra keys in `formElements`. Teaser uses block HTML when present; otherwise `serializeTeaserHtml(formStyle)`.

**Read helpers:** `parseHtmlByStep(metadata)`, `parseHtmlPrimaryStepId(metadata)` in `exportMetadata.ts`; re-exported from `popupBuilderState.ts`.

## Phase 5 — embed runtime

**Script:** `public/adflipr-popup-embed.js` (built from `embed/index.ts` via `npm run build:popup-embed`; included in main `build` / `build:ci`).

**Public API:** `GET /api/v1/popups/embed/{popupId}` → `{ popupId, htmlPrimaryStepId, htmlByStep, renderCss, version, type }` (published popups only). Raw transport may omit `renderCss`; embed runtime normalizes to `PopupEmbedConfig.renderCss: string` (empty string when absent).

**Loader:** `embedSnippet.ts` injects script with optional `data-adflipr-popup-api`; `embed/index.ts` reads it when present.

**Mount points:**
- `data-adflipr-popup="{popupId}"` — overlay popup (optional mount; script auto-discovers)
- `.ad-embedded-campaign[data-campaign-id]` — inline embed

**Step transitions:** Buttons export `data-adflipr-popup-action` / `data-adflipr-popup-action-step`. Close uses `data-adflipr-popup-close`. Form submit → `POST /popups/events` (SUBMIT) → `thank-you` step when present.

**Gamification spinner:** Exported markup includes `data-adflipr-popup-spinner="true"` and `data-adflipr-popup-spinner-config` (JSON: slices with label/probability/coupon/colors, optional `action` / `redirectStep` / `redirectUrl`, wheel style). Embed runtime (`embed/spinner.ts`) draws the wheel; spin is triggered only by CTA buttons (`submit` / `next-step`) via `requestSpinWheel`. Post-spin navigation is owned by the winning slice (SPIN-011); Close on the button still wins over Step. One spin per session when `allowMultipleSpin` is false.

**Telemetry:** VIEW on first open; SUBMIT on form submit (`visitorId` + `sessionId` per analytics spec).

**Global:** `window.AdfliprPopup.init()` / `.mount()`; auto-bootstrap on `DOMContentLoaded`.

**Local demo:** `/app/popup-embed-demo?id=POPUP_ID` — React route with real `GET /api/v1/popups/embed/{id}`. Open from embed Code panel (“Test live”) or `http://localhost:3000/app/popup-embed-demo?id=…`. Requires backend on `:8080` (CRA proxy) and a **published** popup with saved `htmlByStep`.

## Builder fullscreen preview vs embed

| Part | Fullscreen `FormPreview` | Storefront embed |
|------|--------------------------|------------------|
| Popup steps | Live `buildPreviewArtifactExport` → `serializePopupExport` → `PopupEmbedRuntime.renderStepInto` | Saved `htmlByStep` from API → `renderStepInto` |
| Teaser chip | `resolvePreviewTeaserHtml` / `serializeTeaserHtml()` when teaser step has no blocks | `serializeTeaserHtml()` in `htmlByStep['teaser']` |
| Presentation shell | Same `PopupEmbedRuntime` hosts (`previewMode`) | `PopupEmbedRuntime` hosts |

See [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md) and [`popup-builder-rendering-pipeline.md`](./popup-builder-rendering-pipeline.md) for preview vs storefront gaps.

## Custom code policy (CSS-only)

| Surface | Custom CSS | Custom JavaScript |
|---------|------------|-------------------|
| Builder edit canvas | Not injected (avoids breaking editor) | Not supported |
| Builder preview | Applied (unsanitized live edit) | Not supported |
| Exported HTML / embed / thumbnail | Sanitized `<style>` via `serializeLayout` | **Never exported** |

**Rationale:** Storefront embed injects HTML into the merchant origin. Merchant-supplied JS would run with full page privileges (XSS). CSS is sanitized on save; interactive behavior uses Adflipr embed runtime only (step transitions, floating labels, telemetry).

**Legacy:** `formStyle.customJs` in old `builderState` is stripped on load/save. Re-save to persist cleaned state.

## Targeting (Display + Target panels)

| Concern | Panel | Persisted in | Embed / server |
|---------|-------|--------------|----------------|
| When to show (delay, scroll, exit, click) | **Display** | `formStyle.displaySettings` | `PopupEmbedTriggerSupport` |
| Device (all / desktop / mobile) | **Display** → Display On | `displaySettings.displayOn` | Server `deviceMatch` |
| Frequency cap | **Display** | `displaySettings.frequency` | Server + client localStorage |
| URL show/hide lists | **Target** | `formStyle.targetSettings` | `urlMatch` |
| Geo locations | **Target** | `targetSettings.locations` | Server-side (when enabled) |

**Removed:** standalone **Rules** panel — targeting lives in **Display** and **Target** panels only (`targetSettings` + `displaySettings` in metadata).

## Export markers

- `data-adflipr-popup-export="true"` on `<form>`
- `data-adflipr-popup-surface-width` — layout width for thumbnail scaling
- `data-adflipr-popup-custom-css="true"` on injected `<style>` when custom CSS is present
- `data-adflipr-popup-spinner` / `data-adflipr-popup-spinner-config` — gamification wheel (see Phase 5)

## Tests

- `utils/__tests__/popupHtmlSanitize.test.ts` — unit XSS fixtures
- `utils/__tests__/popupHtmlSerializer.test.ts` — integration through `serializePopupHtml` + multi-step export
- `utils/__tests__/popupEmbedRuntime.test.ts` — step transitions + VIEW telemetry

## Related

- `ai/kb/popup-block-registry.md` — **content block contract** (builder / serializer / runtime hooks, new-block checklist)
- `ai/kb/popup-block-registry-phases-0-2.md` — **Phase 0–2 implementation plan** (runtime registry, serializer map)
- `ai/kb/popup-html-serializer-styles.md` — **CSS layout rule** (sheets vs inline builders; no CSS in `serializeElements.ts`)
- [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md) — block DOM ownership (Phase 5A)
- `ai/kb/popups-api.md` — API fields (`htmlContent`, `thumbnailHtml`)
- `ai/kb/popup-builder-structure-dnd.md` — builder structure
