# Prompt template: Debug Email Builder issue

Use this when debugging hydration, traits, selection, or editor state.

---

**Task:** Debug the described issue in the Email Builder.

**Instructions:**
- Load context from `ai/kb/architecture.md` and `ai/kb/guardrails.md` so fixes do not violate pipeline or guardrails.
- Identify whether the bug is in: trait (writing attributes?), hydration (reading attributes / applying styles?), event ownership (listener in init vs editor bootstrap?), or runtime (use of `editor.__*` or missing guard?).
- Propose a fix that keeps: traits as attribute writers only; hydration as pure projection; editor events only at bootstrap; runtime via `getEditorRuntime()`.

**After proposing a fix:**
1. Check against `ai/verification/architecture-checklist.md`.
2. Ensure no new violations (e.g. no DOM read in hydration, no hydrate from trait).
3. Output final code and a one-line summary of root cause.

---

**Short form:** “Debug this [issue]. Use ai/kb for context. Verify the fix with ai/verification/architecture-checklist.md.”
