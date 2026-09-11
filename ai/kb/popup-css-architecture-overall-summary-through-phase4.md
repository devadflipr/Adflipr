# Popup CSS Architecture — Overall Summary

**Status:** Through Phase 5C (2026-07-05)  
**Detailed reports:** [`popup-button-presentation-css-phase5c-report.md`](./popup-button-presentation-css-phase5c-report.md), [`popup-block-ownership-contract-phase5b-audit.md`](./popup-block-ownership-contract-phase5b-audit.md), [`popup-block-css-phase5a-ownership-audit.md`](./popup-block-css-phase5a-ownership-audit.md), [`popup-rendering-css-architecture.md`](./popup-rendering-css-architecture.md)

---

We redesigned the popup rendering architecture after discovering that the previous responsive CSS model was fundamentally unreliable.
The old implementation mixed:

Desktop presentation in inline style="" attributes
Mobile presentation in embedded @media CSS
Shared CSS inside serialized HTML
Runtime CSS and popup-specific CSS
Multiple compatibility branches and skip flags

This caused a core CSS cascade problem: desktop inline styles had higher priority than normal mobile stylesheet rules. As a result, many mobile settings configured in the builder did not actually appear on the storefront.
Real Chromium computed-style tests confirmed the issue across form surfaces, layouts, blocks, positioning, floating elements, and teasers.
We replaced this with a compiled popup artifact architecture.
The popup builder now produces one complete artifact:
CompiledPopupArtifact
├── artifactScopeId
├── htmlContent
├── htmlByStep
├── primaryStepId
└── renderCss
The responsibilities are now clearly separated:
HTML
→ structure
→ stable element identity
→ content
→ runtime behavior hooks

renderCss
→ popup-specific presentation
→ desktop styles
→ mobile responsive styles

JavaScript runtime
→ triggers
→ step transitions
→ form behavior
→ timers
→ coupon behavior
→ other interactive functionality
Popup-specific JavaScript is not stored with the popup. Behavior remains centralized in the shared Adflipr runtime.
The central CSS rule is now:
Desktop and mobile presentation for the same DOM owner
must be compiled together into renderCss.
For example:
[data-adflipr-popup-artifact="popup-123"]
[data-adflipr-popup-surface] {
    padding: 24px;
}

@media (max-width: 480px) {
    [data-adflipr-popup-artifact="popup-123"]
    [data-adflipr-popup-surface] {
        padding: 12px;
    }
}
This removes the previous conflict between desktop inline CSS and mobile stylesheet CSS.
The architecture now explicitly models CSS ownership according to the actual DOM hierarchy:
Popup Surface
    ↓
Outer Layout
    ↓
Columns Container
    ↓
Column
    ↓
Block
Each migrated owner is responsible for its own presentation.
The following presentation areas are now compiled into renderCss:

Popup form surface
Outer layouts
Columns containers
Individual columns
Gamification shell and slots
Button block (Phase 5C — first migrated block family)

The serializer no longer keeps migrated presentation inline in the HTML.
The generated HTML is becoming primarily structural:
<form data-adflipr-popup-artifact="popup-123">

    <div data-adflipr-popup-surface>

        <div data-adflipr-layout-id="layout-1">

            <div data-adflipr-popup-columns>

                <div data-column-id="column-1">

                    <div data-adflipr-block-id="block-button-1">

                        <div><!-- alignment container --></div>

                            <button type="submit">
                                <!-- label content -->
                            </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

</form>
The matching presentation is compiled separately:
HTML
+
renderCss
=
complete visual popup artifact
This contract is used consistently across:

Storefront popup runtime
Builder preview
Fullscreen preview
Popup detail thumbnail
Campaign list thumbnail
Template import
Popup duplication
Test-live flows

The runtime mounts renderCss once for a popup runtime instance.
The stylesheet:

Remains mounted during step transitions
Is not duplicated when the popup reopens
Can be replaced when the runtime receives a different popup configuration
Is removed when the runtime is destroyed

Two identities have intentionally separate responsibilities:
artifactScopeId
→ scopes compiled CSS selectors

runtimeInstanceId
→ owns stylesheet mount and teardown lifecycle
This allows multiple popup instances to coexist safely.
For example, two runtime instances can render the same popup artifact while independently owning their mounted stylesheet nodes.
The architecture is now forward-only.
We removed transitional concepts such as:
new architecture
+
legacy inline architecture
+
compatibility flags
+
skip flags
There is no longer a requirement to preserve old popup rendering behavior.
The production serializer requires a stable artifactScopeId, and compiled popup output always follows the new artifact contract.
Gamification also uses the same overall architecture.
Only its genuinely unique DOM owners use a dedicated compiler:
Gamification shell
→ dedicated gamification compiler

Inner layouts
→ standard layout compiler

Columns container
→ standard columns compiler

Columns
→ standard column compiler
This avoids maintaining a separate legacy rendering system for gamification popups.
The current high-level pipeline is:
Popup Builder Model
        ↓
Production Serializer
        ↓
CompiledPopupArtifact
        │
        ├── Structural HTML
        │
        └── Compiled renderCss
              ├── compileFormSurfaceRenderCss
              ├── compileOuterLayoutRenderCss
              ├── compileColumnsContainerRenderCss
              ├── compileColumnPresentationRenderCss
              ├── compileButtonPresentationRenderCss
              └── compileGamificationRenderCss (when applicable)
        ↓
Database / API
        ↓
Storefront Runtime
        ├── mounts HTML
        ├── mounts matching renderCss
        └── attaches centralized JavaScript behavior
The architecture has been verified using real Chromium computed-style tests rather than only checking generated CSS strings.
The completed work established these main guarantees:
1. Responsive CSS no longer depends on overriding desktop inline styles.

2. HTML and CSS are treated as parts of the same compiled popup artifact.

3. Desktop and mobile presentation are generated together.

4. CSS is scoped to a stable popup artifact identity.

5. Runtime stylesheet lifecycle is independent from CSS selector identity.

6. Migrated DOM owners no longer maintain parallel inline and renderCss implementations.

7. Gamification follows the same compiled artifact architecture.

8. The production architecture is forward-only, without legacy compatibility branches.

9. Popup-specific JavaScript is not stored with popup content.

10. Real browser tests verify actual computed presentation.

---

## Phase 5 — Block-level presentation (5A through 5C)

Phases 1–4 migrated surface, layout, columns, and gamification. Phase 5 addresses **blocks**, which are more complex because a block can contain several different DOM owners:
Block identity wrapper
    ↓
Layout wrapper
    ↓
Actual painted element
    ↓
Content element
    ↓
Behavior element

### Phase 5A — ownership audit

Phase 5A established that **block CSS is not one migration owner**.

Block presentation is not one coherent DOM surface. Wrapped blocks share a common export wrapper (`[data-adflipr-block-id]`), but painted, content, and behavior owners differ by block family. The generic `buildBlockTraitResponsiveCss()` assumed the wrapper was the presentation target — that assumption is false for most merchant-controlled traits.

For example, a button had:
Desktop padding
→ inner <button>

Mobile padding
→ outer block wrapper

This is a **split-target problem**. Simply moving the existing block CSS into renderCss would preserve the bug.

Phase 0 computed-style scoreboard confirmed `block-padding` and `block-margin` as red (split-target and PCL-008 cascade failure).

### Phase 5B — block ownership contract

Phase 5B decided on a **partial hybrid contract (Option C)**:

**Enforced in production:**
- **Identity invariant** — every content block exposes `[data-adflipr-block-id="{element.id}"]` under `[data-adflipr-popup-artifact="{scope}"]` (or on unwrapped root for `cross` / `floating-image`).
- **Layout invariant** — wrapped blocks receive export wrapper layout from `serializeElements.ts`: block margin, width %, alignment container hooks, device-hide attrs.
- **Painted invariant (compiler-local)** — each block-family compiler declares its own painted selector(s); no shared painted selector constant.

**Not a universal contract:**
- Content owner — folded into painted compiler rules where relevant.
- Behavior owner — remains in embed initializers; not part of renderCss compilation.

**Compiler architecture:** one compiler per **family** (~8 modules), not one universal block compiler and not one compiler per block type. All family compilers integrate only through `compilePopupRenderCss()`.

Recommended family compilers (5B plan):
```text
buttonPresentation.ts          // button — DONE (Phase 5C)
textPresentation.ts            // text + RTE link color rules — next
inputShellPresentation.ts      // text-input, email, phone, textarea, dropdown, date
choiceGroupPresentation.ts     // check-box, radio
mediaPresentation.ts           // image, video, divider
positionedDecorativePresentation.ts  // cross, floating-image
commercePresentation.ts        // product, coupon, feedback, social
runtimeChromePresentation.ts   // timer, spinner
```

### Phase 5C — Button (first block family migration)

Phase 5C migrated **Button** merchant presentation from serialized inline CSS and generic embedded responsive trait rules into `buttonPresentation.ts` → `compileButtonPresentationRenderCss()`.

**Button DOM ownership (after migration):**
```text
Wrapper [data-adflipr-block-id]
├── identity
├── block margin (desktop base + mobile @media)
└── (no merchant painted chrome)

Alignment container (> div)
├── structural width: 100%
├── structural text-align (from align trait)
└── elementBackgroundColor (renderCss)

<button>
├── padding (desktop base + mobile @media full painted rule)
├── border-radius, background, border, box-shadow
├── typography
├── width mode (full / fit + fixedWidth)
├── fit/alignment margins
└── structural flex shell
```

**What changed:**
- `<button>` no longer carries merchant painted `style=""` attributes.
- Wrapper block margin moved from inline to renderCss (desktop + mobile together).
- Button excluded from `buildBlockTraitResponsiveCss()` — no wrapper-targeted mobile padding/margin/radius/font.
- Shared normalizer `resolveButtonPaintedCssRules` — canvas and compiler consume the same trait interpretation.
- Multi-step union: same blockId + identical presentation → single CSS emission; conflicts → `ButtonPresentationConflictError`.

**What was preserved:**
- Label content / RTE HTML, `type`, action attrs (`data-adflipr-popup-action*`), runtime behavior — unchanged in serializer.

**Phase 0 scoreboard after 5C:**

| Metric | Before 5C | After 5C |
| ------ | --------- | -------- |
| Risk red | 8 | **6** |
| Migrated green | 10 | **12** |

**Newly green:** `block-padding`, `block-margin`

**Still red (6):** `cross-top`, `cross-right`, `floating-bottom`, `floating-left`, `teaser-padding`, `teaser-border-radius`

**Test evidence:** `buttonPresentationRenderCss.test.ts`, `buttonPresentation.browser.test.ts`, `blockDomOwnership.browser.test.ts`, Phase 0 baseline harness.

---

## Remaining work (after Phase 5C)

**Block families still on legacy path** — 18 wrapped content types still depend on `buildBlockTraitResponsiveCss()` and/or inline painted styles:

`text`, `divider`, `text-input`, `email-input`, `phone-input`, `textarea`, `dropdown`, `check-box`, `radio`, `date`, `timer`, `spinner`, `image`, `video`, `product`, `coupon`, `feedback`, `social`

**Already excluded or migrated:**
- `button` — Phase 5C (renderCss family compiler)
- `cross`, `floating-image` — dedicated embedded generators today; target `positionedDecorativePresentation` in renderCss

**Next recommended family:** `text` (inner content `div` painted owner; split-target equivalent to button padding).

**Non-block red scoreboard items:** cross positioning, floating-image positioning, teaser presentation — separate from generic block trait migration.

**Deletion target (future):** `buildBlockTraitResponsiveCss()` wrapper trait loop — delete as each family migrates to its own compiler; do not hoist wholesale into renderCss.

---

The overall result so far is that the popup system has moved from a mixed inline/embedded responsive CSS model to a scoped, compiled artifact architecture where structural HTML, popup-specific presentation, and runtime behavior have clear and separate responsibilities. Phases 1–4 established the artifact model for surface through gamification; Phase 5 extends that model into blocks through per-family compilers, with Button as the first completed migration.
