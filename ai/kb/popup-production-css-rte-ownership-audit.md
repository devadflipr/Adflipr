# Popup production CSS + RTE/trait ownership audit

**Date:** 2026-07-06  
**Phase:** Audit and decision only — **no** production build fix, **no** RTE stripping, **no** next block-family migration.  
**Related:** [`popup-embed-preview-dom-investigation-summary.md`](./popup-embed-preview-dom-investigation-summary.md), [`popup-css-architecture-overall-summary-through-phase4.md`](./popup-css-architecture-overall-summary-through-phase4.md), [`popup-text-presentation-css-phase5d-report.md`](./popup-text-presentation-css-phase5d-report.md), [`popup-rte-command-ownership-phase-g2a-audit.md`](./popup-rte-command-ownership-phase-g2a-audit.md), [`popup-export-css-production-build-fix-phase-g1-report.md`](./popup-export-css-production-build-fix-phase-g1-report.md) (G1 fix — **implemented**)

---

## A. Executive summary

Two production-adjacent issues block confident continuation of block-family CSS migration:

| Issue | Severity | Root cause (proven) | Blocks migration? |
|-------|----------|---------------------|-------------------|
| **Step export CSS is webpack JS stub in production `<head>`** | High for forms (floating label, radio, responsive utilities) | `.export.css` imported at **save time** in the **main adflipr-app webpack bundle** resolves to mini-css-extract JS stub instead of raw CSS string; stub is embedded into saved `htmlContent` and hoisted verbatim by embed runtime | **Yes** — latent correctness for input/choice blocks |
| **Text RTE inline styles overlap block `renderCss` traits** | Medium — mixed formatting works; trait updates can be defeated | Dual mutation paths: Style panel → `properties.style` → `renderCss`; RTE → `execCommand` / span wrappers → `properties.content` inline HTML. No provenance metadata. Alignment additionally split across `style.textAlignment` vs orphaned `properties.align` | **Yes** — architecture contract required before more presentation moves |

**Embed bundle (`adflipr-popup-embed.js`) is not the `.export.css` importer.** Esbuild bundles `embed/index.ts` → runtime only; it does **not** import the serializer or `.export.css` files. Esbuild fails outright on `.export.css` imports without a loader (verified via CLI). Production symptom originates from **merchant Save** in the hosted app.

**Tests currently hide the production `.export.css` defect:** Jest uses `exportCssTransform.js` (raw file read); dev craco uses `asset/source`. Neither exercises the **production webpack bundle** constant values at runtime.

---

## B. Production `.export.css` build trace

### Files (4)

| File | Constant | Embedded in |
|------|----------|-------------|
| `styles/sheets/floating-label.export.css` | `POPUP_FLOATING_LABEL_EXPORT_CSS` | `serializeStepHtml` → `<style data-adflipr-popup-floating-label-css>` |
| `styles/sheets/embed-responsive.export.css` | `POPUP_EMBED_RESPONSIVE_EXPORT_CSS` | `<style data-adflipr-popup-responsive-css>` |
| `styles/sheets/popup-entrance.export.css` | `POPUP_ENTRANCE_EXPORT_CSS` | `<style data-adflipr-popup-entrance-css>` (when transition set) |
| `styles/sheets/radio.export.css` | `POPUP_RADIO_EXPORT_CSS` | `<style data-adflipr-popup-radio-css>` |

### Import chain

```text
*.export.css
  ↓ default import
styles/sheets/index.ts  → normalizeSheetCss() → trimmed string constants
  ↓ re-export
floatingLabelExportStyles.ts
  ↓
serializeLayout.ts::serializeStepHtml()  — concatenates into step <style> blocks
  ↓ Save (browser)
API htmlContent / htmlByStep
  ↓ storefront
embed/runtime.ts → hoistExportedStepStyles(container) → document.head
```

### TypeScript importers

Only `styles/sheets/index.ts` imports the four `.export.css` files directly. Runtime consumers import the string constants via `floatingLabelExportStyles.ts` / `serializeLayout.ts`.

### Build configurations

| Build | Tool | Touches `.export.css`? | Loader semantics |
|-------|------|------------------------|------------------|
| **App dev / production** | `craco` → webpack | **Yes** — serializer in main chunk | `craco.config.js` **unshift** `{ test: /\.export\.css$/i, type: 'asset/source' }` |
| **App Jest** | `exportCssTransform.js` | **Yes** | Reads file as UTF-8 string |
| **Popup embed** | `esbuild` (`npm run build:popup-embed`) | **No** — entry is `embed/index.ts` only | N/A; CSS import would error without loader |
| **Full production** | `scripts/build-production.sh` | embed esbuild **then** `craco build` | Only craco path matters for save-time CSS |

### App vs embed

| Concern | App (craco) | Embed (esbuild) |
|---------|-------------|-----------------|
| Serializes popup on Save | Yes | No |
| Embeds `.export.css` into step HTML | Yes (in browser at save) | No |
| Hoists step `<style>` to `<head>` | Preview runtime only | Yes (storefront) |
| Mounts artifact `renderCss` | Preview + Save metadata | Yes |

---

## C. Exact production build defect

### Symptom (adcarts.com production, after Save)

```html
<style data-adflipr-popup-runtime-style="data-adflipr-popup-floating-label-css">
// extracted by mini-css-extract-plugin
export default {};
</style>
```

Same pattern for `data-adflipr-popup-responsive-css` and `data-adflipr-popup-radio-css`.

### Mechanism

1. Merchant clicks **Save** on hosted adflipr.com.
2. Browser runs production **main app JS chunk** containing `POPUP_*_EXPORT_CSS` constants.
3. Those constants were compiled with **mini-css-extract-plugin / css-loader** semantics instead of `asset/source`, producing a JS module stub string (or empty export) rather than CSS text.
4. `serializeStepHtml` embeds that stub literally inside `<style>` in `htmlContent`.
5. Embed runtime `hoistExportedStepStyles()` moves the invalid text to `document.head` unchanged.

`normalizeSheetCss()` in `sheets/index.ts` cannot recover real CSS from this stub — it only unwraps string/`default` object shapes, not webpack module source text.

### Why tests did not catch it

| Test path | Result |
|-----------|--------|
| `exportCssSheets.test.ts` | Pass — Jest raw read |
| `popupHtmlSerializer.test.ts` floating-label cases | Pass — same Jest path |
| `popupProductionCssRteOwnership.audit.test.ts` | Pass — Jest path |
| Production webpack bundle constant grep | **Not automated** — required gate before fix |

### Esbuild embed evidence

```bash
# esbuild on .export.css import without loader:
Cannot import "*.export.css" into a JavaScript file without an output path configured
```

Embed bundle grep for `mini-css-extract` / `pbn-floating-field`: **not present** — confirms embed does not bundle serializer CSS.

### Likely trigger for production stub

Current repo `craco.config.js` **should** fix imports via unshifted `asset/source`. Production adcarts evidence implies either:

- Deployed adflipr.com app predates the craco rule, **or**
- Production webpack rule ordering differs from local (unlikely given same `craco build`), **or**
- An additional code path stringifies the wrong module representation (less likely than stale deploy).

**Mandatory verification before implementation:** `npm run build` → grep `build/static/js/*.js` for `pbn-floating-field` (expect present) and `mini-css-extract-plugin` (expect absent).

---

## D. `.export.css` recommended architecture

### Decision: single semantic — raw CSS string end-to-end

```text
*.export.css
  → import as raw string (webpack asset/source OR equivalent)
  → POPUP_*_EXPORT_CSS constants
  → embedded verbatim in serialized step <style>
  → hoisted to document.head at runtime
```

### Do not maintain two interpretations

- **Reject:** css-loader + mini-css-extract for `.export.css` (produces JS stub / hashed classes — wrong for inline `<style>` embedding).
- **Reject:** bundling serializer into esbuild embed without an `asset/source`-equivalent loader (esbuild currently cannot import these files).

### Smallest forward-only fix (recommended, not implemented)

1. **App production webpack:** Confirm `asset/source` unshift applies in production `craco build` output (add post-build grep CI check).
2. **If stub persists:** enforce rule with `resourceQuery` / `issuer` or move sheets to `.export.css.ts` with `fs.readFileSync` at build time only if webpack cannot be made reliable — prefer fixing webpack first.
3. **Embed esbuild:** No change required for `.export.css` **today** (embed does not import them). Document invariant: serializer stays app-side only.
4. **Re-save / migration:** Popups saved while stub was embedded need re-save after fix to refresh step `<style>` content.

---

## E. RTE mutation pipeline

### Persistence model

| Field | Writer(s) | Export consumer |
|-------|-----------|-----------------|
| `properties.style` | Style panel (`StyleSection`), some RTE callbacks (`onTextColorChange`, `onHighlightColorChange`, `onFontSizeChange` on full selection) | `compileTextPresentationRenderCss` → artifact `renderCss` |
| `properties.content` | `InlineEditor` → `onContentChange` → `innerHTML` | `serializeText` → `sanitizeRichTextHtml` → inner HTML |
| `properties.align` | RTE toolbar `onAlignChange` in `TextCanvas` | **Not read** by text serializer or `renderCss` compiler |
| `properties.isBold` etc. | RTE format flags | Canvas display; may also appear in content via `execCommand` |

### InlineEditor mutation paths (Text block)

| Operation | Mechanism | Writes content? | Writes style traits? |
|-----------|-----------|-----------------|----------------------|
| Alignment (toolbar) | `document.execCommand('justifyCenter'…)` + `onAlignChange` | Yes — block-level `text-align` on `<div>`/`<p>` | **No** — writes `properties.align` only (orphan) |
| Alignment (Style panel) | `onStyleChange('textAlignment')` | No | Yes — `style.textAlignment` |
| Text color (RTE balloon) | `applyInlineStyleToSelection` → `<span style="color:…">` + `onTextColorChange` | Yes (if selection) | Yes — updates `style.textColor` even when partial |
| Text color (Style panel) | trait only | No | Yes |
| Highlight | span `background-color` | Yes | Yes — `style.highlightColor` |
| Font size (partial) | span `font-size` | Yes | No (desktop partial) |
| Font size (full selection) | `clearInlineStyles` + `onFontSizeChange` | Clears inline font-size | Yes — trait update |
| Bold / italic / underline | `execCommand` | Yes — `<b>`, `<i>`, `<u>` or inline | `onFormatChange` flags |
| Lists | `execCommand` | Yes | list boolean flags |
| Links | link UI / `execCommand` | Yes — `<a href>` | link traits on block |
| Paste | **plain text only** — `insertText`; HTML stripped | Text nodes only | No |
| Canvas root display | React `displayStyle` on editor root | **Not exported** — only `innerHTML` saved | N/A |

### Serialize path

```text
properties.content (innerHTML)
  → sanitizeRichTextHtml()  — tag allowlist, strip on* / dangerous URLs; **preserves inline style attributes**
  → wrapped in bare <div> inside [data-adflipr-block-id]
properties.style
  → compileTextPresentationRenderCss
  → scoped rules on wrapper + > div
```

### Template import / duplication / reload

- Import uses same serializer/sanitize paths as save.
- Reload/edit/save cycles preserve RTE inline HTML as stored in `properties.content`; trait changes to `properties.style` recompile `renderCss` independently.
- No server-side HTML normalization of RTE styles today.

---

## F. Inline style provenance matrix

Legend: **Trait** = Style panel → `properties.style` → `renderCss`. **RTE** = InlineEditor selection/execCommand → `properties.content`.

| Inline CSS property | Block trait writes it? | RTE writes it? | Paste introduces it? | Persisted in content? | renderCss also owns it? | Conflict risk |
|---------------------|------------------------|----------------|------------------------|----------------------|-------------------------|---------------|
| `color` | Yes (`textColor`) | Yes (span) | No (plain paste) | Yes | Yes | **HIGH** — inline wins cascade |
| `background-color` | Yes (block bg) | Yes (highlight); `transparent` noise | No | Yes | Yes (block bg on `> div`) | **MEDIUM** — highlight vs block bg; transparent noise |
| `text-align` | Yes (`textAlignment`) | Yes (execCommand on div/p) | No | Yes | Yes | **HIGH** — descendant inline defeats trait |
| `font-size` | Yes (trait) | Yes (partial span); cleared on full select | No | Partial | Yes | **MEDIUM** — partial spans override |
| `font-family` | Yes | Rarely via execCommand | No | Possible | Yes | **MEDIUM** |
| `font-weight` | Yes | Yes (`<b>`/bold) | No | Via tags/inline | Yes | **LOW–MED** — tags vs CSS |
| `line-height` | Yes | Possible execCommand | No | Possible | Yes | **MEDIUM** |
| `letter-spacing` | Yes | Uncommon | No | Possible | Yes | **LOW** |
| `text-decoration` | Link trait (descendant `a`) | Yes (underline) | No | Yes | Yes (links) | **LOW** for body; links scoped |
| `padding` | Yes (block padding) | Unlikely in RTE | No | Unlikely | Yes | **LOW** |
| `margin` | Wrapper trait | Unlikely | No | Unlikely | Yes (wrapper) | **LOW** |
| `width` / `height` | Yes (block height trait) | Unlikely | No | Unlikely | Yes | **LOW** |
| `border` / `border-radius` | Yes | Unlikely | No | Unlikely | Yes | **LOW** |

---

## G. Trait versus RTE experiment results

Evidence from code trace + audit tests (`popupProductionCssRteOwnership.audit*.test.ts`) + existing Phase 5D browser tests.

### Scenario 1 — Block color only (Style panel)

| Artifact | Result |
|----------|--------|
| `properties.style.textColor` | Updated |
| `properties.content` | **Unchanged** — no auto span injection |
| `renderCss` | Contains `color:{trait}` on `> div` |
| Serialized HTML | No color inline in content |
| Computed | Block text uses trait color |

**Conclusion:** Style-panel-only trait does **not** mutate RTE HTML. ✅

### Scenario 2 — Partial rich-text color

| Step | Result |
|------|--------|
| Trait blue + span red | Computed: wrapper/div blue, span **red** (inline wins) |
| Save / reload / preview / embed | Content string preserved; same cascade |
| Change trait color again | Span stays red; unstyled text picks up new trait |

**Conclusion:** Intentional partial formatting **works** after persistence. Trait update does not rewrite spans. ✅ (by design)

### Scenario 3 — Alignment conflict

| Step | Result |
|------|--------|
| Trait `textAlignment: left` | `renderCss` sets `text-align:left` on `> div` |
| RTE toolbar align | execCommand writes `text-align` **into content** + orphan `properties.align` |
| Content `<div style="text-align:center">` | Computed **center** on that div — defeats trait on subtree |
| Style panel alignment change | Updates `renderCss` only; inline content alignment **still wins** where present |

**Conclusion:** Inline content alignment makes trait alignment **ineffective** for affected nodes. **Bug / split-brain** between toolbar (`properties.align` + execCommand) and Style panel (`style.textAlignment` + renderCss). ❌

### Scenario 4 — Full-content RTE formatting

| Operation | Result |
|-----------|--------|
| RTE color on full selection | Span wrapper + `style.textColor` updated |
| RTE align on whole block | execCommand block element + orphan `properties.align` |
| Distinguish intentional vs duplicate? | **No** — same HTML shape as redundant trait copy |

**Conclusion:** Model cannot distinguish full-block RTE formatting from trait duplication in persisted HTML alone.

### Scenario 5 — Remove formatting

No dedicated “remove formatting” command found in PopupBuilder RTE. `clearInlineStyles` used for font-size full selection and span reuse paths only.

**Conclusion:** Obsolete inline styles are **not** reliably cleared by product UI today.

### Scenario 6 — Paste

`handlePaste` strips HTML — plain text only via `insertText`.

**Conclusion:** Paste does **not** introduce inline CSS in current implementation. External styled paste is **not** supported (by design).

### Scenario 7 — Trait changes after pasted content

Plain paste only — behaves like Scenario 1/2 without foreign inline styles.

---

## H. Cascade conflict matrix

| Property | renderCss owner | RTE inline target | Inline wins? | Intentional mixed formatting valid? | Cleanup candidate? |
|----------|-----------------|-------------------|--------------|-------------------------------------|--------------------|
| `color` | `> div` | `<span style="color">` | **Yes** | Yes (partial word color) | Full-block span matching trait — **ambiguous** |
| `background-color` | `> div` (block bg) | span highlight / `transparent` | Yes | Highlight yes; transparent — **noise** | `transparent` — **safe sanitizer** |
| `text-align` | `> div` | inner `<div/p style="text-align">` | **Yes** (descendant) | Yes for partial blocks — rare | Full-block duplicate — **ambiguous**; toolbar path — **fix ownership** |
| `font-size` | `> div` | span | Yes | Yes (partial) | Full-selection clears inline — **partial OK** |
| `font-family` | `> div` | span | Yes | Yes | Ambiguous if matches trait |
| `font-weight` | `> div` | `<b>`/inline | Often tag-based | Yes | Low priority |
| `line-height` | `> div` | span | Yes | Rare | Ambiguous |
| `letter-spacing` | `> div` | span | Yes | Rare | Low |
| `text-decoration` | `a` descendant rule | `<u>`, link styles | Contextual | Yes | Link rules in renderCss — OK |
| `padding` | `> div` | — | N/A | Block-only | BLOCK_ONLY |
| `margin` | wrapper | — | N/A | Block-only | BLOCK_ONLY |
| `width`/`height` | wrapper / `> div` | — | N/A | Block-only | BLOCK_ONLY |
| `border`/`border-radius` | `> div` | — | N/A | Block-only | BLOCK_ONLY |

### Classification summary

| Class | Properties |
|-------|------------|
| **BLOCK_ONLY** | margin (wrapper), padding (block), width, height, border, border-radius, box-shadow |
| **RTE_ONLY** | — (none fully — paste doesn't add styles) |
| **SHARED_WITH_CLEAR_SEMANTICS** | partial `color`, partial `font-size`, highlight `background-color`, semantic tags (`<b>`, lists) |
| **SHARED_WITH_CONFLICT** | `text-align`, full-block `color`, `font-family`, `line-height` |
| **UNKNOWN** | — |

---

## I. Formatting-intent recoverability decision

### Answer: **PARTIAL (B)**

| Distinguishable? | Evidence |
|------------------|----------|
| **Partial selection ≠ block trait** | Different span color than sibling text — provably intentional |
| **Paste origin** | Plain-text-only paste — no foreign inline CSS |
| **Toolbar vs Style panel alignment** | Orphan `properties.align` vs `style.textAlignment` — **metadata contradicts content** |
| **Full-block span color matching trait** | **Indistinguishable** — could be RTE all-select, editor default, old duplication, or merchant intent |
| **background-color: transparent** | Almost certainly editor noise — not intentional highlight |
| **Operation provenance** | **Not persisted** — no log of whether Style panel or RTE wrote a value |

Final HTML **alone** cannot support safe value-equality stripping as primary architecture.

---

## J. Cleanup strategy comparison

| Option | Description | Verdict |
|--------|-------------|---------|
| **A — Export-time redundant stripping** | Remove inline styles equal to block traits | **Reject primary** — value equality ≠ lack of intent (see §I) |
| **B — Persistence-time normalization** | Rewrite content on every save | **Reject** — destroys ambiguous-but-intentional full-block formatting |
| **C — Prevent block traits entering RTE HTML** | Traits → `style` only; RTE → ranges only; toolbar alignment updates `style.textAlignment` not execCommand block align | **Recommend** — aligns with Phase 5D forward-only model; requires RTE toolbar + alignment fix |
| **D — Explicit rich-text formatting model** | Structured ranges alongside HTML | **Defer** — larger scope; revisit if C insufficient |
| **E — Conservative sanitizer only** | Strip `background-color: transparent`, empty `style=""`, known artifacts | **Recommend immediate** — low risk, does not touch ambiguous color/align |

---

## K. Recommended RTE ownership contract

### Contract (target state)

1. **Block presentation** (alignment, default text color, font size, font family, line height, letter spacing, padding, margin, border, background of text chrome) lives in **`properties.style` only** → **`renderCss`**.
2. **RTE** may write **`properties.content` inline styles only for strict subset selection formatting:** partial `color`, partial `font-size`, highlight (`background-color` non-transparent), semantic tags, links.
3. **RTE toolbar block-wide operations** (align entire block, color entire block, font size entire block) must update **`properties.style` traits** and **must not** execCommand block-level presentation into content (mirror `applyFontSize` full-selection path).
4. **Deprecate `properties.align` on text blocks** — use `style.textAlignment` exclusively; fix `TextCanvas` `onAlignChange`.
5. **Serialize/export:** continue `sanitizeRichTextHtml`; add conservative noise stripping (Option E) at sanitize boundary.
6. **Cascade rule:** inline span styles intentionally override `renderCss` for partial ranges only — merchant expectation.

---

## L. Preview diagnostic findings

### Stale preview condition (observed)

| Signal | Stale bundle | Healthy |
|--------|--------------|---------|
| `data-adflipr-popup-artifact` | `pbn-preview` | `pbn-preview` (OK) |
| Migrated HTML markers | Old inline on cross/text | Bare wrappers |
| `renderCss` in `<head>` | **0** | **≥ 1** |
| Step runtime styles | Present | Present |

### Recommended dev-only invariant (not implemented)

When **all** of:

- artifact scope id present on form,
- migrated block markup detected (e.g. text inner div without legacy inline font-size),
- compiled `renderCss` non-empty,

but **zero** `style[data-adflipr-popup-render-css]` mounted in preview document → **`console.warn`** with “restart dev server / hard refresh” guidance.

Would have caught P1 stale preview earlier. **No production logging.**

### QA lifecycle

```text
Builder Preview  →  current dev bundle + hard refresh required
Storefront embed →  Save required → API artifact (htmlContent + renderCss) required
```

---

## M. Duplicate renderCss lifecycle finding

### Observation (adcarts.com)

Two identical `data-adflipr-popup-render-css` nodes with owners `popup-runtime-1` / `popup-runtime-2` — inline embed + overlay runtime on same page.

### Answers

| Question | Finding |
|----------|---------|
| Redundant CSS only? | **Yes** — duplicate rules, same specificity |
| Can one runtime destroy the other's styles? | **No** — teardown keyed by `data-adflipr-popup-style-owner` per runtime instance |
| Ownership correct? | **Yes** — `renderArtifactCssLifecycle.test.ts` Case F |
| Document-level dedupe complexity? | Would require reference counting / shared popupId semantics |
| Safer than premature dedupe? | **Yes** |

### Classification: **performance debt** — acceptable architecture, not a correctness defect.

---

## N. Production changes recommended

| Priority | Change | Type |
|----------|--------|------|
| P0 | Verify + fix app production webpack `.export.css` → raw string; add CI grep on build output | Implementation |
| P0 | Re-save affected popups after deploy OR one-time migration script to refresh step styles | Ops |
| P1 | Fix text alignment split-brain (`TextCanvas` → `style.textAlignment`; stop execCommand block align for text) | Implementation |
| P1 | RTE toolbar full-block ops → trait-only (Option C) | Implementation |
| P2 | Conservative sanitizer: strip transparent background, empty style attrs | Implementation |
| P2 | Dev-only preview mount invariant | Implementation |
| P3 | Duplicate renderCss dedupe | Optional perf |

**Not recommended now:** export-time trait equality stripping; Dropdown / next block-family migration.

---

## O. Obsolete code that would become deletable

After P0–P1 proven in production:

| Code | Condition |
|------|-----------|
| `properties.align` on text blocks | After alignment unified to `style.textAlignment` |
| `normalizeSheetCss` workarounds for `[object Object]` | After webpack always returns string |
| Legacy inline text/cross serializers in stale bundles | After deploy + preview diagnostic |
| Duplicate alignment via execCommand in text RTE | After Option C toolbar refactor |
| `applyRichTextLinkStyles` inline on text anchors (Phase 5D already moved links to renderCss) | If any call sites remain for text |

---

## P. Tests added and evidence

| Test file | Proves |
|-----------|--------|
| `utils/__tests__/popupProductionCssRteOwnership.audit.test.ts` | Jest/craco-path `.export.css` strings real; serialized step HTML has `.pbn-floating-field`, no mini-css stub; trait color doesn't mutate content; sanitizer preserves inline; `properties.align` orphan |
| `utils/__tests__/popupProductionCssRteOwnership.audit.browser.test.ts` | Partial RTE color wins cascade; content `text-align` defeats trait; dual runtime renderCss teardown isolation |
| Existing `exportCssSheets.test.ts` | Sheet load baseline |
| Existing `textPresentation.browser.test.ts` | Trait-only renderCss computed styles |
| Existing `renderArtifactCssLifecycle.test.ts` | Artifact mount/teardown/duplicate runtime |

**Gap:** post-`craco build` bundle grep test — recommended as CI, not yet added (requires build in CI).

---

## Q. Blocking decisions before next block-family migration

| # | Decision | Status |
|---|----------|--------|
| 1 | `.export.css` must be raw string in **production app bundle** | **Decided** — implement + verify |
| 2 | Embed esbuild needs no `.export.css` loader today | **Decided** — document invariant |
| 3 | RTE ownership contract (§K) | **Decided** — implement before more block presentation moves |
| 4 | Reject value-equality stripping as primary cleanup | **Decided** |
| 5 | Resume Dropdown / Phase 6 block families | **Blocked** until P0 + P1 |

---

## Required final answer format

1. **Production `.export.css` root cause:** Save-time **adflipr-app production webpack** resolves `.export.css` imports via mini-css-extract/css-loader instead of `asset/source`, embedding JS stub text into saved step `<style>` blocks; embed runtime hoists that text unchanged. **Not** caused by esbuild embed bundle (embed does not import serializer CSS).

2. **Production build fix recommended:** Enforce `asset/source` (or equivalent raw-string loader) for `*.export.css` in **craco production build**; add CI post-build grep asserting `pbn-floating-field` present and `mini-css-extract-plugin` absent in main JS chunks; re-save affected popups.

3. **`.export.css` implementation ready:** **NO** — verify production bundle first, then implement + CI gate.

4. **RTE block-trait duplication confirmed:** **PARTIAL** — Style panel traits do not duplicate into content; RTE toolbar alignment/color/font-size can duplicate block presentation into content; `properties.align` orphan exacerbates alignment split.

5. **Properties with proven ownership conflict:** `color`, `text-align`, `font-size` (partial vs full), `font-family`, `line-height`, `background-color` (highlight vs block bg).

6. **Properties safely RTE-owned:** partial selection `color`, partial `font-size`, non-transparent highlight `background-color`, semantic tags (`bold`/`italic`/lists), link markup.

7. **Properties safely block-owned:** wrapper `margin`, block `padding`, `width`/`height`, `border`/`border-radius`, default typography on `> div` via traits, link descendant color in `renderCss`.

8. **Formatting intent recoverable:** **PARTIAL** — partial spans yes; full-block inline matching traits no; no operation provenance persisted.

9. **Recommended RTE ownership contract:** §K — traits → `renderCss` only for block-wide presentation; RTE inline for partial ranges only; fix alignment path; conservative sanitizer for noise.

10. **Safe immediate cleanup:** Option E — strip `background-color: transparent`, empty `style=""`, invalid CSS; plain-text paste already safe.

11. **Unsafe cleanup rejected:** Option A value-equality stripping; Option B aggressive persistence normalization; blind inline removal before contract.

12. **Preview diagnostic recommended:** **YES** — dev-only warn when migrated HTML + non-empty `renderCss` but zero mounted artifact styles (§L).

13. **Duplicate renderCss classification:** **Performance debt** — acceptable; independent per-runtime teardown; not a correctness blocker.

14. **Production correctness blockers:** (1) invalid step export CSS stub in saved htmlContent; (2) text alignment split-brain + inline `text-align` defeating traits.

15. **Next implementation phase recommended:** **Phase P0** — production `.export.css` build fix + CI verification + re-save; **Phase P1** — RTE ownership contract (alignment fix + toolbar trait-only block ops + conservative sanitizer).

16. **Ready to resume next block-family migration:** **NO**
