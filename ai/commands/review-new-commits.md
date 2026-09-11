# Command: review-new-commits

**Purpose:** Run the AI code reviewer on new commits (e.g. after pull). Pipeline: Command → Code Reviewer Agent → Review Skill + KB + Checklists.

## How to run

- **In chat:** Say “Run review-new-commits” or “Review new commits” or “Use the review-new-commits command.”
- **With diff:** “Review this diff” and paste the diff, or “Review the last commit” / “Review commits since main.”

When this command is invoked, the AI should follow the steps below.

## Steps

1. **Get the git diff**
   - If the user did not provide a diff:
     - **Last commit:** `git diff HEAD~1 HEAD` or `git show --no-patch --name-only HEAD` then `git show HEAD` for the patch.
     - **Since branch (e.g. main):** `git diff main...HEAD` or `git log -p main..HEAD` (or `origin/main...HEAD` if comparing to remote).
     - **Uncommitted changes:** `git diff` or `git diff --staged`.
   - If the user provided a diff or a commit range, use that.
   - Capture the diff so it can be analyzed (e.g. include it in context or summarize file list + key hunks).

2. **Invoke the Code Reviewer agent**
   - Load the agent workflow from **`ai/agents/code-reviewer.md`** (role, responsibilities, workflow).
   - Provide the diff (or reference to it) as the input to review.

3. **Apply the review skill**
   - Execute the steps in **`ai/skills/review-commit.md`** (analyze diff, architecture violations, code smells, potential bugs, missing tests, suggestions).
   - Use **`ai/kb`** and **`ai/repo-brain.md`** for context.
   - Apply **`ai/verification/code-review-checklist.md`** and, for Email Builder paths, **`ai/verification/architecture-checklist.md`**.
   - For Popup Builder serializer/export/embed CSS paths, also apply **`ai/verification/popup-rendering-css-architecture-checklist.md`**.

4. **Output the review report**
   - Summary (one-line verdict).
   - Issues with severity, location, explanation, suggested fix.
   - Optional: list of checklist items that passed.

## Summary

```
User runs: "Review new commits" / "Run review-new-commits"
    ↓
Command: get git diff (last commit or since main)
    ↓
Agent: Code Reviewer (code-reviewer.md)
    ↓
Skill: review-commit (review-commit.md) + KB + checklists
    ↓
Output: structured review report
```

This keeps the system modular: the **command** triggers and gathers input; the **agent** defines reviewer behavior; the **skill** defines the steps; the **KB and checklists** define the policy.
