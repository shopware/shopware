---
# gh aw SOURCE for the documentation-impact check on merged pull requests.
# Compile with `gh aw compile` -> produces sw-docs-impact.lock.yml (committed, never hand-edited).

on:
  # push, not pull_request closed: a merged fork pull request gets no secrets on pull_request.
  push:
    branches: [trunk]
    paths-ignore:
      - "tests/**"
      - "**/*.spec.js"
      - "**/*.spec.ts"
      - "src/Administration/Resources/app/administration/test/**"
      - "src/Storefront/Resources/app/storefront/test/**"
      - ".github/**"
      - ".agents/**"
      - ".agents-templates/**"
      - "coding-guidelines/**"
      - "adr/**"
      - "delivery-process/**"
      - "**/composer.lock"
      - "**/package-lock.json"
      - "phpstan-baseline.php"
  workflow_dispatch:
    inputs:
      pr_number:
        description: "Merged pull request number to check"
        required: true
        type: number
  # Trunk merges are pushed by the merge queue; the default role check rejects bots.
  bots: ["github-merge-queue[bot]"]
  reaction: none

if: github.repository == 'shopware/shopware'

run-name: "Shopware Docs Impact ${{ github.event.inputs.pr_number && format('#{0}', github.event.inputs.pr_number) || github.sha }}"

concurrency:
  group: sw-docs-impact-${{ github.event.inputs.pr_number || github.sha }}
  cancel-in-progress: false
  job-discriminator: ${{ github.event.inputs.pr_number || github.sha }}

# Runs on the host before the agent. The checkout is a trunk commit (push) or a ref a
# maintainer dispatched, so the script is trusted.
steps:
  - name: Collect the merged pull request
    uses: actions/github-script@3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9
    with:
      retries: 3
      script: |
        const { mkdirSync, rmSync } = await import('node:fs')
        const { collectAgentInput } = await import('${{ github.workspace }}/.github/bin/js/docs-impact.ts')
        const dir = '${{ github.workspace }}/.sw-docs-impact'
        rmSync(dir, { recursive: true, force: true })
        mkdirSync(dir)
        await collectAgentInput({ github, context, core }, dir)

engine:
  id: claude
  version: 2.1.303
  model: claude-sonnet-5-5
  max-turns: 25
  env:
    ANTHROPIC_API_KEY: ${{ secrets.QUALITY_INITIATIVE_ANTHROPIC_API_KEY }}

permissions:
  contents: read
  pull-requests: read
network: defaults
timeout-minutes: 15
max-ai-credits: 300

tools:
  github:
    toolsets: [repos]
  bash:
    - "cat"
    - "rg"
    - "find"
    - "ls"
    - "sed"
    - "wc"
    - "jq"

safe-outputs:
  noop:
    max: 1
    report-as-issue: false
  jobs:
    notify-docs-teams:
      description: "Notify the documentation teams that the merged pull request needs a documentation update"
      runs-on: ubuntu-24.04
      output: "Documentation teams notified"
      permissions:
        contents: read
        pull-requests: write
      inputs:
        audiences:
          description: "Comma-separated audiences: end-user, developer"
          required: true
          type: string
        summary:
          description: "Markdown: per audience, what changed and which page to add or adjust"
          required: true
          type: string
      steps:
        - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
          with:
            persist-credentials: false
            sparse-checkout: .github/bin/js
        - uses: actions/github-script@3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9
          with:
            retries: 3
            script: |
              const { readFileSync } = await import('node:fs')
              const { postComment } = await import('${{ github.workspace }}/.github/bin/js/docs-impact.ts')
              const agentOutput = JSON.parse(readFileSync(process.env.GH_AW_AGENT_OUTPUT, 'utf8'))
              await postComment({ github, context, core }, agentOutput)
  threat-detection:
    enabled: true
---

# Shopware Docs Impact

{{#runtime-import .github/aw/sw-docs-impact-policy.md}}

---

## This run

- Event: `${{ github.event_name }}`

Read `.sw-docs-impact/pr.json` and `.sw-docs-impact/pr.diff`, decide per the policy above,
and finish with exactly one `notify_docs_teams` or `noop` call.
