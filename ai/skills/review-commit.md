# Skill: review-commit

**Goal:** Review new code changes (e.g. from a git diff after pull) like a senior engineer. Produce structured feedback against repo KB and the code-review checklist.

## Steps

1. **Analyze the git diff**
   - Obtain the diff (e.g. `git diff main...HEAD` or `git diff HEAD~1` for last commit). If the user did not provide a diff, run `git diff` or `git log -p` as appropriate to get changed lines.
   - Identify touched files and the nature of changes (new block, trait change, hydration, API, etc.).

2. **Identify architecture violations**
   - Check against `ai/kb/architecture.md` and `ai/verification/architecture-checklist.md`.
   - Flag: DOM manipulation in blocks, traits calling `hydrate()`/`setStyle()`, editor events registered in `init()`, use of `editor.__*`, missing trait cleanup, hydration mutating structure.

3. **Identify code smells**
   - Check against `ai/verification/code-review-checklist.md` (Code quality): unnecessary complexity, oversized functions, naming inconsistency, file size.

4. **Identify potential bugs**
   - Guards missing (editor/runtime absent), event listener leaks, incorrect event ownership, backwards compatibility or API breakage.

5. **Identify missing tests**
   - If behavior or public API changed, note whether tests or KB/docs should be updated.

6. **Suggest improvements**
   - Concrete, actionable fixes (file, line or snippet, and replacement or refactor suggestion).

## Rules

- Follow **`ai/kb`** (architecture, guardrails, patterns, block-anatomy).
- Follow **`ai/verification/code-review-checklist.md`** as the policy layer.
- For Email Builder paths, also apply **`ai/verification/architecture-checklist.md`**.
- For Popup Builder **serializer / export artifact / embed CSS lifecycle** paths (`popupHtmlSerializer/`, `embed/`), also apply **`ai/verification/popup-rendering-css-architecture-checklist.md`** against **`ai/kb/popup-rendering-css-architecture.md`**.

## Output format

**Summary:** One-line verdict (e.g. “Approved” / “Approved with minor suggestions” / “Changes requested”).

**Issues:** (if any)

For each issue:

- **Severity:** `blocker` | `major` | `minor` | `suggestion`
- **Location:** file path and line or snippet
- **Explanation:** what is wrong and why it matters
- **Suggested fix:** concrete change or reference to KB/skill

**Passed:** List checklist items that were satisfied (optional but recommended).
