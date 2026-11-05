---
# gh aw SOURCE for the documentation-impact check on merged pull requests.
# Compile with `gh aw compile` -> produces sw-docs-impact.lock.yml (committed, never hand-edited).

on:
  # Started once per merged pull request by sw-docs-impact-dispatch.yml.
  workflow_dispatch:
    inputs:
      pr_number:
        description: "Merged pull request number to check"
        required: true
        type: number
      dry_run:
        description: "Write the comment to the run summary instead of posting it"
        required: false
        default: true
        type: boolean
  reaction: none

if: github.repository == 'shopware/shopware'

run-name: "Shopware Docs Impact #${{ github.event.inputs.pr_number }}"

concurrency:
  group: sw-docs-impact-${{ github.event.inputs.pr_number }}
  cancel-in-progress: false
  job-discriminator: ${{ github.event.inputs.pr_number }}

# Runs on the host before the agent. The checkout is trunk or a ref a maintainer
# dispatched, so the script is trusted.
steps:
  - name: Check out the dispatched ref
    uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
    with:
      ref: ${{ github.sha }}
      persist-credentials: false
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
  - name: Check out the developer documentation
    uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
    with:
      repository: shopware/docs
      path: .sw-docs-impact/docs
      persist-credentials: false
  - id: enduser_docs_token
    # End-user docs are optional input; the last pre-step warns when they are missing.
    continue-on-error: true
    uses: shopware/octo-sts-action@main
    with:
      scope: shopware/enduser-docs-sbp-sync
      identity: sw-docs-impact
  - name: Check out the end-user documentation
    id: enduser_docs_checkout
    if: steps.enduser_docs_token.outputs.token != ''
    # End-user docs are optional input; the last pre-step warns when they are missing.
    continue-on-error: true
    uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
    with:
      repository: shopware/enduser-docs-sbp-sync
      path: .sw-docs-impact-enduser-docs-src
      token: ${{ steps.enduser_docs_token.outputs.token }}
      persist-credentials: false
      sparse-checkout: content/en
      filter: blob:none
  - name: Index the end-user documentation
    if: steps.enduser_docs_checkout.outcome == 'success'
    # End-user docs are optional input; the last pre-step warns when they are missing.
    continue-on-error: true
    uses: actions/github-script@3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9
    with:
      script: |
        const { rmSync } = await import('node:fs')
        const { buildEndUserDocs } = await import('${{ github.workspace }}/.github/bin/js/docs-impact.ts')
        const src = '${{ github.workspace }}/.sw-docs-impact-enduser-docs-src'
        core.info(`Indexed ${buildEndUserDocs(`${src}/content/en`, '${{ github.workspace }}/.sw-docs-impact/enduser-docs')} end-user pages`)
        rmSync(src, { recursive: true, force: true })
  - name: Report missing end-user documentation
    uses: actions/github-script@3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9
    with:
      script: |
        const { warnIfEndUserDocsMissing } = await import('${{ github.workspace }}/.github/bin/js/docs-impact.ts')
        await warnIfEndUserDocsMissing(core, '${{ github.workspace }}/.sw-docs-impact/enduser-docs')

engine:
  id: claude
  version: 2.1.303
  model: claude-sonnet-5-5
  max-turns: 25
  env:
    ANTHROPIC_API_KEY: ${{ secrets.QUALITY_INITIATIVE_ANTHROPIC_API_KEY }}

permissions:
  contents: read
  id-token: write
  pull-requests: read
network: defaults
timeout-minutes: 15
max-ai-credits: 300

tools:
  github: false
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
    docs-candidate:
      description: "Report the merged pull request as a documentation candidate"
      runs-on: ubuntu-24.04
      if: needs.detection.result == 'success' && needs.detection.outputs.detection_success == 'true'
      output: "Docs candidate comment posted"
      permissions:
        contents: read
        pull-requests: write
      inputs:
        audiences:
          description: "Comma-separated audiences: end-user, developer"
          required: true
          type: string
        summary:
          description: "Markdown: per audience, which page to add, adjust or remove and why"
          required: true
          type: string
      steps:
        - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
          with:
            persist-credentials: false
            sparse-checkout: .github/bin/js
        - uses: actions/github-script@3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9
          env:
            SW_DOCS_IMPACT_REDACT: ${{ secrets.GH_AW_DEFAULT_OTLP_HEADERS }}
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

Read `.sw-docs-impact/pr.json` and `.sw-docs-impact/pr.diff`, check `.sw-docs-impact/docs`
and `.sw-docs-impact/enduser-docs`, decide per the policy above, and finish with exactly one `docs_candidate` or `noop` call.
