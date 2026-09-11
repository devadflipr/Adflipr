# Skill: Apply default style filtering (write-time only)

**When to use:** User says "apply the default style filtering again" or "re-apply the setStyleFiltered/addStyleFiltered changes".

**Goal:** Don’t write default CSS values into the model. Use `setStyleFiltered` / `addStyleFiltered` (or `filterOutDefaultStyles`) everywhere we call `setStyle` or `addStyle` in Email Builder hydration and style paths. Do **not** strip defaults at export time in `emailHtmlSizeCleaner`.

**Prerequisites:** These must already exist (if reverted, restore them first):

- `adflipr-app/src/layouts/EmailBuilder/utils/styleDefaultValues.ts` — `STYLE_DEFAULT_VALUES`, `isDefaultStyleValue()`, `filterOutDefaultStyles()`
- `adflipr-app/src/layouts/EmailBuilder/utils/responsiveStylesUtils.ts` — `setStyleFiltered()`, `addStyleFiltered()`, and in `setStyleIfChanged` / `applyMediaStyle` the use of `filterOutDefaultStyles` / `isDefaultStyleValue`

---

## 1. responsiveStylesUtils.ts

- Import: `filterOutDefaultStyles`, `isDefaultStyleValue` from `./styleDefaultValues`.
- In **setStyleIfChanged:** filter `newStyles` with `filterOutDefaultStyles` before merge; return early if filtered object is empty.
- In **applyMediaStyle:** `if (isDefaultStyleValue(property, value)) return;` before registering.
- Add **setStyleFiltered(el, styles):** filter `styles`, then `el.setStyle(filtered)` if non-empty.
- Add **addStyleFiltered(comp, styles):** filter `styles`, then `comp.addStyle(filtered)` if non-empty.

---

## 2. emailHtmlSizeCleaner.ts

- **Do not** add any import from `styleDefaultValues`.
- **Do not** use `isDefaultStyleValue` in `parseStyleToMap` or `compressInlineStyle`.
- Keep: `if (cleaned != null) map.set(prop, cleaned);` in parseStyleToMap.
- Keep: unconditional `map.set('border', '0')`, `map.set('border-radius', v)`, `map.set('padding', v)` after shorthand collapse in compressInlineStyle.

---

## 3. sectionStyle.ts

- Import: `filterOutDefaultStyles` from `./styleDefaultValues`.
- Before every `comp.addStyle(...)`: build style object, then `const filtered = filterOutDefaultStyles(obj); if (Object.keys(filtered).length) comp.addStyle(filtered);` (for border, background, radius, padding).

---

## 4. templateStyle.ts

- Import: `filterOutDefaultStyles` from `./styleDefaultValues`.
- Wrapper background: filter object before `wrapper.addStyle(filtered)`.
- Section max-width: filter object before `section.addStyle?.(filtered)`.

---

## 5. Block style files (customBlocks)

For each file, add `setStyleFiltered` (and where relevant `addStyleFiltered`) to the import from `../../../utils/responsiveStylesUtils`, then replace every direct `setStyle(...)` with `setStyleFiltered(target, ...)` and every `addStyle(...)` with `addStyleFiltered(target, ...)`. Keep the same style object; only the call changes.

| File | Replacements |
|------|--------------|
| **menuBlockStyles.ts** | setStyleFiltered(component, merged), setStyleFiltered(c, ...), setStyleFiltered(anchor, ...), setStyleFiltered(s, ...), setStyleFiltered(sep, ...), setStyleFiltered(innerObj, ...) |
| **cartLinkStyles.ts** | setStyleFiltered(component, ...), setStyleFiltered(anchor, ...), setStyleFiltered(buttonSpan, ...); addStyleFiltered(buttonSpan, childPad) |
| **tableBlockStyles.ts** | setStyleFiltered(component, containerStyles), setStyleFiltered(tableEl, ...), setStyleFiltered(cell, cellStyles) |
| **buttonBlockStyles.ts** | setStyleFiltered(component, containerStyles), setStyleFiltered(anchor, ...), setStyleFiltered(buttonSpan, spanStyles) |
| **imageBlockStyles.ts** | setStyleFiltered(component, ...), setStyleFiltered(parentDiv, ...), setStyleFiltered(imageWrapper, ...), setStyleFiltered(finalImgEl, ...) (all occurrences) |
| **logoBlockStyles.ts** | setStyleFiltered(component, containerStyles), setStyleFiltered(logoImg, ...) |
| **socialBlockStyles.ts** | setStyleFiltered(component, containerStyles), setStyleFiltered(linkComp, ...), setStyleFiltered(imgComp, ...) |
| **footerBlockStyles.ts** | setStyleFiltered(component, styles), setStyleFiltered(footerBlockDiv, ...) |
| **orderSummaryBlockStyles.ts** | setStyleFiltered(el, ...), setStyleFiltered(cell, ...), setStyleFiltered(productImage, ...), setStyleFiltered(column, ...), setStyleFiltered(orderHeader, ...), setStyleFiltered(container, ...), setStyleFiltered(e, ...) |
| **couponBlockStyles.ts** | setStyleFiltered(container, ...), setStyleFiltered(elTyped, ...), setStyleFiltered(header, ...), setStyleFiltered(description, ...), setStyleFiltered(code, ...), setStyleFiltered(button, ...), setStyleFiltered(e, ...) |
| **cartItemsStyles.ts** | setStyleFiltered(component, ...), setStyleFiltered(el, ...), setStyleFiltered(cartItemsWrapper, ...) |
| **productBlockStyles.ts** | setStyleFiltered(cell, ...), setStyleFiltered(element, ...), setStyleFiltered(textComponent, ...), setStyleFiltered(productTable, ...), setStyleFiltered(tbody, ...), setStyleFiltered(tr, ...), setStyleFiltered(img, ...), setStyleFiltered(buttonContainer, ...), setStyleFiltered(link, ...), setStyleFiltered(container, ...), setStyleFiltered(inner, ...), setStyleFiltered(deleteBtn, ...), setStyleFiltered(button, ...), setStyleFiltered(gap, ...), setStyleFiltered(td, ...) |

---

## 6. paddingUtils.ts

- Import: `addStyleFiltered` from `./responsiveStylesUtils`.
- Replace every `X.addStyle?.(...)` or `X.addStyle(...)` with `addStyleFiltered(X, ...)` for: table, target, secondChild, menuItem, separator, cartItemsSpan, customerAddressSpan, orderSummarySpan, couponContainer, couponCode, couponButton, couponCodeWrapper, couponButtonWrapper, tableComponent, productComponent, button, cell.

---

## 7. borderRadiusUtils.ts

- Import: `addStyleFiltered` from `./responsiveStylesUtils`.
- Replace: target.addStyle → addStyleFiltered(target, ...), buttonElement.addStyle → addStyleFiltered(buttonElement, ...), image.addStyle → addStyleFiltered(image, ...), table.addStyle → addStyleFiltered(table, ...), targetElement.addStyle → addStyleFiltered(targetElement, ...).

---

## 8. columnSectionCore.ts

- Import: `addStyleFiltered` from `./responsiveStylesUtils`.
- Replace: (gap as GapLike).addStyle({...}) → addStyleFiltered(gap as GapLike, {...}), g.addStyle({...}) → addStyleFiltered(g, {...}), gc.addStyle({...}) → addStyleFiltered(gc, {...}), column.addStyle({...}) → addStyleFiltered(column, {...}), (containerTd as ColumnLike).addStyle!({...}) → addStyleFiltered(containerTd as ColumnLike, {...}).

---

## 9. watermarkUtils.ts

- Import: `addStyleFiltered` from `./responsiveStylesUtils`.
- Replace: compWithStyle.addStyle({...}) → addStyleFiltered(compWithStyle, {...}).

---

## Optional (not done in original pass)

These can be updated the same way if desired: productUtils.ts, menuLinkUtils.ts, traitManagersUtils.ts, socialTraitUtils.ts, customerAddressBlock.ts, countdownTimerBlock.ts, and trait components (registerProductTrait.tsx, registerGeneralTrait.tsx, registerBorderTrait.tsx, registerColumnTrait.tsx, registerTemplateTrait.tsx, registerSectionTrait.tsx, registerOrderTrait.tsx).

---

## Rule

**Wherever we use `setStyle(styles)` → use `setStyleFiltered(el, styles)`.**  
**Wherever we use `addStyle(styles)` → use `addStyleFiltered(comp, styles)`.**  
Do not strip defaults at export in emailHtmlSizeCleaner; only at write time.
