# Popup Runtime V2 — Design (Merchant-Side Only)

**Scope:** `adflipr-app/src/layouts/PopupBuilder/embed/**` and `public/adflipr-popup-embed.js`  
**Out of scope:** Builder, serializer, backend APIs (assumed stable)  
**Platforms:** Shopify theme app embed + WordPress plugin loaders only (manual snippet remains supported, not first-class)  
**Scale target:** ~10k popup impressions/day (aggregate; not per-merchant hot path)  
**Timeline:** Incremental delivery over ~6 months (small PRs; no big-bang rewrite)  
**Related:** `popup-embed-production-bugs.md` (PEB-###)

### Greenfield policy (2026-06-27)

**No production storefront customers today.** Backward compatibility with embed V1 behavior is **not required**.

- OK to change `window.AdfliprPopup` API shape, script bootstrap, and loader contracts (Shopify/WP) in the same release as runtime changes.
- OK to remove legacy paths when replaced (e.g. duplicate init fallbacks, hidden-DOM dismiss-only shells) instead of flagging.
- OK to cache-bust / rename bundle URL without dual-version hosting.
- **Still keep:** serializer HTML contract and backend API payloads (out of scope for runtime-only work).
- **Still prefer:** incremental PRs and tests — not a license for one-shot rewrite.

---

## P0 implementation status

| ID | Item | Status | Notes |
|----|------|--------|-------|
| **P0-1** | Singleton `EmbedHost` | **Done** (2026-06-27) | `embed/host/EmbedHost.ts`; idempotent `start()` / `PopupEmbedRuntime.init()`; tests in `embed/__tests__/embedHost.test.ts` |
| **P0-2** | Retry embed config fetch + re-arm triggers | **Done** (2026-06-27) | `fetchRetry.ts`; `TriggerWatcher.confirmPresentation/abortPresentation/skipCandidate`; tests in `fetchRetry.test.ts`, `embedConfigRecovery.test.ts` |
| **P0-3** | Debug mode | Pending | Can ship debug UI in main bundle behind query/attr (no separate build required unless size grows) |
| **P0-4** | Block initialization registry | Pending | Can break step handler binding — no legacy init path |
| **P0-5** | Full `destroy()` semantics | Pending | Can remove DOM on dismiss (not only `display:none`) |

---

## Design principles

1. **Evolve, don’t rewrite** — `PopupEmbedRuntime` becomes a thin orchestrator; extract modules from existing files.
2. **Same HTML contract** — `innerHTML` of serialized steps unchanged; block hooks read existing `data-adflipr-*` attrs.
3. **One runtime instance per page** — singleton `EmbedHost` replaces duplicate inits (PEB-010, PEB-011).
4. **Fail visible internally, fail silent for shoppers** — errors go to debug mode + optional RUM beacon, not alert().
5. **Modal surfaces are accessible** — overlay + flyout meet baseline a11y contract; banner/inline exempt where not modal.

---

## 1. Runtime state machine

### V1 problem
Trigger fires once → watcher stopped → HTML fetch fail = terminal blank (PEB-001). No explicit states; `presentationMode` + hidden DOM only.

### V2 model

One **SessionController** per page (singleton). Each **campaign slot** (at most one visible presentation) moves through:

```
IDLE
  → ARMED          (candidates loaded, TriggerEngine running)
  → TRIGGERED      (popupId selected, fetch not yet complete)
  → LOADING        (GET embed config in flight)
  → RENDERING      (innerHTML + block inits)
  → VISIBLE        (presentation shown)
  → SUBMITTING     (optional; form POST telemetry in flight)
  → DISMISSED      (hidden, not destroyed)
  → COOLDOWN       (optional; queue delay before next campaign)
  → ERROR          (recoverable; may return to ARMED or COOLDOWN)
  → DESTROYED      (page unload or explicit teardown)
```

**Inline embed** uses a parallel sub-machine `INLINE_IDLE → INLINE_LOADING → INLINE_VISIBLE` that **skips ARMED/TRIGGERED** when `mode=inline` and `popupId` is pinned (fixes PEB-002, PEB-003).

**Teaser** is a pre-state: `TEASER_VISIBLE → (click) → LOADING main`.

### Transitions (critical fixes)

| From | Event | To | Note |
|------|-------|-----|------|
| TRIGGERED | fetch fail (retry exhausted) | ARMED | Re-arm TriggerEngine; do not consume campaign permanently |
| VISIBLE | dismiss | DISMISSED | Run presentation teardown |
| DISMISSED | queue has next + rules pass | COOLDOWN → ARMED | Session queue |
| VISIBLE | step change | RENDERING | Same campaign |
| * | `destroy()` | DESTROYED | Full cleanup |

### Recommendation summary

| | |
|--|--|
| **Why change?** | PEB-001, unclear recovery, impossible multi-campaign sequencing |
| **Benefits** | Predictable behavior; testable transitions; support can read state in debug |
| **Migration** | Medium — refactor `runtime.ts` into controller + states; keep public HTML paths |
| **Backward compat** | External behavior same when queue length = 1 and fetch succeeds |
| **Business value** | Fewer “popup never showed” tickets; path to welcome + exit on same visit |

---

## 2. Module boundaries

### Target layout (evolve from current 13 files)

```
embed/
  index.ts              # Bootstrap, singleton, public API (thin)
  host/
    EmbedHost.ts        # Singleton: one instance per window
    SessionController.ts # State machine orchestrator
  engine/
    TriggerEngine.ts    # triggers.ts → rename/refactor
    UrlContext.ts         # SPA popstate + href snapshot
    FrequencyStore.ts     # localStorage + in-memory fallback
  fetch/
    EmbedClient.ts      # api.ts + retry wrapper
    ConfigCache.ts        # session memory cache for embed HTML (TTL 5m)
  presentation/
    PresentationRouter.ts # overlay | banner | flyout | teaser | inline
    OverlayPresentation.ts
    BannerPresentation.ts
    FlyoutPresentation.ts
    TeaserPresentation.ts
    InlinePresentation.ts
    shells/               # existing *Layout.ts
  render/
    StepRenderer.ts       # innerHTML + bind handlers
    DeviceRouter.ts       # resolveStepHtml + matchMedia
  blocks/
    registry.ts           # block init registry
    timer.ts              # timerFit.ts
    spinner.ts
    video.ts
    floatingLabel.ts      # extracted from bindStepHandlers
  a11y/
    ModalSurface.ts       # focus trap, scroll lock, aria
  queue/
    CampaignQueue.ts
  telemetry/
    TelemetryClient.ts    # post + retry queue
  debug/
    DebugBus.ts           # trace events, overlay badge
  lifecycle/
    Disposable.ts         # typed teardown interface
    DisposableStack.ts
  compat/
    v1Adapter.ts          # maps old global API if needed
  constants.ts types.ts utils.ts  # unchanged surface
```

**Rule:** modules talk through `SessionController` events, not circular imports. Presentations implement `PresentationHandle extends Disposable`.

| | |
|--|--|
| **Why change?** | 720-line `runtime.ts` god object; leaks untestable |
| **Benefits** | Unit test per module; parallel workstreams in 6-month plan |
| **Migration** | Medium — move code file-by-file; re-export from old paths during transition |
| **Backward compat** | Bundle entry still `embed/index.ts` |
| **Business value** | Faster fixes for Shopify vs WP without touching trigger code |

---

## 3. Lifecycle

### Page lifecycle (Shopify / WordPress)

```
Script load (async)
  → DOMContentLoaded (or immediate if ready)
  → EmbedHost.getOrCreate()
  → resolve platform context (siteId, apiBase from script tag)
  → SessionController.start()
       → FrequencyStore.init (safe storage)
       → EmbedClient.fetchCandidates (retry)
       → TriggerEngine.arm(candidates)
       → InlinePresentation.mountAll(discovered mounts)  // immediate path
  → [user activity / triggers]
  → SessionController.showCampaign(popupId)
  → … state machine …
  → beforeunload / pagehide → EmbedHost.destroy()
```

**WordPress:** consent deferred load calls same `EmbedHost.getOrCreate()` after allow event — no second code path.

**Shopify (V2 addition):** optional `data-adflipr-wait-consent` on script tag mirroring WP event (`adflipr:popup-embed:allow`).

| | |
|--|--|
| **Why change?** | PEB-010 bootstrap + init duplication |
| **Benefits** | Single entry; consent-aware without fork |
| **Migration** | Low for core; Medium for Shopify Liquid one attribute |
| **Backward compat** | Default = immediate init like V1 |
| **Business value** | GDPR Shopify parity with WP |

---

## 4. Error recovery

### Error classes

| Class | Shopper UX | Internal |
|-------|------------|----------|
| `CandidatesFetchError` | No popup | Debug: siteId, status, retry count |
| `EmbedConfigFetchError` | No popup this attempt; re-arm | Debug: popupId, status |
| `NormalizeConfigError` | Skip campaign | Log corrupt payload version |
| `RenderError` | Skip step / fallback primary step | Debug: stepId |
| `BlockInitError` | Partial UI (e.g. no spinner) | warn once per block |
| `StorageError` | Continue in-memory IDs | Debug flag |
| `TelemetryError` | Never block submit UI | Queue retry |

### Recovery policy

- **Never** call `TriggerEngine.stop()` until campaign reaches `VISIBLE` or explicit permanent skip (frequency).
- **RenderError:** try `htmlPrimaryStepId`, then abort campaign and re-arm.
- **Global fatal:** if candidates fail after retries, stay `IDLE` with periodic re-fetch (1× at 30s, 1× at 5m) — cheap at 10k/day scale.

| | |
|--|--|
| **Why change?** | PEB-001 terminal failure |
| **Benefits** | Resilience on slow 3G / API blips |
| **Migration** | Low-Medium — wrap existing fetch/render |
| **Backward compat** | Success path identical |
| **Business value** | Direct conversion recovery |

---

## 5. Retry strategy

### EmbedClient retries (V2)

| Operation | Retries | Backoff | Cache |
|-----------|---------|---------|-------|
| GET candidates | 2 | 500ms, 2s | Session memory 60s |
| GET embed config | 3 | 300ms, 1s, 3s | Memory map popupId+version, TTL 5m |
| POST telemetry | 3 | 1s, 5s, 30s | In-memory queue max 20 events |

Use `fetch` + `AbortSignal.timeout(8000)` per attempt. Respect `Retry-After` on 429.

**No retry** on 404 / 403 (unpublished) — mark campaign skipped for session.

| | |
|--|--|
| **Why change?** | Silent single-attempt fetch |
| **Benefits** | Handles transient CDN/API failures |
| **Migration** | Low — replace `api.ts` calls with `EmbedClient` |
| **Backward compat** | Same URLs and payloads |
| **Business value** | Less flakiness on mobile networks |

---

## 6. Accessibility contract

### Modal surfaces (overlay + flyout when acting as dialog)

| Requirement | V2 behavior |
|-------------|-------------|
| Role | `role="dialog"` + `aria-modal="true"` on content shell |
| Label | `aria-labelledby` → first heading in exported form, or `aria-label="Promotion"` fallback |
| Focus | Move focus to first focusable on open |
| Trap | Tab cycles within dialog |
| Escape | Existing `closeOnEsc` attr respected |
| Restore | Return focus to `document.activeElement` pre-open |
| Scroll | `body` scroll lock while open (overlay/flyout only) |
| Reduced motion | `@media (prefers-reduced-motion: reduce)` → spinner duration cap 300ms |

**Banner + inline:** not modal; no trap. Banner close button requires `aria-label` in export (serializer already owns markup — runtime only verifies).

| | |
|--|--|
| **Why change?** | PEB-006, PEB-100; enterprise a11y |
| **Benefits** | WCAG baseline; keyboard shoppers |
| **Migration** | Medium — `ModalSurface` wraps existing shells |
| **Backward compat** | HTML unchanged; shell attrs added at runtime |
| **Business value** | Unblocks enterprise / public sector |

---

## 7. Debug mode

### Activation (any of)

- `data-adflipr-debug="1"` on script tag
- `localStorage.adflipr_popup_debug = '1'`
- `?adflipr_popup_debug=1` once → sets localStorage (support workflow)

### Debug outputs

1. **Console:** structured `[AdfliprPopup:debug]` JSON lines (phase, state, ms, popupId, error class).
2. **On-page badge** (bottom-left, `z-index` below popup): state + last error; click copies trace clipboard.
3. **Trace buffer:** last 50 events in memory (`DebugBus`), no PII by default.
4. **Trigger trace:** reuse existing `TriggerEvaluationTrace` from TriggerEngine.

Optional POST to internal RUM endpoint later — **not required for V2** at 10k/day.

| | |
|--|--|
| **Why change?** | Support relies on merchant DevTools |
| **Benefits** | L2 diagnosis without store access |
| **Migration** | Low — tree-shaken when debug off |
| **Backward compat** | Opt-in only |
| **Business value** | Lower support cost |

---

## 8. Event architecture

### Internal: typed event bus (in-process)

```ts
type EmbedEvent =
  | { type: 'state/changed'; from; to; popupId? }
  | { type: 'fetch/candidates'; ok; ms; count }
  | { type: 'fetch/config'; popupId; ok; ms; attempt }
  | { type: 'presentation/show'; mode; popupId }
  | { type: 'presentation/dismiss'; popupId; reason }
  | { type: 'telemetry/enqueue'; eventType }
  | { type: 'error'; class; detail }
  | { type: 'queue/dequeue'; popupId };
```

- **DebugBus** subscribes to all.
- **TelemetryClient** subscribes to presentation + submit only.
- **No DOM CustomEvent** for merchants in V2 (avoid collisions); public API uses callbacks (§12).

| | |
|--|--|
| **Why change?** | Cross-cutting concerns scattered in runtime |
| **Benefits** | Test assertions; debug trace for free |
| **Migration** | Low-Medium — introduce bus; migrate logging incrementally |
| **Backward compat** | Internal only |
| **Business value** | Observability foundation |

---

## 9. Block initialization registry

### Pattern

```ts
interface BlockInitializer {
  id: string;
  selector: string; // e.g. [data-adflipr-popup-spinner="true"]
  init(container: HTMLElement, ctx: BlockContext): Disposable;
}
```

**V2 registrars:** `timer`, `spinner`, `video`, `floating-label` (extracted from `bindStepHandlers`).

`StepRenderer.render(html)`:

1. `container.innerHTML = html`
2. `registry.initAll(container, ctx)` → returns `DisposableStack`
3. On step change / dismiss: `stack.dispose()` **before** next innerHTML

Fixes PEB-052, PEB-054, PEB-090.

| | |
|--|--|
| **Why change?** | Ad-hoc inits; leaks on step change |
| **Benefits** | New blocks = one registrar; guaranteed teardown |
| **Migration** | Medium — move existing init code into registrars |
| **Backward compat** | Same selectors and attrs |
| **Business value** | Safer gamification + multi-step |

---

## 10. Memory management

### Disposable contract

Every subscription returns `Disposable`:

- TriggerEngine listeners + timers
- `ResizeObserver` (timers)
- Spinner RAF + redirect timeout
- ModalSurface focus trap
- StepRenderer block stack
- `matchMedia` listener

`PresentationHandle.dismiss()` → dispose presentation disposables.  
`SessionController.destroy()` → dispose all.

**On dismiss:** dispose presentation disposables and **remove overlay/banner/flyout/teaser shells from DOM** (greenfield — no hidden-DOM phase).

| | |
|--|--|
| **Why change?** | PEB-041, PEB-090–094 |
| **Benefits** | SPA-safe; long session stability |
| **Migration** | Medium — add dispose alongside existing code |
| **Greenfield** | No flag; remove-on-dismiss is default |
| **Business value** | Headless storefront readiness |

---

## 11. Version compatibility

### Layers (no backend change)

| Layer | Mechanism |
|-------|-----------|
| **Script** | `adflipr-popup-embed.js?v=BUILD_ID` — **required** on Shopify/WP (no unversioned URL) |
| **Runtime API** | `window.AdfliprPopup.version = '2.x.y'` — bump on breaking API changes |
| **Config** | `PopupEmbedConfig.version` — cache key only |
| **HTML** | Unknown `data-adflipr-*` ignored |

**No feature flags for rollout** — ship behavior directly; repo + deploy are the only migration surface.

| | |
|--|--|
| **Why change?** | PEB-024 stale JS |
| **Benefits** | Simple deploy story |
| **Migration** | Update Shopify Liquid + WP loader + deploy embed in one change |
| **Greenfield** | No dual-version hosting or V1 fallback paths |
| **Business value** | Faster P0–P5 delivery |

---

## 12. Public runtime API

### Target API (greenfield — shape can change until first customer)

```js
window.AdfliprPopup.version
window.AdfliprPopup.init({ apiBaseUrl?, siteId? })
window.AdfliprPopup.mount(popupId, { apiBaseUrl?, siteId?, mode?, mount? })  // mount required when mode=inline
window.AdfliprPopup.destroy()
window.AdfliprPopup.getState()
window.AdfliprPopup.on(event, fn)   // 'show' | 'dismiss' | 'error' | 'submit'
window.AdfliprPopup.off(event, fn)
window.AdfliprPopup.debug(enable?)
window.AdfliprPopup.resetFrequency(popupId?)
```

Singleton: second `init()` → no-op (or `destroy()` then `init()` if we document explicit reset).

| | |
|--|--|
| **Why change?** | PEB-011, PEB-017 |
| **Benefits** | Clear integrator surface for Shopify/WP |
| **Migration** | Update loaders once before launch |
| **Greenfield** | May rename/remove `mount()` if inline-only via DOM discovery |
| **Business value** | Supportable API from day one |

---

## 13. Popup queue management

### V2 session queue (client-only; backend unchanged)

After campaign `DISMISSED`:

1. Mark `popupId` shown this session (sessionStorage set).
2. If other candidates were **ready at fire time** but lost priority, do not auto-show (still V1).
3. **V2 queue:** re-arm TriggerEngine with candidates **excluding** session-shown ids.
4. Optional **cooldown** 2s between presentations (avoid flash).

**Cap:** max **2** presentations per page load default (configurable via script `data-adflipr-max-per-session="2"`).

| | |
|--|--|
| **Why change?** | PEB-013 welcome + exit |
| **Benefits** | Multi-campaign without backend priority API |
| **Migration** | Medium — TriggerEngine re-arm + session set |
| **Greenfield** | Default max=2; no “V1 single popup” preservation |
| **Business value** | Merchandising flexibility |

---

## 14. Destroy semantics

### `EmbedHost.destroy()` order

1. SessionController → `DESTROYED`
2. TriggerEngine.stop()
3. Active presentation.dismiss(force: true)
4. Block DisposableStack.dispose()
5. ModalSurface teardown
6. TelemetryClient flush (best-effort, 2s cap)
7. Remove listeners: beforeunload, pagehide, matchMedia, UrlContext
8. DebugBus optional persist to sessionStorage ring buffer

**Idempotent:** multiple `destroy()` safe.

**WordPress / SPA:** call on consent revoke optional future; V2 only on full page unload.

| | |
|--|--|
| **Why change?** | PEB-094 production never destroys |
| **Benefits** | Clean SPA remounts |
| **Migration** | Medium |
| **Backward compat** | No destroy call = same as V1 today |
| **Business value** | Platform stability |

---

## 15. Testing strategy

### Unit (Jest) — existing pattern

| Module | Focus |
|--------|--------|
| SessionController | state transitions, fetch fail → re-arm |
| TriggerEngine | priority, OR triggers, frequency |
| UrlContext | popstate re-match |
| CampaignQueue | session cap, exclude shown |
| EmbedClient | retry backoff mock |
| Block registry | dispose clears observers |
| ModalSurface | focus trap mock |
| FrequencyStore | storage throw → memory |

### Integration (Jest + jsdom)

- `StepRenderer` + sample HTML fixtures from serializer tests
- Full path: arm → trigger → mock fetch → visible

### E2E (Playwright) — priority scenarios

1. Shopify Dawn fixture page — overlay show + dismiss + a11y tab
2. WP fixture with consent event delay
3. Inline mount immediate render
4. Double script tag → single VIEW telemetry
5. Abort embed fetch → popup shows on retry
6. Two campaigns → second after dismiss (queue flag on)

### CI gates for V2

- Bundle size ≤ 85 KB minified (budget; current ~74 KB)
- No `react` in embed bundle (regression)
- `popup-embed-production-bugs.md` P0 cases automated

| | |
|--|--|
| **Why change?** | Preview ≠ live; leaks undetected |
| **Benefits** | Confidence for incremental ship |
| **Migration** | Ongoing — add tests per module moved |
| **Backward compat** | V1 tests remain green |
| **Business value** | Release safety |

---

## Six-month incremental roadmap

| Phase | Duration | Deliverables | Flags |
|-------|----------|--------------|-------|
| **M1** | Month 1 | EmbedHost singleton, EmbedClient retry, storage safe, debug bus (console only) | **P0-1 done** |
| **M2** | Month 2 | SessionController + ERROR/re-arm; inline immediate path; destroy semantics | — |
| **M3** | Month 3 | Block registry + DisposableStack; memory leak fixes | — |
| **M4** | Month 4 | ModalSurface a11y; scroll lock | Default on |
| **M5** | Month 5 | UrlContext SPA; CampaignQueue max 2 | Default on |
| **M6** | Month 6 | Debug badge; script cache bust in Shopify/WP | Required version query |

---

## Explicit non-goals (V2)

- Shadow DOM / iframe isolation (V3 candidate)
- Client-side JSON schema renderer
- Backend priority API or queue
- Manual snippet first-class UX
- Reducing HTML export format

---

## Architecture review snapshot

| Area | V2 stance |
|------|-----------|
| **Approve** | Singleton, retry, block registry, debug mode, idempotent init |
| **Conditional** | Queue (flagged), a11y (modal only), remove-on-dismiss (flagged) |
| **Defer** | Full CSS isolation, RUM pipeline, JSON renderer |

---

## References

- Bug registry: `ai/kb/popup-embed-production-bugs.md`
- Block registry contract (builder + serializer + runtime hooks): `ai/kb/popup-block-registry.md`
- Current entry: `embed/index.ts`, `embed/runtime.ts`
- Platform loaders: `shopify/extensions/.../popup-embed.liquid`, `wordpress/includes/class-adflipr-popup-embed.php`
