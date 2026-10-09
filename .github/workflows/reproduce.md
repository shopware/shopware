---
name: Shopware Reproduce Issue
description: >
  Turn a Shopware bug report into ONE verified reproduction. The agent only authors a bundle
  (reproduction-plan.json + optional fixtures.json + one test); deterministic steps then re-run that
  exact bundle on the reported version AND on trunk and post the verdict — the agent decides no
  outcome. Compile with `.github/actions/reproduce/dev/compile.sh` (emits the committed .lock.yml).

# /sw-reproduce in an issue (body or comment), the ci:reproduce label, or manual dispatch. Collaborators only.
on:
  slash_command:
    name: sw-reproduce
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
  env:
    # The repo's ANTHROPIC_API_KEY secret is empty on upstream; the real Quality-Initiative key is in
    # QUALITY_INITIATIVE_ANTHROPIC_API_KEY. Map it into what the claude engine reads (matches sw-review /
    # sw-triage / sw-bugfixer). Without this the agent job fails on upstream with an empty key.
    ANTHROPIC_API_KEY: ${{ secrets.QUALITY_INITIATIVE_ANTHROPIC_API_KEY }}

# Sandboxed agent: it runs behind awf (network firewall + host-chroot), reaches the shop at
# host.docker.internal (see "Export shop coordinates" + the sales-channel-domain step below), and its
# bundle is handed back to the trusted post-steps via the workspace. Two things this requires are
# applied by dev/compile.sh [P1] (host port 8000 on awf --allow-host-ports) and the domain step.
strict: true

# Per-run AI-credit cap (~$5). The agent verifies its assumptions with cheap tools and stops; it
# does not run the pipeline, so this is headroom rather than a target (a run usually costs ~$1–2).
max-ai-credits: 500
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

  # Sandbox: register the agent's host.docker.internal URL as an additional storefront domain, else
  # the storefront 400s on the sandbox Host header. Additive — host-side legs keep
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
      # The agent is always sandboxed (strict: true), so it reaches the shop only at
      # host.docker.internal — context.md must show that, not the host-side localhost URL (which is
      # reserved for the trusted post-agent verify steps). Matches "Export shop coordinates" below.
      APP_URL: http://host.docker.internal:8000
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
  # sandbox, but /usr/local/bin is on the sandbox PATH. /tmp is
  # bind-mounted read-only into the sandbox, so the shim's exec target resolves there.
  - name: Install reproduce CLI
    run: |
      set -euo pipefail
      rm -rf /tmp/reproduce
      cp -R .github/actions/reproduce /tmp/reproduce
      ln -s "$PWD/node_modules" /tmp/reproduce/node_modules
      chmod -R a-w /tmp/reproduce
      printf '#!/usr/bin/env bash\nexec node --experimental-strip-types /tmp/reproduce/cli/repro.ts "$@"\n' | sudo tee /usr/local/bin/repro >/dev/null
      sudo chmod +x /usr/local/bin/repro

pre-agent-steps:
  - name: Record pre-agent workspace baseline
    run: |
      git status --porcelain > /tmp/repro-pre-status.txt
      # The provisioned shop is a nested checkout the parent git status can't see. Baseline its source
      # tree too, so a later diff catches the agent patching shop/src (e.g. working around an env issue
      # in core) — which makes the reproduction non-self-contained and must not pass as trusted.
      git -C shop status --porcelain > /tmp/repro-pre-shop-status.txt 2>/dev/null || : > /tmp/repro-pre-shop-status.txt

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
      # Edits inside the provisioned shop's own source (a nested checkout the parent status misses).
      # Only src/ counts: the direct executor legitimately writes its test into shop/tests and may
      # touch composer.json, but a change under shop/src means the agent patched Shopware's core to
      # get the leg to run — so the reported leg ran against a modified shop and the verdict is not
      # self-contained. Record it as an out-of-bundle edit so the comment flags the run as untrusted.
      if git -C shop rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        git -C shop status --porcelain > /tmp/repro-post-shop-status.txt
        shop_new=$(comm -13 <(sort /tmp/repro-pre-shop-status.txt) <(sort /tmp/repro-post-shop-status.txt) || true)
        while IFS= read -r line; do
          [ -n "$line" ] || continue
          path=${line:3}; path=${path#\"}; path=${path%\"}
          case "$path" in
            src/*) printf 'shop/%s\n' "$path" >> workspace-edits.txt ;;
          esac
        done <<< "$shop_new"
      fi
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

  # Sandbox the reported leg the same way as the trunk leg: the reported verify also re-executes the
  # agent-authored spec/test host-side (post-agent, read-only token). Read the executor from the plan
  # and arm the matching container (playwright image / PHP image + socat DB relay), add /etc/hosts so
  # host-side login/seed hit the SAME host.docker.internal origin the container uses, then DROP
  # container egress. continue-on-error so an arm hiccup degrades to a blocked leg, never a dead run.
  # host.docker.internal is already registered as a sales-channel domain by the pre-agent step.
  - name: Arm reported-leg sandbox
    if: always() && hashFiles('reproduction-plan.json') != ''
    continue-on-error: true
    run: |
      set -euo pipefail
      EXE=$(jq -r '.executor // ""' reproduction-plan.json 2>/dev/null || echo "")
      echo '127.0.0.1 host.docker.internal' | sudo tee -a /etc/hosts >/dev/null
      if [ "$EXE" = playwright ]; then
        PW_VER=$(node -p "require('@playwright/test/package.json').version")
        IMG="mcr.microsoft.com/playwright:v${PW_VER}-noble"
        docker pull "$IMG"
        { echo "REPRO_SANDBOX=1"; echo "REPRO_SANDBOX_PW_IMAGE=$IMG"; } >> "$GITHUB_ENV"
        sudo iptables -I DOCKER-USER -j DROP
      elif [ "$EXE" = direct ]; then
        docker build -t repro-php:local - < .github/actions/reproduce/dev/php-sandbox.Dockerfile
        { echo "REPRO_SANDBOX=1"; echo "REPRO_SANDBOX_PHP_IMAGE=repro-php:local"; } >> "$GITHUB_ENV"
        command -v socat >/dev/null || { sudo apt-get update -q && sudo apt-get install -y -q socat; }
        GW=$(ip -4 -o addr show docker0 | awk '{print $4}' | cut -d/ -f1)
        sudo socat "TCP-LISTEN:3306,bind=${GW},fork,reuseaddr" TCP:127.0.0.1:3306 &
        sleep 2
        sudo iptables -I DOCKER-USER -j DROP
      elif [ "$EXE" = http ]; then
        # http runs no agent-authored process, but the assertion `field` is a jq PROGRAM the host-side
        # leg evaluates. Run jq in an egress-locked image with no env passthrough so `env`/`$ENV`
        # cannot exfiltrate a runner secret into the verdict comment (see assertion-classifier.mjs).
        docker build -t repro-jq:local - < .github/actions/reproduce/dev/jq-sandbox.Dockerfile
        { echo "REPRO_SANDBOX=1"; echo "REPRO_SANDBOX_JQ_IMAGE=repro-jq:local"; } >> "$GITHUB_ENV"
      fi
      # Proof-of-arm: only reached if the taken branch fully succeeded (set -e), i.e. the container
      # image is present AND egress is dropped. executeBundle refuses to run a playwright/direct
      # trusted verify without this, so a swallowed arm failure fails CLOSED (blocked leg), never an
      # unsandboxed execution. (http sets it too but doesn't require it — jq falls back to a scrubbed
      # host env when unarmed, so no secret is exposed either way.)
      echo "REPRO_SANDBOX_ARMED=1" >> "$GITHUB_ENV"

  - name: Authoritative reported-version verification
    id: reported_verify
    if: always() && hashFiles('reproduction-plan.json') != ''
    continue-on-error: true
    # APP_URL is the ambient http://host.docker.internal:8000 (set by "Export shop coordinates"), which
    # host-side legs now resolve via the /etc/hosts entry the arm step adds; REPRO_SANDBOX + the image
    # come from the arm step's GITHUB_ENV. So playwright's host-side login mints cookies for the same
    # origin the sandboxed spec uses.
    env:
      REPRO_ALLOW_VERIFY: "1"
      TARGET: reported
      # Trusted resolved version (not the agent's plan.version) → stamped into result.json.
      REPRO_RESOLVED_VERSION: ${{ steps.version.outputs.is_trunk == 'true' && 'trunk' || steps.version.outputs.target_version }}
    run: |
      set -euo pipefail
      node --experimental-strip-types /tmp/reproduce/cli/repro.ts validate
      node --experimental-strip-types /tmp/reproduce/cli/repro.ts verify   # records video too when the plan sets record_video

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
        agent-summary.md
        workspace-edits.txt
        media/
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

# --- Trunk re-run + verdict + comment, split across TWO fresh-runner safe-output jobs by TRUST:
#
#   reproduce-on-trunk  UNTRUSTED  contents:read   re-runs the agent-authored bundle on trunk (the
#                                                  spec executes host-side, OUTSIDE the awf sandbox),
#                                                  then uploads the trunk leg as a DATA artifact.
#   reproduce-report    TRUSTED    contents:write  runs only deterministic report code over data
#                                  issues:write    artifacts; posts the comment + pushes evidence.
#
# Why the split: the trunk verify re-executes agent-authored code, and validate.mjs's static checks
# are a soft boundary (regex-bypassable; narration args are unscanned). Keeping that execution in a
# job with NO write token means a bypass has nothing to steal or plant against — the token lives only
# in reproduce-report, which never runs agent code (it reads result.json / screenshots / the plan as
# data). reproduce-report re-checkouts clean and consumes only declared artifacts, so the untrusted
# job cannot plant files for it either. Both jobs are lock-patched (dev/compile.sh) to run whenever
# the agent job ran, and [P3] wires reproduce-report to `needs` reproduce-on-trunk. ---
safe-outputs:
  # Threat detection stays on for the safe-output handoff that triggers the jobs below. reproduce is
  # the highest-risk of the QI workflows because the agent authors code that later executes, so add a
  # tailored prompt on top of the defaults.
  threat-detection:
    enabled: true
    prompt: |
      The agent's only output is a reproduction bundle (reproduction-plan.json + optional
      fixtures.json + one test file) that later executes as code on trusted runners. In addition to
      the default checks, flag: any attempt to reach hosts other than the provisioned shop; secrets
      or tokens embedded in the plan, fixtures, or test; and spec/fixture content whose purpose is
      exfiltration or reaching other systems rather than reproducing the reported symptom.
  jobs:
    # -- UNTRUSTED trunk re-run. Read-only token on purpose: it re-executes the agent-authored spec,
    #    so it must hold nothing worth stealing. It only uploads the trunk leg for reproduce-report. --
    reproduce-on-trunk:
      description: >
        INTERNAL — do not call this tool. Lock-patched to run whenever the agent job ran. Re-runs the
        authored bundle on trunk with a READ-ONLY token and uploads the trunk leg for reproduce-report.
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
            # a trunk result is meaningless AND a full trunk provision would be wasted. reproduce-report
            # posts the clear "blocked" comment instead (with the authored bundle).
            run_trunk=$([ "$has" = true ] && [ "$reported_status" != blocked ] && echo true || echo false)
            { echo "has=$has"; echo "reported_status=$reported_status"; echo "run_trunk=$run_trunk"; } >> "$GITHUB_OUTPUT"
            echo "bundle=$has reported=$reported_status run_trunk=$run_trunk"

        - name: Derive trunk build flags
          id: plan
          if: steps.bundle.outputs.run_trunk == 'true'
          run: |
            set -euo pipefail
            jq -r '"executor=\(.executor // "")"' reproduction-plan.json >> "$GITHUB_OUTPUT"
            jq -r '"admin_build=\(.build_profile.admin_build // false)"' reproduction-plan.json >> "$GITHUB_OUTPUT"
            jq -r '"storefront_build=\(.build_profile.storefront_build // false)"' reproduction-plan.json >> "$GITHUB_OUTPUT"

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
            # Do NOT generate demodata at provision time. `repro verify` (fullRun) generates it once
            # when plan.fixtures.demodata is set — the SAME place the reported leg does. Generating it
            # here too would give trunk demodata twice (provision + fullRun) and diverge from reported.
            DEMODATA: "false"
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

        # SANDBOX (playwright only): the agent-authored spec is the ONE untrusted thing here, and the
        # trusted verify runs it host-side — so run it in a Playwright container with NO internet.
        # Register host.docker.internal as a storefront domain + resolve it on the host (so host-side
        # seed/readiness/login create auth cookies for the SAME origin the container's baseURL uses),
        # pre-pull the image BEFORE egress is cut, then DROP all container egress: host-gateway traffic
        # (shop + DB) is delivered locally and bypasses the FORWARD/DOCKER-USER chain, so it stays
        # reachable while the internet does not. Mechanism validated on fork run 29320621690.
        - name: Arm playwright sandbox
          if: steps.bundle.outputs.run_trunk == 'true' && steps.plan.outputs.executor == 'playwright' && steps.provision.outcome == 'success'
          env:
            SHOP_DIR: shop
            SANDBOX_URL: http://host.docker.internal:8000
          run: |
            set -euo pipefail
            bash .github/actions/reproduce/steps/register-sandbox-domain.sh
            echo '127.0.0.1 host.docker.internal' | sudo tee -a /etc/hosts >/dev/null
            # Match the container image to the host-installed Playwright EXACTLY: the workspace
            # node_modules is bind-mounted into the container, so `npx playwright test` runs the host
            # package against the image's browsers — a mismatched tag ships the wrong browser build
            # ("Executable doesn't exist"). Deriving the tag keeps them in lockstep automatically.
            PW_VER=$(node -p "require('@playwright/test/package.json').version")
            IMG="mcr.microsoft.com/playwright:v${PW_VER}-noble"
            echo "REPRO_SANDBOX_PW_IMAGE=$IMG" >> "$GITHUB_ENV"
            docker pull "$IMG"                          # pull while the network is still open
            sudo iptables -I DOCKER-USER -j DROP        # container egress: host only, no internet
            echo "REPRO_SANDBOX_ARMED=1" >> "$GITHUB_ENV"   # proof-of-arm; verify refuses without it (fail closed)

        # SANDBOX (direct only): run the agent-authored PHPUnit test in an egress-locked PHP container.
        # Build the PHP image (kernel-boot extensions), then relay the docker0 gateway:3306 -> the host
        # MySQL: the builtin MySQL binds 127.0.0.1, so the container's DB traffic must arrive as a LOCAL
        # connection (existing root@localhost grant applies) — no MySQL reconfig. Then DROP container
        # internet. Validated on fork run 29324172458.
        - name: Arm direct sandbox
          if: steps.bundle.outputs.run_trunk == 'true' && steps.plan.outputs.executor == 'direct' && steps.provision.outcome == 'success'
          run: |
            set -euo pipefail
            docker build -t repro-php:local - < .github/actions/reproduce/dev/php-sandbox.Dockerfile
            echo "REPRO_SANDBOX_PHP_IMAGE=repro-php:local" >> "$GITHUB_ENV"
            command -v socat >/dev/null || { sudo apt-get update -q && sudo apt-get install -y -q socat; }
            GW=$(ip -4 -o addr show docker0 | awk '{print $4}' | cut -d/ -f1)
            sudo socat "TCP-LISTEN:3306,bind=${GW},fork,reuseaddr" TCP:127.0.0.1:3306 &  # relay survives to the verify step
            sleep 2
            sudo iptables -I DOCKER-USER -j DROP        # container egress: host only, no internet
            echo "REPRO_SANDBOX_ARMED=1" >> "$GITHUB_ENV"   # proof-of-arm; verify refuses without it (fail closed)

        # SANDBOX (http only): the leg stays host-side, but the assertion `field` is a jq PROGRAM.
        # Build a tiny jq image so the field is evaluated inside `--network none` with no env
        # passthrough — jq's env/$ENV cannot leak a runner secret into the verdict comment.
        - name: Arm http sandbox
          if: steps.bundle.outputs.run_trunk == 'true' && steps.plan.outputs.executor == 'http' && steps.provision.outcome == 'success'
          run: |
            set -euo pipefail
            docker build -t repro-jq:local - < .github/actions/reproduce/dev/jq-sandbox.Dockerfile
            echo "REPRO_SANDBOX_JQ_IMAGE=repro-jq:local" >> "$GITHUB_ENV"

        # UNTRUSTED: re-executes the agent-authored spec/test. playwright → egress-locked Playwright
        # container reaching the shop at host.docker.internal; direct → egress-locked PHP container
        # reaching the DB via the socat relay; http stays host-side but evaluates its jq field in an
        # egress-locked jq container (no code execution otherwise).
        # Read-only token; the trunk leg is uploaded as data for reproduce-report to judge.
        - name: Verify on trunk
          id: trunk_verify
          if: steps.bundle.outputs.run_trunk == 'true' && steps.provision.outcome == 'success'
          continue-on-error: true
          env:
            REPRO_ALLOW_VERIFY: "1"
            TARGET: trunk
            REPRO_RESOLVED_VERSION: trunk   # trusted version stamped into result.json (not the agent's plan.version)
            APP_URL: ${{ steps.plan.outputs.executor == 'playwright' && 'http://host.docker.internal:8000' || steps.provision.outputs.app_url }}
            SW_ACCESS_KEY: ${{ steps.provision.outputs.access_key }}
            REPRO_SANDBOX: ${{ (steps.plan.outputs.executor == 'playwright' || steps.plan.outputs.executor == 'direct' || steps.plan.outputs.executor == 'http') && '1' || '' }}
            # REPRO_SANDBOX_PW_IMAGE / REPRO_SANDBOX_PHP_IMAGE / REPRO_SANDBOX_JQ_IMAGE are exported by the matching "Arm … sandbox" step.
          run: node --experimental-strip-types .github/actions/reproduce/cli/repro.ts verify   # records video too when the plan sets record_video

        # Assemble the trunk leg (result.json + evidence) as a data artifact. A missing result.json is
        # synthesized into a neutral blocked leg via the single-sourced bundle contract, so a failed
        # trunk provision/verify still hands reproduce-report a well-formed leg to judge.
        - name: Assemble trunk leg
          if: steps.bundle.outputs.run_trunk == 'true'
          run: |
            set -euo pipefail
            mkdir -p artifacts/repro-trunk
            if [ -f result.json ]; then cp result.json artifacts/repro-trunk/; else
              node --experimental-strip-types .github/actions/reproduce/cli/repro.ts blocked-result trunk "trunk leg produced no result (provisioning or verification failed on trunk)" trunk
              cp result.json artifacts/repro-trunk/
            fi
            cp -r test-results playwright-report artifacts/repro-trunk/ 2>/dev/null || true
            cp video.webm artifacts/repro-trunk/ 2>/dev/null || true   # only present when record_video opted in

        - name: Upload trunk leg
          if: steps.bundle.outputs.run_trunk == 'true'
          uses: actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a # v7.0.1
          with:
            name: repro-trunk
            path: artifacts/repro-trunk
            if-no-files-found: ignore
            retention-days: 7

    # -- TRUSTED report. Holds the write token but runs ONLY deterministic report code over the DATA
    #    artifacts produced above (result.json, screenshots, the plan) — it never executes agent code,
    #    so the token has nothing to plant against. `needs` reproduce-on-trunk is wired by compile.sh [P3]. --
    reproduce-report:
      description: >
        INTERNAL — do not call this tool. Consumes the reported + trunk leg artifacts, computes the
        deterministic verdict, pushes evidence, and posts the issue comment. Runs no agent-authored code.
      runs-on: ubuntu-latest
      permissions:
        contents: write   # embed-evidence pushes screenshots to the evidence branch
        issues: write      # post the verdict comment
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
            name: repro-plan
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
            # Same detection as reproduce-on-trunk, re-derived from the downloaded artifacts so the two
            # jobs stay decoupled (no cross-job step outputs to thread).
            if [ -f giveup.txt ]; then
              has=false
            else
              has=$([ -f reproduction-plan.json ] && [ -f artifacts/repro-reported/result.json ] && echo true || echo false)
            fi
            reported_status=$(jq -r '.status // ""' artifacts/repro-reported/result.json 2>/dev/null || echo "")
            run_trunk=$([ "$has" = true ] && [ "$reported_status" != blocked ] && echo true || echo false)
            { echo "has=$has"; echo "reported_status=$reported_status"; echo "run_trunk=$run_trunk"; } >> "$GITHUB_OUTPUT"
            echo "bundle=$has reported=$reported_status run_trunk=$run_trunk"

        # Stage the authored bundle the way verdict.mjs / comment.mjs expect (artifacts/repro-plan).
        - name: Assemble plan artifacts
          run: |
            set -euo pipefail
            mkdir -p artifacts/repro-plan
            cp reproduction-plan.json fixtures.json repro.spec.ts ReproTest.php agent-summary.md workspace-edits.txt artifacts/repro-plan/ 2>/dev/null || true

        # If reproduce-on-trunk died before uploading a leg (e.g. an early provision crash), no
        # repro-trunk artifact exists. Synthesize a neutral blocked leg via the single-sourced bundle
        # contract so the verdict is a clear "trunk couldn't run" rather than a bare null matrix miss.
        - name: Ensure trunk leg
          if: steps.bundle.outputs.run_trunk == 'true' && hashFiles('artifacts/repro-trunk/result.json') == ''
          run: |
            set -euo pipefail
            mkdir -p artifacts/repro-trunk
            node --experimental-strip-types .github/actions/reproduce/cli/repro.ts blocked-result trunk "trunk leg produced no result (provisioning or verification failed on trunk)" trunk
            cp result.json artifacts/repro-trunk/

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

        # ---- Bundle present + trunk ran → verdict + comment. ----
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
            BRANCH: ci/repro-evidence   # hardcoded: a repo var here could redirect the force-push to any branch
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

        # ---- Reported leg blocked → no trunk leg was produced; post the clear blocked reason
        #      alongside the authored bundle. ----
        - name: Render blocked comment
          if: steps.bundle.outputs.has == 'true' && steps.bundle.outputs.reported_status == 'blocked'
          env:
            MODE: incomplete
            ART: artifacts
            RUN_URL: ${{ github.server_url }}/${{ github.repository }}/actions/runs/${{ github.run_id }}
          run: |
            set -euo pipefail
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

{{#runtime-import .github/aw/shared/reproduce-policy.md}}

A live shop on the **reported version** is already running (Admin + Storefront built). In this CI
mode you additionally do not parse the version, run the trunk comparison, decide the verdict, or
write the comment — deterministic scripts own all of that.

**Read `context.md` (workspace root) first, then follow the playbook in
`.github/actions/reproduce/prompt/task.md`.** Author only your own files (`reproduction-plan.json`,
optional `fixtures.json`, one test artifact), verify your assumptions with `repro seed` / `repro
check` / `playwright-cli`, and stop when you're confident. After you stop, the deterministic pipeline
re-runs your bundle on the reported version and on trunk. If you truly cannot reproduce it, run
`repro giveup "<reason>"`.
