# Prompt templates

Reusable prompts so interactions stay consistent.

| Template | Use when |
|----------|----------|
| **refactor-template.md** | Refactoring a block, trait, or hydration-related code to match KB. |
| **debug-template.md** | Debugging hydration, traits, selection, or editor state. |
| **review-template.md** | Reviewing a patch/PR against KB and verification checklists. |
| **popup-builder-pr-review-template.md** | Structured output for Popup Builder PR reviews (functional correctness, rendering, CSS/HTML/serializer/runtime, verdict). |
| **principal-engineer-audit-template.md** | **General auditor** — any feature/integration/production audit (canonical 22-section output). |
| **kb-extraction.md** | Turn a solution/diff into candidate rules, patterns, anti-patterns, decisions. |
| **kb-update-suggestion.md** | Map candidate KB to exact file + section (human pastes patch). |
| **kb-verification.md** | Check proposed KB vs canonical docs for duplication/conflict. |

**Usage:** Copy the template into the chat or say “Use refactor-template.md for this block” and then paste the task (e.g. file or diff). Always run the verification step referenced in the template (e.g. `ai/verification/architecture-checklist.md`) and fix violations before returning final code.
