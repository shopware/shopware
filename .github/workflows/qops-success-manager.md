---
# gh aw SOURCE for the QOps Success Manager nightly pipeline check + Slack notify.
# Compile with `gh aw compile` → produces qops-success-manager.lock.yml (committed, never hand-edited).
#
# v1 scope: Platform (shopware/shopware) only — same repo as this workflow,
# so the default GITHUB_TOKEN is enough. SaaS (shopware/saas) and PaaS
# (shopware-redstone/nightly-paas-ats) are NOT wired up yet — they need
# cross-repo read access via octo-sts (a ShopwareQopsSuccessManager.sts.yaml
# policy in shopware/.github, plus a counterpart in
# shopware-redstone/nightly-paas-ats for the cross-org leg), which hasn't
# been requested/reviewed yet. Validate this Platform-only version first,
# then extend once the policy exists — see the commented-out block below for
# what that extension looks like.
#
# The interactive skill (.agents/skills/qops-success-manager/SKILL.md) has no
# such restriction and already covers all 5 pipelines today — it runs with
# whoever invoked it's own `gh auth` session, not a repo-scoped token. This
# limitation is specific to the unattended/scheduled workflow below.
#
# Posts to a personal test Slack webhook for now
# (secrets.QOPS_SUCCESS_MANAGER_TEST_WEBHOOK_URL) — swap to the team channel's
# webhook once validated.

on:
  schedule:
    - cron: '0 4 * * *'   # ~06:00 Europe/Istanbul (UTC+2) — adjust if this timezone assumption is wrong
  workflow_dispatch: {}
  # TEMPORARY — registration trick (.github/aw/README.md) to expose
  # workflow_dispatch on this feature branch before merge. Remove before PR.
  push:
    branches: [feat/qops-success-manager-skill]
    paths: [.github/workflows/qops-success-manager.md]

run-name: "QOps Success Manager — nightly pipeline check"

concurrency:
  group: qops-success-manager
  cancel-in-progress: false

engine:
  id: claude
  model: claude-sonnet-4-6   # Sonnet family is the repo default; this task is mechanical comparison, no escalation needed
  max-turns: 30              # 20 wasn't enough even with the efficient get_job_logs(failed_only) path; some headroom for retries
  env:
    ANTHROPIC_API_KEY: ${{ secrets.QUALITY_INITIATIVE_ANTHROPIC_API_KEY }}

permissions: read-all        # read-only agent; only output is a run artifact + the Slack post below
network: defaults
timeout-minutes: 10

tools:
  github:
    # actions toolset: list workflow runs and read job logs (mirrors sw-nightly.md's
    # usage of the same toolset for reading nightly-run failure logs). Raw `gh run
    # list`/`gh run view` via bash is NOT available in this sandbox — GitHub API
    # reads go through this MCP toolset instead.
    toolsets: [actions]
    min-integrity: none

safe-outputs:
  upload-artifact:
    max-uploads: 1
    max-size-bytes: 65536     # 64 KB — a qops-success-manager summary is a few KB; default 100 MB is a wide exfil channel we don't need
    retention-days: 7
    allowed-paths:
      - "qops-success-manager-summary.json"
  threat-detection:
    enabled: true

post-steps:
  - name: Set up Node
    if: always()
    uses: actions/setup-node@820762786026740c76f36085b0efc47a31fe5020 # v7.0.0
    with:
      node-version: lts/*

  # TEMPORARY debug step — print the summary regardless of the Slack step's
  # outcome, so it's visible in the run without needing the webhook secret
  # or downloading artifacts. Remove once the webhook is validated end-to-end.
  - name: Print summary (debug)
    if: always()
    shell: bash
    run: |
      if [ -f qops-success-manager-summary.json ]; then
        echo "=== qops-success-manager-summary.json ==="
        cat qops-success-manager-summary.json
        {
          echo "### qops-success-manager-summary.json"
          echo '```json'
          cat qops-success-manager-summary.json
          echo '```'
        } >> "$GITHUB_STEP_SUMMARY"
      else
        echo "No qops-success-manager-summary.json file found in the working directory."
        echo "No qops-success-manager-summary.json file found in the working directory." >> "$GITHUB_STEP_SUMMARY"
      fi

  - name: Notify Slack (personal test webhook)
    if: always()   # notify regardless of whether the agent step succeeded, so a broken run is itself visible
    shell: bash
    env:
      SLACK_WEBHOOK_URL: ${{ secrets.QOPS_SUCCESS_MANAGER_TEST_WEBHOOK_URL }}
      RUN_URL: ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}
    run: node ./.github/bin/js/notify-qops-success-manager-slack.ts qops-success-manager-summary.json
---

# QOps Success Manager — nightly pipeline check

Same procedure as the `qops-success-manager` interactive skill
(`.agents/skills/qops-success-manager/SKILL.md`) — read it for the full
workflow, pipeline table, and Slack message style. Unattended-run specifics:

1. Run the check for **`platformTrunk` and `platform66x` only** (see the
   header comment above for why the other 3 pipelines aren't included yet —
   they need an octo-sts policy that doesn't exist yet).

   <!--
   Once the octo-sts policy exists, extend step 1 to also run `saas`,
   `saasNightly`, and `paas` from the skill's table, and update this
   comment + the header note above accordingly. Nothing else in this
   workflow needs to change — the summary format and Slack post are already
   pipeline-count-agnostic.
   -->

   **Tool efficiency — read this before fetching job data.** This workflow
   has no `bash` access to `gh`/`jq`; GitHub data comes through the
   `actions` MCP toolset instead. Only fetch job-level detail for a run
   whose overall conclusion is not `success` — a green run needs no further
   look. Two different tools cover the two cases below; do not mix them up
   or call both for the same run.

   **For the latest run of each pipeline (if it's not green):** call
   `list_workflow_jobs` with `per_page: 100` (its max). Platform nightly
   runs have 100+ jobs (150-ish is typical) but never more than 200, so at
   most 2 pages covers every job — fetch page 2 only if page 1 came back
   full (100 items). Then sort every job into exactly one bucket by its
   `conclusion`:
     - `success` — ignore entirely, don't mention it.
     - `skipped` — collect the names. Compare against the skipped set from
       the most recent prior run you have data for (a cheap byproduct of
       the same 7-day history lookup step 1 already does). If it's the
       same set as before, say nothing about it — a stable handful of
       conditionally-skipped jobs (e.g. release-prep steps that only run
       under certain conditions) is normal and not worth repeating every
       night. Only mention skipped jobs in the summary if the set changed
       (something new got skipped, or something previously skipped now
       isn't) — that's a real signal, a static baseline isn't.
     - anything else (`failure`, `cancelled`, `timed_out`,
       `action_required`, `stale`, `neutral`) — this is the "needs
       attention" bucket. Treat all of these as failures needing
       classification against the 7-day pattern; don't only look for the
       literal `failure` conclusion, a run marked red with zero `failure`-
       conclusion jobs usually means the real cause is a `cancelled` or
       `timed_out` job that a narrower filter would silently miss.

   **For every other run in the 7-day history window (i.e. not the
   latest):** don't use `list_workflow_jobs` here — call `get_job_logs`
   with `run_id` set and `failed_only: true` instead. It's a single call
   per run (no pagination available or needed) and returns the jobs with a
   literal `failure` conclusion, which is "good enough" for pattern-
   matching against what the latest run found — full precision on every
   historical day isn't worth the extra turns, precision on the latest run
   (handled above) is what actually goes in the report. If `get_job_logs`
   comes back empty for a run whose overall conclusion wasn't `success`,
   note that plainly ("red for an unspecified reason — no `failure`-
   conclusion job found") rather than treating the run as if it were
   clean — a real prior run showed exactly this happening in practice.
   Never fetch page 2+ of anything for these historical runs — one call
   per run, no exceptions, that's what keeps this affordable across a
   7-day window. State any of the limitations above plainly in the summary
   text when they're relevant, the way the interactive skill's step 5
   already asks for evidence to be visible rather than asserted.
2. Write the result to `qops-success-manager-summary.json` in the current
   working directory, with at minimum a `text` field: the message that
   gets posted to Slack as-is.

   **The `text` field must follow the skill's "Slack message style"
   section — specifically the "Automated nightly digest" shape, not the
   ad-hoc one.** Use its fixed opening verbatim (the greeting + "Here's a
   quick summary of the results:"), then exactly one short bullet per
   pipeline checked, in pipeline-table order. A failing pipeline's bullet
   names the known-recurring and new job(s) in one sentence and ends with
   that pipeline's own run URL inline; a green pipeline's bullet is a
   short clause with no link. Don't invent a different opening, don't put
   all findings in one paragraph, and don't put a single link at the end
   for the whole message — see the skill section for the exact template
   and a full example. The detailed per-job breakdown still belongs in the
   `pipelines` object above, not in `text` — `text` is only ever the
   greeting + one bullet per pipeline.
3. Do not take any write action beyond producing this file — no issue
   creation, no comments, no repo writes anywhere. This workflow is
   read-only end to end; the only side effect is the Slack post handled by
   the post-step above.
