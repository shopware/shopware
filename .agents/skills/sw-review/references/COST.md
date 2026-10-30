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
- `maintainer`: `cheap`.
- `ux`: `balanced`.
- `security`: `balanced`.
- `architecture`: `balanced`.

Escalate `security` to `strong` for auth, input, deps, secrets, tenant boundaries, PII, CSRF, or raw output.

Escalate `architecture` to `strong` for migrations, public API, hot paths, destructive changes, DAL shape, or extension points.

Use `strong-required` only for unclear blocking risk or high-impact public/security changes.

Escalate `maintainer` to `balanced` when `platform-scope` or `bc-removal-before-major` is among its guides: scope and removal judgements are the costly misses.

## Guides

A guide is 30-60 lines, measured at 1.3-2.6k tokens (rule sentences are long). A worker reads every guide the router selected for its persona; there is no cap, because dropping a matching guide is the expensive mistake. A typical PR routes 3-5 guides to a persona (about 7-9k tokens, read once and cached); an admin plus PHP plus config PR can route 8 (about 15k tokens per worker), still well inside the run budget. When a worker's budget is tight, read guides in `matched_by` order (`always`/`signal` before `path` before `anchor`) and say in `summary` which ones were skipped.

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
