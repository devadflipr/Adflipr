# Automation Analytics UI contract

**Status:** Locked (2026-07-30)  
**Audience:** Engineering + Cursor agents  
**Surfaces:** `adflipr-app/src/pages/automations/components/Analytics.tsx`, `LineChart.tsx`  
**Regression history:** Jun 2026 — `%` stripped from rate cards (`347835d29`); Performance metric tabs removed (`bb0d486a7`).

## Visible contract (do not break without product request)

### Key Insights — rate cards

These metrics **must** show a **`%` suffix** next to the numeric rate (count in parentheses is separate):

- Unique Open Rate
- Total Open Rate
- Unique Click Rate
- Total Click Rate
- Click to Open Rate

**Preferred encoding:** pass the suffix through `StyledNumber`, e.g. `num={\`${rate}%\`}`, so formatting cleanups cannot delete a lone `%` sibling.

### Performance chart — metric tabs

When more than one series exists (`activeContacts`, `completedContacts`, `orders`, `revenue`):

- Render **analytics-tabs** so the merchant can switch series.
- Keep `selectedTab` / `setSelectedTab` wired to those tabs.
- Prefer defaulting to a series that has non-zero values when the first series is all zeros (e.g. Active = 0, Completed > 0).

Do **not** treat tab UI as dead code during “cleanup” / “prod bugs” diffs.

## Agent / review rules

- Deleting `%` literals or Performance tab controls requires an **explicit product ask** in the task.
- If touching these files, run or update e2e coverage in `adflipr-app/e2e/automation-editor.spec.ts` (`AUTO-E-04*`).
- Code review: see `ai/verification/code-review-checklist.md` § Automation Analytics UI.

## Related

- Cursor rule: `.cursor/rules/automation-analytics-ui.mdc`
- E2E: `AUTO-E-04c` (rates + `%`), `AUTO-E-04e` / `AUTO-E-04f` (Performance tabs)
