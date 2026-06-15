---
# gh aw SOURCE for Shopware issue triage (read-only).
# Compile with `gh aw compile` → produces triage.lock.yml (committed, never hand-edited).

on:
  workflow_dispatch:
    inputs:
      issue_number:
        description: "Issue number to triage"
        required: true
        type: number

run-name: "Shopware Issue Triage #${{ github.event.issue.number || github.event.inputs.issue_number }}"

concurrency:                 # explicit — workflow_dispatch default group cancels parallel runs (gh-aw #19467)
  group: triage-${{ github.event.inputs.issue_number }}
  cancel-in-progress: false

engine:
  id: claude
  model: claude-sonnet-4-6   # explicit pin (Sonnet was already the default, just no drift)
  max-turns: 15              # claude-only; bound the loop so Bash-denials don't burn turns.
  env:
    # The repo's ANTHROPIC_API_KEY secret is empty; the real Quality-Initiative key is in
    # QUALITY_INITIATIVE_ANTHROPIC_API_KEY. Map it into what the claude engine reads.
    ANTHROPIC_API_KEY: ${{ secrets.QUALITY_INITIATIVE_ANTHROPIC_API_KEY }}

permissions: read-all        # read-only agent; the only output is a run artifact
network: defaults
timeout-minutes: 8

tools:
  github:
    toolsets: [issues, labels, pull_requests]
    min-integrity: none   # triage must read issues from any contributor (not just 'approved')
  # Read-only shell for code investigation (affected_paths, recent fixes). Least-privilege:
  # git limited to inspection subcommands; no push/config/remote.
  bash: ["rg", "find", "git log", "git show", "git diff", "git blame"]

safe-outputs:
  upload-artifact:           # Option B: full TriageOutput JSON (richest contract, post-validated)
    max-uploads: 1
    max-size-bytes: 262144   # 256 KB — a sane TriageOutput is ~5 KB; default 100 MB
                             # is a wide exfil channel we don't need.
    retention-days: 7
    allowed-paths:
      - "triage-output.json"
  threat-detection:
    enabled: true
    prompt: |
      The triage output is a potential exfiltration channel. In ADDITION to the default
      checks, set secret_leak=true if any field contains:
        - a GitHub token (prefixes ghp_, gho_, ghu_, ghs_, ghr_, or github_pat_),
        - an Anthropic API key (sk-ant-...) or OpenAI API key (sk-...),
        - any long, high-entropy base64-like blob that could encode a credential or binary payload.
      A valid TriageOutput only ever contains dispositions, severities, labels, reasoning,
      evidence quotes, file paths, and issue/PR/commit references — never credentials,
      tokens, or binary blobs.

post-steps:
  - name: Write deterministic triage context
    if: always()
    shell: bash
    env:
      TRIAGE_ISSUE_NUMBER: ${{ github.event.inputs.issue_number }}
      TRIAGE_RUN_ID: ${{ github.run_id }}
    run: |
      mkdir -p "${RUNNER_TEMP}/triage-context"
      jq -n \
        --argjson issue_number "${TRIAGE_ISSUE_NUMBER}" \
        --argjson run_id "${TRIAGE_RUN_ID}" \
        '{
          issue_number: $issue_number,
          run_id: $run_id
        }' > "${RUNNER_TEMP}/triage-context/triage-context.json"

  - name: Upload deterministic triage context
    if: always()
    uses: actions/upload-artifact@v7
    with:
      name: triage-context
      path: ${{ runner.temp }}/triage-context/triage-context.json
      retention-days: 7
      if-no-files-found: error
---

# Shopware Issue Triage

{{#runtime-import .github/aw/triage-policy.md}}

---

## This run

Triage issue **#${{ github.event.inputs.issue_number }}** using the policy and references
above. Investigate read-only (no labels, comments, or writes). When done, write your
single `TriageOutput` JSON object to a file named `triage-output.json` in the workspace
root, then call the `upload_artifact` tool on that path. Emit ONLY the JSON to that file
— no surrounding prose, no markdown fence.
