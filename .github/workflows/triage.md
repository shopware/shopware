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

concurrency:                 # explicit — workflow_dispatch default group cancels parallel runs (gh-aw #19467)
  group: triage-${{ github.event.inputs.issue_number }}
  cancel-in-progress: false

engine:
  id: claude
  model: claude-sonnet-4-6   # explicit pin (Sonnet was already the default, just no drift)
  max-turns: 15              # claude-only; bound the loop so Bash-denials don't burn turns
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
---

# Shopware Issue Triage

{{#runtime-import .github/aw/triage-policy.md}}

---

## This run

Triage issue **#${{ github.event.inputs.issue_number }}** using the policy and references
above. Investigate read-only (no labels, comments, or writes).

**Two-step output, in this order:**

1. **JSON artifact (for the validator):** write your single `TriageOutput`
   JSON object to a file named `triage-output.json` in the workspace root,
   then call the `upload_artifact` tool on that path. Emit ONLY the JSON to
   that file — no surrounding prose, no markdown fence.

2. **Human-readable summary on the run page:** also append a Markdown table
   to `$GITHUB_STEP_SUMMARY` so reviewers can read the decision directly on
   the Actions run page without downloading the artifact. Use this exact
   `cat`-with-heredoc pattern (substitute your real values; the env var is
   already set):

   ```bash
   cat >> "$GITHUB_STEP_SUMMARY" <<'EOF'
   ## Triage — Issue #<N>: <one-line defect description>

   | Field | Value |
   |---|---|
   | **Disposition** | `<disposition>` |
   | **Severity** | <severity> |
   | **Confidence** | 0.XX |
   | **Suggested labels** | `<label1>`, `<label2>` |
   | **Duplicate of** | #N (or "—") |
   | **Change size** | <change_size> |

   **Reasoning:** <reasoning>

   **Affected paths:** `<path1>`, `<path2>`

   **Related:** #N, PR #N, `<sha> commit subject`
   EOF
   ```

   The summary mirrors the JSON content — same disposition, same evidence,
   same reasoning — but rendered as a table. Keep it under ~30 lines.
