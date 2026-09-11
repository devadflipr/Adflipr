# Prompt A — KB extraction (from a solution)

**Use when:** A refactor, fix, or design discussion produced lessons worth keeping.

**Instructions for the AI**

You are extracting **durable** knowledge from the user’s message (and any pasted diff, PR description, or file paths). Output **only** content suitable for the Adflipr repo KB — not generic engineering advice.

**Scope**

- **Rules / invariants** — Must / must-not statements grounded in this codebase (Email Builder, `service/`, `adflipr-app/`, etc.).
- **Patterns** — Repeatable approaches (with optional short code snippet) that teammates should reuse.
- **Anti-patterns** — What went wrong or what we avoid, with **symptom** and **preferred fix**.
- **Decisions** — If the text implies a trade-off we locked in, capture as a mini decision (context → decision → consequence).

**Exclude**

- Ticket IDs, names, one-off timelines, speculation, unrelated stacks.
- Anything already verbatim in `docs/EMAIL_BUILDER_ARCHITECTURE.md`, `docs/EMAIL_BUILDER_TRAITS.md`, `ai/kb/guardrails.md` — instead say “already documented at …”.

**Output format (markdown, paste-ready)**

Use this structure:

```markdown
## Extracted knowledge (candidate)

### Rules
- …

### Patterns
- **Name:** …
  - **When:** …
  - **Snippet / steps:** …

### Anti-patterns
- **Bad:** …
  - **Symptom:** …
  - **Instead:** …

### Decisions (if any)
- **Context:** …
  - **Decision:** …
  - **Consequences:** …

### References checked
- (List files you compared against or “none supplied”.)
```

**User message template**

> Run kb-extraction. Here is the change / discussion:
>
> [paste diff, summary, or links to files]
