# CI workflows

The rules for `.github/` are in [`.github/AGENTS.md`](../../.github/AGENTS.md).
This document holds only what does not fit there: why the rules are shaped the
way they are, and the examples that make them concrete. Read it when reviewing a
CI change, or before changing one of the rules.

## Fix it at the lowest layer that covers everyone

A unit test ran a Symfony console `Application` through `ApplicationTester`
without `setAutoExit(false)`. `Application::run()` called
`exit(0)`, PHPUnit died mid-suite, no report was written — and the job reported
success. Every later test silently did not run and Codecov stopped receiving
coverage. Full write-up in
[issue #18661](https://github.com/shopware/shopware/issues/18661).

The lesson is not "remember `setAutoExit(false)`". Nobody writing a
console-command test is thinking about job exit-code semantics, and no reviewer
catches it either. The fix has to sit somewhere that does not depend on anyone
remembering — hence the ordering in `.github/AGENTS.md`.

Why the guard ended up in `TestBootstrapper` and not in the workflow is the
clearest illustration. The first attempt asserted that `junit.xml` existed after
the run. That works, but it has to be repeated in every job that needs it, and
the nightlies contain jobs that produce no JUnit report at all — those would have
stayed unprotected. `CompletionGuard` sits in the bootstrapper, so core suites,
plugins, and downstream projects are covered at once with no `phpunit.xml`
wiring. A guard one layer down is worth several assertions one layer up.

The same reasoning applies to assertions you *do* have to write in a job: assert
what the run **produces**, not what the runner set up, and do not assert on an
artifact that some matrix legs legitimately never create — that pushes people
toward weakening the shared assertion until it proves nothing.

Guards are easy to test badly, because a guard is only ever observed *not*
firing. Extract the decision into a pure function and unit-test both branches;
`CompletionGuard::shouldForceFailure()` with `CompletionGuardTest` is the
pattern.

## Why suppression is treated strictly

`continue-on-error`, `|| true`, `set +e`, `if: always()`, and retries are all
legitimate somewhere and corrosive everywhere else, so the rule asks for a
comment on each use rather than banning them.

`|| true` is the one that most often hides a real failure, because it discards
the distinction between "expected non-zero" and "broken". Capture the code and
check it instead:

```bash
# Bad — a crash and a clean "no match" are now the same thing
some-check || true

# Good — exit 1 is the expected "nothing found", anything else is a failure
set +e
some-check
status=$?
set -e
if [ "$status" -ne 0 ] && [ "$status" -ne 1 ]; then
  echo "some-check failed unexpectedly (exit $status)" >&2
  exit "$status"
fi
```

Retries deserve their own argument. A retry around a test run does not just hide
one red run — it removes the flake rate as a measurable quantity, so the
underlying bug never gets prioritised. A flaky test is a bug report; wrapping it
in a retry converts it into a permanently invisible one. Retries are for network
and infrastructure only.

The general form: **do not make a job green by suppressing information.** When a
job genuinely should not run, skip it with a documented `if:` condition — a
skipped job is visible in the UI, a suppressed failure is not.

## Gotchas worth knowing

- `set -euo pipefail` is required, but `pipefail` turns an early-exiting
  downstream command into a `SIGPIPE` failure. Commit `04d02efd2eb` fixed exactly
  this in the markdown-only change detection.
- `.github/actions/phpunit-upload` asks callers for `if: !cancelled()` rather
  than `always()`, so a cancelled run stops instead of continuing to upload.
  Prefer `!cancelled()` wherever you would reach for `always()`.
- Action hash pinning is enforced by zizmor's `unpinned-uses` audit, configured
  in `.github/zizmor.yml` and run from `composer lint:actions`. zizmor is adopted
  one audit at a time; the remaining audits are disabled there and report a few
  hundred findings between them, so enabling one is its own piece of work.
- zizmor cannot parse a workflow whose `strategy:` is a dynamic
  `${{ fromJson(...) }}` matrix, and it *warns and exits 0* rather than failing —
  an unparsed file is an unaudited file. `zizmor-collection-guard.ts` reconciles
  those warnings against a known list so a new one fails the lint, and so a
  listed file that starts parsing again is reported as a stale entry.

## Reviewing a CI change

1. **If this step did nothing at all, would the job still be green?** If yes, the
   job is not asserting anything.
2. Does any new `continue-on-error`, `|| true`, `set +e`, `if: always()`, or
   retry carry a comment explaining why?
3. Is there `run:` logic that a unit test could have covered?
4. Are new third-party actions pinned to a hash?
5. Has the change actually been run — and, for a guard, been seen to fail?

## Related

- Tests must not terminate the process: [`unit-tests.md`](unit-tests.md) and the
  `shopware-phpunit-tests` skill.
- Agent skills and their unattended CI twins: [`agent-skills.md`](agent-skills.md).
- gh aw setup, pinning, and secrets: [`.github/aw/README.md`](../../.github/aw/README.md).
