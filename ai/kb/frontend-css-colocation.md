# Frontend CSS colocation (`adflipr-app`)

**Status:** Locked (2026-07-26)  
**Cursor rule:** [`.cursor/rules/frontend-css-colocation.mdc`](../../.cursor/rules/frontend-css-colocation.mdc)

## Decision

New feature / component UI styles live **next to the owner**, not in `src/styles/global.scss`.

| Kind | Where |
|------|--------|
| Component-private UI | `ComponentName.module.scss` (CSS Modules) |
| Feature page | Colocated `feature.scss` imported by that feature only |
| True cross-app primitives | `global.scss` (buttons, resets, shared shells) |

## Example

`layouts/Workflow/dialogs/EventSelector.module.scss` owns the Loyalty connect empty state. Do not add `.event-loyalty-connect` (or similar) to `global.scss`.

## Rationale

- Limits cascade / naming collisions
- Makes ownership obvious in PRs
- Matches existing Workflow pattern (`ReadyToGoLiveChecklist.module.scss`)
