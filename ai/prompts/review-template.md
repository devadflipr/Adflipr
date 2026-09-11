# Prompt template: Review change against KB and checklists

Use this to review a patch or PR for Email Builder–touching code.

---

**Task:** Review the given change (diff / file list) against project KB and verification checklists.

**Instructions:**
- Check adherence to `ai/kb/architecture.md` (pipeline, event ownership, runtime).
- Check adherence to `ai/kb/guardrails.md` (no DOM mutation from model, traits write attributes only, no `editor.__*`, trait cleanup, etc.).
- Run the review using `ai/verification/architecture-checklist.md` and `ai/verification/code-review-checklist.md`.
- For popup serializer/export/embed CSS changes, also run `ai/verification/popup-rendering-css-architecture-checklist.md` against `ai/kb/popup-rendering-css-architecture.md`.

**Output:**
1. List of checklist items that pass.
2. List of violations or risks with file/line or snippet.
3. Suggested fixes for each violation.
4. Optional: one-line verdict (e.g. “Approved after X fixes” / “Needs rework for Y”).

---

**Short form:** “Review this change using ai/kb and ai/verification/*. List passes, violations with locations, and suggested fixes.”
