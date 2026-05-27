# Tools, Shell Discipline, PII Hygiene

Read-only. Pick the cheapest tool that answers the question.

## Fetching the PR (interactive)

In wrapper-fed mode, PR data arrives in `<input_json>`; do not call `gh`.

- `gh pr view <N> --json number,title,body,labels,state,baseRefName,headRefName,headRefOid,author,additions,deletions,changedFiles`
- `gh pr diff <N>` — unified diff
- `gh pr diff <N> --name-only` — paths only (cheap, use first on large PRs)
- `gh api repos/{owner}/{repo}/pulls/<N>/files --paginate --jq '.[] | {filename, status, additions, deletions, patch}'` — per-file when whole diff is too big

`GH_REPO` is set in CI. Local: `gh` infers from the remote.

## Codebase (cheap; prefer first)

- `rg "<symbol>" -l` — files referencing a symbol
- `rg "<pattern>" --type=php -n` — type-scoped search
- `rg -A 5 -B 5 "<pattern>" <path>` — context around hits
- `find src/Core -name "*.php" -path "*<keyword>*"` — candidate files
- `ls <dir>`

Once a path is known, prefer `Read` — it shows line numbers (`findings[].line` needs them).

## Git

- `git log --oneline -20 -- <path>`
- `git log --oneline --since="6 months ago" -- <path>`
- `git log --all --oneline --grep "<keyword>"`
- `git show <sha> --stat`
- `git blame -L 100,150 <file>` — sparingly, for intent only
- `git diff <base>..<head> -- <path>` (`-U20` for wider hunks)
- `git log --follow --oneline -- <path>` — follow renames before flagging "removed"

## GitHub (cheap, rate-limited — ≤5 per run)

- `gh issue list --search "<keywords>" --state all --limit 10 --json number,title,state,labels`
- `gh issue view <N> --json number,title,body,state,labels,closedAt`
- `gh pr list --search "<keywords>" --state merged --limit 5 --json number,title,mergedAt`
- `gh pr view <N> --json title,body,files,mergedAt`

## Don't

- `cat` huge files → use `Read` with `offset`/`limit`, or `rg -A/-B`.
- `git log` without `--oneline` + `-- <path>` → noisy.
- `find /` or unscoped globs.
- Retry failures in a loop → diagnose or note and proceed.
- Re-read the same file.
- Browse unrelated dirs "to be thorough" — every shell call has a budget.

## PII hygiene in `evidence`

Policy → `BOUNDARIES.md` §4. Recipe:

When quoting from shell output OR the PR diff, substitute identifying spans:

| Source                                                             | Placeholder        |
| ------------------------------------------------------------------ | ------------------ |
| Email                                                              | `[REDACTED_EMAIL]` |
| Long key/token (AWS keys, API tokens, JWTs, meaningful base64/hex) | `[REDACTED_KEY]`   |
| Personal names the surrounding code doesn't need                   | `[REDACTED_PII]`   |
| Customer IDs / order numbers in fixtures                           | `[REDACTED_ID]`    |

Substitution is surgical — only the sensitive span changes, rest stays verbatim.

| Source line                                            | Correct `evidence`                          |
| ------------------------------------------------------ | ------------------------------------------- |
| `+AWS_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/EXAMPLE` | `+AWS_SECRET_ACCESS_KEY=[REDACTED_KEY]`     |
| `+const TOKEN = "sk-proj-9aF8…"`                       | `+const TOKEN = "[REDACTED_KEY]"`           |
| `Author: Jane Doe <jane@example.com>`                  | `Author: [REDACTED_PII] <[REDACTED_EMAIL]>` |
| `// TODO: fix for customer 0123-456-789`               | `// TODO: fix for customer [REDACTED_ID]`   |

Wrapper-fed input is pre-redacted upstream; what you derive yourself is not.

## Hard limits

- Network: only `gh` and `git` read calls. No `curl`/`wget`/arbitrary HTTP.
- Filesystem + GitHub: read-only (`BOUNDARIES.md` §1–§2).
- Time budget: ≤ 3 min of shell calls per review. Finalise even if incomplete — declare the gap in `summary`.
