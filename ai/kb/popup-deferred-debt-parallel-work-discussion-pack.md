# Popup Builder — Deferred Debt Parallel Work Discussion Pack

**Date:** 2026-07-06  
**Purpose:** Discussion document for parallel work while **Spinner** (Phase 7G) and **Product** (final block migration) proceed on the merchant-CSS track.  
**Audience:** Engineering review (e.g. ChatGPT, team sync) — **not** an implementation spec.  
**Branch context:** `css-reft` @ `85c4a55f` (approx.)

---

## 1. Executive summary

The **merchant-CSS migration program** is nearly complete:

| Track | Status | Owner |
|-------|--------|-------|
| Block-family → `renderCss` | **19 migrated**, **2 remaining** (`spinner` in progress, `product` last) | Migration phases 5–7 |
| **Deferred debt** (this document) | **~8 workstreams**, mostly independent | Can run **in parallel** |

**Key insight:** Deferred items are **not** blocked on Spinner/Product finishing. They are explicitly **excluded** from migration phases to avoid scope creep. Most can start now without conflicting with `spinnerMerchantPresentation.ts` / `product/serialize.ts` work.

**Exception — coordinate before editing:**

- Shared files: `ElementStyles.ts`, `serializeLayout.ts`, `popupHtmlSanitize.ts`, `responsiveDeviceCss.ts`
- Any change to `compilePopupRenderCss.ts` registration order
- Phase 0 / G1 regression gates after each stream lands

---

## 2. What is NOT deferred (do not reopen)

These are **done** — parallel work must not regress them:

- Coupon, Image, Video, Divider, Checkbox, Radio, standard fields, Button shell, Text block renderCss, Social, Feedback, Timer
- Branch convergence / partial migrations
- G2B alignment + link inline strip (landed; see G2B report)
- G2B.2 forward-only orphan strip (`properties.align` etc.)

---

## 3. Parallel workstream map

```text
                    ┌─────────────────────────────────────┐
                    │  Merchant-CSS migration (sequential) │
                    │  Spinner (7G) → Product (7H)        │
                    └─────────────────────────────────────┘
                                      │
        ┌─────────────────────────────┼─────────────────────────────┐
        │                             │                             │
        ▼                             ▼                             ▼
  Stream A                    Stream B                    Stream C
  Advanced traits             Button label/RTE            G2C RTE semantics
  (customCss, orphans)        + hover/linked label        (Text dual-write)
        │                             │                             │
        ▼                             ▼                             ▼
  Stream D                    Stream E                    Stream F
  Structural-inline           Checkbox token audit        Form/surface customCss
  final cleanup               (verify 6H closure)         policy
```

| Stream | Effort | Risk | Conflicts with Spinner? |
|--------|--------|------|------------------------|
| **A** Advanced trait orphans | M | Low | **No** |
| **B** Button label / hover | M–L | Medium | **No** |
| **C** G2C RTE semantics | **L** | **High** | **No** (Text only) |
| **D** Structural-inline cleanup | M | Medium | **Low** (touch serializers) |
| **E** Checkbox audit | S | Low | **No** |
| **F** Global customCss policy | S–M | Low | **No** |

**Recommended parallel picks while Spinner runs:** **A + E** (small, isolated), or **B** (well-audited, decision-ready).

---

## 4. Workstream details

### 4.1 Stream A — Advanced trait orphans (`customCss` / `customClass`)

**Problem:** Many blocks expose **Advanced** panel fields that persist to `style.customCss` / `style.customClass` but are **not** compiled into artifact-scoped `renderCss`. Merchants can type CSS that bypasses the migration architecture.

**Evidence (panel writers exist):**

| Block | `customClass` writer | `customCss` writer | In renderCss compiler? |
|-------|---------------------|-------------------|------------------------|
| Social | `SocialProperties.tsx` | yes | **No** |
| Feedback | (advanced tab) | yes | **No** |
| Coupon | (advanced) | yes | **No** |
| Divider, Date, Email, Textarea, Spinner, Section, … | yes | yes | **No** (global or per-block) |

**Export path today:** `serializeLayout.ts` injects **form-level** `formStyle.customCss` via `sanitizeCustomCss()` into `[data-adflipr-popup-custom-css]` style block — **global to the popup**, not artifact-scoped per block.

**Classification options (for discussion):**

| Option | Description | Pros | Cons |
|--------|-------------|------|------|
| **A1 — Remove UI** | Hide advanced fields until implemented | Stops false promise | Breaks merchants using it |
| **A2 — Compile per block** | Scope customCss into renderCss with block selector | Architecturally correct | Sanitization / security review |
| **A3 — Document as escape hatch** | Keep global injection; document as unsupported | Zero code | Technical debt remains |
| **A4 — Strip on save** | Forward-only remove customCss from snapshots | Clean exports | Data loss |

**Related orphans:**

| Field | Block | Panel writer? | Consumer |
|-------|-------|---------------|----------|
| `style.elementWidth` | Image | **No** (only `imageWidth`) | `resolveImagePaintedPresentationCssRules` reads `elementWidth` as fallback? — verify dead path |
| `boxShadowEnabled` + flat fields | Coupon | **No** (uses `boxShadowDesktop/Mobile` elsewhere) | `mergeBoxShadowIntoInlineStyle` legacy path in `ElementStyles.ts` |

**Suggested first slice:** Coupon `boxShadowEnabled` legacy reader — add panel writer **or** delete legacy reader with migration note.

**Files:** `ElementStyles.ts`, `*Properties.tsx`, `couponPresentation.ts`, `serializeLayout.ts`

---

### 4.2 Stream B — Button linked-label + hover defect

**Problem (recorded across Phase 7B–7D reports):** Button supports **RTE link insertion** in label (`ButtonText.tsx` / `InlineEditor`), but hover presentation for the **button shell** vs **linked label** may not match merchant intent. Export uses `buildRichTextLinkExportCss` + `applyRichTextLinkStyles` (structural/global link color), while button hover is artifact-scoped in `buttonPresentation.ts` (`:hover` on painted button).

**Evidence:**

- `ButtonProperties.tsx` writes `hoverBackgroundColor`, `hoverTextColor` → `resolveButtonHoverCssRules` → renderCss
- `ButtonCanvas.tsx` applies hover via mouse events on canvas (preview only)
- H3 audit §34: **linked label is a current supported writer** — not accidental
- Dedicated audit: [`popup-button-label-persistence-cleanup-audit.md`](./popup-button-label-persistence-cleanup-audit.md)

**Open product questions:**

1. Should linked label `<a>` inside button inherit button `textColor` or use global rich-text link color?
2. On hover, should link color change with `hoverTextColor`?
3. Should Button RTE typography commands be removed (block uses trait-only model per G2A.1)?

**Suggested approach:** Decision doc first (like G2A.1), then minimal fix — **do not** mix with Spinner.

**Files:** `buttonPresentation.ts`, `ButtonCanvas.tsx`, `serialize.ts` (button), rich-text link export CSS

---

### 4.3 Stream C — G2C RTE semantics (Text block)

**Problem:** Text block has **intentional dual-write** preserved in G2B:

- RTE color apply → updates **both** span inline color **and** `style.textColor` trait
- Font size → caret/mobile may update trait; range selection stays inline

**Deferred per G2A.1 / G2B:**

- Selection vs block command semantics
- Bold/italic/color at 100% selection → trait? (**Rejected** in G2A.1)
- `RTE_COMMAND_REGISTRY` / `dispatchRteCommand`
- Paragraph alignment product model

**Canonical decision doc:** [`popup-rte-command-semantics-g2a1-decision.md`](./popup-rte-command-semantics-g2a1-decision.md)  
**G2B landed fixes:** alignment migration, link inline strip, transparent bg noise  
**G2B.2:** forward-only orphan strip on save

**Why high risk:** Touches merchant-visible editing behavior, save normalization, renderCss cascade for Text.

**Suggested approach:** Phase **G2C** as its own program — command registry + explicit ownership table — **not** a side task during Spinner.

**Files:** `TextCanvas.tsx`, `InlineEditor`, `textRichContentPersistence.ts`, `textPresentation.ts`

---

### 4.4 Stream D — Structural-inline final cleanup

**Problem:** Migrated blocks may still emit **legitimate structural** inline (`box-sizing`, `pointer-events`, `display:none` on hidden radios). Reports track a **final pass** to move repeated patterns into `*.export.css` and reduce serializer inline to identity/content/behavior only.

**Examples still using `styleAttr` (verify classification):**

| File | Notes |
|------|-------|
| `button/serialize.ts` | align wrapper `text-align` — structural |
| `floating-image/serialize.ts` | `pointerEvents: auto` — behavior |
| `spinner/serialize.ts` | **being migrated** — do not cleanup until 7G done |
| `product/serialize.ts` | **pending migration** |

**Rule:** Only cleanup **migrated** families. Do not touch spinner/product serializers until their phases complete.

**Suggested approach:** Checklist per migrated family: serializer negative assertions + move structural rules to export sheets.

---

### 4.5 Stream E — Checkbox `outlineColor` / `backgroundColor` “orphan” verification

**Recorded debt:** Phase 6H reports listed `outlineColor` / `backgroundColor` as dead traits.

**Current code (may be stale debt label):**

- `CheckBoxProperties.tsx` **does** write `outlineColor`
- `resolveCheckboxColorTokenCssRules` maps `--pbn-checkbox-outline` and `--pbn-checkbox-bg` from `outlineColor` / `backgroundColor`
- **No** `backgroundColor` in checkbox panel grep — may still be orphan

**Action:** Close or downgrade this debt item with a **15-minute audit**:

1. Panel field inventory vs `resolveCheckbox*` consumers
2. Browser test already uses `outlineColor` in `checkboxPresentation.browser.test.ts`

**Outcome options:** Mark **CLOSED** (outline wired) + open **new** ticket for `backgroundColor` only if panel missing.

---

### 4.6 Stream F — Form-level `customCss` injection

**Problem:** `formStyle.customCss` → global `<style data-adflipr-popup-custom-css>` in every export. Not artifact-scoped; not part of `compilePopupRenderCss`.

**Discussion:** Is this intentional merchant escape hatch or architecture violation?

**Files:** `serializeLayout.ts`, `popupHtmlSanitize.ts` (`sanitizeCustomCss`)

---

## 5. Cross-cutting gates (every stream)

| Gate | Command / test | Required |
|------|----------------|----------|
| Phase 0 | `npm run test:popup-responsive-baseline` (or column/button browser suites) | 18 green / 0 red / 5 controls |
| G1 | `npm run test:export-css-production-contract` | PASS |
| Production build | `npm run build` | PASS |

**Merge policy:** Deferred streams should land in **small PRs** with family-scoped tests; avoid mega-PR mixing G2C + Button + traits.

---

## 6. Decision questions for ChatGPT / team discussion

Copy these into a ChatGPT thread:

1. **Parallelization:** Which 2 streams should we run alongside Spinner 7G without reviewer fatigue?
2. **customCss policy:** Remove UI (A1), compile scoped (A2), or document escape hatch (A3)?
3. **Button linked-label:** Should `<a>` inside button use button text color or global link sheet color on export?
4. **Button hover + link:** Does `:hover` on painted button suffice for linked labels, or do we need `a:hover` descendant rules in renderCss?
5. **G2C priority:** Is Text dual-write fix urgent for merchants, or can it wait until Product migration completes?
6. **Checkbox debt:** Is `outlineColor` debt closed after Phase 6H tokens? Is `backgroundColor` still orphan?
7. **Coupon boxShadow:** Delete legacy `boxShadowEnabled` reader or add panel controls?
8. **Image elementWidth:** Delete dead resolver branch or add panel writer?
9. **Structural-inline pass:** Batch by family or one horizontal PR after Product lands?
10. **Ownership:** Should deferred streams use `deferred/` branch prefix or `g2c/` / `traits/` naming?

---

## 7. Suggested parallel assignment (example)

| Agent / developer | Focus | Est. duration |
|-------------------|-------|---------------|
| Agent A (in progress) | Spinner 7G migration | 1–2 days |
| Agent B (next) | Product 7H migration | 2–3 days |
| **Parallel slot 1** | Stream E checkbox audit + Stream A coupon boxShadow slice | 0.5 day |
| **Parallel slot 2** | Stream B button decision doc + hover/link spike | 1 day |
| **Parallel slot 3** | Stream C G2C planning only (no code) | 0.5 day |
| **After Product** | Stream D structural-inline checklist | 1 day |

---

## 8. Source index (canonical KB)

| Topic | Document |
|-------|----------|
| Migration state | [`popup-css-migration-current-state.md`](./popup-css-migration-current-state.md) |
| RTE persistence (G2B) | [`popup-rte-trait-css-persistence-phase-g2b-report.md`](./popup-rte-trait-css-persistence-phase-g2b-report.md) |
| RTE semantics decision | [`popup-rte-command-semantics-g2a1-decision.md`](./popup-rte-command-semantics-g2a1-decision.md) |
| Text forward-only (G2B.2) | [`popup-text-persistence-forward-only-cleanup-g2b2-report.md`](./popup-text-persistence-forward-only-cleanup-g2b2-report.md) |
| Button label audit | [`popup-button-label-persistence-cleanup-audit.md`](./popup-button-label-persistence-cleanup-audit.md) |
| Global trait audit | [`popup-global-trait-presentation-architecture-audit.md`](./popup-global-trait-presentation-architecture-audit.md) |
| H3 grouping | [`popup-remaining-inline-css-migration-grouping-phase-h3-audit.md`](./popup-remaining-inline-css-migration-grouping-phase-h3-audit.md) |
| Feedback + Timer 7F | [`popup-feedback-cleanup-timer-presentation-phase7f-report.md`](./popup-feedback-cleanup-timer-presentation-phase7f-report.md) |

---

## 9. Recommended verdict for discussion

**Yes — deferred work can run in parallel** with Spinner, provided:

1. **No edits** to `spinner/` serializer/compiler until 7G owner merges  
2. **Prefer streams A, B, E** for immediate parallel work  
3. **Defer stream C (G2C)** to a dedicated phase with its own report gate  
4. **Defer stream D** mostly until Product migration completes (avoids serializer conflicts)

**First parallel PR recommendation:** **Stream E + Coupon boxShadow legacy cleanup** — smallest blast radius, closes stale debt labels, improves KB accuracy.

---

## 10. ChatGPT prompt stub

Paste below into ChatGPT after attaching this file:

```text
You are reviewing the Adflipr Popup Builder CSS architecture. Read the attached
"Deferred Debt Parallel Work Discussion Pack" and:

1. Rank workstreams A–F by ROI and risk for parallel execution during Spinner migration.
2. Answer decision questions §6 with explicit recommendations.
3. Propose a 2-week plan with max 2 parallel code streams + migration track.
4. Flag any workstream that would conflict with artifact-scoped renderCss invariants.
5. Draft acceptance criteria for the first parallel PR (Stream E + coupon boxShadow).

Constraints:
- Do not reopen migrated block families (coupon, image, video, social, feedback, timer, etc.).
- No backward compatibility layers unless explicitly justified.
- Merchant presentation must stay in renderCss; behavior in runtime/structural sheets.
```
