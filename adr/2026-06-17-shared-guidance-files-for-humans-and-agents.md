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
- avoids mass `AGENTS.md`, `CLAUDE.md`, and `GEMINI.md` stubs,
- keeps task-specific rules out of the always-loaded root context,
- keeps local setup, tool preferences, Docker setup, and approval rules out of tracked project documentation.

## Decision

1. Root `AGENTS.md` stays concise and carries only repository-wide context, main subtree routing, and mandatory linting guidance.
2. Main subtree `AGENTS.md` files are allowed when they hold real subtree rules or route to substantial existing guidance.
3. Do not add mechanical `AGENTS.md`, `CLAUDE.md`, or `GEMINI.md` stubs just to point at README files.
4. Task-specific guidance lives in Agent Skills under `.claude/skills/`, where it loads only when the task asks for it.
5. Reusable normative rules belong in `coding-guidelines/`.
6. Folder-specific human guidance may live in an existing README when contributors naturally read that README for the work.
7. ADRs capture durable decisions, trade-offs, and consequences; they should not become living checklists.
8. Local-only agent mechanics stay in untracked override files such as `AGENTS.override.md`.

## Initial Skills

- `shopware-guidance-files` for repository guidance, ADR, README, and coding-guideline updates.
- `shopware-change-scope` for root-cause analysis, boyscouting, and cleanup scope.
- `shopware-release-docs` for release notes, upgrade notes, and developer-facing changelog decisions.
- `shopware-pr-hygiene` for PR templates, conventional titles, and review follow-up commits.
- `shopware-php-code` for PHP architecture, API schema, migrations, deprecations, and BC-sensitive code.
- `shopware-admin-js` for Administration JavaScript, TypeScript, Vue, ACL, and Jest work.
- `shopware-phpunit-tests` for PHPUnit test structure, fixtures, feature flags, coverage, and data providers.

## Consequences

- Root agent context stays smaller.
- Humans and agents still share durable coding rules through `AGENTS.md`, `coding-guidelines/`, and existing README files.
- Rules that only matter for certain tasks can trigger as skills instead of occupying every session.
- The repository avoids a large number of one-line routing files.

## Rejected Alternatives

- **Duplicate guidance into every agent file:** makes startup easy, but guarantees drift.
- **Use README stubs everywhere:** helps humans and some agents, but relies on agents following references that are not always auto-loaded.
- **Put all guidance in root `AGENTS.md`:** maximizes visibility, but wastes context and makes task-specific rules feel mandatory for every change.

## References

- [Repository guidance](../AGENTS.md)
- [Agent skills guideline](../coding-guidelines/core/agent-skills.md)
- [ADR coding guideline](../coding-guidelines/core/adr.md)
