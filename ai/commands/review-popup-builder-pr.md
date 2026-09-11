# Command: review-popup-builder-pr

**Trigger:** User says “Review Popup Builder PR”, “Run popup builder reviewer”, “PBN PR review”, or provides a PR/diff for Popup Builder.

**Agent:** `ai/agents/popup-builder-reviewer.md`

## Steps

1. **Identify scope**
   - If PR URL given: `gh pr diff <number>` or `gh pr view <number> --json files,title,body`
   - Else: `git diff main...HEAD` (or user-specified base)

2. **Load reviewer context**
   - `ai/kb/popup-builder-reviewer-module-map.md`
   - `ai/skills/popup-builder-pr-review.md`

3. **Classify changed files** (builder / serializer / embed / contract / backend / tests / storefront)

4. **Expand review** using downstream tracing table in the agent doc

5. **Read dependent files** not in diff when contracts or shared utilities changed

6. **Apply checklists**
   - Always: `ai/verification/code-review-checklist.md`
   - Serializer/embed/CSS: `ai/verification/popup-rendering-css-architecture-checklist.md`

7. **Produce report** using `ai/prompts/popup-builder-pr-review-template.md` (all sections + verdict)

8. **Note KB drift** — suggest updates per `ai/docs/kb-updater-workbook.md`; do not silently edit KB

## Output

Full structured review ending with **Overall Verdict**: APPROVE | APPROVE WITH SUGGESTIONS | REQUEST CHANGES | BLOCK
