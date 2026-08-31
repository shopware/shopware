---
name: nightly-triage
description: >
  Triage a failing Shopware nightly / multi-job PHPUnit CI run (e.g. integration-major).
  Extract every failing test from the run's job logs, resolve each to its owning domain
  via #[Package] markers, cluster failures by error signature into root causes, verify
  opaque clusters with a local Docker reproduction, then file one GitHub issue per
  domain — flat issues, no parent tracking issue — routing collateral failures to the
  root-cause owner. Use when the user links a failing Actions run or job, asks to "identify all
  failing tests" from a nightly, asks to triage integration-major / a red nightly,
  or wants CI failures grouped into per-team issues.
disable-model-invocation: true
license: MIT
allowed-tools: Bash(gh run view:*) Bash(gh run list:*) Bash(gh issue view:*) Bash(gh issue list:*) Bash(rg:*) Bash(grep:*) Bash(git log:*) Bash(git show:*) Bash(python3:*) Bash(docker compose exec:*) Read Glob Grep
---

# Nightly CI-Failure Triage

Turn a red multi-job PHPUnit CI run into a small set of root-cause-grouped,
correctly-routed GitHub issues. Built from the 2026-07-03 `integration-major`
sweep (412 failing tests → 6 domain issues, ~9 root causes). Issues are flat:
one per domain, deliberately no parent tracking issue — the parent/sub-issue
split confused readers about where to look and comment.

## Core principles

Single-sourced in **`.github/aw/shared/sw-nightly-policy.md`**, shared with
the unattended twin (`.github/workflows/sw-nightly.md`, triggered by the
`qi/sw-nightly` label or `/sw-nightly` comment on a nightly tracking issue)
so the two modes cannot drift: cluster before you route, root-cause owner
wins over test-file owner, confirmed ≠ plausible, check the catalogue before
declaring anything new — plus trust boundaries, tool budget, and
anti-reward-hacking rules. Read that file first.

Interactive-only addition: **verify opaque clusters locally before filing.**
`--log-failed` truncates nested errors (`WriteException: There are N error(s)`
hides the detail). Reproduce in Docker before asserting a cause — see
`references/REPRODUCTION.md` for the exact recipe and its traps. Sample the
*actual failing variant* — an assertion-failure sibling of a WriteException
test can pass locally and have a different (schema-dependent) cause.

## Workflow

**Step 0 — Dedupe against previous nightlies.** `gh issue list --search
"[nightly]" --state open`. If tracking issues from a previous run exist,
UPDATE those (comment with the new run, adjust test lists) instead of filing
a new set. Only file fresh issues for a first-of-its-kind run.

Scheduled runs also file/update automated flat issues
(`[nightly] Nightly (Major) test failures: <domain>`, via
`.github/workflows/report-phpunit-failures.yml`): one domain-labeled issue
per domain, grouped by `#[Package]` (PHPUnit) or `@sw-package` (Jest) marker only, with
no clustering and no routing overrides applied. Use them as the starting
inventory for Steps 1–3 and UPDATE those issues (comment, re-route,
close duplicates) instead of filing a parallel set.

**Step 1 — Inventory the run.** `gh run view <run-id> --json jobs` → failing
job IDs and names. The job/shard names are the "job area" axis.

**Step 2 — Extract failing tests per shard.** Download each failing job's
`--log-failed`, extract `N) Shopware\Tests\...::method` entries, dedupe.
Exact commands and regexes: `references/PIPELINE.md`.

**Step 3 — Resolve each test to a domain.** `#[Package]` marker on the test
file → mirrored `src/` file → dominant package of the mirrored dir → path
heuristic. Apply the routing overrides FIRST. Mapping and overrides:
`references/ROUTING.md`.

**Step 4 — Cluster by error signature.** Capture each test's first meaningful
error line; normalize (hex IDs, numbers); group. Check clusters against the
known-cause catalogue and known flaky patterns in `references/ROUTING.md`
before treating anything as new.

**Step 5 — Reproduce what's opaque.** For each cluster whose mechanism the log
doesn't prove, run one member test locally per `references/REPRODUCTION.md`.
Runtime-flag repro first (cheap); full major DB only if it passes with flags
(schema-dependent cause).

**Step 6 — Draft, approve, file.** One issue per domain × job area, flat —
no parent tracking issue. Draft bodies to the scratchpad and get explicit user
approval before any `gh issue create`/`edit` (issue writes are never
pre-authorized). Templates and required sections: `references/PIPELINE.md`
§ Issue generation.

**Step 7 — Restore the local env** if Step 5 touched the test DB
(`references/REPRODUCTION.md` § Restore) and verify with a probe test.

## Deliverable

- N flat domain issues (or updates to an existing set),
  every failing test attributed exactly once, totals reconciled.
- A closing summary: cluster table, what was reproduced vs. mechanism-TBD,
  what was deliberately not moved and why.
