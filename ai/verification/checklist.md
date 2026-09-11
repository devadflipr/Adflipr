# Verification — checklist hub

**Purpose:** One entry point for “what to run before merge / before AI returns code.” Detailed items live in the linked files.

## General auditor (canonical — use for every audit)

**Any audit request** → [`principal-engineer-architecture-audit.md`](./principal-engineer-architecture-audit.md) + [`principal-engineer-audit-template.md`](../prompts/principal-engineer-audit-template.md).  
This is the **only** canonical audit methodology (ADR-006). Same 22-section output for integrations, features, automations, commerce, popup, backend, frontend.

| Checklist | Path | Use when |
|-----------|------|----------|
| **General audit (canonical)** | [`principal-engineer-architecture-audit.md`](./principal-engineer-architecture-audit.md) | **Default for any audit** — ingress → downstream, platform parity, security, KB drift. |
| **Architecture** | [`architecture-checklist.md`](./architecture-checklist.md) | Email Builder post-refactor gate only (blocks, traits, hydration) — not a full audit. |
| **Code review** | [`code-review-checklist.md`](./code-review-checklist.md) | PR/patch review — not a full audit. |
| **Popup export CSS** | [`popup-rendering-css-architecture-checklist.md`](./popup-rendering-css-architecture-checklist.md) | Serializer, `renderCss`, embed stylesheet lifecycle — **in addition to** general auditor when auditing popup CSS end-to-end. |

**Instruct the AI:** *“Audit {SUBJECT} per `ai/verification/principal-engineer-architecture-audit.md`.”*

For popup serializer/export/embed CSS: also run **`popup-rendering-css-architecture-checklist.md`** against **`ai/kb/popup-rendering-css-architecture.md`**.

**KB workflow:** After a refactor, run `ai/prompts/kb-verification.md` on any **new** rules you want to add, so they do not contradict these checklists or `docs/EMAIL_BUILDER_*`. See `ai/docs/kb-updater-workbook.md`.

---

## Example checklist rows (format)

Use the same style when proposing new items for the architecture or review lists:

| # | Check | Pass? |
|---|--------|-------|
| E1 | Trait handler does not call `hydrate()` or `setStyle()`. | ☐ |
| E2 | Hydration does not read `editor.getDevice()` or query canvas DOM for state. | ☐ |
| E3 | No new `editor.__*` — use `getEditorRuntime()` accessors. | ☐ |

*(E1–E3 are samples; the authoritative lists remain in `architecture-checklist.md`.)*
