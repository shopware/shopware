# Cost Rules

Use provider-neutral terms.

## Terms

- `orchestrator`: gathers input, gates personas, slices diffs, merges output.
- `worker`: reviews one persona slice.
- `adapter`: maps this skill to an agent runtime.
- `tier`: cost/capability class, not a model name.
- `review packet`: trusted PR metadata, files, stats, slices, commits, signals.
- `diff slice`: persona-relevant hunks plus needed file metadata.

## Tiers

- `cheap`: routing, style, simple docs, low-risk checks.
- `balanced`: default review work.
- `strong`: complex or high-risk reasoning.
- `strong-required`: blocking risk needs strong review or human fallback.

Adapters map tiers to local execution choices.

## Discovery

Do deterministic discovery before worker fanout:

- file list and stats.
- path classes: core, admin, storefront, tests, config/build, docs, generated/vendor.
- generated files, lockfiles, binary files.
- public API signals.
- UI signals.
- migration signals.
- dependency signals.

Optional cheap discovery worker:

- routes personas.
- summarizes risk signals.
- suggests context.
- emits no findings.

## Persona Tiers

- `code-style`: `cheap`.
- `open-source`: `cheap`.
- `ux`: `balanced`.
- `security`: `balanced`.
- `architecture`: `balanced`.

Escalate `security` to `strong` for auth, input, deps, secrets, tenant boundaries, PII, CSRF, or raw output.

Escalate `architecture` to `strong` for migrations, public API, hot paths, destructive changes, DAL shape, or extension points.

Use `strong-required` only for unclear blocking risk or high-impact public/security changes.

## Budgets

- Start from the assigned diff slice.
- Expand context only after a candidate finding exists.
- Prefer file/path references over repeated full text.
- Stop at the cap.
- If risk remains after the cap, set `needs_human_review`.

## Cache

Gather once:

- PR metadata.
- full diff and names-only diff.
- file list and stats.
- commits.
- path classification and risk signals.
- persona slices.

Workers receive slices or references, not repeated full context.
