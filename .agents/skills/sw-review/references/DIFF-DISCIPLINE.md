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

A large PR is split, never skipped. Dropping a persona or a guide because the
diff is big is a silent gap, and silent gaps cost real findings: a throttled
review of a 250-file PR missed a merchant-visible Admin bug that the skipped
`ux` persona found within minutes when it was run afterwards.

| Scope          |                    Cap | Action                                                                                                                       |
| -------------- | ---------------------: | ---------------------------------------------------------------------------------------------------------------------------- |
| Generated file |                    any | lockfiles, snapshots, `dist/`, vendored code: skim the header, no per-line findings                                          |
| Source file    |      400 changed lines | read it anyway; if the slice is large, split the file's hunks between workers of the same persona                            |
| Persona slice  | 3000 lines or 60 files | split into up to 3 workers of the same persona along `change_profile.path_groups` (largest first); each gets the same guides |
| PR             | 5000 lines or 200 files | every gated persona still runs; decision at least `needs_human_review`; `not_reviewed` lists anything left out               |
| Guides         |         no cap on count | unaffected by size; every matching guide loads for every worker of its personas                                              |
| Budget / time  |         run limits hit | stop in persona priority order (security, architecture, maintainer, ux, code-style); every unreviewed slice goes into `not_reviewed` with paths and changed lines |

Past caps, prefer `needs_human_review` over speculative findings, and prefer an
explicit `not_reviewed` entry over a persona that silently did not run.
