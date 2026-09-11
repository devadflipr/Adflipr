# Popup Builder PR Review — Output Template

Use this template for **every** Popup Builder PR review. Fill all sections. Mark **Not verified** when evidence is insufficient.

---

## Executive Summary

One paragraph: what changed, risk level, and verdict preview.

---

## Functional Correctness

Will the feature actually work end-to-end?

- Edit → save → API persist → preview → embed → storefront
- Broken rendering paths
- State / history / undo issues
- Event handling, validation, form submit
- Animation and trigger interactions

---

## Rendering Review

Compare environments mentally or via tests:

| Environment | Verified? | Notes |
|-------------|-----------|-------|
| Builder canvas | | |
| Builder preview | | |
| Serializer output HTML | | |
| Embed runtime DOM | | |
| Storefront (Shopify/WP) | | |

Document transformations at each boundary.

---

## Mobile Review

Viewports: 320, 360, 375, 390, 414, 768, 1024, 1280+

- Wrapping, spacing, alignment
- Overflow and scroll
- Touch targets (min 44px where interactive)
- Visibility / device toggles (`pbnDeviceVisibility`)
- Preview vs embed at 480px

---

## CSS Review

- Specificity and cascade
- `!important` usage (tactical vs permanent)
- Inline style conflicts
- Media query conflicts
- CSS variable origin and scope
- Dead / duplicate CSS
- Host page conflict risk (`[data-adflipr-popup-artifact]`, export scoping)
- `renderCss` vs legacy inline paths

---

## HTML Review

- Semantic correctness
- Stable `data-adflipr-*` identity
- Builder vs preview vs runtime markup differences
- Forms, inputs, buttons, labels
- Accessibility (ARIA, associated labels)
- DOM stability on step transitions

---

## Serializer Review

- Artifact shape (`htmlContent`, `htmlByStep`, `renderCss`, `contractVersion`, `serializerBuild`)
- Versioning and backward compatibility
- HTML/CSS single compilation
- Migration impact on published popups
- Security (XSS, unsafe attrs) per `popup-html-serialization.md`

---

## Runtime Review

- Mount / unmount / destroy lifecycle
- Stylesheet ownership (one per artifact)
- Event listeners, timers, observers — cleanup on dismiss
- Memory leak risk
- Overlay, backdrop, focus trap, keyboard
- Positioning, z-index, scroll-lock
- Step transition behavior

---

## Architecture Review

- Consistency with `popup-rendering-css-architecture.md` (target)
- Contract violations (`contract/`, embed attrs)
- Encapsulation and ownership (block vs shell vs merchant CSS)
- Coupling across builder ↔ serializer ↔ embed
- Duplication and naming
- Extensibility for new blocks
- Backward compatibility

---

## Regression Risks

Predict regressions even in untouched files:

- Other block families sharing utilities
- Legacy artifacts without `renderCss`
- Multi-step popups
- Flyout / banner / full-page form types
- Spinner / gamification flows
- Product block (store-connected)

---

## Missing Tests

- Serializer unit tests
- Computed-style / browser tests (desktop + mobile)
- Embed lifecycle tests
- Contract tests
- E2E (`adflipr-app/e2e/popup-builder-*.spec.ts`)

---

## Suggested Improvements

Non-blocking enhancements with rationale.

---

## Blocking Issues

Must fix before merge. Format:

- **Severity:** blocker
- **Location:** path:line
- **Issue:**
- **Impact:**
- **Suggested fix:**

---

## Non-blocking Suggestions

Minor / suggestion items.

---

## Overall Verdict

Exactly one of:

- **APPROVE**
- **APPROVE WITH SUGGESTIONS**
- **REQUEST CHANGES**
- **BLOCK**
