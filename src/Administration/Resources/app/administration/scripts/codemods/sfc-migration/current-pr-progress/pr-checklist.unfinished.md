# Missing: PR Checklist Items Are All Unchecked

**Status:** No checklist items have been verified or ticked in PR #15673.

---

## Current state

The PR template includes a standard checklist. None of the items are checked because the PR was opened early as a draft and the checklist was never revisited.

---

## Items to verify before promoting to "Ready for review"

Work through each standard item in the PR template:

### Tests

- [ ] New functionality has test coverage
- [ ] `run-sfc-migration.ts` tests written (see [runner-tests.unfinished.md](runner-tests.unfinished.md))
- [ ] Untested conversion paths have fixtures (see [untested-conversions.unfinished.md](untested-conversions.unfinished.md))
- [ ] All existing tests pass

### Release notes / changelog

- [ ] Determine if this change requires a changelog entry (it is a developer tool, not a user-facing feature — but check the repo's policy)
- [ ] If required, add a changelog entry describing the new codemod

### Documentation

- [ ] `README.md` is up to date with final behavior
- [ ] `README.md` documents all known limitations
- [ ] PR description is filled in (see [pr-description.unfinished.md](pr-description.unfinished.md))

### Breaking changes

- [ ] Confirm whether any existing tooling or scripts are affected by this PR
- [ ] If the `--dry-run` default behavior is changed, note it as a breaking change for any scripts that call the runner directly

### Code quality

- [ ] Linter passes (`npm run lint` or equivalent)
- [ ] TypeScript compiler passes with no errors
- [ ] No `console.log` debug statements left in source

---

## Relevant link

PR: shopware/shopware#15673

---

## Acceptance check

- [ ] All checklist items in the PR template are ticked (or explicitly marked N/A with a reason)
- [ ] PR is ready to promote from draft to "Ready for review"
