# Phase 7D — Image + Video Merchant Presentation Migration Report

**Date:** 2026-07-06  
**Branch:** `css-reft` (merged with `fix-0607`)  
**Baseline HEAD:** `5a6b2b1e672060a6381d5b4195a5c9b7cfa1d161` (Coupon landed)  
**Implementation:** working tree on `css-reft` (post-baseline)  
**Verdict:** **ACCEPT**

---

## 1. Executive verdict

**Outcome A — Image + Video migrated** via **Topology B** (`mediaPresentation.ts` with separate `compileImagePresentationRenderCss` and `compileVideoPresentationRenderCss`).

Both families moved from **serializer inline merchant CSS + `buildBlockTraitResponsiveCss` generic mobile overrides** to **canonical owner-specific normalization in `ElementStyles.ts` → artifact-scoped `renderCss`**, with structural safety in `image.export.css` and `video.export.css`.

Coupon remained fully migrated. Phase 0 (18 migrated / 0 red / 5 controls), G1 production raw-CSS contract, and production build **PASS**.

---

## 2. Starting baseline verification

Verified at `5a6b2b1e6` **before** Phase 7D working-tree changes:

| Check | Expected | Actual |
|---|---|---|
| Coupon compiler active | YES | **YES** — `couponPresentation.ts`, `compileCouponPresentationRenderCss` in `compilePopupRenderCss.ts` |
| Coupon merchant inline CSS absent | YES | **YES** — `coupon/serialize.ts` structure-only |
| Coupon generic responsive merchant CSS absent | YES | **YES** — `coupon` in `MIGRATED_BLOCK_TRAIT_TYPES` at HEAD |
| Image compiler absent | YES | **YES** — no `mediaPresentation.ts` at HEAD |
| Video compiler absent | YES | **YES** |
| Image merchant inline CSS present | YES | **YES** — `buildImageBlockStyle` / `buildImageContainerStyle` in `image/serialize.ts` |
| Video merchant inline CSS present | YES | **YES** — inline styles in `video/serialize.ts` |
| `image` consumes `buildBlockTraitResponsiveCss` | YES | **YES** — not in `MIGRATED_BLOCK_TRAIT_TYPES` at HEAD |
| `video` consumes `buildBlockTraitResponsiveCss` | YES | **YES** |

**Phase 7D starting baseline valid: YES**

No branch archaeology performed.

---

## 3. Image complete trait inventory

| Trait | UI writer | Persisted field | Canvas consumer | Desktop export owner (pre) | Mobile export owner (pre) | Class |
|---|---|---|---|---|---|---|
| `imageUrl` / `imageFileName` | `ImageProperties` + asset dialog | `properties.imageUrl` | `ImageCanvas` | `src` attr | — | CONTENT |
| `imageAlt` | `ImageProperties` | `properties.imageAlt` | `ImageCanvas` | `alt` attr | — | CONTENT |
| `linkUrl` | `ImageProperties` | `properties.linkUrl` | preview `<a>` | optional `<a href>` | — | BEHAVIOR |
| `elementPosition` | `PositionWidthSection` | `style.elementPosition` | painted margin | img inline margin | generic wrapper | MERCHANT |
| `imageWidth` | `PositionWidthSection` | `style.imageWidth` | painted % width | img inline width% | generic (if traits differ) | MERCHANT |
| `imageFullWidth` | implicit default `true` | `style.imageFullWidth` | mode switch | img inline | — | MERCHANT |
| `imageWidthPx` / `imageHeightPx` | dimension inputs | `style.*` | fixed painted size | img inline px | — | MERCHANT |
| `paddingDesktop` / `paddingMobile` | `DeviceSpacingSection` | `style.*` | shell | container inline | generic wrapper padding | MERCHANT |
| `marginDesktop` / `marginMobile` | `DeviceSpacingSection` | `style.*` | wrapper via layout | wrapper layoutStyles | generic wrapper margin | MERCHANT |
| `cornerRadius` / `cornerRadiusMobile` | `DeviceCornerRadiusSection` | `style.*` | painted | img inline | generic wrapper radius | MERCHANT |
| `backgroundColor` | `ColorPickerField` | `style.backgroundColor` | shell | container inline | — | MERCHANT |
| `borderStyle` / `borderWidth` / `borderColor` | Border controls | `style.*` | painted | img inline | — | MERCHANT |
| `boxShadowDesktop` / `boxShadowMobile` | `BoxShadowTraitFields` | `style.*` | shell | container inline | — | MERCHANT |
| `elementWidth` | Not in Image panel | `style.elementWidth` | — | layoutStyles fallback only | — | LEGACY / unused |
| `hideOnDesktop` / `hideOnMobile` | Advanced | `style.*` | — | device visibility CSS | — | BEHAVIOR |
| `customClass` / `customCss` | Advanced | `style.*` | — | custom CSS block | — | DEFERRED |

**Orphans (not implemented):** `elementWidth` on image has no panel writer; `objectFit` only applied when `imageFullWidth` is false.

---

## 4. Video complete trait inventory

| Trait | UI writer | Persisted | Canvas | Desktop export (pre) | Mobile export (pre) | Class |
|---|---|---|---|---|---|---|
| `videoUrl` | `VideoProperties` | `properties.videoUrl` | `VideoCanvas` | iframe/video `src` | — | CONTENT |
| `thumbnailUrl` | Thumbnail asset | `properties.thumbnailUrl` | native poster | `poster` attr | — | CONTENT |
| `autoplay` / `controls` / `loop` / `muted` | Content toggles | `properties.*` | playback | video/iframe attrs | — | BEHAVIOR |
| `elementPosition` | `PositionWidthSection` | `style.elementPosition` | frame margin | frame inline margin | generic wrapper | MERCHANT |
| `videoWidth` | `PositionWidthSection` | `style.videoWidth` | frame % width | frame inline | generic | MERCHANT |
| `videoFullWidth` | default `true` | `style.videoFullWidth` | topology mode | embed scaffold vs fixed | — | MERCHANT |
| `videoWidthPx` / `videoHeightPx` | dimension inputs | `style.*` | fixed frame | frame inline px | — | MERCHANT |
| `paddingDesktop` / `paddingMobile` | `DeviceSpacingSection` | `style.*` | shell | container inline | generic wrapper | MERCHANT |
| `marginDesktop` / `marginMobile` | `DeviceSpacingSection` | `style.*` | wrapper layout | wrapper layoutStyles | generic wrapper | MERCHANT |
| `cornerRadius` / `cornerRadiusMobile` | `DeviceCornerRadiusSection` | `style.*` | frame | frame inline | generic wrapper | MERCHANT |
| `backgroundColor` | `ColorPickerField` | `style.backgroundColor` | shell | container inline | — | MERCHANT |
| `borderStyle` / `borderWidth` / `borderColor` | Border controls | `style.*` | frame | frame inline | — | MERCHANT |
| `boxShadowDesktop` / `boxShadowMobile` | `BoxShadowTraitFields` | `style.*` | shell | container inline | — | MERCHANT |

Playback attributes (`autoplay`, `controls`, `loop`, `muted`, `poster`) **not** moved to renderCss.

---

## 5–6. UI writer evidence

- **Image:** `ImageProperties.tsx` — Content (asset, link, alt); Style (position/width, padding, margin, border, radius, background, shadow); Advanced (hide, custom).
- **Video:** `VideoProperties.tsx` — Content (URL, thumbnail, autoplay, controls); Style (same layout/border/shadow pattern); Advanced (hide, custom).

---

## 7. Image exact DOM topology (post-migration)

```html
<div data-block-type="image" data-adflipr-block-id="{id}">     <!-- BLOCK LAYOUT -->
  <div data-adflipr-image="true" class="pbn-image-shell">       <!-- SHELL -->
    <a href="…" target="_blank" rel="noopener noreferrer">?     <!-- LINK BEHAVIOR -->
      <img src="…" alt="…" class="pbn-image-painted" />          <!-- PAINTED -->
    </a>
  </div>
</div>
```

Unlinked: shell → `img` directly (no `<a>`).

| DOM node | Stable selector | Role | CSS source (post) |
|---|---|---|---|
| Block wrapper | `[data-adflipr-artifact] [data-adflipr-block-id]` | BLOCK LAYOUT | renderCss wrapper rules |
| Shell | `[data-adflipr-image="true"]` | SHELL PRESENTATION | renderCss shell rules |
| Optional `<a>` | — | LINK BEHAVIOR | none |
| `<img>` | `.pbn-image-painted` | PAINTED | renderCss painted rules |

Embedded structural: `image.export.css` — shell `width:100%`, painted `max-width:100%`.

---

## 8. Linked versus unlinked Image topology

| Question | Answer |
|---|---|
| Does optional `<a>` change painted selector? | **NO** — `.pbn-image-painted` on `<img>` in both cases |
| Does link own presentation? | **NO** — behavior only (`href`, `target`, `rel`) |
| One artifact selector for both? | **YES** — `imagePaintedSelector` targets `.pbn-image-painted` |

---

## 9. Video exact DOM topology by mode/provider

| Mode | DOM | Identity | Layout | Painted | Behavior |
|---|---|---|---|---|---|
| YouTube/Vimeo embed-full | shell → `div[data-adflipr-video-frame]` + `iframe.pbn-video-iframe` | block wrapper | wrapper + shell | frame (width %); scaffold in `video.export.css` | iframe `src` via `getVideoEmbedUrl` |
| YouTube/Vimeo embed-fixed | shell → frame + iframe | same | same | frame px w/h | iframe `src` |
| Native MP4 full | shell → `video[data-adflipr-video-frame]` | same | same | video % + aspect-ratio | video attrs |
| Native MP4 fixed | shell → `video[data-adflipr-video-frame]` | same | same | video px w/h | video attrs |

**Providers:** YouTube (`youtube.com`, `youtu.be`), Vimeo (`vimeo.com/{id}`), native file URL.

**Canvas vs export:** Same topology contract; canvas applies embed scaffold inline for preview parity with `video.export.css`.

---

## 10. Video runtime behavior trace

| Question | Answer |
|---|---|
| Runtime mutates merchant inline CSS? | **NO** — export is structure-only; embed runtime does not rewrite presentation |
| Runtime replaces painted owner? | **NO** |
| Runtime depends on serialized style attributes? | **NO** |
| Provider init requires inline dimensions? | **NO** — embed scaffold from export CSS sheet |

**Video runtime replaces painted owner: NO**  
**Video runtime mutates merchant inline CSS: NO**

---

## 11. Image/Video semantic comparison

| Concern | Image | Video | Truly shared? |
|---|---|---|---|
| Wrapper block margin | layoutStyles | layoutStyles | **YES** |
| Shell padding/bg/shadow | shell | shell | **YES** |
| elementPosition margin | painted img | frame | **YES** (semantics) |
| Width % trait | `imageWidth` on img | `videoWidth` on frame | **PARTIAL** |
| Fixed dimensions | imageWidthPx/HeightPx | videoWidthPx/HeightPx | **PARTIAL** |
| Border/radius | painted img | frame | **PARTIAL** |
| Embed scaffold | — | 16:9 padding-bottom | **NO** |
| Content/playback | src/alt/link | src/provider/attrs | **NO** |

---

## 12. Compiler topology decision

**Selected topology: B**

```text
mediaPresentation.ts
├── compileImagePresentationRenderCss
└── compileVideoPresentationRenderCss
```

**Evidence:** Shared wrapper layout collection + shell normalizer; separate painted (image) vs frame (video) normalization and selectors.

---

## 13. Rejected topology alternatives

| Topology | Rejected because |
|---|---|
| A — one shared normalized model | Video embed frame rules diverge from image `<img>` painted owner |
| C — separate files | Genuine shared shell/wrapper infrastructure |
| D — image only | Video modes have stable frame-level painted owner; no runtime blocker |

---

## 14. Current normalization inventory (post)

| Family | Canvas normalizer | Serializer normalizer | Mobile normalizer | Shared? |
|---|---|---|---|---|
| Image | `resolveMediaShellPresentationCssRules`, `resolveImagePaintedPresentationCssRules` | same via `mediaPresentation.ts` | DIFF-ONLY in compiler | **CANONICAL SHARED** |
| Video | `resolveMediaShellPresentationCssRules`, `resolveVideoFramePresentationCssRules`, `resolveVideoPresentationMode` | same | DIFF-ONLY in compiler | **CANONICAL SHARED** |

Pre-migration: **DUPLICATED** (`buildImageBlockStyle`, inline video serialize, `buildBlockTraitResponsiveCss`).

---

## 15–16. Canonical normalization

**Image:** `normalizeImagePresentation` → wrapper / shell / painted (desktop base + mobile diff).

**Video:** `normalizeVideoPresentation` → mode + wrapper / shell / frame.

Shared helpers in `ElementStyles.ts`:

- `resolveMediaShellPresentationCssRules`
- `resolveImagePaintedPresentationCssRules`
- `resolveVideoFramePresentationCssRules`
- `resolveVideoPresentationMode`

---

## 17. Content / presentation / structure / behavior matrix

| Concern | Classification | Future owner |
|---|---|---|
| src / alt / href / target | CONTENT / BEHAVIOR | HTML attrs |
| provider / video URL / autoplay / controls / loop / muted / poster | CONTENT / BEHAVIOR | HTML attrs |
| margin (block) | MERCHANT PRESENTATION | renderCss wrapper |
| padding / background / shadow (shell) | MERCHANT PRESENTATION | renderCss shell |
| border / radius / width / height / object-fit (media) | MERCHANT PRESENTATION | renderCss painted/frame |
| elementPosition margin | MERCHANT PRESENTATION | renderCss painted/frame |
| max-width:100% on painted | RESPONSIVE SAFETY | `image.export.css` / rules in normalizer |
| embed 16:9 scaffold | STRUCTURE | `video.export.css` |
| iframe absolute fill | STRUCTURE | `video.export.css` |

---

## 18–19. Ownership matrices

### Image

| Property | Owner |
|---|---|
| Block identity | `[data-adflipr-block-id]` wrapper |
| Block margin / alignSelf | wrapper (renderCss) |
| elementWidth (layout) | wrapper via `getExportWrapperStyleFromLayout` |
| Padding / background / shadow | shell `[data-adflipr-image="true"]` |
| Border / radius / width / height / object-fit / position margin | painted `.pbn-image-painted` |
| Link | `<a>` behavior only |

### Video

| Property | Owner |
|---|---|
| Block identity | wrapper |
| Block margin / alignSelf | wrapper (renderCss) |
| Shell padding/bg/shadow | `[data-adflipr-video="true"]` |
| Frame dimensions / border / radius / position margin | `[data-adflipr-video-frame="true"]` |
| iframe internals | NOT styled (structural sheet only) |
| Playback | video/iframe attrs |

---

## 20. Width semantics audit

| Family | `elementWidth` | `width` / `imageWidth` / `videoWidth` |
|---|---|---|
| Image | Not exposed in Image panel; layoutStyles may use for column layout | `imageWidth` % on painted when full-width; `imageWidthPx` when fixed |
| Video | Same | `videoWidth` % on frame (embed-full / native-full); `videoWidthPx` when fixed |

Block width (`elementWidth` via layout) and media width are **distinct** — not collapsed.

---

## 21. Height and aspect-ratio audit

| Family | Height | Aspect ratio |
|---|---|---|
| Image | `height:auto` when full-width; fixed px when not | implicit via width mode |
| Video | fixed px on embed-fixed / native-fixed | `aspect-ratio:16/9` on native-full; embed-full uses structural padding-bottom in export CSS |

---

## 22. Responsive safety audit

| Declaration | Classification | Location |
|---|---|---|
| `max-width:100%` on painted | RESPONSIVE SAFETY | normalizer + `image.export.css` |
| `display:block` on shell | STRUCTURE | normalizer + export sheets |
| embed padding-bottom 56.25% | STRUCTURE | `video.export.css` |
| iframe absolute fill | STRUCTURE | `video.export.css` |

---

## 23–25. Migration summary

- **Block layout:** margin, width, alignSelf → renderCss wrapper; stripped from export inline via `applyMigratedWrapperLayoutExportStripping`.
- **Image presentation:** padding, background, shadow, border, radius, dimensions, object-fit, position margin → renderCss shell + painted.
- **Video presentation:** same pattern on shell + frame; embed scaffold remains structural export CSS.

---

## 26–27. Mobile ownership and strategy

| Family | Strategy | Reason |
|---|---|---|
| Image | **DIFF-ONLY** per owner (wrapper, shell, painted) | Same canonical owners; unset mobile inherits desktop |
| Video | **DIFF-ONLY** per owner (wrapper, shell, frame) | Same |

**Image mobile ownership mismatch reproduced (pre): YES** — generic responsive targeted wrapper for radius/padding meant for painted/shell.  
**Video mobile ownership mismatch reproduced (pre): YES**

---

## 28–30. Compiler collection, conflict, determinism

- `collectArtifactImagePresentations` / `collectArtifactVideoPresentations` — multi-step union, sorted by blockId.
- `ImagePresentationConflictError` / `VideoPresentationConflictError` on conflicting presentation for same blockId.
- Presentation key excludes `src` / `linkUrl` / `videoUrl` — content differences do not false-conflict.
- Deterministic output verified in unit tests.

---

## 31. Compiler lifecycle registration

Wired in `compilePopupRenderCss.ts` after Coupon, before positioned decorative:

```text
compileImagePresentationRenderCss
compileVideoPresentationRenderCss
```

All standard artifact consumers receive Media renderCss automatically.

---

## 32–35. Inline merchant CSS removal

| Family | Merchant inline remaining |
|---|---|
| Image | **NONE** |
| Video | **NONE** |

**Legitimate inline/structural remaining:**

- Image: none on merchant nodes (structure-only HTML)
- Video: none on merchant nodes; `video.export.css` owns embed scaffold

---

## 36–37. Generic responsive CSS removal

`image` and `video` added to `MIGRATED_BLOCK_TRAIT_TYPES` and `MIGRATED_WRAPPER_LAYOUT_BLOCK_TYPES`.

Negative assertions in `mediaPresentationRenderCss.test.ts` — image/video absent from `buildEmbeddedStepResponsiveCss` output.

---

## 38–46. Regression evidence

| Gate | Result |
|---|---|
| Image behavior (src, alt, link, href, target) | **PASS** — serializer tests + structure-only export |
| Video behavior (providers, attrs) | **PASS** — serialize preserves attrs; no playback changes |
| Multi-step union | **PASS** |
| Artifact isolation | **PASS** — browser tests |
| Multi-runtime isolation | **PASS** |
| Canvas/export parity | **PASS** — shared `ElementStyles` normalizers |
| Desktop computed-style | **PASS** — `mediaPresentation.browser.test.ts` |
| Mobile computed-style + unset fallback | **PASS** |
| Hostile-theme | **PASS** — artifact-scoped rules win without `!important` |
| Cross-family (Image+Video+Coupon+Divider) | **PASS** |
| Consumer completeness | **PASS** — `compilePopupRenderCss` central dispatch |
| Serializer negative assertions | **PASS** |
| Generic responsive negative assertions | **PASS** |

---

## 47. Obsolete production code deleted

- `buildImageBlockStyle` / `buildImageContainerStyle` removed from `mediaAndFeedbackStyles.ts` (image-specific; floating-image helpers retained).
- Image/video merchant inline branches removed from `image/serialize.ts` and `video/serialize.ts`.

---

## 48. Production-derived remaining-family inventory

| Family | Inline merchant CSS | Generic responsive merchant CSS | Dedicated compiler | Status |
|---|---|---|---|---|
| image | NONE | NONE | `mediaPresentation.ts` | **MIGRATED** |
| video | NONE | NONE | `mediaPresentation.ts` | **MIGRATED** |
| timer | YES | YES | — | UNMIGRATED |
| spinner | YES | YES | — | UNMIGRATED |
| product | YES | YES | — | UNMIGRATED |
| feedback | YES | YES | — | UNMIGRATED |
| social | YES | YES | — | UNMIGRATED |

**Partial migrations: NONE**  
**True remaining merchant-CSS debt count: 5**

---

## 49. Current-state document update

See `ai/kb/popup-css-migration-current-state.md` (updated).

---

## 50. Deferred debt preservation

Unchanged:

- Button linked-label hover defect
- Checkbox orphan/dead-model traits
- Structural-inline final cleanup candidates
- Coupon boxShadow no-panel-writer debt
- Coupon customCss advanced-trait debt

**Newly recorded Media orphans:** Image `elementWidth` (no panel writer).

---

## 51–53. Regression gates

| Gate | Result |
|---|---|
| Phase 0 | **18 migrated green / 0 red / 5 controls green** — `npm run test:popup-responsive-baseline` |
| G1 | **PASS** — `npm run test:export-css-production-contract` |
| Production build | **PASS** — `npm run build` |

---

## 54–55. Remaining debt

**Media debt:** NONE  
**All-block CSS debt:** timer, spinner, product, feedback, social

---

## 56. Architecture gates

| Gate | Status |
|---|---|
| Same-concern hybrid CSS | **NONE** |
| `!important` for internal cascade repair | **NONE** |
| Global merchant selectors | **NONE** |
| New persistence fields | **NONE** |
| Backward compatibility shims | **NONE** |

---

## 57. Final verdict

**ACCEPT**

---

## 58. Recommended next action

**Phase 7E — Timer** (rank by DOM ownership certainty, inline surface, and testability; timer is the next highest-confidence remaining family per H3 audit).

---

## Tests passed

```text
mediaPresentationRenderCss.test.ts — 14 tests PASS
mediaPresentation.browser.test.ts — 14 tests PASS
couponPresentation.browser.test.ts — PASS
couponPresentationRenderCss.test.ts — PASS
responsiveDeviceCss.test.ts — PASS
popupHtmlSerializer.test.ts — 53 tests PASS
responsiveCssBaseline.browser.test.ts — 25 tests PASS (Phase 0)
export-css-production-contract — PASS
npm run build — PASS
```

## Tests failed

```text
NONE
```

---

## Required final answer format

1. Phase implemented: **Phase 7D — Image + Video merchant presentation migration**
2. Starting baseline valid: **YES**
3. Coupon remained fully migrated: **YES**
4. Image debt reproduced: **YES**
5. Video debt reproduced: **YES**
6. Image current UI writers verified: **YES**
7. Video current UI writers verified: **YES**
8. Image DOM topology: **wrapper → shell `[data-adflipr-image]` → optional `<a>` → `img.pbn-image-painted`**
9. Linked Image topology: **link is behavior-only; painted selector unchanged**
10. Video DOM topology: **wrapper → shell `[data-adflipr-video]` → frame `[data-adflipr-video-frame]` → iframe or `<video>`**
11. Video supported modes/providers: **YouTube, Vimeo, native MP4; embed-full, embed-fixed, native-full, native-fixed**
12. Video runtime replaces painted owner: **NO**
13. Video runtime mutates merchant inline CSS: **NO**
14. Image identity owner: **`[data-adflipr-block-id]` wrapper**
15. Image block-layout owner: **wrapper (margin, width, alignSelf)**
16. Image shell owner: **`[data-adflipr-image="true"]`**
17. Image painted owner: **`.pbn-image-painted`**
18. Image link behavior owner: **`<a href target rel>`**
19. Video identity owner: **wrapper**
20. Video block-layout owner: **wrapper**
21. Video shell/frame owner: **shell `[data-adflipr-video]`; frame `[data-adflipr-video-frame]`**
22. Video painted owner(s): **frame element (div or video)**
23. Video behavior owner: **iframe/video attrs + `getVideoEmbedUrl`**
24. Selected topology: **B**
25. Reason for selected topology: **shared shell/wrapper mechanics; separate painted vs frame normalization**
26. Image migrated: **YES**
27. Video migrated: **YES**
28. Compiler module(s): **`mediaPresentation.ts`**
29. Existing canonical normalization reused: **`getExportWrapperStyleFromLayout`, device padding/margin/radius/shadow helpers**
30. New Image normalization added: **`resolveImagePaintedPresentationCssRules`, `normalizeImagePresentation`, `compileImagePresentationRenderCss`**
31. New Video normalization added: **`resolveVideoFramePresentationCssRules`, `resolveVideoPresentationMode`, `normalizeVideoPresentation`, `compileVideoPresentationRenderCss`**
32. Image merchant properties moved to renderCss: **margin, padding, background, boxShadow, border, radius, width, height, object-fit, elementPosition margin**
33. Video merchant properties moved to renderCss: **margin, padding, background, boxShadow, border, radius, frame width/height/aspect-ratio, elementPosition margin**
34. Image content/data retained outside presentation: **src, alt, href, target**
35. Video content/data retained outside presentation: **src, poster, provider URL, playback attrs**
36. Image behavior retained outside presentation: **link navigation, loading**
37. Video behavior retained outside presentation: **autoplay, controls, loop, muted, fullscreen, embed URL construction**
38. elementWidth semantics: **block layout via layoutStyles; distinct from media width traits**
39. Image width semantics: **`imageWidth` % on painted when full-width; `imageWidthPx`/`imageHeightPx` when fixed**
40. Video width semantics: **`videoWidth` % on frame when full-width modes; px on fixed modes**
41. Image height semantics: **auto when full-width; fixed px when not**
42. Video height semantics: **structural aspect-ratio (native-full) or fixed px; embed-full uses export scaffold**
43. Aspect-ratio ownership: **native-full frame in renderCss; embed-full scaffold in `video.export.css`**
44. Object-fit ownership: **image painted when fixed-size; video native modes**
45. Responsive safety retained outside merchant renderCss: **YES — `image.export.css`, `video.export.css`, normalizer max-width**
46. Image mobile ownership mismatch reproduced: **YES** (pre-migration)
47. Video mobile ownership mismatch reproduced: **YES** (pre-migration)
48. Image mobile strategy: **DIFF-ONLY by owner**
49. Video mobile strategy: **DIFF-ONLY by owner**
50. Image merchant inline CSS remaining: **NONE**
51. Video merchant inline CSS remaining: **NONE**
52. Image generic responsive merchant CSS remaining: **NONE**
53. Video generic responsive merchant CSS remaining: **NONE**
54. Legitimate Image inline CSS remaining: **none on merchant nodes**
55. Legitimate Video inline CSS remaining: **none on merchant nodes; structural embed in export sheet**
56. Image merchant selectors artifact-scoped: **YES**
57. Video merchant selectors artifact-scoped: **YES**
58. Global merchant selectors added: **NO**
59. New persistence fields added: **NO**
60. Multi-step union: **PASS**
61. Conflict behavior: **PASS**
62. Deterministic output: **PASS**
63. Artifact isolation: **PASS**
64. Multi-runtime isolation: **PASS**
65. Image behavior regression: **PASS**
66. Video behavior regression: **PASS**
67. Canvas/export parity: **PASS**
68. Desktop computed-style parity: **PASS**
69. Mobile computed-style parity: **PASS**
70. Mobile unset fallback: **PASS**
71. Hostile-theme test: **PASS**
72. Cross-family coexistence: **PASS**
73. Consumer completeness: **PASS**
74. Same-concern hybrid CSS remaining: **NO**
75. !important added for internal cascade repair: **NO**
76. Obsolete production code deleted: **`buildImageBlockStyle`, `buildImageContainerStyle`, inline image/video merchant serialize branches**
77. Current families with inline merchant CSS: **timer, spinner, product, feedback, social**
78. Current families with generic responsive merchant CSS: **timer, spinner, product, feedback, social**
79. Current partial migration families: **NONE**
80. Current buildBlockTraitResponsiveCss consumers: **timer, spinner, product, feedback, social**
81. True remaining merchant-CSS debt count: **5**
82. Phase 0 migrated green: **18**
83. Phase 0 red: **0**
84. Phase 0 controls green: **5**
85. G1 production raw CSS contract: **PASS**
86. Production build: **PASS**
87. Tests passed: **see Tests passed section**
88. Tests failed: **NONE**
89. Remaining Media debt: **NONE**
90. Remaining all-block CSS debt: **timer, spinner, product, feedback, social**
91. Deferred defects modified: **NO**
92. Backward compatibility added: **NO**
93. Verdict: **ACCEPT**
94. Recommended next action: **Phase 7E — Timer merchant presentation migration**
