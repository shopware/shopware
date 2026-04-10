# Original Files Cleanup After Migration ✅

**Status:** Done — `--delete-originals` flag implemented in `run-sfc-migration.ts`.

---

## What was implemented

A `--delete-originals` flag was added to the runner. When passed alongside `--write`, the runner deletes the source `index.js` and `.html.twig` immediately after writing each `.vue` file.

**Behaviour matrix:**

| Migration status | `--delete-originals` absent | `--delete-originals` present |
|---|---|---|
| `fully-migrated` | originals kept | originals deleted |
| `partially-migrated` | originals kept | originals deleted |
| `not-migratable` | originals kept | originals kept (never touched) |
| skipped (no twig) | originals kept | originals kept |
| dry-run (any status) | originals kept | originals kept (dry-run never writes) |

**New stat counter:** `deletedOriginals` in `RunStats` — incremented once per component pair deleted (counts the component, not individual files).

**Report lines:** Two `deleted originals` lines are appended to the report for each cleaned-up component, showing the exact paths removed.

**Summary output** includes a new `Deleted originals:` line.

---

## Invocation

```bash
# Preview (no files written or deleted):
npx tsx run-sfc-migration.ts --dry-run src/app/component/base

# Write .vue files, keep originals:
npx tsx run-sfc-migration.ts --write src/app/component/base

# Write .vue files and delete originals:
npx tsx run-sfc-migration.ts --write --delete-originals src/app/component/base

# Write, overwrite existing .vue, and delete originals:
npx tsx run-sfc-migration.ts --write --force --delete-originals src/app/component/base
```

---

## Tests added

11 new tests across three new `describe` blocks in `run-sfc-migration.spec.ts`:

- `runMigration — delete-originals (fully-migrated)` — 7 tests
- `runMigration — delete-originals (partially-migrated)` — 2 tests
- `runMigration — delete-originals (not-migratable)` — 2 tests

Total test count: **172** (up from 161), all passing.

---

## Acceptance check

- [x] Original files are removed after successful migration (via `--delete-originals` flag)
- [x] `not-migratable` components' originals are never touched
- [x] Partially-migrated components' originals are deleted (the `.vue` is the new source of truth)
- [x] Confirmation is provided via opt-in `--delete-originals` flag (no surprise deletions)
- [x] Dry-run mode never deletes anything even when `--delete-originals` is passed
- [x] `deletedOriginals` stat and report lines show what was removed
