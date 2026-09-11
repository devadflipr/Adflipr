# Phase G2A.1 — RTE command semantics decision correction and final ownership contract

**Date:** 2026-07-06  
**Phase:** Decision-correction and final contract only — **no** G2B implementation, **no** serializer changes, **no** CSS block-family migration, **no** G1 changes.  
**Related:** [`popup-rte-command-ownership-phase-g2a-audit.md`](./popup-rte-command-ownership-phase-g2a-audit.md), [`popup-production-css-rte-ownership-audit.md`](./popup-production-css-rte-ownership-audit.md), [`popup-global-trait-presentation-architecture-audit.md`](./popup-global-trait-presentation-architecture-audit.md), [`popup-text-presentation-css-phase5d-report.md`](./popup-text-presentation-css-phase5d-report.md)

---

## 1. Executive verdict

**Primary ownership model: C — Explicit hybrid**, anchored in **command semantics (Model B)**, with **two narrowly scoped exceptions** — not a global “full selection → trait” rule.

| Decision | Verdict |
|----------|---------|
| Global selection-coverage ownership (Model A) | **Rejected** |
| Command-semantic ownership (Model B) | **Accepted as default** |
| Full-block selection → block trait for bold/italic/color/underline/highlight | **Rejected** |
| Full-block selection → block trait for font size | **Rejected** (correct G2A overreach) |
| Caret-only / mobile font size → block trait | **Accepted exception** (control-context, not 99% vs 100%) |
| Alignment → block trait only | **Accepted** (proven defect fix) |
| RTE semantic commands → content always | **Accepted** |
| G2B ready after this document | **YES** (contract defined; implementation not started) |

**Why this matters:** G2A proposed Option A — any `SELECTION_CAPABLE` presentation command at full-block selection normalizes to block traits. Code inspection and product semantics show that **RTE toolbar commands are character/range formatting commands**. Selecting 100% of text does not change what the command *means*. Merchants would not expect Bold at 100% selection to flip persistence to `style.fontWeight` while Bold at 99% stays in `<strong>` tags — especially because **newly typed text** would inherit block trait styling from Phase 5D `renderCss` on `> div`, producing different futures for identical intent.

**Corrected contract:** Separate **command identity** from **selection geometry**. Only commands that are *architecturally block presentation* (alignment, panel typography) or *explicit block-default controls* (font size with caret / mobile device) may write traits. All RTE inline formatting commands write `properties.content` regardless of selection coverage.

---

## 2. Previous G2A decisions retained

| G2A finding | Status |
|-------------|--------|
| Text RTE is a dual-mutation engine | **Retained** |
| Alignment triple-path defect (`properties.align`, `style.textAlignment`, inline `text-align`) | **Retained** |
| `properties.align` not consumed by serializer/renderCss | **Retained** (proven in `TextCanvas.tsx` + audit tests) |
| Inline descendant `text-align` defeats block trait | **Retained** (browser cascade tests) |
| Partial rich-text color legitimately overrides block default | **Retained** |
| `isWholeEditorSelected` is fragile (text equality only) | **Retained** |
| Multi-paragraph selection not explicitly modeled | **Retained** |
| Registry-driven dispatch preferred over scattered switches | **Retained** |
| Link presentation should come from renderCss descendants, not inline anchor styles | **Retained** |
| `WysiwygEditor.tsx` dead | **Retained** (zero importers) |
| Paste is plain-text only | **Retained** |
| Phase 5D cascade: renderCss on `> div` provides default; legitimate partial inline overrides locally | **Retained** |
| No runtime compatibility branches for historical inline leaks | **Retained** |

---

## 3. Previous G2A decisions rejected or corrected

| G2A recommendation | G2A.1 correction | Reason |
|--------------------|------------------|--------|
| **Option A:** full-block selection normalizes **all** `SELECTION_CAPABLE` commands to traits | **Rejected** | Violates command semantics; creates 99% vs 100% cliff; fragile with `isWholeEditorSelected` |
| Bold/italic/underline full-block → `style.fontWeight` / `fontStyle` | **Rejected** | RTE bold is semantic inline formatting; Button block already uses trait-only via `blockSelectionFormatting` — Text block must stay content-only for these |
| Text color full-block → trait + strip inline color | **Rejected** | Full selection red means “these characters are red,” not “block default is red”; trait update on partial already duplicates (bug) |
| Highlight full-block → strip inline | **Rejected as trait path** | Highlight has no block trait; always content (`<mark>` / span background) |
| Caret color/size → update block trait (G2A “Option D” partial) | **Partially corrected** | Caret color → **typing/content-forward**, not trait; caret font size → **trait OK** (block default control — see exception) |
| Class name `SELECTION_CAPABLE` as primary taxonomy | **Replaced** | Implies selection determines ownership; use command classes + optional `selectionPolicy` |
| `style.highlightColor` as trait | **Rejected** | Orphan — not in `resolveTextPaintedCssRules` / renderCss |
| `onFormatChange` → `properties.bold` etc. | **Rejected** | Writes wrong keys (`bold` vs `isBold`); content tags are source of truth |

---

## 4. Actual command semantics matrix

**Legend — Natural owner:** CONTENT = `properties.content`; TRAIT = `properties.style` → renderCss; HYBRID = command-dependent (see Proposed).

| Command | Natural semantic owner | Partial valid? | Full selection valid as content? | Block trait exists? | Current owner | Proposed final owner | Selection-dependent ownership justified? | Reason |
|---------|------------------------|----------------|-------------------------------|---------------------|---------------|----------------------|------------------------------------------|--------|
| **bold** | CONTENT | Yes | **Yes** | `fontWeight` (renderCss) but **not via RTE** | content tags + orphan `properties.bold`/`isBold` mismatch | **CONTENT** (`<strong>`/execCommand) | **No** | Character style; future text follows caret typing state, not block weight |
| **italic** | CONTENT | Yes | Yes | `fontStyle` (renderCss), not via RTE | content + orphan flags | **CONTENT** | **No** | Same as bold |
| **underline** | CONTENT | Yes | Yes | No text underline trait | content + orphan flag | **CONTENT** | **No** | Decoration on range |
| **strikethrough** | CONTENT | Yes | Yes | No | content only | **CONTENT** | **No** | Range decoration |
| **superscript** | CONTENT | Yes | Yes | No | `<sup>` custom DOM | **CONTENT** | **No** | Semantic markup |
| **subscript** | CONTENT | Yes | Yes | No | `<sub>` custom DOM | **CONTENT** | **No** | Semantic markup |
| **alignment** | TRAIT (block) | **No** (product) | N/A | `style.textAlignment` | inline + `properties.align` + canvas trait | **TRAIT** | **No** — ignore selection | Block chrome on painted `> div` (Phase 5D); see §7 |
| **font size** | HYBRID | Yes | **Yes (inline)** | `style.fontSize` (+ mobile) | partial inline; full/caret/mobile → trait | **HYBRID** | **Limited** — caret/mobile only | Collapsed caret + mobile = block default; non-collapsed = inline even at 100% |
| **text color** | HYBRID | Yes | **Yes (inline)** | `style.textColor` | span + **always** trait update | **HYBRID** | **No** for selection; **Yes** for control surface | RTE color = content; block default only via explicit trait control (panel — missing today) |
| **highlight** | CONTENT | Yes | Yes | No (block bg ≠ highlight) | hiliteColor + orphan `highlightColor` trait | **CONTENT** only | **No** | Highlight is mark/span background, not block `backgroundColor` |
| **bullet list** | CONTENT | Yes | Yes | No | `<ul>/<li>` + orphan flags | **CONTENT** | **No** | Document structure |
| **ordered list** | CONTENT | Yes | Yes | No | `<ol>/<li>` + orphan flags | **CONTENT** | **No** | Document structure |
| **link create/edit** | CONTENT + TRAIT presentation | Yes | Yes | `linkColor`, `linkDecoration` on `a` via renderCss | content `<a>` + inline styles | **CONTENT** behavior + **TRAIT** link paint | **No** | href in content; color/decoration from renderCss |
| **link remove** | CONTENT | Yes | Yes | — | unwrap `<a>` | **CONTENT** | **No** | Structure only |
| **paste** | CONTENT | — | — | — | plain text nodes | **CONTENT** | **No** | HTML stripped by design |

### Non-RTE Text presentation controls (property panel)

| Property | Natural owner | Panel location | Partial valid? | Proposed owner |
|----------|---------------|----------------|----------------|----------------|
| font family | TRAIT | `TextElementProperties` Typography | No | **TRAIT** |
| line height | TRAIT | Typography | No | **TRAIT** |
| letter spacing | TRAIT | Typography | No | **TRAIT** |
| padding / margin | TRAIT | Layout | No | **TRAIT** |
| width / position | TRAIT | Layout | No | **TRAIT** |
| background / border / radius / shadow | TRAIT | Not on Text panel today; defaults via `style` bag | No | **TRAIT** when exposed |
| text alignment | TRAIT | **Not on Text element panel today** (only global theme StyleSection) | No | **TRAIT** — add panel control or route RTE align to trait |
| default text color | TRAIT | **No dedicated Text panel control** (RTE writes trait today) | No | **TRAIT** via panel; RTE must not own block default |
| default font size | TRAIT | **No dedicated Text panel control** | No | **TRAIT** via caret/mobile RTE path + future panel |
| font weight / style | TRAIT | Not on Text panel | No | **TRAIT** only if added to panel — **not** RTE bold/italic |

**Product gap (document, do not fix in G2A.1):** Text block lacks property-panel controls for text color, font size, and alignment — RTE toolbar currently serves as the only merchant UI for those traits. G2B must fix **ownership**, not necessarily add panels; caret font-size → trait remains until panel exists.

---

## 5. 99% versus 100% selection analysis

| Question | Answer |
|----------|--------|
| Should 99% selected → content but 100% → trait? | **No** — unacceptable as a general rule |
| Would merchants notice? | **Yes** — manual select-all vs triple-click / Cmd+A can disagree with `isWholeEditorSelected` |
| Is detection reliable? | **No** — `range.toString().trim() === editor.textContent.trim()` ignores HTML structure, lists, ZWSP, trailing BR |
| Which commands may use selection at all? | **Only font size** distinguishes **collapsed vs non-collapsed**, not partial vs full |
| Correct boundary for font size | **Collapsed caret** (or mobile device policy) → trait; **any non-collapsed range** → content inline, **including 100%** |

**Explicit answer to G2A.1 gate question:**

> Why should selecting 100% rather than 99% change the future behavior of newly typed content?

For **bold/color/italic**: it should **not**. G2B rejects that boundary.

For **font size** under corrected contract: 100% selection also **does not** change to trait — only **caret-only** (and mobile block-default policy) does. That answers the gate without a 99%/100% cliff.

---

## 6. Full-selection normalization verdict

**Verdict: REJECT as global rule; COMMAND-SPECIFIC only.**

| Command | Full-selection normalization to trait? | Action on full selection |
|---------|----------------------------------------|--------------------------|
| bold / italic / underline / strike | **No** | Apply content formatting to all selected nodes/spans |
| text color | **No** | Wrap all selected content in color span(s); **do not** update `style.textColor` |
| highlight | **No** | Content highlight on all selected text |
| font size | **No** (correct G2A.1) | Inline `font-size` on wrapped selection; clear conflicting inline only within selection wrapper |
| alignment | **N/A** | No selection — always update `style.textAlignment` for whole block |

**Remove** `isWholeEditorSelected` → trait path for font size in G2B.

**Retain** `clearInlineStyles` only when normalizing **within a command’s content mutation** (e.g., replacing span wrappers), not as “full selection → trait promotion.”

---

## 7. Paragraph alignment product decision

### Code facts

| Question | Evidence |
|----------|----------|
| Multiple paragraphs in one Text block? | **Yes** — Enter creates `<div>`/`<p>`; lists create `<ul>/<ol>` |
| Lists supported? | **Yes** — `insertUnorderedList` / `insertOrderedList` |
| Different paragraph alignment via RTE today? | **Yes** — `execCommand justify*` sets `text-align` on block elements inside content |
| Intentional product feature? | **No** — incidental browser `execCommand` behavior; conflicts with Phase 5D block-level `textAlignment` on `> div` |
| Mixed alignment inside one block desired? | **No** for popup builder (email-style blocks) |

### Recommendation: **Paragraph Option A**

```text
One Text block → one alignment → style.textAlignment → renderCss on > div
Different alignment → separate Text blocks
```

**Rationale:** Phase 5D explicitly places `text-align` on painted `> div`. Popup/email merchants think in block-level alignment. Incidental per-paragraph `text-align` in content is a **leak**, not a feature.

**Option C (structured paragraph model):** Not required for G2B. Current HTML `<p>`/`<div>`/lists remain valid **content structure**; only **alignment** is block-scoped. Future structured model must not require per-paragraph alignment in content.

---

## 8. Block presentation inventory

These modify **`properties.style` → canonical normalization → artifact-scoped renderCss** only. **Never** write presentation into `properties.content` from these controls.

| Property | Verified control surface | Trait field(s) | renderCss target |
|----------|-------------------------|----------------|------------------|
| Text alignment | RTE toolbar (G2B fix) + future panel | `textAlignment` | `> div` |
| Default text color | Future panel; **not** RTE partial/full | `textColor` | `> div` |
| Default font size | RTE caret/mobile; future panel | `fontSize`, `fontSizeMobile` | `> div` |
| Font family | TextElementProperties | `fontFamily` | `> div` |
| Line height | TextElementProperties | `lineHeight` | `> div` |
| Letter spacing | TextElementProperties | `letterSpacing` | `> div` |
| Font weight / style | **Panel only if added** — not RTE bold/italic | `fontWeight`, `fontStyle` | `> div` |
| Padding | TextElementProperties | `paddingDesktop/Mobile` | `> div` |
| Margin | TextElementProperties | `marginDesktop/Mobile` | wrapper |
| Background | style bag (when exposed) | `backgroundColor` | `> div` |
| Border / radius / shadow | style bag | `border*`, `cornerRadius*`, box shadow | `> div` |
| Width / height / position | TextElementProperties | `elementWidth`, `elementPosition`, `height` | wrapper / `>h` |
| Link color / decoration | element `style.linkColor` / form default | `linkColor`, `linkDecoration` | `> div a` descendant |

---

## 9. RTE content formatting inventory

These modify **`properties.content`** (sanitized rich HTML). **Never** promote to block traits based on selection coverage.

| Command | Persistence form | Trait sync |
|---------|------------------|------------|
| bold | `<strong>`, `<b>`, or browser equivalent | **None** |
| italic | `<em>`, `<i>` | **None** |
| underline | `<u>`, text-decoration inline | **None** |
| strikethrough | `<s>`, `<strike>` | **None** |
| superscript / subscript | `<sup>`, `<sub>` | **None** |
| partial / full text color | `<span style="color:…">` | **None** from RTE |
| partial / full font size | `<span style="font-size:…">` | **None** from RTE (non-collapsed) |
| highlight | `<mark>` or span `background-color` | **None** — delete `style.highlightColor` |
| bullet / ordered list | `<ul>/<ol>/<li>` | **None** — delete orphan list flags |
| link | `<a href="…">` | Presentation from renderCss only |
| paste | plain text nodes | **None** |

---

## 10. Caret / no-selection policy matrix

| Command | None (not focused) | Caret | Partial | Full | Multi-paragraph | Reason |
|---------|---------------------|-------|---------|------|-----------------|--------|
| bold | no-op | **execCommand typing state → content** | content tags | content tags | content tags | Standard RTE character formatting |
| italic | no-op | typing → content | content | content | content | Same |
| underline | no-op | typing → content | content | content | content | Same |
| strikethrough | no-op | typing → content | content | content | content | Same |
| sup/sub | no-op | content wrap / execCommand | content | content | content | Semantic |
| alignment | no-op | **trait whole block** | **trait whole block** | **trait whole block** | **trait whole block** | Block presentation — ignore selection |
| font size | no-op | **trait** (block default) | **content inline** | **content inline** | **content inline** | Only explicit hybrid exception |
| text color | no-op | **typing color → content** (not trait) | **content span only** | **content span only** | **content span only** | Fix dual trait write |
| highlight | no-op | typing/content mark | content | content | content | No block highlight trait |
| lists | no-op | toggle list at caret | toggle in selection | toggle in selection | per-paragraph browser behavior | Content structure |
| link | no-op | insert link at caret | wrap selection | wrap selection | wrap selection | BEHAVIOR |
| paste | no-op | plain insert | plain insert | plain insert | plain insert | BEHAVIOR |

**Caret + bold + type:** Browser `execCommand('bold')` with collapsed range → typed characters inherit bold → persisted in content on input. **Do not** mirror to `style.fontWeight`.

**Caret + color + type:** Use browser typing color / temporary span at caret — **do not** call `onTextColorChange` → trait. Fix current bug where `applyTextColor` always updates trait.

---

## 11. Alignment final contract

### Verified current paths

```text
RTE alignment (BalloonEditor → InlineEditor.applyAlignment)
  → execCommand justifyLeft|Center|Right|Full
  → inline text-align on inner div/p
  → onAlignChange → properties.align (ORPHAN)

Canvas / renderCss
  → style.textAlignment
  → resolveTextPaintedCssRules → renderCss text-align on > div

Canvas sync effect
  → editorRef.style.textAlign = align (from style.textAlignment)
```

### Target contract (G2B)

```text
Alignment command
  → BLOCK_PRESENTATION
  → style.textAlignment only
  → renderCss on > div
  → NO properties.align (Text)
  → NO execCommand justify* (Text)
  → NO new inline text-align in content
  → Selection ignored — whole block updates
```

| Question | Answer |
|----------|--------|
| Is Text alignment BLOCK_ONLY? | **Yes** |
| Should selection state be ignored? | **Yes** |
| Partial selection alignment affects whole block? | **Yes** (merchant expects block align buttons to align the Text block) |
| Multi-paragraph selection affects whole block? | **Yes** |
| Safe to delete `properties.align` for Text after G2B? | **Yes** |
| Button `properties.align` untouched? | **Yes** — Button uses align for wrapper `text-align` in serialize (separate block family) |
| Existing saved inline `text-align`? | See §12 |

---

## 12. Existing content classification

Forward-only: **no runtime fallback branches**. Handle historical data via sanitize-on-save / optional one-time migration.

| Example | Category | Preserve? | Stop creating? | G2B+ handling |
|---------|----------|-----------|----------------|---------------|
| `<span style="color:red">word</span>` | Legitimate rich-text | **Yes** | — | Preserve |
| `<strong>text</strong>` | Legitimate rich-text | **Yes** | — | Preserve |
| `<div style="text-align:center">…</div>` | Block-presentation leak | Render until edited | **Yes** | Strip `text-align` on edit/save normalize; migration candidate |
| `<a style="color:red">` | Presentation leak | Render | **Yes** | Stop `applyLinkStyles`; strip inline color/decoration on save normalize |
| `<span style="background-color:transparent">` | Editor artifact | — | **Yes** | Sanitize immediately (conservative) |
| Full-block span color matching trait | Ambiguous | **Yes** | Stop new duplicates | Do not auto-strip (cannot infer intent) |
| Orphan `properties.align` | Model garbage | — | **Yes** | Stop writing; ignore on read; optional migration delete |

**Inline `text-align` recommendation:** **Preserve in stored HTML until block is edited or popup re-saved through normalizer** — on serialize/save, remove `text-align` from content elements when `style.textAlignment` is authoritative. **No** runtime “if inline then ignore trait” branch — normalize at save boundary only (G2B sanitizer extension, not serializer architecture change in G2A.1).

---

## 13. Final command classes

Replace G2A `SELECTION_CAPABLE` with:

| Class | Meaning | Commands |
|-------|---------|----------|
| **BLOCK_PRESENTATION** | Whole block → `properties.style` → renderCss | alignment; panel typography/layout; caret/mobile font-size default |
| **INLINE_FORMATTING** | Range/caret typing → `properties.content` | bold, italic, underline, strike, color (RTE), size (non-collapsed RTE), highlight |
| **CONTENT_STRUCTURE** | Semantic HTML tree | lists, sup, sub |
| **BEHAVIOR** | Actions on content | link, unlink, paste |

**Selection policy** is a **separate field** on registry entries, not a class:

```typescript
selectionPolicy: 'ignore' | 'collapsed-vs-range' | 'require-range'
```

Only **font size** uses `collapsed-vs-range`. Alignment uses `ignore`. Inline formatting uses `require-range` for apply, with caret typing handled by browser for semantic commands.

---

## 14. Final registry shape

`RTE_COMMAND_REGISTRY` **remains correct** but must not encode “full selection → trait” defaults.

### Per-entry fields

| Field | Purpose |
|-------|---------|
| `id` | Stable command id |
| `class` | One of four classes above |
| `contentOwner` / `traitField` | Explicit mutation targets |
| `selectionPolicy` | See §13 — **not** ownership |
| `caretPolicy` | typing-state \| trait \| no-op |
| `execute` | DOM + model writes |
| `normalizeOnSave` | Optional content cleanup hook |
| `toolbarFeatureKey` | Links to `RTE_CONFIGS` visibility — **separate concern** |

### Toolbar visibility

**Stay separate** from ownership (`RTE_CONFIGS` / `BalloonEditor`). A visible toolbar button does not imply trait ownership.

### Dispatch

Single `dispatchRteCommand(id, ctx)` — InlineEditor/BalloonEditor call registry; **no** ownership switches in UI components.

---

## 15. G2B implementation boundary

### In scope

- Implement registry + dispatch per §14
- Alignment → `style.textAlignment` only; remove Text `execCommand justify*`, `properties.align` writes
- Bold/italic/underline/strike/lists/sup/sub → content-only; remove orphan property flags
- Text color RTE → content only; remove automatic trait sync on RTE color
- Font size → collapsed/mobile → trait; non-collapsed → content (remove `isWholeEditorSelected` → trait)
- Highlight → content only; delete `style.highlightColor` writes
- Links → stop inline presentation; rely on renderCss link descendants
- Save-boundary normalizer hooks: strip leaked `text-align`, transparent backgrounds, anchor inline color (no runtime branches)
- Delete `WysiwygEditor.tsx`, dead `customButton` config
- Browser integration tests per §17

### Out of scope

- G1 `.export.css` (complete)
- Block-family CSS migrations
- Mandatory one-time content migration script (optional G2C)
- Adding Text property-panel controls for color/align (recommended follow-up, not blocking registry)
- Serializer architecture changes beyond sanitizer/normalizer hooks agreed above

---

## 16. G2B deletion inventory

| Item | Classification | Notes |
|------|----------------|-------|
| Text `properties.align` writes | **DELETE in G2B** | `TextCanvas.tsx` |
| `onAlignChange` orphan path | **DELETE in G2B** | Replace with trait update |
| Text `execCommand justify*` | **DELETE in G2B** | `InlineEditor.applyAlignment` |
| `properties.bold` / `onFormatChange` orphan keys | **DELETE in G2B** | Content tags only |
| `isBold` / `isItalic` / `isUnderline` props | **DELETE in G2B** | Use `queryCommandState` for toolbar |
| `isBulletList` / `isOrderedList` flags | **DELETE in G2B** | Derive from DOM or drop |
| `style.highlightColor` | **DELETE in G2B** | Content-only highlight |
| `applyLinkStyles` inline on anchor | **DELETE in G2B** | renderCss owns |
| `WysiwygEditor.tsx` | **DELETE in G2B** | Zero importers |
| `customButton` config | **DELETE in G2B** | Dead flag |
| `isWholeEditorSelected` → trait for font size | **DELETE in G2B** | Replace with collapsed-only trait policy |
| `editorRef.style.textAlign = align` effect | **REFACTOR in G2B** | Canvas reads trait via props/renderCss preview |
| `onTextColorChange` on every RTE color apply | **DELETE in G2B** | Trait only from block-default control |
| Duplicate preview paths | **REFACTOR in G2B** | Consolidate via registry |
| Button `blockSelectionFormatting` | **KEEP** | Button trait-only model is correct reference |
| `shouldBlockSelectionFormattingApply` | **KEEP** | Button-only guard |

---

## 17. Required browser tests (pre-G2B implementation gate)

**Harness:** Mount real `InlineEditor` + `BalloonEditor` in Playwright (minimal popup builder fixture or `@pb/test-utils/rteIntegrationHarness` to add in G2B). Must exercise **live selection**, not static HTML injection alone.

| # | Scenario | Assertion |
|---|----------|-----------|
| 1 | caret + bold + type | Typed text in `<strong>`/bold; `style.fontWeight` unchanged |
| 2 | partial + bold | Only selection wrapped; trait unchanged |
| 3 | full + bold | All content bold tags; **not** `style.fontWeight` |
| 4 | partial + color | Span color; `style.textColor` unchanged |
| 5 | full + color | All selected wrapped/colored; trait unchanged |
| 6 | caret + color + type | Typed chars colored in content; trait unchanged |
| 7 | partial + font size | Span `font-size`; trait unchanged |
| 8 | full + font size | Inline on all selected; trait unchanged |
| 9 | caret + font size | `style.fontSize` updated; no erroneous full-block inline |
| 10 | partial + alignment | **Whole block** `style.textAlignment` updated; no new content inline |
| 11 | multi-paragraph + alignment | Same as 10 — block trait only |
| 12 | link create | `<a href>` present; no inline color on anchor; renderCss colors link |
| 13 | save → reload → edit → save | Partial format preserved; alignment trait survives |
| 14 | trait color change after RTE partial | Unstyled text picks up trait; span keeps RTE color |
| 15 | RTE format after trait change | Trait unchanged; content format applies |

**Preserve Phase 0:** 18 migrated regression green, 0 red, 5 controls green — run full baseline suite on G2B PR.

**Existing audit tests to keep:** `popupProductionCssRteOwnership.audit*.test.ts`, `textPresentation.browser.test.ts`.

---

## 18. Risks and unresolved product decisions

| Risk | Mitigation |
|------|------------|
| Text block lacks panel for default color/align | G2B fixes RTE ownership; follow-up UI adds block-default controls |
| Historical inline `text-align` popups | Save-boundary normalize; optional G2C migration |
| Merchants relied on full-select font size changing “default” | Product note: use caret in empty block or future panel for default; full-select = format all characters |
| Mobile font size → trait always | **Keep** — responsive block presentation (device concern, not selection %) |
| Fragile tests if still using `isWholeEditorSelected` | Remove trait promotion path |

**Unresolved (non-blocking):** Whether to add Text property-panel controls for default color/alignment in G2B or G2C.

---

## 19. Exact next action

1. **Stakeholder sign-off** on this document (Model C / corrected hybrid).
2. **Begin G2B implementation:** registry + alignment fix + command-semantic rewire + save normalizer hooks.
3. **Add RTE Playwright harness** as first G2B PR slice before merging behavior changes.
4. **Do not** resume block-family CSS migration until G2B merges and Phase 0 + RTE tests green.

---

## Required final response format

| # | Item | Answer |
|---|------|--------|
| 1 | Primary ownership model | **C** |
| 2 | Why | Command semantics (B) is default; **only** font-size caret/mobile uses control-context exception — **not** global full-selection → trait |
| 3 | Full-selection normalization | **COMMAND-SPECIFIC** (reject global; font size: **no** full→trait; alignment: block always) |
| 4 | 99% vs 100% ownership switch | **NOT ACCEPTABLE** (limited exception removed for full→trait) |
| 5 | Bold owner | **CONTENT** |
| 6 | Italic owner | **CONTENT** |
| 7 | Underline owner | **CONTENT** |
| 8 | Text color owner | **HYBRID** (RTE → content; trait → block default via panel/explicit control only) |
| 9 | Font size owner | **HYBRID** (caret/mobile → trait; any non-collapsed range → content) |
| 10 | Highlight owner | **CONTENT** |
| 11 | Alignment owner | **TRAIT** |
| 12 | Lists owner | **CONTENT** |
| 13 | Sup/sub owner | **CONTENT** |
| 14 | Links owner | **CONTENT** behavior + **renderCss** presentation |
| 15 | Caret-only policy | Bold/italic/underline/strike/lists/sup/sub/link: typing/content; color: typing/content **not trait**; font size: **trait**; alignment: **trait whole block**; highlight: typing/content |
| 16 | Paragraph alignment model | **A** |
| 17 | `properties.align` for Text | **DELETE** |
| 18 | Existing inline `text-align` | Stop creating; strip on save/edit normalize; optional one-time migration; **preserve until normalized** — no runtime fallback |
| 19 | Command registry classification | **BLOCK_PRESENTATION**, **INLINE_FORMATTING**, **CONTENT_STRUCTURE**, **BEHAVIOR** |
| 20 | Previous G2A assumption corrected | **YES** |
| 21 | G2B ready after this decision | **YES** |
| 22 | Production code changed in this phase | **NO** |
