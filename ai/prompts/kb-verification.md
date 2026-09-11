# Prompt C — KB verification (conflicts & duplication)

**Use when:** Before merging new KB text, or when two docs seem to disagree.

**Instructions for the AI**

Compare the **proposed** knowledge against **canonical** sources in this repo. Do **not** rewrite files; produce a **report**.

**Canonical sources (check in this order)**

1. `docs/EMAIL_BUILDER_ARCHITECTURE.md`, `docs/EMAIL_BUILDER_TRAITS.md`, `docs/EMAIL_BUILDER_HYDRATION.md`, `docs/EMAIL_BUILDER_CLONE_AND_DRAG.md`, `docs/EMAIL_BUILDER_PREVIEW.md`
2. `.cursor/rules/email-builder-typescript-architecture-kb.mdc`, `grapesjs-lifecycle-events-kb.mdc`, `traits-contract-kb.mdc` (pointers), `editor-runtime-store-kb.mdc`
3. `ai/kb/guardrails.md`, `ai/kb/architecture.md`, `ai/kb/patterns.md`, `ai/kb/anti-patterns.md`, `ai/kb/decisions.md`
4. `docs/kb/INVENTORY.md` (file roles and changelog)

**Classify each proposed bullet**

- **OK** — Aligned or additive.
- **Duplicate** — Already stated; suggest **link** instead of copy.
- **Conflict** — Contradicts canonical doc; propose **resolution** (which source should win and why).
- **Gap** — Proposed text fills a hole; suggest **minimal** addition.

**Output format**

```markdown
## KB verification report

### Summary
- OK: n | Duplicate: n | Conflict: n | Gap: n

### Items
| # | Proposal (one-line) | Verdict | Existing source (path + section) | Recommendation |
|---|----------------------|---------|----------------------------------|----------------|
| 1 | … | OK / Duplicate / Conflict / Gap | … | … |

### Contradictions to resolve (if any)
1. …

### Suggested next action
- …
```

**User message template**

> Run kb-verification. Proposed KB:
>
> [paste candidate markdown]
