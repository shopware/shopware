---
name: Reproduce Sandbox Probe
description: >
  TEMPORARY infra probe for re-enabling the gh-aw sandbox on the Reproduce workflow. A cheap,
  narrowly-scoped agent (Sonnet, one allowlisted command) runs a single script INSIDE the sandbox that
  measures every host contact point the reproduce agent depends on — CLI resolution, shop
  reachability, browsers, workspace handoff, firewall — so we learn which walls are real BEFORE
  flipping the sandbox on for reproduce.md. A RED run is the deliverable: each failed check is a
  wall to clear. Delete once reproduce.md runs sandboxed and green. See
  .github/actions/reproduce/dev/sandbox-handoff.md §4a. Compile with `gh aw compile`.

# Manual dispatch only — no issue/comment triggers. Collaborators only.
on:
  workflow_dispatch:
    inputs:
      shopware_version:
        description: "Version to provision (tag like v6.7.0.0, or trunk)"
        required: false
        default: trunk
        type: string
  roles: [admin, maintainer, write]

run-name: "Reproduce Sandbox Probe"
concurrency:
  group: reproduce-sandbox-probe
  cancel-in-progress: true

permissions:
  contents: read

# Mirror the END-STATE reproduce.md sandbox config (what it will ship with), so the probe measures
# the real environment. When reproduce.md's sandbox/network/strict config changes, change it here in
# the SAME commit and diff the two locks' awf invocations to prove parity.
network:
  allowed: [defaults, local, playwright]

engine:
  id: claude
  # NOTE: Haiku was the goal (cheapest tier) but does NOT work here. gh-aw does not inline the
  # workflow body into the agent prompt — it delivers it only as a `{{#runtime-import <this file>}}`
  # directive that the harness never expands, so the agent must READ the referenced workflow file to
  # discover its task (the sibling reproduce.md relies on the same behavior). Haiku lacks the
  # initiative: runs 28923300796 / 28923804213 had it reply "I don't see a specific task" and stop
  # (~5.7 credits, no script run). Sonnet — like the Opus that accidentally ran in 28922794633 —
  # reads the file and runs the script reliably, at a fraction of Opus's cost. See sandbox-handoff §4a.
  model: claude-sonnet-4-6
  max-turns: 6              # hard cap — read the file, run one command, report; anything more is a loop

strict: true

# The agent reads one file + runs one script. Headroom so preamble never 403s mid-probe.
max-ai-credits: 200
timeout-minutes: 30

# ONE allowlisted command so there is nothing for a cheap model to wander into. No edit tools — the
# script writes its own report. Playwright is enabled so gh-aw provisions playwright-cli exactly as
# reproduce.md will, letting the script probe browser availability in-container (wall #4).
tools:
  timeout: 600
  github: false
  playwright:
    mode: cli
  bash:
    - "bash .github/actions/reproduce/dev/sandbox-probe.sh"

# --- Deterministic pre-agent setup (host): provision a shop just like reproduce.md, stand up an
#     allowed-port host-access canary, export coordinates, install browsers + the immutable CLI copy.
#     The agent then runs one script inside the sandbox against all of it. ---
steps:
  - name: Checkout
    uses: actions/checkout@de0fac2e4500dabe0009e67214ff5f5447ce83dd # v6.0.2
    with:
      persist-credentials: false

  - name: Provision Shopware
    uses: shopware/setup-shopware@e12701e21d8a6003103426969ba544cdc91bf41c # v2.0.12
    with:
      shopware-version: ${{ inputs.shopware_version || 'trunk' }}
      shopware-repository: shopware/shopware
      path: shop
      php-version: "8.4"
      composer-root-version: ".auto"
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

  # NOTE: we deliberately do NOT forward the shop onto an "allowed" host port here. Of awf's default
  # allow-host-ports (80,443,8080), 8080 is claimed by gh-aw's own MCP Gateway (binding it collides
  # and kills the agent step), and 80/443 need privilege/TLS. So there is no free allowed port for
  # the shop without a custom allow-host-ports entry — which is itself a wall-#2 finding. The probe
  # therefore reports the shop's raw port (8000) as firewall-blocked and gates wall #3 until the port
  # question is solved in Phase 1 (see dev/sandbox-handoff.md §3.2).

  # Agent-facing coordinates. APP_URL is the INTENDED sandbox-facing URL (host.docker.internal);
  # SW_ACCESS_KEY backs the store-api Host-header check (wall #3) once the shop is reachable.
  - name: Export shop coordinates
    env:
      GH_AW_ACCESS_KEY: ${{ steps.provision.outputs.access_key }}
    run: |
      {
        echo "APP_URL=http://host.docker.internal:8000"
        echo "SW_ACCESS_KEY=$GH_AW_ACCESS_KEY"
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

  # Immutable host copy of the CLI + a `repro` shim on PATH — same as reproduce.md, so the probe can
  # report whether either is visible inside the sandbox (wall #1).
  - name: Install reproduce CLI
    run: |
      set -euo pipefail
      rm -rf /tmp/reproduce
      cp -R .github/actions/reproduce /tmp/reproduce
      ln -s "$PWD/node_modules" /tmp/reproduce/node_modules
      chmod -R a-w /tmp/reproduce
      mkdir -p /tmp/reproduce-bin
      printf '#!/usr/bin/env bash\nexec node /tmp/reproduce/cli/repro.mjs "$@"\n' > /tmp/reproduce-bin/repro
      chmod +x /tmp/reproduce-bin/repro
      echo "/tmp/reproduce-bin" >> "$GITHUB_PATH"

# --- Host-side verdict (outside the sandbox). Render the probe report into the job summary and go
#     RED while any wall remains. The workspace report arriving here IS the wall-#7 test; a missing
#     report falls back to the agent log and is flagged as a broken handoff. ---
post-steps:
  - name: Render probe results + verdict
    if: always()
    env:
      AGENT_LOG: /tmp/gh-aw/agent-stdio.log
    run: bash .github/actions/reproduce/dev/sandbox-probe-report.sh

  - name: Upload probe artifacts
    if: always()
    uses: actions/upload-artifact@043fb46d1a93c77aae656e7c1c64a875d1fc6a0a # v7.0.1
    with:
      name: sandbox-probe
      path: |
        sandbox-probe-report.json
        sandbox-probe-report.fallback.json
      if-no-files-found: ignore
      retention-days: 7
---

# YOUR ONLY TASK — run one command right now

This is NOT a general Shopware development session. **Ignore** the repository development
instructions, coding guidelines, and skills in your context — they do not apply here. You have
exactly one job, and this message IS your explicit task.

**Your very first action must be to run this exact Bash command** (do not read, list, or explore any
files first, do not ask what to do):

```
bash .github/actions/reproduce/dev/sandbox-probe.sh
```

The script always exits 0 and writes its own report. Do **not** retry it, do **not** investigate its
output, do **not** run anything else, and do **not** write any files yourself. After it finishes,
reply with the single line `PROBE COMPLETE` and stop.
