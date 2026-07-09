---
name: Reproduce Issue
description: >
  Turn a Shopware bug report into ONE verified reproduction. The agent only authors a bundle
  (reproduction-plan.json + optional fixtures.json + one test); deterministic steps then re-run that
  exact bundle on the reported version AND on trunk and post the verdict — the agent decides no
  outcome. Compile with `.github/actions/reproduce/dev/compile.sh` (emits the committed .lock.yml).

# /reproduce in an issue (body or comment), the ci:reproduce label, or manual dispatch. Collaborators only.
on:
  slash_command:
    name: reproduce
    events: [issues, issue_comment]
  label_command:
    name: ci:reproduce
    events: [issues]
  workflow_dispatch:
    inputs:
      issue_number:
        description: "Issue number to reproduce"
        required: false
        type: number
  roles: [admin, maintainer, write]

run-name: "Reproduce #${{ github.event.issue.number || inputs.issue_number }}"
concurrency:
  group: reproduce-${{ github.event.issue.number || inputs.issue_number }}
  cancel-in-progress: false

# The agent job is READ-ONLY. The public verdict comment is posted only by the deterministic
# trunk/report job, from trusted post-agent artifacts.
permissions:
  contents: read
  issues: read

network:
  allowed: [defaults, local, playwright]

engine:
  id: claude
  model: claude-sonnet-4-6

# Sandboxed agent. Validated by the reproduce-sandbox-probe workflow (green run 28927226644): the
# agent runs behind awf (network firewall + host-chroot), reaches the shop at host.docker.internal
# (see "Export shop coordinates" + the sales-channel-domain step below), and its bundle is handed
# back to the trusted post-steps via the workspace. Two things the probe proved are REQUIRED and are
# applied by dev/compile.sh [P1] (host port 8000 on awf --allow-host-ports) and the domain step.
strict: true

# Per-run AI-credit cap (~$20). The agent verifies its assumptions with cheap tools and stops; it
# does not run the pipeline, so this is headroom rather than a target.
max-ai-credits: 2000
timeout-minutes: 40

tools:
  timeout: 1800          # a `repro try` may build the Admin/Storefront (slow); let it finish synchronously
  edit:                  # author reproduction-plan.json + fixtures.json + the spec/test
  github: false          # issue context is prefetched to files
  playwright:
    mode: cli            # live browser exploration, like an interactive coding agent
  bash:
    - "repro:*"          # the reproduce CLI (validate | seed | check | try | giveup)
    - "playwright-cli:*"
    - "rg:*"
    - "find:*"
    - "sed:*"
    - "cat:*"
    - "ls:*"
    - "head:*"
    - "tail:*"
    - "grep:*"
    - "sort:*"
    - "wc:*"
    - "jq:*"
    - "pwd"

# --- Deterministic pre-agent setup: provision the reported version, expose it, snapshot the DB,
#     write the run context. The agent starts with a ready-to-use live shop. ---
steps:
  - name: Checkout
    uses: actions/checkout@de0fac2e4500dabe0009e67214ff5f5447ce83dd # v6.0.2
    with:
      persist-credentials: false

  - name: Fetch issue context
    env:
      ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
      GH_TOKEN: ${{ github.token }}
    run: bash .github/actions/reproduce/steps/fetch-issue.sh

  - name: Resolve reported version
    id: version
    env:
      ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
      GH_TOKEN: ${{ github.token }}
    run: bash .github/actions/reproduce/steps/resolve-version.sh

  - name: Register legacy 6.6 conflicts alias
    if: steps.version.outputs.legacy_conflicts_alias == 'true'
    run: bash .github/actions/reproduce/steps/register-legacy-alias.sh

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
    run: bash .github/actions/reproduce/steps/finish-provision.sh

  # Sandbox wall #3: register the agent's host.docker.internal URL as an additional storefront
  # domain, else the storefront 400s on the sandbox Host header. Additive — host-side legs keep
  # using the localhost domain. Port 8000 matches finish-provision.sh and compile.sh [P1].
  - name: Register sandbox host as a storefront sales-channel domain
    env:
      SHOP_DIR: shop
      SANDBOX_URL: http://host.docker.internal:8000
    run: bash .github/actions/reproduce/steps/register-sandbox-domain.sh

  - name: Snapshot clean DB
    run: bash .github/actions/reproduce/steps/snapshot-db.sh

  - name: Write run context
    env:
      ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
      VERSION: ${{ steps.version.outputs.is_trunk == 'true' && 'trunk' || steps.version.outputs.target_version }}
      APP_URL: ${{ steps.provision.outputs.app_url }}
    run: bash .github/actions/reproduce/steps/compose-prompt.sh

  # The sandboxed agent reaches the shop via host.docker.internal (localhost inside the awf sandbox
  # is NOT the runner host). The trusted post-agent reported-verify + trunk legs run host-side and
  # keep using the real localhost app_url from steps.provision.outputs. Both URLs resolve because the
  # step above registered host.docker.internal as an additional sales-channel domain.
  - name: Export shop coordinates
    run: |
      {
        echo "APP_URL=http://host.docker.internal:8000"
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

  # Immutable copy of the CLI + a `repro` shim on PATH. The agent's feedback tools and the trusted
  # post-step verifier both run from this copy; the agent can't edit it. The shim goes in
  # /usr/local/bin (not /tmp/reproduce-bin + GITHUB_PATH): GITHUB_PATH does NOT propagate into the awf
  # sandbox, but /usr/local/bin is on the sandbox PATH (confirmed by reproduce-sandbox-probe). /tmp is
  # bind-mounted read-only into the sandbox, so the shim's exec target resolves there.
  - name: Install reproduce CLI
    run: |
      set -euo pipefail
      rm -rf /tmp/reproduce
      cp -R .github/actions/reproduce /tmp/reproduce
      ln -s "$PWD/node_modules" /tmp/reproduce/node_modules
      chmod -R a-w /tmp/reproduce
      printf '#!/usr/bin/env bash\nexec node /tmp/reproduce/cli/repro.mjs "$@"\n' | sudo tee /usr/local/bin/repro >/dev/null
      sudo chmod +x /usr/local/bin/repro

pre-agent-steps:
  - name: Record pre-agent workspace baseline
    run: git status --porcelain > /tmp/repro-pre-status.txt

# --- Publish trusted post-agent outputs. The agent's `try` never writes result.json; the trusted
#     verify re-runs the reported leg from the IMMUTABLE /tmp CLI copy — so even if the agent touched
#     files in the workspace, the verdict is unaffected. Stray edits are recorded (not fatal) and
#     surfaced in the comment for humans to judge. ---
post-steps:
  - name: Audit workspace edits
    if: always()
    run: |
      set -euo pipefail
      git status --porcelain > /tmp/repro-post-status.txt
      new=$(comm -13 <(sort /tmp/repro-pre-status.txt) <(sort /tmp/repro-post-status.txt) || true)
      : > workspace-edits.txt
      while IFS= read -r line; do
        [ -n "$line" ] || continue
        path=${line:3}; path=${path#\"}; path=${path%\"}
        case "$path" in
          reproduction-plan.json|fixtures.json|repro.spec.ts|ReproTest.php|repro.sh|\
          result.json|builder-result.json|seed-error.txt|phpunit-output.txt|giveup.txt|\
          seeded-readiness.json|admin-state.json|context.md|issue-class.txt|agent-summary.md|workspace-edits.txt|\
          pw-*.txt|pw-*.json|.repro-*|.playwright-cli/*|.playwright-cli|\
          test-results/*|playwright-report/*|shop/*|node_modules/*|package.json|package-lock.json) ;;
          *) printf '%s\n' "$path" >> workspace-edits.txt ;;
        esac
      done <<< "$new"
      if [ -s workspace-edits.txt ]; then echo "::warning::agent changed files outside the bundle:"; cat workspace-edits.txt; else echo "no stray edits"; fi

  - name: Extract agent summary
    if: always()
    # Prefer the agent-authored agent-summary.md (see prompt/task.md); only scrape the gh-aw log as a
    # fallback when the agent didn't write one. Write via a temp file so we never truncate the
    # authored summary before the renderer can read it.
    run: |
      if [ ! -s agent-summary.md ]; then
        node .github/actions/reproduce/report/agent-summary.mjs /tmp/gh-aw/agent-stdio.log > agent-summary.md.tmp 2>/dev/null || true
        [ -s agent-summary.md.tmp ] && mv agent-summary.md.tmp agent-summary.md || rm -f agent-summary.md.tmp
      fi

  - name: Authoritative reported-version verification
    id: reported_verify
    if: always() && hashFiles('reproduction-plan.json') != ''
    continue-on-error: true
    env:
      REPRO_ALLOW_VERIFY: "1"
      TARGET: reported
      APP_URL: ${{ steps.provision.outputs.app_url }}
    run: |
      set -euo pipefail
      node /tmp/reproduce/cli/repro.mjs validate
      node /tmp/reproduce/cli/repro.mjs verify   # records video too when the plan sets record_video

  - name: Upload repro bundle
    if: always()
    uses: actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a # v7.0.1
    with:
      name: repro-plan
      path: |
        reproduction-plan.json
        fixtures.json
        repro.spec.ts
        ReproTest.php
        giveup.txt
        provision-error.txt
        agent-summary.md
        workspace-edits.txt
      if-no-files-found: ignore
      retention-days: 7

  - name: Upload reported leg
    if: always() && hashFiles('result.json') != ''
    uses: actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a # v7.0.1
    with:
      name: repro-reported
      path: |
        result.json
        seed-error.txt
        repro.sh
        repro.spec.ts
        ReproTest.php
        phpunit-output.txt
        video.webm
        test-results/
        playwright-report/
      if-no-files-found: ignore
      retention-days: 7

# --- Deterministic trunk re-run + verdict + comment on a FRESH runner. Compiled as a safe-output job
#     and lock-patched (dev/compile.sh) to run whenever the agent job ran — it reads the artifacts and
#     decides everything itself. ---
safe-outputs:
  # The agent is sandboxed again, so keep gh-aw threat detection on for the tiny safe-output request
  # that only asks the deterministic trunk job to inspect post-agent artifacts.
  threat-detection: true
  jobs:
    reproduce-on-trunk:
      description: >
        INTERNAL — do not call this tool. The compiled lock is patched so this job runs from the
        trusted post-agent artifacts, re-runs the bundle on trunk, and posts the verdict.
      runs-on: ubuntu-latest
      permissions:
        contents: write   # embed-evidence pushes screenshots to the evidence branch
        issues: write      # post the verdict comment
      output: "Trunk reproduction complete; verdict comment posted."
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
            name: repro-plan
        - name: Download reported leg
          continue-on-error: true
          uses: actions/download-artifact@3e5f45b2cfb9172054b4087a40e8e0b5a5461e7c # v8.0.1
          with:
            name: repro-reported
            path: artifacts/repro-reported

        - name: Detect bundle
          id: bundle
          run: |
            # A give-up is authoritative: if the agent recorded giveup.txt, treat the run as incomplete
            # even when a stale reproduction-plan.json / result.json linger from an earlier attempt.
            if [ -f giveup.txt ]; then
              has=false
            else
              has=$([ -f reproduction-plan.json ] && [ -f artifacts/repro-reported/result.json ] && echo true || echo false)
            fi
            reported_status=$(jq -r '.status // ""' artifacts/repro-reported/result.json 2>/dev/null || echo "")
            # Skip the trunk leg entirely when the reported leg is blocked: without a reported baseline
            # a trunk result is meaningless AND a full trunk provision would be wasted. That case posts
            # the clear "blocked" comment instead (with the authored bundle).
            run_trunk=$([ "$has" = true ] && [ "$reported_status" != blocked ] && echo true || echo false)
            { echo "has=$has"; echo "reported_status=$reported_status"; echo "run_trunk=$run_trunk"; } >> "$GITHUB_OUTPUT"
            echo "bundle=$has reported=$reported_status run_trunk=$run_trunk"

        # ---- No bundle → deterministic "incomplete" comment. ----
        - name: Render incomplete comment
          if: steps.bundle.outputs.has != 'true'
          env:
            MODE: incomplete
            REASON: ${{ needs.agent.result == 'success' && 'The agent did not produce a verified reproduction bundle.' || 'The agent run did not complete.' }}
            RUN_URL: ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}
          run: node .github/actions/reproduce/report/comment.mjs
        - name: Post incomplete comment
          if: steps.bundle.outputs.has != 'true'
          env:
            GH_TOKEN: ${{ github.token }}
            ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
          run: gh issue comment "$ISSUE" --repo "${{ github.repository }}" --body-file comment.md

        # ---- Bundle present → trunk re-run + verdict + comment. ----
        - name: Derive trunk build flags
          id: plan
          if: steps.bundle.outputs.run_trunk == 'true'
          run: |
            set -euo pipefail
            jq -r '"executor=\(.executor // "")"' reproduction-plan.json >> "$GITHUB_OUTPUT"
            jq -r '"admin_build=\(.build_profile.admin_build // false)"' reproduction-plan.json >> "$GITHUB_OUTPUT"
            jq -r '"storefront_build=\(.build_profile.storefront_build // false)"' reproduction-plan.json >> "$GITHUB_OUTPUT"
            jq -r '"demodata=\(.fixtures.demodata // false)"' reproduction-plan.json >> "$GITHUB_OUTPUT"

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
          run: bash .github/actions/reproduce/steps/finish-provision.sh

        - name: Setup Node + Playwright
          if: steps.bundle.outputs.run_trunk == 'true' && steps.plan.outputs.executor == 'playwright' && steps.provision.outcome == 'success'
          uses: actions/setup-node@48b55a011bda9f5d6aeb4c2d9c7362e8dae4041e # v6.4.0
          with:
            node-version: 22
        - name: Install Playwright
          if: steps.bundle.outputs.run_trunk == 'true' && steps.plan.outputs.executor == 'playwright' && steps.provision.outcome == 'success'
          run: |
            npm init -y >/dev/null
            npm i -D @playwright/test
            npx playwright install --with-deps chromium

        - name: Verify on trunk
          id: trunk_verify
          if: steps.bundle.outputs.run_trunk == 'true' && steps.provision.outcome == 'success'
          continue-on-error: true
          env:
            REPRO_ALLOW_VERIFY: "1"
            TARGET: trunk
            APP_URL: ${{ steps.provision.outputs.app_url }}
            SW_ACCESS_KEY: ${{ steps.provision.outputs.access_key }}
          run: node .github/actions/reproduce/cli/repro.mjs verify   # records video too when the plan sets record_video

        # Arrange the two legs + the plan the way verdict.mjs / comment.mjs expect.
        - name: Collect artifacts
          if: steps.bundle.outputs.run_trunk == 'true'
          run: |
            set -euo pipefail
            mkdir -p artifacts/repro-plan artifacts/repro-trunk
            cp reproduction-plan.json artifacts/repro-plan/ 2>/dev/null || true
            cp fixtures.json artifacts/repro-plan/ 2>/dev/null || true
            cp agent-summary.md workspace-edits.txt artifacts/repro-plan/ 2>/dev/null || true
            # No trunk result.json → synthesize a blocked leg via the single-sourced bundle contract
            # (bundle.mjs blockedResult). This covers both a failed trunk provision and a verify that
            # produced no result; the message stays neutral rather than claiming a specific cause.
            if [ -f result.json ]; then cp result.json artifacts/repro-trunk/; else
              node .github/actions/reproduce/cli/repro.mjs blocked-result trunk "trunk leg produced no result (provisioning or verification failed on trunk)" trunk
              cp result.json artifacts/repro-trunk/
            fi
            cp -r test-results playwright-report artifacts/repro-trunk/ 2>/dev/null || true
            cp video.webm artifacts/repro-trunk/ 2>/dev/null || true   # only present when record_video opted in

        - name: Compute verdict
          id: verdict
          if: steps.bundle.outputs.run_trunk == 'true'
          run: ART=artifacts node .github/actions/reproduce/report/verdict.mjs

        # Publish screenshots/recordings + write evidence.json BEFORE rendering, so comment.mjs can
        # place them in the Result spoilers.
        - name: Publish evidence
          if: steps.bundle.outputs.run_trunk == 'true' && steps.verdict.outputs.has_results == 'true' && steps.verdict.outputs.verdict != 'blocked'
          continue-on-error: true
          env:
            ART: artifacts
            BRANCH: ${{ vars.REPRO_EVIDENCE_BRANCH || 'ci/repro-evidence' }}
            REPO: ${{ github.repository }}
            RUN_ID: ${{ github.run_id }}
            TOKEN: ${{ github.token }}
          run: bash .github/actions/reproduce/report/embed-evidence.sh

        - name: Render comment
          if: steps.bundle.outputs.run_trunk == 'true' && steps.verdict.outputs.has_results == 'true'
          env:
            ART: artifacts
            VERDICT: ${{ steps.verdict.outputs.verdict }}
            UNSURE: ${{ steps.verdict.outputs.unsure_reason }}
            FIX: ${{ steps.verdict.outputs.fix_candidate }}
            RUN_URL: ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}
          run: node .github/actions/reproduce/report/comment.mjs

        - name: Post comment
          if: steps.bundle.outputs.run_trunk == 'true' && steps.verdict.outputs.has_results == 'true'
          env:
            GH_TOKEN: ${{ github.token }}
            ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
          run: gh issue comment "$ISSUE" --repo "${{ github.repository }}" --body-file comment.md

        # ---- Reported leg blocked → skip trunk (a trunk result is meaningless without a reported
        #      baseline) and post the clear blocked reason alongside the authored bundle. ----
        - name: Render blocked comment
          if: steps.bundle.outputs.has == 'true' && steps.bundle.outputs.reported_status == 'blocked'
          env:
            MODE: incomplete
            ART: artifacts
            RUN_URL: ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}
          run: |
            set -euo pipefail
            mkdir -p artifacts/repro-plan
            cp reproduction-plan.json fixtures.json repro.spec.ts ReproTest.php agent-summary.md workspace-edits.txt artifacts/repro-plan/ 2>/dev/null || true
            REASON="$(jq -r '.blocked_reason // "the reproduction could not be run on the reported version"' artifacts/repro-reported/result.json)" \
              node .github/actions/reproduce/report/comment.mjs
        - name: Post blocked comment
          if: steps.bundle.outputs.has == 'true' && steps.bundle.outputs.reported_status == 'blocked'
          env:
            GH_TOKEN: ${{ github.token }}
            ISSUE: ${{ github.event.issue.number || inputs.issue_number }}
          run: gh issue comment "$ISSUE" --repo "${{ github.repository }}" --body-file comment.md
---

# Reproduce a Shopware bug — produce ONE verified reproduction, then stop

A live shop on the **reported version** is already running (Admin + Storefront built). Your job is to
reproduce the reported bug on it and prove it — you do not parse the version, run the trunk
comparison, decide the verdict, or write the comment; deterministic scripts own all of that.

**Read `context.md` (workspace root) first, then follow the playbook in
`.github/actions/reproduce/prompt/task.md`.** Author only your own files (`reproduction-plan.json`,
optional `fixtures.json`, one test artifact), verify your assumptions with `repro seed` / `repro
check` / `playwright-cli`, and stop when you're confident. After you stop, the deterministic pipeline
re-runs your bundle on the reported version and on trunk. If you truly cannot reproduce it, run
`repro giveup "<reason>"`.
