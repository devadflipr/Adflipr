# Button Label + Typography Persistence — Cleanup Audit (Review Pack)

**Date:** 2026-07-06  
**Scope:** Audit-only — Button block label text, RTE, and `properties.style` typography ownership  
**Purpose:** External review (e.g. ChatGPT) before a cleanup implementation phase  
**References:** Phase 5C (Button renderCss), G2A.1 semantics, G2B/G2B.2 (Text), Phase H1 (partial Button migration)

---

## 1. Executive summary

Button block **shell presentation** (padding, colors, border, width, hover) was migrated to artifact-scoped `renderCss` in **Phase 5C**. The painted `<button>` tag has **no merchant `style="..."`** on export.

However, Button is **not** a finished persistence story:

1. **Label text** lives in `properties.content` (HTML string).
2. **Whole-label typography** (font size, weight, italic) lives in `properties.style` and compiles to `renderCss` on `<button>`.
3. **RTE on canvas** is the **only active UI** for bold / italic / font-size — the property panel Typography section exposes **font family and text colors only**, not size/weight/italic.
4. Button RTE uses **`blockSelectionFormatting`** — partial text selection **blocks** formatting; there is **no range formatting** semantics.
5. Export still has **structural inline** (align wrapper `text-align`) and **link inline** (`applyRichTextLinkStyles` + embedded `buildRichTextLinkExportCss`).
6. **Dead components** exist: `ButtonText.tsx`, `ButtonFontSettings.tsx` (zero imports).

**Core question for review:**

> Button has no meaningful partial-selection formatting. Should label typography stay on `properties.style` (trait → renderCss) with RTE as a trait editor, or should RTE typography be removed and traits move entirely to the property panel — with `properties.content` as plain text only?

**Verdict (this document):** **AUDIT ONLY — DECISION REQUIRED** before implementation.

---

## 2. Target architecture reminder (four concerns)

```text
MERCHANT PRESENTATION  → artifact-scoped renderCss (properties.style)
REUSABLE STRUCTURE     → shared/export CSS or structural serializer inline
RUNTIME BEHAVIOR       → embed sheets, focus/hover mechanics
CONTENT-LOCAL FORMAT   → properties.content (Text block — NOT Button model)
```

**Button canonical model (G2A.1):**

```text
properties.content  → label text (what it says)
properties.style    → whole-block presentation including typography → renderCss
properties.align    → wrapper text-align (structural inline today)
```

Button is **intentionally not** Text-style content-local bold/italic.

---

## 3. Button vs Text — ownership comparison

| Concern | Text (post-G2B) | Button (current) |
|---------|-----------------|------------------|
| Bold / italic | `properties.content` (`<strong>`, `<em>`) | `properties.style.fontWeight` / `fontStyle` → renderCss |
| Font size (block default) | `properties.style.fontSize` → renderCss + partial inline in content | `properties.style.fontSize` → renderCss only |
| Font size (partial range) | Content inline spans | **Not supported** (partial selection blocked) |
| Text color | Hybrid (content spans + block trait) | `properties.style.textColor` → renderCss |
| Alignment | `properties.style.textAlignment` → renderCss | `properties.align` → wrapper inline `text-align` |
| RTE partial selection | Supported | **Blocked** via `shouldBlockSelectionFormattingApply` |
| RTE bold path | `execCommand` → content | `onFormatChange` → style traits (no content tags) |
| Export painted owner inline | **No** (renderCss) | **No** on `<button>` (renderCss) |
| Export content inline | **Yes** (legitimate) | Should be **minimal**; legacy HTML possible |

---

## 4. Current production call graph

```text
Canvas edit (ButtonCanvas)
├── InlineEditor (elementType="button", blockSelectionFormatting=true)
│   ├── onContentChange → properties.content
│   ├── onFormatChange → properties.style.fontWeight / fontStyle
│   └── onFontSizeChange → properties.style.fontSize (+ fontSizeMobile on mobile device)
├── getButtonStyle(style, align, device) → React inline on <button> (canvas preview only)
└── hover handlers → transient inline style on canvas (not exported)

Property panel (ButtonProperties)
├── Typography: fontFamily, textColor, hoverTextColor only
├── Layout / padding / margin / border / shadow → properties.style
└── Content tab: ButtonAction only (no label text field)

Export (serializeButton)
├── wrapper <div style="width:100%;text-align:{align}">  ← structural inline
├── <button> (no style=) + label HTML from properties.content
└── compileButtonPresentationRenderCss → artifact renderCss

Embedded step CSS
└── buildRichTextLinkExportCss(linkColor) for button label links
```

---

## 5. RTE behavior — what “no selection formatting” actually means

**Config:** `RTE_CONFIGS.button` enables `bold`, `italic`, `fontSize`, and `blockSelectionFormatting: true`. Disables underline, link, textColor, alignment, lists.

**Partial selection guard:**

```text
shouldBlockSelectionFormattingApply()
  = blockSelectionFormatting && hasNonCollapsedTextSelection()
```

When true, `applyFormatting` and `applyFontSize` **return early** — no trait update, no execCommand.

**When formatting does apply:**

| Caret/selection state | Bold/italic | Font size |
|----------------------|-------------|-----------|
| Collapsed caret | `onFormatChange` → style traits | `onFontSizeChange` → style; clears content `font-size` inline |
| Partial selection | **Blocked — no-op** | **Blocked — no-op** |
| Full editor selected | `onFormatChange` → style traits | Clears content inline font-size → `onFontSizeChange` |

**Implication:** Button RTE is a **whole-label trait editor** disguised as rich text. It is **not** a substring formatter.

---

## 6. Complete Button persistence field inventory

| Field | Type | Active writer(s) | Active reader(s) | Export effect | Classification |
|-------|------|------------------|------------------|---------------|----------------|
| `properties.content` | HTML string | `ButtonCanvas` `onContentChange` | canvas, serializer | Label HTML inside `<button>` | **CANONICAL LABEL TEXT** |
| `properties.style.backgroundColor` | string | trait panel | renderCss, canvas | renderCss on `<button>` | **CANONICAL TRAIT** |
| `properties.style.textColor` | string | trait panel | renderCss, canvas | renderCss | **CANONICAL TRAIT** |
| `properties.style.hoverBackgroundColor` | string | trait panel | canvas hover only | canvas runtime (not export CSS) | **CANONICAL TRAIT** (canvas behavior) |
| `properties.style.hoverTextColor` | string | trait panel | canvas hover only | canvas runtime | **CANONICAL TRAIT** (canvas behavior) |
| `properties.style.fontFamily` | string | trait panel | renderCss, canvas | renderCss | **CANONICAL TRAIT** |
| `properties.style.fontSize` | string | **RTE only** (no panel control) | renderCss, canvas | renderCss | **CANONICAL TRAIT** |
| `properties.style.fontSizeMobile` | string | RTE on mobile device | renderCss, canvas | renderCss `@media` | **CANONICAL TRAIT** |
| `properties.style.fontWeight` | string | **RTE only** (default `medium`) | renderCss, canvas | renderCss | **CANONICAL TRAIT** |
| `properties.style.fontStyle` | string | **RTE only** (`italic` / `normal`) | renderCss, canvas | renderCss | **CANONICAL TRAIT** |
| `properties.style.padding*` / margin / border / width / radius / shadow | various | trait panel | renderCss, canvas | renderCss | **CANONICAL TRAIT** |
| `properties.style.action*` | action metadata | trait panel | serializer attrs | HTML data attrs | **CANONICAL BEHAVIOR METADATA** |
| `properties.align` | string | trait panel? / defaults | serializer wrapper inline | wrapper `text-align` inline | **CANONICAL STRUCTURAL TRAIT** (inline export) |
| `properties.rteEnabled` | boolean | defaults (`true`) | serializer, canvas | plain text vs sanitized HTML | **FEATURE FLAG** |
| `<b>/<strong>/<i>/<em>` in content | HTML | legacy / paste / bugs | serializer | exported inline in label | **LEGACY / ORPHAN RISK** |
| inline `style=` on spans in content | HTML | legacy RTE leaks | serializer | exported inline | **LEGACY / ORPHAN RISK** |
| inline on `<a>` in label | HTML | `applyRichTextLinkStyles` at export | serializer | exported inline + embedded CSS | **DUPLICATE PATH** |

**Dead / unused files (no production imports):**

| File | Purpose | Status |
|------|---------|--------|
| `ButtonText.tsx` | Panel InlineEditor + typography writers | **DEAD CODE** |
| `ButtonFontSettings.tsx` | Panel fontSize/fontWeight controls | **DEAD CODE** |

---

## 7. Why `properties.style` still matters (even without selection formatting)

`properties.style` is **not** primarily an RTE artifact. It is the **Button merchant presentation model** for Phase 5C renderCss:

- Shell chrome: background, border, padding, margin, width, shadow, hover colors (canvas)
- Whole-label typography: fontFamily, fontSize, fontWeight, fontStyle, textColor

Without `properties.style`, `compileButtonPresentationRenderCss` / `resolveButtonPaintedCssRules` have no input.

**What is questionable is not `properties.style` itself**, but:

1. **RTE as the only writer** for fontSize / fontWeight / fontStyle (panel gap)
2. **Legacy HTML formatting** in `properties.content` coexisting with trait typography
3. **RTE infrastructure** (`blockSelectionFormatting`, balloon) for a block that rejects range semantics

---

## 8. Export inline inventory (Button-specific)

| Location | Properties | Primary concern | Correct long-term destination |
|----------|------------|-----------------|--------------------------------|
| Wrapper `> div` | `width:100%`, `text-align` | REUSABLE STRUCTURE | structural inline **or** renderCss align rule |
| `<button>` | none (merchant) | — | renderCss ✅ (Phase 5C done) |
| Label HTML spans | legacy bold/color/size | CONTENT-LOCAL (orphan on Button) | **strip on save** or plain text |
| Label `<a>` | `color`, `text-decoration` inline | MERCHANT PRESENTATION | renderCss `button a` (move from embedded sheet) |
| Embedded `buildRichTextLinkExportCss` | link color `!important` | MERCHANT PRESENTATION | button renderCss compiler extension |
| Canvas hover | bg/color inline | RUNTIME BEHAVIOR | optional `:hover` in renderCss (future) |

---

## 9. Structural constants inside Button renderCss (Phase 5C)

| Constant | Classification | Cleanup impact |
|----------|----------------|----------------|
| `BUTTON_PAINTED_STRUCTURAL_STYLES` (flex shell, box-sizing, transition) | NECESSARY OWNER-LOCAL STRUCTURE | Keep |
| Merchant rules from `resolveButtonPaintedCssRules` | MERCHANT PRESENTATION | Keep |
| `alignContainer.backgroundColor` in compiler | MERCHANT PRESENTATION | Keep |

No change recommended to Phase 5C compiler in label cleanup phase unless link rules move in.

---

## 10. UI surface gap (important finding)

**ButtonProperties → Typography accordion exposes:**

- Font Family ✅
- Text Color ✅
- Text Hover Color ✅

**Does NOT expose:**

- Font Size ❌
- Font Weight ❌
- Italic ❌

**Therefore:** Canvas RTE balloon is currently the **primary** (only) control for size/weight/italic — not a duplicate of the property panel.

**Dead `ButtonFontSettings.tsx`** would have provided panel fontSize/fontWeight but was never wired into `ButtonProperties`.

---

## 11. Cleanup options (for reviewer decision)

### Option A — Trait panel owns typography; RTE is text-only (Recommended baseline)

```text
properties.content  → plain text or minimal HTML (escaped on export)
properties.style    → all typography + shell → renderCss
RTE                 → content editing only (no bold/italic/fontSize balloon)
Property panel      → add fontSize, fontWeight, italic controls
```

| Pros | Cons |
|------|------|
| Matches “no range formatting” reality | Merchants lose in-canvas typography balloon |
| Single writer per trait | Requires panel UX work |
| Easier forward-only content strip | Behavior change for existing users |
| Aligns with G2A.1 Button trait-only model | |

### Option B — Keep RTE as trait editor; document and harden

```text
Keep blockSelectionFormatting + onFormatChange → style
Add panel controls mirroring RTE (dual write OK if synced)
Strip orphan formatting tags from content on save/export
Move link presentation to renderCss; remove applyRichTextLinkStyles inline
```

| Pros | Cons |
|------|------|
| Minimal UX disruption | RTE stack remains for non-rich-text block |
| Panel + canvas both work | “RTE” name is misleading |
| Incremental | Dual writers need sync tests |

### Option C — Misguided — do NOT do

```text
Move Button bold/italic into properties.content like Text (G2B model)
```

**Rejected by G2A.1** — Button has no partial formatting semantics; would create dual ownership with renderCss fontWeight/fontStyle.

---

## 12. Recommended cleanup phase scope (draft)

**Proposed name:** **Phase B1 — Button Label Persistence + Typography Ownership Cleanup**

### In scope (draft)

1. **Decision lock:** Option A or B (default recommendation: **B** incremental — add panel controls + strip content orphans; optionally disable RTE typography commands in a follow-up)
2. **Forward-only content hygiene:** strip `<b>/<strong>/<i>/<em>/span[style]` from button `properties.content` on save/export (mirror G2B.2 strip list pattern)
3. **Delete dead code:** `ButtonText.tsx`, `ButtonFontSettings.tsx` (after panel gains any needed controls)
4. **Add Typography panel controls:** fontSize (desktop/mobile), fontWeight, italic toggle
5. **Link presentation:** move `buildRichTextLinkExportCss` into button renderCss; stop `applyRichTextLinkStyles` inline injection (or strip inline on export if links remain in content)
6. **Tests:** persistence idempotence, export negative-inline on label, renderCss parity, panel↔RTE sync if Option B

### Out of scope

- Text block / G2C RTE semantics
- Phone Phase 6E
- Removing `properties.style` entirely
- Converting Button to Text content-local formatting model
- Hover CSS in export renderCss (unless explicitly added)
- `properties.align` → renderCss migration (separate structural task)

---

## 13. Explicit do-not-change list (unless reviewer overrides)

| Item | Reason |
|------|--------|
| Phase 5C renderCss compiler contract | Accepted architecture |
| `blockSelectionFormatting` guard semantics | G2A.1 reference implementation |
| Button action attrs (`data-adflipr-popup-action*`) | Behavior metadata |
| `properties.align` on Button | Separate from Text `align`; deferred |
| Canvas hover inline handlers | Canvas-only runtime UX |

---

## 14. Test plan (implementation phase)

| Test | Assert |
|------|--------|
| Export HTML | `<button>` has no `style=` with merchant props |
| Export HTML | label has no orphan `<strong>`/inline color after normalize |
| renderCss | fontWeight/fontStyle/fontSize from traits present |
| Panel edit | fontSize change updates style without content mutation |
| RTE edit (if kept) | bold toggles style.fontWeight, not content tags |
| Partial selection | bold/fontSize still no-op on button |
| Link in label | color from renderCss, not inline (post link migration) |
| Phase 0 baseline | button cases remain green |
| G1 export CSS contract | PASS |
| Dead file removal | no imports of ButtonText/ButtonFontSettings |

---

## 15. Open questions for external reviewer

1. **Option A vs B:** Should Button RTE balloon lose typography commands entirely, or stay as a secondary trait editor?
2. **Content format:** Should `properties.content` be **plain text only** for buttons (`rteEnabled` deprecated), or sanitized HTML without presentation tags?
3. **Font weight vocabulary:** RTE writes `bold`/`normal`; defaults use `medium`; panel dead code used `normal|medium|semibold|bold`. Canonical enum?
4. **Italic:** No panel control today — add toggle in Typography accordion?
5. **Links in button labels:** RTE disables link, but export still supports `<a>` + inline color. Keep links at all?
6. **Forward-only strip:** Strip legacy content formatting on load/save without migration — acceptable?
7. **Align wrapper inline:** Include in B1 or defer to structural CSS phase?
8. **Hover colors:** Export as renderCss `:hover` vs keep canvas-only?

---

## 16. Risk register

| Risk | Severity | Mitigation |
|------|----------|------------|
| Removing RTE typography breaks merchant workflow | Medium | Add panel controls first (Option B) |
| Legacy popups with content tags lose visual bold | Low | Traits may already mirror; strip is forward-only |
| fontWeight `medium` vs `bold` mismatch | Low | Normalize enum in compiler |
| Link inline + renderCss duplicate | Medium | Single owner in renderCss |
| Reviewer assumes Button = Text model | High | This audit §3 + G2A.1 reference |

---

## 17. File reference index

| Path | Role |
|------|------|
| `blocks/button/ButtonCanvas.tsx` | Canvas RTE + trait writes |
| `blocks/button/serialize.ts` | Export HTML + label HTML |
| `property-panels/.../button/ButtonProperties.tsx` | Active trait panel |
| `property-panels/.../button/ButtonText.tsx` | **Dead** |
| `property-panels/.../button/ButtonFontSettings.tsx` | **Dead** |
| `rte/RTEConfig.ts` | Button RTE feature flags |
| `InlineEditor.tsx` | blockSelectionFormatting dispatch |
| `canvas/ElementStyles.ts` | `resolveButtonPaintedCssRules` |
| `popupHtmlSerializer/buttonPresentation.ts` | renderCss compiler |
| `popupHtmlSerializer/serializeLayout.ts` | `buildRichTextLinkExportCss` |
| `popupHtmlSerializer/popupHtmlSanitize.ts` | `applyRichTextLinkStyles` |
| `canvas/ElementDefaults.ts` | Default button style traits |
| `__tests__/buttonPresentationRenderCss.test.ts` | Phase 5C regression |

---

## 18. Suggested ChatGPT review prompt

```text
Review the attached Button Label Persistence Cleanup Audit.

1. Confirm or challenge the Button vs Text ownership model.
2. Choose Option A, B, or a hybrid — with rationale.
3. Answer the 8 open questions in §15.
4. Propose a minimal implementation sequence (ordered steps).
5. Flag any risk this audit missed.
6. Do NOT recommend moving Button bold/italic to properties.content unless you explicitly reject G2A.1.

Assume: Phase 5C renderCss stays; forward-only cleanup acceptable; no Text/G2C scope creep.
```

---

## 19. Verdict

**AUDIT COMPLETE — IMPLEMENTATION NOT STARTED**

Button **`properties.style` is required** for renderCss. The cleanup target is **duplicate/orphan paths** (content HTML formatting, dead panel files, RTE-as-trait-editor confusion, link inline duplication) — **not** removal of `properties.style`.

**Suggested next step after review:** Approve Phase B1 scope (§12) and Option A/B choice, then implement.
