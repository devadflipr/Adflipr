# Popup Block Registry — Phase 0–2 Implementation Plan

**Parent:** [`popup-block-registry.md`](popup-block-registry.md)  
**Status:** Phase 0 ready to execute · Phase 1–2 planned  
**Last updated:** 2026-06-27  

This document is the **execution plan** for the first three registry phases. Phase 3+ remain in the parent doc.

---

## Overview

| Phase | Goal | Duration | Risk | Depends on |
|-------|------|----------|------|------------|
| **0** | Process + checklists wired into review/docs | **2–3 days** | None | Parent contract doc |
| **1** | Embed runtime block init registry + teardown | **2–3 weeks** | Medium (storefront) | Phase 0 |
| **2** | Serializer `Record<BlockType, SerializeFn>` map | **1–2 weeks** | Low | Phase 0 (optional overlap with Phase 1 tail) |

**Team:** 1 engineer can run Phase 0 + 2 sequentially; Phase 1 benefits from someone familiar with embed/runtime.ts.

---

## Phase 0 — Documentation & checklist

### Objective

Every new or changed **content block** PR is reviewed against the same contract **before** Phase 1 code lands. No runtime/serializer refactor in this phase.

### Tasks

| # | Task | Owner | Output | Status |
|---|------|-------|--------|--------|
| 0.1 | Canonical contract doc | — | [`popup-block-registry.md`](popup-block-registry.md) | Done |
| 0.2 | Detailed phase plan (this doc) | — | `popup-block-registry-phases-0-2.md` | Done |
| 0.3 | Popup section in code review checklist | Eng | [`ai/verification/code-review-checklist.md`](../verification/code-review-checklist.md) | Planned |
| 0.4 | Link from serialization KB | Eng | [`popup-html-serialization.md`](popup-html-serialization.md) §Related | Planned |
| 0.5 | Link from runtime v2 design | Eng | [`popup-runtime-v2-design.md`](popup-runtime-v2-design.md) §References | Done |
| 0.6 | Cursor rule pointer | Eng | `.cursor/rules/popup-builder-architecture-kb.mdc` §13 | Done |
| 0.7 | Optional: GitLab MR description snippet | Eng | Copy-paste block in this doc §0.4 | Planned |

### 0.4 MR description snippet (optional paste)

```markdown
### Popup content block checklist
- [ ] `BlockType` updated (`types.ts`)
- [ ] Palette (`FormBlock.tsx` / `BLOCK_CONFIGS`)
- [ ] Canvas (`FormElementRenderer` or extracted block module)
- [ ] Properties panel (`ElementPropertiesPanel` + `*Properties.tsx`)
- [ ] Serializer (`serializeElements` + `styles/inline/` if needed)
- [ ] Embed runtime + `contract/` attrs (if interactive)
- [ ] Tests: `popupHtmlSerializer.test.ts` and/or `popupEmbedRuntime.test.ts`
- [ ] i18n (7 locales) for user-visible strings
- [ ] KB: block row in `popup-html-serialization.md` if export behavior new/changed

Ref: `ai/kb/popup-block-registry.md` §9
```

### Acceptance criteria (Phase 0)

- [ ] Reviewer can find Popup block checklist without opening parent doc
- [ ] Serialization KB points to registry for new blocks
- [ ] No production code changes required for Phase 0 sign-off

### Exit criteria → start Phase 1

Team agrees Phase 0 checklist is used on the **next block-touching PR** (even before registry code exists).

---

## Phase 1 — Embed runtime block registry

### Objective

Replace ad-hoc `initPopupSpinners` / `initPopupTimers` / `initPopupVideos` + inline floating-label listeners in `bindStepHandlers` with a **registry that always disposes** before the next `innerHTML` swap.

Fixes open bugs: **PEB-052**, **PEB-054**, **PEB-090** ([`popup-embed-production-bugs.md`](popup-embed-production-bugs.md)).

### Current state (audit)

| Init | File | Called from | Teardown today |
|------|------|-------------|----------------|
| Close / action buttons | `runtime.ts` `bindStepHandlers` | every step mount | None (re-bind on new HTML — OK) |
| Floating labels | `runtime.ts` `bindStepHandlers` | `[data-pbn-floating-field]` | **Leaks** input listeners on step change |
| Form submit | `runtime.ts` `bindStepHandlers` | `POPUP_EXPORT_FORM_SELECTOR` | `dataset.adfliprPopupBound` prevents re-bind but old nodes gone |
| Spinner | `embed/spinner.ts` `initPopupSpinners` | `bindStepHandlers` | **RAF leak** on dismiss mid-spin (PEB-054) |
| Timer fit | `embed/timerFit.ts` `initPopupTimers` | `bindStepHandlers` | **ResizeObserver leak** (PEB-052, PEB-090) |
| Video autoplay | `embed/video.ts` `initPopupVideos` | `bindStepHandlers` | Low risk (one-shot play) |

**Not in scope for Phase 1 registrars:** close/action/submit handlers stay in `bindStepHandlers` (presentation-level, not block-type registry).

### Target architecture

```
embed/
  disposable.ts          # Disposable, DisposableStack
  blockRegistry.ts       # registerBlockInitializer, initAll, types
  blockInitializers/
    spinner.ts           # wraps PopupSpinnerController + dispose
    timer.ts             # wraps timer ResizeObserver + dispose
    video.ts             # wraps initPopupVideos (optional no-op dispose)
    floatingLabel.ts     # extracted from bindStepHandlers
  runtime.ts             # bindStepHandlers calls blockRegistry.initAll + stores stack
```

### 1.1 Core types (`embed/disposable.ts`) — **Done**

```ts
export interface Disposable {
  dispose(): void;
}

export class DisposableStack implements Disposable {
  push(d: Disposable): void;
  dispose(): void;
}
```

Helpers: `disposableFrom(fn)`, `disposableEventTarget(el, type, handler)`.

**Shipped:** `embed/disposable.ts`, `embed/__tests__/disposable.test.ts`

### 1.2 Block initializer contract (`embed/blockRegistry.ts`) — **Done (skeleton)**

```ts
export interface BlockContext {
  onStepChange: StepChangeHandler;
  spinner?: SpinnerEmbedContext;  // existing embed context slice
}

export interface BlockInitializer {
  id: string;           // 'spinner' | 'timer' | 'video' | 'floating-label'
  selector: string;   // from contract/ — e.g. POPUP_SPINNER_SELECTOR
  init(root: HTMLElement, ctx: BlockContext): Disposable;
}

export function registerBlockInitializer(init: BlockInitializer): void;
export function initAllBlocks(container: HTMLElement, ctx: BlockContext): DisposableStack;
```

**Rules:**

- Selectors imported from `@pb/contract/popupEmbedContract` / `popupSpinnerContract` only.
- `initAllBlocks` queries each selector under `container`, calls `init` per match, pushes disposables onto stack.
- Idempotent: registrars may keep `dataset.*Bound` guards until dispose removes nodes.

**Shipped:** `embed/blockRegistry.ts`, `embed/__tests__/blockRegistry.test.ts` (no runtime wiring yet)

### 1.2 Block initializers + runtime wiring — **Done**

**Shipped:**

- `embed/blockInitializers/` — `floatingLabel`, `video`, `timer`, `spinner`, `registerAll`
- Contract selectors in `popupEmbedContract.ts` (`POPUP_FLOATING_FIELD_SELECTOR`, etc.)
- `timerFit.ts` — `bindPopupTimerUnits()` returns `Disposable`
- `spinner.ts` — `PopupSpinnerController.dispose()` (RAF + redirect timeout + listeners)
- `runtime.ts` — `stepBlockDisposables`, `disposeStepBlockInits()` before every `innerHTML` swap
- Tests: `floatingLabel.test.ts`, `stepTransitionDispose.test.ts`, `runtimeSmoke.test.ts`
- Rebuilt `public/adflipr-popup-embed.js`

### 1.3 Runtime integration (`runtime.ts`) — **Done** (merged into 1.2)

**Add field:**

```ts
private stepBlockDisposables: DisposableStack | null = null;
```

**On every step HTML mount** (`renderStepHtml`, teaser mount, overlay/banner/flyout open):

```ts
this.stepBlockDisposables?.dispose();
this.stepBlockDisposables = initAllBlocks(container, ctx);
```

**On dismiss / destroy:**

```ts
this.stepBlockDisposables?.dispose();
this.stepBlockDisposables = null;
```

**Remove from `bindStepHandlers`:**

- `initPopupSpinners`, `initPopupTimers`, `initPopupVideos` direct calls
- Floating-label forEach loop → `floatingLabel` registrar

**Keep in `bindStepHandlers`:**

- Close buttons, action buttons, form submit (presentation chrome)

### 1.4 Per-block migration tasks

#### Spinner (`blockInitializers/spinner.ts`)

| Task | Detail |
|------|--------|
| Refactor `PopupSpinnerController` | Expose `dispose()` — cancel RAF, remove listeners, clear redirect timeout |
| Replace `initPopupSpinners` | Registrar calls controller; returns disposable |
| Tests | Extend `spinnerEmailGate.test.ts`, `spinnerButtonTrigger.test.ts` |
| PEB-054 | Test: dismiss mid-spin → no further RAF |

#### Timer (`blockInitializers/timer.ts`)

| Task | Detail |
|------|--------|
| Refactor `timerFit.ts` | Return `Disposable` with `ResizeObserver.disconnect()` |
| PEB-052 / PEB-090 | Test: 20 step transitions → RO count stable (or mock RO) |

#### Video (`blockInitializers/video.ts`)

| Task | Detail |
|------|--------|
| Thin wrapper | `initPopupVideos` → registrar; dispose no-op or abort pending play |
| Tests | Existing `embed/__tests__/video.test.ts` still pass |

#### Floating label (`blockInitializers/floatingLabel.ts`)

| Task | Detail |
|------|--------|
| Extract | Lines 652–667 from `bindStepHandlers` |
| Dispose | Remove input/change listeners on each field |

### 1.5 Registration bootstrap

`embed/index.ts` or `runtime.ts` module init:

```ts
import './blockInitializers/registerAll'; // side-effect: registerBlockInitializer x4
```

### 1.6 Testing plan

| Test file | Scenario |
|-----------|----------|
| `embed/__tests__/blockRegistry.test.ts` | **New** — initAll finds nodes, dispose clears listeners |
| `embed/__tests__/stepTransitionDispose.test.ts` | **New** — mount step-1 → step-2 → timer RO disposed |
| `popupEmbedRuntime.test.ts` | Existing step transitions still pass |
| `spinnerEmailGate.test.ts` | Use registry path or keep calling init via registry |

### 1.7 Rollout

1. Land `disposable.ts` + `blockRegistry.ts` (no behavior change).
2. Migrate **floating-label** first (lowest risk, proves dispose on step change).
3. Migrate **video** (simple).
4. Migrate **timer** (RO fix).
5. Migrate **spinner** (RAF + email gate — highest risk).
6. Rebuild embed bundle: `npm run build:popup-embed` (CI already runs on embed TS changes).
7. Manual: popup-embed-demo — multi-step + timer + spin + dismiss mid-spin.

### Phase 1 acceptance criteria

- [ ] `bindStepHandlers` does not call `initPopup*` directly
- [ ] `stepBlockDisposables.dispose()` called before every `container.innerHTML = html`
- [ ] All four registrars registered
- [ ] PEB-052, PEB-054, PEB-090 regression tests green (or bugs closed)
- [ ] No new listeners after 20 step transitions (timer test)
- [ ] `public/adflipr-popup-embed.js` rebuilt and committed if TS embed sources changed

### Phase 1 risks & mitigations

| Risk | Mitigation |
|------|------------|
| Spinner email gate breaks | Keep `SpinnerEmbedContext` on `BlockContext`; existing tests |
| Double init on same node | Dispose before innerHTML; bound dataset cleared with node |
| Bundle size | Registry is tiny; no new deps |

---

## Phase 2 — Serializer map — **Done**

### Objective

Replace the `switch (element.type)` in `serializeElement()` with a typed map. Keep per-block functions in `serializeElements.ts` (or move to `blocks/<id>/serialize.ts` later in Phase 3).

Add **compile-time exhaustiveness** so a new `BlockType` without serializer fails CI.

### Current state (audit)

- **Dispatch:** `serializeElements.ts` ~lines 1377–1438 — 19 content cases + layout types handled elsewhere
- **Per-block functions:** Already extracted (`serializeText`, `serializeSpinner`, …)
- **Tests:** [`popupHtmlSerializer.test.ts`](../../adflipr-app/src/layouts/PopupBuilder/utils/__tests__/popupHtmlSerializer.test.ts) — ~35 cases; good coverage for phone, feedback, spinner, timer, video, coupon, etc.
- **Gaps (verify during Phase 2):** `radio`, `check-box`, `product`, `cross`, `divider`, `text-input` may lack dedicated golden tests — add minimal fixtures

### Target files

```
utils/popupHtmlSerializer/
  blockRegistry.ts       # POPUP_BLOCK_SERIALIZERS map + types
  serializeElements.ts     # serializeElement → map lookup + wrapper only
  serializeLayout.ts       # unchanged (layout blocks)
```

### 2.1 Types (`blockRegistry.ts`)

```ts
import type { BlockType } from '@pb/components/form-builder/types';
import type { FormElement } from '@pb/components/form-builder/types';
import type { SerializeContext } from './types';

/** Inner HTML only — wrapper applied by serializeElement */
export type BlockSerializeFn = (
  element: FormElement,
  ctx: SerializeContext,
) => string;

/** Content blocks only — not section/column layout types */
export type ContentBlockType = Exclude<
  BlockType,
  'one-column' | 'two-column' | 'three-column' | 'section'
>;

export const POPUP_BLOCK_SERIALIZERS = {
  text: serializeText,
  button: serializeButton,
  // ... every ContentBlockType
} as const satisfies Record<ContentBlockType, BlockSerializeFn>;
```

**Special cases preserved in wrapper (not in map):**

- `cross` → returns full button HTML **without** standard wrapper (today: early `return serializeCross`)
- Device visibility gate (`isHiddenOnDevice`) — wrapper in `serializeElement`
- `getExportBlockWrapperStyle` wrapper — unchanged

### 2.2 Refactor `serializeElement`

```ts
export function serializeElement(element: FormElement, ctx: SerializeContext): string {
  // ... visibility gate ...

  if (element.type === 'cross') {
    return serializeCross(element, ctx);
  }

  const serialize = POPUP_BLOCK_SERIALIZERS[element.type as ContentBlockType];
  if (!serialize) {
    return ''; // layout types should not reach here
  }

  const inner = serialize(element, ctx);
  if (!inner) return '';

  const wrapperStyle = getExportBlockWrapperStyle(...);
  return wrapInnerHtml(inner, wrapperStyle); // extract if not already a helper
}
```

Remove the 40-line `switch`.

### 2.3 Exhaustiveness enforcement

**Option A (recommended):** `satisfies Record<ContentBlockType, BlockSerializeFn>` on the map — TypeScript errors when `BlockType` grows.

**Option B:** CI script diffing `BlockType` union vs map keys — backup if layout types pollute union.

**Decision for Phase 2:** Option A + `ContentBlockType` exclude layout.

### 2.4 Test plan

| Task | Detail |
|------|--------|
| All existing tests green | No snapshot churn expected (behavior-preserving) |
| **New:** `blockRegistry.exhaustiveness.test.ts` | Import map keys; assert equals `ContentBlockType` set |
| **Gap fill:** minimal golden per block | One `it()` each for blocks missing dedicated test (radio, product, cross, …) |
| Fixture helper | `minimalSnapshotWithBlock(type)` in test utils — reduces boilerplate |

### 2.5 Implementation order (Phase 2)

| Step | Work | Verify |
|------|------|--------|
| 2.1 | Add `blockRegistry.ts` exporting map (re-export fns from `serializeElements.ts`) | Compile |
| 2.2 | Switch `serializeElement` to map lookup | All serializer tests pass |
| 2.3 | Delete old switch | Diff review — no behavior change |
| 2.4 | Add exhaustiveness test | CI fails if key missing |
| 2.5 | Add golden tests for gaps | Coverage table updated in parent doc |

### 2.6 Rollout

- **Single PR** preferred (refactor only, no HTML output change).
- Run: `npm test -- popupHtmlSerializer` (or project test script for PopupBuilder utils).
- Update [`popup-html-serialization.md`](popup-html-serialization.md) — note registry map location.

### Phase 2 acceptance criteria

- [x] No `switch (element.type)` in `serializeElement` for content blocks
- [x] `definePopupBlockSerializers` uses `satisfies Record<WrappedContentBlockType, BlockSerializeFn>`
- [x] 41/43 `popupHtmlSerializer.test.ts` tests pass (+6 gap-fill); 2 pre-existing video autoplay tests fail (`serializeVideo` never emitted autoplay attrs)
- [x] Exhaustiveness test in `popupHtmlSerializer/__tests__/blockRegistry.exhaustiveness.test.ts`

**Shipped:** `popupHtmlSerializer/blockRegistry.ts`, refactored `serializeElement`, gap-fill tests

### Phase 2 out of scope

- Moving serialize functions to per-block folders (Phase 3)
- Builder registry (Phase 3)
- Changing HTML output or sanitization rules

---

## Timeline (suggested)

```
Week 1
  Mon–Tue   Phase 0 complete (checklists, links, team sign-off)
  Wed–Fri   Phase 1.1–1.2: disposable + blockRegistry skeleton

Week 2
  Mon–Tue   Phase 1: floating-label + video registrars
  Wed–Fri   Phase 1: timer registrar + RO dispose tests

Week 3
  Mon–Wed   Phase 1: spinner registrar + RAF dispose
  Thu–Fri   Phase 1: integration testing, embed bundle, bug closure

Week 4
  Mon–Wed   Phase 2: blockRegistry map + serializeElement refactor
  Thu–Fri   Phase 2: exhaustiveness + gap tests, docs update
```

Phases 1 and 2 can overlap in week 3–4 if two engineers — **do not** merge Phase 2 before Phase 1 spinner dispose is tested if same files touched.

---

## Definition of done (Phases 0–2)

| Phase | Done when |
|-------|-----------|
| **0** | Checklist in verification doc; serialization KB linked; one PR reviewed with checklist |
| **1** | Runtime uses registry; dispose on step change; PEB-052/054/090 addressed; embed tests green |
| **2** | Serializer map + exhaustiveness test; zero content switch in `serializeElement`; all serializer tests green |

---

## References

- [`popup-block-registry.md`](popup-block-registry.md) — master contract
- [`popup-runtime-v2-design.md`](popup-runtime-v2-design.md) §9–10 — runtime disposable pattern
- [`popup-embed-production-bugs.md`](popup-embed-production-bugs.md) — PEB-052, PEB-054, PEB-090
- Code: `embed/runtime.ts` `bindStepHandlers`, `utils/popupHtmlSerializer/serializeElements.ts`
