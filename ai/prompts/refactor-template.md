# Prompt template: Refactor according to project KB

Use this for refactoring a block, trait, or hydration-related code.

---

**Task:** Refactor the given component/block according to project KB.

**Instructions:**
- Follow `ai/kb/architecture.md` (pipeline, event ownership, runtime).
- Follow `ai/kb/guardrails.md` (no DOM mutation from model, traits write attributes only, no `editor.__*`, etc.).
- Use patterns from `ai/kb/patterns.md` (attribute-scoped hydration, trait cleanup, style guards).
- Use `ai/kb/block-anatomy.md` for conceptual structure (model vs view vs traits vs events).

**Process:**
- Apply the workflow in `ai/skills/refactor-block.md` (identify model, remove DOM mutation, attribute-only traits, bind hydration, register traits, verify editor events).

**After refactoring:**
1. Run verification using `ai/verification/architecture-checklist.md`.
2. Fix any violations.
3. Output final code.

---

**Short form (for chat):** “Refactor this block according to ai/kb. After generating the solution, verify using ai/verification/architecture-checklist.md, fix violations, then output final code.”
