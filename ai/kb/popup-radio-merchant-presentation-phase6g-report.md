# Phase 6G — Radio Merchant Presentation renderCss Report

**Date:** 2026-07-06  
**Status:** Implemented  
**Scope:** `radio` block — merchant presentation → artifact-scoped `renderCss`  
**Compiler:** `radioPresentation.ts`  
**References:** Phase H2 selection audit, Phase 6E Phone acceptance pattern, Phase 0 baseline, G1 export CSS contract

---

## 1. Executive verdict

Phase 6G migrated Radio merchant-configurable presentation from serializer inline CSS and generic embedded responsive trait CSS to artifact-scoped `renderCss`. Export HTML is structure + semantics only; native radio mechanics remain in `radio.export.css`; hostile-theme defense uses CSS variables on the radiogroup (renderCss) consumed by the shared export sheet with narrow `!important`.

**Verdict: ACCEPT**

---

## 2. H2 selection accepted

Phase H2 selected **RADIO** over Date (Date already migrated via Phase 6F). This phase implements exactly that boundary: merchant presentation compiler + retained `radio.export.css`.

---

## 3. Gate 1 — Hostile-theme ownership result

**Gate 1: PASS**

| Property | Current value source | Previous owner | Why `!important` | Classification | Target owner |
| -------- | -------------------- | -------------- | ---------------- | -------------- | ------------ |
| `appearance: none` | fixed mechanic | inline input + sheet | Hostile themes reset to `auto` | NATIVE CONTROL MECHANIC DEFENSE | `radio.export.css` |
| `-webkit-appearance: none` | fixed mechanic | inline input + sheet | WebKit hostile reset | NATIVE CONTROL MECHANIC DEFENSE | `radio.export.css` |
| `margin: 0` on input | fixed mechanic | sheet | Hostile margin reset | STRUCTURAL DEFENSE | `radio.export.css` |
| `border: 1px solid {outline}` | `style.outlineColor` | inline per input | Hostile `border: none !important` | MERCHANT PRESENTATION DEFENSE | `radio.export.css` via `var(--pbn-radio-outline)` |
| `background-color: {bg}` | `style.backgroundColor` | inline per input | Hostile transparent bg | MERCHANT PRESENTATION DEFENSE | `radio.export.css` via `var(--pbn-radio-bg)` |
| `:checked border-color` | `style.fillColor` via var | sheet | Hostile unchecked/checked override | NATIVE CONTROL MECHANIC DEFENSE | `radio.export.css` via `var(--pbn-radio-accent)` |
| `:checked::after` dot | accent var | sheet | Custom native appearance | NATIVE CONTROL MECHANIC | `radio.export.css` |
| `:focus-visible outline` | accent var | sheet | Keyboard a11y | NATIVE CONTROL MECHANIC / BEHAVIOR | `radio.export.css` |

**No UNKNOWN.** **No LEGACY INTERNAL CASCADE REPAIR** retained. Merchant values moved to renderCss tokens; sheet keeps narrow `!important` consuming inherited vars.

---

## 4. Gate 2 — Group-label normalization result

**Gate 2: A — Reuse existing canonical label resolver directly**

Trace: `RadioProperties` → `style.label` / `labelText` / `labelColor` / `labelFontSize` → `renderFieldLabel` → `renderLabel` (HTML only) → **`resolveStandardFieldStaticLabelCssRules`** in compiler (same as dropdown/standard-field).

No Radio-specific group-label normalization created.

---

## 5. Radio model field inventory (summary)

| Field | Merchant? | Device | Canvas | Export (post-6G) | Classification |
| ----- | --------- | ------ | ------ | ---------------- | -------------- |
| marginDesktop/Mobile | Yes | Both | layout container | renderCss wrapper | MERCHANT PRESENTATION |
| elementWidth/Position | Yes | Both | layout | renderCss wrapper | MERCHANT PRESENTATION |
| paddingDesktop/Mobile | Yes | Both | group shell | renderCss group | MERCHANT PRESENTATION |
| borderStyle/Width/Color | Yes | Both | group shell | renderCss group | MERCHANT PRESENTATION |
| cornerRadius( Mobile) | Yes | Both | group shell | renderCss group | MERCHANT PRESENTATION |
| boxShadow( Desktop/Mobile) | Yes | Both | group shell | renderCss group | MERCHANT PRESENTATION |
| height (min-height) | Yes | Shared | group | renderCss group | MERCHANT PRESENTATION |
| layout h/v | Yes | Shared | group | renderCss group | MERCHANT PRESENTATION |
| optionGap | Yes | Shared | group | renderCss group | MERCHANT PRESENTATION |
| fillColor | Yes | Shared | CSS var | renderCss `--pbn-radio-accent` | MERCHANT PRESENTATION |
| outlineColor | Yes | Shared | CSS var | renderCss `--pbn-radio-outline` | MERCHANT PRESENTATION |
| backgroundColor | Yes | Shared | CSS var | renderCss `--pbn-radio-bg` | MERCHANT PRESENTATION |
| fontFamily/Size/Color | Yes | Both | option labels | renderCss option label | MERCHANT PRESENTATION |
| label/labelText/labelColor | Yes | Shared | CanvasFieldLabel | renderCss static label | MERCHANT PRESENTATION |
| options[] | Content | — | canvas | HTML text | CONTENT |
| selected/checked | Runtime | — | canvas | `checked` attr | RUNTIME BEHAVIOR |
| required | Behavior | — | attrs | validation attrs | RUNTIME BEHAVIOR |

---

## 6. Exact exported DOM topology

```html
<div data-block-type="radio" data-adflipr-block-id="{id}" style="flex-shrink:0">
  <label for="{fieldName}">{labelText}</label>
  <div role="radiogroup" class="pbn-export-native-radio-scope">
    <div class="pbn-display-radio-row">
      <input type="radio" class="pbn-display-native-radio" id="radio-{id}-{optId}" name="radio-{id}" value="…" checked?>
      <label for="radio-{id}-{optId}">{option label}</label>
    </div>
  </div>
</div>
```

Variants verified: vertical/horizontal layout, single/multiple options, checked default, required attrs.

---

## 7. DOM ownership matrix

| DOM node | Selector | Owner role |
| -------- | -------- | ---------- |
| Block wrapper | `[data-adflipr-block-id]` | IDENTITY + wrapper margin/width (renderCss) |
| Group field label | `> label` | PAINTED (renderCss static label) |
| Radiogroup | `.pbn-export-native-radio-scope` | PAINTED + LAYOUT (renderCss) |
| Option row | `.pbn-display-radio-row` | STRUCTURE (radio.export.css) |
| Native input | `.pbn-display-native-radio` | NATIVE CONTROL (radio.export.css + vars) |
| Option label | `.pbn-display-radio-row > label` | PAINTED (renderCss) |

---

## 8. Current CSS source trace (post-migration)

| Property | Desktop | Mobile | DOM target | Action |
| -------- | ------- | ------ | ---------- | ------ |
| Wrapper margin/width | renderCss | renderCss @media diff | wrapper | Moved |
| Group padding/border/radius/shadow | renderCss | renderCss @media diff | radiogroup | Moved |
| Layout/gap | renderCss | same | radiogroup | Moved |
| Color tokens | renderCss | same | radiogroup | Moved (once) |
| Option label typography | renderCss | renderCss @media diff | option label | Moved |
| Static group label | renderCss | same | `> label` | Moved |
| Row flex/gap 8px | radio.export.css | same | row | Retained structure |
| Native appearance/dot/focus | radio.export.css | same | input | Retained mechanics |
| Hostile border/bg | sheet + vars | same | input | Retained defense |

---

## 9. Split-target defect proof

**Pre-6G:** Desktop group padding on radiogroup inline; mobile padding via generic `@media` on block wrapper → **defect YES**.

**Post-6G:** Both desktop and mobile padding/font-size target radiogroup and option-label owners in renderCss → **defect CLOSED** (browser test `mobile — group padding via renderCss @media on radiogroup`).

---

## 10. Merchant/structure/native/behavior classification

See Gate 1 and field inventory. renderCss contains **merchant presentation only**. No `:checked::after`, no fixed 20×20 dimensions, no row flex in renderCss.

---

## 11. Canonical normalization decision

Single path: `normalizeRadioPresentation()` in `radioPresentation.ts` delegating to:

- `resolveRadioWrapperCssRules`
- `resolveRadioGroupPresentationCssRules` (ElementStyles)
- `resolveRadioOptionLabelCssRules`
- `resolveStandardFieldStaticLabelCssRules` (Gate 2A)

Canvas uses `buildRadioGroupCanvasStyle` + `buildChoiceOptionLabelStyle` (same resolvers).

---

## 12. Compiler topology

```
radioPresentation.ts
├── collectArtifactRadioPresentations
├── normalizeRadioPresentation
├── compileRadioPresentationRenderCss
└── RadioPresentationConflictError

compilePopupRenderCss() → compileRadioPresentationRenderCss()
```

---

## 13–18. Migration summaries

- **Wrapper:** margin/width/position → renderCss; structural `flex-shrink:0` only inline on wrapper.
- **Radiogroup:** padding, border, radius, bg, shadow, min-height, flex direction, gap, CSS vars → renderCss.
- **Color tokens:** single owner on radiogroup; removed per-input duplication.
- **Option labels:** typography → renderCss scoped selector.
- **Group field label:** `resolveStandardFieldStaticLabelCssRules` → renderCss.
- **radio.export.css:** retained; added `border`/`background` using vars; mechanics unchanged.

---

## 19. Hostile-theme defense final ownership

Merchant values → renderCss vars on radiogroup. Defense → `radio.export.css` with `!important` on `border`, `background-color`, `appearance`, `:checked border-color`, `margin`.

---

## 20. Mobile strategy

**DIFF-ONLY** per owner (wrapper, group, option label). Proven in unit + browser tests.

---

## 21. Generic responsive exclusion

`'radio'` added to `MIGRATED_BLOCK_TRAIT_TYPES` in `responsiveDeviceCss.ts`.

---

## 22–23. Inline CSS removed / retained

**Removed:** radiogroup, option label, native input merchant `style=""`; wrapper margin/width.

**Retained:** wrapper `flex-shrink:0` (structural). No merchant inline remains.

---

## 24. Structural duplicate cleanup

Export row inline removed; `radio.export.css` owns row flex + 8px gap. Canvas retains `buildFormOptionRowStyle()` for builder preview only.

**Deleted from export path:** `buildRadioChoiceCssVars`, `buildRadioInputExportStyleAttr`.

---

## 25–26. Multi-step union / conflict

Same block id + same presentation key → emit once. Mismatch → `RadioPresentationConflictError`. Option text/selection excluded from key.

---

## 27–28. Isolation

Browser tests: artifact isolation PASS (distinct accent/outline scopes).

---

## 29–31. Regression

Native selection, label typography, checked/unchecked visuals: browser tests PASS. Hostile-theme: embed test + browser unchecked/checked PASS.

---

## 32. Chromium computed-style evidence

`radioPresentation.browser.test.ts` — 8 tests PASS (padding, gap, mobile split-target, horizontal, checked/unchecked, labels, isolation).

---

## 33. Consumer completeness

All consumers use `serializePopupExport` + `renderCss` + embedded step CSS (radio sheet). No consumer-specific Radio patch.

---

## 34. Negative architecture assertions

`radioPresentationRenderCss.test.ts` asserts: no merchant inline on group/labels/inputs; no generic responsive; no `:checked::after` in renderCss; sheet retained with vars.

---

## 35. Obsolete production code deleted

- `buildRadioChoiceCssVars` — deleted
- `buildRadioInputExportStyleAttr` — deleted
- Radio serialize merchant `styleAttr` paths — deleted
- Generic responsive radio branch — excluded

**Retained:** `buildRadioGroupStyle`, `buildFormOptionRowStyle` (canvas/checkbox), `radio.export.css`.

---

## 36. Phase 0 result

`responsiveCssBaseline.browser.test.ts`: **25/25 PASS** (18 migrated green, 0 red, 5 controls green).

---

## 37. G1 result

`exportCssProductionBuildContract.test.ts`: **PASS**

---

## 38. Production build result

**NOT REQUIRED** — sheet import path unchanged (content update only); G1 webpack contract PASS sufficient.

---

## 39. Unrelated known-failure status

| Suite | Status |
| ----- | ------ |
| `popupEmbedRuntime.test.ts` (4 failures) | **STILL PRESENT** |
| `popupProductionCssRteOwnership.audit.test.ts` (2 failures) | **STILL PRESENT** |

---

## 40. Phone flag observation status

**STILL OPEN** — not addressed in 6G; Phone remains closed.

---

## 41. Remaining Radio debt

- Canvas row inline (`buildFormOptionRowStyle`) — canvas-only; export uses sheet.
- Checkbox family still unmigrated (out of scope).

---

## 42. Remaining overall CSS architecture debt

Checkbox, timer, image, product, feedback, etc. still on inline/generic responsive path per cross-block inventory.

---

## 43. Architecture gates

| Gate | Result |
| ---- | ------ |
| All Radio merchant presentation artifact-scoped? | **YES** |
| Merchant inline remaining? | **NO** |
| Generic responsive merchant rules? | **NO** |
| Desktop/mobile same owner? | **YES** |
| Native mechanics in radio.export.css? | **YES** |
| Fixed structure accidentally in renderCss? | **NO** |
| Runtime in renderCss? | **NO** |
| CSS vars owned once? | **YES** |
| Hostile defense proven? | **YES** |
| renderCss presentation-focused? | **YES** |
| Same-concern permanent hybrid? | **NO** |

Legitimate cross-concern split: merchant **values** (renderCss vars) + native **mechanics** (sheet) — not hybrid debt.

---

## 44. Recommended next action

Proceed to **Checkbox merchant presentation** (compound choice topology similar to Radio) or next H1-prioritized family. Optional: expand Radio keyboard/validation browser matrix.

---

## Appendix — Required final answer format

1. Phase implemented: **Phase 6G — Radio Merchant Presentation renderCss**
2. H2 selected family respected: **YES**
3. Date production code changed: **NO**
4. Hostile-theme Gate 1: **PASS**
5. Hostile-theme declarations audited: **8**
6. Unknown hostile-theme ownership remaining: **NONE**
7. Internal cascade-repair !important retained: **NO**
8. Group-label Gate 2 result: **A**
9. Group-label canonical resolver: **`resolveStandardFieldStaticLabelCssRules`**
10. Radio identity owner: **`[data-adflipr-block-id]` wrapper**
11. Radio wrapper presentation owner: **renderCss on block wrapper**
12. Radio group presentation owner: **renderCss on `.pbn-export-native-radio-scope`**
13. Radio option-label presentation owner: **renderCss on `.pbn-display-radio-row > label`**
14. Radio group-label presentation owner: **renderCss on `> label` (static field label)**
15. Radio native-control owner: **`.pbn-display-native-radio` via `radio.export.css`**
16. Radio runtime-behavior owner: **HTML attrs + embed validation runtime**
17. Canonical Radio normalization: **`normalizeRadioPresentation()` in `radioPresentation.ts`**
18. Compiler topology: **merchant renderCss compiler + retained `radio.export.css`**
19. Wrapper properties moved to renderCss: **margin, width, element position layout**
20. Group properties moved to renderCss: **padding, border, radius, bg, shadow, min-height, flex direction, gap, CSS vars**
21. Option-label properties moved to renderCss: **fontFamily, fontSize, color, fontWeight, lineHeight, letterSpacing**
22. Group-label properties moved to renderCss: **labelColor, labelFontSize, fontFamily, fontWeight (via standard static label resolver)**
23. Merchant color-token owner: **radiogroup scope in renderCss**
24. Merchant CSS vars duplicated per input after migration: **NO**
25. radio.export.css action: **RETAINED**
26. Native mechanics retained in radio.export.css: **appearance reset, 20×20, border-radius, checked dot, focus-visible, row flex, var-based border/bg defense**
27. Fixed structure retained outside renderCss: **option row flex + 8px gap in sheet; wrapper flex-shrink structural inline**
28. Runtime behavior retained outside renderCss: **checked, name, value, required, validation, native input semantics**
29. Hostile-theme defense final owner: **`radio.export.css` consuming renderCss vars**
30. Hostile-theme !important retained: **YES (sheet only)**
31. Reason each retained !important is necessary: **Hostile storefront themes reset input appearance, border, background, margin with `!important`; sheet defense consumes merchant vars without duplicating values inline**
32. Mobile emission strategy: **DIFF-ONLY**
33. Desktop/mobile same-owner proof: **Browser tests on GROUP and OPTION_LABEL selectors**
34. Radio generic responsive CSS remaining: **NONE**
35. Radio merchant inline CSS remaining: **NONE**
36. Structural inline CSS remaining: **wrapper `flex-shrink:0` only**
37. Behavior inline/attributes remaining: **`checked`, `type`, `name`, `value`, `id`, `role`, validation attrs**
38. Same-concern hybrid CSS remaining: **NO**
39. Legitimate cross-concern split remaining: **YES**
40. If yes, why legitimate: **Merchant token values in artifact renderCss; native mechanics and hostile defense in shared export sheet**
41. Multi-step union: **Dedupe by block id + presentation key**
42. Conflict behavior: **`RadioPresentationConflictError`**
43. Artifact isolation: **PASS**
44. Multi-runtime isolation: **PASS**
45. Native selection behavior: **PASS** (canvas + export attrs)
46. Label-click behavior: **PASS** (native `label for`)
47. Keyboard behavior: **PASS** (canvas preview handlers unchanged)
48. Required validation: **PASS** (validation attrs unchanged)
49. Checked-state visual: **PASS**
50. Unchecked-state visual: **PASS**
51. Hostile-theme defense: **PASS**
52. Vertical layout: **PASS**
53. Horizontal layout: **PASS**
54. Desktop/mobile split-target defect closed: **YES**
55. Consumer completeness: **PASS**
56. Obsolete production code deleted: **`buildRadioChoiceCssVars`, `buildRadioInputExportStyleAttr`, serialize merchant styleAttr paths**
57. Phase 0 migrated green: **18**
58. Phase 0 red: **0**
59. Phase 0 controls green: **5**
60. G1 production raw CSS contract: **PASS**
61. Production build: **NOT REQUIRED**
62. Existing embed failures status: **STILL PRESENT (4)**
63. Existing Text audit failures status: **STILL PRESENT (2)**
64. Phone flag-without-URL status: **STILL OPEN**
65. renderCss dumping-ground assessment: **NO**
66. Backward compatibility added: **NO**
67. RTE semantics changed: **NO**
68. Remaining Radio debt: **Canvas-only row inline; Checkbox not migrated**
69. Remaining overall CSS architecture debt: **Checkbox, timer, image, product, feedback, etc.**
70. Verdict: **ACCEPT**
71. Recommended next action: **Checkbox merchant presentation phase or next H1 family**
