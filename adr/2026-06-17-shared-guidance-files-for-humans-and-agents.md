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
4. Task-specific guidance lives in Agent Skills under `.claude/skills/`, where it loads only when the task asks for it.
5. Reusable normative rules belong in `coding-guidelines/`.
6. Folder-specific human guidance may live in an existing README when contributors naturally read that README for the work.
7. ADRs capture durable decisions, trade-offs, and consequences; they should not become living checklists.
8. Local-only agent mechanics stay in untracked override files such as `AGENTS.override.md`.
9. Plugin repositories may use their own `AGENTS.md` to explicitly point agents at a neighbouring platform checkout's `AGENTS.md` and selected platform skills. This is a reuse hint, not skill installation; copy or sync selected skills only when another repository needs reliable triggering.

Example plugin `AGENTS.md`:

```md
## Shopware Guidance

This plugin follows Shopware platform guidance. If available, read:
- `../platform/AGENTS.md`
- `../platform/.claude/skills/shopware-php-code/SKILL.md`
- `../platform/.claude/skills/shopware-phpunit-tests/SKILL.md`
- `../platform/.claude/skills/shopware-pr-hygiene/SKILL.md`
```

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
- Humans and agents still share durable coding rules through `AGENTS.md`, `coding-guidelines/`, and existing README files.
- Rules that only matter for certain tasks can trigger as skills instead of occupying every session.
- The repository accepts one-line Claude bridge files only where real `AGENTS.md` guidance exists, while avoiding duplicated agent-specific guidance.
- Plugin repositories can reuse platform guidance cheaply through explicit `AGENTS.md` references without introducing a shared skills distribution workflow.

## Rejected Alternatives

- **Duplicate guidance into every agent file:** makes startup easy, but guarantees drift.
- **Use README stubs everywhere:** helps humans and some agents, but relies on agents following references that are not always auto-loaded.
- **Put all guidance in root `AGENTS.md`:** maximizes visibility, but wastes context and makes task-specific rules feel mandatory for every change.

## References

- [Repository guidance](../AGENTS.md)
- [Agent skills guideline](../coding-guidelines/core/agent-skills.md)
- [ADR coding guideline](../coding-guidelines/core/adr.md)
