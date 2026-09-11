# Sticky banner — presentation modes (architecture proposal)

**Status:** Proposal (supersedes direction in ADR-005 when accepted)  
**Scope:** Sticky banner (`STICKY_BANNER` / `formType: banner`) on live storefront + builder preview  
**Out of scope:** Side message (flyout), centered popup overlay, inline **embed** campaigns (`formType: embed`)  
**Related:** ADR-005, `popup-sticky-banner-header-clearance.md`, PEB-044, `embed/bannerLayout.ts`, `embed/runtime.ts`

---

## 1. Problem statement

Today sticky banner is a **single presentation strategy**: a fixed overlay shell (`position: fixed; top: 0`) appended to `document.body` (or `position: absolute` inside builder preview’s `presentationRoot`). It does not participate in document flow and **covers** theme chrome unless separately offset.

ADR-005 rejected **manual pixel offset as primary UX**. This proposal treats overlay as **one of three modes**, defines **Below Header** as the header-aware variant, and separates **Inline** (flow) from the existing **embed** product type.

---

## 2. Current architecture (baseline)

| Layer | Behavior today |
|-------|----------------|
| Campaign type | `STICKY_BANNER` → runtime `presentationMode: 'banner'` |
| Shell | `.adflipr-popup-embed-banner` host + content node |
| Positioning | `resolveBannerHostStyle`: `fixed`, `top:0` or `bottom:0`, full width, z-index ~2147483000 |
| Builder preview | Same shell via `mountFormPreviewEmbedPresentation` + `presentationRoot` (contained absolute) |
| Config | `formPosition` (`top` \| `bottom`); no merchant-facing banner offset (ADR-005) |
| Distinct from | **`formType: embed`** — pinned `popupId`, `mode: inline`, replaces mount innerHTML (different product) |

**Assumption for this proposal:** Presentation mode is a **new dimension** on sticky banner campaigns, not a rename of embed campaigns.

---

## 3. Three presentation modes

### 3.1 Mode A — **Overlay** (current behavior)

**Semantics:** Banner is a viewport-fixed layer; site content does not reflow.

| Aspect | Detail |
|--------|--------|
| CSS | `position: fixed; left: 0; right: 0; top: 0` (or `bottom: 0`) |
| Header interaction | Banner paints **over** header unless extra inset applied |
| Scroll | Banner stays pinned while page scrolls |
| z-index | High (above theme; risk vs chat widgets — PEB-043) |
| Best for | Full-bleed promo, “takeover” strip, merchants who explicitly want overlay |
| Builder preview | Overlay on sample site iframe (with live embed suppressed via `adflipr_builder_preview=1`) |

**Unchanged from today** when mode = Overlay and position = top/bottom.

---

### 3.2 Mode B — **Below Header** (recommended default)

**Semantics:** Banner remains **fixed/sticky in the viewport** but its **top edge** (or bottom edge for bottom banners) is inset by the **effective height of site chrome** above it. Site header stays visible and clickable; banner sits immediately under it.

| Aspect | Detail |
|--------|--------|
| CSS | Still `position: fixed` (or `sticky` on host — see §5.3); `top: var(--adflipr-banner-inset-top, 0px)` |
| Header interaction | **Automatic** inset from detected/platform header height |
| Scroll | Banner tracks viewport below header (header may shrink on scroll — see dynamic behavior) |
| Fallback | Merchant override only when detection fails or is low-confidence (§4.4) — **not** default onboarding step |
| Best for | Default ecommerce: promo visible without hiding nav/cart/login |

**Not the same as manual-offset-primary:** inset is **computed at runtime**; override is exception path.

#### Dynamic header behavior (Below Header)

| Header type | Desired banner behavior |
|-------------|-------------------------|
| Static header (scrolls away) | Option policy: (a) inset only while header visible, or (b) inset locked at initial measured height — **recommend (a)** with scroll listener |
| Sticky/shrink header | Re-measure on scroll/resize (debounced); cap max inset to avoid jumpiness |
| Announcement bar + header | Sum heights of stacked top-fixed elements |
| No detectable header | inset = 0 (same as Overlay) or optional safe minimum — **recommend 0 + low-confidence flag** |

---

### 3.3 Mode C — **Inline** (document flow)

**Semantics:** Banner is inserted into the **document** at a defined anchor; it **pushes** following content down. No viewport-fixed shell (or fixed only within a short scroll region if using `position: sticky` on the banner itself).

| Aspect | Detail |
|--------|--------|
| DOM | Inject after platform-provided anchor or first `<body>` child per policy |
| CSS | `position: relative` or `sticky` within flow; width 100% |
| Header interaction | Natural — banner is **below** header in DOM order if anchor is after header |
| Scroll | Scrolls with page unless banner itself uses `position: sticky; top: <headerHeight>` |
| Best for | Themes where overlay breaks layout; SEO-sensitive merchants; long-form content sites |
| Risk | Anchor placement varies; theme updates can move anchor; harder WYSIWYG in preview |

**Naming:** Call this **`bannerPresentation: 'inline'`** in API to avoid collision with **`formType: embed`** (pinned inline form block).

---

## 4. Header detection (Shopify & WordPress)

### 4.1 What we can measure (same-origin storefront)

On live storefront, embed script runs in **page context** — full DOM/CSSOM access. Detection is **feasible but heuristic**, not guaranteed.

#### Signal pipeline (proposed)

```
1. Platform hint (if present)     → highest priority
2. DOM scan for top chrome        → candidate elements
3. Geometry filter                → width, top, height, z-index
4. Confidence score               → accept / fallback
5. Observe changes                → ResizeObserver + scroll (debounced)
```

#### Platform hints (preferred when available)

| Platform | Mechanism | Reliability |
|----------|-----------|-------------|
| **Shopify** | Theme app embed block exposes optional `data-adflipr-header-selector` or CSS variable `--adflipr-header-height` set by future theme snippet | **High** when theme cooperates |
| **Shopify** | Dawn/OS 2.0 conventions: `header.shopify-section`, `#shopify-section-header`, sticky via `.shopify-section-header-sticky` | **Medium** on stock Dawn derivatives |
| **WordPress** | Plugin filter `adflipr_header_clearance_px` / `adflipr_header_selector` | **High** for WooCommerce plugin |
| **WordPress** | WooCommerce Storefront / block themes: `header.site-header`, `#masthead` | **Medium** on default themes |
| **Both** | Merchant advanced: `window.adfliprHeaderClearance = { top: N }` once, set by agency | **High** (fallback override) |

Neither Shopify app embed nor WordPress loader **today** pass header hints — detection is 100% runtime heuristic unless extended.

#### DOM heuristics (runtime scan)

**Include** elements where (at initial layout):

- `getComputedStyle(el).position` ∈ `{ fixed, sticky }`
- Bounding rect: `top <= 0` (or within small epsilon, e.g. 2px)
- `width >= 0.9 * viewportWidth` (full-bleed bar)
- `height` between **24px and 200px** per bar (ignore full-screen overlays)
- `z-index` not `auto` and ≥ theme baseline (e.g. ≥ 100), or stacking context at top
- Not inside `.adflipr-popup-embed-*` or known cookie iframes (class denylist)

**Stacking:** Sort by `top`, sum **non-overlapping** vertical extents for announcement + header (avoid double-counting same box).

**Exclude:**

- Hidden (`display:none`, `visibility:hidden`, `opacity:0`, zero height)
- Mobile-only bars when desktop viewport (optional: use `matchMedia` paired with hide classes)
- Admin bar (WordPress `#wpadminbar`) — **customers don’t see it**; exclude when detecting for storefront, or detect only when `body.admin-bar` and adjust for logged-in preview only

#### Confidence scoring (example)

| Score | Condition |
|-------|-----------|
| **High (≥0.85)** | Platform hint present, or exactly one full-width fixed/sticky candidate matching theme allowlist |
| **Medium (0.5–0.84)** | Multiple candidates but consistent summed height; or single sticky `header` without fixed |
| **Low (<0.5)** | No candidates; >3 competing top-fixed elements; heights change >40% between scroll samples without stable header |

**Action:**

- High → apply computed inset (Below Header)
- Medium → apply inset + optional debug telemetry (`headerDetection: 'heuristic'`)
- Low → **fallback chain** (§4.4)

### 4.2 Shopify-specific analysis

| Theme class | Typical header DOM | Sticky behavior | Detection reliability |
|-------------|-------------------|-----------------|------------------------|
| **Dawn / OS 2.0** | `header` in section group; announcement bar separate section | Often `position: sticky` on header wrapper | **Medium–High** on unmodified Dawn |
| **Premium themes** | Custom classes, mega-menus | Mixed sticky/fixed | **Low–Medium** |
| **Page builders in theme** | Non-standard wrappers | Unpredictable | **Low** |
| **Headless / Hydrogen** | Not using theme app embed | N/A — script may not run in same DOM | **Out of scope** unless custom integration |

**Script load:** App embed in `<head>`, async. Header may not be fully laid out at first paint.

**Mitigation:** Run detection at `DOMContentLoaded`, re-run at `load`, and once after `requestAnimationFrame` × 2; subscribe to ResizeObserver on `document.body` for late-loading announcement bars.

**Shopify sections:** Section reload (Theme Editor) not customer-facing; AJAX cart drawers don’t usually change header height.

**Reliability estimate:** ~**70–85%** of Shopify merchants on Dawn-like themes get correct Below Header without config; **lower** on heavily customized themes.

### 4.3 WordPress / WooCommerce-specific analysis

| Theme class | Typical header | Complications | Detection reliability |
|-------------|---------------|---------------|------------------------|
| **Storefront / Astra / GeneratePress** | `#masthead`, `.site-header` | Sticky plugins add classes | **Medium–High** |
| **Elementor / Divi** | Builder wrappers | Multiple fixed layers | **Low–Medium** |
| **Cookie consent** | Fixed bottom or top bar | Extra top bar candidate | **Medium** (needs denylist + sum) |
| **Caching/minify** | Deferred CSS | FOUC may change position after load | **Medium** (needs re-measure) |

**Plugin advantage:** WordPress can ship **`adflipr_header_selector` filter** in `class-adflipr-popup-embed.php` without theme edits — **recommended Phase 2**.

**Reliability estimate:** ~**60–80%** without plugin hint; **85%+** with optional filter documented for agencies.

### 4.4 Fallback when detection fails (manual override **as exception**)

Per ADR-005, override is **not** the default onboarding path. Fallback order:

1. **Platform hint** (WP filter, Shopify theme setting, JS global)
2. **Runtime heuristic** (§4.1)
3. **Campaign-level advanced override** (hidden under “Advanced” or support-only): `headerClearancePx` / `headerSelector` — single optional field, not “measure your header” wizard
4. **Degrade to Overlay** with debug flag — last resort if mode=Below Header and confidence Low (banner at top:0) **OR** switch to Inline anchor if platform provides default anchor (WP: after `#masthead`)

**When to show merchant UI:** Only after preview/storefront reports `headerDetectionStatus: 'failed'` in builder (post-publish check or preview diagnostic), not at campaign creation.

---

## 5. Mode comparison

### 5.1 UX

| Criterion | Overlay | Below Header | Inline |
|-----------|---------|--------------|--------|
| Header remains usable | Poor (default) | **Good** | **Good** |
| Promo visibility | **High** (always visible) | **High** | Medium (scrolls away unless sticky) |
| Mobile | Overlap worse (taller chrome) | **Good** if detection works | **Good** if anchor correct |
| Merchant mental model | “Banner on top of site” | “Banner under my menu” | “Banner as part of page” |
| WYSIWYG preview | OK | Hard (iframe cross-origin) — needs preview strategy §6 | Hard (anchor-dependent) |

### 5.2 Implementation complexity

| Criterion | Overlay | Below Header | Inline |
|-----------|---------|--------------|--------|
| Embed runtime | **Done** | Medium — `HeaderClearanceService`, inset on host, observers | High — anchor injection, DOM contract, theme hooks |
| Builder UI | **Done** | Low — mode selector + diagnostic readout | Medium — anchor picker / platform defaults |
| Serializer/export | **Done** | +1 enum in metadata | + anchor id / insertion policy |
| Backend API | **Done** | + `bannerPresentation` field | + anchor policy |
| Shopify/WP loaders | **Done** | Medium — optional hints | High — document anchor hooks |
| Preview | Partial | Medium — layout split or simulated clearance | High |

### 5.3 Browser compatibility

| Criterion | Overlay | Below Header | Inline |
|-----------|---------|--------------|--------|
| `position: fixed` | Universal | Universal | N/A |
| `ResizeObserver` | N/A | IE11 out (acceptable for ecommerce 2026) | For dynamic anchors |
| `position: sticky` on banner | N/A | Optional enhancement | Widely supported |
| CSP strict sites | Same as today | Same | Same |

### 5.4 Maintainability

| Criterion | Overlay | Below Header | Inline |
|-----------|---------|--------------|--------|
| Theme drift risk | Low (behavior simple) | **Medium** (heuristics need allowlist updates) | **High** (anchor breaks on theme update) |
| Support burden | Overlap tickets | Mis-detection tickets — mitigated by confidence + override | “Banner in wrong place” |
| Testability | **High** (unit + DOM fixtures) | **Medium** (fixture themes, snapshot heights) | **Medium** (integration per theme) |
| Aligns with ADR-005 | No (overlap default) | **Yes** | **Yes** |

---

## 6. Builder preview strategy (all modes)

Preview today: site iframe (z-0) + presentation overlay (z-1). Cross-origin iframe **cannot** measure real header.

| Mode | Preview approach |
|------|------------------|
| **Overlay** | Current overlay on iframe |
| **Below Header** | **Layout split:** reserve top band height = `max(detected in wireframe, platform default for connected store)` OR run detection on same-origin preview stub; do not rely on iframe DOM |
| **Inline** | Insert banner **above** iframe content area in a synthetic layout, or show wireframe with banner slot after mock header |

Suppress live embed in preview iframe (`adflipr_builder_preview=1`) remains required.

---

## 7. Recommendation

### Default for ecommerce stores: **Below Header**

**Why:**

1. **Matches merchant expectation** — promo visible, navigation intact (fixes PEB-044 without manual px config).
2. **Preserves sticky-bar UX** — banner stays visible while scrolling (unlike pure Inline unless banner is also sticky).
3. **Lower implementation risk than Inline** — no theme-specific anchor contract required for v1; heuristics + WP/Shopify hints cover most WooCommerce/Shopify stock themes.
4. **Overlay remains available** — power users / legacy campaigns; not default for new sticky banner campaigns.
5. **Inline** — secondary mode for “insert in page flow” use cases; document as advanced; consider converging with embed product long-term but keep separate initially.

### Mode defaults by campaign creation

| Scenario | Default mode |
|----------|--------------|
| New sticky banner (Shopify/WP connected) | **Below Header** |
| New sticky banner (manual snippet only) | **Below Header** (heuristic) with diagnostic in builder |
| Merchant explicitly chooses “overlay full top” | Overlay |
| Merchant chooses “part of page content” | Inline |

### What **not** to default

- Manual top offset fields in Theme panel (ADR-005 stands until superseded by this ADR when accepted).
- Inline for all banners — breaks “always visible promo” expectation.

---

## 8. Data model (conceptual)

Extend sticky banner metadata (builder + embed API):

```text
bannerPresentation: 'overlay' | 'below_header' | 'inline'   // default: below_header
formPosition: 'top' | 'bottom'                               // unchanged
headerClearanceOverridePx?: number                           // optional fallback only
headerSelectorOverride?: string                              // optional fallback only
inlineAnchorPolicy?: 'after_header' | 'body_start' | 'custom' // inline only
```

Embed runtime resolves effective top inset:

```text
effectiveTopPx = f(bannerPresentation, formPosition, headerDetectionResult, overrides)
```

Flyout `positionOffset` unchanged (orthogonal).

---

## 9. Migration strategy

### Phase 0 — Decision & KB (now)

- Accept this proposal as ADR-006 (or extend ADR-005).
- Update PEB-044 fix column to “Below Header mode + detection”.

### Phase 1 — Below Header (MVP, ~4–6 weeks)

- Add `bannerPresentation` with default `below_header` for **new** campaigns only.
- Implement `HeaderClearanceService` in embed runtime (heuristics §4.1, no merchant UI).
- WP: add `adflipr_header_clearance_px` / `adflipr_header_selector` filters (optional).
- Builder preview: layout split with wireframe header band + clearance (no iframe measurement).
- Telemetry: `headerDetectionConfidence`, `appliedInsetPx` (debug/RUM only).
- **Existing published campaigns:** remain **Overlay** until merchant re-publishes or one-time migration job sets `bannerPresentation: overlay` explicitly for backward compatibility.

### Phase 2 — Platform hints & fallback override (~2–3 weeks)

- Shopify theme app embed setting: optional header selector (advanced).
- Builder diagnostic: “Header clearance: automatic (72px)” or “Low confidence — contact support”.
- Advanced override fields (support/agency only), not creation wizard.

### Phase 3 — Inline mode (~4+ weeks)

- Anchor policies for WP/Shopify.
- Builder mode selector + preview slot.
- Documentation for theme developers.

### Phase 4 — Deprecation (optional, 6+ months)

- Consider defaulting legacy overlay-only campaigns to Below Header on re-publish prompt.
- Do **not** force migration without preview confirmation.

### Rollback

- Campaign-level `bannerPresentation: overlay` instant rollback.
- Feature flag `embedBannerBelowHeader` per site if detection regresses in production.

---

## 10. Open questions (for product sign-off)

1. **Bottom banner:** Does Below Header apply `bottom` inset for cookie bars / mobile tab bars (PEB-045)? Same detection pipeline mirrored for bottom-fixed elements.
2. **Shrink-on-scroll headers:** Inset follows shrinking header (dynamic) vs locked initial height — product preference?
3. **Z-index vs chat widgets (PEB-043):** Below Header still fixed; may need configurable z-index cap.
4. **Single global default vs per-store learning:** Store last successful inset in localStorage for repeat visits?
5. **Supersede ADR-005 explicitly?** This proposal replaces “automatic only, TBD” with Below Header as the automatic mechanism; manual override relegated to fallback tier.

---

## 11. Summary

| Mode | One line |
|------|----------|
| **Overlay** | Fixed to viewport edge; may cover header; simplest; legacy default. |
| **Below Header** | Fixed but inset by detected/hinted header height; **recommended ecommerce default**. |
| **Inline** | In document flow at anchor; best for flow-first layouts; highest theme coupling. |

**Default for ecommerce:** **Below Header**, with runtime detection + platform hints, manual override only on low confidence — not as primary onboarding (ADR-005 compliant).
