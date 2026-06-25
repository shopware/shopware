---
name: shopware-knowledge-capture
description: Preserve Shopware knowledge for later. Use when the user asks to save, remember, preserve, document, or store information, rules, lessons learned, workflow knowledge, coding guidance, or agent instructions for future humans or agents.
license: MIT
---

# Shopware Knowledge Capture

Put knowledge in the smallest durable home where future readers will naturally find it.

## Workflow

1. Identify the knowledge being preserved, who needs it later, and whether it is local, repository-wide, or domain-specific.
2. Reuse the nearest existing home before creating a new file.
3. Store only durable knowledge. Keep one-off context in the current conversation.
4. Remove or update stale conflicting guidance in the same area.
5. Cross-link sparingly. Link only when it changes what the reader should do.

## Where Knowledge Goes

- `AGENTS.md`: always-needed agent routing, repository conventions, and short rules agents must load before work.
- Nested `AGENTS.md`: subtree-specific agent rules that must apply automatically when working below that directory.
- `CLAUDE.md`: Claude Code compatibility bridge. For every tracked `AGENTS.md`, keep a sibling `CLAUDE.md` whose only content is `@AGENTS.md`.
- `coding-guidelines/`: durable normative coding rules, rationale, and examples useful to humans and agents.
- Existing README: folder-specific human guidance when contributors naturally read that README for the work.
- ADR: durable decisions with real trade-offs, consequences, or future compatibility impact.
- Skill: task triggers, short workflows, and non-obvious rules that help an agent decide what to do next.
- Local untracked notes: personal setup, Docker worktree notes, approval rules, tool preferences, and other machine-local knowledge.

## Skills And Coding Guidelines

- Use skills for task triggers, short workflows, and non-obvious rules that help an agent decide what to do next.
- Use `coding-guidelines/` for durable normative detail, examples, and rationale that should stay useful for humans and agents.
- Link from a skill to coding guidelines only when the linked guideline adds task-relevant detail.
- Make those links conditional: say when to read each guideline instead of adding passive "see also" lists.
- When adding a coding guideline, check whether an existing skill should link to it so agents can discover it for matching tasks.
- Do not copy guideline content into skills unless agents repeatedly miss the linked rule in practice.

## Guardrails

- Do not add mechanical `AGENTS.md` or `GEMINI.md` stubs just to point at README files.
- Do not put independent guidance in `CLAUDE.md`; it only imports the sibling `AGENTS.md`.
- Do not duplicate ADR or coding-guideline content in READMEs.
- Do not add README or AGENTS files just to index `coding-guidelines/`.
- Keep local setup, Docker worktree notes, approval rules, and personal tool preferences in untracked local notes, not tracked project docs.
