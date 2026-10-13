#!/usr/bin/env bash
# Precompute the sw-review change profile in CI, before the agent starts.
#
# Runs as a gh aw `steps:` entry in the agent job: after checkout, outside the agent
# sandbox, with the job's GITHUB_TOKEN. The agent sandbox cannot execute repository
# scripts or redirect output, so the deterministic classification happens here and the
# orchestrator only reads the result with `cat`.
#
#   PR_NUMBER=123 GITHUB_REPOSITORY=owner/repo GH_TOKEN=... ci-profile.sh [out-file]
#
# Writes <out-file> (default .sw-review/profile.json) with either the classify.sh
# output or {"available": false, "reason": "..."} when the PR branch predates the
# classifier. The classifier itself is taken from the merge base when it exists there
# (rules from the base branch), otherwise from the checkout.
set -euo pipefail

PR="${PR_NUMBER:?PR_NUMBER is required}"
REPO="${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
OUT="${1:-.sw-review/profile.json}"
SKILL=".agents/skills/sw-review"
mkdir -p "$(dirname "$OUT")"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT

unavailable() {
  jq -n --arg r "$1" '{available: false, reason: $r}' > "$OUT"
  echo "sw-review profile: unavailable ($1)"
  echo "### sw-review change profile" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
  echo "unavailable: $1" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
  exit 0
}

pr_json="$(gh pr view "$PR" --repo "$REPO" --json baseRefName,isCrossRepository,authorAssociation,labels,body)" || unavailable "could not read PR #$PR"
base="$(jq -r '.baseRefName' <<<"$pr_json")"
git fetch --no-tags origin "$base" >/dev/null 2>&1 || unavailable "could not fetch base branch $base"
mb="$(git merge-base "origin/$base" HEAD)" || unavailable "no merge base with origin/$base"

jq -n --argjson j "$pr_json" '{
  fork: $j.isCrossRepository,
  author_association: $j.authorAssociation,
  labels: [$j.labels[].name],
  fixes_issue: (($j.body // "") | test("(?i)\\b(fix(es|ed)?|close[sd]?|resolve[sd]?)\\s*:?\\s+#[0-9]+"))
}' > "$TMP/meta.json"

if git cat-file -e "$mb:$SKILL/scripts/classify.sh" 2>/dev/null; then
  git show "$mb:$SKILL/scripts/classify.sh" > "$TMP/classify.sh"; chmod +x "$TMP/classify.sh"; script="$TMP/classify.sh"; origin="merge base $mb"
elif [ -x "$SKILL/scripts/classify.sh" ]; then
  script="$SKILL/scripts/classify.sh"; origin="checkout (base branch has no classifier yet)"
else
  unavailable "neither the merge base nor the checkout has $SKILL/scripts/classify.sh"
fi

"$script" --range "$mb...HEAD" --base "$base" --meta "$TMP/meta.json" --root "$PWD" --rules-ref "$mb" > "$TMP/profile.json"
jq -e '.signals and .guides' "$TMP/profile.json" >/dev/null || { echo "classify.sh produced no profile" >&2; exit 1; }
# CI state of the PR head, so workers know whether the PR's own tests ran and passed.
# A failed job that crashed before the suite ran hides every other result; the review must
# say "test results unavailable" instead of trusting a green-looking diff.
ci_json="$(gh pr checks "$PR" --repo "$REPO" --json name,state,bucket 2>/dev/null || echo '[]')"
jq --argjson ci "$ci_json" '. + {ci: {checks: ($ci | length), failed: [$ci[] | select(.bucket == "fail") | .name], pending: ([$ci[] | select(.bucket == "pending")] | length)}}' "$TMP/profile.json" > "$OUT"
{
  echo "### sw-review change profile"
  echo
  echo "classifier: $origin; base: \`$base\`; merge base: \`$mb\`; rules: \`$(jq -r .rules_source "$OUT")\`"
  echo
  echo "signals: $(jq -r '.signals | join(", ")' "$OUT")"
  echo
  echo "guides: $(jq -r '[.guides[] | .guide + (if .file_exists then "" else " (missing)" end)] | join(", ")' "$OUT")"
  echo
  echo "size: $(jq -r '.size.files' "$OUT") files, $(jq -r '.size.changed_lines' "$OUT") changed lines$(jq -r 'if .size.over_cap then " (over cap: split slices, never skip)" else "" end' "$OUT"); base commits merged in: $(jq -r '.merged_from_base | length' "$OUT"); CI failed checks: $(jq -r '.ci.failed | join(", ") | if . == "" then "none" else . end' "$OUT")"
} >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
echo "sw-review profile written to $OUT ($(jq -r '.guides | length' "$OUT") guides, classifier from $origin)"
