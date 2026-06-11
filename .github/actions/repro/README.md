# Bug-reproduction pipeline

Automatically reproduces a reported bug issue **on the reported version and on trunk in
parallel**, then posts the verdict + evidence back to the issue. The agent owns only the
thin slices (read the issue → derive a repro plan → write the report); provisioning,
building and running are deterministic CI jobs. The guiding principle is **derive, don't
discover**: the repro is derived from the linked fix PR's regression test, not searched
for by trial and error.

## How to enable

1. Add a `QUALITY_INITIATIVE_ANTHROPIC_API_KEY` repository secret (used only by the
   Analyze job); `ANTHROPIC_API_KEY` is accepted as a fallback. Without either the
   workflow **hard-fails** rather than fabricating a plan.
2. Trigger a run by either:
   - applying the `ci:reproduce` label to an issue, **or**
   - `gh workflow run reproduce.yml -f issue_number=<N> -f post_comment=true`
3. The workflow reads the issue, reproduces, and (when triggered by a label, or with
   `post_comment=true`) comments the verdict. Manual runs default to job-summary-only.

## Phases

```
gate ─▶ analyze ─▶ reproduce (matrix: reported ‖ trunk) ─▶ verdict ─▶ report
```

- **gate** — deterministic checks (a real "How to reproduce" / "Steps to reproduce" section
  must exist) before any agent runs; cheap rejection of under-specified issues.
- **analyze** — the only AI step. Emits `analysis.json`: the cheapest faithful `layer`,
  minimal `build_profile`, `fixtures`, and an `assertion` derived from the fix PR's
  regression test. Pinned to a bounded turn budget.
- **reproduce** — one parallel leg per target version. The `executor` is chosen by layer.
- **verdict / report** — deterministic merge + verdict map, then the issue comment.

## Executors (cheapest faithful layer first)

| Executor | Layer | Use when | Evidence |
|---|---|---|---|
| `http` | `*-api` | the bug surfaces on a store-/admin-API response | the resolved `curl` script + HAR |
| `playwright` | `*-ui` | a genuine UI/storefront bug | the spec + screenshot/video/trace |
| `direct` | `service` | the bug lives in an internal service/indexer/calculation that store-api or the UI can't faithfully exercise (license-gated, heavy domain setup) | the generated PHPUnit integration test |

`expect = healthy`: the generated test asserts the *fixed* behaviour, so it **fails on the
buggy version** (`reproduced`) and **passes when healthy** (`not_reproduced`).

## Verdict states

| Verdict | reported | trunk | Meaning |
|---|---|---|---|
| `live_bug` | reproduced | reproduced | still broken on trunk |
| `fixed_on_trunk` | reproduced | not_reproduced | fixed; cites the backport candidate |
| `regression` | not_reproduced | reproduced | newly broken on trunk |
| `not_reproducible` | not_reproduced | not_reproduced | cannot reproduce as described |
| `needs_info` | — | — | issue lacks usable repro steps (asked, not run) |
| `needs_human_review` | — | — | mid-confidence plan or an indeterminate leg |
| `blocked` | — | — | provisioning/seeding failed (infra, not a verdict) |

## Cost discipline

- **Match env to surface** — `direct`/`http` legs build neither storefront nor theme.
- **Confidence bands** — a plan the analyzer doesn't trust (`< 0.4`) is **not run**; it
  asks a human to confirm the draft first, rather than provisioning two installs to test
  a guess. `0.4–0.7` runs but routes to `needs_human_review`.
- **Fail fast, never yield mid-build** — one-shot provision, poll until READY.

See [`../../../.claude/skills/reproduce/references/SCHEMA.md`](../../../.claude/skills/reproduce/references/SCHEMA.md)
for the full JSON contracts.

## Layout

```
.github/workflows/reproduce.yml        orchestrator (gate→analyze→reproduce→verdict→report)
.github/workflows/reproduce-eval.yml   skillgrade eval for the Analyze phase
.github/actions/repro/provision/       setup-shopware + server-ready poll
.github/actions/repro/bin/run-http.sh        http executor
.github/actions/repro/bin/run-playwright.sh  playwright executor
.github/actions/repro/bin/run-direct.sh      direct (PHPUnit) executor
.github/actions/repro/bin/seed.sh            minimal fixtures via the admin sync API
.claude/skills/reproduce/              the interactive skill + JSON contracts + tests
```
