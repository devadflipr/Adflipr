# Verification

Checklists for self-verification before returning code.

**Start here:** [`checklist.md`](./checklist.md) — hub + when to use which file.

| Checklist | Use after |
|-----------|-----------|
| **architecture-checklist.md** | Any refactor or change that touches blocks, traits, hydration, events, or editor runtime. |
| **code-review-checklist.md** | Reviewing a patch or PR (use together with architecture-checklist). |
| **popup-rendering-css-architecture-checklist.md** | Popup serializer/export/embed CSS, `renderCss`, responsive presentation, artifact lifecycle. |
| **principal-engineer-architecture-audit.md** | **General auditor (canonical)** — every audit: ingress → downstream, platform parity, security, KB drift. |

**Instruct the AI:** “Verify the solution using ai/verification/architecture-checklist.md. Fix violations before returning final code.”

For production feature audits: “Follow ai/verification/principal-engineer-architecture-audit.md; use ai/prompts/principal-engineer-audit-template.md.”

For popup export/embed CSS: also use **popup-rendering-css-architecture-checklist.md** against **ai/kb/popup-rendering-css-architecture.md**.
