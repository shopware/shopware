# Done: Silent Overwrite of Existing `.vue` Files — Fixed

**Status:** Implemented — existing `.vue` files are skipped by default; `--force` flag allows overwriting.

---

## What was done

`run-sfc-migration.ts` now checks whether a `.vue` output file already exists before writing.

**Default behavior (no `--force`):** If the output file already exists, the runner skips it and logs a `SKIP (already exists)` line. The existing content is not touched.

**`--force` flag:** Passing `--force` disables the check and overwrites existing files as before.

**Dry-run mode:** The existence check is intentionally skipped in dry-run mode — dry-run never writes anything, so skipping based on existence would suppress report lines that are still useful for preview.

---

## Changes made

- `RunOptions` — added `force?: boolean`
- `RunStats` — added `skippedExisting: number`
- `runMigration` — existence check before `writeFileSync` in both `fully-migrated` and `partially-migrated` cases
- CLI — `--force` parsed from `argv`; `Skipped (exists):` line added to the summary output
- `run-sfc-migration.spec.ts` — 6 new tests in `runMigration — overwrite protection`:
  - skips and increments `skippedExisting`
  - preserves existing content
  - report line contains `SKIP (already exists)`
  - `--force` overwrites and counts as `fullyMigrated`
  - `--force` sets `skippedExisting` to 0
  - dry-run mode is unaffected by existence

---

## Acceptance check

- [x] If a `.vue` file already exists, the runner skips it and logs a warning
- [x] A `--force` flag allows overwriting existing files
- [x] The skip is counted/reported in the final summary (distinct from `not-migratable` skips)
- [x] Test in `run-sfc-migration.spec.ts` covers the skip-existing behavior
