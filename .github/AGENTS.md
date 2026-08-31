# GitHub Automation

How the CI here is laid out, and the rules for changing it. The rules are
mandatory; the reasoning, worked examples, and the incident that produced most of
them live in
[`coding-guidelines/core/ci-workflows.md`](../coding-guidelines/core/ci-workflows.md).

## How the CI fits together

The tables and lists in this section are orientation, not a specification. The
workflow files are the source of truth: read the file before relying on a
version, a branch name, a schedule, or the completeness of a list here.

Almost every test workflow is **both** a PR entry point and a reusable workflow:
it declares `pull_request`, `merge_group`, `workflow_dispatch` *and*
`workflow_call`, so the same file runs on a PR and is called again by the
nightlies and the release gate. Change one and you change all three contexts.

| Workflow | Covers |
|---|---|
| `php.yml` | lint, phpstan, rector, bc-checker, openapi-lint, PHPUnit `unit` and `migration` suites, license-check, composer-audit, composer-prefer-lowest |
| `integration.yml` | PHPUnit integration shards, dynamic matrix |
| `integration-major.yml` | the same with `FEATURE_ALL: major` |
| `admin.yml` | ESLint, Stylelint and Jest for the Administration |
| `storefront.yml` | ESLint, Stylelint, snippet and Twig lints, Jest and Vitest |
| `acceptance.yml` | Playwright acceptance runs |
| `lint-actions.yml` | actionlint, yamlfmt, zizmor, and the `.github/bin/js` tests |

Composition, rather than duplication, is how the arms are built:

- `nightly.yml` calls `admin`, `integration`, `acceptance`, `visual-tests`,
  `php`, `storefront`, `zugferd-compliance`, `downstream` and `05-prepare-release`
  with `profile: nightly`.
- `nightly-major.yml` calls `acceptance` and `integration-major`; its cron is
  deliberately offset from `nightly.yml` so the two do not overlap.
- `release-gate.yml` runs on pushes to **every** maintenance branch — the minor
  and patch-line branch globs in its `on: push:` block, not a fixed list of
  versions — and calls the same workflows with `profile: release`.

Three mechanisms decide how much runs:

- **Profile** — `''` (PR), `nightly`, or `release`, passed into
  `generate-phpunit-matrix.php` / `generate-acceptance-matrix.php`. Only
  `nightly` widens the matrix. The matrix is generated at runtime and consumed as
  `strategy: ${{ fromJson(...) }}`.
- **Major arms** — opt in on a PR with the `major-php` or `major-acceptance`
  label, or the `major-tests` umbrella. `01-pr-issue-labeler.yml` applies
  `major-php` automatically when the diff touches major feature flags. Nightly
  and manual runs ignore the labels.
- **`markdown-only-changes`** — a first job in each heavy workflow that
  short-circuits docs-only PRs.

PHPUnit runs through three composite actions rather than one, so each phase gets
its own timing in the job UI: `phpunit-prepare` (PHP, database, webserver, test
install) → `phpunit-run` (the suite) → `phpunit-upload` (Codecov, called with
`if: !cancelled()`).

The agentic workflows (`sw-triage`, `sw-review`, `sw-bugfixer`) are
[gh aw](https://github.com/githubnext/gh-aw) sources: edit `<name>.md`, never the
generated `<name>.lock.yml`, and run `gh aw compile`. Setup and the version pin
live in [`.github/aw/README.md`](aw/README.md).

Locally: `composer lint:actions` runs the workflow linters,
`cd .github/bin/js && node --test` runs the automation-script tests.

## Fix it at the lowest layer that covers everyone

A convention that only lives in prose is not enforced. Prefer, in this order:

1. **A guard inside the tool or its bootstrap** — protects every caller at once.
   `Shopware\Core\Test\PHPUnit\CompletionGuard`, registered from
   `TestBootstrapper::bootstrap()`, is the reference: it fails any PHPUnit run
   that dies before the suite finishes, for core, plugins, and downstream
   projects alike.
2. **An assertion in the job**, when no tool-level hook exists.
3. **A lint rule** under `.github/bin/js/` or in `.github/bin/lint-actions.bash`,
   when the mistake is visible in the source.
4. **A written rule**, only for what none of the above can catch.

Guards need tests, and they are easy to test badly — a guard is only ever
observed *not* firing. Extract the decision into a pure function and unit-test
both branches, as `CompletionGuard::shouldForceFailure()` does.

## Green means proven green

**An exit code is not evidence that the work happened.** A step can exit `0`
without running anything: a command that was never reached, a test selection that
matched nothing, a build that skipped every target, a script that swallowed its
own error.

- PHPUnit is covered by `CompletionGuard`. **Every other tool a job invokes is
  not** — ask of each one whether it can exit `0` without doing the work, and add
  an assertion when it can.
- Assert something the run **produces**, not something the runner set up. Put the
  assertion in its own step, and never behind `continue-on-error`.
- A missing tool is a failure in CI, not a skip. `.github/bin/lint-actions.bash`
  is the pattern to copy: print an install hint and skip locally, hard error
  when `CI=true`, so the check cannot silently pass.

## No suppression without a stated reason

`continue-on-error`, `|| true`, `set +e`, and `if: always()` on an assertion each
turn a red job green. Every occurrence needs an inline comment saying why, and
the exception has to be narrow.

- **Retries** cover network and infrastructure flakiness only. Never wrap a test
  run, a linter, or an assertion in a retry — a flaky test is a bug report, not
  a retry budget.
- **`if: always()`** belongs on upload and cleanup steps so artifacts survive a
  failure. It must never be the reason a failed job reports success.
- Never widen a failure filter to get a job passing. Fix the cause, or skip the
  job explicitly with a documented condition so the skip is visible.

## Don't reinvent, don't inline a parser

- Prefer an established action or library over hand-rolled Bash or JavaScript.
- Do not parse JSON, YAML, or Markdown in Bash. Use `jq`, or move the logic into
  a script (below).
- Pin third-party actions to a commit hash with a version comment
  (`uses: actions/checkout@9c091bb… # v7.0.0`). `shopware/*` actions may use a
  mutable ref such as `@main`.

## Logic lives in a tested script

`run:` blocks are glue, not programs. Once a step passes roughly ten lines or
grows a branch worth getting wrong, move it out:

- **JavaScript/TypeScript** → `.github/bin/js/<name>.ts` with a sibling
  `<name>.test.ts`; `node --test` runs them from `lint-actions.yml`. See
  `auto-label-major-php.ts` for the shape. Do not use Python.
- **PHP** → `.github/bin/<name>.php` with a PHPUnit test.
- Logic repeated across workflows → a composite action under `.github/actions/`.
- Start every non-trivial Bash `run:` block with `set -euo pipefail`.

## Before you open the PR

- `composer lint:actions` — mandatory for any file under `.github/workflows/` or
  `.github/actions/`. `composer lint:actions:fix` applies the formatting.
- `cd .github/bin/js && node --test` when you touched a script there.
- **Prove it ran.** Link a real run — `workflow_dispatch`, a draft PR, or a
  throwaway branch. For a new guard, also show it failing: a unit test over its
  decision function, or a run on a deliberately broken branch. A guard that has
  only ever been observed passing is untested.
