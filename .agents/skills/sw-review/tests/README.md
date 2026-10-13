# Review skill tests

> [!WARNING]
> Running the tests is cost-intensive.

End-to-end evals for the `.agents/skills/sw-review` PR-review skill. Built on
[skillgrade](https://github.com/mgechev/skillgrade).

## Layout

```
tests/
├── eval.yaml   # persona and guide task definitions
├── routing/    # deterministic classify.sh cases (run.sh)
├── _lib.sh     # shared check/emit helpers (mounted into every workspace)
└── persona/<persona>/{catch,ignore}/{input.json, diff.patch, grader.sh}
```

## Prerequisites

- Node 20+ (`npm i -g skillgrade`)
- `zod` global (`npm i -g zod` — skillgrade has an unbundled peer dep)
- `jq`
- `claude` CLI on `$PATH` with an authenticated session

## Routing tests (no LLM, run on every change)

```bash
bash tests/routing/run.sh
```

Each case under `tests/routing/<case>/` holds a real PR diff plus `expected.json`
(signals that must be present, signals that must be absent, the exact guide
set). These run in seconds and prove that `scripts/classify.sh` routes the same
way every time.

## Running

From this directory:

```bash
# Smoke (1 trial per task) — fastest, fail-fast signal.
skillgrade --provider=local --agent=claude --trials=1

# A single task (cheapest sanity check).
skillgrade --provider=local --agent=claude --eval=persona-maintainer-catch --trials=1

# Variance — same task five times, reports pass@k and pass^k.
skillgrade --provider=local --agent=claude --smoke

# Tighter pass-rate estimate for CI.
skillgrade --provider=local --agent=claude --reliable --ci --threshold=0.8
```

Results land in `$TMPDIR/skillgrade/tests/results/<task>_<ts>.json`.
Override with `--output=/path`.
