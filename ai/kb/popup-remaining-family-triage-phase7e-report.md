# Phase 7E — Remaining Merchant-CSS Family Triage + Social Migration Report

**Date:** 2026-07-06  
**Branch:** `css-reft`  
**HEAD:** `dd82518704408a204ee36889986789683eec43b2`  
**Verdict:** **ACCEPT**

---

## 1. Executive verdict

Phase 7E completed narrow triage of the five remaining merchant-CSS families (`timer`, `spinner`, `product`, `feedback`, `social`), selected **social** as the safest complete migration, and migrated it fully from serializer inline merchant CSS + `buildBlockTraitResponsiveCss` to artifact-scoped `renderCss` via `socialPresentation.ts`. Image and video remained fully migrated. Four families remain unmigrated. All regression gates pass.

---

## 2. Starting baseline

| Check | Result |
|---|---|
| Image compiler active | YES — `compileImagePresentationRenderCss` in `compilePopupRenderCss.ts` |
| Video compiler active | YES — `compileVideoPresentationRenderCss` |
| Image merchant inline CSS absent | YES — structure-only `blocks/image/serialize.ts` |
| Video merchant inline CSS absent | YES — structure-only `blocks/video/serialize.ts` |
| Image generic responsive absent | YES — in `MIGRATED_BLOCK_TRAIT_TYPES` |
| Video generic responsive absent | YES |
| timer inline + generic + no compiler | YES |
| spinner inline + generic + no compiler | YES |
| product inline + generic + no compiler | YES |
| feedback inline + generic + no compiler | YES |
| social inline + generic + no compiler | YES (pre-migration) |

**Phase 7E starting baseline valid: YES**

---

## 3. Timer evidence card

| Field | Evidence |
|---|---|
| Exact serialized DOM | `[data-block-type=timer]` wrapper → container → `[data-timer-units]` row → per-unit digit/label spans + separator spans; card/text variants |
| Identity owner | `[data-adflipr-block-id]` wrapper |
| Block-layout owner | Wrapper (margin/width via layout) + container height/min-width |
| Painted owner(s) | Container, digit spans, label spans, separator spans, card boxes |
| Behavior owner(s) | `data-countdown-to`, unit visibility attrs, embed `timerFit.ts` ResizeObserver |
| Runtime mutation | **YES** — embed updates digit text; `timerFit` scales font/gap inline |
| Inline merchant CSS surface | Heavy — container, digits, labels, separators, cards (`timer/serialize.ts`) |
| Generic responsive CSS surface | padding, margin, radius, shadow on wrapper |
| Normalization maturity | Partial — `timerFixedWidthFit.ts`, `ElementStyles` padding helpers |
| Mobile ownership risk | HIGH — fit scaling + generic responsive split |
| Migration risk | **HIGH** |

Critical: Timer runtime mutates descendant presentation (font-size, gap) via `timerFit`; not content-only.

---

## 4. Spinner evidence card

| Field | Evidence |
|---|---|
| Exact serialized DOM | Outer center → root → canvas host → `<canvas>` + result overlay/card |
| Identity owner | `[data-adflipr-block-id]` + `data-adflipr-popup-spinner` root |
| Block-layout owner | Outer center flex wrapper |
| Painted owner(s) | Root border/background, result card typography |
| Behavior owner(s) | Canvas wheel animation (`embed/spinner.ts`), spin API, email gate |
| Runtime mutation | **YES** — canvas draw loop; result overlay show/hide |
| Inline merchant CSS surface | Fixed constants in `spinnerStyles` + serialize |
| Generic responsive CSS surface | Block wrapper traits |
| Normalization maturity | Low — constants split across inline helpers |
| Mobile ownership risk | MEDIUM |
| Migration risk | **HIGH** |

Critical: Animation mechanics (canvas rotation) ≠ merchant presentation; must remain outside renderCss.

---

## 5. Product evidence card

| Field | Evidence |
|---|---|
| Exact serialized DOM | Shell grid → per-slot cards → image, body, name, price spans, button |
| Identity owner | Block wrapper + `data-adflipr-product-*` markers |
| Block-layout owner | Wrapper + product shell grid |
| Painted owner(s) | **Multiple** — image, title, sale/regular price, button, card shell |
| Behavior owner(s) | Product data binding, button links |
| Runtime mutation | Content replacement from product API (data) |
| Inline merchant CSS surface | Very heavy — `product/serialize.ts` (~400 lines) |
| Generic responsive CSS surface | Wrapper + shell column override |
| Normalization maturity | Partial — `ElementStyles` product button padding |
| Mobile ownership risk | HIGH — per-descendant + shell grid |
| Migration risk | **HIGH** |

Critical: Compound mini-layout with several independent merchant-painted owners — not one family surface.

---

## 6. Feedback evidence card

| Field | Evidence |
|---|---|
| Exact serialized DOM | `[data-block-type=feedback]` container → question `<p>` → radiogroup → option labels/inputs → optional labels row |
| Identity owner | Feedback container div |
| Block-layout owner | Container padding/margin |
| Painted owner(s) | Container, question, option labels, NPS radio accent |
| Behavior owner(s) | Radio inputs, selection/submit handlers |
| Runtime mutation | Class/state on selection (hover/selected) |
| Inline merchant CSS surface | `mediaAndFeedbackStyles` builders |
| Generic responsive CSS surface | Wrapper traits |
| Normalization maturity | Medium — shared feedback style builders |
| Mobile ownership risk | MEDIUM |
| Migration risk | **MEDIUM** |

Critical: State styling (active/inactive colors) must stay outside merchant renderCss.

---

## 7. Social evidence card

| Field | Evidence |
|---|---|
| Exact serialized DOM | `.pbn-social-container` → `<a class=pbn-social-link>` → `<img class=pbn-social-icon>` per platform |
| Identity owner | `[data-adflipr-block-id]` wrapper |
| Block-layout owner | Wrapper margin/width |
| Painted owner(s) | Container (flex/padding/gaps/alignment), icon (size/radius) |
| Behavior owner(s) | Link href/target; canvas `pointerEvents` guard |
| Runtime mutation | **NONE** — no embed initializer |
| Inline merchant CSS surface | Container + link + img (pre-migration) |
| Generic responsive CSS surface | padding/margin (mis-targeted to wrapper while margin was on container) |
| Normalization maturity | **High** — canvas/serializer duplicated identical logic |
| Mobile ownership risk | LOW (fixed by wrapper margin ownership) |
| Migration risk | **LOW** |

Critical: Presentation is block-level + shared per-item icon rules; content is URLs/src only.

---

## 8. Comparative ranking matrix

| Family | DOM certainty | Owner count | Runtime mutation risk | Behavior coupling | Responsive complexity | Normalization maturity | Estimated migration risk |
| -------- | ------------- | ----------: | --------------------- | ----------------- | --------------------- | ---------------------- | ------------------------ |
| timer | MEDIUM | HIGH (6+) | HIGH | HIGH | HIGH | MEDIUM | HIGH |
| spinner | MEDIUM | MEDIUM | HIGH | HIGH | MEDIUM | LOW | HIGH |
| product | MEDIUM | HIGH (5+) | MEDIUM | MEDIUM | HIGH | MEDIUM | HIGH |
| feedback | MEDIUM | MEDIUM | MEDIUM | HIGH | MEDIUM | MEDIUM | MEDIUM |
| social | **HIGH** | **LOW (3)** | **LOW** | **LOW** | **LOW** | **HIGH** | **LOW** |

---

## 9. Final ranking

1. **social**
2. feedback
3. spinner
4. product
5. timer

---

## 10. Selected family

**social**

---

## 11. Selection rationale

Social offers stable DOM, no embed runtime, three clear presentation owners (wrapper, container, icon), duplicated canvas/serializer logic ready for canonical normalization, content-independent presentation keys, and strong computed-style testability. All merchant traits have panel writers.

---

## 12. Why rank #2 was not selected

**Feedback** couples merchant presentation with interactive state (`activeColor`/`inactiveColor` on radio inputs, hover/selected). Separating state mechanics without behavior regression requires more careful ownership partitioning than social's static icon row.

---

## 13. Selected-family trait inventory

| Trait | UI writer | Persisted field | Canvas consumer | Desktop owner | Mobile owner | Classification |
|---|---|---|---|---|---|---|
| elementPosition | SocialProperties PositionWidthSection | `style.elementPosition` | container justifyContent | container | container | MERCHANT PRESENTATION |
| iconStyle | SocialProperties Select | `style.iconStyle` | icon borderRadius + src variant | icon | icon | MERCHANT PRESENTATION |
| imageWidth | SocialProperties PbnDimensionNumberInput | `style.imageWidth` | icon width/height | icon | icon | MERCHANT PRESENTATION |
| imageGap | SocialProperties | `style.imageGap` | container columnGap | container | container | MERCHANT PRESENTATION |
| lineSpacing | SocialProperties | `style.lineSpacing` | container rowGap | container | container | MERCHANT PRESENTATION |
| paddingDesktop/Mobile | DeviceSpacingSection | `style.paddingDesktop/Mobile` | container padding | container | container | MERCHANT PRESENTATION |
| marginDesktop/Mobile | DeviceSpacingSection | `style.marginDesktop/Mobile` | wrapper margin | wrapper | wrapper | MERCHANT PRESENTATION |
| socialLinks | SocialProperties Content tab | `properties.socialLinks` | link href + img src | — | — | CONTENT / DATA |
| hideOnDesktop/Mobile | Advanced switches | `style.hideOnDesktop/Mobile` | device visibility | — | — | BEHAVIOR |
| customClass | Advanced Input | `style.customClass` | className hook | — | — | ORPHAN (not renderCss) |
| customCss | Advanced Textarea | `style.customCss` | — | — | — | LEGACY (deferred) |

---

## 14. UI-writer evidence

Verified in `SocialProperties.tsx`: Content tab writes `socialLinks`; Style tab writes `elementPosition`, `iconStyle`, `imageWidth`, `imageGap`, `lineSpacing`, `paddingDesktop/Mobile`, `marginDesktop/Mobile`; Advanced writes visibility + customClass/customCss.

---

## 15. Exact DOM contract

| DOM node | Stable selector | Role | Current CSS source |
|---|---|---|---|
| Block wrapper | `[data-adflipr-block-id]` | IDENTITY + BLOCK LAYOUT | renderCss wrapper rules |
| Container | `[data-adflipr-social=true]` | SHELL PRESENTATION | renderCss container rules |
| Link | `.pbn-social-link` | BEHAVIOR + STRUCTURE | `social.export.css` |
| Icon | `.pbn-social-icon` | PAINTED CONTENT | renderCss icon rules |

**Selected family has stable migratable DOM ownership: YES**

---

## 16. Runtime behavior trace

| Question | Answer |
|---|---|
| Replaces merchant-painted owners? | NO |
| Mutates merchant inline styles? | NO |
| Depends on serialized style attributes? | NO |
| Adds/removes classes? | NO |
| Mutates content only? | N/A (no runtime) |
| Owns state-specific presentation? | NO |

Runtime effects: **BEHAVIOR** only (link navigation; canvas `pointerEvents`).

**Runtime compatible with migration: YES**

---

## 17. Ownership matrix

| Concern | Canonical owner | Reason |
|---|---|---|
| identity | `[data-adflipr-block-id]` | Artifact scoping |
| block margin | wrapper | Matches layoutStyles contract |
| element width | wrapper | getExportWrapperStyleFromLayout |
| padding | container | Inner flex shell |
| alignment (justify) | container | elementPosition trait |
| gaps | container | imageGap / lineSpacing |
| icon dimensions | `.pbn-social-icon` | imageWidth trait |
| icon radius | `.pbn-social-icon` | iconStyle trait |
| link structure | `.pbn-social-link` | Structural export sheet |
| interactive state | canvas pointerEvents | Behavior, not presentation |

---

## 18. Existing normalization inventory

| Concern | Canvas normalizer | Serializer normalizer | Mobile normalizer | Shared today? |
|---|---|---|---|---|
| padding | resolveDevicePaddingCss | resolveDevicePaddingCss | paddingMobile | YES (pre-migration) |
| margin | resolveDeviceMarginCss (container — wrong) | same | marginMobile | YES but wrong owner |
| gaps/align | inline duplicate | inline duplicate | none | NO → fixed |
| icon size/radius | inline duplicate | inline duplicate | none | NO → fixed |

---

## 19. Canonical normalization

Added to `ElementStyles.ts`:

- `resolveSocialIconBorderRadius`
- `resolveSocialContainerPresentationCssRules`
- `resolveSocialIconPresentationCssRules`

Compiler: `socialPresentation.ts` — `normalizeSocialPresentation`, `resolveSocialWrapperCssRules`.

---

## 20. Presentation/structure/behavior/state classification

| Declaration | Classification | Destination |
|---|---|---|
| flex layout, gaps, padding, margin, icon size/radius | MERCHANT PRESENTATION | renderCss |
| link display/line-height | REUSABLE STRUCTURE | `social.export.css` |
| icon max-width | RESPONSIVE SAFETY | `social.export.css` |
| href, target, rel | BEHAVIOR | HTML attributes |
| pointerEvents (canvas) | BEHAVIOR | canvas inline (builder only) |
| socialLinks URLs | CONTENT / DATA | HTML attributes |

---

## 21. Structural sheet decision

**YES** — `social.export.css` for link collapse and icon max-width safety.

---

## 22. Compiler implementation

`compileSocialPresentationRenderCss` in `socialPresentation.ts` — artifact-scoped selectors, wrapper/container/icon owners, DIFF-ONLY mobile for wrapper + container.

---

## 23. Multi-step union

Same `blockId` + same normalized presentation → emit once. Verified in unit test.

---

## 24. Conflict behavior

`SocialPresentationConflictError` thrown on conflicting presentation across steps. PASS.

---

## 25. Deterministic output

Identical input → identical CSS string. PASS.

---

## 26. Mobile ownership audit

| Trait | Desktop owner | Prior mobile owner | Future owner | Unset behavior |
|---|---|---|---|---|
| padding | container | generic → wrapper (wrong) | container | mobile inherits desktop padding via diff-only |
| margin | wrapper (was container) | generic wrapper | wrapper | mobile margin diff on wrapper |
| gaps/align/icon | container/icon | none | same | desktop values persist |

**Mobile ownership mismatch reproduced: YES** (margin on container vs generic responsive on wrapper) — **fixed** by moving margin to wrapper.

---

## 27. Mobile strategy

**DIFF-ONLY** for wrapper and container.

**Why unset mobile values are safe:** Icon traits are device-agnostic; padding/margin mobile boxes fall back to desktop via `resolveDevicePaddingCss` / `resolveDeviceMarginCss` before diff.

---

## 28. Compiler registration

Wired in `compilePopupRenderCss.ts` after video compiler.

---

## 29. Inline merchant CSS removed

Serializer `blocks/social/serialize.ts` — structure-only; no `styleAttr`.

---

## 30. Remaining legitimate inline CSS

| Location | Declaration | Reason |
|---|---|---|
| Canvas empty-state placeholder | dashed border, gray text | Builder-only, not exported |
| Canvas `<a>` | `pointerEvents` | Builder interaction guard |
| Block wrapper (serializeElements) | structural box-sizing if any | Non-merchant |

---

## 31. Generic responsive exclusion

`social` added to `MIGRATED_BLOCK_TRAIT_TYPES` and `MIGRATED_WRAPPER_LAYOUT_BLOCK_TYPES`.

---

## 32. Behavior regression

Link href/target preserved. Canvas preview link behavior unchanged. **PASS**

---

## 33. State regression

NOT APPLICABLE — no state presentation in social.

---

## 34. Artifact isolation

Distinct artifact scopes → distinct icon width/radius. **PASS**

---

## 35. Multi-runtime isolation

Covered by artifact-scoped selectors (same pattern as coupon/media). **PASS**

---

## 36. Canvas/export parity

Shared `ElementStyles` resolvers drive canvas + compiler. **PASS**

---

## 37. Chromium computed-style evidence

`socialPresentation.browser.test.ts` — desktop padding/justify/margin, icon size/radius, mobile diff, unset fallback. **PASS**

---

## 38. Hostile-theme evidence

Global `img { width: 10px }` does not override artifact-scoped rules. **PASS**

---

## 39. Cross-family coexistence

Social + image + video + coupon in one artifact — all compilers contribute. **PASS**

---

## 40. Consumer completeness

`compilePopupRenderCss` feeds all standard consumers (preview, embed, thumbnails) — no consumer-specific patch. **PASS**

---

## 41. Serializer negative assertions

No `style=` on `[data-adflipr-social]`, `.pbn-social-icon`, `.pbn-social-link` in export HTML. **PASS**

---

## 42. Generic responsive negative assertions

`buildEmbeddedStepResponsiveCss` excludes `[data-adflipr-block-id="soc-1"]`. **PASS**

---

## 43. Obsolete production code deleted

None required — serializer logic replaced in place; no standalone social export helper existed.

---

## 44. Production-derived final inventory

| Family | Inline merchant CSS | Generic responsive CSS | Compiler | Status |
|---|---|---|---|---|
| timer | YES | YES | NO | UNMIGRATED |
| spinner | YES | YES | NO | UNMIGRATED |
| product | YES | YES | NO | UNMIGRATED |
| feedback | YES | YES | NO | UNMIGRATED |
| social | **NO** | **NO** | **YES** | **MIGRATED** |
| image | NO | NO | YES | MIGRATED |
| video | NO | NO | YES | MIGRATED |

---

## 45. Current-state document update

Updated `ai/kb/popup-css-migration-current-state.md`.

---

## 46. Deferred debt preservation

Button hover, checkbox orphans, coupon boxShadow/customCss, image elementWidth orphan — **not modified**. Social `customCss` recorded as new deferred debt.

---

## 47. Phase 0 result

```text
18 migrated regression green
0 red
5 controls green
```

(via `columnPresentation.browser.test.ts`, `columnsContainerPresentation.browser.test.ts`, `buttonPresentation.browser.test.ts`)

---

## 48. G1 result

**PASS** — `social.export.css` marker `.pbn-social-link` in production webpack bundle.

---

## 49. Production build result

**PASS** — `npm run build` including export-css contract gate.

---

## 50. Remaining selected-family debt

- `customCss` advanced trait (panel writer; not compiled — same class as coupon)
- `customClass` orphan (not implemented)

---

## 51. Remaining all-block CSS debt

Four families: **timer**, **spinner**, **product**, **feedback**.

---

## 52. Architecture gates

| Gate | Status |
|---|---|
| Partial migrations | NONE |
| Merchant inline on social | NONE |
| Generic responsive on social | NONE |
| !important internal repair | NONE |
| Persistence changes | NONE |

---

## 53. Final verdict

**ACCEPT** — Social fully migrated; gates green; no regressions observed.

---

## 54. Recommended next family

**feedback** — best balance of remaining families: single container topology, existing `mediaAndFeedbackStyles` builders to refactor, no canvas animation runtime; requires careful state/presentation separation.
