# Diff Discipline

Misreading diffs creates most false positives.

## Reading Order

1. Path list first: group by core, admin, storefront, tests, config/build, release docs, generated/vendor, other.
2. Assigned diff slice second: understand intent and changed behavior.
3. Context only for candidate findings.

Use file-scoped diff or `Read` around the changed area when needed.

## Traps

- Empty or boilerplate PR body: do not invent intent; use diff and title.
- Description/diff mismatch: mention only as a summary gap unless it triggers your persona's rule.
- Closed/merged PR: informational only; no findings unless user asks otherwise.
- Deleted code: search for moves, replacements, dead-code removal, or completed deprecation before flagging.
- Renames/splits: check rename metadata or path history before claiming removal.
- Generated/vendor/binary/lockfile-only diff: usually no findings; mention limited surface in summary.

## Context Expansion

- PHP: read full method/class and at least one caller when relevant.
- Vue: read script and parent/mounting context when relevant.
- Twig: read surrounding blocks and extension/override context.
- Migrations: check idempotency and data loss risk.

## Size Caps

| Scope      |               Cap | Action                                                                  |
| ---------- | ----------------: | ----------------------------------------------------------------------- |
| Per file   | 400 changed lines | skim first hunk/header, no per-line findings unless obvious             |
| PR lines   |              5000 | throttle personas, max 5 findings, set decision to `needs_human_review` |
| PR files   |               200 | same as PR lines                                                        |
| Shell time |            ~3 min | stop and declare gaps                                                   |

Past caps, prefer `needs_human_review` over speculative findings.
