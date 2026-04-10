# Fixed: `normaliseJsContent` AST-Based Rewrite

**Status:** ✅ Done — fragile `lastIndexOf('};')` replaced with ts-morph AST parsing.

---

## What was wrong

`normaliseJsContent` found the closing `};` of the `export default { … }` block by
calling `lastIndexOf('};')` on the already-replaced string. This breaks when any code
with a `};` pattern appears **after** the export default declaration in the file
(e.g. a module-level constant appended after the options object), because
`lastIndexOf` would find the wrong position.

---

## What was done

Rewrote `normaliseJsContent` in `run-sfc-migration.ts` to use `ts-morph`:

1. Parse the file with `Project` (in-memory, `allowJs: true`)
2. Locate the `ExportAssignment` node via `getExportAssignment`
3. Slice out the exact text range `[getStart(), getEnd()]` and replace it with
   `Shopware.Component.register('name', <objectLiteral>);`
4. Preserve everything before and after the export default verbatim

This is immune to any `};` patterns elsewhere in the file because it operates on the
AST node positions, not raw text.

---

## Tests added (`run-sfc-migration.spec.ts`)

Two new test cases in the `normaliseJsContent` describe block:

- **`does not match a module-level };  that appears before the export default`** —
  verifies that a `const CONFIG = { … };` before the export default is left untouched.
- **`does not corrupt trailing module-level code that contains }; after the export default`** —
  demonstrates the exact bug: a `const TRAILING = { … };` after the export default was
  previously mangled; now it is preserved correctly.

**Test count: 159 → 161 (all passing)**

---

## Acceptance check

- [x] `normaliseJsContent` correctly handles components with module-level `};` patterns
- [x] Test covers `export default {}` normalization with a nested object (existing test)
- [x] New tests cover module-level const before AND after export default
- [x] Fix uses AST-based parsing (ts-morph)
