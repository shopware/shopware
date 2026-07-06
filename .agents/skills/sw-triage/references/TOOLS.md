# Tools, Shell Discipline & PII Hygiene

Loaded by the triage agent on-demand when it needs the full tool catalogue. Cost matters — be efficient.

## Codebase exploration (cheap — prefer these first)

- `rg "ImportExportService" -l` — find files referencing a symbol or string
- `rg "function exportFile" --type=php -n` — search for code patterns
- `find src/Core -name "*.php" -path "*ImportExport*"` — list candidate files
- `ls src/Core/Content/ImportExport/` — explore a directory
- `rg -A 5 "function generateFilename" src/Core/Content/ImportExport/` — show context window around a pattern (use `-A`/`-B` instead of `head`/`tail`)

## Git history (cheap)

- `git log --oneline -10 -- src/Core/Content/ImportExport/` — recent changes in an area
- `git log --since="12 months ago" --oneline -- <path>` — changes in a timeframe
- `git log --all --oneline --grep "filename extension"` — find commits matching a description
- `git show <sha> --stat` — what changed in a specific commit (file list, not full diff)
- `git blame -L 100,150 <file>` — who last touched specific lines (use sparingly)

## GitHub (network calls — use sparingly, max ~5 per run)

The default repository is set via `GH_REPO` / `AI_TRIAGE_REPO` env (typically `shopware/shopware`). `gh` honours `GH_REPO` automatically — no `--repo` flag needed.

- `gh issue list --search "<keywords>" --state all --limit 10 --json number,title,state,closedAt,labels` — find related/similar issues
- `gh issue view <number> --json number,title,body,state,labels,closedAt` — read a candidate duplicate
- `gh pr list --search "<keywords>" --state merged --limit 5 --json number,title,mergedAt,files` — find related fixes
- `gh pr view <number> --json title,body,files,mergedAt` — what did a fix change

## PII hygiene in `evidence_quotes`

When you quote shell output (e.g. a `git log` author line, a `gh issue view` body), **redact any email addresses, API-key-shaped strings, or other identifying info** before including the quote. Replace with `[REDACTED_EMAIL]` / `[REDACTED_KEY]` / `[REDACTED_PII]` as appropriate. Verbatim quoting of customer-pasted secrets in other issues is a leak path.

## Anti-patterns — do NOT do this

- Do not `cat` huge files — use `rg -A N`/`rg -B N` for context windows. (`head` / `tail` work in the gh aw sandbox, but they are noisy and a prompt-injected `head /proc/self/environ` would dump environment vars; the AWF firewall and `--exclude-env` for the live secrets neutralise the exfil path but the noise still costs turns. Stick to `rg` context windows.)
- Do not `git log` without `--oneline` and without a `-- <path>` filter — too noisy.
- Do not `find /` or unrestricted globs — too slow.
- Do not run `gh issue list` multiple times with slight variations — pick 1–2 good queries.
- Do not read the same file twice — note what you already saw.
- Do not speculatively browse unrelated directories.

## Hard limits

- **Network:** only `gh` and `git`-style read calls — no arbitrary HTTP.
- **Filesystem:** workspace-write sandbox (engine-dependent), but **DO NOT write or modify files**. Your output is the final JSON only.
- **Time budget:** aim for ≤ 2 minutes of shell-tool calls. Finalize even if research is incomplete.
