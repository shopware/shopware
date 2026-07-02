# Reproduce Issue

Turn a Shopware bug report into **one verified reproduction**, then post a verdict on the issue.

An AI agent authors a small reproduction *bundle* on a live shop. It decides no outcome: after it
stops, deterministic scripts re-run that exact bundle on the **reported version** and on **trunk**,
compare the results, and post the verdict. Because the trusted re-run happens from an immutable copy
of this tooling on fresh runners, the agent cannot fake a result — so the evidence can be trusted.

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
- `cli/` — one Node CLI (`repro.mjs`) used by the agent and the deterministic pipeline:
  `validate` · `seed` · `check` · `try` (agent preview) · `giveup` · `verify` (trusted) · `reset`.
  Executors live in `cli/executors/`; `admin-api.mjs` is the MCP-independent seeding transport.
- `steps/` — thin bash glue for the GitHub-Actions-only concerns (fetch/version/provision/proxy/snapshot/context).
- `report/` — `verdict.mjs` (two legs → verdict) and `comment.mjs` (render from `templates/`).
- `templates/` — `verdicts.json` (all comment copy, as data) + `comment.*.md` layouts.
- `mcp-bridge.mjs` — Shopware MCP bridge the agent uses to author fixtures (Shopware 6.7+).
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

## Changing the workflow

Edit `.github/workflows/reproduce.md` (the gh-aw source), then run
`bash .github/actions/reproduce/dev/compile.sh` to regenerate `reproduce.lock.yml` (it also
re-applies the two patches gh-aw source can't express). Commit both. Never hand-edit the lock file.
