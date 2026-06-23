---
name: shopware-guidance-files
description: Maintain Shopware repository guidance files. Use when adding or changing AGENTS.md, CLAUDE.md, GEMINI.md, README guidance, coding-guidelines, ADRs, or deciding where durable human/agent guidance belongs.
---

# Shopware Guidance Files

Keep guidance close to the smallest durable owner.

## Workflow

1. Put always-needed agent routing in the nearest `AGENTS.md`.
2. Put reusable normative rules in `coding-guidelines/`.
3. Put folder-specific human guidance in that folder's existing README only when humans naturally read it there.
4. Add an ADR only for durable decisions with real trade-offs, consequences, or future compatibility impact.
5. Cross-link sparingly. Link only when it changes what the reader should do.
6. Remove stale guidance instead of preserving historical clutter.

## Guardrails

- Do not add mechanical `AGENTS.md`, `CLAUDE.md`, or `GEMINI.md` stubs just to point at README files.
- Do not duplicate ADR or coding-guideline content in READMEs.
- Do not add README or AGENTS files just to index `coding-guidelines/`.
- Keep local setup, Docker worktree notes, approval rules, and personal tool preferences in untracked local notes, not tracked project docs.
