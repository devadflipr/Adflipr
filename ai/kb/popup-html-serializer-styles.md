# Popup HTML serializer — CSS layout

**Code:** `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/`

## Rule

**`serializeElements.ts` and `serializeLayout.ts` assemble HTML only.** They must not contain large CSS literals, `CSSProperties` object literals, or static `<style>` text.

All export styling lives under `popupHtmlSerializer/styles/`.

## Directory layout

```
popupHtmlSerializer/
  serializeElements.ts   # HTML assembly + block dispatch only
  serializeLayout.ts     # Form shell, columns, injected <style> tags
  styles/
    index.ts             # Barrel re-exports
    sheets/              # Static CSS for <style> blocks (*.export.css)
    inline/              # Trait-driven inline style builders (CSSProperties)
    markup/              # HTML fragment helpers (labels, floating-field wrappers)
```

## Where CSS goes

| Kind | Location | Loaded by |
|------|----------|-----------|
| Shared export rules (floating labels, embed overflow, radio) | `styles/sheets/*.export.css` | Webpack `asset/source`; imported in `styles/sheets/index.ts` |
| Per-block inline `style=""` (trait/model driven) | `styles/inline/*.ts` — `build*Style()` helpers | `serializeElements.ts` via `./styles` |
| Static inline constants (timer digits, spinner overlay, coupon copy btn) | Same `styles/inline/*.ts` as named exports | `serializeElements.ts` |
| Markup helpers (floating label HTML, field names) | `styles/markup/*.ts` | `serializeElements.ts` |

## Inline vs sheet

- **Sheets** — CSS that is identical for every export and safe as a class-based block (e.g. `.pbn-floating-field`). Injected once in `serializeLayout.ts` inside `<style>`. Loaded from `styles/sheets/*.export.css`; import constants via `floatingLabelExportStyles.ts`.
- **Inline builders** — Values derived from element traits, device (`ctx.device`), or model attributes. Email-safe export uses inline styles; builders return `CSSProperties` and callers pass them through `styleAttr()`.

## Adding a new block

1. Add `buildMyBlockStyle(...)` (and constants if needed) in the appropriate `styles/inline/*.ts` file — group by domain (inputs, media, commerce, etc.).
2. Export from `styles/index.ts` (via barrel).
3. In `serializeElements.ts`, import builders from `./styles` and call them; no raw style objects in the serializer.
4. If the block needs shared class-based CSS, add `styles/sheets/my-feature.export.css` and export a trimmed constant from `styles/sheets/index.ts`.

## Radio export (preview + adcarts)

Hybrid — internal sheet + trait inline on inputs + style hoisting at mount.

| Piece | Location |
|-------|----------|
| Checked dot, `:checked` border, focus ring | `styles/sheets/radio.export.css` → `POPUP_RADIO_EXPORT_CSS` |
| Injected in export HTML | `serializeLayout.ts` → `<style data-adflipr-popup-radio-css>` |
| Selected-state CSS variables | `buildRadioChoiceCssVars()` on radiogroup wrapper |
| **Unselected border** (trait `outlineColor`) | `buildRadioInputExportStyleAttr()` — inline `border: 1px solid {color} !important` on each `<input>` |
| Classes | `pbn-export-native-radio-scope`, `pbn-display-native-radio`, `pbn-display-radio-row` |
| Serializer | `blocks/radio/serialize.ts` |

**Why inline border on inputs:** adcarts / WooCommerce themes often ship `input { border: none !important }`. Scoped internal CSS alone is not enough for unselected outline; trait-driven inline `!important` is required.

**Style hoisting:** After `container.innerHTML = stepHtml`, embed runtime and preview mount call `hoistExportedStepStyles()` (`embed/mountExportedStepStyles.ts`) to **clone** export `<style>` nodes into `document.head` and remove originals from the container. Otherwise radio/floating-label sheets may not apply. Full lifecycle (dismiss cleanup, multi-popup keys, isolation): [`popup-css-lifecycle-audit.md`](./popup-css-lifecycle-audit.md).

**Canvas vs export:** Builder uses `.pbn-root` + `pbn-canvas-native-radio-scope` in `popup-builder.css`. Preview/embed do not load that scope.

**Republish** popup on live site after serializer changes (stored `htmlByStep`).

## Build / test

- **Webpack (craco):** `*.export.css` uses `type: 'asset/source'` so imports resolve to CSS strings.
- **Jest:** `src/test-utils/exportCssTransform.js` mirrors the same behavior.

## Related

- `ai/kb/popup-rendering-css-architecture.md` — **target** storefront CSS artifact (`renderCss`); review mandatory for export/embed changes
- `ai/kb/popup-css-lifecycle-audit.md` — as-is CSS lifecycle (hoisting, cascade PCL-008)
- `ai/kb/popup-html-serialization.md` — export pipeline and security
- [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md) — block ownership audit
- `ai/verification/popup-rendering-css-architecture-checklist.md` — PR review checklist
- `.cursor/rules/popup-html-serializer-styles.mdc` — Cursor trigger for this folder
- `.cursor/rules/popup-rendering-css-architecture.mdc` — Cursor trigger for serializer + embed CSS architecture
