# Reproduce Workflow Sandbox Follow-Up

## Current rollback

Sandboxed gh-aw agent execution is temporarily disabled in `.github/workflows/reproduce.md`.

The deterministic preflight passed on run `28107092854`, including:

- sandbox-visible storefront URL checks
- MCP bridge fixture tools
- static rejection of hardcoded local Playwright URLs
- minimal storefront Playwright execution

The real sandboxed reproduce workflow still did not produce a usable reported-leg artifact on run
`28107396157`. The agent job completed, but `reproduce_on_trunk` could not download
`repro-reported` and recorded a missing reproduction bundle instead of running the normal verdict
path.

## TODO before enabling sandbox again

- Inspect run `28107396157` agent artifacts and logs to find why authoritative reported-version
  verification skipped or failed to upload `repro-reported`.
- Add a preflight or post-agent assertion that fails clearly when the agent generated no
  reproduction bundle or no `repro-reported` artifact would be uploaded.
- Decide whether the failure is caused by sandbox filesystem/tool restrictions, safe-output
  handoff behavior, or an agent no-op bundle.
- Re-enable `strict: true` / sandboxed agent only after the real workflow produces the reported leg
  artifact and either a trusted verdict or the intended pipeline-failed issue comment.
