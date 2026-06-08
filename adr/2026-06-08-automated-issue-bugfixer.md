---
title: Automated issue bugfixer
date: 2026-06-08
area: workflow
tags: [automation, ai, github-actions]
---

## Context

We want selected GitHub issues to trigger an automated bug-fixing agent that prepares a branch and opens a pull request. The first version needs to prove the fix loop before adding a second-stage watcher for failed checks and review feedback.

## Decision

We will keep the bugfixer in `shopware/shopware` under `tools/bugfixer` as a self-contained Flue project, triggered by the `qi:fix` issue label and by manual `workflow_dispatch`. The workflow runs against a shallow `trunk` checkout, uses a GitHub App installation token for `gh` and `git`, defaults to the Codex OAuth model `openai-codex/gpt-5.5` through `CODEX_AUTH_JSON`, allows model overrides through `FLUE_MODEL`, creates branches named `bugfixer/issue-<number>-<short-slug>`, and opens normal PRs for confident fixes or draft PRs when confidence, reproduction, or validation is incomplete.

## Consequences

The trigger, target checkout, and PR permissions stay in the repository they affect, and the agent can use existing `AGENTS.md` guidance. The tradeoff is that Shopware now contains a small Node-based automation project, but keeping it in `tools/bugfixer` avoids turning the repository root into a Flue project and leaves room to extend the payload shape for additional repositories later.
