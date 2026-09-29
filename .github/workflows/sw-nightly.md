---
# gh aw SOURCE for Shopware nightly failure triage (read-only).
# Compile with `gh aw compile` → produces sw-nightly.lock.yml (committed, never hand-edited).
#
# Deep-triage pass over the auto-filed nightly PHPUnit tracking issues
# (report-phpunit-failures.yml): clusters the failing tests into root causes
# and routes each cluster to its owning domain. Unattended twin of the
# interactive `nightly-triage` skill.

on:
  workflow_dispatch:
    inputs:
      issue_number:
        # (github.event.issue.number); only a manual workflow_dispatch fills this input.
        description: "Nightly tracking issue number to triage (manual dispatch only)"
        required: false
        type: number
  slash_command:
    name: sw-nightly
    events: [issue_comment]
  label_command:
    name: qi/sw-nightly
    events: [issues]
    remove_label: false
  status-comment:
    issues: true
    pull-requests: false

if: >-
  github.event_name == 'workflow_dispatch' ||
  (
    github.event_name == 'issue_comment' &&
    github.event.issue.pull_request == null &&
    startsWith(github.event.comment.body, '/sw-nightly')
  ) ||
  (
    github.event_name == 'issues' &&
    github.event.action == 'labeled' &&
    github.event.label.name == 'qi/sw-nightly'
  )

run-name: "Shopware Nightly Triage #${{ github.event.issue.number || github.event.inputs.issue_number }}"

concurrency:                 # explicit — workflow_dispatch default group cancels parallel runs (gh-aw #19467)
  group: sw-nightly-${{ github.event.issue.number || github.event.inputs.issue_number }}
  cancel-in-progress: false

engine:
  id: claude
  model: claude-sonnet-4-6   # explicit pin (Sonnet family is the repo default)
  max-turns: 50              # claude-only hard cap; log reading is bounded per the policy's tool budget.
  env:
    # The repo's ANTHROPIC_API_KEY secret is empty; the real Quality-Initiative key is in
    # QUALITY_INITIATIVE_ANTHROPIC_API_KEY. Map it into what the claude engine reads.
    ANTHROPIC_API_KEY: ${{ secrets.QUALITY_INITIATIVE_ANTHROPIC_API_KEY }}

permissions: read-all        # read-only agent; the only output is a run artifact
network: defaults
timeout-minutes: 20          # wall-clock budget; max-turns (50) bounds runaway loops. Log fetching
                             # makes individual turns heavier than sw-triage's, hence 20 over 15.

tools:
  github:
    # actions toolset: read the linked nightly run's failed-job logs (get_job_logs).
    toolsets: [issues, labels, pull_requests, actions]
    min-integrity: none   # the tracking issues are bot-filed, but comments can come from anyone
  # Read-only shell for locating breaking changes (feature-flag blocks, migrations). Least-privilege:
  # git limited to inspection subcommands; no push/config/remote.
  bash: ["rg", "find", "git log", "git show", "git diff", "git blame"]

safe-outputs:
  upload-artifact:           # full NightlyTriageOutput JSON (richest contract, post-validated)
    max-uploads: 1
    max-size-bytes: 262144   # 256 KB — a sane NightlyTriageOutput is ~10 KB; default 100 MB
                             # is a wide exfil channel we don't need.
    retention-days: 7
    allowed-paths:
      - "nightly-triage-output.json"
  threat-detection:
    enabled: true
    prompt: |
      The triage output is a potential exfiltration channel. In ADDITION to the default
      checks, set secret_leak=true if any field contains:
        - a GitHub token (prefixes ghp_, gho_, ghu_, ghs_, ghr_, or github_pat_),
        - an Anthropic API key (sk-ant-...) or OpenAI API key (sk-...),
        - any long, high-entropy base64-like blob that could encode a credential or binary payload.
      A valid NightlyTriageOutput only ever contains a summary, error signatures, root-cause
      descriptions, confidence values, domain labels, test identifiers, evidence quotes, and
      issue/PR references — never credentials, tokens, or binary blobs.

post-steps:
  - name: Write deterministic nightly-triage context
    if: always()
    shell: bash
    env:
      NIGHTLY_ISSUE_NUMBER: ${{ github.event.issue.number || github.event.inputs.issue_number }}
      NIGHTLY_RUN_ID: ${{ github.run_id }}
    run: |
      mkdir -p "${RUNNER_TEMP}/nightly-context"
      jq -n \
        --argjson issue_number "${NIGHTLY_ISSUE_NUMBER}" \
        --argjson run_id "${NIGHTLY_RUN_ID}" \
        '{
          issue_number: $issue_number,
          run_id: $run_id
        }' > "${RUNNER_TEMP}/nightly-context/nightly-context.json"

  - name: Upload deterministic nightly-triage context
    if: always()
    uses: actions/upload-artifact@v7
    with:
      name: nightly-context
      path: ${{ runner.temp }}/nightly-context/nightly-context.json
      retention-days: 7
      if-no-files-found: error
---

# Shopware Nightly Failure Triage

{{#runtime-import .github/aw/sw-nightly-policy.md}}

---

## This run

Triage the nightly tracking issue **#${{ github.event.issue.number || github.event.inputs.issue_number }}**
using the policy and references above. Investigate read-only (no labels, comments, or writes).

Your single deliverable is a `NightlyTriageOutput` JSON object written to a file named
`nightly-triage-output.json` in the workspace root, then handed off by calling the
`upload_artifact` tool on that path. **This upload is the only thing that produces a
result — a run that is cut off by the turn limit before it calls `upload_artifact`
produces nothing.** So your job is to *finish*: investigate efficiently per the tool
budget in the policy, and **emit and upload well before the turn limit** rather than
spending every turn investigating. When you reach your emit deadline (or have enough to
cluster), write the complete JSON and call `upload_artifact` — once. A lower-confidence
result that ships beats a perfect one that never uploads. Emit ONLY the JSON to that
file — no surrounding prose, no markdown fence.
