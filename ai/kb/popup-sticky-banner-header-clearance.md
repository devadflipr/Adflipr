# Sticky banner — clearing theme headers (KB)

**Status:** Active guardrail (see **ADR-005** in `decisions.md`).

---

## Problem

Top **sticky banner** campaigns render as a **fixed overlay** at the viewport top (`position: fixed; top: 0`). That covers the merchant’s theme header (logo, nav, login) unless clearance is handled another way.

---

## What we reject as primary UX

| Approach | Why not primary |
|----------|-----------------|
| Theme → **Offset → Top** (merchant enters header height in px) | Merchants don’t know desktop/mobile/sticky/announcement heights; high friction; wrong values still ship. |
| Fixed preview defaults (e.g. 56px / 80px) | Every theme differs; misleads WYSIWYG. |
| “Measure in DevTools and publish” as docs/workflow | Support burden; not install-and-go. |

**Side message (flyout) `positionOffset`** is unchanged — inset from viewport edges for panel placement, not “guess your header height.”

---

## What agents and implementers must do

1. **Do not** add banner offset UI, banner `positionOffset` in embed API, or preview default top insets without a new ADR superseding ADR-005.
2. **Do not** recommend “set Offset → Top to 64/80/96” as the default fix for PEB-044 in reviews, support, or PR descriptions.
3. **Do** treat header overlap as an **open product/engineering** item until automatic clearance ships.

---

## Acceptable directions for a real fix (not decided — pick one in implementation ADR)

1. **Runtime header detection** — On embed init, measure `position: fixed/sticky` top bars in `document` (same origin on live site); set banner host `top` to sum of heights. Preview: suppress live embed in iframe (`adflipr_builder_preview=1`) + restructure preview layout (header band + banner slot).
2. **Theme / platform integration** — WordPress/Shopify snippet exposes `--adflipr-header-offset` or data attribute from theme.
3. **Document-flow banner mode** — Optional product mode that pushes content (different from overlay sticky bar); large behavior change.

---

## Current code (post ADR-005)

| Layer | Banner offset behavior |
|-------|-------------------------|
| Builder Theme UI | No offset controls for banner |
| Preview embed config | No `positionOffset` for banner |
| `PopupEmbedResponse` | No `positionOffset` for `STICKY_BANNER` |
| Embed runtime | `buildBannerHostOffsetCss` exists but API does not supply offset for banners |
| Flyout | `positionOffset` still supported |

---

## Related

- **PEB-044** — `popup-embed-production-bugs.md`
- **Presentation modes proposal** — `popup-sticky-banner-presentation-modes.md` (Overlay / Below Header / Inline)
- **Preview iframe suppress** — `POPUP_BUILDER_PREVIEW_QUERY_PARAM` in `popupEmbedContract.ts` (avoids duplicate live banner in preview; does not fix clearance alone)
