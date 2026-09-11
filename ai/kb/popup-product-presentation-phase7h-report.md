# Phase 7H — Product Merchant Presentation Migration Report

**Date:** 2026-07-06  
**Verdict:** **ACCEPT**

---

## 1. Executive verdict

Product merchant-configurable presentation migrated from serializer inline CSS + `buildBlockTraitResponsiveCss` + embedded product responsive helpers to canonical `productMerchantPresentation.ts` resolvers + artifact-scoped `productPresentation.ts` renderCss + structural `product.export.css`.

Spinner (Phase 7G) remains migrated. All known block-family merchant presentation is now in renderCss compilers.

---

## 2. Starting baseline

| Check | Result |
|---|---|
| Product compiler | **ABSENT** (pre-7H) |
| Product serializer merchant inline | **PRESENT** (pre-7H) |
| Product generic responsive | **PRESENT** (pre-7H) |
| Timer/Spinner/Social/Feedback/Image/Video compilers | **ACTIVE** |
| `buildBlockTraitResponsiveCss` consumers (pre) | **product only** |

**Phase 7H starting baseline valid: YES**

---

## 3. Product compiler topology

**Selected: A** — one `productPresentation.ts` with multiple owner-specific resolvers in `productMerchantPresentation.ts`.

**Reason:** Compound DOM with stable block/shell/card/descendant owners; layout modes differ by selector scope, not separate compilers.

---

## 4. DOM ownership

```text
[data-block-type=product][data-adflipr-block-id]     → wrapper (margin/width/position → renderCss)
  [data-adflipr-product-shell].pbn-product-shell     → grid shell (padding/shadow/grid → renderCss)
    [data-adflipr-product-card].pbn-product-card     → card chrome + layout (renderCss)
      [data-adflipr-product-image]                   → image (renderCss)
      [data-adflipr-product-body]                    → horizontal body flex (renderCss)
      [data-adflipr-product-name]                    → title typography (renderCss)
      [data-adflipr-product-price].pbn-product-price-row → price row (renderCss)
        .pbn-product-sale-price / .pbn-product-regular-price / .pbn-product-price-on-sale-regular
      [data-adflipr-product-button].pbn-product-button → button (renderCss)
```

**No description or badge nodes** in current production Product block.

---

## 5. Runtime / data lifecycle

Product content is **fully serialized** at export. No Product embed runtime. Runtime compatible with renderCss: **YES**.

Presentation identity excludes `selectedProducts` data (name, price, image URL, salePrice).

---

## 6. Implementation

| Artifact | Path |
|---|---|
| Canonical resolvers | `productMerchantPresentation.ts` |
| renderCss compiler | `productPresentation.ts` |
| Structural sheet | `product.export.css` (horizontal mobile stack) |
| Structure-only serializer | `blocks/product/serialize.ts` |
| Canvas delegation | `blocks/product/ProductCanvas.tsx` |

---

## 7. Mobile strategy

**MIXED BY OWNER**

| Owner | Strategy |
|---|---|
| wrapper/shell/card/image/button | DIFF-ONLY |
| horizontal row stack on mobile | structural `product.export.css` |
| multi-column grid | shell mobile DIFF (1 column) in renderCss |

---

## 8. Verification

| Gate | Result |
|---|---|
| Phase 0 | **25/25 PASS** |
| G1 | **PASS** (`product.export.css`) |
| Phase 7H tests | **8/8 PASS** |
| Product serializer tests | **PASS** |
| responsiveDeviceCss tests | **12/12 PASS** |

---

## 9. Final inventory

| Family | Inline merchant | Generic responsive | Compiler | Status |
|---|---|---|---|---|
| product | **NONE** | **NONE** | **YES** | **MIGRATED** |
| spinner | NONE | NONE | YES | MIGRATED |

**Remaining merchant-CSS debt: NONE**

**Partial migrations: NONE**

**buildBlockTraitResponsiveCss merchant block consumers: NONE** (retained for device visibility + phone select width)

---

## 10. Architecture closure

**All known block-family merchant presentation migrated: YES**
