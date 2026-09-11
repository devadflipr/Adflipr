# Popup rendering CSS architecture — review checklist

**Use when:** Reviewing PRs that touch Popup Builder **serializer**, **export artifact**, **embed runtime CSS lifecycle**, or **responsive presentation**.  
**Canonical KB:** [`ai/kb/popup-rendering-css-architecture.md`](../kb/popup-rendering-css-architecture.md)  
**Current-state reference:** [`ai/kb/popup-css-lifecycle-audit.md`](../kb/popup-css-lifecycle-audit.md)

Tick what was verified. List violations with file, severity, and fix.

---

## A. Direction — does the change align with target architecture?

- [ ] **HTML** changes are structure, content, stable identity, runtime hooks — not new presentation via `style=""` (unless explicitly legacy/migration path).
- [ ] **Presentation** (colors, spacing, typography, layout, responsive) goes toward **`renderCss`** / artifact-owned CSS, not new inline or per-step duplicated `<style>` blocks.
- [ ] **Desktop + mobile** share one HTML structure; mobile deltas are `@media` in the same cascade system as base rules — not a second HTML tree.
- [ ] Change does **not** introduce desktop-inline + mobile-stylesheet without `!important` for the **same property on the same element** (PCL-008 anti-pattern).
- [ ] Change does **not** expand permanent `!important` usage without documented tactical exception and migration plan.

---

## B. Artifact contract (when touching save/export/API payload)

- [ ] If adding fields: prefer **`renderCss`**, `serializerBuild`, `contractVersion` — not `sharedCss` or ambiguous names.
- [ ] `renderCss` is **once per popup artifact**, not duplicated per step in `htmlByStep`.
- [ ] HTML and CSS are generated in **one compilation** with matching version metadata.
- [ ] Backend treats legacy artifacts as opaque strings; new fields are optional for backward compatibility.

---

## C. Runtime lifecycle (when touching `embed/`)

- [ ] **New path:** If `renderCss` present → mount artifact stylesheet **once** per popup instance; step transitions replace HTML only.
- [ ] **Legacy path:** If no `renderCss` → existing hoist lifecycle unchanged or explicitly improved (dismiss teardown if touching lifecycle).
- [ ] **Destroy** removes exactly the instance-owned stylesheet (no global key collisions between popups — PCL-002).
- [ ] Runtime CSS changes are **shell infrastructure only** (overlay, scroll-lock, positioning) — not block/merchant presentation.

---

## D. Scoping and isolation

- [ ] New generated rules use **`[data-adflipr-popup-artifact="…"]`** (target) or existing `form[data-adflipr-popup-export="true"]` (legacy) — not unscoped `input`, `button`, `form`, `::placeholder`, `*`.
- [ ] Custom merchant CSS is scoped or rejected — not raw concat into `document.head` (PCL-004).
- [ ] Keyframes, animation names, CSS variables namespaced where collision risk exists.

---

## E. Stable identity

- [ ] Styled elements have stable `data-adflipr-*` ids (block, layout, column, artifact root).
- [ ] Selectors avoid fragile `:nth-child` / deep positional chains for presentation contracts.

---

## F. Testing (required for responsive or presentation changes)

- [ ] Serializer tests updated for HTML identity + CSS rule output.
- [ ] **Computed-style tests** added or updated for desktop **and** mobile viewport (not only `toContain('padding:12px')` on CSS string).
- [ ] Lifecycle test: one stylesheet per artifact across step transitions; destroy removes owned styles.
- [ ] Visual parity considered: builder preview vs embed at 480px and desktop widths.

---

## G. Migration / legacy

- [ ] Legacy published popups still render without forced republish.
- [ ] Feature flags or `contractVersion` gate new behavior where partial migration.
- [ ] KB updated if artifact shape or invariant changes.

---

## H. Explicit rejections (flag if PR does any of these as permanent design)

- [ ] Permanent architecture = desktop inline + mobile `@media` + widespread `!important`
- [ ] Separate desktop/mobile HTML artifacts
- [ ] All presentation CSS only in live runtime bundle (breaks old artifacts)
- [ ] Shadow DOM or iframe-only rendering as the primary model

---

**Output format:** Pass/fail per section; violations with path, invariant number (§20), suggested fix.
