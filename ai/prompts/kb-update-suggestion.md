# Prompt B — KB update suggestion (where to put knowledge)

**Use when:** You have candidate KB text (from Prompt A or from the user) and need a **concrete placement** in this repo.

**Instructions for the AI**

Propose **exactly one primary location** per chunk (or “split” if one chunk belongs in two places). Follow Adflipr conventions:

- **Long narrative / onboarding** → `docs/` (and link from `ai/kb/README.md` if AI-facing).
- **Short snippet / checklist / pattern** → `ai/kb/*.md`.
- **Cursor-only trigger or always-on invariant** → `.cursor/rules/*.mdc` **only if** it must be auto-loaded; keep **thin** and point to `docs/` / `ai/kb/`.
- **New topic file** → Prefer `ai/kb/<topic>.md` only if it does not fit an existing file; then add a row to `docs/kb/INVENTORY.md`.

**Do not** suggest editing files unless the user will paste the patch themselves in a later step (this prompt is advisory).

**Output format**

For each candidate chunk:

```markdown
## Suggested placement

### Chunk 1
- **Primary file:** `ai/kb/patterns.md` (or path)
- **Section:** New `## Pattern: <Name>` after existing “Pattern: Section-table structure”
- **Alternative:** None / or `docs/EMAIL_BUILDER_….md` § … if narrative
- **INVENTORY:** Update row for `patterns.md` Notes / or add new file row
- **Rationale:** One sentence (audience, length, duplication risk)

### Patch to apply (markdown block for human paste)
…
```

**User message template**

> Run kb-update-suggestion. Here is extracted or draft KB:
>
> [paste markdown from kb-extraction or your own notes]
