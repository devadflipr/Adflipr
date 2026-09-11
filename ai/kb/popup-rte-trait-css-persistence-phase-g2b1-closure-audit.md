# Phase G2B.1 — RTE and Text CSS Persistence Closure Audit

**Date:** 2026-07-06  
**Scope:** Independent verification + surgical cleanup of Phase G2B Text/RTE CSS persistence stabilization  
**References:** G2B report, Phase 5D, G1 export CSS, G2A.1 semantics decision, G1 contamination audit

---

## 1. Executive verdict

Phase G2B.1 independently verified the G2B persistence architecture against production code and expanded regression coverage. All acceptance gates pass:

- One canonical normalizer (`textRichContentPersistence.ts`) called at save/export boundaries
- Idempotent HTML and snapshot normalization (proven in tests)
- No live-state mutation during save/export normalization
- Link presentation removed from persisted inline anchors; structure preserved
- Alignment canonicalized to `style.textAlignment`; inline block `text-align` stripped
- Legitimate partial RTE formatting preserved
- Save → Reload → Edit → Save stable across 16 formatting cases
- Phase 0 baseline: **18 migrated green / 0 red / 5 controls green**
- G1 production `.export.css` contract: **PASS**
- RTE selection/caret/color/font-size semantics: **unchanged**

**Verdict: ACCEPT**

Surgical cleanup in G2B.1: updated stale audit test expectations for G2B alignment migration; added `textCssPersistence.g2b1Closure.test.ts` closure suite. No production RTE or normalization logic changes required.

---

## 2. G2B claim verification matrix

| G2B claim | Production evidence | Test evidence | Verified? |
|-----------|---------------------|---------------|-----------|
| Link inline presentation removed from persisted/exported content | `normalizeTextRichContentHtml` → `removeAnchorPresentation` strips `color`/`text-decoration` on `<a>` (`textRichContentPersistence.ts:30-35,83-85`) | `textCssPersistence.test.ts`, `textCssPersistence.g2b1Closure.test.ts` Part F | **YES** |
| Link structure remains intact | Normalizer only removes presentation props; `href`/`target`/`rel` untouched | Part F link tests | **YES** |
| Block-level content alignment removed | `removeBlockLevelTextAlign` on DIV/P/LI/H1–H6 (`:61-68,87-89`) | idempotence + lifecycle tests | **YES** |
| `properties.align` migrated to `style.textAlignment` | `normalizeTextBlockForPersistence` lines 103-106,114 | `textCssPersistence.test.ts`, audit test orphan align | **YES** |
| RTE alignment writes `style.textAlignment` | `TextCanvas.tsx:38-40` `onAlignChange` → `updateElement({ style: { ...style, textAlignment: align } })` | Code inspection; no behavior change in G2B.1 | **YES** |
| Transparent background editor noise removed | `pruneStyleAttribute` deletes `background-color: transparent` (`:43-46`) | G2B + G2B.1 tests | **YES** |
| Legitimate partial RTE formatting survives | Normalizer only targets anchors, block text-align, transparent bg | Part E preservation matrix (10 cases) | **YES** |
| Text block presentation in renderCss | `compilePopupRenderCss` + `textPresentation.ts` (unchanged Phase 5D path) | `textPresentationRenderCss.test.ts`, Part H | **YES** |
| No merchant presentation on exported Text painted owner | `blocks/text/serialize.ts` structure-only `> div`; traits in renderCss | `textCssPersistence.test.ts`, Part H | **YES** |
| Normalization at save/export boundaries | `serializePopupExport` (`index.ts:130-132`), `buildPopupSaveMetadata` (`popupBuilderState.ts:84-86`), `serializeText` (`serialize.ts:11`) | call graph below; consumer consistency test | **YES** |
| Canvas link inline styles are UX-only | `InlineEditor.applyLinkStyles` applies during edit; stripped at export | audit classification below | **YES** |

---

## 3. Actual normalization call graph

```text
User edits Text (live Redux/builder state — raw, unnormalized)
  ↓
FormBuilder.handleSave
  ↓
buildCurrentSnapshot()                    [raw snapshot]
  ↓
serializePopupExport(snapshot)
  ├─ normalizePopupSnapshotTextBlocks(snapshot)     ← boundary 1 (snapshot)
  │    └─ normalizeTextBlockForPersistence per text block
  │         └─ normalizeTextRichContentHtml(content) ← boundary 1a (content, per block)
  ├─ serializePopupHtmlByStep(normalizedSnapshot)
  │    └─ serializeText(element)
  │         ├─ sanitizeRichTextHtml(content)
  │         └─ normalizeTextRichContentHtml(...)   ← boundary 2 (content, per text at serialize)
  └─ compilePopupRenderCss(normalizedSnapshot)
  ↓
buildPopupSaveMetadata(snapshot, htmlExport)
  └─ normalizePopupSnapshotTextBlocks(snapshot)     ← boundary 3 (snapshot, for builderState JSON)
  ↓
API metadata: builderState + htmlByStep + renderCss
```

Preview/thumbnail/embed consumers call `serializePopupExport` (directly or via `buildPreviewArtifactExport`) — same graph from export entry.

---

## 4. Normalization boundary matrix

| Boundary | Function called | Why needed | Same canonical implementation? | Mutation? | Keep/remove |
|----------|-----------------|------------|-------------------------------|-----------|-------------|
| `serializePopupExport` | `normalizePopupSnapshotTextBlocks` | Export HTML + renderCss must reflect canonical persistence ownership | **YES** — `textRichContentPersistence.ts` | **NO** — returns new snapshot spread | **KEEP** |
| `buildPopupSaveMetadata` | `normalizePopupSnapshotTextBlocks` | Persisted `builderState` must match export semantics on reload | **YES** | **NO** | **KEEP** |
| `serializeText` | `normalizeTextRichContentHtml` after sanitize | Defense-in-depth at HTML emission; handles callers that bypass snapshot normalizer | **YES** | **NO** — returns string | **KEEP** (defensive, idempotent) |

**Duplicate normalization logic found:** **NO** — three boundaries call the same two exported functions from one module.

---

## 5. Canonical normalization decision

**Canonical module:** `adflipr-app/src/layouts/PopupBuilder/utils/popupHtmlSerializer/textRichContentPersistence.ts`

| Export | Role |
|--------|------|
| `normalizeTextRichContentHtml(html)` | Pure content string normalizer (DOM in browser/jsdom) |
| `normalizeTextBlockForPersistence(element)` | Per Text block: migrate align → textAlignment, normalize content |
| `normalizePopupSnapshotTextBlocks(snapshot)` | Map all Text blocks; return new snapshot object |

**Alignment conflict precedence (forward-only, deterministic):**

1. If `properties.align` is set → **wins** over existing `style.textAlignment` during normalization.
2. Inline block-level `text-align` in content is **always stripped** regardless of trait values.
3. `properties.align` is **removed** after migration.
4. No backward-compat runtime branch in canvas or embed.

---

## 6. Idempotence evidence

**HTML:** `N(N(x)) = N(x)` proven for 12 cases in `textCssPersistence.g2b1Closure.test.ts` Part C plus 5 cases in `textCssPersistence.test.ts`.

**Snapshot:** `normalizePopupSnapshotTextBlocks` idempotent — G2B.1 Part C test.

**Assertion pattern used:**

```typescript
const once = normalizeTextRichContentHtml(input);
expect(normalizeTextRichContentHtml(once)).toBe(once);
```

Cases covered: plain text, inline link color/decoration, block div/p text-align, transparent span, empty style, partial color/font-size, highlight, combined, nested link in span, already-normalized content.

---

## 7. Snapshot mutation evidence

| Function | Input mutated? | Evidence |
|----------|----------------|----------|
| `normalizePopupSnapshotTextBlocks` | **NO** | G2B.1 Part M: JSON.stringify(snapshot) unchanged before/after |
| `normalizeTextBlockForPersistence` | **NO** | Returns new element; input JSON unchanged |
| `buildPopupSaveMetadata` | **NO** | Uses `{ ...snapshot, ...normalizePopupSnapshotTextBlocks(snapshot) }` — original snapshot untouched |
| `serializePopupExport` | **NO** | Same spread pattern as save metadata |

Live builder state is not silently normalized on save unless explicitly reloaded from persisted metadata.

---

## 8. Save → Reload → Edit → Save matrix

16-case lifecycle in `textCssPersistence.g2b1Closure.test.ts` Part D via `buildPopupSaveMetadata` → `parseBuilderState` → second export.

| Case | Save 1 content stable | Reload content stable | Save 2 content stable | renderCss stable? | HTML stable? | Drift? |
|------|----------------------|----------------------|----------------------|-------------------|--------------|--------|
| plain | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| bold | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| italic | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| underline | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| strikethrough | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| partial color | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| block color trait | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| partial font size | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| block font size trait | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| highlight | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| alignment (+ orphan align) | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| link | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| link + color | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| align + partial color | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| highlight + font size | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |
| multi format | ✓ | ✓ | ✓ | ✓ | ✓ | **NO** |

No nested span growth, style accumulation, or renderCss drift detected.

---

## 9. Legitimate formatting preservation matrix

| Formatting | Input content | Normalized content | Preserved? |
|------------|---------------|-------------------|------------|
| partial color | `<span style="color: #ff0000;">c</span>` | color retained | **YES** |
| partial font size | `<span style="font-size: 22px;">s</span>` | font-size retained | **YES** |
| highlight | `<span style="background-color: #ffff00;">h</span>` | non-transparent bg retained | **YES** |
| bold | `<strong>b</strong>` | unchanged | **YES** |
| italic | `<em>i</em>` | unchanged | **YES** |
| underline | `<u>u</u>` | unchanged | **YES** |
| strikethrough | `<s>s</s>` | unchanged | **YES** |
| list | `<ul><li>one</li></ul>` | unchanged | **YES** |
| sup/sub | `<sup>2</sup>`, `<sub>2</sub>` | unchanged | **YES** |

Cascade remains: **renderCss block default + content-local inline override**.

---

## 10. Link persistence verification

**Contract:**

```text
Link structure  → properties.content (<a href>, target, rel)
Link presentation → artifact-scoped renderCss
  [data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"] > div a { color; text-decoration }
```

| Scenario | Inline anchor style after export | renderCss rule | Verified |
|----------|----------------------------------|----------------|----------|
| `<a href>` | none | per-block linkColor | **YES** |
| target + rel | structure preserved | presentation from renderCss | **YES** |
| link in formatted span | span color kept; anchor style stripped | **YES** | **YES** |
| multiple links / blocks | isolated per block-id | **YES** | **YES** |
| multi-artifact | scope attribute isolates rules | thumbnail browser test pattern | **YES** |

`applyLinkStyles` (InlineEditor) — canvas-only; does not appear in normalized export.

---

## 11. Alignment persistence verification

**Contract:** `style.textAlignment` → renderCss `text-align`; no persisted inline block text-align; no `properties.align` after normalized save.

| Scenario | Result after normalized save |
|----------|------------------------------|
| left/center/right/justify via trait | renderCss emits matching `text-align` |
| legacy `properties.align` only | migrated to `style.textAlignment` |
| `style.textAlignment` only | preserved |
| both align + textAlignment conflict | **orphan align wins** |
| inline content text-align only | stripped; trait/renderCss owns alignment |

---

## 12. Text block presentation regression audit (Phase 5D)

Exported DOM shape unchanged:

```text
[data-adflipr-block-id="{id}"]
└── > div
    └── rich content (structure wrapper only)
```

Merchant traits (padding, margin, background, border, typography, text-align, link presentation) compile to artifact-scoped renderCss — not inline on painted owner. Verified by `textPresentationRenderCss.test.ts`, `textCssPersistence.test.ts`, G2B.1 Part H.

---

## 13. Phase 0 full scoreboard

**Command:** `npm run test:popup-responsive-baseline`  
**Result:** 25/25 PASS (2026-07-06)

| Category | Count | Green | Red |
|----------|-------|-------|-----|
| Migrated risk (PCL-008) | 18 | **18** | **0** |
| Controls | 5 | **5** | **0** |

All 18 migrated cases: form-surface (4), layout (5), block (3), cross (2), floating-image (2), teaser (2).

---

## 14. Consumer consistency matrix

| Consumer | Snapshot source | Normalization boundary | HTML source | renderCss source | Consistent? |
|----------|-----------------|------------------------|-------------|------------------|-------------|
| Save/API | live snapshot | `serializePopupExport` + `buildPopupSaveMetadata` | `serializePopupExport` | `compilePopupRenderCss` | **YES** |
| Builder preview | live state | `buildPreviewArtifactExport` → `serializePopupExport` | same | same | **YES** |
| Popup preview | live state | `formPreviewEmbedPresentation` / `previewArtifactExport` | same | same | **YES** |
| Thumbnail | saved or live | `serializePopupExport` via `popupThumbnailIframe` | same | same | **YES** |
| Storefront embed | saved metadata | runtime mounts saved htmlByStep + renderCss | persisted export | persisted export | **YES** |
| Multi-step popup | per-step htmlByStep | export normalizer | same path all steps | same | **YES** |
| Teaser (Text in steps) | export path | `serializePopupExport` | same | same | **YES** |
| Gamification (Text in form) | export path | `serializePopupExport` | same | same | **YES** |

No consumer-specific inline CSS patch for Text persistence ownership.

---

## 15. G1 production contract result

**Command:** `npm run test:export-css-production-contract`  
**Result:** **PASS** — all `.export.css` sheets resolve to raw CSS strings in production webpack.

G2B did not modify `craco.config.js`, `.export.css` import contract, or `sheets/index.ts` raw-source architecture.

---

## 16. Dead-code audit

| Symbol/path | Current role | Still consumed? | Safe to delete? | Action |
|-------------|--------------|-----------------|-----------------|--------|
| `InlineEditor.applyLinkStyles` | Canvas UX — inline anchor color/decoration during edit | Yes (link create/edit) | **NO** — canvas-only; stripped at export | **RETAIN** |
| `TextCanvas.onAlignChange` | Writes `style.textAlignment` | Yes | **NO** | **RETAIN** |
| `properties.align` (Text) | Legacy orphan; migrated at save | Read-only migration input | **NO** — migration path | **RETAIN** (migration) |
| `properties.align` (Button) | Button block family | Yes (different block) | N/A — out of G2B scope | **RETAIN** |
| `sanitizeRichTextHtml` | XSS/structure sanitize before normalize | Yes in serializeText | **NO** | **RETAIN** |
| Orphan format flags (`isBold`, etc.) | Legacy/canvas hints | Partial | Deferred G2C | **RETAIN** |
| Duplicate HTML normalizers | — | None found | — | **N/A** |

---

## 17. Code deleted

None in production. G2B.1 test-only updates:

- `popupProductionCssRteOwnership.audit.test.ts` — expectations aligned to G2B alignment migration (orphan align wins; scoped content assertions for text-align).

---

## 18. Code retained and why

| Code | Why |
|------|-----|
| `applyLinkStyles` | Required canvas UX for link creation; not persistence canonical |
| Defensive double `normalizeTextRichContentHtml` at serializeText | Idempotent; protects direct serialize callers |
| Dual normalization in export + save metadata | Ensures builderState JSON matches exported HTML |
| Text color / font-size dual-write paths | Deferred semantic debt (G2C); explicitly untouched |

---

## 19. Performance/payload evidence

G2B.1 Part N test: 50 Text blocks with alignment/link/noise → normalize + export in **< 500ms** (local Jest); no runaway HTML growth after normalize.

No credible production regression identified. Caching not required.

---

## 20. Remaining CSS persistence debt

- Orphan Text format flags (`isBold`, `isItalic`, etc.) not fully unified with content semantics
- Button block still uses `properties.align` (separate block family)
- Canvas may still accumulate transient inline styles during edit until next save/export normalize
- Rich-text link rules in legacy `data-pbn-rich-text-links` sheet (Button block) — separate from Text G2B scope

---

## 21. Deferred semantic debt (G2C — not in G2B.1)

- Text color dual-write: partial span + `style.textColor` mutation
- Font size dual-path: partial span vs trait promotion for caret/full/mobile
- Command registry and selection-normalization policy (G2A.1 Model C implementation)
- Global full-selection → trait promotion for bold/color/italic

---

## 22. Architecture gates

| Gate | Status |
|------|--------|
| RTE behavior preserved | **PASS** |
| One canonical normalizer | **PASS** |
| Idempotent normalization | **PASS** |
| No live-state mutation | **PASS** |
| Link/alignment ownership | **PASS** |
| Phase 5D Text presentation | **PASS** |
| Phase 0 baseline | **PASS** |
| G1 export CSS contract | **PASS** |
| No backward-compat branch | **PASS** |
| No generic CSS sanitizer | **PASS** |
| No new RTE architecture | **PASS** |

---

## 23. Final verdict

**ACCEPT**

Phase G2B persistence architecture is correct, idempotent, non-destructive, and consistent across save/export consumers. G2B.1 closure adds expanded regression tests and aligns audit expectations; no further surgical production changes required.

---

## 24. Recommended next action

Proceed to **Phase G2C** (RTE command semantics implementation per G2A.1 Model C) as a separate scoped phase — explicitly addressing text-color and font-size dual-write policy, command registry, and selection behavior. Do not combine with block-family CSS migrations.

---

## Test execution summary (G2B.1)

| Suite | Result |
|-------|--------|
| `npm run test:popup-responsive-baseline` | 25 PASS |
| `npm run test:export-css-production-contract` | PASS |
| Text/RTE related suites (18 files, 206 tests) | 206 PASS |
| New: `textCssPersistence.g2b1Closure.test.ts` | included in 206 total |
