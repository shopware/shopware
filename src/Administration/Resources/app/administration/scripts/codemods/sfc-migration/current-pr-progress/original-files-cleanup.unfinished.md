# Missing: Original Files Not Deleted After Migration

**Status:** Not implemented — `index.js` and `.html.twig` remain on disk after `.vue` is written.

---

## Current behavior

After a successful migration, `run-sfc-migration.ts` writes `<name>.vue` but leaves the original files untouched:

```
src/app/component/sw-button/
├── index.js          ← still here
├── sw-button.html.twig  ← still here
└── sw-button.vue     ← newly written
```

This means:
- The build system will pick up both `index.js` and `sw-button.vue`, potentially registering the component twice
- The old files give a false impression that migration hasn't happened
- Developers have to manually find and delete originals after every run

---

## What needs to be done

### Option A: Auto-delete with confirmation (preferred)

After writing the `.vue` file, prompt the user (or respect a `--delete-originals` flag):

```
Written: sw-button/sw-button.vue
Delete sw-button/index.js and sw-button/sw-button.html.twig? [y/N]
```

Auto-deletion should only happen for files that were *fully or partially migrated*. Files that were `not-migratable` should not have their originals touched.

### Option B: Print explicit cleanup instructions

If auto-deletion is out of scope, the runner summary should list every original file that needs to be removed:

```
Migration complete. To remove original files, run:
  rm src/app/component/sw-button/index.js
  rm src/app/component/sw-button/sw-button.html.twig
  ...
```

This is the minimum acceptable behavior — silent leaving is not acceptable.

---

## Edge cases to handle

- **Partially-migrated components:** The `.vue` file contains the original Options API script, so the originals are still "the source of truth" in a sense. Decide explicitly: delete anyway (`.vue` is the new source) or keep (manual migration still needed).
- **Not-migratable components:** Never delete originals — no `.vue` was written.
- **Components where only `index.js` exists** (no twig, skipped): Do not delete the `index.js`.
- **Re-runs:** If the `.vue` already exists from a previous run and originals were already deleted, the runner should not error.

---

## Relevant file

- `run-sfc-migration.ts` — the only place to add this logic

---

## Acceptance check

- [ ] Original files are removed (or instructions to remove them are printed) after successful migration
- [ ] `not-migratable` components' originals are never touched
- [ ] Partially-migrated behavior is explicitly defined and implemented
- [ ] If auto-deleting: confirmation or `--delete-originals` flag is required
