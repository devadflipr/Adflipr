# Phase P1 — Preview Device CSS Transform Architecture Closure

**Date:** 2026-07-06  
**Branch:** `css-reft`  
**Mode:** architecture closure + narrow hardening (preview-only)

---

## 1. Executive verdict

The preview device CSS transform is **accepted as steady-state preview architecture** with controlled-contract hardening.

**Verdict:** **ACCEPT WITH CLEANUP**

**Cleanup applied in this phase:**
- Desktop preview no longer pass-through raw `@media` (selected device is now authoritative on all host viewports).
- Media-query header strings centralized in `responsiveDeviceCss.ts` (`POPUP_EXPORT_MEDIA_MOBILE_HEADER`, `POPUP_EXPORT_MEDIA_DESKTOP_HEADER`).
- Embedded `<style>` rewriting narrowed to blocks containing canonical viewport media headers only.
- Controlled grammar documented in `previewDeviceCssTransform.ts`.

**Long-term architecture:** **TRANSFORM WITH CONTROLLED CONTRACT** — not iframe.

---

## 2. Bug reproduction

**Repro block:** Button with `marginDesktop.bottom = 20`, `marginMobile.bottom = 4`.

| Condition | Expected margin-bottom | Proven |
|-----------|------------------------|--------|
| Live narrow viewport (≤480px) | 4px | YES (Phase 0 baseline / prior live report) |
| Preview Desktop on wide browser | 20px | YES |
| Preview Mobile on wide browser **before** transform | 20px (bug) | YES — `@media` never matched 375px frame |
| Preview Mobile on wide browser **after** transform | 4px | YES — `formPreviewEmbedPresentation.test.ts`, Chromium |

**Root cause confirmed:** **YES** — post-migration merchant presentation lives in artifact `renderCss` inside `@media only screen and (max-width: 480px)`. Preview modal shrinks presentation frame but host browser viewport stays desktop-wide, so mobile media never activates.

---

## 3. Root-cause verification

```text
merchant presentation → compilePopupRenderCss → @media (max-width: 480px)
preview frame width ≠ browser viewport width
→ mobile @media inactive in preview
```

---

## 4. Save/live boundary verification

| Path | Transform applied? | Evidence |
|------|-------------------|----------|
| `serializePopupExport` | NO | `previewOnlyBoundary.test.ts` |
| `buildPopupSaveMetadata` renderCss | NO | canonical `@media` preserved |
| Live embed `createPopupEmbedRuntime` | NO | transform only in `buildPreviewEmbedConfig` |
| `buildPreviewArtifactExport` (raw) | NO | only `buildPreviewEmbedConfig` wraps transform |

**Save payload changed:** NO  
**Stored renderCss changed:** NO  
**Live embed changed:** NO

---

## 5. Preview CSS source inventory

| CSS source | Location | Viewport @media? | Class | Transform on preview? |
|------------|----------|------------------|-------|----------------------|
| `artifact.renderCss` | `buildPreviewEmbedConfig` | YES (all migrated families) | Merchant presentation | YES |
| Embedded `data-adflipr-popup-device-css` | step HTML via `serializeLayout` | YES (hide rules, phone select) | Structural/responsive safety | YES (if @media present) |
| `product.export.css` embedded sheet | step HTML | YES (horizontal card stack) | Structural | YES (if @media present) |
| Other `*.export.css` sheets | step HTML | NO | Structural/behavior | NO (skipped) |
| `formStyle.customCss` embedded | step HTML | Merchant-dependent | Form-level | NO (no canonical header) |
| Teaser export CSS | teaser HTML / runtime inject | NO (@keyframes only) | Structural/animation | NO |
| `TEASER_PREVIEW_DEVICE_CSS` | runtime inject on preview root | NO (attribute selectors) | Preview device | N/A — separate channel |
| Entrance / radio / checkbox / etc. sheets | step HTML | NO | Structural | NO |
| Runtime-mounted structural CSS | embed runtime hoist | NO viewport media | Runtime | NO |

**Responsive CSS sources requiring preview adaptation:**
1. `artifact.renderCss` (primary)
2. Embedded `data-adflipr-popup-device-css` (hide-on-device, phone country select)
3. `product.export.css` in embedded product sheet (horizontal layout mobile stack)

---

## 6. Embedded step-style responsive inventory

| Source | Responsive media | Preview adaptation required | Reason |
|--------|------------------|----------------------------|--------|
| `buildEmbeddedStepDeviceCss` | YES (`max-width: 480px`, `min-width: 481px`) | YES | Hide-on-device + phone select width |
| `POPUP_PRODUCT_EXPORT_CSS` | YES (`max-width: 480px`) | YES | Horizontal product mobile stack |
| All other embedded export sheets | NO | NO | Structural only |

---

## 7. HTML style transformation decision

```text
transformExportHtmlForPreviewDevice action: KEEP (narrowed)
```

- Only transforms `<style>` blocks whose content includes canonical `POPUP_EXPORT_MEDIA_*_HEADER` strings.
- Does not rewrite arbitrary merchant `formStyle.customCss` blocks without viewport media.
- Required for hide-on-device and product horizontal stack in preview.

---

## 8. Production renderCss grammar inventory

| Syntax | Emitted by compilers | Transformer supports | Test coverage |
|--------|---------------------|---------------------|---------------|
| Plain rules | YES | YES | `previewCompilerOutputTransform.test.ts` |
| Comma-separated selectors | YES | YES (`split(',')`) | `previewDeviceCssTransform.test.ts` |
| Mobile `@media only screen and (max-width: 480px)` | YES | YES | integration + compiler tests |
| Desktop `@media only screen and (min-width: 481px)` | YES (embedded only in renderCss: rare; mainly embedded step CSS) | YES | unit test |
| Nested `@media` | NO | NO (not emitted) | N/A |
| `@supports` / `@container` | NO | NO | N/A |
| `@keyframes` | NO in renderCss | NO | N/A (teaser only) |
| Comments in export sheets | YES (structural sheets) | Safe (not in renderCss) | N/A |
| Quoted strings / `url()` / data URLs | YES | Brace balancer ignores string contents* | compiler output test |
| Custom properties | YES | YES | indirect |
| Escaped attribute selectors | YES | YES | compiler output test |

\*Brace balancer is safe for **compiler-controlled** output (no `{`/`}` inside declaration values in practice).

**Unsupported current production grammar:** **NONE**

---

## 9. Media-query matching contract

```text
preview transform matches: semantic responsive contract
```

Headers centralized:
- `POPUP_EXPORT_MEDIA_MOBILE_HEADER` = `@media only screen and (max-width: 480px)`
- `POPUP_EXPORT_MEDIA_DESKTOP_HEADER` = `@media only screen and (min-width: 481px)`

Regex built from exported headers — no duplicate hardcoded `480` in transform module.

**Duplicate breakpoint knowledge remaining:** NONE (single source in `responsiveDeviceCss.ts` + `POPUP_RESPONSIVE_MAX_WIDTH_PX` constant)

---

## 10. Brace-balanced parser safety

**Classification:** **SAFE FOR CONTROLLED PRODUCTION GRAMMAR**

Not a general CSS parser. Safe because:
- Compilers emit flat `@media` blocks with rule lists only
- No nested `@media`, no `@supports` in renderCss
- No rule bodies containing unescaped `{`/`}` in values in production output

---

## 11. Selector prefixing safety

**Selector splitting method:** `selectorText.split(',')` after extracting top-level rule boundaries.

**Current production selectors safely supported:** **YES**

Evidence: all presentation compilers emit artifact-scoped attribute selectors without nested commas inside `:is()` / `:not()` / attribute values.

---

## 12. Cascade equivalence evidence

Preview transformation:
- Unwraps `@media` and prefixes selectors with `.pbn-form-preview-presentation[data-pbn-preview-device="mobile|desktop"]`
- Base (non-media) rules unchanged
- Mobile-scoped rules inactive when `data-pbn-preview-device="desktop"` (ancestor mismatch)
- Desktop-scoped rules inactive when `data-pbn-preview-device="mobile"`

**Preview transformation changes only device activation:** **YES**

Higher specificity from preview root prefix is intentional and scoped to preview subtree only.

---

## 13. Device-authority matrix

| Selected device | Host viewport | Expected | Actual (post-P1) |
|---------------|---------------|----------|------------------|
| Desktop | Wide (1280px) | Desktop traits | PASS |
| Desktop | Narrow (375px) | Desktop traits (not host @media) | PASS — `previewDeviceCss.browser.test.ts` |
| Mobile | Wide (1280px) | Mobile traits | PASS |
| Mobile | Narrow (375px) | Mobile traits | PASS |

**Selected preview device authoritative:** **YES** (after desktop pass-through fix)

---

## 14. Desktop-only rule behavior

Desktop-only rules (`min-width: 481px`) scoped under `data-pbn-preview-device="desktop"`.

On mobile preview selection: inactive (ancestor mismatch).  
On desktop preview selection: active regardless of host viewport.

**Desktop-only rules follow selected device:** **YES**

---

## 15. Transform vs iframe comparison

| Concern | Current transform | Controlled parser | iframe viewport |
|---------|-------------------|-------------------|-----------------|
| CSS fidelity | High for compiler output | Same | Highest |
| Runtime fidelity | Reuses embed runtime | Same | Good; event bridge cost |
| Complexity | Low | Low | High |
| Existing embed reuse | Full | Full | Partial rewrite |
| Modal UX risk | Low | Low | iframe sizing/scroll risk |
| customCss compatibility | **Unsafe assumption** | Document boundary | Safer for arbitrary CSS |
| Future CSS grammar | Requires contract update | Same | Most flexible |

**Long-term preview architecture:** **TRANSFORM WITH CONTROLLED CONTRACT**

**iframe required now:** **NO**

---

## 16. Future customCss boundary

```text
Will merchant block customCss pass through previewDeviceCssTransform?
→ DEPENDS ON FINAL CUSTOMCSS LANGUAGE

Future customCss assumed safe for transform: NO
```

Block `customCss` (Phase 8B, separate) is declaration-list only — **no `@media`** — passes through unchanged.  
If rule-block or `@media` merchant CSS is ever supported, preview transform must **not** be assumed sufficient without a new contract.

Form-level `customCss` (global rules in embedded `<style>`) is **not** transformed unless it contains canonical viewport headers.

---

## 17. Preview entry-point inventory

| Surface | Real viewport | `buildPreviewEmbedConfig` | Device toggle | Needs transform |
|---------|---------------|---------------------------|---------------|-----------------|
| `FormPreview` modal | Host browser | YES | YES | YES — wired |
| Builder `FormCanvas` React | N/A (trait resolvers) | NO | YES (React path) | NO — different architecture |
| Popup detail thumbnail | iframe | NO | NO | NO |
| Campaign list thumbnail | iframe | NO | NO | NO |
| Simulated embed preview | Host browser | YES | YES | YES — same as FormPreview |

**Surfaces with same bug:** `FormPreview` (and any caller of `mountFormPreviewEmbedPresentation`)

**Duplicate preview adaptation paths:** **NONE**

---

## 18. Preview-device API decision

Canonical API:

```text
buildPreviewArtifactExport() → raw canonical artifact
buildPreviewEmbedConfig()  → preview device adaptation (renderCss + embedded step styles)
mountFormPreviewEmbedPresentation() → applies data-pbn-preview-device on presentation root
```

---

## 19. Real compiler-output evidence

`previewCompilerOutputTransform.test.ts` — **PASS**

Uses `serializePopupExport` output from Button + Text + Image families; verifies mobile `@media` unwrapped and scoped.

---

## 20. Cross-family evidence

Same test artifact includes button margin, text padding, image block — all mobile rules unwrapped together.

**Cross-family preview test:** **PASS**

---

## 21. Chromium evidence

`previewDeviceCss.browser.test.ts` — **PASS**

Wide host: desktop 20px → toggle mobile 4px → restore desktop 20px.  
Narrow host + desktop selection: still 20px.

---

## 22. Runtime regression evidence

Existing `formPreviewEmbedPresentation.test.ts` mount tests (flyout, banner, teaser, spinner) — **PASS** (no changes to runtime init path).

**Runtime preview regression:** **PASS**

---

## 23. Teaser interaction evidence

`TEASER_PREVIEW_DEVICE_CSS` uses attribute selectors on preview root — orthogonal to `@media` unwrapping.

Transform does not modify teaser animation CSS (no viewport media).

**Teaser preview device behavior:** **PASS**

---

## 24. Preview-only boundary evidence

`previewOnlyBoundary.test.ts` — **PASS**

---

## 25. Obsolete code deleted

- Removed desktop pass-through branch in `transformCssForPreviewDevice`
- Removed duplicate `POPUP_RESPONSIVE_MAX_WIDTH_PX` regex literals in transform (now imports shared headers)
- HTML transform early-return for `device === 'desktop'` removed

Broad arbitrary `<style>` rewriting was not present; narrowing uses header string check.

---

## 26. Phase 0 result

```text
25 / 25 PASS (red: 0)
```

---

## 27. G1 result

```text
FAIL — pre-existing SpinnerProperties.tsx TypeScript errors (ColorPickerField import missing)
```

Not caused by Phase P1 preview transform changes.

---

## 28. Production build result

```text
FAIL — same SpinnerProperties.tsx errors as G1
```

---

## 29. Remaining risks

1. **Form-level `customCss`** with manual `@media` rules would not transform unless they use exact canonical headers (acceptable — documented).
2. **Future block `customCss`** must not add `@media` without revisiting preview contract.
3. **Brace/selector parser** is not safe for arbitrary merchant CSS rule blocks — must remain compiler-controlled.

---

## 30. Final verdict

**ACCEPT WITH CLEANUP**

Preview device transform is steady-state architecture for `FormPreview` embed presentation path.

---

## 31. Recommended next phase

**Phase 8B** — block-level `customCss` / `customClass` production pipeline (declaration-list customCss does not require preview transform changes; fix `SpinnerProperties.tsx` build break before G1).

Also recommended: fix `SpinnerProperties.tsx` `ColorPickerField` import to restore G1/build gates.
