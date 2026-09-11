# AI Code Review on GitLab

The same AI review flow works on **GitLab**: CI jobs run on merge requests (auto) or on demand (manual), and post the review as an MR note when possible.

## What runs

- **Config:** `.gitlab-ci.yml` with two jobs:
  - **`ai-review`** — runs automatically on every **merge request** (open or push to MR branch). Diffs against the MR target branch.
  - **`ai-review-manual`** — **manual** job you can start from **Pipelines → Run pipeline** (or from any branch pipeline). Diffs current branch vs `CI_DEFAULT_BRANCH` (or set variable `BASE_REF` when running).
- **Steps:** Fetch base ref → `git diff origin/${BASE_REF} HEAD` → `diff.txt` → `python scripts/ai_review.py` → `python scripts/post_review.py`.
- **Post:** `post_review.py` posts the **overview** as an MR note and **per-line comments** as MR discussions (inline on the diff). For manual runs without an MR, review is only in the job log (no MR note).

## GitLab CI/CD variables

In your GitLab project: **Settings → CI/CD → Variables**. Add:

| Variable           | Type    | Protected | Masked | Description                          |
|--------------------|---------|-----------|--------|--------------------------------------|
| `OPENAI_API_KEY`   | Variable| Optional  | Yes    | OpenAI API key for the review model. |
| `GITLAB_TOKEN`     | Variable| Optional  | Yes    | Personal or project access token with `api` scope (to create MR notes). |

- **GITLAB_TOKEN:** Create a [Personal Access Token](https://docs.gitlab.com/ee/user/profile/personal_access_tokens.html) or [Project Access Token](https://docs.gitlab.com/ee/user/project/settings/project_access_tokens.html) with scope `api` (or at least `write_repository` if that’s enough for MR notes in your version). The job uses it to call `POST /projects/:id/merge_requests/:iid/notes`.

GitLab CI automatically provides `CI_PROJECT_ID`, `CI_MERGE_REQUEST_IID`, and `CI_MERGE_REQUEST_TARGET_BRANCH_NAME` in MR pipelines, so no extra variables are needed for the script.

## How to trigger the pipeline

1. **Automatically:** Open a merge request or push new commits to an MR branch → `ai-review` runs.
2. **Manually:** Go to **CI/CD → Pipelines → Run pipeline**, choose the branch to review, optionally set variable **`BASE_REF`** (e.g. `main` or `dev`) to diff against, then run. Click the **Play** icon on the `ai-review-manual` job to run the review.

## Where to find the review comment

- **Overview:** One merge request note with the summary and general feedback (same as a normal comment). Open the **Discussion** tab and scroll to the bottom.
- **Per-line comments:** Inline on the diff. Open the **Changes** (or **Files**) tab and look for comments on specific lines; each issue from the AI is posted as a discussion thread on the relevant line.

The pipeline log prints a direct link to the MR; use it and check both **Discussion** (overview) and **Changes** (inline comments).

## Self-hosted GitLab

If your GitLab host is not `https://gitlab.com`, the runner already sets `CI_SERVER_URL`. `post_review.py` uses it when posting the note, so no code change is needed.

## Summary

| Item        | GitHub                    | GitLab                          |
|------------|----------------------------|----------------------------------|
| Config     | `.github/workflows/ai-review.yml` | `.gitlab-ci.yml`                |
| Trigger    | `pull_request`             | `merge_request_event`           |
| Token var  | `GITHUB_TOKEN` (auto)      | `GITLAB_TOKEN` (you add it)     |
| Post API   | `POST .../issues/:pr/comments` | `POST .../merge_requests/:iid/notes` |

The Python scripts (`ai_review.py`, `post_review.py`) support both: `post_review.py` uses GitLab when `CI_MERGE_REQUEST_IID` and `GITLAB_TOKEN` are set, otherwise GitHub.
