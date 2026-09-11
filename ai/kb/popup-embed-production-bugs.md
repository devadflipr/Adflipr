# Popup Embed — Production Bug Registry

**Purpose:** Consolidated register of identified production reliability, lifecycle, and integration bugs for Popup Builder storefront embed.  
**Audience:** Engineering, QA, support.  
**As of:** 2026-06-27  
**Scope:** Builder save/publish, serializer, embed runtime, triggers, analytics, Shopify/WordPress integrations, backend public APIs.

**Greenfield (2026-06-27):** No live storefront customers — fixes may break prior embed behavior without a migration period. Coordinate Shopify/WP loader + bundle deploy together.

**Related KB:** `popup-css-lifecycle-audit.md`, `popup-html-serialization.md`, `wordpress-popup-rendering-audit.md`, `popup-analytics-and-submissions-spec.md`, `popup-rendering-css-architecture.md` (target CSS artifact model)

**Legend**

| Field | Values |
|-------|--------|
| **Prob** | H = high, M = medium, L = low likelihood in production |
| **Impact** | Critical / High / Medium / Low |
| **Priority** | P0 (block GA) / P1 (next sprint) / P2 (backlog) |
| **Status** | Open / Fixed / Won't fix (documented) |

**Popup types:** Overlay · Banner · Flyout · Inline · Gamification · Multi-step · Teaser · All

---

## P0 — Block GA or critical merchant impact

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-001 | Embed HTML fetch fails after trigger; watcher already stopped | All | H | Critical | Throttle network; fire trigger; abort `GET /popups/embed/{id}` | Retry fetch 2–3×; on failure re-arm watcher or show fallback | Playwright: abort embed GET → retry or re-arm | **Fixed** (PEB-FIX-003) |
| PEB-002 | Inline mount waits for site triggers instead of immediate render | Inline | H | Critical | `<div data-adflipr-popup="ID">` + exit-intent-only rules | Bypass triggers for inline: fetch pinned id and render on load | E2E: inline visible without trigger | Open |
| PEB-003 | Wrong popup in inline mount when higher-priority candidate wins | Inline | M | Critical | Two published popups; inline pins A; B wins priority | Filter `onFire` to pinned `popupId` in inline mode | Inline mount shows only pinned id | Open |
| PEB-004 | `localStorage`/`sessionStorage` throw breaks entire embed init | All | M | Critical | Safari private mode / strict storage; call `init()` | try/catch in `getOrCreateStorageId`; fallback in-memory IDs | Mock storage throwing → init still runs | Open |
| PEB-005 | Double form submit → duplicate SUBMIT / contacts | Multi-step | H | High | Double-click submit on slow network | Disable submit until complete; server idempotency key | Double-click → one POST | Open |
| PEB-006 | No focus trap — Tab escapes overlay to page behind | Overlay, Flyout | H | High | Open popup; Tab repeatedly | Focus trap + `aria-modal="true"` on shell | a11y: focus stays in dialog | Open |
| PEB-007 | Theme CSS overrides form controls (inputs, buttons, selects) | All | H | High | Embed on Shopify Dawn / WP Elementor | Scoped reset layer or shadow root phase 1 | Visual regression on theme fixtures | Open |
| PEB-008 | Publish without save — live site serves stale HTML | All | H | High | Edit in builder; publish from Detail without Save | Guard publish if unsaved; or auto-serialize on publish | Publish blocked when dirty | Open |

---

## P1 — High impact / next sprint

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-010 | Double script init (auto-bootstrap + manual init or duplicate snippet) | All | H | High | Load script twice (theme + manual) | Global singleton guard (`__adfliprPopupEmbedHost`) | Inject script twice → one watcher | **Fixed** (P0-1) |
| PEB-011 | `init()` not idempotent — leaks TriggerWatcher + duplicate listeners | All | M | High | Call `AdfliprPopup.init()` twice on SPA navigation | `destroy()` before re-init; guard second `init()` | Double init → stable listener count | **Fixed** (P0-1) |
| PEB-012 | SPA client navigation does not re-evaluate URL rules | All | H | High | Headless storefront: navigate without full reload | `popstate` + `history.pushState` hook; refetch or re-match URL | pushState to matching URL → popup fires | Open |
| PEB-013 | Only one popup per page — exit never shows after welcome | All | H | High | Welcome (immediate) + exit intent same page | Session queue or re-arm after dismiss | Two campaigns → second after dismiss | Open |
| PEB-014 | Exit intent uses `mouseout` — no fire on touch/mobile | Overlay | H | High | Mobile Safari; exit-intent-only rule | Mobile trigger strategy or document limitation in UI | Touch device: exit intent never fires | Open |
| PEB-015 | No background scroll lock while overlay open | Overlay | H | Medium | Scroll wheel on long page behind overlay | `overflow:hidden` on body while modal visible | body scroll unchanged when scrolling overlay | Open |
| PEB-016 | Preview (FormCanvas) ≠ live export HTML | All | H | High | Compare preview vs storefront same design | Preview path renders exported HTML via embed runtime | Snapshot parity test per block | Open |
| PEB-017 | `AdfliprPopup.mount(id, { mode: 'inline' })` never calls `setInlineMount` | Inline | M | High | Programmatic inline mount without DOM element | Require mount element parameter | API test with element ref | Open |
| PEB-018 | Gamification wheel min ~340px overflows small mobile popup | Gamification | H | High | 320px viewport gamification popup | Scale canvas to container width | 320px viewport wheel fits | Open |
| PEB-019 | iOS Safari `100vh` flyout clipped by browser chrome | Flyout | H | High | middle-right flyout on iPhone | `dvh` / `-webkit-fill-available` | iOS Safari visual regression | Open |
| PEB-020 | Host ancestor `transform` breaks `position:fixed` shells | All | M | High | Page builder parent with `transform` | Detect broken fixed; portal to `body` | Fixture with transformed ancestor | Open |
| PEB-021 | Candidates API cached without `Vary: User-Agent` | All | M | High | Shared CDN cache across device classes | Add `Vary: User-Agent` or disable public cache on candidates | Cache header assertion | Open |
| PEB-022 | Telemetry accepts draft/unpublished popups | All | M | Medium | POST `/events` with draft popup id | Enforce PUBLISHED + not deleted on ingest | Draft id → 403/404 | Open |
| PEB-023 | Corrupt `builderState` → blank editor → destructive save | All | M | High | Invalid metadata JSON on load | Block save on failed parse; show recovery UI | Corrupt state → save blocked | Open |
| PEB-024 | Unversioned embed JS URL — stale bundle after deploy | All | M | Medium | Deploy new runtime; browser cache old JS | Content-hash or version query on script URL | Script URL includes build id | Open |
| PEB-025 | Multiple runtimes → duplicate VIEW telemetry | All | M | Medium | Duplicate script init (#PEB-010) | Singleton runtime | Two inits → one VIEW | Open |
| PEB-026 | Dismiss does not unhoist popup `<style>` from `document.head` | All | H | High | Open popup → close → inspect `<head>` | Call `disposeStepBlockInits()` on dismiss or unhoist teardown | Close → no `data-adflipr-popup-runtime-style` nodes | Open — see **PCL-001** in `popup-css-lifecycle-audit.md` |
| PEB-027 | Global `input::placeholder` in floating-label export sheet | All | M | High | Open popup on page with native inputs | Scope under `form[data-adflipr-popup-export]` | Merchant inputs unchanged | Open — **PCL-003** |
| PEB-028 | Inline multi-popup shared hoist keys — later mount steals head CSS | Inline | M | High | Two inline mounts same page | Instance-scoped `data-adflipr-popup-runtime-style` keys | Both popups styled after sequential mount | Open — **PCL-002** |
| PEB-029 | Merchant `customCss` unscoped — can target `body`, `*` | All | L | Medium | Custom CSS `.page { display:none }` in builder | Auto-wrap in popup scope at serialize | Custom rules cannot affect page chrome | Open — **PCL-004** |
| PEB-030 | Trait `@media` rules without `!important` may not override desktop inline | All | H | High | Mobile padding on surface with desktop padding inlined | Add `!important` to trait device rules or omit desktop inline for differing props | Computed style mobile padding on narrow viewport | Open — **PCL-008**, §12 |

---

## P2 — Medium impact / backlog

### Trigger & lifecycle

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-030 | bfcache restore — no trigger re-evaluation | All | M | Medium | Back → forward with bfcache | `pageshow` persisted handler | back/forward behavior | Open |
| PEB-031 | Priority implicit (`updated DESC`) — merchant confusion | All | M | Medium | Two popups same rules; edit order | Explicit priority field | API priority test | Open |
| PEB-032 | Display sub-triggers OR (delay OR scroll OR inactivity) — early fire | All | M | Medium | Delay 30s + scroll 50%; scroll immediately | AND/OR product toggle; document | Unit trigger logic | Open |
| PEB-033 | Custom-click default selector missing on theme | All | H | Medium | Custom click on; no `.adflipr_form_trigger` | Validation in embed code panel | Wrong selector → no fire | Open |
| PEB-034 | Short pages: scroll depth instantly 100% | All | M | Medium | Page shorter than viewport | Min height guard for scroll % | Unit scroll depth | Open |
| PEB-035 | Slow 3G long gap trigger → visible popup | All | H | Medium | Throttle 3G; large HTML | Loading skeleton; prefetch HTML | Time-to-visible SLA | Open |
| PEB-036 | Candidates API 429 — silent no popup | All | M | Medium | Burst SPA remounts | Backoff + client cache | Mock 429 → retry | Open |
| PEB-037 | 60s cache stale empty candidates after first publish | All | M | Medium | Publish; load store within 60s | Shorter cache when empty | Publish → immediate candidates | Open |

### Overlay / banner / flyout

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-040 | No focus restore on dismiss | Overlay, Flyout | M | Medium | Focus link → open → close | Restore `document.activeElement` | Focus returns to trigger | Open |
| PEB-041 | Dismiss hides DOM but listeners/observers remain | All | M | Medium | Open/close 10× | `destroy()` or remove nodes on dismiss | Listener count stable | Open |
| PEB-042 | Resize/orientation swaps desktop↔mobile HTML — field values lost | All | M | Medium | Cross 480px while open | Debounce; preserve form values | Resize retains input values | Open |
| PEB-043 | Chat widget z-index beats 2147483000 | Overlay | M | Medium | Intercom + popup | Configurable z-index | Overlay not covered | Open |
| PEB-044 | Top banner under sticky theme header | Banner | H | Medium | Sticky nav + top banner | **Below Header presentation mode** + runtime header detection (see `popup-sticky-banner-presentation-modes.md`); manual px override fallback only | Visual sticky header fixture | Open |
| PEB-045 | Bottom banner vs mobile cart / cookie bar | Banner | H | Medium | Bottom banner + cookie UI | safe-area / bottom offset | Mobile screenshot | Open |
| PEB-046 | Flyout middle-center tall form clipping | Flyout | M | Medium | Tall form middle-center | Inner scroll verification | Tall form scrolls | Open |
| PEB-047 | Flyout RTL storefront — LTR-only positioning | Flyout | M | Medium | `dir=rtl` theme | Mirror positions | RTL fixture | Open |
| PEB-048 | Banner close does not restore teaser chip | Banner, Teaser | L | Low | Teaser → banner → close | Align with overlay/flyout teaser restore | Teaser+banner flow | Open |

### Inline / multi-step / gamification / teaser

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-050 | Inline multi-step layout jump on parent page | Inline, Multi-step | M | Medium | Step → thank-you height change | Min-height on mount | Stable height transition | Open |
| PEB-051 | Thank-you step missing — submit appears to do nothing | Multi-step | M | High | Submit without thank-you exported | Save validation; inline success fallback | Submit UX without thank-you | Open |
| PEB-052 | Step transition leaks ResizeObserver (timers) | Multi-step | M | Medium | 20 step transitions with timer | Disconnect RO on teardown registry | RO count stable | Open |
| PEB-053 | Teaser delay timer fires after main already opened | Teaser | L | Medium | Delay 10s; open main at 2s | Cancel teaser timer on main open | No duplicate teaser | Open |
| PEB-054 | Spinner RAF continues after dismiss mid-spin | Gamification | M | Medium | Close during spin animation | Cancel RAF on dismiss | No RAF after close | Open |
| PEB-055 | Spinner redirect timeout races user close | Gamification | L | Medium | Spin → redirect; close at 1s | Clear timeout on dismiss | Close before redirect | Open |
| PEB-056 | Invalid spinner config — silent missing wheel | Gamification | M | High | Corrupt spinner JSON in export | Save validation | Bad config blocked at save | Open |
| PEB-057 | `sessionStorage` blocked — unlimited spins | Gamification | M | Medium | Block sessionStorage | Defined degrade behavior | Block storage → documented behavior | Open |

### Analytics, save/publish, backend

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-060 | SUBMIT advances step even if telemetry fails silently | Multi-step | H | Medium | Block POST /events | Optional: show error; queue retry | Offline submit behavior | Open |
| PEB-061 | VIEW deduped per runtime but not across duplicates | All | M | Medium | #PEB-010 | Singleton | One VIEW per page | Open |
| PEB-062 | Frequency cap written on every `recordViewIfNeeded` | All | M | Low | Step transitions re-open shell | Write once per session | Cap write count | Open |
| PEB-063 | Unknown siteId → 200 empty candidates (hard to debug) | All | M | Low | Wrong `data-adflipr-site` | Optional debug header or 404 mode | Misconfig diagnostic | Open |
| PEB-064 | Unpublished embed config returns 500 not 404 | All | L | Low | Fetch draft popup embed | 404/403 response | Status code test | Open |
| PEB-065 | Candidates: load all popups per account (unbounded) | All | M | Medium | Account with 50+ popups | Pagination / index | Load test | Open |
| PEB-066 | N+1 frequency cap queries per candidate | All | M | Medium | Many candidates + visitorId | Batch cap lookup | Query count test | Open |
| PEB-067 | Metadata full replace on save drops backend-only keys | All | M | Medium | Save from editor when `rules` only on server | Merge metadata keys | rules preserved after save | Fixed — `PopupMetadataMerge` (backend) + `preservePassthroughMetadataKeys` (frontend) |
| PEB-068 | Silent export omission for unknown block types | All | M | Medium | Unknown block in snapshot | Fail save or warn | Unknown block → error | Open |
| PEB-069 | Empty `htmlByStep` save succeeds; embed fails at runtime | All | M | Medium | Save with all steps empty | Validation on save | Empty export rejected | Open |

### Security & sanitization

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-070 | `backgroundImage` URLs not sanitized in layout export | All | L | Medium | Malicious url in trait | Pass through `sanitizeMediaSrc` | XSS vector test | Open |
| PEB-071 | Custom CSS sanitizer shallow (`@import`, etc.) | All | L | Medium | Custom CSS with `@import` | CSS allowlist sanitizer | CSS injection tests | Open |
| PEB-072 | Preview injects unsanitized custom CSS (canvas) | All | M | Low | Custom CSS in preview only | Same sanitizer as export | Preview matches sanitize | Open |
| PEB-073 | No server-side HTML re-sanitize on save | All | L | Medium | Crafted API payload | Server sanitize pass | API integration test | Open |
| PEB-074 | Telemetry spoofing if popupId known | All | M | Low | POST events from arbitrary origin | Optional HMAC / domain binding | Security review test | Open |

### Platform integration

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-080 | Shopify theme embed not enabled — app connected, no script | All | H | High | Install app; skip theme embed | Post-install verification UX | Connect checklist | Open |
| PEB-081 | Shopify has no consent deferral (unlike WordPress) | All | M | Medium | GDPR store + Shopify | Consent event pattern like WP | Consent deferral test | Open |
| PEB-082 | Shopify duplicate script — no runtime singleton | All | M | High | Theme embed + manual snippet | #PEB-010 | Duplicate script test | Open |
| PEB-083 | WP strict CSP blocks inline loader IIFE | All | M | High | Security plugin CSP | Nonce/hash or external loader file | CSP fixture | Open |
| PEB-084 | WP consent never granted — script never loads | All | M | Medium | Consent filter on; no event | Document + admin warning | Consent timeout UX | Open |
| PEB-085 | Ad blocker blocks `adflipr-popup-embed.js` | All | M | Medium | uBlock with adflipr filter | Neutral filename path option | Adblock simulation | Open |
| PEB-086 | Strict CSP blocks inline styles on runtime shells | All | M | High | `style-src` without unsafe-inline | CSS classes in bundle vs cssText | CSP strict test | Open |

### Memory & listener leaks

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-090 | Timer `ResizeObserver` never disconnected | All | M | Medium | Step changes with timer block | RO registry + disconnect | RO count after 20 steps | Open |
| PEB-091 | Spinner RAF / redirect timeout not cancelled on dismiss | Gamification | M | Medium | Dismiss during spin | Cancel on destroy | RAF count after dismiss | Open |
| PEB-092 | `beforeunload` / `matchMedia` only removed in `destroy()` — prod never calls | All | M | Medium | SPA remount | Call destroy on re-init | SPA navigation test | Open |
| PEB-093 | Overlay backdrop click listener never removed | Overlay | L | Low | Create shell once; long session | Store ref; remove on destroy | Listener audit | Open |
| PEB-094 | Production path never calls `destroy()` | All | M | Medium | Long-lived SPA storefront | Lifecycle API on route change | destroy called on teardown | Open |

### Accessibility & UX gaps

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-100 | No ARIA dialog semantics on embed shells | Overlay, Flyout | H | High | Screen reader on open popup | `role="dialog"`, labelledby | a11y audit | Open |
| PEB-101 | Gamification spinner no reduced-motion respect | Gamification | M | Medium | `prefers-reduced-motion: reduce` | Skip/s shorten animation | reduced-motion test | Open |
| PEB-102 | Escape disabled for inline (by design) but not documented | Inline | L | Low | Esc on inline embed | Merchant docs | N/A | Open |
| PEB-103 | HTML5 required fields — theme hides validation UI | Multi-step | M | Medium | Submit empty required field | Export validation styling | Required field submit | Open |
| PEB-104 | Native date inputs broken styling on theme | Multi-step | H | Medium | Date block on Dawn | Export date styling | Visual date field | Open |
| PEB-105 | Phone country select truncated by theme CSS | Multi-step | H | Medium | Phone block on Elementor | Stronger export select rules | Visual phone field | Open |

### Operational / watchlist

| ID | Title | Types | Prob | Impact | Reproduce | Suggested fix | Regression test | Status |
|----|-------|-------|------|--------|-----------|---------------|-----------------|--------|
| PEB-110 | Images fail to load — no fallback | All | H | Low | Broken image URL in block | `onerror` placeholder | Broken img test | Open |
| PEB-111 | Video autoplay blocked — silent (acceptable) | All | M | Low | iOS low power mode | Document; muted retry exists | Video autoplay test | Open |
| PEB-112 | YouTube iframe blocked by merchant CSP | All | M | Medium | Strict `frame-src` | Document requirement | CSP iframe test | Open |
| PEB-113 | Countdown timer drift when tab backgrounded | All | M | Medium | Background tab 5 min | `visibilitychange` resync | Tab hide/show timer | Open |
| PEB-114 | Tablet ~481px — client desktop vs server mobile UA mismatch | All | M | Medium | iPad landscape | Align breakpoint + UA rules | Tablet UA test | Open |
| PEB-115 | DOM clobbering `#adflipr-popup-embed-root` | All | L | Low | Host defines same id | Random suffix id | Id collision test | Open |
| PEB-116 | Rate limit filter fail-open on Mongo error | All | L | Low | Mongo down | Alerting on fail-open | Chaos test | Open |
| PEB-117 | `AdfliprPopup.mount()` uncaught rejection on candidate fail | All | M | Medium | Block candidates API | try/catch in mount | mount error handling | Open |
| PEB-118 | Element-level `customCss` in builder not serialized to export | All | M | Medium | Set element custom CSS; check live | Serialize or hide in UI | Export includes customCss | Open |
| PEB-119 | Refresh while open — frequency cap blocks immediate re-show | All | H | Low | View cap 1/day; refresh | Document cap semantics | Refresh + cap test | Open |
| PEB-120 | localStorage cleared — popup shows again (expected; support confusion) | All | H | Low | Clear storage; revisit | Support docs | N/A | Open |

---

## Fixed (changelog)

| ID | Title | Fixed | Notes |
|----|-------|-------|-------|
| PEB-FIX-001 | React bundled in embed via `timerFixedWidthFit` import | 2026-06-27 | Split `timerFixedWidthFitCore.ts`; embed ~74 KB, no React |
| PEB-FIX-002 | Duplicate embed init / overlay runtimes (PEB-010, PEB-011) | 2026-06-27 | P0-1 `EmbedHost` singleton + idempotent `PopupEmbedRuntime.init()` |
| PEB-FIX-003 | Embed fetch fail = terminal blank popup (PEB-001) | 2026-06-27 | P0-2 `fetchWithRetry` (3 retries); `TriggerWatcher` pending/confirm/abort; exponential re-arm backoff |

---

## Recommended test suites (from audit)

1. **Playwright storefront matrix** — Dawn, Elementor WP, manual snippet; all presentation modes.
2. **Network profiles** — Offline, Slow 3G, abort embed fetch after trigger.
3. **Device matrix** — iPhone Safari, Android Chrome, landscape.
4. **Chaos** — double script, refresh while open, resize while open, back/forward, `localStorage.clear()`.
5. **Unit** — `TriggerWatcher`, `resolveStepHtml`, inline pin filtering, storage throw handling.
6. **Parity** — serializer HTML fixtures accepted by embed runtime per block type.

---

## Priority summary

| Priority | Count (open) | Focus |
|----------|--------------|--------|
| P0 | 8 | Fetch-after-trigger, inline embed, storage crash, double submit, a11y focus, theme CSS, publish-without-save |
| P1 | 16 | Init singleton, SPA triggers, multi-popup, mobile exit, preview parity, iOS flyout, cache headers, telemetry |
| P2 | 60+ | Leaks, banner offsets, gamification, backend scale, integration CSP, sanitization gaps |

---

## How to use this doc

- **Triage:** Pick P0 → P1 → P2; link PRs to `PEB-###` ids in commit messages.
- **QA:** Use Reproduce column as manual test steps; automate Regression test column where listed.
- **Support:** Map merchant reports to IDs (e.g. “popup flashes then nothing” → PEB-001).
- **Do not duplicate:** Update status here when fixed; reference this file from release notes.
