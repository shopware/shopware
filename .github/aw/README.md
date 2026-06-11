# GitHub Agentic Workflows (`gh aw`)

This directory holds the **shared assets** for our [`gh aw`](https://github.com/githubnext/gh-aw)
workflows — policy fragments, the SHA-pinned actions lock, and the run-log archive. The
workflow **sources** live one level up at `.github/workflows/<name>.md` and are compiled
to `.lock.yml` siblings (committed, never hand-edited).

For the bigger picture (interactive Agent Skills, unattended twins, the dedup pattern,
the checklist for adding a new skill), see
[`coding-guidelines/core/agent-skills.md`](../../coding-guidelines/core/agent-skills.md).

## Layout

```
.github/
├── workflows/
│   ├── triage.md         # gh aw SOURCE (edit this)
│   └── triage.lock.yml   # compiled — `gh aw compile` regenerates it
└── aw/
    ├── README.md            # this file
    ├── triage-policy.md     # gh-aw-mode policy, runtime-imported by the workflow
    ├── shared/
    │   └── triage-policy.md # shared rubric, runtime-imported by the gh-aw fragment
    │                        # AND referenced by the interactive skill (single source)
    ├── actions-lock.json    # SHA pins for every action gh aw injects
    └── logs/                # gh aw run snapshots (gitignored — personal scratch)
```

## Current workflows

| Workflow | Trigger | Engine | Output |
|---|---|---|---|
| `triage` | `workflow_dispatch` (input: `issue_number`) | `claude` / `claude-sonnet-4-6` | `triage-output.json` via `upload-artifact` |

The triage agent is read-only — it has no write permissions and cannot label, comment, or close. Its only side effect is the artifact, which a downstream job (or a human) consumes.

## Editing a workflow

1. Edit the markdown source at `.github/workflows/<name>.md`.
2. If the policy changes, edit the corresponding fragment in `.github/aw/<name>-policy.md` or the shared rubric at `.github/aw/shared/<name>-policy.md`. The shared file is runtime-imported by both the interactive skill and the gh-aw fragment — single source of the rubric.
3. Compile:
   ```bash
   gh aw compile
   ```
   This regenerates the `.lock.yml`. It may also touch `.gitattributes` and `.github/dependabot.yml` (one-time reformat — re-runs are stable). Commit the compiled files together with the source.

## Pinning

- **`gh aw` itself** — install via `gh extension install github/gh-aw --pin v0.76.1`. v0.76.1 is the current "Latest" tag; v0.77.x are pre-release. gh aw ships 2–3 releases per day — verify against `gh release list --repo github/gh-aw` before bumping, and re-run `gh aw compile` to refresh the lock-file.
- **Engine model** — pinned to `claude-sonnet-4-6` in `triage.md` (`engine.model`). New workflows in this repo should use the same model unless there is a concrete reason to diverge.
- **Actions** — every action gh aw injects is SHA-pinned via `actions-lock.json`, managed by `gh aw compile`. Do not hand-edit.

## Secrets

The repo's `ANTHROPIC_API_KEY` secret is empty. The real key lives in `QUALITY_INITIATIVE_ANTHROPIC_API_KEY`. The workflow remaps it via `engine.env`:

```yaml
engine:
  id: claude
  env:
    ANTHROPIC_API_KEY: ${{ secrets.QUALITY_INITIATIVE_ANTHROPIC_API_KEY }}
```

That keeps the engine code path unchanged while sourcing from the correct secret.

## Running and inspecting

```bash
# Dispatch a workflow (workflow must be registered — see below)
gh aw run triage -f issue_number=17018

# Audit a run (token usage + cost)
gh aw audit <run-id>

# Tail the most recent run
gh workflow view triage --web
```

Runs are persisted under `.github/aw/logs/run-<id>/` after `gh aw audit` — useful for replay, regression diffing, and as snapshot evidence. The directory is gitignored; treat it as personal scratch, not shared state.

## Registration trick

GitHub Actions only exposes `workflow_dispatch` for workflows that have run at least once **or** exist on the default branch — a GitHub limitation, not a `gh aw` quirk. To register a workflow on a feature branch without merging it to trunk:

1. Add a temporary `push:` trigger scoped to the workflow files:
   ```yaml
   on:
     workflow_dispatch:
       inputs:
         issue_number: { description: ..., required: true, type: number }
     push:
       branches: [<feature-branch>]
       paths: [.github/workflows/<name>.md, .github/aw/<name>-policy.md]
   ```
2. Push once — GitHub registers the workflow.
3. Remove the `push:` trigger and recompile.

## Output processing

`gh aw` does **not** enforce user-defined output schemas — the `upload-artifact` safe-output just stores the file. We run our own post-processing:

- `.github/workflows/process-triage-result.yml` triggers on every triage `workflow_run` completion, downloads the staging artifact, and runs `.github/bin/js/validate-triage-output.ts` against the `triage-output.json` payload before applying deterministic issue updates.
- The validator enforces the field-level limits the agent had only as prompt hints (`reasoning` ≤ 2000 chars, `evidence_quotes[]` ≤ 500 chars × ≤ 5 entries) and scans for accidental or prompt-injection-induced secret leakage (GitHub PATs, Anthropic keys, long base64 blocks). It is TypeScript, run via Node's native type-stripping, no dependencies.
- The `TriageOutput` shape and field rules live in `.claude/skills/triage/assets/examples.md`; the validator is the machine-readable enforcement of those rules.

A failed validation appears as a red `Triage Result Processor` run — visible to the maintainer who dispatched the triage. The staging artifact is not deleted on failure (would need `actions: write`); the visibility of the failed check is the gate.
