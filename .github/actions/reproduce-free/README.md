# Reproduce Issue — FREE variant

A/B experiment against [`../reproduce`](../reproduce): same goal (turn a bug report into **one
verified reproduction** + a posted verdict), but the agent is maximally free — **prompts, not
schemas, guide it**. No executors, no plan contract, no sandbox. Trust comes from three
deterministic mechanisms instead:

1. **Differential execution** — the exact authored bundle is re-run on the reported version AND on
   a fresh trunk shop; the verdict is derived from the difference, never from the agent.
2. **Harness-resolved reporting** — the agent writes the issue-comment *template*; every code
   block, run output, and evidence image in it is inserted by the harness from the trusted runs.
   The agent references facts, it cannot author them.
3. **Disclosure by default** — every file the agent changed but never referenced via `{{file:…}}`
   is called out in the posted comment; edits to the provisioned shop's own code downgrade the
   verdict to `needs_human_review`.

## The bundle (authored by the agent in `repro/`)

Only two file names are contract:

- `repro/run.sh` — the whole reproduction (prep + observe), executed identically on both legs.
  Exit 0 = healthy, 1 = bug observed, ≥2 = setup failure (never counts as the bug).
- `repro/comment.md` — the report template (scaffolded house style, freely editable).

Optional `repro/manifest.json` (`admin_build`/`storefront_build`/`demodata`/`timeout_s`) tells the
trunk leg what to provision. Everything else in `repro/` is free-form.

`run.sh` speaks to the harness through **`##repro` output markers** (`blocked` overrides the exit
code; `observed`/`expected` become the comment's evidence; `step` narrates; `evidence` captions
files dropped in `$EVIDENCE_DIR`). See `markers.mjs` and `prompt/task.md`.

## Pipeline

```
issue → fetch-issue → resolve-version → provision(reported) → snapshot-db → context.md
      → AGENT (unsandboxed, full shell): repro init → author bundle → repro try → repro render → stop
      → post-steps: audit-files → run-bundle reported (immutable /tmp copy) → upload artifacts
      → reproduce-free-on-trunk (fresh runner, READ-ONLY token): provision(trunk) → run-bundle trunk
      → reproduce-free-report (fresh runner, write token, runs NO agent code):
            verdict → embed-evidence → render frame + agent template → post comment
```

The verdict matrix and blocked/needs-review precedence live in `report/verdict.mjs`; all frame
wording in `templates/verdicts.json`.

## Layout

- `lib.mjs` — bundle contract names, manifest defaults, shared helpers.
- `markers.mjs` — `##repro` protocol parser + leg classification (the whole "executor" layer).
- `run-bundle.mjs` — trusted leg runner: DB reset → run.sh → classify → result.json/run.log/evidence.
- `audit-files.mjs` — disclosure audit (changed files, shop-code edits).
- `cli/repro.mjs` — the agent's feedback tools: `init | try | render | reset | giveup`.
- `scaffold/` — the boilerplate bundle `repro init` copies (run.sh, comment.md, manifest.json).
- `report/` — `verdict.mjs`, `render-comment.mjs` (frame + placeholder resolution),
  `embed-evidence.sh` (evidence branch + `evidence.json` manifest).
- `templates/` — frame layouts + all harness copy.
- `prompt/task.md` — the agent playbook: the contract, the markers, and the honesty rules
  (no version-sniffing, setup failure ≠ bug, decide from observations, disclose everything).
- `steps/` — self-contained pre-agent bash glue: `compose-prompt.sh` (writes `context.md` +
  `run-context.json`) plus the provision helpers (`fetch-issue.sh`, `resolve-version.sh`,
  `register-legacy-alias.sh`, `finish-provision.sh`, `snapshot-db.sh`).
- `dev/compile.sh` — MAINTAINER tool: regenerate the committed lock from the gh-aw source.

This tree is standalone — it has no dependency on any other `.github/actions/` workflow tree.

## Changing the workflow

Edit `.github/workflows/reproduce-free.md`, then run
`bash .github/actions/reproduce-free/dev/compile.sh` (from the repo root) to regenerate the
committed `reproduce-free.lock.yml` (it also re-applies the P2/P3 lock patches gh-aw source can't
express). Never hand-edit the lock file.
