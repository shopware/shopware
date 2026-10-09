#!/usr/bin/env bash
# Deterministic routing tests for scripts/classify.sh. No LLM involved.
#
#   bash tests/routing/run.sh            # all cases
#   bash tests/routing/run.sh pr-18612   # one case
#
# A case is a folder with files.txt, diff.patch, meta.json and expected.json:
#   { "signals": [...],      // every listed signal must be present
#     "not_signals": [...],  // none of these may be present
#     "guides": [...] }      // the selected guide set must equal this list (order ignored)
set -uo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/../../../../.." && pwd)"
CLASSIFY="$HERE/../../scripts/classify.sh"
cases=("$@"); [ "${#cases[@]}" -gt 0 ] || cases=($(cd "$HERE" && ls -d */ | tr -d /))
fail=0; total=0
for c in "${cases[@]}"; do
  d="$HERE/$c"; [ -f "$d/expected.json" ] || continue
  total=$((total+1))
  out="$("$CLASSIFY" --files "$d/files.txt" --diff "$d/diff.patch" --meta "$d/meta.json" --root "$ROOT" 2>&1)" || { echo "FAIL $c: classify.sh exited non-zero: $out"; fail=$((fail+1)); continue; }
  missing="$(jq -r --argjson o "$out" '.signals[] as $s | select(($o.signals | index($s)) == null) | $s' "$d/expected.json")"
  forbidden="$(jq -r --argjson o "$out" '(.not_signals // [])[] as $s | select(($o.signals | index($s)) != null) | $s' "$d/expected.json")"
  exp_guides="$(jq -c '.guides | sort' "$d/expected.json")"
  got_guides="$(jq -c '[.guides[].guide] | sort' <<<"$out")"
  if [ -z "$missing" ] && [ -z "$forbidden" ] && [ "$exp_guides" = "$got_guides" ]; then
    echo "ok   $c  guides=$got_guides"
  else
    echo "FAIL $c"; [ -n "$missing" ] && echo "     missing signals: $(echo $missing)"; [ -n "$forbidden" ] && echo "     forbidden signals present: $(echo $forbidden)"
    [ "$exp_guides" != "$got_guides" ] && { echo "     expected guides: $exp_guides"; echo "     got guides:      $got_guides"; }
    fail=$((fail+1))
  fi
done
echo "$((total-fail))/$total routing cases passed"
[ "$fail" -eq 0 ]
