# Phase G2B — RTE and trait CSS persistence stabilization report

**Date:** 2026-07-06  
**Phase:** CSS persistence audit + minimal surgical fixes  
**Related:** [`popup-rte-command-semantics-g2a1-decision.md`](./popup-rte-command-semantics-g2a1-decision.md) (semantics **deferred**), [`popup-text-presentation-css-phase5d-report.md`](./popup-text-presentation-css-phase5d-report.md), [`popup-export-css-production-build-fix-phase-g1-report.md`](./popup-export-css-production-build-fix-phase-g1-report.md)

---

## 1. Executive verdict

**Verdict: ACCEPT WITH CLEANUP**

Phase 5D Text block presentation in artifact-scoped `renderCss` is **correct and intact**. G2B found **duplicate CSS persistence** (not missing migration) in three areas:

| Defect | Severity | G2B fix |
|--------|----------|---------|
| Inline `<a style="color; text-decoration">` duplicated renderCss link rules | High | Export/save normalizer strips anchor presentation |
| Inline `text-align` on content `<div>`/`<p>` duplicated `style.textAlignment` / renderCss | High | Normalizer strips block-level content align; RTE align syncs trait |
| Orphan `properties.align` not in serializer/renderCss | Medium | Migrate to `style.textAlignment` on save; RTE writes trait |
| `background-color: transparent` editor noise in content | Low | Normalizer removes |
| Text color trait updated on every RTE color apply | Known dual-write | **Preserved** (current behavior) |
| Font size caret/full/mobile → trait + partial inline | Known dual-write | **Preserved** (current behavior) |

**No** command registry, **no** selection-semantics changes, **no** renderCss architecture changes.

---

## 2. Scope intentionally deferred

Per superseding G2B prompt — **not implemented:**

- Selection vs block command semantics
- 99% vs 100% selection behavior
- Caret vs range ownership policies
- Bold/color/font-size ownership redesign
- `RTE_COMMAND_REGISTRY` / `dispatchRteCommand`
- Paragraph alignment product model
- New Text property controls
- Block-family CSS migration

See [`popup-rte-command-semantics-g2a1-decision.md`](./popup-rte-command-semantics-g2a1-decision.md) for deferred semantic work.

---

## 3. Complete CSS persistence pipeline

```text
Merchant interaction (RTE toolbar / trait panel)
    ↓
InlineEditor / BalloonEditor / TextCanvas callbacks
    ↓
Live editor DOM (contentEditable innerHTML + canvas React styles)
    ↓
properties.content  AND/OR  properties.style  (+ legacy properties.align)
    ↓
PopupBuilderSnapshot (Redux/history state)
    ↓
[G2B] normalizePopupSnapshotTextBlocks()  — on serializePopupExport + buildPopupSaveMetadata
    ↓
serializePopupExport()
    ├─ serializeText() → sanitizeRichTextHtml → normalizeTextRichContentHtml
    ├─ serializeElement() → structure-only Text wrapper (margin/width structural inline only)
    └─ compilePopupRenderCss() → artifact-scoped renderCss
    ↓
buildPopupSaveMetadata() → builderState + htmlByStep + renderCss
    ↓
API persistence (htmlContent + metadata)
    ↓
Reload → parseBuilderState → TextCanvas hydrates content + getTextBlockContentStyle
    ↓
Re-save → same normalize path (idempotent)
    ↓
Storefront embed → mountRenderArtifactCss(renderCss) + hoist step HTML
    ↓
Computed cascade: renderCss defaults + legitimate content inline overrides
```

---

## 4. CSS persistence location inventory

| Presentation | Editor representation | Model storage | Serialized HTML | renderCss | Reload source | Duplicate? |
|--------------|----------------------|---------------|-----------------|-----------|---------------|------------|
| Font size (block default) | Root `fontSize` prop / trait path | `style.fontSize` (+ mobile) | **No** on `> div` | **Yes** `> div` | `style` | Partial span possible |
| Font size (partial RTE) | span `font-size` | `content` | span inline | block default on `> div` | `content` | **Intentional cascade** |
| Font family | trait panel | `style.fontFamily` | **No** | **Yes** | `style` | No |
| Font weight (block) | trait if set | `style.fontWeight` | **No** | **Yes** | `style` | `<strong>` separate |
| Bold/italic/underline | execCommand tags | `content` (+ orphan flags) | semantic tags | block weight/style if trait | `content` | flags orphan only |
| Strikethrough | execCommand | `content` | `<s>`/`<strike>` | — | `content` | No |
| Text color (block) | trait / RTE callback | `style.textColor` | **No** on `> div` | **Yes** | `style` | span if RTE partial |
| Text color (partial RTE) | span `color` | `content` + **also** trait today | span inline | **Yes** block default | both | **Dual-write preserved** |
| Highlight | hiliteColor / span bg | `content` (+ orphan `highlightColor`) | inline bg | block bg separate | `content` | trait orphan unused |
| Alignment | execCommand + canvas effect | `style.textAlignment` [G2B]; was `properties.align` | **No** inline after G2B | **Yes** | `style` | **Fixed** |
| Line height | trait panel | `style.lineHeight` | **No** | **Yes** | `style` | No |
| Letter spacing | trait panel | `style.letterSpacing` | **No** | **Yes** | `style` | No |
| Link color | renderCss + was inline on `<a>` | `style.linkColor` / form | **No** inline after G2B | `> div a` | `style` | **Fixed** |
| Link decoration | same | `style.linkDecoration` | **No** inline after G2B | `> div a` | `style` | **Fixed** |
| Background | trait | `style.backgroundColor` | **No** | **Yes** | `style` | No |
| Padding/margin | trait panel | device padding/margin | wrapper structural only | wrapper/`> div` | `style` | No |
| Border/radius/shadow | trait | style bag | **No** merchant on Text owner | **Yes** | `style` | No |
| Width/height | trait layout | `style` | wrapper structural | wrapper | `style` | No |

---

## 5. Text trait persistence matrix

| Trait | Canvas application | Model field | Export inline on merchant owner | renderCss | Correct after 5D/G2B |
|-------|-------------------|-------------|-----------------------------------|-----------|----------------------|
| padding | `getTextBlockContentStyle` | `paddingDesktop/Mobile` | **No** | **Yes** `> div` | **Yes** |
| margin | wrapper style | `marginDesktop/Mobile` | structural wrapper only | wrapper | **Yes** |
| border radius | content style | `cornerRadius*` | **No** | **Yes** | **Yes** |
| background | content style | `backgroundColor` | **No** | **Yes** | **Yes** |
| border | content style | `border*` | **No** | **Yes** | **Yes** |
| box shadow | content style | `boxShadow*` | **No** | **Yes** | **Yes** |
| font family | content style | `fontFamily` | **No** | **Yes** | **Yes** |
| font size | content style | `fontSize` | **No** | **Yes** | **Yes** |
| font weight | content style | `fontWeight` | **No** | **Yes** | **Yes** |
| font style | content style | `fontStyle` | **No** | **Yes** | **Yes** |
| text color | content style | `textColor` | **No** on `> div` | **Yes** | **Yes** |
| text alignment | content style + editor root | `textAlignment` | **No** after G2B | **Yes** | **Yes** |
| line height | content style | `lineHeight` | **No** | **Yes** | **Yes** |
| letter spacing | content style | `letterSpacing` | **No** | **Yes** | **Yes** |
| height | content style | `height` | **No** | **Yes** | **Yes** |
| width | wrapper | `elementWidth` etc. | structural wrapper | wrapper | **Yes** |

**Finding:** Phase 5D migration holds. No trait re-leaked as merchant inline on Text painted `> div` in export.

---

## 6. RTE persistence matrix

| RTE action | HTML mutation | Inline CSS | Trait mutation | renderCss overlap | Persistence defect |
|------------|---------------|------------|----------------|-------------------|-------------------|
| bold | `<strong>`/`<b>` | rare | orphan `bold` flag | `fontWeight` if trait | orphan flag only |
| italic | `<em>`/`<i>` | rare | orphan flag | `fontStyle` if trait | orphan flag only |
| underline | `<u>` | decoration | orphan flag | — | orphan flag only |
| strikethrough | `<s>` | — | — | — | No |
| text color | span `color` | **Yes** | **always** `textColor` | **Yes** | dual-write **preserved** |
| font size | span or trait path | partial | caret/full/mobile trait | **Yes** | **preserved** |
| highlight | span/bg | **Yes** | orphan `highlightColor` | block bg different | orphan trait unused |
| alignment | div `text-align` | was **Yes** | now `textAlignment` | **Yes** | **fixed** |
| link | `<a href>` | was color/decor | — | link descendant | **fixed** at export |
| lists | `<ul>/<ol>` | — | orphan flags | — | orphan flags only |
| sup/sub | `<sup>`/`<sub>` | — | — | — | No |
| paste | plain text | — | — | — | No |

---

## 7–10. Actual saved shape examples (via serializePopupExport tests)

### Plain text

- **content:** `<p>Plain</p>`
- **style:** `{ textColor, fontSize, textAlignment, … }`
- **HTML:** structure-only wrapper + `<div><p>Plain</p></div>`
- **renderCss:** `color`, `font-size`, `text-align` on `[artifact][block-id] > div`

### RTE partial color

- **content:** `<p>Hi <span style="color:rgb(255,0,0)">red</span></p>`
- **style:** `{ textColor: '#111111' }`
- **HTML:** span color preserved; no merchant CSS on `> div`
- **renderCss:** block `color:#111111` + span wins locally in browser

### Link (after G2B)

- **content:** `<p><a href="https://example.com">Shop</a></p>` (no inline on anchor)
- **style:** `{ linkColor: '#336699' }`
- **renderCss:** `[artifact][block-id] > div a { color:#336699; text-decoration:underline }`

### Alignment (after G2B)

- **content:** no `text-align` inline on div/p
- **style:** `{ textAlignment: 'center' }`
- **renderCss:** `text-align:center` on `> div`

---

## 11. Duplicate CSS ownership findings

| Duplicate | Canonical owner | Accidental source | Browser winner | Survives reload | G2B action |
|-----------|-----------------|-------------------|----------------|----------------|------------|
| Link color | renderCss `> div a` | `applyLinkStyles` in InlineEditor | inline (before fix) | content | Strip inline at save/export |
| Block text-align | `style.textAlignment` → renderCss | execCommand in content | inline descendant (before) | both | Strip inline; sync trait on RTE align |
| `properties.align` vs `textAlignment` | `style.textAlignment` | RTE `onAlignChange` orphan | renderCss ignored align | align orphan | Migrate align → trait; stop writing align |
| Block textColor + span color | both intentional today | RTE always updates trait | span wins partial | both | **Preserved** |
| transparent background on span | — | editor noise | — | content | Strip on normalize |

---

## 12. Editor/browser artifact findings

| Artifact | Proven | Action |
|----------|--------|--------|
| `background-color: transparent` on spans | Production adcarts example + unit test | Strip on normalize |
| Empty `style=""` after prune | DOM prune logic | Remove attribute |
| Orphan `properties.bold` / `isBold` mismatch | Code trace | **Not deleted** (semantics deferred) |
| `applyLinkStyles` during editing | Canvas only | Kept for live UX; stripped at persistence boundary |

---

## 13. Link persistence result

- **Structure/href:** `properties.content` — unchanged
- **Presentation:** renderCss descendant rules — canonical
- **Fix:** `normalizeTextRichContentHtml` removes inline `color` and `text-decoration` from `<a>`
- **Canvas:** `applyLinkStyles` still runs during editing for visible feedback
- **Browser test:** link computes `rgb(51, 102, 153)` from renderCss after export

---

## 14. Alignment persistence result

- **Before:** `properties.align` + inline content `text-align` + `style.textAlignment` (often divergent)
- **After:** `style.textAlignment` only in persisted model; no inline block `text-align` in saved content
- **RTE UX:** execCommand still runs (unchanged toolbar behavior); `TextCanvas.onAlignChange` now writes `style.textAlignment`
- **Cascade:** renderCss `text-align` on `> div` is authoritative in export/embed

---

## 15. Text color persistence result

- **Preserved:** RTE color still updates `style.textColor` via `onTextColorChange` even on partial selection (current behavior)
- **Preserved:** partial span `color` in content
- **Not changed:** dual-write on purpose this phase
- **Export:** block color in renderCss; partial span in content — valid cascade

---

## 16. Font-size persistence result

- **Preserved:** caret/full/mobile → trait; partial selection → inline span (`applyFontSize` logic unchanged)
- **Export:** block `font-size` in renderCss only on `> div`; partial spans remain in content
- **Not changed:** `isWholeEditorSelected` trait promotion

---

## 17. Save → Reload → Save stability results

| Case | Save 1 | Reload (normalized state) | Save 2 | Stable? |
|------|--------|---------------------------|--------|---------|
| Link + align leak + transparent span | normalized content + trait | same fields | identical export | **Yes** |
| Idempotence unit test | normalize twice | — | same HTML + renderCss | **Yes** |
| Nested span growth | not observed in normalize path | — | — | **No growth mechanism added** |

---

## 18. Production fixes implemented

| File | Change |
|------|--------|
| `utils/popupHtmlSerializer/textRichContentPersistence.ts` | **New** — narrow normalizer + snapshot block normalizer |
| `blocks/text/serialize.ts` | Apply normalizer after sanitize |
| `utils/popupHtmlSerializer/index.ts` | Normalize snapshot before export |
| `utils/popupBuilderState.ts` | Normalize text blocks in saved `builderState` |
| `blocks/text/TextCanvas.tsx` | RTE align writes `style.textAlignment` instead of orphan `properties.align` |

---

## 19. User-visible behavior changes

| Area | Changed? |
|------|----------|
| RTE toolbar commands | **No** — same handlers |
| Bold/italic/color/font-size selection behavior | **No** |
| Alignment toolbar | **No** — still execCommand; trait now synced |
| Storefront/embed link appearance | **No** — renderCss already owned presentation; inline was redundant |
| Storefront alignment when legacy inline-only | May align to trait after re-save (forward-only normalize) |

---

## 20–21. Inline styles remaining after fix (legitimate)

| Inline | Why legitimate |
|--------|----------------|
| `<span style="color:…">` | Partial RTE color formatting |
| `<span style="font-size:…">` | Partial RTE size formatting |
| `<span style="background-color:…">` (non-transparent) | Highlight |
| `<strong>`, `<em>`, `<u>`, `<s>`, lists, sup/sub | Semantic content formatting |
| Structural wrapper `flex-shrink:0` | Layout identity, not merchant presentation |

---

## 22. Obsolete code deleted

**None removed in G2B** — `applyLinkStyles` kept for canvas UX; orphan format flags deferred with semantics phase.

---

## 23. Browser computed-style evidence

| Test | Result |
|------|--------|
| `textCssPersistence.browser.test.ts` | Link color from renderCss after inline stripped |
| `textPresentation.browser.test.ts` | Phase 5D trait presentation — **pass** |
| `popupProductionCssRteOwnership.audit.browser.test.ts` | Partial color override; align leak removed |

---

## 24. Phase 0 scoreboard

G2B tests run: `textCssPersistence*`, `textPresentation*`, `popupProductionCssRteOwnership*` — **all pass**.

Full Phase 0 baseline (18 migrated + 5 controls) — **not re-run in this session**; no serializer/renderCss architecture changes that would regress Phase 5D Text migration.

---

## 25. Consumer parity

| Consumer | Path | G2B impact |
|----------|------|------------|
| Save/API | `serializePopupExport` + `buildPopupSaveMetadata` | Normalized content in metadata |
| Builder preview | same export path | Same |
| Thumbnails | same export path | Same |
| Storefront embed | saved htmlContent + renderCss | Cleaner content HTML |

---

## 26. Remaining CSS persistence debt

| Item | Priority |
|------|----------|
| Orphan format flags (`isBold`, list flags, `highlightColor` trait) | Low — cosmetic model noise |
| Text color dual-write (trait + span) | Medium — deferred to semantics phase |
| Font size dual-path | Medium — deferred |
| One-time migration for legacy popups with inline-only alignment | Ops — re-save |
| RTE Playwright harness for live toolbar (not static HTML) | Follow-up tests |

---

## 27. Deferred RTE semantic questions

See G2A.1: command ownership, selection policies, registry, paragraph alignment model, bold-as-trait vs content, caret color policy.

---

## 28. Recommended next action

1. **Deploy G2B** persistence fixes with regression tests in CI.
2. **Re-save** popups with known inline link/alignment leaks (optional batch).
3. **Resume** deferred **G2C semantics** phase when product ready — not block-family CSS migration prerequisite.
4. **Do not** start Dropdown or other block-family migration until stakeholder confirms G2B acceptance.

---

## Required final answer format

| # | Item | Answer |
|---|------|--------|
| 1 | RTE selection semantics changed | **NO** |
| 2 | Block-vs-selection architecture changed | **NO** |
| 3 | Caret semantics changed | **NO** |
| 4 | Command registry added | **NO** |
| 5 | Current user-visible RTE behavior preserved | **YES** |
| 6 | Complete CSS persistence pipeline traced | **YES** |
| 7 | Text trait persistence audited | **YES** |
| 8 | RTE persistence audited | **YES** |
| 9 | Duplicate CSS persistence found | Link inline presentation; block text-align in content; orphan `properties.align`; transparent background noise; (known) text color dual-write |
| 10 | Duplicate CSS persistence removed | Link inline; content block text-align; orphan align migration; transparent background |
| 11 | Block presentation canonical owner | `properties.style` → renderCss on wrapper + painted `> div` |
| 12 | Rich-content formatting canonical owner | `properties.content` (sanitized HTML) |
| 13 | Link structure owner | `properties.content` (`<a href>`) |
| 14 | Link presentation owner | renderCss descendant `[block-id] > div a` |
| 15 | Inline anchor presentation remaining | **NO** (in persisted/exported HTML) |
| 16 | Alignment persistence locations before | `properties.align` + inline content `text-align` + `style.textAlignment` |
| 17 | Alignment persistence locations after | `style.textAlignment` + renderCss only |
| 18 | Text color dual writes remaining | **YES** (preserved intentionally) |
| 19 | Font-size dual writes remaining | **YES** (preserved intentionally) |
| 20 | Save → Reload → Save stable | **YES** (for normalized cases tested) |
| 21 | Nested style/span growth present | **NO** (in normalize path) |
| 22 | Block merchant presentation inline in exported Text owner | **NO** |
| 23 | Legitimate RTE inline formatting preserved | **YES** |
| 24 | Generic CSS sanitizer added | **NO** (narrow normalizer only) |
| 25 | Backward compatibility branch added | **NO** |
| 26 | renderCss architecture changed | **NO** |
| 27 | G1 build architecture changed | **NO** |
| 28 | Button behavior changed | **NO** |
| 29 | Production fixes implemented | `textRichContentPersistence.ts`; serialize + export + save metadata; TextCanvas align trait sync |
| 30 | Obsolete code deleted | **None** (canvas link styling kept) |
| 31 | Remaining inline styles | Partial color/size spans; semantic tags; highlight; structural wrapper flex |
| 32 | Browser tests passing | **YES** (`textCssPersistence.browser.test.ts` + Phase 5D browser tests) |
| 33 | Phase 0 scoreboard | G2B-related suites green; full baseline not re-run this session |
| 34 | Consumer parity | Save, preview, embed share normalized export path |
| 35 | Remaining CSS persistence debt | Orphan format flags; color/size dual-write; legacy popup re-save |
| 36 | Deferred semantic decisions | G2A.1 full list |
| 37 | Verdict | **ACCEPT WITH CLEANUP** |
| 38 | Recommended next action | Deploy G2B; optional re-save migration; then G2C semantics when ready |
