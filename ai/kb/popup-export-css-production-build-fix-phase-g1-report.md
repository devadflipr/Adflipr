# Popup `.export.css` Production Build Fix — Phase G1 Report

**Date:** 2026-07-06  
**Status:** Implemented and verified  
**Related:** [`popup-production-css-rte-ownership-audit.md`](./popup-production-css-rte-ownership-audit.md), [`popup-global-trait-presentation-architecture-audit.md`](./popup-global-trait-presentation-architecture-audit.md), [`popup-embed-preview-dom-investigation-summary.md`](./popup-embed-preview-dom-investigation-summary.md)

---

## 1. Executive summary

Production Save was embedding **webpack mini-css-extract JavaScript stubs** inside popup step `<style>` blocks instead of raw CSS from `*.export.css` files. Root cause: the prior craco fix unshifted `type: 'asset/source'` at the **top level** of `module.rules`, but CRA’s catch-all `{ oneOf: [...] }` rule still matched `.export.css` via `/\.css$/` + mini-css-extract.

**Fix:** Register `asset/source` as **`oneOf[0]`** and **exclude** `/\.export\.css$/i` from the generic CSS rule. Removed runtime `normalizeSheetCss()` unwrapping (forward-only contract). Added a **production webpack fixture gate** (`scripts/verify-export-css-production-build.js`) that compiles real imports through `createWebpackProdConfig` — not Jest transforms.

**Embed build (`adflipr-popup-embed.js`) was not involved** — esbuild entry does not import serializer or `.export.css`.

---

## 2. Exact root cause

CRA webpack structure:

```text
module.rules[0]  enforce:pre  source-map-loader  (matches *.css)
module.rules[1]  oneOf        (no test → matches ALL modules)
  oneOf[n]       test: /\.css$/  → mini-css-extract + css-loader + postcss
```

Prior craco fix:

```javascript
module.rules.unshift({ test: /\.export\.css$/i, type: 'asset/source' });
```

Webpack still applied the **oneOf** `.css$` pipeline to `.export.css` imports. Production bundle constant:

```javascript
g("// extracted by mini-css-extract-plugin\nexport default {};").trim()
```

That string was serialized into `<style data-adflipr-popup-floating-label-css>` on Save and hoisted unchanged by `hoistExportedStepStyles()`.

---

## 3. Build pipeline before

| Stage | Behavior |
| ----- | -------- |
| Dev `craco start` | Top-level `asset/source` appeared to work in some paths |
| Jest | `exportCssTransform.js` reads file as UTF-8 string — **always passed** |
| Production `craco build` | `.export.css` → mini-css-extract stub in JS chunk |
| Embed esbuild | Does not import `.export.css` |
| Save | Browser uses production chunk constants → invalid `<style>` text |
| Storefront | `hoistExportedStepStyles()` copies invalid text to `document.head` |

---

## 4. Build pipeline after

| Stage | Behavior |
| ----- | -------- |
| Dev / production craco | `oneOf[0]`: `/\.export\.css$/i` → `type: 'asset/source'` |
| Generic CSS rule | `exclude` includes `/\.export\.css$/i` |
| Jest | Unchanged — `exportCssTransform.js` (same string contract) |
| Embed esbuild | Unchanged — no `.export.css` imports |
| Save | Constants contain raw CSS selectors/declarations |
| Storefront | Hoisted `<style>` contains parseable CSS |

---

## 5. Canonical `.export.css` contract

```text
*.export.css
  → imported default export is a UTF-8 CSS source string
  → identical semantics in: development, Jest, production app build
  → NOT processed by mini-css-extract / css-loader
  → NOT unwrapped or sanitized at serialization time
```

**Option A (`asset/source` inside CRA `oneOf`)** — selected. Smallest change; one build system owns serializer imports.

---

## 6. Files affected

| File | Change |
| ---- | ------ |
| `adflipr-app/craco.config.js` | `applyExportCssRawSourceContract()` — oneOf unshift + css exclude |
| `styles/sheets/index.ts` | Removed `normalizeSheetCss()` — direct `.trim()` on imports |
| `scripts/export-css-production-fixture-entry.js` | Minimal webpack entry importing all 4 sheets |
| `scripts/verify-export-css-production-build.js` | Production config gate |
| `utils/__tests__/exportCssProductionBuildContract.test.ts` | Jest wrapper invoking verify script |
| `package.json` | `test:export-css-production-contract` script |

---

## 7. Build configuration change

```javascript
function applyExportCssRawSourceContract(webpackConfig) {
  const oneOfRule = webpackConfig.module.rules.find((rule) => Array.isArray(rule.oneOf));
  oneOfRule.oneOf.unshift({ test: /\.export\.css$/i, type: 'asset/source' });
  // Exclude *.export.css from CRA cssRegex mini-css-extract rule
  for (const rule of oneOfRule.oneOf) {
    if (rule.test && String(rule.test) === '/\\.css$/') {
      rule.exclude = /* append /\.export\.css$/i */;
    }
  }
}
```

Removed ineffective top-level `module.rules.unshift` for `.export.css`.

---

## 8. Actual production bundle evidence

### Before fix (chunk `32.*.js`)

```text
g("// extracted by mini-css-extract-plugin\nexport default {};").trim()
translate(0, 18px) → NOT FOUND
mini-css-extract-plugin → FOUND
```

### After fix

**Fixture bundle** (`scripts/verify-export-css-production-build.js`):

```text
pbn-floating-field → FOUND
translate(0, 18px) → FOUND
pbn-export-native-radio-scope → FOUND
mini-css-extract-plugin → NOT FOUND
export default {} → NOT FOUND
```

**Full production build** (`npx craco build`):

```text
stub chunks: 0
raw CSS chunks (translate(0, 18px)): 1 (725.*.chunk.js)
```

---

## 9. Serializer evidence

`serializePopupExport` + `popupProductionCssRteOwnership.audit.test.ts`:

- Step HTML contains `.pbn-floating-field`
- No `mini-css-extract-plugin` or `export default {}` in serialized `<style>`

Constants sourced from Jest path match production string shape after fix (both are raw CSS text).

---

## 10. Runtime hoist evidence

| Test | Evidence |
| ---- | -------- |
| `mountExportedStepStyles.test.ts` | Hoists `<style>` to `document.head`; teardown removes nodes |
| `radioExport.test.ts` | Serialized radio CSS hoisted; `appearance: none` applied under hostile theme CSS |

Hoisted `textContent` contains CSS rules (e.g. `.pbn-export-native-radio-scope`), not JS stubs.

---

## 11. Computed-style evidence

| Test | Evidence |
| ---- | -------- |
| `radioExport.test.ts` | After hoist, native radio inputs get custom appearance under hostile `!important` theme rules |
| `popupProductionCssRteOwnership.audit.browser.test.ts` | Chromium computed styles for renderCss / RTE scenarios (unchanged by G1) |

G1 restores **parseable** hoisted rules so browser cascade can apply floating-label, radio, and responsive utilities.

---

## 12. Regression test design

**Not Jest-only.** `exportCssProductionBuildContract.test.ts` spawns `node scripts/verify-export-css-production-build.js`, which:

1. Calls `createWebpackProdConfig(craco.config.js)` (same as production build)
2. Compiles `scripts/export-css-production-fixture-entry.js` importing all 4 `.export.css` files
3. Asserts bundle contains CSS markers and forbids mini-css-extract stub text

Run manually: `npm run test:export-css-production-contract`

---

## 13. Obsolete build code removed

| Removed | Reason |
| ------- | ------ |
| Top-level `module.rules.unshift({ type: 'asset/source' })` | Ineffective against CRA oneOf |
| `normalizeSheetCss()` in `sheets/index.ts` | Forward-only — no runtime stub unwrapping |

---

## 14. Remaining `.export.css` inventory

| File | Constant | Role | Presentation? |
| ---- | -------- | ---- | ------------- |
| `floating-label.export.css` | `POPUP_FLOATING_LABEL_EXPORT_CSS` | Floating label structure/behavior | No — structure only |
| `embed-responsive.export.css` | `POPUP_EMBED_RESPONSIVE_EXPORT_CSS` | Overflow guards, error shell, export scoping | No — runtime/behavior |
| `popup-entrance.export.css` | `POPUP_ENTRANCE_EXPORT_CSS` | Entrance keyframes | No — animation behavior |
| `radio.export.css` | `POPUP_RADIO_EXPORT_CSS` | Native radio appearance | No — control mechanics |

Merchant presentation remains in artifact **`renderCss`**. These sheets may shrink as block families migrate, but remain necessary for behavior/structure until replaced.

---

## 15. Interaction with `renderCss`

**Unchanged.** G1 fixes embedded step `<style>` string constants only. `compilePopupRenderCss`, `mountRenderArtifactCss`, and artifact metadata pipeline untouched.

---

## 16. Production-save trust verdict

**YES after deploy** — new production app builds serialize valid CSS into step `<style>` blocks. Popups saved **before** this fix may still contain stub text in MongoDB; merchants should re-save affected popups after deployment.

---

## 17. Test results

| Suite | Result |
| ----- | ------ |
| `exportCssProductionBuildContract.test.ts` | PASS |
| `exportCssSheets.test.ts` | PASS |
| `popupProductionCssRteOwnership.audit.test.ts` | PASS |
| `popupProductionCssRteOwnership.audit.browser.test.ts` | PASS |
| `mountExportedStepStyles.test.ts` | PASS |
| `radioExport.test.ts` | PASS |
| `npx craco build` (post-fix) | PASS — 0 stub chunks |
| `npm run test:export-css-production-contract` | PASS |

---

## 18. Remaining risks

1. **Pre-fix saved popups** — contain stub text until re-saved.
2. **CI integration** — add `test:export-css-production-contract` to CI pipeline if not already wired.
3. **Future CRA/webpack upgrades** — oneOf ordering must be preserved; regression test guards this.

---

## Stage 1 trace table

| File | Importer | Expected | Dev | Jest | Prod app (before) | Prod app (after) | Embed |
| ---- | -------- | -------- | --- | ---- | ----------------- | ---------------- | ----- |
| `floating-label.export.css` | `sheets/index.ts` | Raw CSS string | string | string | **stub** | string | N/A |
| `embed-responsive.export.css` | `sheets/index.ts` | Raw CSS string | string | string | **stub** | string | N/A |
| `popup-entrance.export.css` | `sheets/index.ts` | Raw CSS string | string | string | **stub** | string | N/A |
| `radio.export.css` | `sheets/index.ts` | Raw CSS string | string | string | **stub** | string | N/A |
