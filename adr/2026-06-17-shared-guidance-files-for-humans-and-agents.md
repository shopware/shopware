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
Review feedback on the first version showed the same risk inside this repository: the root `AGENTS.md` grew too large, some human-relevant rules were placed only in agent files, and some rules needed more precise ownership by component or test type.

We need one documentation model that:

- keeps durable working guidance useful for humans and agents,
- avoids duplicating the same rules across README, AGENTS, CLAUDE, and GEMINI files,
- separates reusable coding rules from folder-specific working context,
- records durable decisions without turning living guidance into historical essays,
- keeps the root agent context small by routing readers to the relevant subtree guidance,
- keeps local setup, tool preferences, Docker setup, and approval rules out of tracked project documentation.

## Decision

1. Folder-specific durable guidance lives in the folder's `README.md`.
   The README is the source of truth and should be understandable for humans and explicit enough for agents.
2. The root `AGENTS.md` stays concise and routes conditionally to process and subtree guidance:
   repository process work uses `delivery-process/README.md`, PHP/server code uses `src/Core/AGENTS.md`, Administration code uses `src/Administration/AGENTS.md`, Storefront code uses `src/Storefront/AGENTS.md`, and tests use `tests/AGENTS.md`.
3. Subtree `AGENTS.md` files are allowed when they route to more specific guidance or hold concise rules for that whole subtree.
   They must stay readable for humans and agents, and they must not conflict with `coding-guidelines/`.
4. Human-relevant component rules belong in README or `coding-guidelines/`.
   For example, Administration ACL guidance belongs in the Administration README/coding guideline rather than only in an agent file.
5. `AGENTS.md` stubs inside subtrees should point agents to the README when the README is the source of truth.
   They should not duplicate README content.
6. `CLAUDE.md` and `GEMINI.md` files mirror that routing by importing the local `AGENTS.md` with `@AGENTS.md`.
7. Standalone overview or documentation READMEs do not need agent routing stubs unless the companion `AGENTS.md` routes domain-specific guidance.
   Folders whose path contains `docs` usually do not need stubs.
8. Reusable normative rules belong in `coding-guidelines/`.
   Component READMEs should link to the concrete guideline files that apply instead of duplicating those rules.
9. ADRs capture durable decisions, trade-offs, and consequences.
   README guidance, coding guidelines, and recent/current ADRs should cross-link where the link changes what the reader should do.
10. Local-only agent mechanics stay in untracked override files such as `AGENTS.override.md`.
   This includes Docker worktree setup, approval rules, personal tool preferences, and other machine-local instructions.

## Consequences

- Humans and agents share the same durable guidance instead of following parallel documents.
- Context windows stay smaller because the root agent entry file routes to the relevant subtree instead of repeating PHP, Administration, Storefront, and PHPUnit guidance.
- Process guidance lives with the rest of the delivery-process documentation instead of expanding the root agent context.
- Contributors can update working guidance in the same place they already expect to find component documentation.
- Coding guidelines stay reusable and normative, while READMEs stay focused on local working context.
- Review feedback can move misplaced rules to the right owner without changing the overall model: human-facing component rules go to READMEs or coding guidelines; subtree-wide routing can stay in AGENTS files.
- ADRs keep the rationale available without making the active guidance files stale or verbose.
- The repository contains more tiny routing files, but they are intentionally boring and tool-compatible.

## Rejected Alternatives

- **Duplicate guidance into every agent file:** makes agent startup easy, but guarantees drift.
- **Use `AGENTS.md` as the source of truth:** hides rules from humans even though humans should follow the same guidance.
- **Symlink agent files to README files:** removes file duplication, but depends on symlink support and blurs the difference between guidance content and tool routing.
- **Put all guidance in the root `AGENTS.md`:** maximizes visibility, but wastes context and makes local component rules harder to maintain.

## References

- [Repository guidance](../AGENTS.md)
- [Delivery process guidance](../delivery-process/README.md)
- [ADR coding guideline](../coding-guidelines/core/adr.md)
- [Co-located Administration technical documentation ADR](2025-10-14-colocate-administration-technical-docs.md)
