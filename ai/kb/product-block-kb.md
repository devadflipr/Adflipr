# Product Block — KB (Audit & Rules)

**Scope:** Product block registration, product block styles (hydration), product utils, ProductBlockManager, registerProductTrait, and related trait utils.

**Cross-reference:** `.cursor/rules/email-builder-typescript-architecture-kb.mdc`, `grapesjs-lifecycle-events-kb.mdc`, `traits-contract-kb.mdc`, `email_builder_styling_kb.mdc`, `docs/EMAIL_BUILDER_ARCHITECTURE.md`.

---

## 1. Three rules (invariants)

All product-block-related code must satisfy:

### Rule 1 — No direct DOM manipulation

- **Do not** use `component.view.el`, `view.el.style`, `getEl()`, or any canvas/component DOM for mutation or for reading state that drives styling.
- **Allowed:** Trait panel DOM (the `el` that is the trait container) for UI only (e.g. `el.querySelector` for inputs inside the trait). RTE utilities and drag/drop helpers may use DOM where documented.
- **Forbidden:** `table.view.el.style.setProperty(...)`, `cell.view.el.style...`, or reading from canvas DOM to set styles.

### Rule 2 — Style only through hydration

- **Single path:** Attributes → `change:attributes` → hydration (productBlockStyles) → `setStyle` / `addStyle` / `applyMediaStyle`.
- **Do not** call `setStyle`, `addStyle`, `removeStyle`, or `applyMediaStyle` from:
  - Traits (registerProductTrait).
  - Orchestration/utilities (productUtils, ProductBlockManager) except as explicitly allowed below.
- **Allowed:** productBlockStyles (the hydration layer) is the only place that applies styles from attributes. productUtils / ProductBlockManager may call helpers that live in productBlockStyles (e.g. for structural defaults) only if those helpers are conceptually part of hydration and invoked in a way that stays consistent with “attributes decide; styles follow” (e.g. after setting attributes and triggering hydration).
- **Triggering hydration:** Orchestration must not call `component.hydrate()` directly. It may set an attribute that is wired to hydration (e.g. `data-product-hydrate-refresh`) so that the component’s `change:attributes` listener runs hydration.

### Rule 3 — Traits update attributes only

- In **registerProductTrait.tsx** (and any product trait code): trait handlers must **only** call `component.addAttributes({ ... })` (and optionally mark template as changed, guard equality).
- **Must not:** call `hydrate()`, `setStyle()`, `addStyle()`, `applyMediaStyle()`, or touch `view.el`. No double update: trait writes attributes; hydration reacts.

---

## 2. File roles (target state)

| File | Role | Rule 1 | Rule 2 | Rule 3 |
|------|------|--------|--------|--------|
| **productBlock.ts** | Block registration, defaults, init (change:attributes → selectiveHydrate), view lifecycle | No DOM | No runtime setStyle in block; defaults.style is initial schema only | N/A |
| **productBlockStyles.ts** | Hydration only: read attributes, write styles (setStyle/applyMediaStyle) | No DOM | Only place that applies styles from attributes | N/A |
| **productUtils.ts** | Structure (append, classes), set attributes, trigger hydration; no direct style application | No canvas DOM | Only set attributes + trigger hydration; no setStyle/addStyle/applyMediaStyle | N/A |
| **registerProductTrait.tsx** | Trait UI; write attributes only | No view.el; trait panel DOM OK for UI | No setStyle/addStyle/applyMediaStyle | Only addAttributes |
| **ProductBlockManager.ts** | Orchestration (register, init, add product content); no direct style application | No DOM | Only set attributes + trigger hydration; no setStyle/applyMediaStyle | N/A |
| **transactionTraitUtils.ts** | Trait config (labels, names, apply callbacks); callbacks must only addAttributes | No DOM | Ensure apply*Functions only addAttributes | Config only; behavior in traits |

---

## 3. Audit summary (current state)

Findings as of the last audit:

- **productBlock.ts:** Compliant for Rule 1. Rule 2: only concern is default `style` on component definition and view `onRender`/`onUpdate` calling `hydrate()` (second source of hydration).
- **productBlockStyles.ts:** Compliant. It is the hydration layer; no DOM; styles only here from attributes.
- **productUtils.ts:** **Rule 2 violations.** Uses `setStyleFiltered` / `addStyleFiltered` / `applyMediaStyle` in `applyDefaultProductStyles`, `updateProductBlockLayout`, and add-product flow. Should be refactored to only set attributes and trigger hydration.
- **registerProductTrait.tsx:** **Rule 1 and 2 and 3 violations.** Uses `table.view.el.style.setProperty` / `cell.view.el.style.setProperty`; uses `setStyleFiltered` / `addStyleFiltered` / `applyMediaStyle` in many handlers (line height, border radius, gap, padding, image, button). Traits must only call `addAttributes`.
- **ProductBlockManager.ts:** **Rule 2 violations.** Uses `setStyleFiltered` / `applyMediaStyle` for block background and placeholder/empty state. Should drive those via attributes and hydration.
- **transactionTraitUtils.ts:** Compliant if the referenced `apply*Function`s (e.g. from paddingUtils, borderRadiusUtils) only call `addAttributes`. If any call setStyle/applyMediaStyle, those must be moved to attribute-only and hydration.

---

## 4. Refactor direction (no code here)

1. **registerProductTrait.tsx:** Remove all `view.el` usage and all `setStyleFiltered` / `addStyleFiltered` / `applyMediaStyle` from trait handlers. Each trait handler: read value → optional guard → `component.addAttributes({ ... })`. Ensure every product visual has a corresponding `data-*` attribute and a hydrator in productBlockStyles.
2. **productUtils.ts:** Replace direct style application in `applyDefaultProductStyles`, `updateProductBlockLayout`, and add-product path with attribute updates and a single hydration trigger (e.g. `data-product-hydrate-refresh`). Move style logic into productBlockStyles hydrators.
3. **ProductBlockManager.ts:** Replace `setStyleFiltered` / `applyMediaStyle` for block/card/placeholder with attribute updates; hydration applies the same styles.
4. **productBlock.ts (optional):** Prefer single source of hydration (e.g. only `change:attributes` + one orchestration point); consider reducing defaults.style and applying equivalent styles from attributes in hydration.
5. **transactionTraitUtils / paddingUtils / borderRadiusUtils:** Confirm every `apply*Function` used by product traits only calls `addAttributes`; fix any that set styles.

---

## 5. Quick checklist (before changing product block code)

- [ ] No `component.view.el` or canvas DOM mutation/read for styling.
- [ ] No `setStyle` / `addStyle` / `applyMediaStyle` in traits or in productUtils/ProductBlockManager (except inside productBlockStyles).
- [ ] Traits only call `component.addAttributes(...)` (and guard / mark changed).
- [ ] Hydration triggered only from component’s `change:attributes` (or documented orchestration point); no direct `hydrate()` from utils/traits.
- [ ] New visual behavior = new/updated attribute + hydrator in productBlockStyles.
