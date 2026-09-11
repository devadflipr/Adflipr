# Popup Builder — Preview Mobile Responsive Fix

**Date:** 2026-07-06  
**Scope:** Builder preview modal mobile device toggle only  
**Status:** Implemented (local); not part of save/export/live embed paths  
**Related:** Post–CSS-migration architecture (`renderCss` + `@media (max-width: 480px)`)

---

## Summary

Mobile-specific popup styles worked on the **live site** but not when toggling **Mobile** in the **builder preview** on a desktop browser. The fix rewrites preview-only CSS so mobile `renderCss` rules apply under the existing preview presentation root, without changing saved artifacts or embed behavior.

---

## Symptom

| Context | Expected | Observed (before fix) |
|---------|----------|------------------------|
| Live site on phone | Mobile traits (margin, padding, font size, etc.) | Correct |
| Builder preview → Mobile toggle (desktop browser) | Same mobile traits | Desktop traits often shown |
| Builder preview → Desktop toggle | Desktop traits | Correct |

---

## Root cause

### Where mobile styles live after CSS migration

All block-family **merchant presentation** compiles into artifact-scoped **`renderCss`**. Device-specific traits use viewport media queries:

```css
@media only screen and (max-width: 480px) {
  [data-adflipr-popup-artifact="…"] … { /* mobile overrides */ }
}
```

(`POPUP_RESPONSIVE_MAX_WIDTH_PX = 480` in `popupEmbedContract.ts`.)

### What preview mobile toggle did before fix

`FormPreview.tsx` passes `device: previewDevice` into `mountFormPreviewEmbedPresentation()`, which:

1. Shrinks the **visual frame** to 375px when mobile is selected.
2. Calls `applyPreviewPresentationDevice(root, device)` — sets:
   - class `pbn-form-preview-presentation`
   - attribute `data-pbn-preview-device="desktop" | "mobile"`

That attribute was already used for **teaser hide rules** (`TEASER_PREVIEW_DEVICE_CSS` in `embed/teaserExportCss.ts`). It was **not** connected to `renderCss` mobile presentation.

### Why live worked but preview did not

`@media` queries match the **browser viewport**, not the width of a child element.

| Environment | Viewport width | `@media (max-width: 480px)` |
|-------------|----------------|----------------------------|
| Phone (live) | ~375px | Matches → mobile CSS applies |
| Desktop browser + preview mobile frame | ~1280px+ | Does not match → mobile CSS ignored |

Phase 0 baseline tests document the same constraint: responsive verification requires a narrow viewport (Playwright `VIEWPORT_MOBILE = 375×812`), because `@media` does not follow a nested 375px div.

### What this is not

- **Not** a regression from deleting `buildBlockTraitResponsiveCss` (that path was already empty for migrated blocks).
- **Not** a save/export or live embed bug.
- **Not** the editor **canvas** device toggle (canvas passes `device` into React resolvers directly; see §Out of scope).

---

## Fix design

### Strategy

When preview device is **`mobile`**, transform CSS **at preview config build time**:

1. Find `@media only screen and (max-width: 480px) { … }` blocks.
2. Remove the `@media` wrapper.
3. Prefix each inner rule selector with:

   ```text
   .pbn-form-preview-presentation[data-pbn-preview-device="mobile"]
   ```

4. Similarly, scope `@media (min-width: 481px)` rules under:

   ```text
   .pbn-form-preview-presentation[data-pbn-preview-device="desktop"]
   ```

   so desktop-only rules (e.g. hide-on-desktop) do not apply when preview root is `mobile`.

When preview device is **`desktop`**, pass CSS through unchanged (desktop browser viewport already satisfies desktop `@media` behavior).

This mirrors the existing teaser preview device pattern in `teaserExportCss.ts`.

### Why this approach

| Alternative | Rejected because |
|-------------|------------------|
| Restore generic block-trait responsive generator | Wrong layer; dead for migrated families; not preview-specific |
| Compile device-specific `renderCss` at save time | Preview would diverge from saved WYSIWYG contract |
| iframe with 375px viewport | Heavier UX/infrastructure |
| Container queries across all compilers | Large migration; out of scope for this bug |

---

## Implementation

### New file: `previewDeviceCssTransform.ts`

**Path:** `adflipr-app/src/layouts/PopupBuilder/utils/previewDeviceCssTransform.ts`

| Export | Purpose |
|--------|---------|
| `transformCssForPreviewDevice(css, device)` | Rewrite `renderCss` string for preview device |
| `transformExportHtmlForPreviewDevice(html, device)` | Rewrite embedded `<style>` blocks in exported step HTML |
| `scopeCssRulesToPreviewRoot(css, scope)` | Prefix selector lists with preview root (testable helper) |

Implementation notes:

- Uses regex to find media query headers + brace-balanced block extraction.
- Handles comma-separated selector lists.
- Only transforms when `device === 'mobile'`.

### Modified: `formPreviewEmbedPresentation.ts`

**Path:** `adflipr-app/src/layouts/PopupBuilder/utils/formPreviewEmbedPresentation.ts`

In `buildPreviewEmbedConfig()`:

```text
renderCss  ← transformCssForPreviewDevice(artifact.renderCss, options.device)
htmlByStep ← transformExportHtmlForPreviewDevice(each step html, options.device)
```

Call chain unchanged otherwise:

```text
FormPreview
  → mountFormPreviewEmbedPresentation(root, { device: previewDevice, … })
    → buildPreviewEmbedConfig()
    → createPopupEmbedRuntime() → showSimulatedEmbedConfig(config)
    → mountRenderArtifactCss({ css: config.renderCss })
    → hoistExportedStepStyles(container)
```

`applyPreviewPresentationDevice()` still sets `data-pbn-preview-device` on the presentation root so scoped selectors match.

### Example transformation

**Before** (fragment of `renderCss`):

```css
[data-adflipr-popup-artifact="x"] [data-adflipr-block-id="btn-1"]{margin:0 0 20px 0}
@media only screen and (max-width: 480px){
[data-adflipr-popup-artifact="x"] [data-adflipr-block-id="btn-1"]{margin:0 0 4px 0}
}
```

**After** (mobile preview):

```css
[data-adflipr-popup-artifact="x"] [data-adflipr-block-id="btn-1"]{margin:0 0 20px 0}
.pbn-form-preview-presentation[data-pbn-preview-device="mobile"] [data-adflipr-popup-artifact="x"] [data-adflipr-block-id="btn-1"]{margin:0 0 4px 0}
```

Mobile rule wins via higher specificity under the preview root.

---

## Tests

| File | Coverage |
|------|----------|
| `utils/__tests__/previewDeviceCssTransform.test.ts` | Media unwrapping, desktop pass-through, HTML `<style>` transform, comma selectors |
| `utils/__tests__/formPreviewEmbedPresentation.test.ts` | `buildPreviewEmbedConfig({ device: 'mobile' })` emits scoped mobile margin in `renderCss` |

Run:

```bash
cd adflipr-app
npm run test -- --watchAll=false --testPathPattern="previewDeviceCssTransform|formPreviewEmbedPresentation"
```

---

## Out of scope

| Area | Behavior |
|------|----------|
| Save / publish payload | Unchanged — no transform at serialize/save |
| Live embed (`adflipr-popup-embed.js`) | Unchanged — uses real viewport `@media` |
| Editor canvas device toggle | Unchanged — `FormCanvas` + `device` prop → `ElementStyles` resolvers |
| Orphan preview canvas (removed) | `SerializedFormPreviewCanvas` / `serializedFormPreviewMount` deleted; only `FormPreview` embed path remains |

---

## Manual verification

1. In builder, set a block with **different desktop vs mobile** traits (e.g. margin bottom 20px desktop / 4px mobile).
2. Open **Preview** modal.
3. **Desktop** toggle → desktop values visible.
4. **Mobile** toggle → mobile values visible.
5. Publish and open on phone → mobile values still correct (unchanged).

---

## Risks and limitations

1. **CSS parsing** — Regex + brace balancing, not a full CSS AST. Assumes compiler output uses ` @media only screen and (max-width: 480px)` / `min-width: 481px` form.

2. **Specificity** — Mobile preview rules gain an extra ancestor selector; should align with live mobile but worth spot-checking on complex blocks.

3. **Desktop preview on narrow browser** — Still relies on viewport `@media`; edge case if user selects Desktop preview on a phone-sized browser window.

4. **Preview entry points** — Transform only runs through `buildPreviewEmbedConfig`. Any alternate preview mount must call the same helpers.

5. **No persisted data change** — Transform is runtime preview-only; stored `renderCss` in DB remains standard export format.

---

## Files touched (this fix only)

| File | Action |
|------|--------|
| `utils/previewDeviceCssTransform.ts` | Added |
| `utils/formPreviewEmbedPresentation.ts` | Modified |
| `utils/__tests__/previewDeviceCssTransform.test.ts` | Added |
| `utils/__tests__/formPreviewEmbedPresentation.test.ts` | Modified |

---

## SME review prompts

1. Is preview-only CSS rewrite acceptable vs iframe viewport simulation for long-term preview architecture?
2. Should desktop preview on narrow viewports also get explicit attribute scoping?
3. Is regex-based media unwrapping sufficient given controlled `renderCss` output, or should a CSS parser be used?
4. Are there any remaining preview surfaces outside `FormPreview` / `mountFormPreviewEmbedPresentation` that need the same device-transform wiring?

---

## References

- Preview presentation: `components/form-builder/FormPreview.tsx`
- Preview embed mount: `utils/formPreviewEmbedPresentation.ts`
- Teaser preview device pattern: `embed/teaserExportCss.ts` (`TEASER_PREVIEW_DEVICE_CSS`)
- Responsive breakpoint: `contract/popupEmbedContract.ts` (`POPUP_RESPONSIVE_MAX_WIDTH_PX`)
- Phase 0 viewport note: `test-utils/responsiveCssBaselineHarness.ts` (`VIEWPORT_MOBILE`)
