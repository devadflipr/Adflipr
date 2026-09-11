# Phase 7F — Feedback Merchant Presentation + Interactive-State Ownership Report

**Date:** 2026-07-06  
**Branch:** `css-reft`  
**HEAD:** `85c4a55f7421efee3a421afcc15fc1dc18629ee5`  
**Verdict:** **ACCEPT**

---

## 1. Executive verdict

Feedback merchant presentation is fully migrated to artifact-scoped `renderCss` via `feedbackPresentation.ts`, with explicit separation of **state activation** (`:checked` / native radio) from **merchant state appearance** (`activeColor`, `inactiveColor` via CSS tokens and `:has()` selectors). Canvas now uses the same canonical resolvers as export. Social remains fully migrated. **2 families remain:** spinner, product.

---

## 2. Starting baseline

| Check | Result |
|---|---|
| Social compiler active | YES |
| Social inline merchant CSS absent | YES |
| Social generic responsive absent | YES |
| Feedback compiler absent (pre-7F spec) | NO — compiler landed in 7E-C branch; canvas still duplicated |
| Feedback merchant inline CSS present (pre-7F) | NO in serializer; YES in FeedbackCanvas |
| Feedback generic responsive present (pre-7F) | NO — already in `MIGRATED_BLOCK_TRAIT_TYPES` |

**Phase 7F starting baseline valid: YES** (serializer/compiler path complete; canvas parity gap remained)

---

## 3–7. Variants, DOM, owners (summary)

**Supported variants:** `rating` (stars), `nps` (0–10), `emoji`, `thumbs`

**DOM topology (all variants):**
```
[data-adflipr-block-id] wrapper
  └─ [data-adflipr-feedback=true].pbn-feedback-shell[data-feedback-type]
       ├─ p.pbn-feedback-question
       ├─ div.pbn-feedback-options[role=radiogroup]
       │    └─ label.pbn-feedback-option
       │         ├─ input.pbn-feedback-native-radio[--nps]
       │         └─ content (digit/★/emoji/thumb)
       ├─ div.pbn-feedback-labels (rating/nps + showLabels)
       └─ div.pbn-feedback-additional (optional rich text)
```

| Owner | Node |
|---|---|
| Identity | `[data-adflipr-block-id]` |
| Block layout | wrapper (margin/width) |
| Shell | `[data-adflipr-feedback]` |
| Question | `.pbn-feedback-question` |
| Option (inactive) | `.pbn-feedback-option` |
| Option (active) | `.pbn-feedback-option:has(.pbn-feedback-native-radio:checked)` |
| Native control | `input.pbn-feedback-native-radio` |
| Labels row | `.pbn-feedback-labels` |

---

## 8–11. Input/label mechanics & runtime

- Label wraps input; `for`/`id` paired
- Star/emoji/thumbs: input `display:none` via `feedback.export.css`
- NPS: visible radio; `accent-color: var(--pbn-feedback-active)` in structural sheet
- Selection: native `:checked` — no runtime class injection on export
- Canvas preview: React `checked` state simulates selection (behavior only)
- No embed feedback initializer — runtime compatible: **YES**

---

## 12. State architecture decision

**Selected: A — Stateful selectors in renderCss**

- Merchant inactive: `color: var(--pbn-feedback-inactive)` on `.pbn-feedback-option`
- Merchant active: `:has(.pbn-feedback-native-radio:checked)` + `resolveFeedbackSelectedOptionPresentationCssRules`
- Shell sets `--pbn-feedback-active` / `--pbn-feedback-inactive` tokens (merchant values)

**Rejected B:** No runtime class hooks needed — native `:checked` sufficient  
**Rejected C as primary:** Variables used only as token bridge for NPS `accent-color`, not as sole presentation path  
**Rejected D:** Migration proven safe

---

## 13–27. Implementation summary

| Module | Role |
|---|---|
| `feedbackPresentation.ts` | Compiler — wrapper, shell, question, options, selected state, labels, additional |
| `ElementStyles.ts` | Canonical resolvers + canvas adapters |
| `feedback.export.css` | Hidden radios, NPS accent scaffold, footer spacing |
| `serialize.ts` | Structure-only export |
| `FeedbackCanvas.tsx` | Canvas adapters (Phase 7F completion) |

**Mobile strategy:** DIFF-ONLY on wrapper + shell  
**Conflict:** `FeedbackPresentationConflictError` on presentation mismatch across steps  
**Presentation key excludes:** question text, option values, runtime selection

---

## 28–42. Verification

| Gate | Result |
|---|---|
| Merchant inline CSS | NONE |
| Merchant state inline CSS | NONE |
| Generic responsive | Excluded (`feedback` in `MIGRATED_BLOCK_TRAIT_TYPES`) |
| Global merchant Feedback CSS | NONE |
| Selected-state computed-style | PASS (Chromium `:has(:checked)`) |
| Artifact isolation | PASS |
| Hostile-theme | PASS |
| Cross-family (Feedback+Social+Image+Coupon) | PASS |
| G1 | PASS |
| Production build | PASS |
| Phase 0 | 18/0/5 |

---

## 43–54. Remaining debt & recommendation

**Feedback deferred:** `customCss` advanced trait (not compiled)

**Remaining merchant-CSS families:** spinner, product

**Recommended next family:** **spinner** (animation mechanics vs presentation separation required)
