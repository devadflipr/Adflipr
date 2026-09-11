# Lessons learned (code + KB)

This folder supports **git/code context** after sub-agents and **periodic deduplication** of those captures.

## What runs automatically (Cursor hooks)

Configured in **`.cursor/hooks.json`** (project root).

### `subagentStop` — code / workspace context

Runs **`.cursor/hooks/lesson-learned-subagent-stop.sh`** when a **Task / sub-agent** stops.

- If **git is dirty**, writes **`ai/lessons-learned/_hook-capture/<timestamp>-subagent.md`** (status + diff stats) and may return a **`followup_message`** nudging the parent agent to distill lessons into **`ai/kb/`**.

## What does *not* run automatically

- **Hooks cannot run an LLM** or the **`lesson-learned`** skill (`~/.agents/skills/lesson-learned/SKILL.md`) by themselves.
- **Judging** whether there is a durable lesson still belongs to **you** or the **orchestrating agent** (review the captured git context, then update `ai/kb/*.md`).

Workflow that works well:

1. Hooks **append** lightweight evidence (git stats only).
2. After a milestone, ask the agent: *“Review today’s `ai/lessons-learned/_hook-capture/` files; if anything should be durable, add a short bullet to `ai/kb/decisions.md` or `patterns.md`.”*

## Duplicate cleanup (regular basis)

There is no built-in “cron” inside Cursor. Run this **weekly** (or from CI on a schedule if you add it):

```bash
# Dry-run: report only
python3 ai/lessons-learned/scripts/dedupe_captures.py

# Apply: delete identical duplicate .md captures
python3 ai/lessons-learned/scripts/dedupe_captures.py --apply
```
- **`_hook-capture/*.md`**: if two files have **identical content**, keeps the **first filename** and deletes the rest (`--apply`).

**KB files (`ai/kb/*.md`)** are not auto-merged (too risky). Dedupe script only touches **`_hook-capture/`** artifacts under `ai/lessons-learned/`. For KB overlap, ask the agent explicitly or edit by hand.

### Example cron (your Mac)

```cron
0 9 * * 1 cd /path/to/adflipr && python3 ai/lessons-learned/scripts/dedupe_captures.py --apply >> ~/Library/Logs/adflipr-dedupe.log 2>&1
```

## Where files go

| Path | Role |
|------|------|
| `ai/lessons-learned/_hook-capture/` | Markdown stubs after sub-agent + dirty git (gitignored). |
| `ai/lessons-learned/scripts/dedupe_captures.py` | Dedupe helper (committed). |
| `ai/kb/*.md` | Durable lessons after human/agent curation. |

If your clone is **`~/work/adflipr`**, open that folder in Cursor — hooks always write under **`ai/lessons-learned/`** in the **opened workspace**.

## Cursor setup

1. `.cursor/hooks.json` present; scripts **executable**:
   - `chmod +x .cursor/hooks/lesson-learned-subagent-stop.sh`
2. Reload hooks (save `hooks.json` or restart Cursor).
3. Hooks output channel if something never fires.

## References

- Cursor hooks: `https://cursor.com/docs/agent/hooks`
- Lesson-learned skill: `~/.agents/skills/lesson-learned/SKILL.md`
