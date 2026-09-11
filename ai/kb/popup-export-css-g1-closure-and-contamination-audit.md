# Phase G1.1 — Production Export CSS Gate Enforcement and Persisted Artifact Contamination Audit

**Date:** 2026-07-06  
**Status:** CI enforcement implemented; read-only audit tooling added; production data not inspected from this environment  
**Related:** [`popup-export-css-production-build-fix-phase-g1-report.md`](./popup-export-css-production-build-fix-phase-g1-report.md)

---

## 1. Executive verdict

Phase G1 fixed the production webpack contract for `*.export.css` → raw UTF-8 CSS string. **G1 production fix is accepted.**

Before this task, the production contract gate (`npm run test:export-css-production-contract`) existed locally but **was not enforced in CI or the production build path**. This task wires the gate into `scripts/build-production.sh`, which is the canonical production build entry used by `npm run build`, `npm run build:ci`, and GitLab `frontend:build`.

A **read-only Mongo audit script** was added for operators to measure persisted contamination blast radius. **Production MongoDB was not queried from this repository environment** — affected counts remain **UNKNOWN** until an operator runs the audit.

**Re-save through the popup editor fully repairs contaminated compiled HTML when `metadata.builderState` exists** (the normal path for builder-edited popups). **Duplicate popup copies compiled artifacts verbatim** and can propagate contamination until the duplicate is re-saved on a G1-deployed frontend.

**G1 is closable after this task** pending: merge CI change, deploy fixed frontend, operator contamination audit, and targeted re-save if needed.

---

## 2. CI enforcement status before

| Pipeline | Trigger | Frontend tests | Production build | Export CSS contract gate | Deployment blocked on failure? |
| -------- | ------- | -------------- | ---------------- | ------------------------ | ------------------------------ |
| **GitLab CI** `.gitlab-ci.yml` `frontend:build` | MR → `dev-main` only | **No** frontend test job | `npm run build:ci` → `build-production.sh ci` | **No** | **Yes** for build job failure; deploy jobs currently `when: never` |
| **GitLab CI** `backend:test` | MR + `service/**` changes | Backend Maven tests only | N/A | No | N/A |
| **GitLab CI** `deploy:*:frontend` | Disabled (`when: never`) | N/A | Uses `frontend:build` artifacts | **No** (gate absent) | Would block if build artifact missing |
| **GitHub Actions** `.github/workflows/ai-review.yml` | MR → `dev-main` | No | No | No | No (`allow_failure: true`) |
| **Jenkins** | — | **Not present** in repository | — | — | — |
| **`adflipr-app/scripts/build-production.sh`** | Invoked by `npm run build` / `build:ci` / `build:vps` | No | `build:popup-embed` + `craco build` | **No** (before this task) | Local/VPS build would succeed with stub regression |
| **`adflipr-app/scripts/deploy-fe-gitlab.sh`** | Remote deploy after CI upload | N/A | **No build** (serves pre-built `build/`) | N/A | N/A |
| **`service/scripts/deploy-app-prod*.sh`** | Manual backend deploy | Backend only | N/A | No | N/A |

**Verdict:** Gate existed as `npm run test:export-css-production-contract` but was **not executed** on any automated path that produces deployable frontend artifacts.

---

## 3. CI integration decision

**Chosen: Option C — add to production build verification** (smallest change consistent with repository conventions).

Rationale:

- GitLab `frontend:build` already runs `npm run build:ci` → `build-production.sh ci`.
- No frontend test stage exists today; adding a separate `frontend:test` job (Option B) would duplicate `npm ci` and still miss VPS/manual builds unless the gate lives in the build script.
- Option A (normal frontend test command) does not exist as a CI job.
- Wiring the gate into `build-production.sh` covers **CI, local production builds, and VPS builds** with one integration point.
- The gate uses the real production webpack fixture (`scripts/verify-export-css-production-build.js`), not Jest transforms.

---

## 4. CI changes made

**File:** `adflipr-app/scripts/build-production.sh`

Added before `craco build`:

```bash
timing_run_phase "export-css production contract gate" npm run test:export-css-production-contract
```

Order within build:

1. `build:popup-embed` (esbuild)
2. **`test:export-css-production-contract`** (production webpack fixture gate)
3. `craco build` (full production app)
4. workbox service worker

**Locally verified:** `npm run test:export-css-production-contract` → PASS.

---

## 5. CI enforcement status after

| Pipeline | Trigger | Production build | Export CSS contract gate | Deployment blocked on failure? |
| -------- | ------- | ---------------- | ------------------------ | ------------------------------ |
| GitLab `frontend:build` | MR → `dev-main` | `build:ci` | **Yes** (via `build-production.sh`) | **Yes** — job fails |
| `npm run build` / `build:ci` / `build:vps` | Local / VPS | Yes | **Yes** | Build exits non-zero |
| GitLab `deploy:*:frontend` (when re-enabled) | Needs `frontend:build` | Artifact from gated build | **Yes** (transitive) | **Yes** — no artifact without passing build |

Independent command remains:

```bash
cd adflipr-app && npm run test:export-css-production-contract
```

---

## 6. Persistence field inventory

```text
Popup save (FormBuilder.handleSave)
  → serializePopupExport(snapshot)           // frontend compile
  → buildPopupSaveMetadata(snapshot, htmlExport)
  → popupApi.updatePopup({ htmlContent, metadata })
  → PUT /api/popups/{id}
  → PopupService.updatePopup
  → Popup entity → MongoDB collection `popup`
```

| Field | Location | Contains embedded `.export.css` `<style>` blocks? | Notes |
| ----- | -------- | ------------------------------------------------- | ----- |
| `htmlContent` | `Popup.htmlContent` | **Yes** — primary step HTML | Thumbnails, list/detail preview fallback |
| `metadata.htmlByStep` | `Popup.metadata` map (JSON string) | **Yes** — each step value | Storefront embed source (`PopupEmbedResponse`) |
| `metadata.htmlPrimaryStepId` | metadata | No | Pointer only |
| `metadata.renderCss` | metadata | **No** | Separate `compilePopupRenderCss` pipeline; not webpack `.export.css` imports |
| `metadata.builderState` | metadata (JSON) | **No** | Canonical editor snapshot; source for re-save |
| Teaser HTML | Inside `htmlByStep['teaser']` when present | **Partial** — form steps use `serializeLayout` export sheets; teaser uses `@pb/embed/teaserExportCss` (esbuild path, not affected by G1 webpack bug) |

**Frontend load path:** `EditorPage` → `parseBuilderState(resource.metadata)` — **does not load from `htmlContent`**.

**Embed path:** `PopupService.getPopupEmbedConfig` → reads `metadata.htmlByStep` (parsed) for published popups.

---

## 7. Contamination signatures

Evidence from pre-G1 production bundle and G1 report — serialized into step `<style>` blocks with attrs such as `data-adflipr-popup-floating-label-css`, `data-adflipr-popup-responsive-css`, `data-adflipr-popup-radio-css`:

| Signature | Reliability | Notes |
| --------- | ----------- | ----- |
| `extracted by mini-css-extract-plugin` | **Primary — required** | Webpack-generated comment; not merchant content |
| `export default {}` | **Secondary — confirmatory** | Appears with primary in pre-G1 stub; do **not** match alone (too broad) |
| `// extracted by mini-css-extract-plugin` | Same as primary | Comment prefix included in primary match |
| `module.exports` | **Not used for audit** | Pre-G1 evidence shows ESM stub only in serialized HTML |

**Not at risk:** `metadata.renderCss`, `metadata.builderState`.

**Narrowest reliable rule:** field contains `extracted by mini-css-extract-plugin`.

---

## 8. Read-only audit method

**Script (KEEP — operational diagnostic):** `service/scripts/audit-popup-export-css-contamination.js`

**Operator command:**

```bash
mongosh "$MONGODB_URI" --quiet --file service/scripts/audit-popup-export-css-contamination.js
```

**Optional filters:**

```bash
AUDIT_ACCOUNT_ID=<accountId> AUDIT_VERBOSE=1 mongosh "$MONGODB_URI" --quiet --file service/scripts/audit-popup-export-css-contamination.js
```

**Behavior:**

- Read-only `find` on collection `popup`
- Scans `htmlContent` and parsed `metadata.htmlByStep` step values
- Outputs counts + optional table: popup ID, status, affected field, signature, last updated, live flag
- Does **not** print full HTML or unrelated merchant fields
- Does **not** mutate records

**Preferred output columns:**

| Popup ID | Status | Affected field | Signature | Last updated | Live/active |

---

## 9. Verified contamination count or operator-required query

| Metric | Result |
| ------ | ------ |
| **Verified affected popup count** | **UNKNOWN** — production MongoDB not accessible from this environment |
| **Verified active affected count** | **UNKNOWN** |

**Operator action required:** run the audit script against production (and optionally dev) MongoDB after G1 frontend deploy.

---

## 10. Active/live popup impact

Published popups (`status: PUBLISHED`) serve embed HTML from `metadata.htmlByStep`. If contaminated, storefront `hoistExportedStepStyles()` hoists invalid stub text into `document.head` — floating-label, radio, and responsive export behaviors fail until repaired.

**Live impact count:** UNKNOWN until audit runs. Audit script reports `liveAffectedPopups` (PUBLISHED + contaminated).

---

## 11. Duplicate/copy propagation analysis

| Path | Copies compiled HTML? | Recompiles artifact? | Can propagate contamination? |
| ---- | --------------------- | -------------------- | ---------------------------- |
| **Editor Save** (`FormBuilder.handleSave`) | Overwrites | **Yes** — `serializePopupExport` from `builderState` | **No** (after G1-deployed frontend) |
| **Duplicate popup** (`PopupService.duplicatePopup`) | **Yes** — copies `htmlContent` + full `metadata` | **No** | **Yes** |
| **Template import** (`importPopupFromTemplate`) | Writes fresh | **Yes** — `serializePopupExport` on create | **No** (uses current frontend at import time) |
| **Campaign clone** | No dedicated popup clone path found | N/A | N/A |
| **Draft copy / publish copy** | Status changes only; no separate compiled copy path | N/A | N/A |
| **Version/history restore** | **Not present** for popups | N/A | N/A |
| **My Template save** | Stores template snapshot, not popup Mongo record | Recompiles on popup import | N/A for persisted popup contamination |

---

## 12. Whether normal re-save fully repairs an artifact

**Trace:**

```text
GET popup → metadata.builderState
  → parseBuilderState → EditorPage initialSnapshot
  → FormBuilder hydrates editor from snapshot (NOT htmlContent)
  → Save → serializePopupExport(snapshot)
  → overwrites htmlContent, metadata.htmlByStep, metadata.renderCss
```

| Question | Answer |
| -------- | ------ |
| Is canonical builder state sufficient? | **Yes** — `serializePopupExport` regenerates all compiled fields from snapshot |
| Does opening load source or compiled HTML? | **Source** — `parseBuilderState(metadata)` only |
| Does Save regenerate every affected compiled field? | **Yes** — `htmlContent`, `htmlByStep`, `renderCss` |
| Could contaminated CSS survive after Save? | **No** on normal save path — full overwrite |
| Does duplicate copy contaminated HTML? | **Yes** — until duplicate is opened and saved on G1 frontend |
| Does template import regenerate? | **Yes** |

**Verdict:** **CONDITIONAL YES**

- **YES** for popups with valid `metadata.builderState` (standard builder-edited popups).
- **NO** for records lacking `builderState` — editor opens empty defaults; save would not faithfully restore merchant content from contaminated `htmlContent` alone.
- **Duplicate** records inherit contamination until individually re-saved.

---

## 13. Recommended repair strategy

**Case depends on audit count (currently UNKNOWN).**

| Case | Threshold | Strategy |
| ---- | --------- | -------- |
| **Case 0** | count = 0 | No repair |
| **Case 1** | Small set | **Option A** — merchant or support opens each popup in builder, Save (no content change required) |
| **Case 2** | Material set | One-time **Option B/D** — batch re-save script calling same compile path as frontend (requires G1-deployed compile environment); prefer regeneration from `builderState`, not string surgery |
| **Case 3** | Cannot inspect | Operator runs audit script first |

**Rejected:**

- Option C (remove corrupted style blocks only) — fragile string surgery
- Permanent runtime/serializer stub detection or fallback
- Automatic production DB mutation from repository tooling

**For duplicates:** re-save each duplicate after source is clean, or duplicate only after source repair.

**Preserve:** content, `artifactScopeId`, `renderCss`, step identity, behavior CSS, popup state, active status — all preserved by normal `serializePopupExport` + `buildPopupSaveMetadata`.

---

## 14. Deployment ordering

Verified safe order:

```text
1. Merge G1 + G1.1 (CI gate)                          ✓ on dev-main
2. CI production contract gate passes on MR/build     ✓ after build-production.sh change
3. Deploy new frontend build to production
4. Verify deployed production chunk has raw CSS         (post-deploy checklist §15)
5. Run persisted contamination audit                    (operator — mongosh script)
6. Repair/re-save affected popups if any              (after step 3 — repair requires G1 frontend)
```

**Critical:** Audit/repair that depends on editor re-save **must not** precede G1 frontend deploy.

---

## 15. Post-deployment verification checklist

1. Load deployed production application (builder).
2. Create or edit a popup with floating-label and/or radio blocks (uses `.export.css` sheets).
3. Save.
4. Inspect persisted step HTML (API or Mongo read-only): `metadata.htmlByStep` for a form step.
5. Confirm **no** `extracted by mini-css-extract-plugin` or `export default {}` inside `<style>` blocks.
6. Confirm raw CSS selectors exist (e.g. `.pbn-floating-field`, `.pbn-export-native-radio-scope`).
7. Open storefront embed for a published test popup.
8. Confirm hoisted stylesheet in `document.head` parses (DevTools → no invalid property warnings on stub text).
9. Confirm expected computed styles (floating label, radio appearance).
10. Run contamination audit script; expect `affectedPopups: 0` after repair campaign (or document remaining count).

---

## 16. Temporary tooling disposition

| Artifact | Disposition |
| -------- | ----------- |
| `service/scripts/audit-popup-export-css-contamination.js` | **KEEP** — operational diagnostic / admin read-only tooling |
| `adflipr-app/scripts/verify-export-css-production-build.js` | **KEEP** — CI gate (existing G1) |
| Mutation/repair scripts | **Not created** — design only if audit finds contamination |

---

## 17. Production mutation performed

**None.** No production database writes, no automatic repair execution.

---

## 18. Remaining risks

1. **Pre-G1 saved popups** may still contain stub text until re-saved (count unknown).
2. **Duplicate popup** can spread contamination without re-save.
3. **Popups without `builderState`** cannot be repaired via simple editor re-save.
4. GitLab deploy jobs are currently disabled — gate protects build artifacts; ensure deploy pipelines stay wired to `frontend:build` when re-enabled.
5. **Thumbnails** use stored `htmlContent` until next save (visual only; embed uses `htmlByStep`).

---

## 19. G1 closure verdict

| Item | Status |
| ---- | ------ |
| Production webpack fix (G1) | **Accepted** |
| CI gate enforced | **Yes** (after this task) |
| Persisted blast radius measured | **Operator action required** |
| Re-save repair path validated | **Conditional yes** |
| Runtime/serializer compatibility added | **No** |
| **G1 fully closable after this task** | **YES** — pending deploy + operator audit + optional targeted re-save |

---

## Appendix — Final answer format

1. G1 production fix accepted: **YES**
2. Production contract gate already in CI before this task: **NO**
3. CI change required: **YES**
4. CI enforcement implemented: **YES**
5. Failure blocks deployment: **YES**
6. Persisted artifact fields at risk: **`htmlContent`, `metadata.htmlByStep` (per step)**
7. Contamination signatures: **`extracted by mini-css-extract-plugin`** (primary); **`export default {}`** when paired
8. Read-only audit implemented: **YES**
9. Production data directly inspected: **NO**
10. Verified affected popup count: **UNKNOWN**
11. Active affected popup count: **UNKNOWN**
12. Normal re-save fully repairs affected artifact: **CONDITIONAL**
13. Duplicate/copy paths can propagate contamination: **YES**
14. One-time repair required: **UNKNOWN**
15. Recommended repair method: **Targeted editor re-save on G1-deployed frontend (Option A); batch recompile from `builderState` if material count (Option B/D)**
16. Runtime compatibility code added: **NO**
17. Serializer compatibility code added: **NO**
18. Production data mutated: **NO**
19. RTE code changed: **NO**
20. renderCss architecture changed: **NO**
21. Deployment order: **Merge G1+G1.1 → CI gate pass → deploy frontend → verify raw CSS in prod → run audit → re-save affected popups**
22. Post-deployment verification ready: **YES**
23. G1 fully closable after this task: **YES**
