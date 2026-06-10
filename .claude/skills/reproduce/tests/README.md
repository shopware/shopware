# Reproduce skill tests

> [!WARNING]
> Running the evals is cost-intensive (each task spawns a real agent run).

End-to-end evals for the `.claude/skills/reproduce` skill's **Analyze** phase,
built on [skillgrade](https://github.com/mgechev/skillgrade). They check that
Analyze **derives** a correct, schema-valid repro plan from a bug report + the
fix PR's regression test — the "derive, don't discover" contract.

## Layout

```
tests/
├── eval.yaml            # task definitions
├── _lib.sh              # shared check/emit helpers (mounted into every workspace)
└── analyze/<issue>/     # input.json, issue.md, regression-test.php, grader.sh
```

Fixtures are **self-contained** (the issue body and the fix PR's test are checked
in as files), so the eval is deterministic — no live `gh` fetch, no issue drift.

## Prerequisites

- Node 20+ (`npm i -g skillgrade zod`)
- `jq`
- `claude` CLI on `$PATH` with an authenticated session (or `CLAUDE_CODE_OAUTH_TOKEN`)

## Running

From this directory:

```bash
# One task (cheapest sanity check).
skillgrade --provider=local --agent=claude --eval=analyze-16511-listing-pagination --trials=1

# All tasks.
skillgrade --provider=local --agent=claude --trials=1

# Tighter pass-rate estimate for CI.
skillgrade --provider=local --agent=claude --reliable --ci --threshold=0.8
```

Or trigger the **`Reproduce Skill Eval`** workflow (`workflow_dispatch`) and pick a
branch — runs the same thing in CI (needs `CLAUDE_CODE_OAUTH_TOKEN`).

## Grading

Each `grader.sh` sources `_lib.sh`, asserts the produced `output.json` against the
plan's `SCHEMA.md` shape (`check_schema_analysis`) plus task-specific predicates
(right layer/executor, verbatim request, **healthy** assertion value, minimal
fixtures, `derived_from` the test). Score is `passed/total`; threshold `0.8`.
