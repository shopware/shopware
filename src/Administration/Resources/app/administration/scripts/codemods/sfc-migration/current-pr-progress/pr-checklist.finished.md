# PR Checklist — Verified ✅

**Status:** All checklist items verified.

---

## Verified items

### Tests

- [x] New functionality has test coverage — 198 tests across 5 spec files, all passing
- [x] `run-sfc-migration.ts` tests written — 42 tests in `run-sfc-migration.spec.ts`
- [x] Untested conversion paths have fixtures — 11 fixtures covering all code paths
- [x] All existing tests pass — 198/198 ✓

### Release notes / changelog

- [x] Change added to `RELEASE_INFO-6.7.md` under `## Administration` (upcoming) — describes the new codemod, its usage, and links to the README

### Documentation

- [x] `README.md` is up to date with final behavior and all flags (`--write`, `--force`, `--delete-originals`, `--dry-run`)
- [x] `README.md` documents all known limitations (manual review items, `$el`, `extends`, `<sw-block>` not yet existing)
- [x] PR description drafted in `pr-description.finished.md` — ready to paste into shopware/shopware#15673

### Breaking changes

- [x] No existing tooling or scripts are affected — this is a purely additive new tool
- [x] `--dry-run` is the default (safe) mode — no files are ever written without `--write`; not a breaking change as this is a new script

### Code quality

- [x] Linter — `scripts/**/*` is intentionally excluded from ESLint by the project's `eslint.config.mjs`; 0 errors
- [x] TypeScript compiler — `npx tsc --noEmit` exits 0, no errors
- [x] No debug `console.log` statements — all three `console.log` calls in `run-sfc-migration.ts` are intentional CLI output (report lines and migration summary)

---

## Remaining open items before promoting to "Ready for review"

| Item | Notes |
|------|-------|
| `<sw-block>` / `<sw-block-parent>` components | Tracked in `sw-block-components-missing.unfinished.md`; belongs in a separate companion PR |
| PR checklist boxes in GitHub | Paste `pr-description.finished.md` into the PR and tick all checklist boxes manually |

---

## Relevant link

PR: shopware/shopware#15673
