# GitHub Automation

Rules for everything under `.github/` — workflow sources, composite actions, and
the scripts they call. They are mandatory. The reasoning, worked examples, and
the incident that produced most of them live in
[`coding-guidelines/core/ci-workflows.md`](../coding-guidelines/core/ci-workflows.md).

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
