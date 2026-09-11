# How the AI Review Bot Flow Works

When someone pushes code to a PR, the AI runs automatically and posts review comments like a review bot.

## How to get review comments line-by-line (inline) instead of only a general comment

The bot posts **two kinds** of comments:

1. **General (overview) comment** — One summary comment on the PR (Conversation tab).
2. **Line-by-line (inline) comments** — Each finding that has a `file` and `line` is posted on the **diff** at that line.

**Where to see line-by-line comments:**

- **GitHub:** Open your PR → go to the **“Files changed”** tab (not “Conversation”). Inline comments appear on the right side of the diff at the relevant line; you can also open “Review comments” from the top of the PR.
- **GitLab:** Open your MR → go to the **“Changes”** tab. Inline comments appear as discussions on the specific lines.

**If you only see the general comment:** The AI may have returned no line-specific issues (empty `issues` in `review_issues.json`). The script only posts inline comments when the AI output includes issues with both `file` and `line` (line = line number in the **new** version of the file in the diff). To get more line-by-line comments, the review prompt asks the AI to attach findings to specific lines; re-running the review (e.g. push a small change) can yield different results. You can also run the scripts locally and inspect `review_issues.json` to confirm the AI produced line-level issues.

## Pipeline

```
Developer pushes commit to PR
        ↓
GitHub Action triggers (pull_request: opened, synchronize)
        ↓
Workflow runs: checkout repo, get PR diff (git), run Python review scripts
        ↓
Diff written to diff.txt; ai_review.py reads it and calls AI API
        ↓
Script sends diff + review policy (ai/kb + checklists) to AI API
        ↓
AI returns structured review (summary + overview + issues with file/line/severity/fix)
        ↓
Script posts overview as PR/MR comment and posts each issue as an inline comment on the diff (GitHub review comments / GitLab MR discussions)
```

So the AI behaves like a **review bot**: no manual “Review new commits” in chat; the bot runs on every push to the PR and posts the review.

## What the bot uses

- **Diff:** The workflow runs `git diff origin/${{ github.base_ref }} HEAD` and writes it to `diff.txt`; `scripts/ai_review.py` reads that file.
- **Review policy:** The same policy as the manual flow: `ai/skills/review-commit.md` (steps + output format) and `ai/verification/code-review-checklist.md` (and `architecture-checklist.md` for Email Builder paths; `popup-rendering-css-architecture-checklist.md` for popup serializer/embed CSS). The script builds a prompt from these and the diff.
- **AI API:** The script calls an AI provider (e.g. OpenAI or Anthropic) with that prompt and parses the response into a summary and issues.
- **Posting:** `scripts/post_review.py` posts the **overview** as a PR issue comment and **per-line comments** as pull request review comments (GitHub) or MR discussions (GitLab), so feedback appears on the relevant lines in the **Changes** / **Files** view.

## Relation to the rest of the AI layer

| Piece | Manual flow (Cursor chat) | Automated flow (PR bot) |
|-------|---------------------------|--------------------------|
| **Trigger** | User says “Review new commits” | GitHub Action on `pull_request` |
| **Diff** | AI runs `git diff` or user pastes | Script fetches PR diff via API |
| **Review logic** | Code Reviewer agent + review-commit skill | Same: script sends skill + checklists + diff to AI API |
| **Output** | AI returns report in chat | Script posts report as PR review |

The **same** KB, checklists, and review skill drive both flows; only the trigger and the way the result is delivered (chat vs PR comment) differ.

## Setup

1. **Secrets:** In the repo’s GitHub Settings → Secrets and variables → Actions, add:
   - `OPENAI_API_KEY` (used by the review script to call the AI).
2. **Workflow:** The main workflow is **`.github/workflows/ai-review.yml`**. It runs whenever a PR is opened or updated:
   - Checkout repo → Get PR diff (`git diff origin/base...HEAD` → `diff.txt`) → Run **`scripts/ai_review.py`** (writes **`review.txt`** and **`review_issues.json`**) → Run **`scripts/post_review.py`** (posts overview + inline comments).
3. **Optional:** To only run on certain paths (e.g. only when `adflipr-app/` or `ai/` change), add `paths` or `paths-ignore` under the `pull_request` trigger.

## GitLab

If you use **GitLab** instead of GitHub, use **`.gitlab-ci.yml`** and set CI/CD variables `OPENAI_API_KEY` and `GITLAB_TOKEN`. The same Python scripts run; `post_review.py` posts an MR note via the GitLab API. See **`ai/docs/gitlab-setup.md`**.

## Disabling or limiting the bot

- Disable: remove or comment out the workflow, or delete the file.
- Limit to certain branches: add `branches: [main]` (or your base branch) under `pull_request` so it only runs for PRs targeting that branch.
- Limit to certain files: use `paths: ['adflipr-app/**', 'ai/**']` so the action only runs when those paths change.
