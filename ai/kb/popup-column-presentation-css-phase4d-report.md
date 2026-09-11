# Popup CSS architecture — Phase 4D implementation report

**Status:** Implemented (2026-07-05)  
**Type:** Forward-only cleanup — remove Phases 2B–4B transitional architecture  
**Related:** [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md)

---

## 1. Gate decisions

### Gate 1 — Gamification

| Decision | **A — Standard owner compilers + dedicated gamification compiler** |
| -------- | ------------------------------------------------------------------ |
| Evidence | Gamification DOM is unique (spinner/form columns, flex shell) but inner layouts/columns/column presentation are identical to standard popups. `compileFormSurfaceRenderCss` already excludes `[data-adflipr-popup-surface="gamification"]`. |
| Consequence | Added `gamificationPresentation.ts` → `compileGamificationRenderCss()`. Removed `shouldCompileStandardSurfaceRenderCss` gamification rejection. Gamification popups now receive full `renderCss` (standard owners + gamification shell). |

**Gamification concern matrix**

| Concern | Owner | CSS source (after 4D) | Existing compiler? | Dedicated? |
| ------- | ----- | --------------------- | ------------------ | ---------- |
| Outer gamification shell (bg, border, flex row) | Gamification surface | `compileGamificationRenderCss` | No | Yes |
| Spinner column layout | Gamification spinner slot | same | No | Yes |
| Form column layout | Gamification form slot | same | No | Yes |
| Mobile stack | Gamification responsive | same (was embedded) | No | Yes |
| Layouts inside form column | Outer layout | `compileOuterLayoutRenderCss` | Yes | No |
| Columns container | Columns container | `compileColumnsContainerRenderCss` | Yes | No |
| Column presentation | Column | `compileColumnPresentationRenderCss` | Yes | No |

### Gate 2 — Embedded CSS organization

| Decision | **Explicit owner generators composed in `buildEmbeddedStepResponsiveCss()`** |
| -------- | ---------------------------------------------------------------------------- |
| Evidence | Remaining owners are unrelated (blocks, cross, product, phone, visibility). A single “unmigrated dump” would hide ownership. |
| Consequence | Deleted migrated chunks from embedded builder. Renamed `buildResponsiveDeviceCss` → `buildEmbeddedStepResponsiveCss`. Each remaining function keeps its semantic name and is composed explicitly. |

### Gate 3 — Compiled artifact contract

| Decision | **B — `CompiledPopupArtifact` with mandatory fields; no separate mega-type churn** |
| -------- | ----------------------------------------------------------------------------------- |
| Evidence | `htmlByStep`, `primaryStepId`, `htmlContent`, `renderCss`, and `artifactScopeId` always travel together on save/preview/embed. |
| Consequence | `CompiledPopupArtifact` in `popupCompiledArtifact.ts`. `PopupHtmlExport` is a type alias. `serializePopupExport` throws without `artifactScopeId`. `renderCss` is always `string` (may be `''`). |

### Gate 4 — Domain normalization sharing

| Decision | **C — Keep proven sharing; no forced column canvas/compiler merge** |
| -------- | ------------------------------------------------------------------- |
| Evidence | Form surface and outer layout already share `buildStandardFormSurfacePresentationStyle` / `buildSectionLayoutStyle`. Column compiler uses `normalizeColumnPresentation()`; canvas uses `buildColumnLayoutStyle()` — merging would create ElementStyles ↔ columnPresentation circular imports. |
| Consequence | Added `artifactCssUtils.ts` for shared CSS emission mechanics only. Column domain normalization remains compiler-canonical per Phase 4B. |

---

## 2. Before architecture

```text
artifactScopeId present && !gamification
    → useStandardSurfaceRenderCss=true → renderCss + skip flags
artifactScopeId absent OR gamification
    → legacy inline + full embedded responsiveDeviceCss
```

## 3. After architecture

```text
serializePopupExport(snapshot, { artifactScopeId })  // required
    ↓
HTML — structure + identity only (migrated owners)
    ↓
compilePopupRenderCss()
    ├── compileFormSurfaceRenderCss
    ├── compileOuterLayoutRenderCss
    ├── compileColumnsContainerRenderCss
    ├── compileColumnPresentationRenderCss
    └── compileGamificationRenderCss (when spinner present)
    ↓
Embedded step CSS — buildEmbeddedStepResponsiveCss() (unmigrated owners only)
    ↓
Runtime — mountRenderArtifactCss + hoistExportedStepStyles(unmigrated)
```

---

## 4. Gamification result

Gamification no longer disables artifact compilation. HTML emits identity attrs only; presentation lives in `renderCss` via `compileGamificationRenderCss` plus standard inner layout/column compilers.

---

## 5. Artifact contract result

```typescript
interface CompiledPopupArtifact {
  artifactScopeId: string;
  htmlContent: string;
  htmlByStep: Record<string, string>;
  primaryStepId: string;
  renderCss: string;
}
```

---

## 6. Remaining embedded CSS owners

`hoistExportedStepStyles()` exists because of:

1. **Block device visibility** — `buildBlockDeviceVisibilityCss`
2. **Block trait responsive** — `buildBlockTraitResponsiveCss`
3. **Cross / floating image responsive margins** — `buildCrossFloatingImageResponsiveCss`
4. **Phone input country select** — `buildPhoneInputCountrySelectResponsiveCss`
5. **Product horizontal / grid responsive** — `buildProductHorizontalResponsiveCss`, `buildProductGridResponsiveCss`
6. **Floating label sheet** — `POPUP_FLOATING_LABEL_EXPORT_CSS`
7. **Embed overflow guards** — `resolveEmbedResponsiveExportCss`
8. **Entrance animation** — `POPUP_ENTRANCE_EXPORT_CSS`
9. **Radio controls** — `POPUP_RADIO_EXPORT_CSS`
10. **Rich text link color** — `buildRichTextLinkExportCss`
11. **Custom CSS** — user `formStyle.customCss`
12. **Teaser** — separate teaser export path (outside form scope)

No migrated owner appears in this list.

---

## 7. Deleted production code

| Category | Items |
| -------- | ----- |
| Flags | `useStandardSurfaceRenderCss`, `skipStandardFormSurfaceResponsive`, `skipOuterLayoutSectionResponsive`, `skipColumnsContainerResponsive` |
| Functions | `shouldCompileStandardSurfaceRenderCss`, `buildEmbeddedFormSurfaceStyle`, `buildFormSurfaceResponsiveCss`, `buildLayoutSectionResponsiveCss`, `buildLayoutResponsiveCss`, `buildGamificationResponsiveCss` (embedded), `buildResponsiveDeviceCss` (replaced) |
| Branches | Legacy inline layout/column/columns-container paths in `serializeLayout.ts` |
| Types | Optional `renderCss?` on `PopupHtmlExport`; optional `artifactScopeId` on serialize options |

---

## 8. Refactored domain normalization

| Shared | Location |
| ------ | -------- |
| Form surface presentation | `buildStandardFormSurfacePresentationStyle` — canvas + `compileFormSurfaceRenderCss` |
| Outer layout presentation | `buildSectionLayoutStyle` — canvas + `compileOuterLayoutRenderCss` |
| CSS emission utilities | `artifactCssUtils.ts` — escape, mobile wrap, chunk join |
| Gamification shell | `gamificationPresentation.ts` — new dedicated owner |
| RenderCss composition | `compilePopupRenderCss.ts` |

---

## 9. Tests deleted / rewritten

Removed legacy tests for: unscoped export, HTML-only artifacts, gamification legacy inline, embedded migrated-owner rules.

---

## 10. Tests added

- `phase4dArchitecture.test.ts` — mandatory scope, mandatory renderCss, negative migrated inline/embedded assertions, gamification renderCss

---

## 11. Repository search evidence

Post-implementation grep for transitional symbols:

```text
useStandardSurfaceRenderCss          → 0
skipStandardFormSurfaceResponsive    → 0
skipOuterLayoutSectionResponsive     → 0
skipColumnsContainerResponsive       → 0
shouldCompileStandardSurfaceRenderCss → 0
buildEmbeddedFormSurfaceStyle        → 0
buildResponsiveDeviceCss             → 0 (replaced by buildEmbeddedStepResponsiveCss)
```

Optional `renderCss` remains on **embed API load types** (`PopupEmbedConfig`) for API payload parsing only — production save always emits `renderCss`.

---

## 12. Code delta

```text
Production + tests (PopupBuilder + KB header): ~301 insertions, ~556 deletions (net −255)
New files: artifactCssUtils.ts, gamificationPresentation.ts, compilePopupRenderCss.ts, exportTestScope.ts, phase4dArchitecture.test.ts
```

---

## 13. KB cleanup

| Document | Action |
| -------- | ------ |
| `popup-rendering-css-architecture.md` | Updated — implemented forward-only path |
| `popup-column-presentation-css-phase4d-report.md` | Implementation report |
| `popup-column-presentation-css-phase4d-report.md` | This document |
| `ai/kb/README.md` | Index updated |

---

## 14. Remaining CSS architecture debt

| Owner | Status |
| ----- | ------ |
| Block traits | Embedded responsive |
| Cross / floating image | Embedded + inline positioning |
| Product blocks | Embedded responsive |
| Phone input | Embedded responsive |
| Teaser | Separate serializer |
| Custom CSS | Embedded |
| Block visibility | Embedded |

---

## 15. Next recommended owner

**Phase 5 — Block trait presentation → renderCss** (`buildBlockTraitResponsiveCss` + block inline styles).

---

## P. Test evidence

```bash
cd adflipr-app
npx craco test --watchAll=false --testPathPattern="columnsContainer|outerLayout|formSurface|columnPresentation|responsiveCssBaseline|phase4bPayload|phase4dArchitecture|popupHtmlSerializer|popupCompiledArtifact|popupThumbnail|renderArtifactCss|responsiveDeviceCss|popupBuilderState|formPreviewEmbed"
```

**Result:** 21 suites, **253 tests passed** (2026-07-05).
