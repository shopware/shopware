# Reproduce Issue

Turn a Shopware bug report into **one verified reproduction**, then post a verdict on the issue.

An AI agent authors a small reproduction *bundle* on a live shop. It decides no outcome: after it
stops, deterministic scripts re-run that exact bundle on the **reported version** and on **trunk**,
compare the results, and post the verdict. The trusted re-run happens from an immutable copy of this
tooling on fresh runners, so the agent cannot fake a result.

## Pipeline

```
issue → fetch-issue → resolve-version → provision(reported) → snapshot-db → context.md
      → AGENT: author bundle, verify assumptions (seed / check / playwright-cli), stop
      → post-steps: guard workspace → repro verify (reported, from the immutable copy) → upload artifacts
      → trunk job (fresh runner): provision(trunk) → repro verify (trunk) → verdict → comment
```

The agent's own `try` is feedback only; the **official** result of each leg is produced by `repro
verify`, which only the deterministic steps run (gated by `REPRO_ALLOW_VERIFY=1`).

## Layout

- `prompt/task.md` — the concise agent playbook; `prompt/guides/*.md` — depth read on demand.
- `cli/` — terminal-facing entrypoint, one file per public subcommand in `commands/`, plus shared
  CLI helpers such as `full-run.ts` and `execute-bundle.ts`.
- `executors/` — code that runs authored test cases and classifies their output.
  Playwright harness assets and storage-state scripts live in `executors/playwright/boilerplate/`.
- `bundle.ts` — shared bundle contract, placeholder helpers, and canonical result construction.
- `admin-api.ts` — Admin API transport used by seeding and HTTP placeholder resolution.
- `steps/` — thin bash glue for the GitHub-Actions-only concerns (fetch/version/provision/snapshot/context, plus the sandbox sales-channel-domain + legacy-alias helpers).
- `report/` — `verdict.ts` (two legs → verdict) and `comment.ts` (render from `templates/`).
- `templates/` — `verdicts.json` (all comment copy, as data) + `comment.*.md` layouts.
- `dev/compile.sh` — MAINTAINER tool: regenerate the committed lock from the gh-aw source.

## The bundle

- `reproduction-plan.json` — the contract (see `prompt/guides/plan.md`).
- `fixtures.json` — optional Admin Sync seed data (`prompt/guides/fixtures.md`).
- one test artifact: `repro.spec.ts` | `ReproTest.php` | inline http request/assertions in the plan.

The test asserts the **healthy** behaviour, so it fails on the buggy version (⇒ `reproduced`) and
passes when healthy (⇒ `not_reproduced`). The verdict combines the two legs:

| reported \ trunk | reproduced | not_reproduced |
| --- | --- | --- |
| **reproduced** | `live_bug` | `fixed_on_trunk` |
| **not_reproduced** | `regression` | `not_reproducible` |

A blocked leg → `blocked`; low confidence, a `blocked_reason`, or an inconclusive leg →
`needs_human_review`.

## Language & runtime

Sources are **TypeScript run with no build step** — Node's native type-stripping executes the `.ts`
files directly (see the `repro` wrapper in `reproduce.md`, which runs `node --experimental-strip-types
…/repro.ts`). Nothing is emitted, so the immutable copied source is exactly what runs. Two constraints
this imposes:

- **Node ≥ 22.18** (or ≥ 23.6) wherever the CLI runs. The `--experimental-strip-types` flag on the
  wrapper is belt-and-suspenders for 22.6–22.17.
- **Erasable syntax only** (no `enum`/`namespace`/parameter properties — enforced by
  `erasableSyntaxOnly`) and **`import type` for every type-only import** (`verbatimModuleSyntax`) — a
  type imported as a value isn't erased and throws at runtime.

`typescript`/`@types/node` are **devDependencies only**; the runtime needs zero deps (stripping is
built into Node). The Playwright `boilerplate/` stays plain `.mjs`/`.js`/`.ts` (browser/Playwright
context) and is excluded from the Node `tsconfig`.

## Linting & tests

This action has its own isolated Node toolchain (`package.json` here; `npm ci` installs it):

- `npm run typecheck` — `tsc --noEmit` (strict). This is the type gate.
- `npm run lint` — ESLint (flat config, `typescript-eslint`) over the `.ts`/`.mjs` sources.
- `npm test` — `node:test` unit + integration suites (co-located `*.test.ts`). Covers the pure
  trust-critical logic (assertion/output classifiers, verdict matrix, bundle helpers,
  placeholder/narration handling), the executor/CLI/report modules, and an HTTP-executor integration
  slice against a local server. `npm run test:coverage` prints a coverage table.
- `npm run test:bash` — plain-bash tests (`tests/bash/`, no `bats` dependency) for the offline logic
  in `steps/*.sh`, e.g. version extraction in `resolve-version.sh`.

CI runs all of these, path-filtered to this directory, via
`.github/workflows/reproduce-action-tests.yml`. The docker/Playwright/live-shop executor paths are
intentionally proven by the end-to-end sample runs rather than mocked here.

## Changing the workflow

Edit `.github/workflows/reproduce.md` (the gh-aw source), then run
`bash .github/actions/reproduce/dev/compile.sh` to regenerate `reproduce.lock.yml` (it also
re-applies the two patches gh-aw source can't express). Commit both. Never hand-edit the lock file.
