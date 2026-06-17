---
title: Shared Guidance Files for Humans and Agents
date: 2026-06-17
area: process
tags: [documentation, agents, developer-experience, coding-guidelines, adr]
status: accepted
---

## Context

Shopware now has several consumers for repository guidance: human contributors, Codex, Claude, Gemini, and other agent tools.
Keeping separate guidance for each audience caused duplication, drift, and larger context loads than necessary.

We need one documentation model that:

- keeps durable working guidance useful for humans and agents,
- avoids duplicating the same rules across README, AGENTS, CLAUDE, and GEMINI files,
- separates reusable coding rules from folder-specific working context,
- records durable decisions without turning living guidance into historical essays,
- keeps local setup, tool preferences, Docker setup, and approval rules out of tracked project documentation.

## Decision

1. Folder-specific durable guidance lives in the folder's `README.md`.
   The README is the source of truth and should be understandable for humans and explicit enough for agents.
2. `AGENTS.md` files in subtrees are short routing files that point agents to the README.
   They should not duplicate README content.
3. `CLAUDE.md` and `GEMINI.md` files mirror that routing by importing the local `AGENTS.md` with `@AGENTS.md`.
4. Standalone overview or documentation READMEs do not need agent routing stubs.
   This includes the main component READMEs under `src/*` and folders whose path contains `docs`.
5. Reusable normative rules belong in `coding-guidelines/`.
   Component READMEs should link to the concrete guideline files that apply instead of duplicating those rules.
6. ADRs capture durable decisions, trade-offs, and consequences.
   README guidance, coding guidelines, and ADRs should cross-link where the link helps readers understand both what to do and why.
7. The root `AGENTS.md` is reserved for concise repo-wide guidance that agents should not miss.
   Detailed or component-specific guidance should move to the relevant README or coding guideline.
8. Local-only agent mechanics stay in untracked override files such as `AGENTS.override.md`.
   This includes Docker worktree setup, approval rules, personal tool preferences, and other machine-local instructions.

## Consequences

- Humans and agents share the same durable guidance instead of following parallel documents.
- Context windows stay smaller because agent entry files route to the source instead of repeating it.
- Contributors can update working guidance in the same place they already expect to find component documentation.
- Coding guidelines stay reusable and normative, while READMEs stay focused on local working context.
- ADRs keep the rationale available without making the active guidance files stale or verbose.
- The repository contains more tiny routing files, but they are intentionally boring and tool-compatible.

## Rejected Alternatives

- **Duplicate guidance into every agent file:** makes agent startup easy, but guarantees drift.
- **Use `AGENTS.md` as the source of truth:** hides rules from humans even though humans should follow the same guidance.
- **Symlink agent files to README files:** removes file duplication, but depends on symlink support and blurs the difference between guidance content and tool routing.
- **Put all guidance in the root `AGENTS.md`:** maximizes visibility, but wastes context and makes local component rules harder to maintain.

## References

- [Repository guidance](../AGENTS.md)
- [ADR coding guideline](../coding-guidelines/core/adr.md)
- [Co-located Administration technical documentation ADR](2025-10-14-colocate-administration-technical-docs.md)
