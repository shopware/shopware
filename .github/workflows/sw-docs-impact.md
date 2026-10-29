---
# gh aw SOURCE for the documentation-impact check on merged pull requests.
# Compile with `gh aw compile` -> produces sw-docs-impact.lock.yml (committed, never hand-edited).

on:
  # push, not pull_request closed: a merged fork pull request gets no secrets on pull_request.
  push:
    branches: [trunk]
    paths-ignore:
      - "tests/**"
      - "src/**/Test/**"
      - "**/*.spec.js"
      - "**/*.spec.ts"
      - "**/__snapshots__/**"
      - "**/__mocks__/**"
      - "src/Administration/Resources/app/administration/test/**"
      - "src/Storefront/Resources/app/storefront/test/**"
      - "src/Core/DevOps/StaticAnalyze/**"
      - "**/eslint-rules/**"
      - "**/eslint.config.*"
      - "**/stylelint.config.*"
      - "**/jest.config.*"
      - "**/vitest.config.*"
      - ".github/**"
      - ".agents/**"
      - ".agents-templates/**"
      - ".claude/**"
      - "**/AGENTS.md"
      - "**/CLAUDE.md"
      - "coding-guidelines/**"
      - "adr/**"
      - "changelog/**"
      - "delivery-process/**"
      - ".devcontainer/**"
      - ".idea/**"
      - ".run/**"
      - ".vscode/**"
      - "devenv.*"
      - ".envrc"
      - "**/composer.lock"
      - "**/package-lock.json"
      - "vendor-bin/**"
      - "phpstan*.neon*"
      - "phpstan-baseline.php"
      - "phpstan-paths.php"
      - "phpunit.xml.dist"
      - "phpbench.json"
      - "rector*"
      - ".php-cs-fixer.dist.php"
      - ".phpstorm.meta.php"
      - "composer-dependency-analyser.php"
      - ".bc-exclude.php"
      - "deprecation.ignore"
      - ".danger.php"
      - ".codeowners"
      - "codeowners.toml"
      - "sonar-project.properties"
      - "snippet-validation.json"
      - ".allowed-licenses"
      - ".git-blame-ignore-revs"
      - ".gitattributes"
      - ".gitignore"
      - ".editorconfig"
      - ".nvmrc"
      - ".yamlfmt"
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
  # Trunk merges are pushed by the merge queue; the default role check rejects bots.
  bots: ["github-merge-queue[bot]"]
  reaction: none
  steps:
    - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
      with:
        persist-credentials: false
        sparse-checkout: .github/bin/js
    - id: commit_type
      uses: actions/github-script@3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9
      env:
        COMMIT_MESSAGE: ${{ github.event.head_commit.message }}
      with:
        script: |
          const { shouldCheck } = await import('${{ github.workspace }}/.github/bin/js/docs-impact.ts')
          core.setOutput('check', String(shouldCheck(context.eventName, process.env.COMMIT_MESSAGE ?? '')))

jobs:
  pre-activation:
    outputs:
      check: ${{ steps.commit_type.outputs.check }}

if: github.repository == 'shopware/shopware' && needs.pre_activation.outputs.check == 'true'

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
  - name: Check out the developer documentation
    uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
    with:
      repository: shopware/docs
      path: .sw-docs-impact/docs
      persist-credentials: false
  - id: enduser_docs_token
    continue-on-error: true
    uses: shopware/octo-sts-action@main
    with:
      scope: shopware/enduser-docs-sbp-sync
      identity: sw-docs-impact
  - name: Check out the end-user documentation
    id: enduser_docs_checkout
    if: steps.enduser_docs_token.outputs.token != ''
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
    continue-on-error: true
    uses: actions/github-script@3a2844b7e9c422d3c10d287c895573f7108da1b3 # v9
    with:
      script: |
        const { rmSync } = await import('node:fs')
        const { buildEndUserDocs } = await import('${{ github.workspace }}/.github/bin/js/docs-impact.ts')
        const src = '${{ github.workspace }}/.sw-docs-impact-enduser-docs-src'
        core.info(`Indexed ${buildEndUserDocs(`${src}/content/en`, '${{ github.workspace }}/.sw-docs-impact/enduser-docs')} end-user pages`)
        rmSync(src, { recursive: true, force: true })

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
