---
name: qops-success-manager
description: >
  Check the QOps nightly pipelines — Platform (shopware/shopware trunk +
  6.6.x), SaaS (shopware/saas: chore-update-dependencies PR runs + Nightly),
  and PaaS (shopware-redstone/nightly-paas-ats main). Compare the latest run
  against the pattern from the last ~7 days at job level, and flag whether any
  failure is new/different or matches a known recurring issue. Use when the
  user asks to check the nightly pipeline status, run the QOps Success
  Manager check, asks whether last night's run differs from before, or wants
  a QOps-style nightly comparison summary.
license: MIT
allowed-tools: Bash(gh run list:*) Bash(gh run view:*) Read
---

# QOps Success Manager — nightly pipeline check

Covers the nightly-workflow-comparison part of the weekly QOps Success
Manager rotation duty (the full rotation also includes Slack triage and
patch/security coordination — those live in the rotation's own notes, out of
scope here).

## Pipelines

| Keyword | Area | Repo | Workflow | Branch/scope |
|---|---|---|---|---|
| `platformTrunk` | Platform trunk | `shopware/shopware` | `nightly.yml` | `trunk` — ignore "major"-named jobs (6.8-flagged, tracked separately) |
| `platform66x` | Platform 6.6.x | `shopware/shopware` | `nightly.yml` | `6.6.x` |
| `saas` | SaaS chore: update dependencies | `shopware/saas` | `ci.yml` | PR branch matching `update-trunk-*` — see caveat below |
| `saasNightly` | SaaS Nightly | `shopware/saas` | `nightly.yml` | scheduled, no branch filter needed |
| `paas` | PaaS main | `shopware-redstone/nightly-paas-ats` | `nightly-paas-ats.yml` | `main` |

Accepts a single keyword, several, or `all` (check every pipeline above).
Plain-language requests work too (e.g. "check the SaaS dependency-update
job") — the keyword table is a shortcut, not a requirement.

## Workflow

1. **Last ~7 days:** `gh run list --repo <repo> --workflow <workflow>
   [--branch <branch>] --limit 15 --json databaseId,conclusion,createdAt` —
   establish the pattern of failures over the last week.
   - **Cross-repo caveat (`saas` keyword specifically):** `--branch <branch>`
     filters by the run's *head* branch. The `chore: update dependencies` PR
     run lives on the bot's source branch (e.g. `update-trunk-xxxxx`), not on
     `trunk` — filtering by `trunk` only catches the post-merge run and can
     miss the actual failing check. Fetch without a branch filter, match by
     `headBranch` starting with `update-trunk-` and `event == "pull_request"`.
   - On Platform `trunk`, ignore any run/job with **"major"** in the name.
2. For every failing run in that window, get the job-level breakdown: `gh run
   view <run-id> --repo <repo> --json jobs -q '.jobs[] |
   select(.conclusion=="failure") | .name'`. The overall run conclusion alone
   is not enough — a run can be red for a job that's been failing for weeks
   while everything else, including what the user actually cares about, is
   green.
3. **Latest run:** check the same way and compare its failing job(s) (if any)
   against the pattern from step 2.
4. Classify each finding as:
   - **Known/recurring** — same job name(s) seen repeatedly in the last week.
   - **New/different** — a job failing that wasn't part of the recent pattern.
   - **Green** — no failure.
5. Summarize per pipeline: result + the specific evidence for the
   classification — *which* prior day(s)/job(s) it was compared against, not
   just a bare verdict (e.g. "NEW — not seen in the last 7 days; compared
   against `draft-release-notes` recurring 23-29 Jul and a one-off `mysql`
   job on 2 Aug, neither matches"). This is what makes a one-shot judgment
   call (no back-and-forth to correct a wrong guess) verifiable at a glance
   instead of requiring the reader to redo the comparison themselves.

## Slack message style

When drafting a message about a failure for `#product-operations` or
`#product-qops-nightly`, match the channels' real tone — short, low-fluff, not
a formal report:

- Casual greeting if any (`Hi team :wave:`), often skipped for pure FYI notes.
- State the problem in 1-2 sentences: what's failing, which job/workflow,
  since when.
- A direct link (PR, run, or workflow page) as evidence.
- If new/unusual, say so briefly in plain English ("This is a new failure,
  first observed last night") — don't list the whole week's comparison here,
  that belongs in the fuller summary above, not the Slack message.
- Short, polite ask to close ("could someone take a look?").
- No headers, no bullet-point reports, no signature.

## Notes

- Don't conflate "the run is red" with "there's a new problem" — most red
  runs turn out to be one long-standing recurring job dragging the overall
  conclusion down while everything else passes.
- Keep summaries factual — job names, dates, and comparison evidence, not
  speculation about root cause unless it's already established elsewhere.
