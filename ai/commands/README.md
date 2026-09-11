# Commands

Commands trigger a full pipeline: they gather input (e.g. git diff), invoke an agent, and optionally a skill + KB.

| Command | What it does |
|---------|----------------|
| **review-new-commits.md** | Get git diff (last commit or since main) → run Code Reviewer agent → apply review-commit skill + KB + checklists → output review report. |
| **review-popup-builder-pr.md** | Get PR/diff → run Popup Builder Reviewer agent → apply popup-builder-pr-review skill + module map + popup checklists → full structured review with verdict. |

**How to run:** In chat, say “Run review-new-commits” or “Review new commits” or “Review the last commit.” The AI should follow the command steps and use the referenced agent and skill. For Popup Builder: “Review Popup Builder PR” or “Run review-popup-builder-pr”.
