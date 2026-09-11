# Phase G2B.2 — Forward-Only Text Persistence Legacy Cleanup

**Date:** 2026-07-06  
**Scope:** Delete proven-dead Text persistence fields and compatibility branches; retain active RTE behavior  
**References:** G2B report, G2B.1 closure audit, Phase 5D, G2A.1 semantics decision

---

## 1. Executive verdict

Phase G2B.2 applied the forward-only rule: legacy Text fields with **no current production writer** were removed from the active architecture. The `properties.align → style.textAlignment` migration branch was deleted. Orphan format flags (`isBold`, `isItalic`, `isUnderline`, `isBulletList`, `isOrderedList`, plus mistaken `bold`/`italic`/`underline` keys) were stripped on save/export and removed from `TextCanvas` reads/writes.

G2B persistence contracts, idempotence, mutation safety, Phase 0 baseline, and G1 production contract all remain green.

**Verdict: ACCEPT**

---

## 2. Forward-only assumption

```text
NO BACKWARD COMPATIBILITY REQUIRED
```

Old saved popups containing `properties.align` or orphan format flags are **not** migrated forward. Those fields are **stripped** on save/export. Alignment and formatting authority remains:

```text
style.textAlignment → renderCss
properties.content  → rich formatting (semantic tags + partial spans)
properties.style    → block presentation traits
```

---

## 3. Complete Text persistence field inventory

| Field | Type | Current writer | Current reader | Persisted? | Export effect | Classification |
|-------|------|----------------|----------------|------------|---------------|----------------|
| `properties.content` | HTML string | `TextCanvas` `onContentChange` | `TextCanvas`, serializer | **YES** | Rich content HTML | **CANONICAL CONTENT** |
| `properties.style.*` | trait object | trait panel, `TextCanvas` (align/color/size/highlight) | `TextCanvas`, renderCss compiler | **YES** | artifact-scoped renderCss | **CANONICAL MODEL** |
| `properties.style.textAlignment` | string | `TextCanvas.onAlignChange`, trait panel | `TextCanvas`, renderCss | **YES** | `text-align` in renderCss | **CANONICAL MODEL** |
| `properties.style.textColor` | string | trait panel, `onTextColorChange` | renderCss + canvas | **YES** | block default color in renderCss | **CANONICAL MODEL** (+ deferred dual-write) |
| `properties.style.fontSize` | string | trait panel, font-size path | renderCss + canvas | **YES** | block font-size in renderCss | **CANONICAL MODEL** (+ deferred dual-path) |
| `properties.style.highlightColor` | string | `onHighlightColorChange` | canvas | **YES** | canvas/highlight UX | **CANONICAL MODEL** |
| `properties.style.linkColor` | string | trait panel | renderCss | **YES** | link presentation in renderCss | **CANONICAL MODEL** |
| `properties.align` | string | **NONE** (Text) | **NONE** (stripped on normalize) | **NO** | none | **DELETED — LEGACY** |
| `properties.isBold` etc. | boolean | **NONE** (removed from TextCanvas) | **NONE** | **NO** | none | **DELETED — DEAD** |
| `properties.bold/italic/underline` | boolean | **NONE** (was mistaken `onFormatChange` key) | **NONE** | **NO** | none | **DELETED — DEAD** |
| `properties.isBulletList/isOrderedList` | boolean | **NONE** (removed from TextCanvas) | **NONE** | **NO** | none | **DELETED — DEAD** |
| `<strong>/<em>/<u>/<s>` in content | HTML | RTE execCommand | serializer | **YES** | exported content | **CANONICAL CONTENT** |
| partial span color/font-size | inline HTML | RTE | serializer (preserved) | **YES** | content-local override | **CANONICAL CONTENT** |

Button block `properties.align` — **unchanged** (different block family).

---

## 4. `properties.align` read/write trace (Text only)

| Question | Answer |
|----------|--------|
| Current Text editor writes `properties.align`? | **NO** — `TextCanvas` writes `style.textAlignment` since G2B |
| Text trait control writes it? | **NO** |
| RTE alignment command writes it? | **NO** — `onAlignChange` → `style.textAlignment` |
| TextCanvas reads it? | **NO** (removed in G2B.2) |
| Serializer reads it? | **NO** — stripped in `normalizeTextBlockForPersistence` |
| renderCss compiler reads it? | **NO** — reads `style.textAlignment` |
| Save requires it? | **NO** |
| Reload/hydration requires it? | **NO** |
| Present only in old shapes/tests? | Legacy tests/fixtures updated |
| Migration branch deletable? | **YES — DELETED** |

---

## 5. Current-production-write evidence

**Default creation:** `getDefaultPropertiesForType('text')` → `{ content: "" }`

**After simulated current editor flow** (`textPersistenceForwardOnly.g2b2.test.ts`):

| Action | properties.content | properties.style | Other fields |
|--------|-------------------|-------------------|--------------|
| create | `""` | defaults from trait panel / canvas | none |
| type plain text | HTML updated | unchanged | none |
| bold/italic/underline | `<strong>`, `<em>`, `<u>` tags | unchanged | none |
| alignment | unchanged | `textAlignment` updated | none |
| text color | partial spans + trait | `textColor` updated | none |
| font size | partial spans + trait | `fontSize` updated | none |
| highlight | span background in content | `highlightColor` updated | none |
| save | normalized content | traits preserved | **no align, no format flags** |

---

## 6. Format flag inventory

| Flag | Writers (Text) | Readers (Text) | Visual effect | Persistence effect | Verdict |
|------|----------------|----------------|---------------|---------------------|---------|
| `isBold` | **NONE** | was TextCanvas → InlineEditor init only | toolbar init only; DOM queryCommandState owns runtime | none on export | **DELETED** |
| `isItalic` | **NONE** | same | same | none | **DELETED** |
| `isUnderline` | **NONE** | same | same | none | **DELETED** |
| `isBulletList` | **NONE** | same | lists via execCommand → content | none | **DELETED** |
| `isOrderedList` | **NONE** | same | same | none | **DELETED** |
| `bold/italic/underline` | was mistaken `onFormatChange` keys | none | none | junk on live state if ever written | **STRIPPED on save** |

**Note:** `InlineEditor`/`BalloonEditor` `isBold` props are **component UI props**, not persisted model fields. Button block uses `style.fontWeight`/`fontStyle` — separate family.

---

## 7. Duplicate representation matrix

| Concern | properties.content | properties.style | orphan flag | canvas DOM | renderCss | Classification |
|---------|---------------------|------------------|-------------|------------|-----------|----------------|
| alignment | inline block text-align stripped | `textAlignment` | ~~align~~ deleted | editor textAlign UX | `text-align` | **CANONICAL** |
| bold/italic/underline/strike | semantic tags | — | ~~flags~~ deleted | execCommand | — | **CANONICAL CONTENT** |
| text color | partial spans | `textColor` | — | execCommand + trait | block default | **DEFERRED SEMANTICS** |
| font size | partial spans | `fontSize` | — | execCommand + trait | block default | **DEFERRED SEMANTICS** |
| link color | structure only | `linkColor` | — | applyLinkStyles canvas UX | descendant `a` rule | **CANONICAL** |
| highlight | span background | `highlightColor` trait | — | execCommand | — | **CANONICAL CONTENT** |

---

## 8. Compatibility branch inventory

| Compatibility path | Current writer? | Behavior dependency? | Historical-only? | Action |
|--------------------|-----------------|----------------------|--------------------|--------|
| `orphanAlign → style.textAlignment` in normalizer | N/A | **NO** | **YES** | **DELETED** |
| orphan align wins over textAlignment | N/A | **NO** | **YES** | **DELETED** |
| TextCanvas reads `properties.isBold` etc. | **NO** | **NO** (DOM command state) | **YES** | **DELETED** |
| TextCanvas `onFormatChange` → `{bold:…}` | **NO** (removed) | **NO** | dead junk writer | **DELETED** |
| TextCanvas `onListChange` → list flags | **NO** (removed) | **NO** (execCommand) | **YES** | **DELETED** |
| Inline block text-align strip | editor can produce | **YES** | active defect cleanup | **RETAINED** |
| anchor presentation strip | editor can produce | **YES** | active defect cleanup | **RETAINED** |

---

## 9. Model/type/default audit

- `FormElement.properties` remains `Record<string, any>` — no typed legacy fields existed for Text flags.
- Text defaults: `{ content: "" }` only — already canonical.
- Template fixtures updated: `popupTemplateSnapshots.ts` text blocks use `style.textAlignment` not `align`.
- Test fixtures updated across G2B/G2B.1/G2B.2 suites.

---

## 10. Fields deleted

**From active Text persistence model (save/export strip + no writers):**

```text
properties.align
properties.isBold
properties.isItalic
properties.isUnderline
properties.isBulletList
properties.isOrderedList
properties.bold
properties.italic
properties.underline
```

---

## 11. Fields retained

| Field | Reason |
|-------|--------|
| `properties.content` | Canonical rich formatting |
| `properties.style.*` | Canonical block presentation traits |
| `style.textAlignment` | Canonical alignment |
| `style.textColor` / partial span color | Active + deferred G2C semantics |
| `style.fontSize` / partial span size | Active + deferred G2C semantics |
| `style.linkColor` | Link presentation in renderCss |
| `style.highlightColor` | Active highlight trait |
| Button `properties.align` | Different block family — out of scope |

---

## 12. Exact reason for every retained non-canonical field

| Field / behavior | Why retained |
|------------------|--------------|
| Text-color dual-write (span + trait) | **DEFERRED G2C** — explicit project constraint; active user-visible behavior |
| Font-size dual-path | **DEFERRED G2C** — same |
| `InlineEditor.applyLinkStyles` | **CANVAS UX** — stripped at export; not persistence |
| InlineEditor `isBold` props | **COMPONENT UI** — toolbar state from `queryCommandState`, not model |

---

## 13. Production code deleted

| File | Change |
|------|--------|
| `textRichContentPersistence.ts` | Removed `orphanAlign → textAlignment` migration; added `STRIPPED_TEXT_ORPHAN_PROPERTIES` strip list |
| `TextCanvas.tsx` | Removed reads of format flags; removed `onFormatChange` and `onListChange` dead writers |

---

## 14. Tests/fixtures deleted or rewritten

| File | Change |
|------|--------|
| `textCssPersistence.test.ts` | Migration test → strip-without-migrate test |
| `textCssPersistence.g2b1Closure.test.ts` | Removed orphan-align lifecycle/conflict cases |
| `popupProductionCssRteOwnership.audit.test.ts` | Migration expectation → strip-without-migrate |
| `popupHtmlSerializer.test.ts` | Text fixture uses `style.textAlignment` |
| `popupTemplateSnapshots.ts` | Text template uses `style.textAlignment` |
| **New:** `textPersistenceForwardOnly.g2b2.test.ts` | Forward-only write path + strip evidence |

---

## 15. G2B persistence contract after cleanup

Unchanged:

```text
Block presentation     → properties.style → renderCss
Rich formatting        → properties.content
Link structure         → properties.content
Link presentation      → renderCss
Alignment              → style.textAlignment → renderCss
```

Legacy fields no longer participate.

---

## 16. Idempotence result

**PASS** — `textCssPersistence*`, `textPersistenceForwardOnly.g2b2`, G2B.1 closure suite (212 Text/RTE tests).

---

## 17. Mutation-safety result

**PASS** — `normalizePopupSnapshotTextBlocks` does not mutate input; legacy fields remain on input object until save uses returned normalized snapshot.

---

## 18. Save/reload stability result

**PASS** — 16-case lifecycle matrix (G2B.1) + G2B.2 save/reload alignment test.

---

## 19. Phase 0 scoreboard

**Command:** `npm run test:popup-responsive-baseline`  
**Result:** 18 migrated green / **0 red** / 5 controls green

---

## 20. G1 production contract result

**Command:** `npm run test:export-css-production-contract`  
**Result:** **PASS**

---

## 21. Repository search evidence

**Text `properties.align` production references (excluding tests, Button block, strip constant):**

```text
0
```

**Text orphan format flag production references (excluding tests, strip constant, shared RTE UI components):**

```text
0 in TextCanvas / Text serializers
```

**Legacy alignment migration helper:**

```text
0 — migration branch deleted
```

**Backward compatibility Text persistence branch:**

```text
0
```

Strip-on-save in `textRichContentPersistence.ts` is **hygiene**, not migration.

---

## 22. Remaining semantic debt

- Text-color dual-write (partial span + `style.textColor`)
- Font-size dual-path (partial span vs trait promotion)
- G2C command registry + selection normalization (G2A.1 Model C)

---

## 23. Remaining CSS architecture debt

- Button block `properties.align` (separate family)
- Canvas transient inline styles until save normalize
- Orphan format flags may exist in **old DB records** until next save (stripped then, not migrated)

---

## 24. Final verdict

**ACCEPT**

Forward-only Text persistence cleanup complete. No RTE behavior change. No new compatibility layer.

---

## 25. Recommended next action

Proceed to **Phase G2C** (RTE command semantics per G2A.1 Model C) as a separate scoped phase. Do not combine with Button `properties.align` cleanup or other block-family migrations.
