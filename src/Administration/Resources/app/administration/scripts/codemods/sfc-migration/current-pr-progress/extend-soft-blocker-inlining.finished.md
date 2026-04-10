# Done: `Shopware.Component.extend()` — Option A Implemented

**Status:** ✅ Completed — soft blocker documented; runner now surfaces parent name and actionable guidance.

---

## What was done

Chose **Option A**: keep the soft blocker, improve documentation and developer guidance.

### 1. Parent component name surfaced in the blocker string

Both `detectBlockers` functions (`analyze-component.ts` and `transform-script.ts`) now extract the parent
component name from the second argument of `Shopware.Component.extend()` and embed it in the blocker string:

```
extends (parent: sw-button)
```

Previously the blocker was just `'extends'`. Now developers can see exactly which parent component needs
to be inlined, directly in the migration report line:

```
~  partially-migrated  [extends (parent: sw-button)]  sw-extended-button.vue
```

### 2. Runner prints an actionable `⚠` warning line

For every partially-migrated component whose blocker starts with `extends`, the runner now appends:

```
   ⚠  manually inline parent options from 'sw-button' before re-running codemod; see README.md
```

This is consistent with the `$el` warning pattern already in place.

### 3. `extendsComponents` stat added to `RunStats`

`RunStats` now includes `extendsComponents: number`, incremented for every partially-migrated component
that has an `extends` blocker. The CLI summary prints:

```
Components (extends): 1
```

### 4. `README.md` extended with a manual migration guide

A new section **"Manual migration: `extends`-based components"** explains:
- Why automatic inlining is out of scope
- Step-by-step instructions (find parent, merge options, swap `.extend()` → `.register()`, re-run)
- A before/after code example

### 5. Tests added / updated

- `transform-script.spec.ts`: updated `'lists extends as a blocker'` to assert
  `result.blockers.toContain('extends (parent: sw-button)')`.
- `run-sfc-migration.spec.ts`: new describe block `runMigration — partially-migrated (extends)` with
  6 tests covering: `partiallyMigrated` count, `extendsComponents` stat, parent name in report line,
  `⚠` warning line content, ordering of warning after main line, and that a mixins-only component does
  not increment `extendsComponents`.

**Total: 185 tests, all passing.**

---

## Files changed

| File | Change |
|------|--------|
| `analyze-component.ts` | `detectBlockers` extracts parent name into blocker string |
| `transform-script.ts` | `detectBlockers` extracts parent name into blocker string |
| `run-sfc-migration.ts` | `extendsComponents` stat; `⚠` warning line; summary line |
| `README.md` | New "Manual migration: extends-based components" section |
| `transform-script.spec.ts` | Updated blocker assertion to match new string format |
| `run-sfc-migration.spec.ts` | 6 new tests for extends runner behavior |

---

## Acceptance check

- [x] The chosen approach (Option A) is documented in `README.md`
- [x] The migration summary clearly identifies all components that need manual `extends` migration
  (parent name in report line + `⚠` warning + `extendsComponents` counter)
- [x] A test exists for the `extends` soft blocker behavior (6 new tests in `run-sfc-migration.spec.ts`)
