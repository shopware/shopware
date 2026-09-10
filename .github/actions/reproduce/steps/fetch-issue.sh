#!/usr/bin/env bash
# Prefetch the agent's context so it spends turns authoring, not fetching:
#   issue.md      — title + body + HUMAN comments (prior bot repro reports are excluded, so the
#                   agent can't learn issue-specific answers from an earlier run)
#   issue-assets/ — up to 3 attached screenshots, kept only if their magic bytes say "image"
#
# Env: ISSUE (req), GH_TOKEN (req), REPO (default $GITHUB_REPOSITORY).
set -euo pipefail

: "${ISSUE:?ISSUE is required}"
REPO=${REPO:-${GITHUB_REPOSITORY:?REPO or GITHUB_REPOSITORY required}}

gh issue view "$ISSUE" --repo "$REPO" --json title,body,comments \
  --jq '
    def is_bot_comment:
      ((.author.login // "") == "github-actions")
      or ((.body // "") | contains("## AI Report (Reproduction)"))
      or ((.body // "") | contains("## Reproduction: incomplete"))
      or ((.body // "") | contains("gh-aw-comment-type"));
    "# " + .title + "\n\n" + (.body // "") + "\n\n## Comments\n\n"
    + ([.comments[]? | select(is_bot_comment | not) | "**@" + (.author.login // "?") + ":** " + (.body // "")] | join("\n\n"))
  ' > issue.md 2>/dev/null || echo "(issue unavailable)" > issue.md
head -c 60000 issue.md > issue.cap && mv issue.cap issue.md   # bound context

# Screenshots → issue-assets/. Untrusted user content: fetch UNAUTHENTICATED, only from GitHub's
# attachment hosts, capped in count + size, kept only when the magic bytes say it's an image.
mkdir -p issue-assets
i=0
{ grep -oE 'https://(github\.com/user-attachments/assets/[A-Za-z0-9-]+|user-images\.githubusercontent\.com/[A-Za-z0-9./_-]+)' issue.md || true; } \
  | sort -u | head -3 | while read -r url; do
  i=$((i + 1)); tmp="issue-assets/.dl-$i"
  curl -fsSL --proto '=https' --max-time 20 --max-filesize 3145728 -o "$tmp" "$url" 2>/dev/null || { rm -f "$tmp"; continue; }
  case "$(file -b --mime-type "$tmp" 2>/dev/null || echo unknown)" in
    image/png)  mv "$tmp" "issue-assets/img-$i.png" ;;
    image/jpeg) mv "$tmp" "issue-assets/img-$i.jpg" ;;
    image/gif)  mv "$tmp" "issue-assets/img-$i.gif" ;;
    image/webp) mv "$tmp" "issue-assets/img-$i.webp" ;;
    *) rm -f "$tmp" ;;
  esac
done
rmdir issue-assets 2>/dev/null || true   # nothing image-like was kept

[ -d issue-assets ] && echo "prefetched $(ls issue-assets | wc -l | tr -d ' ') screenshot(s)"
echo "prefetched issue.md ($(wc -c < issue.md) bytes)"
