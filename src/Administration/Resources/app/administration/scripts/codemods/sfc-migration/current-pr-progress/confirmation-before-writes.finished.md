# Missing: Confirmation Before Writing Files (AC #4)

**Acceptance criterion:** Breaking changes are only introduced after confirmation.
**Status:** Not implemented — files are written immediately without any prompt or opt-in flag.

---

## Current behavior

`run-sfc-migration.ts` writes `.vue` files unconditionally as soon as `mergeComponentFiles` returns a non-empty result. There is no way to preview what would be written without it actually happening.

```ts
// run-sfc-migration.ts (current)
fs.writeFileSync(outPath, result.sfc, 'utf8');
```

Running the codemod against a directory is already destructive on the first invocation.

---

## What needs to be done

### 1. Add a `--dry-run` flag

When `--dry-run` is passed, the runner should:
- Print every file that *would* be written (with its migration status and blockers)
- Not write any files to disk
- Not delete any files

Example output in dry-run mode:
```
[DRY RUN] Would write: src/app/component/sw-button/sw-button.vue (fully-migrated)
[DRY RUN] Would write: src/app/component/sw-card/sw-card.vue (partially-migrated) [mixins]
[DRY RUN] Would skip:  src/app/component/sw-render/sw-render.vue (not-migratable) [render function]
```

### 2. Interactive confirmation prompt (optional but preferred)

Without `--dry-run`, show a summary of what will be written and ask for confirmation before proceeding:

```
About to write 42 .vue files:
  38 fully-migrated
   3 partially-migrated
   1 skipped (not-migratable)

Proceed? [y/N]
```

Use Node's `readline` or a small dependency like `prompts` / `enquirer`. No heavy CLI framework needed.

### 3. `--dry-run` should be the default (or clearly documented)

Since the codemod is destructive, consider making `--dry-run` the default and requiring an explicit `--write` flag to actually write files. This is the approach used by Prettier and many other codemods.

---

## Relevant file

- `run-sfc-migration.ts` — the only place files are written

---

## Acceptance check

- [ ] Running without flags prints a preview and does not write files (or prompts first)
- [ ] `--dry-run` flag prevents all file writes
- [ ] `--write` (or equivalent) flag is required for actual writes
- [ ] Dry-run output clearly marks each file with its status
