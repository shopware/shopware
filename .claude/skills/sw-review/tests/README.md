# Review skill tests

> [!WARNING]
> Running the tests is cost-intensive.

End-to-end evals for the `.claude/skills/sw-review` PR-review skill. Built on
[skillgrade](https://github.com/mgechev/skillgrade).

## Layout

```
tests/
├── eval.yaml   # 10 task definitions
├── _lib.sh     # shared check/emit helpers (mounted into every workspace)
└── persona/<persona>/{catch,ignore}/{input.json, diff.patch, grader.sh}
```

## Prerequisites

- Node 20+ (`npm i -g skillgrade`)
- `zod` global (`npm i -g zod` — skillgrade has an unbundled peer dep)
- `jq`
- `claude` CLI on `$PATH` with an authenticated session

## Running

From this directory:

```bash
# Smoke (1 trial per task) — fastest, fail-fast signal.
skillgrade --provider=local --agent=claude --trials=1

# A single task (cheapest sanity check).
skillgrade --provider=local --agent=claude --eval=persona-open-source-catch --trials=1

# Variance — same task five times, reports pass@k and pass^k.
skillgrade --provider=local --agent=claude --smoke

# Tighter pass-rate estimate for CI.
skillgrade --provider=local --agent=claude --reliable --ci --threshold=0.8
```

Results land in `$TMPDIR/skillgrade/tests/results/<task>_<ts>.json`.
Override with `--output=/path`.
