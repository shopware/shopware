---
title: Shared Guidance Files for Humans and Agents
date: 2026-06-17
area: process
tags: [documentation, agents, developer-experience, coding-guidelines, adr]
status: accepted
---

## Context

Shopware has several consumers for repository guidance: human contributors, Codex, Claude, Gemini, and other agent tools.
Keeping separate guidance for every audience causes duplication and drift, but routing every folder through mechanical agent files also creates noise and weakens confidence that referenced files are actually loaded.

We need a documentation model that:

- keeps always-needed agent instructions visible,
- keeps reusable coding rules in one durable place,
- avoids duplicate agent-specific guidance and README-only stubs,
- keeps task-specific rules out of the always-loaded root context,
- keeps local setup, tool preferences, Docker setup, and approval rules out of tracked project documentation.

## Decision

1. Root `AGENTS.md` stays concise and carries only repository-wide context, main subtree routing, and mandatory linting guidance.
2. Main subtree `AGENTS.md` files are allowed when they hold real subtree rules or route to substantial existing guidance.
3. Do not add mechanical `AGENTS.md` or `GEMINI.md` stubs just to point at README files.
   **Exception:** every tracked `AGENTS.md` has a sibling `CLAUDE.md` whose only body is `@AGENTS.md`, so Claude Code loads the same guidance without duplicating rules.
4. Task-specific guidance lives in Agent Skills under `.agents/skills/`, where it loads only when the task asks for it.
5. Reusable normative rules belong in `coding-guidelines/`.
6. Folder-specific human guidance may live in an existing README when contributors naturally read that README for the work.
7. ADRs capture durable decisions, trade-offs, and consequences; they should not become living checklists.
8. Local-only agent mechanics stay in untracked override files such as `AGENTS.override.md`.

## Skill Location

Shopware-specific skills live in this repository under `.agents/skills/`.
They are branch-local guidance tied to `AGENTS.md`, `coding-guidelines/`, ADRs, PR conventions, and the platform code they describe. Keeping them here makes guidance changes reviewable with the related platform change and avoids a separate install or sync step for agents working from this checkout.

Claude Code discovers project skills from `.claude/skills/`, so that path is a Git-tracked symlink to `../.agents/skills`. `.agents/skills` remains the source of truth; do not edit or duplicate skill files through the symlink as a separate copy.

The accepted downside is that exact reuse across plugin or other repositories is harder. That trade-off is intentional: Shopware-specific skills should stay close to the branch-local platform guidance they depend on instead of becoming a second source of truth.

## Initial Skills

- `shopware-knowledge-capture` for saving durable knowledge and routing it to AGENTS, coding guidelines, README, ADR, skills, or local notes.
  This skill codifies the placement rules from this ADR so agents can reuse the decision model when users ask to preserve knowledge for later.
- `shopware-change-scope` for root-cause analysis, boyscouting, and cleanup scope.
- `shopware-release-docs` for release notes, upgrade notes, and developer-facing changelog decisions.
- `shopware-pr-hygiene` for PR templates, conventional titles, and review follow-up commits.
- `shopware-php-code` for PHP architecture, API schema, migrations, deprecations, and BC-sensitive code.
- `shopware-admin-js` for Administration JavaScript, TypeScript, Vue, ACL, and Jest work.
- `shopware-phpunit-tests` for PHPUnit test structure, fixtures, feature flags, coverage, and data providers.

## Consequences

- Root agent context stays smaller.
- Claude Code loads repo guidance via sibling `CLAUDE.md → @AGENTS.md` bridges; `AGENTS.md` remains the single source of truth for all tools.
- Claude Code loads the same skills through the `.claude/skills` symlink while `.agents/skills` remains canonical.
- Humans and agents still share durable coding rules through `AGENTS.md`, `coding-guidelines/`, and existing README files.
- Rules that only matter for certain tasks can trigger as skills instead of occupying every session.
- The repository accepts one-line Claude bridge files only where real `AGENTS.md` guidance exists, while avoiding duplicated agent-specific guidance.

## Rejected Alternatives

- **Duplicate guidance into every agent file:** makes startup easy, but guarantees drift.
- **Dedicated skills repository:** separates the skills from the branch-local platform docs they rely on and creates install/sync work.
- **Use README stubs everywhere:** helps humans and some agents, but relies on agents following references that are not always auto-loaded.
- **Put all guidance in root `AGENTS.md`:** maximizes visibility, but wastes context and makes task-specific rules feel mandatory for every change.

## References

- [Repository guidance](../AGENTS.md)
- [Agent skills guideline](../coding-guidelines/core/agent-skills.md)
- [ADR coding guideline](../coding-guidelines/core/adr.md)
