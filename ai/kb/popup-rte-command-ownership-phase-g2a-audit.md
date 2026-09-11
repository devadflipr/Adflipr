# Phase G2A — RTE Command Ownership Audit and Configurable Command Contract

**Date:** 2026-07-06  
**Phase:** Audit + architecture decision only — **no** production RTE changes, **no** serializer/renderCss migration, **no** `properties.align` deletion.  
**Related:** [`popup-global-trait-presentation-architecture-audit.md`](./popup-global-trait-presentation-architecture-audit.md), [`popup-production-css-rte-ownership-audit.md`](./popup-production-css-rte-ownership-audit.md), [`popup-text-presentation-css-phase5d-report.md`](./popup-text-presentation-css-phase5d-report.md)

---

## A. Executive summary (audit verdict)

**Verdict:** The Popup Text RTE is a **dual-mutation engine** — most toolbar actions write **inline HTML** via `document.execCommand` / span wrappers while **some** also update **traits**, **orphan properties**, or **ephemeral DOM preview state**. Only **font size** implements explicit **full-block → trait normalization**. **Alignment** is the highest-severity defect: RTE writes **`properties.align` (orphan)** + **inline `text-align` in content**, while **renderCss** and canvas read **`style.textAlignment` only**.

**Command count (Text block, production path):** **17** distinct toolbar/keyboard commands (+ **1** clipboard behavior). **8** checklist commands are **not implemented** as RTE commands. **Button** block exposes **3** additional RTE commands under `blockSelectionFormatting`.

**Architecture readiness for G2B:** **NO** — decisions are defined here; implementation awaits review of the ownership matrix.

**Preferred forward-only contract:** Registry-driven ownership with **Option A** full-block normalization for `SELECTION_CAPABLE` presentation commands, **Option B** caret-only typing-format state (browser-native) with **serialization guard** (do not persist caret-only chrome as block traits until content exists), **BLOCK_ONLY** alignment → `style.textAlignment` only.

---

## B. Scope and code map

### Primary production RTE stack (Text block)

| Layer | File | Role |
|-------|------|------|
| Toolbar UI | `BalloonEditor.tsx` | `preventMouseDown`, color pickers, alignment/list/format triggers |
| Command engine | `InlineEditor.tsx` | Selection save/restore, `execCommand`, span wrappers, trait callbacks |
| Feature flags | `rte/RTEConfig.ts` | Per `elementType` toolbar visibility + `blockSelectionFormatting` |
| Canvas bridge | `blocks/text/TextCanvas.tsx` | Trait/content property writes |
| Trait UI | `property-panels/element-properties/TextElementProperties.tsx` | `fontFamily`, `lineHeight`, `letterSpacing`, layout only |
| Canvas CSS adapter | `canvas/ElementStyles.ts` | `resolveTextPaintedCssRules`, `getTextBlockContentStyle` |
| Serializer | `blocks/text/serialize.ts` | Structure-only `<div>` + `sanitizeRichTextHtml(content)` |
| renderCss | `utils/popupHtmlSerializer/textPresentation.ts` | Trait → artifact-scoped CSS |
| Sanitizer | `utils/popupHtmlSerializer/popupHtmlSanitize.ts` | Preserves allowed inline styles/tags |

### Secondary / legacy / non-Text paths

| Symbol | Status |
|--------|--------|
| `WysiwygEditor.tsx` | **Dead code** — zero importers in repo |
| `elementType: 'heading'` in `RTE_CONFIGS` | **Config only** — no heading block uses InlineEditor |
| `customButton: true` (text/default config) | **Dead config** — no toolbar render, no `onCustomButtonClick` handler wired |
| `CanvasFieldLabel` / `CanvasOptionEditor` | `rteEnabled={false}` — plain contentEditable only |
| `ButtonCanvas` | `blockSelectionFormatting: true` — bold/italic/fontSize trait-only |

### Existing audit tests (not modified in G2A)

| Test file | Proves |
|-----------|--------|
| `popupProductionCssRteOwnership.audit.test.ts` | `properties.align` orphan; trait renderCss vs content |
| `popupProductionCssRteOwnership.audit.browser.test.ts` | Partial color cascade; inline `text-align` defeats trait |

---

## C. Part A — Complete RTE command inventory

### Text block (`elementType="text"`, `RTE_CONFIGS.text`)

| # | Command | Exists? | Toolbar | Keyboard | Registration |
|---|---------|---------|---------|----------|--------------|
| 1 | **bold** | ✅ | `BalloonEditor` → `triggerFormat` | Ctrl/Cmd+B | `applyFormatting` → `runExecCommand('bold')` |
| 2 | **italic** | ✅ | same | Ctrl/Cmd+I | `runExecCommand('italic')` |
| 3 | **underline** | ✅ | same | Ctrl/Cmd+U | `runExecCommand('underline')` |
| 4 | **strikethrough** | ✅ | same | — | `runExecCommand('strikeThrough')` |
| 5 | **superscript** | ✅ | same | — | `applyScriptFormatting` (custom DOM + collapsed `execCommand`) |
| 6 | **subscript** | ✅ | same | — | `applyScriptFormatting` |
| 7 | **text alignment** (4 variants) | ✅ | align buttons | — | `applyAlignment` → `justifyLeft/Center/Right/Full` |
| 8 | **font size** | ✅ | dropdown | — | `applyFontSize` / `applyFontSizePreview` |
| 9 | **text color** | ✅ | color picker | — | `applyTextColor` / `applyTextColorPreview` |
| 10 | **highlight / background color** | ✅ | highlighter picker | — | `applyHighlightColor` (`hiliteColor`) / preview span |
| 11 | **unordered list** | ✅ | list button | — | `applyListFormatting('bullet')` |
| 12 | **ordered list** | ✅ | list button | — | `applyListFormatting('ordered')` |
| 13 | **link create / edit** | ✅ | link dialog | — | `applyLink` |
| 14 | **link remove** | ✅ | unlink in dialog | — | `removeLink` |
| 15 | **paste (plain text)** | ✅ | — | — | `handlePaste` → `insertText` |
| 16 | **font family** | ❌ | — | — | Trait panel only (`TextElementProperties`) |
| 17 | **line height** | ❌ | — | — | Trait panel only |
| 18 | **letter spacing** | ❌ | — | — | Trait panel only |
| 19 | **paragraph** | ❌ | — | — | No command; `<p>` from browser/list behavior |
| 20 | **heading levels** | ❌ | — | — | Tags allowed in sanitizer if pasted; paste strips HTML |
| 21 | **clear formatting** | ❌ | — | — | Not implemented |
| 22 | **undo** | ❌ | — | — | FormBuilder history only (not RTE-scoped) |
| 23 | **redo** | ❌ | — | — | FormBuilder history only |
| 24 | **customButton** | ❌ (config only) | — | — | `RTE_CONFIGS` flag with no UI |

**Distinct Text RTE commands: 17** (count alignment as one command with four values; exclude preview-only sub-paths).

### Button block (`blockSelectionFormatting: true`)

| Command | Exists? | Behavior |
|---------|---------|----------|
| bold | ✅ | Trait `style.fontWeight` only; blocks partial `execCommand` |
| italic | ✅ | Trait `style.fontStyle` only |
| font size | ✅ | Trait `fontSize` / responsive; clears inline on apply |
| underline | ❌ (disabled in config) | — |

---

## D. Part B — End-to-end trace (Text block)

### Shared pipeline

```text
BalloonEditor (mousedown preventDefault on format buttons)
    ↓
InlineEditor handler
    ↓
saveSelection / restoreSelection (savedRangeRef)
    ↓
getSelectionRangeWithinEditor() — live Selection OR savedRangeRef
    ↓
DOM: execCommand | applyInlineStyleToSelection | custom script/link DOM
    ↓
onContentChange(innerHTML)  AND/OR  trait callbacks (TextCanvas)
    ↓
properties.content  AND/OR  properties.style  AND/OR  orphan properties.*
    ↓
serializeText → sanitizeRichTextHtml → exported HTML
    ↓
normalizeTextPresentation → renderCss on painted `> div` + link descendants
    ↓
embed runtime mount → computed cascade (inline descendants override renderCss)
```

### Per-command current storage result

| Command | DOM mutation | TextCanvas write | Serializer | renderCss | Storefront winner |
|---------|--------------|------------------|------------|-----------|-------------------|
| bold | `<b>`/`<strong>`/inline | `properties.bold` (**orphan**; reads `isBold`) | content tags | `fontWeight` if trait set | **content tags** (trait flag broken) |
| italic | `<i>`/`<em>` | `properties.italic` (orphan) | content | `fontStyle` if trait | **content** |
| underline | `<u>`/decoration | `properties.underline` (orphan) | content | — (no underline trait on text) | **content** |
| strikethrough | `<s>`/`<strike>` | none | content | — | **content** |
| superscript | `<sup>` | none | content | — | **content** |
| subscript | `<sub>` | none | content | — | **content** |
| alignment | `text-align` on block elements via `execCommand` | `properties.align` (**orphan**) | inline in content | `style.textAlignment` only | **inline descendant** if present; else trait |
| font size | span `font-size` OR cleared | `style.fontSize` (+ responsive keys) | partial inline or none | `font-size` on `> div` | **inline span** > trait |
| text color | span `color` OR preview on root | **always** `style.textColor` | partial inline | `color` on `> div` | **inline span** > trait |
| highlight | `hiliteColor` / span bg | `style.highlightColor` (**orphan** — not in renderCss) | inline bg | block `backgroundColor` only | **content bg** (trait unused in CSS) |
| bullet/ordered list | `<ul>`/`<ol>`/`<li>` | `isBulletList` / `isOrderedList` (**orphan flags**) | semantic HTML | — | **content structure** |
| link | `<a href>` + inline color/decoration on anchor | content only | content + sanitizer | `linkColor`/`linkDecoration` on `a` descendants | **inline on anchor** vs renderCss link rules (both apply) |
| paste | plain text nodes | content | content | — | content |

---

## E. Selection-state model (Part A trace requirement)

### Detection machinery (`InlineEditor.tsx`)

| State | Detection | Reliable? | Notes |
|-------|-----------|-----------|-------|
| **NONE** | No focus; `getSelectionRangeWithinEditor()` null; no `savedRangeRef` | Partial | Blur after edit may still have `savedRangeRef` until cleared |
| **CARET_ONLY** | `range.collapsed === true` | Yes | Used implicitly; no named constant |
| **PARTIAL_SELECTION** | `hasNonCollapsedTextSelection()` && !`isWholeEditorSelected(range)` | Mostly | Fails when HTML structure makes text comparison misleading |
| **FULL_BLOCK_SELECTION** | `isWholeEditorSelected(range)` — `range.toString().trim() === editor.textContent.trim()` | **Fragile** | Text-only; ignores HTML equivalence; Cmd+A usually works; manual select-all may differ with ZWSP/whitespace |
| **MULTI_PARAGRAPH_SELECTION** | **Not distinguished** | No | Treated as partial or full via text-length check only |

### Event behaviors (proven in code)

| Scenario | Behavior |
|----------|----------|
| Focused, no selection (caret) | `hasNonCollapsedTextSelection()` false → font size/color go trait path; bold uses `execCommand` (browser typing state) |
| Cmd+A / Ctrl+A | Non-collapsed range; if trimmed text matches editor text → `isWholeEditorSelected` true (**font size only** uses this) |
| Toolbar mousedown | `preventMouseDown` + `saveSelection` on format buttons; color picker uses `handleRteColorPickerSessionChange` + `preserveSelectionForPicker` |
| Selection after toolbar click | `restoreSelection()` before `execCommand`; `refreshCommandState` temporarily restores saved range for `queryCommandState` |
| Color picker open | Selection shown via `showSelectionHighlightWithoutFocus`; `skipRestoreSelection` on preview apply |
| Collapsed after toolbar | `savedRangeRef` updated after apply via `saveSelection()` |

### Gaps

- No explicit **MULTI_PARAGRAPH** policy.
- **FULL_BLOCK** not used for color, bold, alignment, highlight — only **font size**.
- **NONE** vs **CARET_ONLY** not branched separately (same code path for trait-only font size/color).

---

## F. Part C — Command ownership matrix (Text block)

| Command | Current implementation | NONE | CARET_ONLY | PARTIAL_SELECTION | FULL_BLOCK_SELECTION | MULTI_PARAGRAPH_SELECTION | Current storage owner | Recommended class | Final owner | Trait field / content representation | Migration action |
|---------|------------------------|------|------------|-------------------|----------------------|---------------------------|----------------------|-------------------|-------------|--------------------------------------|------------------|
| **alignment** | `execCommand justify*` + `properties.align`; canvas reads `style.textAlignment` | trait mismatch | inline `text-align` + orphan align | inline on selected blocks | inline + orphan align | inline on paragraphs | **mixed** orphan + content + trait (visual) | **BLOCK_ONLY** | `style.textAlignment` → renderCss | Stop `execCommand`; delete `properties.align`; strip inline `text-align` on normalize |
| **line height** | Trait panel only | trait | trait | N/A (no RTE) | trait | trait | `style.lineHeight` | **BLOCK_ONLY** | same | Add RTE guard: never inline |
| **letter spacing** | Trait panel only | trait | trait | N/A | trait | trait | `style.letterSpacing` | **BLOCK_ONLY** | same | Add RTE guard |
| **font family** | Trait panel only | trait | trait | N/A | trait | trait | `style.fontFamily` | **BLOCK_ONLY** | same | No RTE command planned |
| **font size** | Partial span; full/mobile/caret → trait | trait | trait | content `font-size` span | trait (normalized) | partial span unless full text match | **mixed** | **SELECTION_CAPABLE** | partial: content span; else `style.fontSize` | Extend full-block normalization to registry; mobile = block |
| **text color** | Always updates trait; partial also span | trait (+ root preview) | trait only | content `color` span | trait + possible inline | partial span | **mixed** | **SELECTION_CAPABLE** | partial: content; else `style.textColor` | Full-block → trait + strip inline color; caret → typing state only |
| **highlight** | `hiliteColor` + orphan `style.highlightColor` | content+orphan trait | content | content | content | content | **content** (+ orphan trait) | **SELECTION_CAPABLE** | partial: content `background-color`; block highlight N/A | Remove orphan trait or map to block bg explicitly; no `hiliteColor` for full block |
| **bold** | `execCommand` + orphan `properties.bold` | content tags | browser typing | content tags | content tags | content tags | **content** (+ broken flag) | **SELECTION_CAPABLE** | partial: `<strong>`/inline; block: `style.fontWeight` | Full-block → trait; delete `isBold`/`bold` flags |
| **italic** | same | content | typing | content | content | content | **content** | **SELECTION_CAPABLE** | partial: `<em>`; block: `style.fontStyle` | same |
| **underline** | same | content | typing | content | content | content | **content** | **SELECTION_CAPABLE** | partial: `<u>`/decoration; block: optional trait or content-only | Product decision: underline rarely block-level |
| **strikethrough** | `execCommand` only | content | typing | content | content | content | **content** | **SELECTION_CAPABLE** | partial only | Full-block → strip + optional trait if added |
| **superscript** | custom `<sup>` | content | content | content | content | content | **content** | **CONTENT_STRUCTURE** (semantic) | `<sup>` | Keep content-only |
| **subscript** | custom `<sub>` | content | content | content | content | content | **content** | **CONTENT_STRUCTURE** | `<sub>` | Keep content-only |
| **bullet list** | `insertUnorderedList` | content | content | content | content | content | **content** + orphan flag | **CONTENT_STRUCTURE** | `<ul>/<li>` | Delete orphan `isBulletList` |
| **ordered list** | `insertOrderedList` | content | content | content | content | content | **content** + orphan flag | **CONTENT_STRUCTURE** | `<ol>/<li>` | Delete orphan `isOrderedList` |
| **link create/edit** | DOM `<a>` + inline styles | content | content | content | content | content | **content** | **BEHAVIOR** | `<a href>`; presentation from `linkColor`/`linkDecoration` renderCss | Stop inline color on anchor; use renderCss link descendant |
| **link remove** | unwrap `<a>` | content | content | content | content | content | **content** | **BEHAVIOR** | plain text | Keep |
| **paste** | plain `insertText` | content | content | content | content | content | **content** | **BEHAVIOR** | text nodes | Keep |

---

## G. Part D — Command class validation

### 1. BLOCK_ONLY ✅ (confirmed for product model)

**Members:** alignment, line height, letter spacing, font family (trait-only today).

**Contract:** Any selection state → update `properties.style` → `resolveTextPaintedCssRules` → renderCss. **Never** emit descendant inline presentation because toolbar fired.

**Violation today:** Alignment uses `execCommand` regardless of selection (including partial/multi-paragraph).

### 2. SELECTION_CAPABLE — validated per command

| Command | Truly dual-mode? | Caret semantics | Full-block today | Recommendation |
|---------|------------------|-----------------|------------------|----------------|
| text color | Yes | trait updated always (surprising) | inline possible | Full-block → trait only; caret → typing format |
| font size | Yes | trait | **normalized** | Model for other commands |
| bold/italic/underline | Yes | browser typing | stays inline | Full-block → trait weight/style |
| highlight | Yes (partial only meaningful) | hiliteColor inline | inline | Full-block → trait N/A; use partial content only |
| strikethrough | Yes | typing | inline | partial only |

### 3. CONTENT_STRUCTURE ✅

Lists, sup/sub — must remain semantic HTML in `properties.content`. **Never** block traits.

### 4. BEHAVIOR ✅

Links, paste — structural/behavior persistence in content. Undo/redo out of RTE scope (form history).

**Additional class required?** **NO** — four classes suffice.

---

## H. Part E — Full-block selection normalization

### Today

| Path | Storage |
|------|---------|
| Click block → trait color (no RTE) | `style.textColor` |
| RTE → select all → color | `style.textColor` **+** inline spans/`foreColor` residue |
| RTE → select all → font size | trait only (inline cleared) ✅ |
| RTE → select all → alignment | `properties.align` + inline `text-align` ❌ |

### Can full-block be recognized?

**Partially.** `isWholeEditorSelected` works for plain text equality but:

- Fragile with HTML tags, lists, multiple paragraphs, zero-width spaces.
- Only **font size** consumes it.

### Decision: **Option A (preferred)**

**Full-block selection normalizes all SELECTION_CAPABLE presentation commands to block traits** and strips conflicting inline presentation for that property.

**Exception (registry `fullSelectionStrategy`):** Commands that are **CONTENT_STRUCTURE** or **BEHAVIOR** ignore full-block normalization.

---

## I. Part F — Caret-only formatting

### Current behavior

| Command | Caret behavior | Survives serialize? |
|---------|----------------|---------------------|
| bold/italic/underline | `execCommand` → browser typing state | **Yes** — next typed chars wrapped |
| text color | `style.textColor` trait updated; preview may set `editorRef.style.color` | trait yes; root preview **not** in content |
| font size | trait `fontSize` | yes |
| highlight | `hiliteColor` | typed text may get mark/bg |
| alignment | `execCommand` block align | persists in content HTML |

Toolbar does **not** destroy caret if `preventMouseDown` + `restoreSelection` succeed.

### Decision: **Option D (command-specific)** implemented via registry defaults:

| Class | Caret strategy |
|-------|----------------|
| BLOCK_ONLY | Apply block trait (alignment, if ever triggered at caret) |
| SELECTION_CAPABLE presentation | **Option B** — temporary typing-format state; **do not** mirror to block trait until blur/save or full-block normalize |
| SELECTION_CAPABLE with current trait bug (color/size) | **Change** — caret should **not** update block trait immediately (G2B) |

**Rationale:** Option A (caret → whole block trait) is surprising for color/size. Option C (no-op) breaks user expectations for bold. Option B matches browser RTE semantics.

---

## J. Part G — Alignment audit (10 questions)

1. **Does `properties.align` still exist?** **Yes** — written by `TextCanvas.onAlignChange`.
2. **Who writes it?** RTE `applyAlignment` → `onAlignChange` → `updateElement({ align })`.
3. **Who reads it?** `TextCanvas` **does not** read it; canvas uses `style.textAlignment`. Button serialize reads `properties.align` (button block only).
4. **Does serializer use it (text)?** **No** — `serializeText` ignores `align`.
5. **Does renderCss use it?** **No** — only `style.textAlignment` (`popupProductionCssRteOwnership.audit.test.ts`).
6. **Simultaneous sources?** **Yes** — `properties.align`, `style.textAlignment`, inline `text-align` in content.
7. **Visual winner?** Painted `> div` gets trait `text-align`; **descendant inline `text-align` wins** on that subtree (browser test Scenario 3).
8. **Save/reload drift?** **Yes** — RTE alignment does not update `style.textAlignment`; orphan `align` and inline HTML diverge from trait panel / renderCss.
9. **Stale inline descendants?** **Yes** — changing trait alignment does not remove inline `text-align` in content.
10. **Safe to delete `properties.align` (text)?** **Yes** — after routing RTE alignment to `style.textAlignment` and removing `execCommand` inline path. Button `properties.align` is separate (layout, not RTE).

**Target contract confirmed:**

```text
Alignment = BLOCK_ONLY
RTE → style.textAlignment → textPresentation renderCss
Never → properties.align, never inline text-align in content
```

---

## K. Part H — Inline content classification

| Pattern | Classification | Notes |
|---------|----------------|-------|
| `<span style="color:...">` (partial word) | **VALID_PARTIAL_SELECTION_CONTENT** | Legitimate |
| `<span style="font-size:...">` (partial) | **VALID_PARTIAL_SELECTION_CONTENT** | Legitimate |
| `<b>`, `<strong>`, `<i>`, `<em>`, `<u>`, `<s>` (partial) | **VALID_PARTIAL_SELECTION_CONTENT** | Legitimate |
| `<sup>`, `<sub>` | **CONTENT_STRUCTURE** | Semantic |
| `<ul>`, `<ol>`, `<li>` | **CONTENT_STRUCTURE** | Semantic |
| `<a href>` | **CONTENT_STRUCTURE** + **BEHAVIOR** | Valid; inline color on `a` is **BLOCK_PRESENTATION_LEAK** (should be renderCss link rules) |
| `<div style="text-align:center">` wrapping all content | **BLOCK_PRESENTATION_LEAK** | Should be trait |
| `<span style="color">` wrapping 100% text | **BLOCK_PRESENTATION_LEAK** | Indistinguishable from partial without provenance metadata |
| `style="background-color"` from hiliteColor | **VALID_PARTIAL_SELECTION_CONTENT** (if partial) else **LEAK** if block-wide |
| `<font face/size>` | **LEGACY_BROWSER_ARTIFACT** | Allowed tag; rare from execCommand |
| `style="line-height"`, `letter-spacing`, `font-family` in content | **BLOCK_PRESENTATION_LEAK** | Trait-owned |
| `style="text-align"` on inner div/p | **BLOCK_PRESENTATION_LEAK** | Trait-owned |

**Can full-block inline be distinguished from intentional partial?** **NO** with current data model — no provenance bit. Heuristic for G2B cleanup: if inline style span covers all text nodes and no sibling partial spans → treat as leak → normalize to trait on save/migration.

---

## L. Part I — Trait synchronization matrix

| RTE command | Existing trait field | RTE writes trait? | Trait UI writes same? | renderCss consumes? | Duplicate storage risk |
|-------------|---------------------|-------------------|----------------------|---------------------|------------------------|
| text alignment | `style.textAlignment` | **No** (writes `properties.align`) | **No** text panel control | **Yes** | **HIGH** + inline |
| font size | `style.fontSize` (+ responsive) | **Yes** (caret/full/mobile) | **No** text panel control | **Yes** | **MEDIUM** + inline spans |
| font family | `style.fontFamily` | No | **Yes** (TextElementProperties) | **Yes** | LOW |
| font weight (bold) | `style.fontWeight` | **No** (orphan `bold`/`isBold`) | **No** | **Yes** | **HIGH** — content tags |
| font style (italic) | `style.fontStyle` | **No** | **No** | **Yes** | **HIGH** |
| text color | `style.textColor` / `fontColor` | **Yes** (always) | **No** text panel | **Yes** | **HIGH** + inline |
| line height | `style.lineHeight` | No | **Yes** | **Yes** | LOW |
| letter spacing | `style.letterSpacing` | No | **Yes** | **Yes** | LOW |
| link color | `style.linkColor` (form/global) | **No** (inline on `<a>`; default constant) | Global links section | **Yes** (`a` descendant) | **HIGH** |
| link decoration | `style.linkDecoration` | **No** (inline underline on anchor) | Global links section | **Yes** | **MEDIUM** |
| background (block) | `style.backgroundColor` | No | **No** text panel | **Yes** | vs highlight inline |
| highlight | `style.highlightColor` | **Yes** | **No** | **No** | **ORPHAN trait** |

**Duplicates / orphans / gaps**

- **Orphan writes:** `properties.align`, `properties.bold`/`italic`/`underline`, `isBold`/`isItalic`/`isUnderline` mismatch, `isBulletList`/`isOrderedList`, `style.highlightColor`.
- **Trait panel gaps (text block):** no UI for `textColor`, `textAlignment`, `fontSize`, `fontWeight`, `backgroundColor`, `linkColor`.
- **RTE-only traits:** color, size, alignment (broken path), highlight orphan.

---

## M. Part J — Proposed configurable RTE command registry (design only)

### Separation of concerns

| Registry | Owns |
|----------|------|
| **`RTE_COMMAND_REGISTRY`** | `id`, `class`, trait field map, selection strategies, normalization/cleanup callbacks, `execute` delegate |
| **`RTE_TOOLBAR_REGISTRY`** (or derive from `RTE_CONFIGS`) | visibility per `elementType`, icon, label, keyboard shortcut, `commandId` reference |

**Do not merge** toolbar metadata into ownership registry — link by `commandId` only.

### Minimal configuration shape

```ts
type RteSelectionStrategy =
  | 'block_trait'
  | 'content_inline'
  | 'typing_state'      // caret-only browser state, not serialized as block trait
  | 'ignore'            // BLOCK_ONLY under partial selection
  | 'content_semantic';

interface RteCommandDefinition {
  id: string;
  class: 'BLOCK_ONLY' | 'SELECTION_CAPABLE' | 'CONTENT_STRUCTURE' | 'BEHAVIOR';
  traitField?: keyof TextStyleTraits;           // block owner
  contentProperty?: 'color' | 'fontSize' | ...; // partial inline key
  strategies: {
    none: RteSelectionStrategy;
    caret: RteSelectionStrategy;
    partial: RteSelectionStrategy;
    full: RteSelectionStrategy;
    multiParagraph: RteSelectionStrategy;
  };
  cleanupOnNormalize?: (root: HTMLElement, property: string) => void;
  exec: (ctx: RteCommandContext) => void;
}
```

### Example entries

```text
alignment
  class: BLOCK_ONLY
  traitField: textAlignment
  strategies: { all → block_trait }
  exec: update trait only; strip inline text-align

textColor
  class: SELECTION_CAPABLE
  traitField: textColor
  strategies: { partial → content_inline, full → block_trait + cleanup, caret → typing_state, none → block_trait }

fontSize
  class: SELECTION_CAPABLE
  traitField: fontSize
  strategies: { partial → content_inline, full → block_trait, caret → typing_state, mobile → block_trait }

link
  class: BEHAVIOR
  strategies: { all → content_semantic }
  traitField: (presentation via linkColor/linkDecoration renderCss only)
```

**Execution:** Single `dispatchRteCommand(id, ctx)` reads registry — **no** per-command switch in `InlineEditor`.

---

## N. Part K — Full-selection inline cleanup policy

### Decision: **Option D (command-specific)** with default **A** for SELECTION_CAPABLE presentation

| Command | Full-block + new trait | Inline cleanup |
|---------|------------------------|----------------|
| text color | set `style.textColor` | **Strip all inline `color`** (Option A) |
| font size | set `style.fontSize` | **Strip all inline `font-size`** (already today) |
| bold/italic | set `style.fontWeight` / `fontStyle` | Strip `<b>/<strong>`/`<i>/<em>` and inline weight/style |
| highlight | no block trait | Strip inline `background-color` on full-block apply |
| alignment | set `style.textAlignment` | **Strip all inline `text-align`** |

**Preserve partial (Option B) only when** selection is **verified partial** — not on full-block.

**Distinguish leak vs intentional partial:** **Not reliable** without metadata; use span-coverage heuristic on normalize/export boundary.

---

## O. Part L — Text renderCss cascade contract (Phase 5D)

| Property | Block trait owner | Partial content owner | Contract |
|----------|-------------------|----------------------|----------|
| font-size | `> div` renderCss | span `font-size` | **BOTH** — inline wins locally |
| font-family | `> div` | rare inline | **BOTH** — inline wins |
| font-weight | `> div` | `<b>`/inline | **BOTH** — inline wins |
| color | `> div` | span `color` | **BOTH** — inline wins (browser test) |
| text-align | `> div` | descendant `text-align` | **BOTH** — **INVALID DUPLICATION** for block intent; inline should not exist |
| line-height | `> div` | descendant | **BLOCK_ONLY** target — inline = leak |
| letter-spacing | `> div` | descendant | **BLOCK_ONLY** target |
| background | `> div` (block bg) | span highlight | **BOTH** — contextual |
| link color / decoration | `a` descendant renderCss | inline on `a` | **INVALID DUPLICATION** — renderCss should own |

**Cascade rule (valid dual ownership):**

```text
block renderCss on painted > div provides default
partial inline content / semantic tags override locally (more specific / descendant)
```

**Invalid duplication** must be removed at RTE write time, not tolerated in steady state.

---

## P. Part M — Browser / test evidence

| # | Scenario | Evidence status |
|---|----------|-----------------|
| 1 | No selection + color | **Unit/code trace only** — trait path; no InlineEditor Playwright harness |
| 2 | Caret-only + bold | **Code trace** — `execCommand` collapsed; no automated browser RTE test |
| 3 | Partial selection + color | **Proven** — `popupProductionCssRteOwnership.audit.browser.test.ts` Scenario 2 |
| 4 | Full-block + color | **Code trace** — trait always updated; inline may remain; **no dedicated test** |
| 5 | Partial selection + alignment | **Code trace** — `execCommand` writes inline; **no dedicated test** |
| 6 | Full-block + alignment | **Code trace** — inline + orphan align; **no dedicated test** |
| 7 | Multi-paragraph + alignment | **Not reproduced** — infrastructure gap |
| 8 | Partial link creation | **Code trace** — `applyLink`; **no browser test** |
| 9 | Toolbar selection preservation | **Code trace** — `preventMouseDown` + `savedRangeRef`; **no browser test** |
| 10 | Save → serialize → reload partial format | **Proven** — audit unit test preserves inline color |

**Confidence:** Cascade on **static serialized HTML** is proven; **live RTE interaction** scenarios require G2B harness (Playwright + mounted InlineEditor or integration fixture).

---

## Q. Part N — Obsolete code deletion inventory (G2B+)

| Symbol | File | Consumers | Replacement | Safe deletion point |
|--------|------|-----------|-------------|---------------------|
| `properties.align` (text RTE write) | `TextCanvas.tsx` | None for text render | `style.textAlignment` | After registry routes alignment |
| `onAlignChange` orphan path | `TextCanvas.tsx` | InlineEditor | `onStyleChange('textAlignment')` | G2B |
| `applyAlignment` `execCommand` | `InlineEditor.tsx` | BalloonEditor | trait-only dispatcher | G2B |
| `isBold`/`isItalic`/`isUnderline` props | `TextCanvas.tsx` | InlineEditor initial state | `style.fontWeight`/`fontStyle` | G2B |
| `onFormatChange` → `properties.bold` | `TextCanvas.tsx` | — | registry trait or content only | G2B |
| `isBulletList`/`isOrderedList` flags | `TextCanvas.tsx` | Toolbar active state | `queryCommandState` only or drop | G2B |
| `style.highlightColor` orphan | `TextCanvas.tsx` | Balloon display | content-only highlight | G2B |
| `applyHighlightColor` `hiliteColor` | `InlineEditor.tsx` | Balloon | span wrapper or semantic `<mark>` | G2B |
| `applyLinkStyles` inline on anchor | `InlineEditor.tsx` | link apply | renderCss link descendant | G2B |
| `WysiwygEditor` | `WysiwygEditor.tsx` | **none** | delete file | G2B cleanup |
| `customButton` config flag | `RTEConfig.ts` | none | delete flag | G2B |
| `editorRef.style.textAlign = align` | `InlineEditor.tsx` | canvas sync | renderCss-driven canvas | G2B |
| `isWholeEditorSelected` text-only | `InlineEditor.tsx` | font size | registry + DOM walk helper | G2B refactor |
| Duplicate color/size preview paths | `InlineEditor.tsx` | Balloon | registry strategies | G2B |

---

## R. Required architecture decisions (28 answers)

1. **How many RTE commands exist?** **17** Text block (+ **3** Button-only) = **20** total in PopupBuilder RTE.
2. **List every command:** bold, italic, underline, strikethrough, superscript, subscript, alignment (4), font size, text color, highlight, bullet list, ordered list, link create/edit, link remove, paste plain text. **Not present:** font family, line height, letter spacing, paragraph, headings, clear format, undo, redo, customButton UI.
3. **BLOCK_ONLY:** alignment, line height, letter spacing, font family.
4. **SELECTION_CAPABLE:** text color, font size, bold, italic, underline, strikethrough, highlight.
5. **CONTENT_STRUCTURE:** bullet list, ordered list, superscript, subscript.
6. **BEHAVIOR:** link create/edit/remove, paste.
7. **Additional classes?** **NO.**
8. **Selection detection:** `getSelectionRangeWithinEditor`, `range.collapsed`, `hasNonCollapsedTextSelection`, `isWholeEditorSelected` (text compare); no MULTI_PARAGRAPH type.
9. **Full-block reliably recognized?** **Partially** — text equality only; used only for font size today.
10. **Full-block presentation commands:** **Option A** — normalize to block traits + strip conflicting inline.
11. **Caret-only presentation:** **Option D** — typing state for semantic formats; **do not** update block color/size traits on caret (G2B fix).
12. **Alignment always block-owned?** **Yes** (target); **No** today.
13. **Alignment writes inline HTML today?** **Yes** — `execCommand justify*`.
14. **`properties.align` exists?** **Yes** (text RTE write).
15. **Safe to delete (text)?** **Yes** after G2B alignment routing.
16. **Commands with equivalent traits:** font size, text color, alignment (trait exists but unused by RTE), font family/line-height/letter-spacing (trait only, no RTE).
17. **Duplicate state:** color (trait+inline), font size (trait+inline), alignment (align+textAlignment+inline), bold (tags+orphan flags), highlight (hilite+orphan trait), links (inline+renderCss).
18. **Legitimate partial inline:** span color/size, semantic tags, partial highlight, lists, sup/sub.
19. **Block presentation leaks:** full-block inline color/size, inner `text-align`, anchor inline color, orphan flags.
20. **Distinguish leak vs partial?** **No** — heuristic only.
21. **Full-selection cleanup:** Default **strip conflicting inline** (Option A); preserve partial only on verified partial selection.
22. **Registry contains:** command id, class, trait mapping, selection strategies, cleanup/normalize callbacks, execute delegate.
23. **Toolbar vs ownership registry:** **Separate**, linked by `commandId`.
24. **renderCss overridable by partial inline:** font-size, font-family, font-weight, color, background (highlight), text-decoration.
25. **Cascade contract:** renderCss default on `> div`; legitimate partial inline/descendant overrides locally; **no** inline `text-align` or link color in steady state.
26. **Obsolete after implementation:** see Part N table + `WysiwygEditor.tsx`.
27. **Tests before G2B:** extend browser audit for alignment/full-block/caret; InlineEditor integration fixture; keep Phase 0 green.
28. **Ready for G2B?** **NO** — audit complete; awaits stakeholder review of matrix.

---

## S. G2B implementation boundary

**In scope G2B**

- Implement `RTE_COMMAND_REGISTRY` + `dispatchRteCommand`.
- Rewire `InlineEditor` / `BalloonEditor` to registry (no ownership switches).
- Fix Text alignment → `style.textAlignment`; delete text `properties.align` writes.
- Fix `onFormatChange` orphan keys; align bold/italic with traits or content-only policy.
- Link apply: remove inline anchor color; use `style.linkColor` from element/form.
- Full-block normalization + inline cleanup per registry.
- Caret-only policy for color/size traits.
- Delete `WysiwygEditor.tsx`, dead `customButton` config.
- Audit/integration tests for RTE scenarios in Part M.

**Out of scope G2B**

- G1 production `.export.css` webpack gate.
- Remaining block-family CSS migrations (divider, phone-input, etc.).
- Content migration script for existing saved popups (optional follow-up G2C).
- Email Builder RTE (different codebase).

---

## T. Cross-references

- Prior RTE/CSS overlap audit: [`popup-production-css-rte-ownership-audit.md`](./popup-production-css-rte-ownership-audit.md) Workstreams B–F.
- Global trait gate: [`popup-global-trait-presentation-architecture-audit.md`](./popup-global-trait-presentation-architecture-audit.md) §G2.
- Text renderCss ownership: [`popup-text-presentation-css-phase5d-report.md`](./popup-text-presentation-css-phase5d-report.md).

---

## U. Final response checklist

| # | Item | Answer |
|---|------|--------|
| 1 | Audit verdict | Dual-mutation RTE; alignment orphan; registry required |
| 2 | Command count | 17 (text) |
| 3 | Command list | §C |
| 4 | Selection model | §E |
| 5 | Ownership matrix | §F |
| 6 | BLOCK_ONLY | alignment, line height, letter spacing, font family |
| 7 | SELECTION_CAPABLE | color, size, bold, italic, underline, strike, highlight |
| 8 | CONTENT_STRUCTURE | lists, sup, sub |
| 9 | BEHAVIOR | link, paste |
| 10 | Storage conflicts | §B table |
| 11 | Full-block decision | Option A |
| 12 | Caret decision | Option D (typing state; fix color/size trait bug) |
| 13 | Alignment decision | BLOCK_ONLY → `style.textAlignment` |
| 14 | `properties.align` | Delete for text after G2B |
| 15 | Inline classification | §K |
| 16 | Trait sync matrix | §L |
| 17 | Registry design | §M |
| 18 | Cleanup policy | §N |
| 19 | renderCss cascade | §O |
| 20 | Deletion inventory | §Q |
| 21 | Required tests | §P |
| 22 | G2B boundary | §S |
| 23 | Ready to implement | **NO** (review gate) |
