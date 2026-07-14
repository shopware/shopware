---
name: Reproduce Issue (free)
description: >
  FREE VARIANT of the Reproduce workflow (A/B experiment against reproduce.md). The agent authors a
  fully free-form bundle — repro/run.sh (arbitrary prep + test, exit-code verdict contract) and
  repro/comment.md (its own report template) — and prompts, not schemas, guide honesty. Trust comes
  from the same differential design: deterministic steps re-run the exact bundle on the reported
  version AND on trunk, resolve the report placeholders from those trusted runs only, and call out
  every undisclosed file edit. Compile with `.github/actions/reproduce-free/dev/compile.sh` (emits the
  committed .lock.yml).

# /sw-reproduce-free in an issue (body or comment), the ci:reproduce-free label, or manual dispatch.
on:
  slash_command:
    name: sw-reproduce-free
    events: [issues, issue_comment]
  label_command:
    name: ci:reproduce-free
    events: [issues]
  workflow_dispatch:
    inputs:
      issue_number:
        description: "Issue number to reproduce"
        required: false
        type: number
  roles: [admin, maintainer, write]

run-name: "Reproduce (free) #${{ github.event.issue.number || inputs.issue_number }}"
concurrency:
  group: reproduce-free-${{ github.event.issue.number || inputs.issue_number }}
  cancel-in-progress: false

# The agent job is READ-ONLY. The public comment is posted only by the deterministic report job,
# from trusted post-agent artifacts.
permissions:
  contents: read
  issues: read

network:
  allowed: [defaults, local, playwright]

engine:
  id: claude
  model: claude-sonnet-4-6

# DELIBERATELY UNSANDBOXED (unlike reproduce.md): maximum freedom is the experiment. The agent works
# host-side with a full shell so it can rehearse run.sh exactly as the pipeline executes it —
# bin/console, mysql, plugin builds included. Integrity does not depend on the sandbox: the agent
# job holds a read-only token with no persisted credentials, and the verdict comes from fresh-runner
# re-runs of the bundle plus a disclosure audit of everything it changed.
strict: false
sandbox:
  agent: false
features:
  dangerously-disable-sandbox-agent: "Free-variant experiment: the agent needs host-side bin/console/mysql access to rehearse its bundle; integrity comes from the trusted re-runs, not the sandbox"

# Per-run AI-credit cap (~$5). Free exploration burns more than the strict variant; still headroom.
max-ai-credits: 500
timeout-minutes: 45

tools:
  timeout: 1800          # a `repro try` may build assets or install a plugin (slow); let it finish
  edit:                  # author repro/ (run.sh, comment.md, manifest.json, anything else)
  github: false          # issue context is prefetched to files
  playwright:
    mode: cli            # live browser exploration, like an interactive coding agent
  bash: true             # full shell — freedom is the point; honesty is enforced by re-runs + audits

# --- Deterministic pre-agent setup: provision the reported version, snapshot the DB, write the run
#     context. The agent starts with a ready-to-use live shop and a full shell. ---
steps:
  - name: Checkout
    uses: actions/checkout@de0fac2e4500dabe0009e67214ff5f5447ce83dd # v6.0.2
    with:
      persist-credentials: false

  - name: Fetch issue context
    env:
      ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
      GH_TOKEN: ${{ github.token }}
    run: bash .github/actions/reproduce-free/steps/fetch-issue.sh

  - name: Resolve reported version
    id: version
    env:
      ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
      GH_TOKEN: ${{ github.token }}
    run: bash .github/actions/reproduce-free/steps/resolve-version.sh

  - name: Register legacy 6.6 conflicts alias
    if: steps.version.outputs.legacy_conflicts_alias == 'true'
    run: bash .github/actions/reproduce-free/steps/register-legacy-alias.sh

  - name: Provision reported Shopware
    uses: shopware/setup-shopware@e12701e21d8a6003103426969ba544cdc91bf41c # v2.0.12
    with:
      shopware-version: ${{ steps.version.outputs.provision_version }}
      shopware-repository: shopware/shopware
      path: shop
      php-version: "8.4"
      composer-root-version: ${{ steps.version.outputs.composer_root_version }}
      mysql-version: "builtin"
      install: "true"
      install-admin: "true"
      install-storefront: "true"
      skip-js-build: "false"
      allow-insecure-versions: "true"
      env: prod

  - name: Finish provision
    id: provision
    env:
      SHOP_DIR: shop
      DEMODATA: "false"
    run: bash .github/actions/reproduce-free/steps/finish-provision.sh

  - name: Snapshot clean DB
    run: bash .github/actions/reproduce-free/steps/snapshot-db.sh

  - name: Write run context
    env:
      ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
      VERSION: ${{ steps.version.outputs.is_trunk == 'true' && 'trunk' || steps.version.outputs.target_version }}
      APP_URL: ${{ steps.provision.outputs.app_url }}
    run: bash .github/actions/reproduce-free/steps/compose-prompt.sh

  # Unsandboxed agent → plain localhost coordinates for exploration, rehearsal, and the trusted
  # reported leg alike.
  - name: Export shop coordinates
    run: |
      {
        echo "APP_URL=${{ steps.provision.outputs.app_url }}"
        echo "SW_ACCESS_KEY=${{ steps.provision.outputs.access_key }}"
        echo "ADMIN_USER=admin"
        echo "ADMIN_PASS=shopware"
      } >> "$GITHUB_ENV"

  - name: Setup Node + Playwright
    uses: actions/setup-node@48b55a011bda9f5d6aeb4c2d9c7362e8dae4041e # v6.4.0
    with:
      node-version: 22
  - name: Install Playwright
    run: |
      npm init -y >/dev/null
      npm i -D @playwright/test
      npx playwright install --with-deps chromium

  # Immutable copy of the free CLI + a `repro` shim on PATH. The agent's feedback tools and the
  # trusted post-step leg runner both execute from this copy; the agent can edit the workspace copy
  # all it wants — the verdict never runs from it.
  - name: Install reproduce CLI
    run: |
      set -euo pipefail
      rm -rf /tmp/reproduce-free
      cp -R .github/actions/reproduce-free /tmp/reproduce-free
      chmod -R a-w /tmp/reproduce-free
      printf '#!/usr/bin/env bash\nexec node /tmp/reproduce-free/cli/repro.mjs "$@"\n' | sudo tee /usr/local/bin/repro >/dev/null
      sudo chmod +x /usr/local/bin/repro

pre-agent-steps:
  - name: Record pre-agent workspace baseline
    run: |
      # -uall lists untracked files individually, matching audit-files.mjs — otherwise a new
      # directory collapses to one `dir/` entry and the per-file disclosure audit can't work.
      git status --porcelain -uall > /tmp/repro-pre-status.txt
      # The provisioned shop is a nested checkout the parent git status can't see. Baseline it too,
      # so the audit catches the agent patching shop/src or shop/custom — the trusted reported leg
      # runs on this very shop, so such edits mean the verdict needs a human.
      git -C shop status --porcelain -uall > /tmp/repro-pre-shop-status.txt 2>/dev/null || : > /tmp/repro-pre-shop-status.txt

# --- Publish trusted post-agent outputs. The agent's `try` is feedback only; the OFFICIAL reported
#     leg is executed here from the immutable /tmp copy on a clean DB. The disclosure audit records
#     everything the agent changed so the report can call out what its comment never mentions. ---
post-steps:
  - name: Audit agent file changes
    if: always()
    run: node /tmp/reproduce-free/audit-files.mjs

  - name: Authoritative reported-version run
    if: always() && hashFiles('repro/run.sh') != '' && hashFiles('giveup.txt') == ''
    continue-on-error: true
    env:
      REPRO_FREE_ALLOW_RUN: "1"
      APP_URL: ${{ steps.provision.outputs.app_url }}
      SHOP_DIR: shop
    run: node /tmp/reproduce-free/run-bundle.mjs reported

  - name: Upload repro bundle
    if: always()
    uses: actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a # v7.0.1
    with:
      name: repro-bundle
      path: |
        repro/
        run-context.json
        agent-summary.md
        files-changed.txt
        shop-src-edits.txt
        giveup.txt
      if-no-files-found: ignore
      retention-days: 7

  - name: Upload reported leg
    if: always() && hashFiles('result.json') != ''
    uses: actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a # v7.0.1
    with:
      name: repro-reported
      path: |
        result.json
        run.log
        evidence/
      if-no-files-found: ignore
      retention-days: 7

# --- Trunk re-run + verdict + comment, split across TWO fresh-runner safe-output jobs by TRUST
#     (same shape as reproduce.md):
#
#   reproduce-free-on-trunk  UNTRUSTED  contents:read   re-executes the agent-authored run.sh on a
#                                                       fresh trunk shop; uploads the leg as DATA.
#   reproduce-free-report    TRUSTED    contents:write  runs only deterministic report code over
#                                       issues:write    data artifacts; posts the comment + evidence.
#
# Both are lock-patched (dev/compile.sh [P2]) to run whenever the agent job ran, and [P3] wires the
# report job to `needs` the trunk job. ---
safe-outputs:
  # Threat detection requires the gh-aw sandbox, which this variant deliberately disables.
  threat-detection: false
  jobs:
    # -- UNTRUSTED trunk re-run. Read-only token on purpose: it executes agent-authored code, so it
    #    must hold nothing worth stealing. It only uploads the trunk leg for the report job. --
    reproduce-free-on-trunk:
      description: >
        INTERNAL — do not call this tool. Lock-patched to run whenever the agent job ran. Re-runs
        the authored bundle on trunk with a READ-ONLY token and uploads the trunk leg as data.
      runs-on: ubuntu-latest
      permissions:
        contents: read
      output: "Trunk reproduction complete; leg uploaded."
      env:
        FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: "true"
      steps:
        - name: Checkout
          uses: actions/checkout@de0fac2e4500dabe0009e67214ff5f5447ce83dd # v6.0.2
          with:
            persist-credentials: false

        - name: Download repro bundle
          continue-on-error: true
          uses: actions/download-artifact@3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c # v8.0.1
          with:
            name: repro-bundle
        - name: Download reported leg
          continue-on-error: true
          uses: actions/download-artifact@3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c # v8.0.1
          with:
            name: repro-reported
            path: artifacts/repro-reported

        - name: Detect bundle
          id: bundle
          run: |
            # A give-up is authoritative: if the agent recorded giveup.txt, treat the run as
            # incomplete even when bundle files linger from an earlier attempt.
            if [ -f giveup.txt ]; then
              has=false
            else
              has=$([ -f repro/run.sh ] && [ -f artifacts/repro-reported/result.json ] && echo true || echo false)
            fi
            reported_status=$(jq -r '.status // ""' artifacts/repro-reported/result.json 2>/dev/null || echo "")
            # Skip the trunk leg when the reported leg is blocked: without a reported baseline a
            # trunk result is meaningless AND a full trunk provision would be wasted.
            run_trunk=$([ "$has" = true ] && [ "$reported_status" != blocked ] && echo true || echo false)
            { echo "has=$has"; echo "reported_status=$reported_status"; echo "run_trunk=$run_trunk"; } >> "$GITHUB_OUTPUT"
            echo "bundle=$has reported=$reported_status run_trunk=$run_trunk"

        - name: Derive trunk build flags
          id: plan
          if: steps.bundle.outputs.run_trunk == 'true'
          run: |
            set -euo pipefail
            manifest=repro/manifest.json
            for key in admin_build storefront_build demodata; do
              value=$(jq -r ".${key} // false" "$manifest" 2>/dev/null || echo false)
              echo "${key}=${value}" >> "$GITHUB_OUTPUT"
            done

        - name: Provision trunk
          id: provision-setup
          if: steps.bundle.outputs.run_trunk == 'true'
          continue-on-error: true
          uses: shopware/setup-shopware@e12701e21d8a6003103426969ba544cdc91bf41c # v2.0.12
          with:
            shopware-version: trunk
            shopware-repository: shopware/shopware
            path: shop
            php-version: "8.4"
            composer-root-version: ".auto"
            mysql-version: "builtin"
            install: "true"
            install-admin: ${{ steps.plan.outputs.admin_build }}
            install-storefront: ${{ steps.plan.outputs.storefront_build }}
            skip-js-build: ${{ (steps.plan.outputs.admin_build == 'false' && steps.plan.outputs.storefront_build == 'false') && 'true' || 'false' }}
            allow-insecure-versions: "true"
            env: prod

        - name: Finish trunk provision
          id: provision
          if: steps.bundle.outputs.run_trunk == 'true'
          continue-on-error: true
          env:
            PREVIOUS_OUTCOME: ${{ steps.provision-setup.outcome }}
            SHOP_DIR: shop
            DEMODATA: ${{ steps.plan.outputs.demodata }}
          run: bash .github/actions/reproduce-free/steps/finish-provision.sh

        # The free bundle may drive a browser regardless of any declared "executor" (there is none),
        # so Playwright is always available on the trunk leg.
        - name: Setup Node + Playwright
          if: steps.bundle.outputs.run_trunk == 'true' && steps.provision.outcome == 'success'
          uses: actions/setup-node@48b55a011bda9f5d6aeb4c2d9c7362e8dae4041e # v6.4.0
          with:
            node-version: 22
        - name: Install Playwright
          if: steps.bundle.outputs.run_trunk == 'true' && steps.provision.outcome == 'success'
          run: |
            npm init -y >/dev/null
            npm i -D @playwright/test
            npx playwright install --with-deps chromium

        # UNTRUSTED: executes the agent-authored run.sh host-side. Read-only token; the trunk leg is
        # uploaded as data for the report job — this job renders no verdict and posts nothing.
        - name: Run bundle on trunk
          if: steps.bundle.outputs.run_trunk == 'true' && steps.provision.outcome == 'success'
          continue-on-error: true
          env:
            REPRO_FREE_ALLOW_RUN: "1"
            APP_URL: ${{ steps.provision.outputs.app_url }}
            SW_ACCESS_KEY: ${{ steps.provision.outputs.access_key }}
            ADMIN_USER: admin
            ADMIN_PASS: shopware
            SHOP_DIR: shop
          run: node .github/actions/reproduce-free/run-bundle.mjs trunk

        # Assemble the trunk leg as a data artifact. A missing result.json is synthesized into a
        # neutral blocked leg so a failed provision still hands the report a well-formed leg.
        - name: Assemble trunk leg
          if: steps.bundle.outputs.run_trunk == 'true'
          run: |
            set -euo pipefail
            mkdir -p artifacts/repro-trunk
            if [ ! -f result.json ]; then
              node .github/actions/reproduce-free/run-bundle.mjs blocked-result trunk \
                "trunk leg produced no result (provisioning or the run failed on trunk)"
            fi
            cp result.json artifacts/repro-trunk/
            cp run.log artifacts/repro-trunk/ 2>/dev/null || true
            cp -r evidence artifacts/repro-trunk/ 2>/dev/null || true

        - name: Upload trunk leg
          if: steps.bundle.outputs.run_trunk == 'true'
          uses: actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a # v7.0.1
          with:
            name: repro-trunk
            path: artifacts/repro-trunk
            if-no-files-found: ignore
            retention-days: 7

    # -- TRUSTED report. Holds the write token but runs ONLY deterministic report code over the DATA
    #    artifacts produced above — it never executes agent-authored code. --
    reproduce-free-report:
      description: >
        INTERNAL — do not call this tool. Consumes the bundle + leg artifacts, computes the
        deterministic verdict, publishes evidence, renders the frame around the agent's report
        template, and posts the issue comment. Runs no agent-authored code.
      runs-on: ubuntu-latest
      permissions:
        contents: write   # embed-evidence pushes files to the evidence branch
        issues: write      # post the comment
      output: "Verdict comment posted."
      env:
        FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: "true"
      steps:
        - name: Checkout
          uses: actions/checkout@de0fac2e4500dabe0009e67214ff5f5447ce83dd # v6.0.2
          with:
            persist-credentials: false

        - name: Download repro bundle
          continue-on-error: true
          uses: actions/download-artifact@3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c # v8.0.1
          with:
            name: repro-bundle
            path: artifacts/repro-bundle
        - name: Download reported leg
          continue-on-error: true
          uses: actions/download-artifact@3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c # v8.0.1
          with:
            name: repro-reported
            path: artifacts/repro-reported
        - name: Download trunk leg
          continue-on-error: true
          uses: actions/download-artifact@3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c # v8.0.1
          with:
            name: repro-trunk
            path: artifacts/repro-trunk

        - name: Detect bundle
          id: bundle
          run: |
            # Same detection as the trunk job, re-derived from the downloaded artifacts so the two
            # jobs stay decoupled.
            if [ -f artifacts/repro-bundle/giveup.txt ]; then
              has=false
            else
              has=$([ -f artifacts/repro-bundle/repro/run.sh ] && [ -f artifacts/repro-reported/result.json ] && echo true || echo false)
            fi
            echo "has=$has" >> "$GITHUB_OUTPUT"
            echo "bundle=$has"

        # ---- No bundle → deterministic "incomplete" comment. ----
        - name: Render incomplete comment
          if: steps.bundle.outputs.has != 'true'
          env:
            MODE: incomplete
            ART: artifacts
            REASON: ${{ needs.agent.result == 'success' && 'The agent did not produce a runnable reproduction bundle.' || 'The agent run did not complete.' }}
            RUN_URL: ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}
          run: node .github/actions/reproduce-free/report/render-comment.mjs
        - name: Post incomplete comment
          if: steps.bundle.outputs.has != 'true'
          env:
            GH_TOKEN: ${{ github.token }}
            ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
          run: gh issue comment "$ISSUE" --repo "${{ github.repository }}" --body-file comment.md

        # ---- Bundle present → verdict + comment. A missing trunk leg (blocked reported leg, or a
        #      trunk job that died early) reads as a null/blocked leg — verdict.mjs handles it. ----
        - name: Compute verdict
          id: verdict
          if: steps.bundle.outputs.has == 'true'
          run: ART=artifacts node .github/actions/reproduce-free/report/verdict.mjs

        # Publish each leg's evidence/ files + write evidence.json BEFORE rendering, so the renderer
        # can resolve {{evidence:…}} placeholders in the agent's report.
        - name: Publish evidence
          if: steps.bundle.outputs.has == 'true' && steps.verdict.outputs.verdict != 'blocked'
          continue-on-error: true
          env:
            ART: artifacts
            BRANCH: ${{ vars.REPRO_EVIDENCE_BRANCH || 'ci/repro-evidence' }}
            REPO: ${{ github.repository }}
            RUN_ID: ${{ github.run_id }}
            TOKEN: ${{ github.token }}
          run: bash .github/actions/reproduce-free/report/embed-evidence.sh

        - name: Render comment
          if: steps.bundle.outputs.has == 'true'
          env:
            ART: artifacts
            VERDICT: ${{ steps.verdict.outputs.verdict }}
            UNSURE: ${{ steps.verdict.outputs.unsure_reason }}
            RUN_URL: ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}
          run: node .github/actions/reproduce-free/report/render-comment.mjs

        - name: Post comment
          if: steps.bundle.outputs.has == 'true'
          env:
            GH_TOKEN: ${{ github.token }}
            ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
          run: gh issue comment "$ISSUE" --repo "${{ github.repository }}" --body-file comment.md
---

# Reproduce a Shopware bug — free-form, then stop

A live shop on the **reported version** is already running (Admin + Storefront built), and you have
a full shell. Your job is to reproduce the reported bug and prove it — you do not run the trunk
comparison, decide the verdict, or post the comment; deterministic scripts own all of that and
re-execute your exact bundle on both versions.

**Read `context.md` (workspace root) first, then follow the playbook in
`.github/actions/reproduce-free/prompt/task.md`.** Scaffold with `repro init`, author
`repro/run.sh` (exit 0 healthy / 1 bug / ≥2 setup failure — identical on both versions, never
version-sniffing) and `repro/comment.md` (your report; placeholders are filled from the trusted
runs). Rehearse with `repro try`, preview with `repro render`, write `agent-summary.md`, then stop.
If you truly cannot reproduce it, run `repro giveup "<reason>"`.
