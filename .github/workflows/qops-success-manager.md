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
        echo "### qops-success-manager-summary.json" >> "$GITHUB_STEP_SUMMARY"
        echo '```json' >> "$GITHUB_STEP_SUMMARY"
        cat qops-success-manager-summary.json >> "$GITHUB_STEP_SUMMARY"
        echo '```' >> "$GITHUB_STEP_SUMMARY"
      else
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
   `actions` MCP toolset instead, and its `list_workflow_jobs` method has no
   failed-only filter — paginating through every job of a run (Platform
   nightly runs have 100+ matrix jobs) burns turns fast and can blow the
   turn budget before writing any output. Do NOT call `list_workflow_jobs`
   for this. Instead, for each run you need the failing job names for, call
   `get_job_logs` with `run_id` set and `failed_only: true` — it returns
   only the failed jobs directly, in one call per run, which is what the
   skill's workflow step 2 needs.
2. Write the result to `qops-success-manager-summary.json` in the current
   working directory, with at minimum a `text` field: a short, Slack-ready
   plain-text summary covering every pipeline checked (green/known/new
   classification, one line each, with the evidence the skill's workflow
   step 5 requires). Keep it concise — this posts directly to Slack.
3. Do not take any write action beyond producing this file — no issue
   creation, no comments, no repo writes anywhere. This workflow is
   read-only end to end; the only side effect is the Slack post handled by
   the post-step above.
