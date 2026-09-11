# Popup CSS end-to-end lifecycle audit

**Scope:** Popup HTML and CSS only — generation, storage, WordPress bootstrap, runtime injection, `<style>` hoisting, cleanup, isolation.  
**Not in scope:** Triggers, analytics, form validation logic, backend architecture beyond embed payload, JavaScript block behavior (except mount/teardown that owns styles).  
**Code:** `adflipr-app/src/layouts/PopupBuilder/`  
**As of:** 2026-07-04  
**Related:** [`popup-html-serializer-styles.md`](./popup-html-serializer-styles.md), [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md), [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md) (**target**), [`wordpress-popup-rendering-audit.md`](./wordpress-popup-rendering-audit.md), [`popup-builder-rendering-pipeline.md`](./popup-builder-rendering-pipeline.md)

---

## Executive summary

| Question | Answer |
|----------|--------|
| How is CSS generated? | Builder model → `serializeStepHtml()` → inline `style=""` + embedded `<style>` blocks (static sheets + `@media` + custom CSS) |
| What is stored? | `metadata.htmlByStep` — JSON map of **HTML strings** that already contain inline CSS and `<style>` tags (not separate CSS) |
| Does WordPress render CSS? | **No** — identity + gating + async loader for `adflipr-popup-embed.js` only |
| How does runtime apply CSS? | `innerHTML` → `hoistExportedStepStyles()` clones `<style>` to `document.head` |
| Is architecture correct? | **Mostly yes** — fix dismiss cleanup, global `::placeholder`, inline multi-popup head-key collision, unscoped custom CSS |
| Inline vs one scoped sheet? | **~60–70%** inline must stay (trait + CSS vars + `!important`); **~20–30%** structural chrome sheet-eligible; **~10–18%** duplicated with existing sheets (§11) |
| Responsive `@media` vs inline? | **~35–45%** of device rules use `!important` (reliable); **~55–65%** use specificity only — **cannot beat** desktop inline on same property (§12, PCL-008) |

**Recommendation:** Keep architecture; fix specific lifecycle/isolation problems (see §10) — **or** migrate per [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md) (target: `renderCss`, no presentation inline in new artifacts).

---

## 1. CSS generation path

### Sequence

```
PopupBuilderSnapshot (metadata.builderState — source of truth)
  ↓ FormBuilder.handleSave → buildCurrentSnapshot()
serializePopupExport(snapshot)                         [popupHtmlSerializer/index.ts]
  ↓ serializePopupHtmlByStep() per exportable step
serializeStepExportHtml() → serializeStepHtml()        [serializeLayout.ts]
  ↓ serializeFormBody() → serializeLayout() / serializeElement()
Block serializers + styles/inline/* builders           [serializeElements.ts, blocks/*/serialize.ts]
  ↓
Inline style="..." + internal <style> blocks
  ↓
Final HTML string per step
  ↓
buildPopupSaveMetadata()                               [popupBuilderState.ts]
  ↓ JSON.stringify(htmlByStep)
Popup.metadata + htmlContent                           [Popup.java]
  ↓
GET /popups/embed/{id} → PopupEmbedResponse            [PopupEmbedResponse.java]
```

### Key files

| Stage | File | Functions |
|-------|------|-----------|
| Save | `components/form-builder/FormBuilder.tsx` | `handleSave` → `serializePopupExport`, `buildPopupSaveMetadata` |
| Export entry | `utils/popupHtmlSerializer/index.ts` | `serializePopupExport`, `serializePopupHtmlByStep` |
| Step HTML + `<style>` | `utils/popupHtmlSerializer/serializeLayout.ts` | `serializeStepHtml`, `serializeFormBody` |
| Blocks | `utils/popupHtmlSerializer/serializeElements.ts` | `serializeElement` |
| Static sheets | `utils/popupHtmlSerializer/styles/sheets/*.export.css` | via `styles/sheets/index.ts` |
| Trait inline | `utils/popupHtmlSerializer/styles/inline/*.ts` | e.g. `buildRadioInputExportStyleAttr` |
| Responsive `@media` | `utils/popupHtmlSerializer/responsiveDeviceCss.ts` | `buildResponsiveDeviceCss` |
| Teaser | `utils/popupHtmlSerializer/serializeTeaser.ts` | `serializeTeaserHtml` |
| Sanitize | `utils/popupHtmlSerializer/popupHtmlSanitize.ts` | `sanitizeCustomCss`, `sanitizeRichTextHtml` |

### CSS classification

| Source | Type | Where |
|--------|------|-------|
| Surface, layouts, blocks, traits | **Inline** `style=""` | `serializeLayout.ts`, `serializeElements.ts`, block serializers |
| Host-theme resistance (radio border) | **Inline** `!important` | `styles/inline/blockStyles.ts` → `buildRadioInputExportStyleAttr` |
| Floating labels, overflow, radio `:checked`, entrance | **Internal** `<style>` | Injected in `serializeStepHtml` from `*.export.css` |
| Desktop/mobile deltas, hide-on-device | **Internal** `<style>` + `@media` | `buildResponsiveDeviceCss()` → `data-adflipr-popup-device-css` |
| Merchant custom CSS | **Internal** `<style>` | `formStyle.customCss` after `sanitizeCustomCss()` |
| Rich-text links | **Inline on `<a>`** + **scoped `<style>`** | `applyRichTextLinkStyles` + `buildRichTextLinkExportCss` |
| Teaser host/animation | **In teaser HTML** + **runtime once** | `serializeTeaser.ts` + `ensureTeaserExportStyles()` |
| Entrance animation | **Runtime class** on embed host | `applyPopupEntranceAnimation()` (CSS pre-exported) |
| Overlay/banner/flyout shell | **Runtime inline** `cssText` | `embed/runtime.ts` |
| External per-popup CSS files | **None** | No `<link>` in export |

### Generation rules (evidence)

- **Single responsive artifact per step:** `serializePopupExport` always passes `device: 'desktop'` for base inline; mobile overrides are `@media` in device `<style>`, not a second HTML map.

```115:125:adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/index.ts
  const htmlByStep = serializePopupHtmlByStep(snapshot, { ...options, device: 'desktop' });
```

- **Per-step `<style>` injection** in `serializeStepHtml`:

```316:340:adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/serializeLayout.ts
  const cssBlock = joinHtml([
    openTag('style', { [POPUP_ATTR_STYLE_FLOATING_LABEL]: 'true' }) + POPUP_FLOATING_LABEL_EXPORT_CSS + closeTag('style'),
    openTag('style', { [POPUP_ATTR_STYLE_RESPONSIVE]: 'true' }) + POPUP_EMBED_RESPONSIVE_EXPORT_CSS + closeTag('style'),
    // entrance (conditional), radio, rich-text-links, device-css (conditional), custom-css (conditional)
  ]);
```

- **Custom CSS sanitization** (XSS tags only — not selector scoping):

```180:187:adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/popupHtmlSanitize.ts
export function sanitizeCustomCss(css: string): string {
  return css.replace(/<script[\s\S]*?<\/script>/gi, '').replace(/<\/?[^>\s]+[^>]*>/gi, '');
}
```

### Duplication by design

| What | Why |
|------|-----|
| Static sheets per step in `htmlByStep` | Each step runs full `serializeStepHtml` |
| Link color inline + `<style>` | Host cascade resistance |
| Radio inline border + sheet for `:checked` | Theme `input { border: none !important }` vs pseudo-states |

---

## 2. Stored artifact

### Format

```text
HTML containing inline style="" attributes + embedded <style> blocks
```

**Not** HTML-only. **Not** separate CSS storage.

| Field | Content |
|-------|---------|
| `metadata.builderState` | **Source of truth** (editor model) |
| `metadata.htmlByStep` | **Generated artifact** — JSON `Record<stepId, htmlFragment>` |
| `htmlContent` | Primary step duplicate (thumbnails, fallback) |

Backend (`PopupEmbedResponse.parseHtmlByStep`) treats each value as an **opaque HTML string** — no CSS parsing.

### Logical shape

```json
{
  "htmlPrimaryStepId": "step-1",
  "htmlByStep": {
    "step-1": "<style data-adflipr-popup-floating-label-css>...</style>...<form data-adflipr-popup-export=\"true\" style=\"...\">...</form>",
    "thank-you": "<style ...>...</style><form data-adflipr-popup-step=\"thank-you\">...</form>",
    "teaser": "<style data-adflipr-popup-device-css>...</style><div data-adflipr-popup-teaser=\"true\" style=\"...\">...</div>"
  }
}
```

### Answers

| Question | Answer |
|----------|--------|
| `htmlByStep` contains `<style>`? | **Yes** — min. floating-label + responsive + radio per form step |
| Contains inline `style=""`? | **Yes** — surface, layouts, blocks |
| CSS duplicated across steps? | **Yes** — static sheets repeated per step |
| Desktop/mobile duplicated? | **No** — one HTML; `@media` in device block |
| Backend understands CSS? | **No** |

Tests: `utils/__tests__/popupHtmlSerializer.test.ts` (`exports htmlByStep for multiple visible steps`, `exports single responsive htmlByStep with mobile padding in device css`).

---

## 3. WordPress bootstrap (verified)

WordPress **never** receives, stores, or injects popup HTML/CSS.

```112:143:wordpress/includes/class-adflipr-popup-embed.php
// enqueue_storefront_embed() → wp_add_inline_script loader only
// loads adflipr-popup-embed.js with data-adflipr-popup-api + data-adflipr-site
```

| Question | Answer |
|----------|--------|
| WP receives popup HTML? | **No** |
| WP injects popup HTML/CSS? | **No** |
| WP knows serialized CSS format? | **No** |

**Role:** identity (`adflipr_store_uuid`) + gating + async runtime load.  
Full pipeline: [`wordpress-popup-rendering-audit.md`](./wordpress-popup-rendering-audit.md).

---

## 4. Runtime HTML injection

### Path

```
embed/index.ts → EmbedHost.installBootstrap
  → fetchPopupSiteCandidates [embed/api.ts]
Trigger fires
  → loadAndShowPopup → fetchPopupEmbedConfig [embed/runtime.ts, embed/api.ts]
  → normalizeEmbedConfig [embed/utils.ts]
presentConfig → openOverlay | openBanner | openFlyout | renderInline | renderTeaser
  → resolveStepHtml(config, stepId)
  → container.innerHTML = html
  → hoistExportedStepStyles(container)
```

### Containers

| Mode | `innerHTML` target | Parent |
|------|-------------------|--------|
| Overlay | `contentEl` (`data-adflipr-popup-embed-content`) | `#adflipr-popup-embed-root` on `document.body` |
| Banner | `bannerContentEl` | `.adflipr-popup-embed-banner` |
| Flyout | `flyoutContentEl` | `.adflipr-popup-embed-flyout` |
| Inline | `inlineMountEl` | Merchant mount node |
| Teaser | `teaserHostEl` | `body` or presentation root |

```679:689:adflipr-app/src/layouts/PopupBuilder/embed/runtime.ts
  private renderStepInto(container: HTMLElement, stepId: string): void {
    const html = resolveStepHtml(this.config, stepId);
    this.disposeStepBlockInits();
    container.innerHTML = html;
    const unhoistStyles = hoistExportedStepStyles(container);
```

### DOM immediately after `innerHTML` (before hoist)

```html
<div data-adflipr-popup-embed-content="true">
  <style data-adflipr-popup-floating-label-css="true">...</style>
  <style data-adflipr-popup-responsive-css="true">...</style>
  <style data-adflipr-popup-radio-css="true">...</style>
  <style data-pbn-rich-text-links="true">...</style>
  <!-- optional: entrance, device-css, custom-css -->
  <form data-adflipr-popup-export="true" data-adflipr-popup-step="step-1" style="width:100%;...">
    <div data-adflipr-popup-surface="popup" style="background-color:#FFFFFF;...">...</div>
  </form>
</div>
```

Preview uses the same hoisting path as storefront: `FormPreview` → `mountFormPreviewEmbedPresentation` → `PopupEmbedRuntime.renderStepInto` → `hoistExportedStepStyles` / `mountRenderArtifactCss`.

---

## 5. `<style>` hoisting (critical)

### Implementation

File: `embed/mountExportedStepStyles.ts` — `hoistExportedStepStyles(container)`

| Behavior | Detail |
|----------|--------|
| MOVE original? | **No** — **clone** to `document.head`, **remove** original from container |
| Ownership marker | `data-adflipr-popup-runtime-style="{key}"` where `key` comes from export marker attrs |
| Dedup on hoist | Removes existing head nodes with same runtime key before append |
| Teardown | Returns `() => void` that removes cloned nodes from head |
| Wired into runtime | Pushed to `stepBlockDisposables` via `disposableFrom(unhoistStyles)` |

```28:58:adflipr-app/src/layouts/PopupBuilder/embed/mountExportedStepStyles.ts
export function hoistExportedStepStyles(container: HTMLElement): () => void {
  // clone style → document.head.appendChild(clone); styleEl.remove();
  // return () => hoisted.forEach(el => el.remove());
}
```

**Why:** Comment in source — browsers do not always apply `<style>` left inside a subtree set via `innerHTML`. KB: [`popup-html-serializer-styles.md`](./popup-html-serializer-styles.md) § Radio export / Style hoisting.

### Before / after

**Before hoist:** `<style>` siblings before `<form>` inside content container.  
**After hoist:** `<style>` in `document.head` with runtime marker; container has `<form>` only.

Tests: `embed/__tests__/mountExportedStepStyles.test.ts`.

---

## 6. Lifecycle scenarios

### A — First open

1. Trigger → `renderStepInto`
2. Typically **4–7** hoisted `<style>` nodes in `<head>` per step (+ `adflipr-teaser-export-styles` if teaser path via `ensureTeaserExportStyles`)

### B — Step transition

`transitionToStep` → `renderStepInto`:

1. `disposeStepBlockInits()` — runs previous unhoist teardown
2. `innerHTML` replaces step DOM
3. Fresh hoist

Step 1 styles do **not** accumulate; head keys are replaced per `styleBlockKey`.

### C — Popup close (asymmetry)

```631:632:adflipr-app/src/layouts/PopupBuilder/embed/runtime.ts
  private closeOverlay(): void {
    this.hideOverlay();  // display:none only
```

| On dismiss | Behavior |
|------------|----------|
| Popup DOM | **Hidden**, not destroyed; `innerHTML` content remains |
| Hoisted `<style>` in head | **Not removed** — `disposeStepBlockInits()` not called on close |
| Cleanup owner | `stepBlockDisposables` — only on next `renderStepInto`, `destroy()`, or teaser remount |

**Finding PCL-001 (P1):** Mount adds head styles; dismiss does not unhoist.

### D — Reopen 10×

- Each **reopen** via `renderStepInto`: dispose first → no accumulation.
- Each **close only**: hoisted styles **remain** in `document.head` until navigation or `destroy()`.
- Hoist dedupes by runtime key before append.

### E — Two popups

| Mode | Behavior |
|------|----------|
| Overlay | Single `PopupEmbedRuntime` per site (`EmbedHost`) — one popup at a time |
| Inline | Multiple runtimes (`inlineRuntimes` Set) |

**Finding PCL-002 (P1):** `styleBlockKey()` is **not popup-instance-scoped**. Hoist removes **any** head style with the same runtime key — inline Popup B can remove Popup A's hoisted CSS.

### F — Device resize

- Runtime does **not** remount on viewport change (`resolveStepHtml` has no device branch).
- Single artifact uses `@media` — resize toggles rules; no stale desktop/mobile HTML.

---

## 7. CSS isolation

### Export sheet selectors

| Selector pattern | Classification |
|------------------|----------------|
| `form[data-adflipr-popup-export="true"] ...` | Properly scoped |
| `.pbn-floating-field ...` | Class-scoped (popup-specific) |
| `input::placeholder, textarea::placeholder` | **Global — PCL-003 (P1)** |
| Merchant `customCss` | **Unscoped — PCL-004 (P1)** (sanitized for tags/scripts only) |
| `@keyframes pbn-popup-kf-*` | Global names (prefixed, low collision) |

Global placeholder leak (evidence):

```92:96:adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/styles/sheets/floating-label.export.css
input::placeholder,
textarea::placeholder {
  opacity: 1;
  color: var(--pbn-canvas-input-placeholder, #9ca3af);
}
```

### Leakage summary

**Adflipr → merchant**

1. Hoisted `<style>` in `document.head` — applies site-wide until removed.
2. Global `::placeholder` rule.
3. Unscoped merchant `customCss`.
4. `ensureTeaserExportStyles` — `#adflipr-teaser-export-styles` persists for page lifetime.

**Merchant → Adflipr**

1. Theme `input { border: none !important }` — mitigated by radio inline `!important`.
2. Generic form/button/img resets — mitigated by `form[data-adflipr-popup-export]` scoping + trait inline where needed.

---

## 8. Why hoisting (options)

| Option | Assessment |
|--------|------------|
| A — Keep `<style>` in container | Matches storage; fails observed browser behavior; preview also hoists |
| B — Hoist to head (**current**) | Works with stored artifact; needs lifecycle ownership |
| C — Separate HTML + CSS storage | Requires API/schema change; backend is opaque strings today |
| D — One runtime stylesheet | Dedupes across steps; larger refactor |

**Conclusion:** Hoisting is justified; problems are cleanup and global keys, not the hoist mechanism itself.

---

## 9. Architecture correctness checklist

| # | Question | Verdict |
|---|----------|---------|
| 1 | `<style>` inside stored HTML correct? | **Yes** |
| 2 | `innerHTML` for trusted generated artifact? | **Yes** (sanitized at save) |
| 3 | Hoist to `document.head` necessary? | **Yes** (documented browser + preview parity) |
| 4 | Style ownership explicit and safe? | **Partial** — teardown exists; keys global not per-instance |
| 5 | Cleanup symmetrical mount/unmount? | **No** — dismiss does not unhoist |
| 6 | CSS leak to merchant site? | **Yes** — placeholder, head persistence, custom CSS |
| 7 | Merchant CSS break popup? | **Possible** — partial mitigations |
| 8 | Repeated opens duplicate styles? | **No** on re-open; **Yes** after close-only |
| 9 | Multiple inline popups interfere? | **Yes** — shared head keys |
| 10 | Production-ready? | **Conditional** — fix PCL-001–004 |

---

## 10. Verdict and scorecard

### Architecture (current)

```text
Builder → serializeStepHtml (inline + <style>)
  → metadata.htmlByStep (opaque HTML strings)
  → API → EmbedHost / PopupEmbedRuntime
  → innerHTML → hoistExportedStepStyles → document.head
  → stepBlockDisposables teardown (step change / destroy — NOT dismiss)
```

### Scorecard (1–10)

| Area | Score |
|------|-------|
| CSS generation | 8 |
| Storage format | 7 |
| Runtime mounting | 8 |
| Style hoisting | 7 |
| CSS isolation | 5 |
| Cleanup | 5 |
| Multi-popup safety | 6 |
| Maintainability | 8 |
| Production readiness | 7 |

### Findings registry

| ID | Priority | Finding |
|----|----------|---------|
| PCL-001 | **P1** | Dismiss hides overlay but does not call `disposeStepBlockInits()` — hoisted styles remain in `document.head` |
| PCL-002 | **P1** | Inline multi-popup: `styleBlockKey` not instance-scoped; later hoist removes prior popup's head styles |
| PCL-003 | **P1** | `input::placeholder, textarea::placeholder` in `floating-label.export.css` is globally scoped |
| PCL-004 | **P1** | Merchant `customCss` not auto-scoped to `form[data-adflipr-popup-export]` |
| PCL-005 | **P2** | Static export sheets duplicated in every `htmlByStep` entry (payload size) |
| PCL-006 | **P2** | Rich-text link styling duplicated (inline on `<a>` + `<style>` block) |
| PCL-007 | **P2** | ~10–18% of inline structural rules duplicate export sheet classes (floating label wrap/label); removable without new sheet |
| PCL-008 | **P1** | ~55–65% of `buildResponsiveDeviceCss()` trait rules use specificity only (no `!important`) on properties also desktop-inlined — likely **cannot** override inline per CSS cascade (§12) |

Cross-reference production bugs: [`popup-embed-production-bugs.md`](./popup-embed-production-bugs.md) (PEB-041 related to dismiss cleanup).

### Recommendation

**Keep architecture but fix specific lifecycle problems:**

1. Unhoist on dismiss (or instance-scoped runtime style keys).
2. Scope `::placeholder` under `form[data-adflipr-popup-export="true"]`.
3. Instance-scoped `data-adflipr-popup-runtime-style` keys for inline multi-popup.
4. Optional: wrap merchant custom CSS in popup scope at serialize time.

**Do not:** stop hoisting, separate HTML/CSS storage, Shadow DOM, iframe rendering, or external per-popup CSS files.

---

## 11. Inline CSS decomposition — design data vs sheet-eligible chrome

**Question:** How much of today’s inline `style=""` is truly popup-specific **design data** (per-instance traits), and how much could move into **one generated, scoped internal stylesheet**?

**Method:** Code taxonomy across `styles/inline/*.ts`, block `serialize.ts` files, `serializeLayout.ts`, and existing `styles/sheets/*.export.css`. Declaration-level estimates use a representative step (text + floating email + button) — same fixture as `utils/__tests__/popupCssInlineMix.test.ts` (run for fresh byte counts when Jest is available).

### 11.1 Two CSS channels today (do not conflate)

| Channel | What it holds | Scoped? | Duplicated per step? |
|---------|---------------|---------|----------------------|
| **Internal `<style>` blocks** | Static sheets + generated `@media` + custom CSS | Mostly `form[data-adflipr-popup-export="true"]` | **Yes** — full sheet bundle per `htmlByStep` entry |
| **Inline `style=""`** | Trait values + structural chrome + CSS variables | Per element | Per element (necessary for traits) |

This section analyzes **inline only**. Moving static **`<style>`** duplication to one sheet per popup is a separate win (PCL-005); inline consolidation is additional.

### 11.2 Classification of inline declarations

| Class | Definition | Examples in code | Must stay inline? |
|-------|------------|------------------|-------------------|
| **A — Trait design data** | Values from `formStyle` / `element.properties.style` / block traits | Surface bg/padding/radius (`buildEmbeddedFormSurfaceStyle`), `getButtonStyle`, `getElementStyle`, timer colors/fonts, image px dimensions, cross colors | **Yes** — per popup / per block instance |
| **B — Structural chrome** | Layout/reset identical for every export; no trait input | `width:100%`, `border:none`, `display:flex`, `box-sizing:border-box`, phone row flex, option row gap, YouTube 16:9 box | **No** — candidate for scoped sheet + existing classes |
| **C — CSS variables on element** | Trait encoded as `--pbn-*` for pseudo-class sheets | `--pbn-radio-accent`, `--pbn-canvas-input-focus`, `--pbn-floating-label-bg` | **Yes on element** — sheet cannot know per-group values without per-id rules |
| **D — Host-defense inline** | Trait + `!important` vs merchant theme | `buildRadioInputExportStyleAttr` border/background | **Yes** — even if sheet exists for `:checked` |
| **E — Duplicated structural** | Class B rules **also** in an export sheet or builder class | `buildFloatingFieldWrapStyle` vs `.pbn-floating-field` in `floating-label.export.css` | **Remove from inline** — sheet already covers (PCL-006) |

### 11.3 Estimated mix (representative popup step)

Approximate share of **inline `style=""` declarations** on a typical step (popup + text + floating email + button):

| Class | Share of inline declarations | Share of inline **bytes** (approx.) | Notes |
|-------|-------------------------------|-------------------------------------|-------|
| **A — Trait design data** | **55–65%** | **60–70%** | Dominates surface, button, text typography, input shell borders/colors, layout padding from model |
| **B — Structural chrome** | **20–28%** | **15–22%** | Input resets, flex rows, form `width:100%`, gamification 50/50 columns, feedback flex rows |
| **C — CSS variables** | **8–12%** | **8–10%** | Floating-label + radio group vars; small byte count but required on node |
| **D — Host-defense** | **3–5%** | **5–8%** | Radio `!important` border/background strings |
| **E — Duplicated (removeable)** | **10–18%** of B | **3–6%** of total inline | Floating wrap/label position rules that mirror `floating-label.export.css` |

**Headline answer:**

- **~60–70%** of inline CSS is **truly popup-specific design data** (class A + C + D) and should remain attribute-driven (inline or CSS vars on the element).
- **~20–30%** of inline declarations are **structural chrome** (class B); **~25–35% of those** are safe to move into **one scoped internal stylesheet** without visual change, if markup keeps existing classes (`pbn-floating-field`, `pbn-canvas-input-shell`, `pbn-display-radio-row`, etc.).
- **~10–18%** of structural inline is **already duplicated** in export sheets (class E) — delete inline, rely on sheet (hygiene, not new architecture).

### 11.4 Already in scoped internal sheets (not inline)

These are **out of scope for “move off inline”** — already centralized in `serializeStepHtml` `<style>` injection:

| Sheet | Lines (approx.) | Role |
|-------|-----------------|------|
| `floating-label.export.css` | ~96 | Float animation, input reset inside `.pbn-floating-field`, phone chrome |
| `embed-responsive.export.css` | ~63 | Overflow guards, field-error visibility |
| `radio.export.css` | ~36 | `:checked`, `::after`, `:focus-visible` |
| `popup-entrance.export.css` | ~51 | `@keyframes` + host class animations |
| `buildResponsiveDeviceCss()` | variable | Per-block `@media` trait overrides (trait data, but correctly in sheet) |

Static sheets total **~250 lines** per step today, **repeated for every step** in `htmlByStep` (PCL-005).

### 11.5 Inline inventory by area

| Area | Primary inline source | Mostly A / B / C / D | Sheet-eligible? |
|------|----------------------|----------------------|-----------------|
| Form surface | `buildEmbeddedFormSurfaceStyle` | **A** | No — all trait |
| Form wrapper | `serializeStepHtml` form attrs | **B** | Yes — `width/max-width/box-sizing` under export form scope |
| Layout / columns | `serializeLayout` | **A** + **B** | Partial — flex column stack rules; trait padding stays inline |
| Block wrapper | `getExportWrapperStyleFromLayout` | **A** + **B** | Partial — `width:100%`, `box-sizing` |
| Text / button | `getTextBlockContentStyle`, `getButtonStyle` | **A** | No |
| Input (non-floating) | `inputOutlineStyle`, `innerInputFieldStyle` | **A** + **B** | Partial — inner `border:none; background:transparent` → sheet |
| Floating label | `floatingOutlineStyle`, `buildFloatingFieldWrapStyle`, `buildFloatingLabelStyle` | **A** + **E** | **E** — remove duplicate structural; keep trait vars (**C**) on shell |
| Phone | `buildPhoneRowStyle`, `buildPhoneSelectStyle`, `buildPhoneDividerStyle` | **B** + **A** typography | Row/divider/select chrome → sheet (partially in floating-label sheet already) |
| Radio / checkbox | `buildFormOptionRowStyle`, `buildRadioInputExportStyleAttr` | **B** + **D** | Row layout → sheet; input border → stay **D** |
| Divider | `buildDividerInnerStyle` | **A** (color/width) + **B** | `display:block; height:0` → sheet |
| Image / video | `buildImageBlockStyle`, `buildYoutubeWrapStyle` | **A** + **B** | 16:9 padding-box pattern → sheet; trait dimensions stay inline |
| Timer | `serializeTimer` inline builders | **A** (heavy) | Digit colors/fonts stay inline; unit flex shell partial **B** |
| Spinner | `spinnerStyles.ts` constants | **B** (fixed) | **Yes** — all `SPINNER_*` constants → one scoped sheet |
| Product | `commerceAndCrossStyles.ts`, `product/serialize.ts` | **A** + **B** | Card flex/layout → sheet; title/price/button colors stay **A** |
| Coupon | `COUPON_INNER_ROW_STYLE`, `COUPON_COPY_BUTTON_STYLE` | **B** (fixed defaults) | **Yes** — defaults to sheet; trait shell from `buildCouponShellStyle` stays **A** |
| Feedback | `mediaAndFeedbackStyles.ts` | **A** + **B** | Flex row/center → sheet; colors stay **A** |
| Cross / floating-image | position + margin px | **A** | No — per-element offsets |
| Teaser | `serializeTeaserHtml` | **A** | No — all trait-driven chip |
| Gamification columns | `serializeFormBody` 50% columns | **B** | **Yes** — `[data-adflipr-popup-surface="gamification"]` layout rules (partially overlaps `buildGamificationResponsiveCss`) |

### 11.6 What “one generated, scoped internal stylesheet” means

**Feasible shape** (no storage redesign):

```css
/* Single block: form[data-adflipr-popup-export="true"] { ... } */
/* + existing class hooks (.pbn-floating-field, .pbn-canvas-input-shell, ...) */
/* + one @media section merged from buildResponsiveDeviceCss (already generated) */
/* + optional per-popup customCss appended last */
```

**Could merge into one `<style data-adflipr-popup-bundle>` per step (or per popup at runtime):**

1. Current four static `*.export.css` files (already scoped).
2. Generated `buildResponsiveDeviceCss()` output (already scoped to `form[...]`).
3. **New** `layout-chrome.export.css` (or generated chunk) absorbing class **B** rules currently inline: input resets, option rows, coupon copy btn, spinner chrome, feedback flex, gamification column flex, form wrapper box model.
4. Merchant `customCss` (last, optionally auto-wrapped — PCL-004).

**Must remain inline (or as CSS vars on elements):**

- All class **A** trait properties (colors, fonts, spacing, borders, shadows, dimensions).
- All class **C** variables on group/shell nodes.
- All class **D** `!important` radio (and similar) defenses.
- Rich-text `<a style="color:…">` (or keep scoped sheet only — today both, PCL-006).
- Per-element absolute positioning (cross, floating-image, teaser offsets).

### 11.7 Payload and maintainability impact (order of magnitude)

| Change | Inline reduction | Storage reduction | Risk |
|--------|------------------|-------------------|------|
| Remove class **E** duplicates (floating structural inline) | ~3–6% bytes | None | Low — sheet already exists |
| Move class **B** to scoped sheet | ~15–22% inline declarations | Slight **increase** in single sheet if not deduping steps | Low if classes unchanged |
| Dedupe static sheets **once per popup** (not per step) | None | **~8–15 KB × (steps−1)** per popup | Medium — hoist/ownership must handle one bundle (ties to PCL-001/002) |
| Move trait **A** to sheet via per-id rules | Theoretical | — | **High** — explodes rule count; **do not** |

### 11.8 Recommendation (inline vs one sheet)

| Action | Priority |
|--------|----------|
| **Keep ~60–70% inline** (trait + vars + host-defense) | — |
| **Delete duplicated floating-label structural inline** (class E) | P2 hygiene |
| **Add one `layout-chrome` scoped sheet** for shared class B rules (spinner, coupon, feedback flex, input resets not already in floating-label sheet) | P2 optimization |
| **Collapse per-step static `<style>` tags into one bundle tag** per step; dedupe across steps at save or runtime | P2 (PCL-005) |
| **Do not** move trait colors/fonts/spacing off inline unless using per-block-id CSS rules | — |

**Verdict:** The current **inline-first for traits** split is correct. The win is not “replace inline with classes everywhere” — it is **(1)** stop duplicating structural rules that sheets already own, **(2)** centralize remaining structural chrome into **one scoped generated sheet** alongside existing export sheets, and **(3)** emit that bundle **once per popup** instead of per step.

### 11.9 Evidence and regression

| Artifact | Purpose |
|----------|---------|
| `utils/__tests__/popupCssInlineMix.test.ts` | Declaration/byte mix metrics (`POPUP_CSS_INLINE_MIX` console JSON) |
| `utils/__tests__/popupHtmlSerializer.test.ts` | Serializer contracts per block |
| PCL-006 | Floating-label inline duplication (class E) |

---

## 12. Responsive override mechanism (`buildResponsiveDeviceCss`)

**Question:** For every responsive property emitted by `buildResponsiveDeviceCss()`, how does the `@media` rule override the corresponding desktop inline style?

**Source:** `utils/popupHtmlSerializer/responsiveDeviceCss.ts` — injected in `serializeStepHtml` as `<style data-adflipr-popup-device-css>` (`serializeLayout.ts`).

### 12.1 Cascade context (export contract)

| Layer | Desktop values | Mobile values |
|-------|----------------|---------------|
| Inline `style=""` | Always serialized with `device: 'desktop'` (`serializePopupExport` → `serializeStepHtml`, `ctx.device: 'desktop'`) | **Not** inlined — only in `@media` |
| Device `<style>` | N/A | `@media only screen and (max-width: 480px)` (`POPUP_RESPONSIVE_MAX_WIDTH_PX`) |

**CSS cascade fact:** Author stylesheet rules (including inside `@media`) **lose to inline `style=""` on the same element and property** unless the stylesheet declaration uses **`!important`**. Higher selector specificity does **not** beat inline. **CSS variables are not used** for responsive trait switching in this pipeline.

```50:53:adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/responsiveDeviceCss.ts
const FORM_SCOPE = `form[${POPUP_ATTR_EXPORT}="true"]`;
const MEDIA_MOBILE = `@media only screen and (max-width: ${POPUP_RESPONSIVE_MAX_WIDTH_PX}px)`;
const MEDIA_DESKTOP = `@media only screen and (min-width: ${POPUP_RESPONSIVE_MAX_WIDTH_PX + 1}px)`;
```

After `hoistExportedStepStyles()`, device CSS lives in `document.head` — same cascade rules vs inline on nodes in the popup subtree.

### 12.2 Mechanisms used (summary)

| Mechanism | Used? | Where |
|-----------|-------|-------|
| **`!important` in `@media`** | **Yes** — layout stack, product layouts, gamification, hide-on-device, phone select | See §12.3 rows marked **!important** |
| **Higher specificity** | Selectors like `form[…] [data-adflipr-block-id="…"]` | **Insufficient alone** vs inline on same node |
| **CSS variables** | `--pbn-*` on elements | **Not** used for responsive trait overrides |
| **No conflicting inline** | Mobile sets properties desktop left unset on same element | Rare (e.g. `font-size` on block wrapper when only inner nodes have typography inline) |
| **`display:none !important`** | Hide-on-device attrs | Overrides any inline `display` on that node |

### 12.3 Per-generator property matrix

`buildResponsiveDeviceCss()` concatenates these builders (`responsiveDeviceCss.ts` L594–647):

#### A — `buildBlockDeviceVisibilityCss()` (when hide traits / product slot diff)

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `display: none` | `form[…] [data-adflipr-hide-desktop="true"]` | min-width 481px | **`!important`** | Usually none — **effective** |
| `display: none` | `form[…] [data-adflipr-hide-mobile="true"]` | max-width 480px | **`!important`** | Usually none — **effective** (incl. extra product slots via `POPUP_ATTR_HIDE_MOBILE` on cards) |

#### B — `buildFormSurfaceResponsiveCss()` → `resolveFormSurfaceMobileRules()`

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `padding` | `form[…] [data-adflipr-popup-surface]` | mobile | **Specificity only** (no `!important`) | **Yes** — `buildEmbeddedFormSurfaceStyle` inlines desktop padding (`serializeLayout.ts` L155–157) — **likely ineffective** |
| `marginTop/Right/Bottom/Left` | same | mobile | Specificity only | **Yes** — inlined on surface — **likely ineffective** |
| `border-radius` | same | mobile | Specificity only | **Yes** — inlined — **likely ineffective** |
| `width` | same | mobile | Specificity only | **Yes** — inlined — **likely ineffective** |

#### C — `buildLayoutSectionResponsiveCss()`

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `padding` | `form[…] [data-adflipr-layout-id="…"]` | mobile | Specificity only | **Yes** — `buildLayoutStyle` / column styles inline desktop — **likely ineffective** |
| `margin` | layout scope | mobile | Specificity only | **Yes** — **likely ineffective** |
| `border-radius` | layout / `[data-column-id]` | mobile | Specificity only | **Yes** — **likely ineffective** |
| `box-shadow` | layout / column | mobile | Specificity only | **Yes** when desktop shadow inlined — **likely ineffective** |

#### D — `buildLayoutResponsiveCss()` (multi-column, `mobileResponsive !== false`)

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `gap` | `form[…] [data-adflipr-layout-id] [data-adflipr-popup-columns]` | mobile | Specificity only | **Yes** — `columnGapDesktop` inlined on columns container (`serializeLayout.ts` L95–98) — **likely ineffective** |
| `flex-direction: column` | columns container | mobile | **`!important`** | **Yes** — inline `flex-direction: row` — **effective** |
| `width: 100%` | columns container `> div` | mobile | **`!important`** | **Yes** — column slot `%` width inlined — **effective** |
| `flex-basis: 100%` | column slots | mobile | **`!important`** | **Yes** — **effective** |

#### E — `buildBlockTraitResponsiveCss()`

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `padding` | `form[…] [data-adflipr-block-id="…"]` | mobile | Specificity only | **Often yes** — wrapper `getExportWrapperStyleFromLayout` uses desktop padding — **likely ineffective** |
| `margin` | block wrapper | mobile | Specificity only | **Often yes** — **likely ineffective** |
| `border-radius` | block wrapper | mobile | Specificity only | **Often yes** — **likely ineffective** |
| `font-size` | block wrapper | mobile | Specificity only | **Sometimes no** on wrapper (typography on inner) — **may be effective** for those blocks |

#### F — `buildCrossFloatingImageResponsiveCss()`

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `top/right/bottom/left` (margin-position) | close btn or `[data-block-type="floating-image"]` | mobile | Specificity only | **Yes** — `buildCrossButtonStyle` / `buildFloatingImageWrapStyle` inline desktop offsets — **likely ineffective** |

#### G — `buildPhoneInputCountrySelectResponsiveCss()`

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `max-width` | `form[…] [data-block-type="phone-input"] select` | mobile | **`!important`** | **Yes** — desktop `maxWidth: 45%` in `buildPhoneSelectStyle` — **effective** |

#### H — `buildProductHorizontalResponsiveCss()`

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `flex-direction`, `align-items`, `text-align` | product card / image / body / name / button scopes | mobile | **`!important`** on all | **Yes** — horizontal card flex inlined — **effective** |
| `width`, `height`, `margin-top`, `flex` | image / body / button scopes | mobile | **`!important`** | **Yes** — **effective** |

#### I — `buildProductGridResponsiveCss()`

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `grid-template-columns: repeat(1, …)` | `[data-adflipr-product-shell]` per block id | mobile | **`!important`** | **Yes** — desktop `gridTemplateColumns: repeat(N, …)` inlined on shell (`product/serialize.ts` L316) — **effective** |

#### J — `buildGamificationResponsiveCss()`

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `flex-direction: column` | `[data-adflipr-popup-surface="gamification"]` | mobile | **`!important`** | **Yes** — inline `flex-direction: row` on gamification surface — **effective** |
| `width`, `padding` | spinner / form columns | mobile | **`!important`** | **Yes** — inline `width:50%` and padding on columns — **effective** |

#### K — Teaser (separate path: `buildTeaserExportDeviceCss`, not in `buildResponsiveDeviceCss`)

| Property | Selector | Media | Override mechanism | Desktop inline on same node? |
|----------|----------|-------|-------------------|------------------------------|
| `display: none` | `[data-adflipr-popup-teaser]` + hide attrs | desktop/mobile media | **`!important`** | **effective** |
| `padding` | `[data-adflipr-popup-teaser="true"]` | mobile | Specificity only | **Yes** — `teaserSurfaceStyle` inlines desktop padding (`serializeTeaser.ts` L86) — **likely ineffective** |
| `border-radius` | teaser scope | mobile | Specificity only | **Yes** — inlined — **likely ineffective** |

### 12.4 Override mechanism counts

Across all responsive rules in `responsiveDeviceCss.ts`:

| Override style | Approx. share of responsive declarations | Reliable vs desktop inline? |
|----------------|----------------------------------------|-----------------------------|
| **`!important` in `@media`** | **~35–45%** (hide, layout stack, product, gamification, phone select) | **Yes** |
| **Specificity only** (no `!important`) | **~55–65%** (surface, layout/block padding-margin-radius-shadow, gap, cross margins, teaser padding) | **No** when same property is desktop-inlined on same element |

**CSS variables:** not used for responsive trait projection.  
**Dual HTML / runtime remount on resize:** not used (`resolveStepHtml` has no device branch).

### 12.5 Architectural implication (PCL-008)

The pipeline assumes **single HTML + `@media` overrides**, but **~55–65% of responsive declarations rely on stylesheet specificity without `!important`**, while desktop trait values for those same properties are **inlined on the same nodes**. Per standard cascade, those mobile rules **do not override** desktop inline values.

Tests in `responsiveDeviceCss.test.ts` assert **CSS string presence** (e.g. `padding:8px 8px 8px 8px`), not computed style after cascade — so tests pass even when browser override would fail.

**Categories that work as designed:** layout column stack, product horizontal/grid, gamification stack, hide-on-device, phone `max-width` — all use **`!important`** or target properties not set inline.

**Categories at risk:** form surface mobile padding/margin/radius/width, layout/column/block trait padding/margin/radius/shadow/gap, cross/floating-image mobile margins, teaser mobile padding/radius — **specificity only**.

**Fix options (if visual bugs confirmed):** add `!important` to trait `@media` rules; or stop inlining properties that differ on mobile (use CSS vars on element + single rule); or emit mobile values only in sheet without desktop inline on same property. Out of scope here — document only.

### 12.6 Evidence

| File | Role |
|------|------|
| `responsiveDeviceCss.ts` | All `@media` builders |
| `serializeLayout.ts` | Desktop inline on surface, layouts, gamification columns |
| `serializeElements.ts` + `layoutStyles.ts` | Desktop inline on block wrappers |
| `product/serialize.ts` | Desktop inline grid + card layout |
| `serializeTeaser.ts` | Desktop inline teaser chip |
| `utils/__tests__/responsiveDeviceCss.test.ts` | Emitted CSS string contracts |
| `utils/__tests__/popupHtmlSerializer.test.ts` | End-to-end export contains mobile padding in device block |

---

## 13. Key file index

| File | Role |
|------|------|
| `utils/popupHtmlSerializer/serializeLayout.ts` | Step HTML + `<style>` injection |
| `utils/popupHtmlSerializer/responsiveDeviceCss.ts` | `@media` device CSS |
| `utils/popupHtmlSerializer/styles/sheets/*.export.css` | Static export sheets |
| `utils/popupHtmlSerializer/popupHtmlSanitize.ts` | customCss / rich text sanitization |
| `utils/popupBuilderState.ts` | `buildPopupSaveMetadata` |
| `embed/mountExportedStepStyles.ts` | Style hoisting + teardown |
| `embed/runtime.ts` | `renderStepInto`, dismiss, `disposeStepBlockInits` |
| `embed/utils.ts` | `resolveStepHtml`, `normalizeEmbedConfig` |
| `embed/host/EmbedHost.ts` | Single overlay runtime; inline multi-runtime |
| `embed/teaserExportCss.ts` | `ensureTeaserExportStyles` (runtime head inject) |
| `utils/formPreviewEmbedPresentation.ts` | Preview → embed runtime (same hoist as storefront) |
| `utils/__tests__/popupCssInlineMix.test.ts` | Inline vs internal mix metrics (§11) |
| `wordpress/includes/class-adflipr-popup-embed.php` | Storefront loader only |
| `service/.../PopupEmbedResponse.java` | Serves opaque `htmlByStep` |
