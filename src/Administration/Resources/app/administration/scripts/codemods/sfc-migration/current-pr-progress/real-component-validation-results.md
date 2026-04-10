# Real Component Validation Results

**Date:** 2026-04-10
**Branch:** `14681-admin-create-codemod-to-migrate-components-to-sfcs`

---

## Scope

The codemod was run (in dry-run and write mode) against all components in:

- `src/app/component/base/` — 33 components with twig files, 1 skipped (no twig)
- `src/app/component/form/` — 38 fully migrated, 18 partially migrated (mixins), 3 skipped (no twig)

**Total across both directories: 89 components processed, 0 crashes, 0 hard-blocked.**

---

## Outcome summary

| Category | base/ | form/ | Total |
|---|---|---|---|
| Fully migrated | 33 | 38 | 71 |
| Partially migrated (mixins) | 0 | 18 | 18 |
| Not migratable (render) | 0 | 0 | 0 |
| Skipped (no twig) | 1 | 3 | 4 |
| Components with $el | 2 | 7 | 9 |
| Components with extends | 0 | 0 | 0 |

---

## Components inspected in detail

| Component | Status | Findings |
|---|---|---|
| `sw-button` | ✓ fully migrated | Correct: `router.push()` from `$router`, `onClick` in public |
| `sw-empty-state` | ✓ fully migrated | Correct: props-only component, no state |
| `sw-avatar` | ✓ fully migrated | Correct: `swAvatar = ref(null)` from `$refs.swAvatar`, computed, watcher, lifecycle; naming collision in `generateAvatarInitialsSize` (local `const avatarSize` shadows computed) — valid JS, minor readability issue |
| `sw-confirm-field` | ✓ fully migrated | Correct: emits, watcher, `onBeforeUnmount`, all `this.` refs rewritten; closures correctly reference later-declared `const` sibling functions |
| `sw-snippet-field` | ✓ fully migrated | Correct: inject, async methods, computed |
| `sw-modal` | ✓ fully migrated (with ⚠ $el) | `$el` correctly flagged with TODO comment; `getCurrentInstance()?.proxy?.$el` workaround generated |
| `sw-price-field` | ✓ fully migrated (after fixes) | See "Bugs found" below |

---

## Bugs found and fixed

### 1. `this.$attrs` not rewritten

**Pattern:** `Object.keys(this.$attrs).forEach(...)` in method bodies.

**Scope:** 23 component `index.js` files use `this.$attrs`.

**Fix applied:**
- Added `this.$attrs` → `attrs` replacement in `rewriteThisInBody`
- Added `needsAttrs` to `UsedComposables` interface and `detectUsedComposables`
- Added `useAttrs` to Vue imports when needed
- Added `const attrs = useAttrs()` declaration in `buildCompositionApiScript`

**Tests added:** 4 assertions in `transform-script.spec.ts` (composables-component suite), composables fixture updated with `getAttrsClass()` using `this.$attrs`.

### 2. `method: debounce(...)` pattern not converted

**Pattern:** Methods defined as property assignments with a `debounce()` call value:
```js
searchDebounce: debounce(function onSearch() {
    this.doSearch();
}, 300)
```

`extractMethodProps` only handled `MethodDeclaration` AST nodes and silently dropped `PropertyAssignment` nodes, leaving `this.methodName` unrewritten in other methods that called the debounced version.

**Scope:** 13 component `index.js` files use this pattern.

**Fix applied:**
- `extractMethodProps` now also processes `PropertyAssignment` nodes: captures the full initializer text as `rawText`
- Code generator emits `const name = rawText;` (after `this.` rewriting) for raw-text methods instead of the arrow-function template
- `this.` references inside the debounce callback body are rewritten via `rewriteThisInBody`

**Tests added:** 7 assertions + 1 snapshot in a new `debounce-component` suite in `transform-script.spec.ts`. New fixtures: `debounce-component.index.js` and `debounce-component.html.twig`.

---

## Known remaining issues (not fixed in this pass)

### Naming collision in `generateAvatarInitialsSize`

`sw-avatar` declares a computed `avatarSize` and a local variable `const avatarSize = swAvatar.value.offsetHeight;` inside a method. The local variable shadows the computed. This is valid JS but may produce a linter warning. It is an inherent consequence of preserving the original variable name from the Options API — no clean automatic fix is possible.

**Recommended manual fix:** Rename the local variable (e.g., `const avatarSizePx = ...`).

---

## Test count after fixes

| Spec file | Tests |
|---|---|
| `analyze-component.spec.ts` | ✅ Full coverage |
| `transform-template.spec.ts` | ✅ Full coverage |
| `generate-sfc.spec.ts` | ✅ Full coverage |
| `transform-script.spec.ts` | ✅ Full coverage — 11 fixtures, 2 new suites |
| `run-sfc-migration.spec.ts` | ✅ Full coverage |

**Total: 198 tests, all passing**
