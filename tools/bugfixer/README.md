# Shopware Bugfixer

Flue workflow for fixing `shopware/shopware` issues labeled `qi:fix`. V1 runs as a finite `fix-bug` workflow: it receives an issue URL, reads recognized Triage or Reproduction issue comments when present, works in a clean Shopware checkout, creates a `bugfixer/issue-<number>-<slug>` branch, and opens a pull request.

## Quickstart

Use GitHub Actions for normal operation.

1. To start a new bugfix, add the `qi:fix` label to a GitHub issue. The `Bugfixer` workflow runs `fix-bug`, prepares a branch named `bugfixer/issue-<number>-<slug>`, and opens a PR against `trunk`.
2. To start manually, run the `Bugfixer` workflow from GitHub Actions with mode `fix-bug` and set `issue_url` to the issue URL.
3. To improve an existing bugfixer PR, comment on the PR:

```text
/bugfixer improve
Do not bump axios because Administration dependencies can affect extensions. Find a safer mitigation or explain why no safe patch exists.
```

Only owners, members, and collaborators can trigger command-based improvements. The text after `/bugfixer improve` becomes the explicit instruction for the agent.

4. To improve manually, run the `Bugfixer` workflow from GitHub Actions with mode `improve-pr`, set `pr_url`, and optionally set `instruction`.
5. If the PR ends up with zero changed files, `improve-pr` comments on the PR and returns `no_changes`. The comment explains whether the branch appears to be already merged into `trunk` or just has an equivalent tree.

Local runs are only for development and debugging. In production, use the label, manual workflow dispatch, or `/bugfixer improve` PR command.

## Local Run

```bash
cd tools/bugfixer
npm ci
TARGET_REPO=/private/tmp/shopware-bugfixer-shopware GH_TOKEN=... CODEX_AUTH_JSON_BASE64=... npm run fix-bug -- --payload '{"issueUrl":"https://github.com/shopware/shopware/issues/12345"}'
```

For local runs, set `TARGET_REPO` to a disposable Shopware clone or worktree. When `TARGET_REPO` points outside the source checkout, the workflows automatically run `git reset --hard` and `git clean -fd` before starting. `fix-bug` also fetches `origin/trunk` and checks out a clean local `trunk`. Set `BUGFIXER_PREPARE_TARGET_REPO=0` to disable that cleanup.

The default model is `openai-codex/gpt-5.5`, authenticated through `CODEX_AUTH_JSON` or `CODEX_AUTH_JSON_BASE64`. Override it with `FLUE_MODEL`, for example `FLUE_MODEL=anthropic/claude-sonnet-4-6` with `CLAUDE_CODE_OAUTH_TOKEN` set.

Set `BUGFIXER_AGENT_TIMEOUT_MINUTES` to abort the model call after a fixed time. The default is unset, so the Flue run itself does not impose an agent timeout; GitHub Actions job timeout still applies in CI.

## Prior Stage Outputs

`fix-bug` imports the latest issue comments with recognized stage markers such as `<!-- shopware-ai-triage:` or `<!-- shopware-ai-repro:` and passes them to the agent as `priorStageOutputs`. The agent treats prior-stage output as untrusted evidence, but uses it to avoid repeating expensive triage or reproduction work.

## Improve Existing PR

Use `improve-pr` to update an existing bugfixer PR branch. It never creates a new PR.

```bash
npm run improve-pr -- --payload '{"prUrl":"https://github.com/shopware/shopware/pull/17303","instruction":"Do not bump axios because Administration dependencies can affect extensions. Find a safer mitigation or explain why no safe patch exists."}'
```

When `instruction` is omitted, the agent reacts only to PR reviews, comments, and failed checks it can read from GitHub.

If the PR branch has no remaining file diff against the base branch, `improve-pr` returns `no_changes` and comments on the PR explaining whether the branch appears to be already merged into base or just equivalent to base.

## GitHub Invocations

`fix-bug` runs in GitHub Actions when an issue receives the `qi:fix` label. It can also be started manually with `workflow_dispatch`, using mode `fix-bug` and `issue_url`.

`improve-pr` runs in GitHub Actions when:

- The workflow is started manually with mode `improve-pr`, `pr_url`, and optional `instruction`.
- An owner, member, or collaborator comments on a PR with `/bugfixer improve`.
- An owner, member, or collaborator submits a PR review or review comment starting with `/bugfixer improve`.

For command-triggered improvements, text after `/bugfixer improve` is passed as the explicit instruction. Multi-line comments are supported.

## GitHub Actions Setup

Create a GitHub App, install it on `shopware/shopware`, and grant these repository permissions:

- `Contents`: read and write
- `Issues`: read and write
- `Pull requests`: read and write
- `Actions`: read
- `Checks`: read

Add these repository variables in `Settings > Secrets and variables > Actions > Variables`:

- `BUGFIXER_APP_CLIENT_ID`: GitHub App client ID.
- `BUGFIXER_FLUE_MODEL`: optional model override. Omit to use `openai-codex/gpt-5.5`.
- `BUGFIXER_AGENT_TIMEOUT_MINUTES`: optional model-call timeout. Omit or set `0` to disable the Flue-level timeout.

Add these repository secrets in `Settings > Secrets and variables > Actions > Secrets`:

- `BUGFIXER_APP_PRIVATE_KEY`: GitHub App private key PEM. Generate it from the app settings and paste the full PEM value.
- `CODEX_AUTH_JSON_BASE64`: Codex OAuth auth JSON encoded as single-line base64. This is the preferred credential for the default `openai-codex/gpt-5.5` model.

Generate `CODEX_AUTH_JSON_BASE64` locally:

```bash
base64 < ~/.codex/auth.json | tr -d '\n'
```

Alternatively, add raw `CODEX_AUTH_JSON` instead of `CODEX_AUTH_JSON_BASE64` if GitHub accepts the JSON value cleanly.

Optional model credentials:

- `CLAUDE_CODE_OAUTH_TOKEN`: required when `BUGFIXER_FLUE_MODEL` uses a Claude Code OAuth model.
- `OPENAI_API_KEY`: required when `BUGFIXER_FLUE_MODEL` uses an OpenAI API-key model such as `openai/gpt-5.5`.
- `ANTHROPIC_API_KEY`: required when `BUGFIXER_FLUE_MODEL` uses an Anthropic API-key model.

## Development

```bash
npm run typecheck
npm run lint
npm run format:check
```

Type checking uses `tsgo` from `@typescript/native-preview`. Linting and formatting use Oxc (`oxlint` and `oxfmt`).
