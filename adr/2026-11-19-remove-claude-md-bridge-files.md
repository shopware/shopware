---
title: Remove CLAUDE.md bridge files
date: 2026-11-19
area: process
tags: [documentation, agents, developer-experience, adr]
---

## Context

The ADR "Shared Guidance Files for Humans and Agents" (2026-06-17) allowed one exception to its stub rule: every tracked `AGENTS.md` has a sibling `CLAUDE.md` whose only body is `@AGENTS.md`.
Since Claude Code v2.1.277, Claude Code reads `AGENTS.md` natively, in the places where it would read `CLAUDE.md`.
It does so only in a project without `CLAUDE.md`: a single `CLAUDE.md` in the start directory or above disables `AGENTS.md` loading for the whole session, and a sibling `AGENTS.md` next to a `CLAUDE.md` is skipped.
With the bridges in place, an `AGENTS.md` loads only through its sibling `CLAUDE.md`, and an `AGENTS.md` added without a bridge never loads.

## Decision

This ADR supersedes the bridge exception of the 2026-06-17 ADR.
The repository contains no `CLAUDE.md` files, and `AGENTS.md` is the only guidance file.
Do not add a `CLAUDE.md`, including at the repository root, because one file disables native `AGENTS.md` loading.

## Consequences

- Claude Code loads every `AGENTS.md` natively, with no bridge to maintain.
- Claude Code v2.1.277 or later is required.
  Older versions load no repository guidance automatically.
