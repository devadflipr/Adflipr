# AI Layer — Adflipr Email Builder

This folder organizes instructions so the AI loads the right context, follows the right process, and verifies its work.

**Typical flow:** Task prompt → AI reads KB → AI applies skill/reasoning → AI verifies with checklist → Final answer.

**Review pipeline:** Command → Subagent → Skill + KB. Example: `review-new-commits` → Code Reviewer agent → review-commit skill + KB + checklists.

| Folder | Role |
|--------|------|
| **kb/** | Knowledge base: how the system works (architecture, guardrails, patterns, block anatomy). |
| **skills/** | Repeatable workflows (e.g. refactor block, review-commit, JS→TS). |
| **prompts/** | Reusable prompt templates for consistent interactions. |
| **verification/** | Checklists + **general auditor** (`principal-engineer-architecture-audit.md`) — canonical for every audit. |
| **agents/** | Specialized roles (e.g. Code Reviewer, Popup Builder Reviewer) invoked by commands or user request. |
| **commands/** | Trigger workflows (e.g. review-new-commits: get diff → run reviewer → output report). |

**Quick start:** For refactors, use `ai/prompts/refactor-template.md` and verify with `ai/verification/architecture-checklist.md`.

**Evolving KB (human-in-the-loop):** `ai/docs/kb-updater-workbook.md` + `ai/prompts/kb-extraction.md`, `kb-update-suggestion.md`, `kb-verification.md`. The AI suggests file + section; it does not silently edit KB.

**Review new commits (manual):** Say “Run review-new-commits” or “Review new commits”; the AI runs `ai/commands/review-new-commits.md` → Code Reviewer → `ai/skills/review-commit.md` + KB + checklists.

**Review bot (automated):** On every push to a PR/MR, the AI review runs: gets diff, runs `scripts/ai_review.py` and `scripts/post_review.py`, posts the review as a comment. **GitHub:** `.github/workflows/ai-review.yml` + `OPENAI_API_KEY` secret. **GitLab:** `.gitlab-ci.yml` + `OPENAI_API_KEY` and `GITLAB_TOKEN` CI/CD variables. See **`ai/docs/automated-review-flow.md`** and **`ai/docs/gitlab-setup.md`**.

**Repo brain:** See `repo-brain.md` for a compressed system overview.

**Cursor:** `.cursor/rules/ai-folder-canonical.mdc` and `.cursor/rules/ai-layer.mdc` tell the AI to use this `ai/` folder as the canonical source for KB, skills, prompts, and verification (so .cursor refers to `ai/` implicitly when applying process or checklists).
