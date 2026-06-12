#!/usr/bin/env bash
# Persist a PROVEN repro plan so re-runs replay it instead of re-deriving from scratch.
#
# A regenerated plan is a gamble: issue #32 reproduced live_bug, then two re-runs derived
# weaker fixtures and regressed to inconclusive. When a run's verdict shows the symptom was
# actually reproduced on some leg (live_bug / fixed_on_trunk / regression), save the plan
# (analysis.json + generated script + fixtures) to the evidence branch under
# plans/issue-<N>/; prefetch.sh offers it to the next analyze as ./prior-plan/.
#
# Env: VERDICT (req), ISSUE (req), BRANCH (evidence branch, req), REPO (req), TOKEN (req),
#      ART (default artifacts).
set -euo pipefail

: "${VERDICT:?}"; : "${ISSUE:?}"; : "${BRANCH:?}"; : "${REPO:?}"; : "${TOKEN:?}"
ART=${ART:-artifacts}

case "$VERDICT" in
  live_bug|fixed_on_trunk|regression) ;; # the symptom was genuinely reproduced somewhere
  *) echo "verdict '$VERDICT' is not a proven reproduction — not persisting"; exit 0 ;;
esac
[ -f "$ART/analysis/analysis.json" ] || { echo "no analysis.json — nothing to persist"; exit 0; }

EV=$(mktemp -d)
git -C "$EV" init -q
git -C "$EV" remote add origin "https://x-access-token:${TOKEN}@github.com/${REPO}.git"
if git -C "$EV" fetch -q --depth 1 origin "$BRANCH" 2>/dev/null; then
  git -C "$EV" checkout -q FETCH_HEAD
else
  git -C "$EV" checkout -q --orphan "$BRANCH"
fi
DEST="$EV/plans/issue-$ISSUE"
rm -rf "$DEST" && mkdir -p "$DEST"
cp "$ART/analysis/analysis.json" "$DEST/"
for f in fixtures.json repro.spec.ts ReproTest.php; do
  [ -f "$ART/analysis/$f" ] && cp "$ART/analysis/$f" "$DEST/"
done
printf 'verdict: %s\nrun: %s\n' "$VERDICT" "${GITHUB_RUN_ID:-local}" > "$DEST/PROVEN"
git -C "$EV" add "plans/issue-$ISSUE"
git -C "$EV" -c user.name=github-actions -c user.email=actions@github.com \
  commit -q -m "proven plan for issue #$ISSUE ($VERDICT, run ${GITHUB_RUN_ID:-local})" || { echo "plan unchanged"; exit 0; }
git -C "$EV" push -q origin "HEAD:refs/heads/$BRANCH"
echo "persisted proven plan for issue #$ISSUE ($VERDICT)"
