# AI knowledge design — Adflipr (workbook)

Designer notes: **as-built analysis**, **target shape**, **KB updater workflow** (human-in-the-loop), and **how dev + AI evolve KB**.

---

## Step 1 — Current structure (as-built)

| Location | What lives there |
|----------|------------------|
| **`ai/`** | Canonical **process + compressed KB** per `.cursor/rules/ai-folder-canonical.mdc`: `kb/`, `skills/`, `prompts/`, `verification/`, `agents/`, `commands/`, `docs/` (tooling/review), `repo-brain.md`. |
| **`ai/kb/`** | Short playbooks: `architecture.md`, `guardrails.md`, `patterns.md`, `block-anatomy.md`, topic files (`product-block-kb.md`, `elasticsearch-mongo-indexing.md`, …), `README.md`. |
| **`ai/skills/`** | Repeatable workflows (refactor block, JS→TS, review-commit, …). |
| **`ai/prompts/`** | Task templates (refactor, debug, review) + **kb-*** prompts (this rollout). |
| **`ai/verification/`** | `architecture-checklist.md`, `code-review-checklist.md`, `README.md`. |
| **`docs/`** | **Long-form** product/architecture KB (Email Builder pipeline, traits canonical doc, infra). **`docs/kb/INVENTORY.md`** catalogs paths and merge status. |
| **`.cursor/rules/*.mdc`** | Cursor triggers and **thin** invariants; should **point** to `ai/` and `docs/`, not duplicate novels. |

**Architecture / patterns / examples today**

- **Architecture (deep):** `docs/EMAIL_BUILDER_ARCHITECTURE.md`, `docs/EMAIL_BUILDER_TRAITS.md`, `docs/EMAIL_BUILDER_HYDRATION.md`, … + `.cursor/rules/email-builder-typescript-architecture-kb.mdc`, `grapesjs-lifecycle-events-kb.mdc`, etc.
- **Patterns (snippet-sized):** `ai/kb/patterns.md`, `ai/kb/guardrails.md`.
- **Examples:** Skills embed steps; `ai/kb/block-anatomy.md`; code comments reference `docs/…`.

---

## Step 2 — Proposed structure (minimal, aligned)

Do **not** add many new top-level folders. Extend what exists:

| Need | Where | Notes |
|------|--------|--------|
| **KB (facts + how-it-works)** | `docs/` (narrative) + `ai/kb/` (short) | New topics = new `ai/kb/<topic>.md` or a section in `docs/`; register in `ai/kb/README.md` + `docs/kb/INVENTORY.md`. |
| **Reusable patterns** | **`ai/kb/patterns.md`** (+ link out to `docs/` for full context) | Keep snippets small; one pattern = one heading + code + “when to use”. |
| **Architectural decisions** | **`ai/kb/decisions.md`** | ADR-lite: context → decision → consequences → pointers to `docs/` / `.mdc`. |
| **Anti-patterns** | **`ai/kb/anti-patterns.md`** | Symptom → bad pattern → fix → link to guardrails/patterns/docs. Complements `guardrails.md` (rules) with narrative. |
| **Verification** | **`ai/verification/checklist.md`** (hub) + existing `architecture-checklist.md`, `code-review-checklist.md` | Hub explains when to run which; avoid a third full checklist unless needed. |

**Rule of thumb:** If it is **long or onboarding-heavy**, put it under **`docs/`**. If it is **copy-paste guidance for AI + devs**, keep it under **`ai/kb/`**.

---

## Step 3 — KB updater workflow (no auto-write)

**Principles**

1. **Human approves all file edits.** The AI produces *suggestions* (markdown blocks + target path + section anchor), not silent commits.
2. **Extract only reusable knowledge:** invariants, patterns, decisions, anti-patterns — not ticket chatter, one-off bug stories, or code dumps.
3. **Dedupe before suggest:** Search `ai/kb/`, `docs/`, `.cursor/rules/`, `docs/kb/INVENTORY.md`; if overlap exists, suggest **merge** or **link**, not a new file.
4. **No contradictions:** Run the verification prompt against canonical sources (`docs/EMAIL_BUILDER_*`, `traits-contract-kb` → traits doc, architecture KB).

**Suggested workflow (roles)**

| Phase | Actor | Action |
|-------|--------|--------|
| 1 | Dev | Ships a refactor/fix; optionally pastes diff or summary to AI. |
| 2 | AI | Runs **KB extraction prompt** → markdown “candidate KB chunks”. |
| 3 | AI | Runs **KB update suggestion prompt** → exact file + `## Section` + insert-after/before note. |
| 4 | AI | Runs **Verification prompt** → conflict / duplicate report vs existing KB. |
| 5 | Dev | Accepts, edits, or rejects; applies patch manually (or asks AI to apply in a follow-up **explicit** step). |
| 6 | Maintainer | Updates `docs/kb/INVENTORY.md` if paths/roles change. |

**Output format for suggestions (AI → dev)**

```text
## Suggested KB update
- **File:** ai/kb/patterns.md
- **Section:** (new) ## Pattern: Foo
- **Relation:** No existing pattern; links to docs/EMAIL_BUILDER_ARCHITECTURE.md § Events
- **Patch (paste):** …markdown…
```

---

## Step 6 — Example: real development loop

1. **Dev** refactors Section block hydration to use a new attribute key and removes a trait `hydrate()` call.
2. **Dev** asks AI: “Use `ai/prompts/kb-extraction.md` on this change” + paste diff.
3. **AI** returns: rule “traits must not call hydrate”; pattern “key-scoped listener for `data-section-*`”.
4. **Dev** asks AI: “Use `ai/prompts/kb-update-suggestion.md`” → AI proposes `ai/kb/patterns.md` new subsection + optional one line in `docs/EMAIL_BUILDER_TRAITS.md` if contract-level.
5. **Dev** asks AI: “Use `ai/prompts/kb-verification.md`” → AI reports “already stated in EMAIL_BUILDER_TRAITS; suggest shortening to a link in patterns.md only”.
6. **Dev** merges the agreed markdown manually, updates **INVENTORY** if a new file was added.

---

## Related files

| File | Role |
|------|------|
| `ai/prompts/kb-extraction.md` | Prompt A |
| `ai/prompts/kb-update-suggestion.md` | Prompt B |
| `ai/prompts/kb-verification.md` | Prompt C |
| `ai/kb/patterns.md` | Snippet patterns |
| `ai/kb/anti-patterns.md` | What to avoid + fixes |
| `ai/kb/decisions.md` | ADR-lite log |
| `ai/verification/checklist.md` | Checklist hub |
| `docs/kb/INVENTORY.md` | File catalog |
