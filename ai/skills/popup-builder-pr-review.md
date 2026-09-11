# Skill: popup-builder-pr-review

**Goal:** Perform an exhaustive Popup Builder PR review with functional correctness, rendering parity, and architecture contract validation.

**Agent:** `ai/agents/popup-builder-reviewer.md`  
**Output template:** `ai/prompts/popup-builder-pr-review-template.md`

---

## Phase 1 — Build domain knowledge (before reviewing)

Do **not** assume. Read or refresh:

1. `ai/kb/popup-builder-reviewer-module-map.md` — full module index + lifecycle diagram.
2. Skim changed areas in the map; load linked KB docs for those modules only.
3. If the PR touches an unfamiliar block family, read its phase report in `ai/kb/` (search `popup-*-presentation-*` or `popup-*-phase*`).

**Confirm understanding of:**

- BuilderState → save metadata → API → embed artifact → DOM injection → customer browser.
- Builder vs preview vs serializer HTML vs runtime HTML differences.
- CSS ownership: inline vs `renderCss` vs embed shell vs host page.
- Responsive: shared HTML + `@media` in artifact (not separate mobile HTML trees).

---

## Phase 2 — Obtain change context

1. Get diff: `git diff main...HEAD` or user-supplied diff/PR URL (`gh pr diff`).
2. List touched paths; classify each into: **builder** | **serializer** | **embed** | **contract** | **preview** | **API/backend** | **tests** | **storefront (WP/Shopify)**.
3. Expand review scope using downstream tracing table in `ai/agents/popup-builder-reviewer.md`.
4. Read **unaffected but dependent** files when contract or shared utilities changed.

---

## Phase 3 — Functional correctness review

For each changed behavior, verify:

- [ ] Feature works end-to-end (edit → save → preview → embed).
- [ ] No broken desktop/tablet/mobile rendering.
- [ ] No preview ↔ storefront mismatch.
- [ ] No serializer ↔ runtime HTML/CSS mismatch.
- [ ] State/history/undo not corrupted.
- [ ] Events, animations, positioning, z-index, scroll-lock still correct.
- [ ] Accessibility: labels, ARIA, focus, keyboard where touched.
- [ ] Empty popup, single element, long text, reopen, resize, orientation (mental or test-backed).

---

## Phase 4 — Checklist application

### Always

- `ai/verification/code-review-checklist.md` (quality, tests, naming)

### When touching serializer / export / CSS artifact

- `ai/verification/popup-rendering-css-architecture-checklist.md`
- `ai/kb/popup-rendering-css-architecture.md` (target invariants)

### When touching structure / DnD

- `ai/kb/popup-builder-structure-dnd.md`
- `ai/kb/popup-builder-rules-inventory.md`

### When touching embed / storefront

- `ai/kb/popup-embed-contract.md`
- `ai/kb/popup-runtime-v2-design.md` (if lifecycle changed)

### When touching backend

- `ai/kb/popups-api.md`
- `service/.../PopupEmbedTriggerSupport` behavior if metadata/triggers changed

---

## Phase 5 — Responsive mental validation

Validate at: **320, 360, 375, 390, 414, 768, 1024, 1280px** and large desktop.

Check: wrapping, spacing, alignment, overflow, touch targets, visibility, stacking.

---

## Phase 6 — Produce report

Use **every section** in `ai/prompts/popup-builder-pr-review-template.md`:

Executive Summary → Functional Correctness → Rendering → Mobile → CSS → HTML → Serializer → Runtime → Architecture → Regression Risks → Missing Tests → Suggested Improvements → Blocking Issues → Non-blocking Suggestions → **Overall Verdict**.

**Verdict:** APPROVE | APPROVE WITH SUGGESTIONS | REQUEST CHANGES | BLOCK

For each issue: severity, file:line, explanation, suggested fix, downstream impact.

---

## Phase 7 — Self-learning

After the review, note:

- New patterns discovered
- KB drift (doc vs code)
- Recurring mistake classes
- Recommended KB updates (file + section) — do not silently edit KB
