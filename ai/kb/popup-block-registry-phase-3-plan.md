# Popup Block Registry — Phase 3 Implementation Plan (Builder)

**Parent:** [`popup-block-registry.md`](popup-block-registry.md)  
**Prerequisites:** Phase 0–2 complete ([`popup-block-registry-phases-0-2.md`](popup-block-registry-phases-0-2.md))  
**Status:** Phase 3 **complete** (Waves 3.0–3.9)  
**Test naming:** `blocks/__tests__/wave3-{N}-blocks.test.ts(x)` (Wave 3.0 uses `layoutStyles` + `builderBlockRegistry.exhaustiveness`)  
**Last updated:** 2026-06-28  

---

## 1. Objective

Modularize the Popup Builder **canvas + property panel** layers so each content block lives in its own folder, dispatched through a **builder registry** — without changing user-visible behavior, export HTML, or embed runtime.

**Success looks like:**

| Metric | Today | Target after Phase 3 |
|--------|-------|----------------------|
| `FormElementRenderer.tsx` | ~2,424 lines | **≤1,200 lines** (router + shared shell only) |
| `ElementPropertiesPanel.tsx` switch | ~20 cases + layout logic | **Dispatch-only** for content blocks |
| New block touch surface | 3+ large files | **`blocks/<id>/` + registry entry** |
| Preview ≈ export ≈ canvas | Manual / ad hoc | **Per-block parity checklist in CI** |

**Explicit non-goals for Phase 3:**

- Unified cross-layer registry (Phase 4)
- Moving serializer fns out of `serializeElements.ts` (optional **3b** — see §12)
- Layout/section DnD changes (`LayoutRenderer`, `formStructure.ts`)
- Embed/runtime changes (Phase 1 done)
- New block features or UX redesign
- Fixing unrelated pre-existing gaps unless required for extraction safety (listed in §8)

---

## 2. Decisions required before coding (Socratic gate)

Resolve these in a short team review **before** the first extraction PR:

| # | Question | Recommendation | If not decided |
|---|----------|----------------|----------------|
| D1 | Registry root path | **`components/form-builder/blocks/`** (colocated with canvas) | Agents must not invent a second path |
| D2 | Input field family | **One shared primitive** (`shared/CanvasInputField.tsx`) + thin `text-input` / `email-input` wrappers | Duplicate extractions, drift |
| D3 | Lazy vs eager imports | **Lazy** `React.lazy` per block canvas + properties (palette metadata eager) | Bundle size regression |
| D4 | PR granularity | **One block type per PR** (or one family: text-input + email-input) | Harder bisect on regressions |
| D5 | Parity gate | **Mandatory** manual + automated checklist per block PR (§10) | “Refactor-only” regressions ship |
| D6 | `feedback` / `floating-image` / `cross` canvas | **Wire or stub explicitly** in registry (§8) — do not leave `default:` placeholder | Silent broken blocks |
| D7 | i18n for layer labels | Move hardcoded `getElementName` strings to **`t()`** only when touching a block | Avoid big-bang locale PR |

**Sign-off artifact:** comment on first Phase 3 PR linking agreed D1–D7.

---

## 3. Current state (post Phase 0–2)

### 3.1 Already modular ✅

| Layer | Status |
|-------|--------|
| Serializer map | `popupHtmlSerializer/blockRegistry.ts` + `definePopupBlockSerializers` |
| Embed runtime registry | `embed/blockRegistry.ts` + `blockInitializers/*` |
| Property panel components | **26 files** under `property-panels/element-properties/` |
| Default properties | `canvas/ElementDefaults.ts` → `getDefaultPropertiesForType` |
| Palette metadata | `FormBlock.tsx` → `BLOCK_CONFIGS` |
| Structure / layout | `LayoutRenderer`, `formStructure.ts`, section/column panels |

### 3.2 Still monolithic ⚠️

| File | Lines | Problem |
|------|-------|---------|
| `FormElementRenderer.tsx` | ~2,424 | **All canvas `switch` cases**, shared shells, preview state, layout math |
| `ElementPropertiesPanel.tsx` | ~532 | Content `switch` + **section/column** navigation (must stay) |
| `FormBlock.tsx` | ~314 | Palette only — low risk |

### 3.3 Shared logic embedded in monolith (extract first)

These are **not** block-specific; extract to `blocks/shared/` **before** block extractions:

| Helper / concern | Lines (approx) | Used by |
|------------------|----------------|---------|
| `makeCanvasInputShell`, floating vs standard outline | ~80 | text-input, email, phone, textarea, date |
| `renderLabel`, `renderOptionEditor` | ~60 | fields, checkbox, radio |
| Preview option/date local state | ~40 | checkbox, radio, date, dropdown |
| `getSelectionContainerStyle`, `getBlockLayoutStyle` | ~250 | **All blocks** — type-specific branches |
| DnD wrapper shell (`data-pbn-canvas-block`, drag indicators) | ~80 | All edit-mode blocks |
| Preview wrapper (`pointer-events-none` rules) | ~40 | All preview blocks |
| `PhoneCountryCodeSelect` | ~30 | phone-input |
| Product picker dialog state | ~20 | product |

**Rule:** Block modules receive **computed styles** from shell; they do not reimplement margin/width/device rules unless block-specific branch is documented.

---

## 4. Target architecture

### 4.1 Directory layout (recommended D1)

```
components/form-builder/
  blocks/
    registry.ts                 # BuilderBlockDefinition map + lazy loaders
    types.ts                    # CanvasBlockProps, BuilderBlockMeta
    shared/
      CanvasBlockShell.tsx      # Edit + preview outer wrapper (DnD, selection chrome)
      CanvasInputShell.tsx      # Input outline / floating label shell
      CanvasFieldLabel.tsx
      useCanvasPreviewFieldState.ts
      layoutStyles.ts           # getSelectionContainerStyle, getBlockLayoutStyle (pure fns)
    text/
      TextCanvas.tsx
      index.ts                  # re-export meta + lazy components
    button/
      ButtonCanvas.tsx
      index.ts
    … (one folder per content BlockType)
  canvas/
    FormElementRenderer.tsx     # Thin router (~150–250 lines)
  property-panels/
    ElementPropertiesPanel.tsx  # Thin router + layout/section logic unchanged
```

**Do not** put layout blocks (`one-column`, `section`, …) in this registry — they stay in structure pipeline per parent KB §4.2.

### 4.2 Builder block definition (contract)

```ts
/** Content blocks only — not layout types */
export type BuilderContentBlockType = Exclude<
  BlockType,
  'one-column' | 'two-column' | 'three-column' | 'section'
>;

export interface BuilderBlockDefinition {
  id: BuilderContentBlockType;

  /** Palette — migrated from BLOCK_CONFIGS */
  palette: {
    labelKey: string;              // i18n path (future); literal ok during migration
    category: 'standard' | 'chrome';
    icon: ComponentType;
    emailBuilderIconSrc?: string;
    maxInstances?: number;
    requiresStore?: boolean;         // product, coupon
  };

  /** Layer panel display name */
  layerLabelKey: string;

  /** Factory — wrap getDefaultPropertiesForType today; colocate defaults in block folder later */
  createDefaultProperties: () => Record<string, unknown>;

  /** Canvas — lazy */
  Canvas: ComponentType<CanvasBlockProps>;

  /** Properties — lazy; omit only if truly chrome-only via parent panel */
  PropertiesPanel?: ComponentType<PropertiesPanelProps>;

  /** Shell overrides */
  shell?: {
    skipSelectionTransition?: boolean;
    previewInteractive?: boolean;
    selectionContainerStyle?: (ctx: CanvasLayoutContext) => CSSProperties;
    blockLayoutStyle?: (ctx: CanvasLayoutContext) => CSSProperties;
  };
}
```

### 4.3 `CanvasBlockProps` (stable public API per block)

Mirror today’s `FormElementRenderer` inner needs — **freeze this interface** before extractions:

```ts
export interface CanvasBlockProps {
  element: FormElement;
  device: 'desktop' | 'mobile';
  isPreview: boolean;
  isActive: boolean;
  onClick: () => void;
  onUpdateElement: (elementId: string, updates: Partial<FormElement['properties']>) => void;
  onInvalidPhoneInput?: () => void;
  /** Passed from shell — block must not re-resolve column list layout */
  columnListStyle?: React.CSSProperties;
}
```

**Forbidden in block modules:**

- Importing `embed/*`, `popupHtmlSerializer/*`
- Reading/writing DOM for persisted state
- Calling `onFormElementsChange` directly (shell/parent only)

### 4.4 Router flow (target)

```
FormElementRenderer
  → resolve BuilderBlockDefinition from registry
  → CanvasBlockShell (shared: DnD, preview/edit wrapper, layout styles)
      → def.Canvas (block inner markup only)
```

```
ElementPropertiesPanel (content path only)
  → registry.PropertiesPanel ?? fallback message
  (section/column/cross paths unchanged)
```

---

## 5. Migration strategy — zero-behavior rule

### 5.1 Golden rule

> **Every Phase 3 PR is a pure move** unless explicitly tagged `fix:` in the same block folder.

- Copy code verbatim first; rename imports second
- No drive-by formatting, trait renames, or “while we’re here” fixes
- One block (or one family) per PR
- PR description includes §10 checklist ticks

### 5.2 Phase 3 internal waves

| Wave | Focus | Risk | Duration |
|------|-------|------|----------|
| **3.0** | Shared shell + `layoutStyles.ts` + registry skeleton (no block moves) | Low | **Done** (2026-06-28) |
| **3.1** | Leaf blocks: `divider`, `text`, `button` | Low | **Done** (2026-06-28) |
| **3.2** | Input family: `text-input`, `email-input`, `phone-input`, `textarea`, `date` | Medium | **Done** (2026-06-28) |
| **3.3** | Choice fields: `dropdown`, `check-box`, `radio` | Medium | **Done** (2026-06-28) |
| **3.4** | Media: `image`, `video`, `floating-image` | Medium | **Done** (2026-06-28) |
| **3.5** | Commerce: `product`, `coupon` | Medium | **Done** (2026-06-28) |
| **3.6** | Interactive: `timer`, `spinner` | **High** | **Done** (2026-06-28) |
| **3.7** | Chrome + feedback: `cross`, `feedback` | Medium | **Done** (2026-06-28) |
| **3.8** | Palette merge: `BLOCK_CONFIGS` → registry; property panel dispatch cleanup | Low | **Done** (2026-06-28) |
| **3.9** | Delete dead code; enforce exhaustiveness test | Low | **Done** (2026-06-28) |

**Optional 3b (serializer file split):** after 3.9, move each `serializeX` into `blocks/<id>/serialize.ts` and re-export into serializer map — **separate wave**, same one-block-per-PR rule.

**Total:** ~6–8 weeks calendar (1 engineer), parallelizable with features if waves are respected.

### 3.3 Recommended extraction order (within waves)

Extract **simplest → most coupled**:

1. `divider` — small, isolated layout quirks documented
2. `text` — InlineEditor, width % on shell
3. `button` — RTE, action preview links
4. `text-input` + `email-input` — shared input primitive first, then split files
5. `phone-input` — country select
6. `textarea` — resize height trait
7. `date` — picker popover + preview local state
8. `dropdown`, `check-box`, `radio` — shared option list patterns
9. `image`, `video` — placeholders + link normalization
10. `product`, `coupon` — store dialog, spin outcome preview
11. `timer`, `spinner` — heaviest preview behavior
12. `cross`, `feedback`, `floating-image` — fix gaps while extracting

---

## 6. Wave 3.0 — detailed tasks (foundation)

**Goal:** Registry and shell exist; `FormElementRenderer` delegates to shell + **still inline** `renderElement` until 3.1.

| Task | Acceptance |
|------|------------|
| Create `blocks/types.ts` with `CanvasBlockProps`, `CanvasLayoutContext` | Typecheck |
| Extract `layoutStyles.ts` from `FormElementRenderer` — **pure functions**, unit tests for 2–3 block types | Tests pass; **no visual change** |
| Create `CanvasBlockShell.tsx` — move preview/edit wrapper, DnD refs, `blockWrapperLayoutClass` | Manual: drag still works |
| Create `registry.ts` with `registerBuilderBlock`, `getBuilderBlock`, idempotent registration | Test: unknown type → explicit fallback UI |
| Wire `FormElementRenderer` to shell; keep internal switch temporarily | All existing tests green |
| Add `builderBlockRegistry.exhaustiveness.test.ts` | Fails until all 21 content types registered (can stub `Canvas: FallbackCanvas`) |

**Do not** lazy-load in 3.0 — add lazy in 3.1 when first block moves out.

### 6.1 Wave 3.0 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/types.ts` — `CanvasBlockProps`, `CanvasLayoutContext`, `BuilderBlockDefinition` | Done |
| `blocks/shared/layoutStyles.ts` — pure layout fns extracted from `FormElementRenderer` | Done (~330 lines) |
| `blocks/shared/CanvasBlockShell.tsx` — DnD, selection chrome, preview/edit wrapper | Done (~121 lines) |
| `blocks/registry.ts` + `registerBuilderBlocks.ts` — 21 content types registered | Done |
| `FormElementRenderer` delegates to shell; **internal switch unchanged** | Done (2,040 lines, −384 from ~2,424) |
| `layoutStyles.test.ts` + `builderBlockRegistry.exhaustiveness.test.ts` | Done (7 tests) |

**Validation:** embed + serializer registry + Wave 3.0 block tests green (40 tests). No routing via registry yet — stubs are exhaustiveness-only until 3.1.

### 6.2 Wave 3.1 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/divider/DividerCanvas.tsx` | Done |
| `blocks/text/TextCanvas.tsx` | Done |
| `blocks/button/ButtonCanvas.tsx` | Done |
| Shared: `useCanvasElementUpdates.ts`, `normalizeFormBuilderLinkHref.ts` | Done |
| Registry routes all blocks via `getBuilderBlock` | Done |
| `getElementName` uses registry `layerLabel` for all blocks | Done |
| Divider `shell.skipSelectionTransition` via registry | Done |
| `FormElementRenderer` switch cases removed for 3 blocks | Done (1,752 lines, −288 from 3.0) |
| `wave3-1-blocks.test.ts` | Done (4 tests) |

**Validation:** 11/11 builder block tests green.

### 6.3 Wave 3.2 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/shared/CanvasTextInputField.tsx` + `buildCanvasInputShellStyles.ts` | Done |
| `blocks/shared/CanvasFieldLabel.tsx`, `traitBorderProps.ts` | Done |
| `text-input`, `email-input`, `phone-input`, `textarea`, `date` canvases | Done |
| `email-input` added to `layoutStyles` fullWidth-off path (quirk fix) | Done |
| `wave3-2-blocks.test.tsx` — text-input regression + RTL smoke | Done |
| `FormElementRenderer` switch cases removed for input family | Done (1,165 lines, −587 from 3.1) |

**Validation:** 17/17 builder block tests green.

### 6.4 Wave 3.3 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/shared/useCanvasPreviewOptions.ts` — preview option state for choice fields | Done |
| `blocks/shared/CanvasOptionEditor.tsx`, `isClickOnCheckboxControl.ts` | Done |
| `dropdown/DropdownCanvas.tsx`, `check-box/CheckboxCanvas.tsx`, `radio/RadioCanvas.tsx` | Done |
| `check-box` + `radio` `shell.skipSelectionTransition` via registry | Done |
| `FormElementRenderer` switch cases removed for choice fields | Done (873 lines, −292 from 3.2) |
| `wave3-3-blocks.test.tsx` — registry + routing smoke | Done |

**Validation:** 22/22 builder block tests green.

### 6.5 Wave 3.4 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/image/ImageCanvas.tsx`, `blocks/video/VideoCanvas.tsx` | Done |
| `blocks/floating-image/FloatingImageCanvas.tsx` — first functional canvas (was `default:` stub) | Done |
| Shared: `getVideoEmbedUrl`, `resolveElementPositionMargin`, `resolveFloatingImageShellStyle`, `buildFloatingImageTagStyle` | Done |
| `layoutStyles` + `CanvasBlockShell` — floating-image absolute shell via `blockProperties` | Done |
| `FormElementRenderer` switch cases removed for media family | Done (594 lines, −279 from 3.3) |
| `wave3-4-blocks.test.tsx` + floating-image layout test | Done |

**Validation:** 30/30 builder block tests green.

### 6.6 Wave 3.5 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/product/ProductCanvas.tsx` + picker dialog state | Done |
| `blocks/coupon/CouponCanvas.tsx` | Done |
| `wave3-5-blocks.test.tsx` | Done |

### 6.7 Wave 3.6 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/timer/TimerCanvas.tsx` | Done |
| `blocks/spinner/SpinnerCanvas.tsx` | Done |
| `wave3-6-blocks.test.tsx` | Done |

### 6.8 Wave 3.7 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/feedback/FeedbackCanvas.tsx` — first functional canvas (was `default:` stub) | Done |
| `blocks/cross/CrossCanvas.tsx` + `CrossCanvasBlock.tsx` — FormCanvas mounts cross; registry adapter for exhaustiveness | Done |
| `FeedbackProperties` wired in `ElementPropertiesPanel` | Done |
| `feedback` added to preview interactive types | Done |
| `FormElementRenderer` — **109 lines**, registry router only | Done |
| `wave3-7-blocks.test.tsx` | Done |

**Validation:** 39/39 builder block tests green.

### 6.9 Legacy cleanup — completion notes (2026-06-27)

No backward-compat shims in the block registry layer:

| Removed | Replacement |
|---------|-------------|
| `registerStubBlocks.ts` + stub registration loop | `registerBuilderBlocks.ts` — all 21 real canvases |
| `FallbackCanvas` + `Canvas !== FallbackCanvas` routing | Direct `getBuilderBlock` → `Canvas` dispatch |
| `isTimerLegacyDefaultChromeBorder.ts` | `omitTraitBorder` from `borderStyle === 'none'` |
| `CrossCanvasRegistryStub` | `CrossCanvasBlock` adapter |

**Policy:** Phase 3 block registry does not preserve pre-registry DOM quirks or migration paths.

### 6.10 Wave 3.8 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| `blocks/paletteIcons.ts` + `layoutBlockPalette.ts` + `palette.ts` | Done |
| Palette metadata merged into `BuilderBlockDefinition.palette` | Done |
| `registerBuilderBlockProperties.ts` — properties panel wiring (split from canvas bundle) | Done |
| `FormBlock.tsx` reads `getPaletteConfig()` — **`BLOCK_CONFIGS` deleted** | Done |
| `ElementPropertiesPanel` — registry `PropertiesPanel` dispatch; switch removed | Done |
| `LayerPanel.getElementName` — registry `layerLabel` fallback | Done |
| `wave3-8-blocks.test.ts` | Done |

### 6.11 Wave 3.9 — completion notes (2026-06-28)

| Task | Status |
|------|--------|
| Dead code removed (`BLOCK_CONFIGS`, property panel switch, `isCrossElement` branch) | Done |
| Exhaustiveness test — palette + canvas + properties panel per block | Done |
| `FormElementRenderer` ≤120 lines; no type switch | Done |
| `wave3-9-blocks.test.ts` — structural guards | Done |

**Validation:** 48/48 builder block tests green. Phase 3 builder registry complete.

---

## 7. Per-block extraction checklist (repeat every PR)

### 7.1 Code moves

- [ ] Create `blocks/<id>/<Id>Canvas.tsx` — **only** inner markup from former `case '<id>'`
- [ ] Register in `blocks/registry.ts` with `palette`, `layerLabel`, `createDefaultProperties`
- [ ] Point `PropertiesPanel` to existing `*Properties.tsx` (or add missing wiring)
- [ ] Remove `case '<id>'` from `FormElementRenderer` switch
- [ ] Remove duplicate label from `getElementName` switch — use `registry.layerLabel`
- [ ] If block had shell overrides (`skipSelectionTransition`, etc.), set `def.shell`

### 7.2 Behavior parity (mandatory)

- [ ] **Edit mode:** select, drag, duplicate, delete unchanged
- [ ] **Preview mode:** readonly rules; interactive types still clickable per `formPreviewInteraction`
- [ ] **Desktop + mobile** device toggle: width, fontSize, padding, hideOnDesktop/Mobile
- [ ] **Layers panel** label correct
- [ ] **Properties panel** opens and mutates model (not DOM)
- [ ] **Export:** `serializePopupExport` HTML unchanged for snapshot containing block
- [ ] **Store gating** (product/coupon): blocked dialog when disconnected
- [ ] **maxInstances** (email, spinner): palette disabled at cap

### 7.3 Automated tests (add when missing)

| Block kind | Minimum automated coverage |
|------------|----------------------------|
| All | Registry key exists; canvas renders without throw (RTL smoke) |
| Static | Serializer golden already exists from Phase 2 |
| Form field | Canvas smoke + serializer golden |
| Interactive | Above + preview interaction test if applicable |
| Store | Mock `isStoreConnected` false → drop blocked |

---

## 8. Known gaps — resolve during Phase 3 (not optional for those types)

These are **today** inconsistencies; extraction must **not** preserve broken state silently.

| Block | Canvas today | Properties today | Phase 3 action |
|-------|--------------|------------------|----------------|
| `feedback` | Falls through to `default:` placeholder | `FeedbackProperties.tsx` exists but **not wired** in panel switch | Extract canvas + wire panel in same PR |
| `floating-image` | Falls through to `default:` | No dedicated panel found | Implement canvas (mirror serializer) + properties or document defer |
| `cross` | Falls through to `default:` | `ButtonCrossProperties` via `isCrossElement` branch | Extract `CrossCanvas` + keep chrome positioning |
| `video` | Canvas exists | Panel exists | Verify autoplay props parity with export (separate `fix:` if needed) |

**Policy:** Registry exhaustiveness test treats **registered + functional** as required — stubs must be `{ id, palette, Canvas: BlockNotImplemented }` with a **visible** dev-only banner, not empty div in production paths.

---

## 9. Property panel modularization

### 9.1 Today

Most panels already live in `element-properties/*.tsx`. `ElementPropertiesPanel` is a **router + layout column navigator**.

### 9.2 Target

```ts
// registry entry
propertiesPanel: lazy(() => import('./text/TextPropertiesPanel')),
// re-export existing TextElementProperties — no rewrite required
```

### 9.3 Keep in `ElementPropertiesPanel` (do not extract)

- Section/column views (`currentView`, `handleAddColumn`, …)
- Cross detection routing to `ButtonCrossProperties` (until merged into registry as `cross` type)
- `useEffect` property initialization for layout/cross (move to factories over time)

### 9.4 Property panel PR rule

When extracting block `X`:

1. Add `case 'X'` → registry dispatch **or** remove case and use `getBuilderBlock(type).PropertiesPanel`
2. Verify `onUpdate` / `handleElementUpdate` wiring identical
3. Confirm device toggle props passed through

---

## 10. Testing & QA matrix (bug prevention)

### 10.1 Automated (CI)

| Suite | Purpose |
|-------|---------|
| `builderBlockRegistry.exhaustiveness.test.ts` | All `BuilderContentBlockType` keys registered (Wave 3.0) |
| `layoutStyles.test.ts` | Pure layout fn snapshots (Wave 3.0) |
| `wave3-{N}-blocks.test.ts(x)` | Per-wave registry + routing smoke after each extraction (N = 1…9) |
| `popupHtmlSerializer.test.ts` | Export unchanged (Phase 2) |
| `embed/__tests__/*` | Runtime unchanged (Phase 1) |

### 10.2 Manual QA script (per block PR)

Copy into MR description:

```markdown
### Phase 3 block parity — `<blockType>`

**Editor**
- [ ] Drop from palette into empty column / existing column / between blocks
- [ ] Select, style tab changes reflect on canvas
- [ ] Drag reorder within column
- [ ] Duplicate / delete
- [ ] Mobile preview: traits apply

**Preview**
- [ ] Fullscreen preview: block visible and behaves per spec
- [ ] Teaser flow (if form has teaser): N/A or verified

**Export**
- [ ] Save → inspect htmlByStep contains prior golden markers
- [ ] Detail thumbnail renders

**Regression**
- [ ] Adjacent blocks (text + `<blockType>` + button) stack without overlap
```

### 10.3 Visual regression (recommended)

If Playwright or Chromatic is available later, capture **one screenshot per block** on desktop + mobile after 3.0. Until then, manual QA is mandatory per §5.1.

---

## 11. Risk register & mitigations

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Layout math drift (margin %, fullWidth off) | High | Overlap / wrong width in column | Centralize in `layoutStyles.ts` + unit tests; blocks don’t copy math |
| Preview local state leaks into builder | Medium | Corrupts saved form | Keep `useCanvasPreviewFieldState` in shared hook; same guard as today |
| DnD breaks after shell extract | Medium | Blocks can’t reorder | 3.0 dedicated PR; test drag in FormCanvas + PbnDnDZones |
| Spinner preview spin / email gate | High | Gamification regression | Extract last (3.6); run `spinnerEmailGate`, `spinnerButtonTrigger` tests |
| Product picker + API coupling | Medium | Store blocks fail silently | Extract with store mock tests; no API changes |
| Lazy load flash / panel delay | Low | UX jank | Prefetch selected block panel on element select |
| Circular imports (registry ↔ canvas) | Medium | Build fail | Block folders import shared + types only; registry lazy-imports canvases |
| i18n churn | Low | Locale PR noise | Defer label i18n until block touched (D7) |

### Rollback strategy

- Each block PR is independently revertable
- Keep `FormElementRenderer` switch behind **feature flag only if** parallel implementations needed — **default: direct cutover per block** to avoid dual maintenance
- If a block extraction regresses: revert single PR; registry falls back to remaining switch cases until fixed

---

## 12. Optional Phase 3b — serializer colocation

**When:** After Wave 3.9 (builder stable).

**What:** Move `serializeText`, etc. from `serializeElements.ts` → `blocks/<id>/serialize.ts`; register in existing `popupHtmlSerializer/blockRegistry.ts` via `registerPopupBlockSerializers.ts`.

**Rules:**

- Batch PRs OK when pattern is proven (wave 1 = 10 blocks)
- Run full serializer test suite (`popupHtmlSerializer.test.ts`)
- No HTML output change (diff must be empty except file paths)
- Keep sanitization imports from `popupHtmlSanitize.ts`
- Shared field helpers: `blocks/shared/serializeFieldHelpers.ts`, `serializeTypes.ts`
- Remaining inline serializers stay in `serializeElements.ts` until their wave

### 12.1 Wave 1 — **Done** (2026-06-27)

Colocated serializers (10 blocks):

| Block | File |
|-------|------|
| `text` | `blocks/text/serialize.ts` |
| `divider` | `blocks/divider/serialize.ts` |
| `button` | `blocks/button/serialize.ts` |
| `date` | `blocks/date/serialize.ts` |
| `dropdown` | `blocks/dropdown/serialize.ts` |
| `textarea` | `blocks/textarea/serialize.ts` |
| `image` | `blocks/image/serialize.ts` |
| `feedback` | `blocks/feedback/serialize.ts` |
| `video` | `blocks/video/serialize.ts` |
| `check-box` | `blocks/check-box/serialize.ts` |

Also wired in registry (still colocated import, not moved): `text-input`, `email-input` via shared `serializeTextInputLike`.

**Still inline in `serializeElements.ts` (wave 2):** `phone-input`, `radio`, `timer`, `spinner`, `product`, `coupon`, plus unwrapped `cross`, `floating-image`.

### 12.2 Wave 2 — **Done** (2026-06-27)

Colocated remaining serializers:

| Block | File | Notes |
|-------|------|-------|
| `phone-input` | `blocks/phone-input/serialize.ts` | |
| `radio` | `blocks/radio/serialize.ts` | |
| `timer` | `blocks/timer/serialize.ts` | |
| `spinner` | `blocks/spinner/serialize.ts` | |
| `product` | `blocks/product/serialize.ts` | |
| `coupon` | `blocks/coupon/serialize.ts` | |
| `cross` | `blocks/cross/serialize.ts` | Unwrapped — imported by `serializeElements.ts` |
| `floating-image` | `blocks/floating-image/serialize.ts` | Unwrapped — imported by `serializeElements.ts` |

**After wave 2:**

- `serializeElements.ts` is ~75 lines: wrapper shell + device visibility only
- `registerPopupBlockSerializers.ts` registers all 18 wrapped content types
- Phase 3b **complete** — every content block has colocated canvas + export serializer

**Benefit:** Single folder per block for builder + export; enables Phase 4 unified metadata.

---

## 13. Phase 4 handoff criteria (do not start until)

- [x] All 21 content types in builder registry with functional canvas
- [x] `FormElementRenderer.tsx` ≤120 lines
- [x] `BLOCK_CONFIGS` deleted — palette via `blocks/palette.ts` + registry
- [x] Exhaustiveness tests green for builder registry
- [ ] §8 gaps closed or explicitly documented as product deferrals
- [ ] Team agrees on unified `popupBlockRegistry.ts` shape (parent KB § Phase 4)

---

## 14. Timeline summary

```
Week 1      3.0 Foundation (shell, layoutStyles, registry skeleton)
Week 2      3.1 divider, text, button
Week 3      3.2 input family
Week 4      3.3 choice fields + start media
Week 5      3.4 media + commerce start
Week 6      3.5 product, coupon
Week 7      3.6 timer, spinner
Week 8      3.7 cross, feedback, floating-image + 3.8 palette merge + 3.9 cleanup
Optional    3b serializer colocation — **Done** (waves 1–2)
```

Buffer **+20%** for spinner/product QA.

---

## 15. Documentation updates (per wave)

| When | Update |
|------|--------|
| 3.0 | ~~This doc → status “In progress”~~ **Done** (2026-06-28); link from `popup-block-registry.md` § Phase 3 |
| Each block PR | None required unless export behavior changes |
| 3.8 | `popup-block-registry.md` §6.1 builder path; code review checklist palette line |
| 3.9 | Mark Phase 3 done in parent KB; metrics §11 |

---

## 16. References

- Completed phases: [`popup-block-registry-phases-0-2.md`](popup-block-registry-phases-0-2.md)
- Structure DnD (untouched): [`popup-builder-structure-dnd.md`](popup-builder-structure-dnd.md)
- Preview contract: `components/form-builder/shared/formPreviewInteraction.tsx`
- Default factories: `canvas/ElementDefaults.ts`, `structure/formStructure.ts` → `createBlockElement`
- Code review: [`ai/verification/code-review-checklist.md`](../verification/code-review-checklist.md) § Popup Builder
