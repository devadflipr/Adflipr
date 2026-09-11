# Popup embed contract (serializer ↔ runtime)

**Canonical code:** `adflipr-app/src/layouts/PopupBuilder/contract/`  
**As of:** 2026-06-28

## Purpose

Single source of truth for `data-adflipr-*` attribute names, action values, step ids, and spinner JSON shape shared between:

- HTML export (`popupHtmlSerializer/`)
- Storefront embed (`embed/`)
- Contract tests (`contract/__tests__/`)

## Modules

| File | Contents |
|------|----------|
| `popupEmbedContract.ts` | Attribute strings, `POPUP_ACTION_VALUES`, `POPUP_STEP_IDS`, coupon sources, form selector |
| `popupSpinnerContract.ts` | Spinner config types, `buildSpinnerSerializedConfig`, `parseSpinnerSerializedConfig`, `normalizePopupRedirectStep` |

## Import guidance

- Import attrs, actions, and step ids from `@pb/contract/popupEmbedContract` or `@pb/contract/popupSpinnerContract`.
- `embed/constants.ts` holds embed-only paths, storage keys, and CSS class names — not HTML data attrs.
- `popupHtmlSerializer/types.ts` holds serializer TypeScript types only.

## Out of scope (stay local)

- Embed shell DOM ids/classes (`#adflipr-popup-embed-root`)
- API paths, localStorage keys
- Export CSS sheet content (may reference attrs by string; lint/test separately)
- Backend `PopupMetadataKeys` / submission field names (separate API boundary)

## Adding a new interactive block

1. Add attr names to `popupEmbedContract.ts` (if new `data-*` markers).
2. Emit attrs in `serializeElements.ts` using contract constants.
3. Read attrs in embed initializer using the same constants.
4. Extend `contract/__tests__/popupEmbedContract.test.ts`.

## Related KB

- [`popup-html-serialization.md`](./popup-html-serialization.md)
- [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md)
