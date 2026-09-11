# Skills

Repeatable workflows for common tasks.

| Skill | Use when |
|-------|----------|
| **refactor-block.md** | Refactoring a GrapesJS block or trait to match project architecture (no DOM mutation, traits write attributes only, hydration pure, events correct). |
| **review-commit.md** | Reviewing a git diff or new commits: analyze diff, apply checklists, output structured feedback. Used by Code Reviewer agent. |
| **popup-builder-pr-review.md** | Exhaustive Popup Builder PR review: functional correctness, rendering parity, serializer/runtime contracts, responsive CSS, regressions. Used by Popup Builder Reviewer agent. |
| **js-to-ts-migration.md** | Migrating a JS/JSX file to TypeScript (general playbook; Email Builder section for EB-specific rules and verification). |
| **elasticsearch-mapping-update.md** | Changing ES mappings/settings for Adflipr: new physical index `_dev`/`_prod` + `vYYYYMMDD`, reindex, atomic alias swap. Also: `.cursor/skills/elasticsearch-mapping-update/SKILL.md`. |

**Usage:** “Apply refactor-block skill to this block” or follow the steps in the skill file, then verify with `ai/verification/architecture-checklist.md`. For reviews, use the **review-new-commits** command (invokes Code Reviewer + review-commit skill). For Popup Builder PRs, use **review-popup-builder-pr** command or Cursor skill `popup-builder-pr-review`.
