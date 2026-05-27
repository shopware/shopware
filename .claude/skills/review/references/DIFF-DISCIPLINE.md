# Diff Discipline

Misreading a diff is the single biggest source of false positives. Rules below prevent that.

## 0. PR state — check before reading

- **Empty / boilerplate body** (template placeholders, "see commits", "wip", title copy): for `product-owner` this _is_ the finding. Other personas: rely on diff + code for `summary`; do not invent intent.
- **Description ↔ diff mismatch:** `product-owner` flags directly; other personas acknowledge in `summary`.
- **Closed / merged:** informational only — `summary` notes the state, empty `findings`.

## 1. Three passes

**Pass 1 — name only.** Group paths:

- core (`src/Core/**`)
- admin (`src/Administration/Resources/app/administration/**`)
- storefront (`src/Storefront/**`)
- tests (`tests/**`, `**/Test/**`, `**/__tests__/**`)
- config / build (`composer.json`, `package*.json`, `*.yaml`, `webpack.*`)
- release artefacts (`UPGRADE-*.md`, `CHANGELOG.md`)
- generated / vendored (§5 — usually skip)
- other

If your persona's group is empty, the review may legitimately be empty.

**Pass 2 — unified diff, once.** Form a one-sentence model of the PR intent → goes in `summary`.

**Pass 3 — hunks with context.** For each candidate:

- PHP method: read full method, class header, ≥1 caller (`rg "MyClass::myMethod" --type=php`).
- Vue component: read `<script setup>` and the parent that mounts it.
- Twig block: read surrounding blocks + any extended block.

A finding without context is a guess.

## 2. Hunk reading order

Top to bottom within a file — the author's order partially conveys intent. Catches "added validation but forgot to wire it" patterns.

Expand ±20 lines of context before commenting: `git diff <base>..<head> -U20 -- <file>` or `Read` at the right offset.

## 3. Renames, moves, splits

Before flagging "removed":

- `git log --follow --oneline -- <new-path>` — did it move?
- `git diff <base>..<head> --diff-filter=R` — what was renamed?
- `gh api repos/{owner}/{repo}/pulls/<N>/files --jq '.[] | select(.status == "renamed") | {previous_filename, filename}'`

Flagging "`Foo::bar` removed" when it moved to `Baz::bar` is the most embarrassing false positive.

## 4. "Deleted code" trap

A `-` hunk isn't a finding by itself. Code gets deleted because:

- moving behind a flag
- responsibility moved (look for matching `+` elsewhere)
- dead code (search callers with `rg`)
- deprecation cycle ending (`UPGRADE-*.md`)

Before flagging, run `rg "<deleted-symbol>"` + `git log` for the original commit.

## 5. Generated / vendored — skip

- `vendor/**`, `node_modules/**`
- `**/dist/**`, `**/build/**`, `**/.cache/**`
- `**/*.lock`, `**/*-lock.json`, `composer.lock`, `yarn.lock`, `package-lock.json`
- generated migrations with auto-gen headers
- `**/__snapshots__/**`, `*.snap`
- icon SVGs, font binaries, `*.min.js`, `*.min.css`

Diff is _only_ in these → `decision: comment`, empty `findings`, `summary` says so.

## 6. Binary / lockfile changes

Lockfile: one-line acknowledgement (`supply_chain` for security, `compatibility` for architecture). The question: does the PR description explain _why_ dependencies moved? Yes → no finding. No + large → `minor` `docs`.

Binaries (images, fonts, audio): no content review. Verify the file is referenced; that's enough.

## 7. Size caps

Caps apply twice: per persona-worker, **and** to the orchestrator before fan-out (§SKILL.md Step 2a). If `pr.additions + pr.deletions` already exceeds the per-PR cap, orchestrator runs only `product-owner` + `open-source` + `security`; skips the rest.

| Scope                  | Cap    | Action                                                                                                                                                                  |
| ---------------------- | ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Per-file changed lines | 400    | Skim header + first hunk, mention in `summary`, no per-line findings                                                                                                    |
| Per-PR changed lines   | 5000   | `summary` names dominant areas; ≤5 findings, all `confidence ≤ 0.5`, all `requires_human: true`. Decision → `needs_human_review`. Orchestrator skips expensive personas |
| Per-PR changed files   | 200    | Same as above                                                                                                                                                           |
| Shell time             | ~3 min | Stop, finalise, declare gaps in `summary`                                                                                                                               |

Past the caps the persona starts inventing findings to look thorough — worst failure mode.
