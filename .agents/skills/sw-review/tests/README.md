# Review skill tests

> [!WARNING]
> Running the tests is cost-intensive.

End-to-end evals for the `.agents/skills/sw-review` PR-review skill. Built on
[skillgrade](https://github.com/mgechev/skillgrade).

## Layout

```
tests/
├── eval.yaml   # persona and guide task definitions
├── routing/    # deterministic classify.ts cases (routing.test.ts)
├── _lib.sh     # shared check/emit helpers (mounted into every workspace)
└── persona/<persona>/{catch,ignore}/{input.json, diff.patch, grader.sh}
```

## Prerequisites

- Node 22.18+ (runs the TypeScript scripts directly; `npm i -g skillgrade` for the evals)
- `zod` global (`npm i -g zod` — skillgrade has an unbundled peer dep)
- `jq`
- `claude` CLI on `$PATH` with an authenticated session

## Routing tests (no LLM, run on every change)

```bash
node --test scripts/*.test.ts tests/routing/*.test.ts   # from the skill root
```

Each case under `tests/routing/<case>/` holds a real PR diff plus `expected.json`
(signals that must be present, signals that must be absent, the exact guide
set). These run in seconds and prove that `scripts/classify.ts` routes the same
way every time; `scripts/classify.test.ts` and `scripts/ci-profile.test.ts` are
the unit tests of the scripts themselves.

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
