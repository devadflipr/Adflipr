# Skill: JS → TypeScript Migration

**Goal:** Convert JavaScript files to TypeScript while preserving behavior and improving type safety.

Use this playbook when migrating `.js` / `.jsx` files to TypeScript.

---

## Rules

1. **Rename by content**
   - `.js` → `.ts` when the file has no JSX.
   - `.js` / `.jsx` → `.tsx` when the file contains JSX.

2. **Add explicit types for**
   - Function parameters
   - Return values
   - Object structures (props, state, API payloads, config objects)

3. **Avoid `any`**
   - Prefer `unknown` for truly unknown data, then narrow.
   - Use concrete interfaces or type aliases for known shapes.

4. **Prefer `interface` for object structures**
   - Use for component props, state, API responses, and options objects.
   - Export interfaces that are part of the module’s public API.

5. **Reuse existing project types**
   - Check `src/types/`, existing `.ts`/`.tsx` modules, and Redux/store typings before defining new ones.

6. **Keep imports valid**
   - After changing the extension, ensure all imports of the file still resolve.
   - Update any direct references to the old filename if necessary.

---

## Migration Steps

1. **Analyze the JS file**
   - List exported names (default and named).
   - Note all function signatures, state shapes, and external APIs (e.g. Redux, react-hook-form, REST).

2. **Identify data structures**
   - Props, hook return values, state objects, API request/response bodies, event payloads.
   - Define an `interface` (or type) for each; put shared ones in a types file or at the top of the module.

3. **Add types to functions**
   - Type every parameter and return value.
   - Use the defined interfaces for object parameters and return types.

4. **Replace implicit `any`**
   - Fix any `any` introduced by untyped callbacks, `useSelector`, or third-party APIs with explicit types or minimal generics.

5. **Convert default exports to typed exports if needed**
   - Keep `export default Component` for React components; type the component as appropriate.
   - Type named exports (functions, constants) explicitly.

6. **Ensure compatibility with existing TS config**
   - Respect `strict`, `noImplicitAny`, and `exactOptionalPropertyTypes` if enabled.
   - Resolve any new errors reported by the TypeScript compiler and the project linter.

---

## Verification Checklist

- [ ] No TypeScript errors (`tsc` or build succeeds).
- [ ] No implicit `any` (strict typing for public APIs and new code).
- [ ] Public APIs (exports, props, callback params) have clear types.
- [ ] Existing tests still pass.
- [ ] Imports and file references updated so the old `.js`/`.jsx` file can be removed and the new `.ts`/`.tsx` is the single source.

---

## Optional: Remove Old File

After the new `.ts`/`.tsx` file is in place and all references use the new module, delete the original `.js` or `.jsx` file to avoid duplicates.

---

## Email Builder–specific

When migrating files under **Email Builder** (e.g. `adflipr-app/src/layouts/EmailBuilder/**`):

- Use project conventions: `getEditorRuntime()` / `getOrCreateEditorRuntime(editor)` from `utils/editorRuntime.ts`; no `editor.__*`.
- Use GrapesJS types (`Editor`, `Component`) from `grapesjs` where applicable.
- Follow `.cursor/rules/email-builder-typescript-architecture-kb.mdc` and, for blocks/traits, `.cursor/rules/block-js-ts-migration-kb.mdc`.
- After migrating, verify with **`ai/verification/architecture-checklist.md`** (no new guardrail violations).

**References**

- `ai/kb/guardrails.md`
- `ai/kb/architecture.md`
- `.cursor/rules/block-js-ts-migration-kb.mdc` (blocks/traits)
