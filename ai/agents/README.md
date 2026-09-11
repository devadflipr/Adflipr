# Agents

Specialized AI roles for consistent workflows. Invoked by commands or by user request.

| Agent | Role | Invoked by |
|-------|------|------------|
| **code-reviewer.md** | Senior engineer reviewing PRs/commits; analyzes diff, applies checklists, produces structured feedback. | `ai/commands/review-new-commits.md` or “Review new commits” / “Review this diff” |
| **popup-builder-reviewer.md** | Senior Principal UI Platform Engineer for Popup Builder; deep functional correctness, rendering parity, serializer/runtime contracts, responsive CSS, regression analysis. | `ai/commands/review-popup-builder-pr.md` or “Review Popup Builder PR” / “PBN PR review” / Cursor skill `popup-builder-pr-review` |

To add an agent: create a `.md` file with Role, Responsibilities, Workflow, and Behavior; then reference it from a command or prompt.
