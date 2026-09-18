---
name: sw-screenshot
description: >
  Produce context screenshots for a Shopware issue or pull request. Decide whether it is about a
  visual change; if so, get a running shop into the described state and photograph it — before and
  after for a pull request. Use when the user asks what a bug looks like, wants visual context on an
  issue or PR, asks for a before/after of a UI change, or names an issue and wants to see it.
disable-model-invocation: true
license: MIT
allowed-tools: Bash(shot:*) Bash(gh issue view:*) Bash(gh pr view:*) Bash(gh pr diff:*) Bash(gh api repos/*:*) Bash(rg:*) Bash(find:*) Bash(ls:*) Bash(node:*) Bash(npx playwright:*) Read Glob Grep Write Edit
---

# Context screenshots

The rubric is `.github/aw/shared/sw-screenshot-policy.md`. Read it first, then the mode file for
what you were handed — `modes/issue.md` or `modes/pr.md`. This file covers only what is different
when you run interactively rather than in CI.

## What is different here

**The shop is the user's.** Nothing is provisioned for you. Use a local instance — the
`shopware-local-dev` skill maps domains to directories and explains how to repair one. It may have
no demo data; check before assuming a listing has products in it.

**There is no `context.md` or `mode.md`.** Fetch the issue or pull request yourself with `gh`, and
pick the mode from what it is.

**`shot swap` does not exist for you.** In CI it asks a host-side worker to move a shop the agent
cannot touch. Against a local checkout, switch refs the way you normally would, run
`bin/console database:migrate --all`, rebuild what the diff touched, and clear the cache.

**Nothing is published.** There is no `upload_asset`; save images to disk. Nothing is posted; the
user decides where the pictures go.

## What you hand back

For each image: its path and one line saying what it shows. If `shot diff` reported zero, say that,
with the count. The policy's "you do not explain" holds here too — no methodology, no diagnosis, no
verdict.

Then stop. Once the images exist you are done: no extra crops or viewports, no measuring elements,
no reading the source or the built assets to check whether the change arrived. The user asks if
they want more.

If it is not a visual change, say so in one sentence and take no screenshots.
