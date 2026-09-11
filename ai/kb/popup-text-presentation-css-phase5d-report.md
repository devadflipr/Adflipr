# Popup CSS architecture — Phase 5D implementation report

**Status:** Implemented (2026-07-05)  
**Type:** Text block-family presentation → artifact-scoped `renderCss`  
**Related:** [`popup-button-presentation-css-phase5c-report.md`](./popup-button-presentation-css-phase5c-report.md), [`popup-button-presentation-css-phase5c-acceptance-audit.md`](./popup-button-presentation-css-phase5c-acceptance-audit.md)

---

## A. Executive summary

Phase 5D migrates **Text** merchant presentation from serialized inline CSS, embedded generic responsive trait rules, and global per-step link-color sheets into a dedicated family compiler integrated only through `compilePopupRenderCss()`.

**Key fixes:**

- Text **split-target padding defect** (desktop on inner `div`, mobile on wrapper via `buildBlockTraitResponsiveCss`) — resolved.
- Text **link-color** moved from global embedded `!important` sheet to per-block artifact-scoped descendant rules (no `!important` on Text rules).
- Canonical normalization via `resolveTextPaintedCssRules` / `resolveTextWrapperCssRules`.

**Phase 0 scoreboard:** unchanged (12 migrated green / 6 risk red / 5 controls green). No remaining Phase 0 red case was Text-owned.

---

## B. Pre-implementation ownership trace

```text
FormElement Text model (properties.style, properties.content)
  → StyleSection / element traits (padding, margin, typography, linkColor, …)
  → TextCanvas: getElementStyle → strip margin/padding → InlineEditor
  → layoutStyles: buildTextBlockWrapperStyle on selection container (canvas)
  → serializeText: buildTextBlockStyle → getTextBlockContentStyle → inline on > div
  → serializeElements: getExportWrapperStyleFromLayout → buildTextBlockWrapperStyle on wrapper
  → buildBlockTraitResponsiveCss: mobile padding/margin/radius/font on wrapper (split-target bug)
  → buildRichTextLinkExportCss: global form linkColor on all [data-block-type="text"] a
  → applyRichTextLinkStyles: inline color on anchors in exported HTML
  → compilePopupRenderCss (no Text compiler before 5D)
```

---

## C. Exact Text DOM contract

```text
[data-adflipr-block-id="{textId}"]     ← wrapper (identity + block layout)
└── div                                ← painted/content owner (single direct child)
    └── RTE HTML (p, strong, a, …)
```

| DOM node | Stable selector | Owner |
| -------- | --------------- | ----- |
| Wrapper | `[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"]` | Block margin, width %, position auto-margins |
| Content `> div` | `… [data-adflipr-block-id="{id}"] > div` | Padding, radius, bg, border, shadow, typography, alignment |
| RTE `a` | `… > div a` | Link color, text-decoration (descendant rules) |

**Split-target defect (pre-5D):** desktop padding on `> div`, mobile padding on wrapper — fixed by migrating both to correct owners in renderCss.

---

## D. Trait ownership matrix

| Trait | Desktop field | Mobile field | Pre-5D export | Owner (5D) |
| ----- | ------------- | ------------ | ------------- | ---------- |
| Block margin | `marginDesktop` | `marginMobile` | wrapper inline | wrapper renderCss |
| Width % / position | `elementWidth`, `elementPosition` | same | wrapper inline | wrapper renderCss |
| Padding | `paddingDesktop` | `paddingMobile` | `> div` inline | content renderCss |
| Border radius | `cornerRadius` / device | device variants | `> div` inline | content renderCss |
| Background | `backgroundColor` | — | `> div` inline | content renderCss |
| Border | `borderStyle/Width/Color` | — | `> div` inline | content renderCss |
| Box shadow | structured / legacy | device | `> div` inline | content renderCss |
| Font family | `fontFamily` | — | `> div` inline | content renderCss |
| Font size | `fontSize` | `fontSizeMobile` | `> div` inline | content renderCss |
| Font weight | `fontWeight` | — | `> div` inline | content renderCss |
| Text color | `textColor` / `fontColor` | — | `> div` inline | content renderCss |
| Text alignment | `textAlignment` | — | `> div` inline | content renderCss |
| Line height | `lineHeight` | — | `> div` inline | content renderCss |
| Letter spacing | `letterSpacing` | — | `> div` inline | content renderCss |
| Height | `height` | — | `> div` inline | content renderCss |
| Link color | `style.linkColor` | — | inline on `<a>` + global embedded | descendant renderCss |
| Link decoration | `style.linkDecoration` | — | embedded global | descendant renderCss |
| RTE content | `properties.content` | — | HTML | HTML (unchanged) |
| Hide on device | `hideOnMobile/Desktop` | — | visibility CSS (separate) | out of scope |

---

## E. Canonical normalization decision

**Decision:** Extract `resolveTextPaintedCssRules` and `resolveTextWrapperCssRules` as canonical shared normalizers.

| Function | Role |
| -------- | ---- |
| `resolveTextWrapperCssRules` | Wrapper margin + width % layout (**includes** `adjustLayoutWidthForHorizontalMargins` so `width:100%` + horizontal margin cannot overflow) |
| `resolveTextPaintedCssRules` | Content `> div` merchant presentation |
| `TEXT_PAINTED_STRUCTURAL_STYLES` | `white-space: pre-wrap` |
| `getTextBlockContentStyle` | Canvas adapter (structural + painted) |
| `buildTextBlockWrapperStyle` | Canvas/export helper delegating to wrapper rules |

Trait interpretation exists **once** — canvas and compiler both consume the same resolvers.

---

## F. Before architecture

```text
Wrapper: inline margin + width (buildTextBlockWrapperStyle)
> div: inline full presentation (getTextBlockContentStyle)
Embedded: buildBlockTraitResponsiveCss mobile rules on wrapper
Embedded: buildRichTextLinkExportCss global text link rules (!important)
HTML: applyRichTextLinkStyles inline color on <a>
```

---

## G. After architecture

```text
Wrapper: structure + identity only (flexShrink structural); margin/width in renderCss
> div: RTE HTML only (no style attr)
renderCss: compileTextPresentationRenderCss
  ├── wrapper base + mobile diffs
  ├── content base + mobile diffs
  └── per-block link descendant rules
Embedded: Text excluded from buildBlockTraitResponsiveCss
Embedded: buildRichTextLinkExportCss — button RTE links only (retained)
```

---

## H. Wrapper layout rules migrated

- Block margin (desktop base + mobile `@media` diff)
- Width % + `elementPosition` auto-margin layout (`elementWidth`, `elementPosition`)

---

## I. Painted/content rules migrated

- Padding, border-radius, background, border, box-shadow
- font-family, font-size, font-weight, color, text-align, line-height, letter-spacing, height
- Structural: `white-space: pre-wrap`

---

## J. RTE descendant CSS migration

**Before:** Global embedded sheet targeting all `[data-block-type="text"] a` with form `linkColor` and `!important`; plus inline `style="color:…"` on each anchor.

**After:** Per-block artifact-scoped rule:

```text
[data-adflipr-popup-artifact="{scope}"] [data-adflipr-block-id="{id}"] > div a
```

- Color from `style.linkColor` with `formStyle.linkColor` fallback
- Decoration from `style.linkDecoration` (default underline)
- No `!important`
- Inline anchor color removed from export HTML

**`buildRichTextLinkExportCss`:** RETAINED for **button** RTE links only (`button a` selector).

---

## K. Mobile emission strategy

**DIFF-ONLY** (column pattern) — not Button full-mobile merge.

**Reason:** Text painted owner has no flex structural shell requiring reconstruction; base desktop rules apply outside `@media`; mobile chunks emit only changed properties on the same owner. Omitted mobile values inherit desktop via `resolveDevicePaddingCss` / `resolveInputFontSizePx`.

---

## L. Multi-step union and conflicts

- `collectArtifactTextPresentations` scans all steps
- Same block id + identical `normalizeTextPresentationKey` → emit once
- Conflicting presentation → `TextPresentationConflictError` with block id + step ids
- Different **content** with same presentation → no conflict (CSS compiler is presentation-only)
- Deterministic sort by block id

---

## M. Inline CSS removed

| Location | Removed |
| -------- | ------- |
| Wrapper | margin, width %, position margins |
| Content `> div` | All merchant presentation `style` attr |
| RTE `<a>` | Inline `color` / `text-decoration` from `applyRichTextLinkStyles` |
| `buildTextBlockStyle` | Deleted (obsolete serializer helper) |

**Retained inline:** `flexShrink: 0` on wrapper (column flex structural).

---

## N. Embedded CSS removed

- Text no longer in `buildBlockTraitResponsiveCss`
- Text selectors removed from `buildRichTextLinkExportCss`

---

## O. Old generator cleanup

```typescript
if (… || element.type === 'button' || element.type === 'text') {
  continue;
}
```

---

## P. Content and RTE behavior preservation

- `sanitizeRichTextHtml` unchanged
- Paragraphs, bold, links, href preserved in HTML
- No RTE/compiler coupling
- Unit test: `preserves rich text HTML content in serialized output`

---

## Q. Phase 0 mapping before implementation

| Remaining risk id | Family | Text-owned? | In 5D scope? |
| ----------------- | ------ | ----------- | ------------ |
| cross-top/right | cross | No | No |
| floating-bottom/left | floating-image | No | No |
| teaser-padding/border-radius | teaser | No | No |

Text split-target was documented in `blockDomOwnership.browser.test.ts` but was **not** a Phase 0 scoreboard row. **No scoreboard change expected.**

---

## R. Phase 0 scoreboard before/after

| Metric | Before | After |
| ------ | ------ | ----- |
| Risk red | 6 | **6** |
| Migrated green | 12 | **12** |
| Controls green | 5 | **5** |

**Newly green:** (none — scoreboard unchanged by design)

---

## S. Consumer verification

All consumers use `serializePopupExport` → `compilePopupRenderCss` — Text CSS included automatically. Thumbnail/embed/preview tests pass.

---

## T. Deleted production code

| Item | Location |
| ---- | -------- |
| `buildTextBlockStyle` | `inputFieldStyles.ts` |
| Inline content `style` on `> div` | `text/serialize.ts` |
| `applyRichTextLinkStyles` on text export | `text/serialize.ts` |
| Text branch in `buildBlockTraitResponsiveCss` | `responsiveDeviceCss.ts` |
| Text selectors in `buildRichTextLinkExportCss` | `serializeLayout.ts` |

---

## U. Remaining `buildBlockTraitResponsiveCss` consumers

17 wrapped families: `divider`, `text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, `check-box`, `radio`, `date`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social`

Excluded: `button`, `text`, `cross`, `floating-image`

---

## V. Test evidence

| Suite | Tests |
| ----- | ----- |
| `textPresentationRenderCss.test.ts` | 14 pass |
| `textPresentation.browser.test.ts` | 5 pass |
| `blockDomOwnership.browser.test.ts` | text padding fixed |
| `popupHtmlSerializer.test.ts` | updated |
| Full regression batch | **143 pass** |

Command:

```bash
npx craco test --watchAll=false --testPathPattern="textPresentation|blockDomOwnership|responsiveCssBaseline|popupHtmlSerializer|responsiveDeviceCss|popupThumbnail|renderArtifactCss"
```

---

## W. Architecture verification

| Gate | Status |
| ---- | ------ |
| Proven DOM owners | ✅ |
| No wrapper/painted conflation | ✅ |
| Single normalization path | ✅ |
| No inline merchant on migrated owners | ✅ |
| Text excluded from generic responsive | ✅ |
| Link-color ownership resolved | ✅ |
| No permanent hybrid on same owner | ✅ |
| No `!important` on Text rules | ✅ |
| No compatibility branch | ✅ |
| Multi-step conflicts explicit | ✅ |
| Content/behavior unchanged | ✅ |
| Consumer renderCss parity | ✅ |
| Obsolete code deleted | ✅ |

---

## X. Next recommended family

**Input shell family** (`text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, `date`) — outline `div` + field painted owners per Phase 5B; highest remaining split-target surface after Text.

---

## Files touched (production)

| File | Change |
| ---- | ------ |
| `textPresentation.ts` | **New** family compiler |
| `compilePopupRenderCss.ts` | Wire text compiler + formStyle |
| `ElementStyles.ts` | `resolveTextPaintedCssRules`, `resolveTextWrapperCssRules`, refactor helpers |
| `text/serialize.ts` | Structure + content only |
| `serializeElements.ts` | Strip text wrapper layout inline |
| `responsiveDeviceCss.ts` | Exclude text |
| `serializeLayout.ts` | Remove text from link export CSS |
| `TextCanvas.tsx` | Use `getTextBlockContentStyle` |
| `inputFieldStyles.ts` | Delete `buildTextBlockStyle` |
