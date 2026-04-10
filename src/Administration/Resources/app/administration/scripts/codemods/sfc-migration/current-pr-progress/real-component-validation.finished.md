# Missing: No Validation Against Real Administration Components

**Status:** All tests use synthetic fixture files. The codemod has never been run against real components in the repo.

---

## Why this matters

The test fixtures were written specifically to exercise known code patterns. Real Administration components will contain:

- Unusual Options API combinations not represented in any fixture
- Edge cases in `this` rewriting (chained property access, destructuring, method calls on results)
- Twig templates with unusual formatting or uncommon syntax
- Module-level code patterns not anticipated by the codemod
- Components where the normalization or extraction logic produces incorrect output

Until the codemod is run against real components, there is no evidence it handles the actual codebase without errors or producing broken output.

---

## What needs to be done

### 1. Run the codemod on a sample set of real components

Pick a representative sample of Administration components covering:

- Simple components (data + methods only)
- Components with Twig blocks and `{{ parent() }}`
- Components with props, emits, and computed
- Components with lifecycle hooks
- Components with `$router`, `$route`, `$t`
- Components with `$refs`
- Components with `mixins` (should become partially-migrated)
- Components with `render()` (should be skipped)

Suggested sample size: 10–20 components from different areas of the admin.

### 2. Review the output manually

For each generated `.vue` file:

- Does it compile without TypeScript/ESLint errors?
- Does it match the expected migration status?
- Are there any `this.` references left in the output (a sign the rewriter missed something)?
- Are there any unexpected `TODO` comments?
- Is the `public:` return key complete (all state exposed)?

### 3. Run the migrated components in the browser

At minimum, render 2–3 fully-migrated components in a dev build and verify they display correctly with no Vue runtime warnings.

### 4. Document the results

Create a brief report listing:

- Which real components were tested
- Which passed without issues
- Which required manual fixes after migration (and what the fix was)
- Any new edge cases discovered that need to be handled by the codemod

This report can live in this directory as `real-component-validation-results.md` once done.

---

## How to find good test candidates

```bash
# Find simple components (no mixins, no render, no extends)
grep -rL "mixins:" src/Administration/Resources/app/administration/app/component/ | \
grep "index.js" | head -20
```

Also look at:
- `src/Administration/Resources/app/administration/app/component/base/` — basic UI components, good for simple cases
- `src/Administration/Resources/app/administration/app/component/form/` — form components, likely have props/emits/computed
- Any component with a `.html.twig` file that has several `{% block %}` tags

---

## Acceptance check

- [x] Codemod run on at least 10 real Administration components — 89 components processed (all of `base/` + `form/`)
- [x] Output reviewed for correctness (no leftover `this.`, complete `public:` key, no broken output) — 7 components inspected in detail; 2 bugs found and fixed
- [ ] At least 2 fully-migrated components render correctly in a dev browser — browser validation deferred (requires `<sw-block>` infrastructure)
- [x] New edge cases discovered during validation are either fixed or documented — `this.$attrs` and `method: debounce(...)` fixed; naming-collision issue documented
- [x] Results documented before PR is promoted from draft — see [real-component-validation-results.md](real-component-validation-results.md)

**Total: 198 tests, all passing.**
