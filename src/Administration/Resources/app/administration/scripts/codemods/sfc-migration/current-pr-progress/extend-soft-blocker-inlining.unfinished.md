# Missing: `Shopware.Component.extend()` Has No Inlining Attempt

**Status:** Soft blocker drops to Options API backoff with no attempt to inline the parent component's options.

---

## Current behavior

When a component is registered via `Shopware.Component.extend('parent-name', { ... })`, `transform-script.ts` detects the `extends` pattern as a soft blocker and returns the component in Options API backoff mode — the original code is kept as-is, only the template import is stripped.

No attempt is made to:
- Resolve what the parent component is
- Inline the parent's options into the child
- Produce a Composition API output with the merged result

---

## Why this is hard

`Shopware.Component.extend()` calls look like:

```js
Shopware.Component.extend('sw-new-card', 'sw-card', {
    // overrides / additions
});
```

The second argument is a string (the parent component name). To inline the parent:

1. The codemod would need to locate the parent component's `index.js` on disk
2. Parse its options
3. Deep-merge the parent options with the child's overrides (respecting Vue's merge strategy for each option type: data, methods, computed, lifecycle hooks, etc.)
4. Then run the full Composition API transform on the merged result

This is significantly more complex than a single-file transformation and has many edge cases (chained inheritance, circular references, parent components that are themselves partially-migratable).

---

## What needs to be decided

### Option A: Keep the soft blocker, improve documentation

Accept that `extends` components require manual migration and make this very clear:

- Document in `README.md` what manual steps are needed for `extends`-based components
- In the migration summary, list all components that used `extends` with a direct link to the manual migration guide
- Consider printing the parent component name so developers know where to look

### Option B: Implement basic inlining for simple extend cases

If the parent component is in the same migration run (i.e., also an `index.js` in the scanned directory), attempt to:

1. Parse the parent's options
2. Merge with the child's options
3. Run the Composition API transform on the merged result

This is feasible for simple inheritance chains but should fall back to soft blocker if the parent cannot be found or is itself partially/not-migratable.

---

## Relevant files

- `analyze-component.ts` — `detectBlockers` function, `extends` branch
- `transform-script.ts` — `buildOptionsApiBackoff` function
- `run-sfc-migration.ts` — where the runner could pass parent component context

---

## Acceptance check

Regardless of which option is chosen:

- [ ] The chosen approach is documented in `README.md`
- [ ] The migration summary clearly identifies all components that need manual `extends` migration
- [ ] A test exists for the `extends` soft blocker behavior (currently untested — see [untested-conversions.unfinished.md](untested-conversions.unfinished.md))
