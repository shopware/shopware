#!/usr/bin/env bash
# Tests steps/fetch-issue.sh offline & deterministically. `gh`, `curl` and `file` are stubbed onto a
# throwaway PATH so no network is touched: the `gh` stub runs the script's real embedded jq filter
# against a fixture JSON (so bot-comment filtering is genuinely exercised), while `curl`/`file` record
# which attachment URLs were fetched and dictate their pretend mime types via markers in the URL.
# Covered: title/body/human-comment rendering + bot-comment exclusion, the two allowed attachment
# hosts vs. rejected hosts, the 3-attachment count cap, magic-byte keep/drop, the empty-dir cleanup,
# the 60000-byte context cap, the missing-issue fallback, and required-env validation.
set -u
here=$(cd "$(dirname "$0")" && pwd)
# shellcheck source=lib.sh
. "$here/lib.sh"
STEPS=$(cd "$here/../../steps" && pwd)

# Populate <dir>/bin with offline stubs for gh, curl and file.
#   gh   — honours GH_MODE=fail (exit 1, exercising the "(issue unavailable)" fallback); otherwise
#          extracts the --jq expression the script passes and runs the REAL jq over $GH_FIXTURE.
#   curl — appends each requested URL to $CURL_LOG, fails for URLs containing "fail", else writes a
#          body of "content:<url>" to the -o target so `file` can classify it.
#   file — reports a mime type derived from URL markers baked into the body: pngimg/jpgimg -> image,
#          notimg -> text/plain (dropped by the script).
make_stub_bin() { # dir
  local bin="$1/bin"; mkdir -p "$bin"
  cat > "$bin/gh" <<'EOF'
#!/bin/sh
if [ "${GH_MODE:-ok}" = "fail" ]; then exit 1; fi
prev=""; jqexpr=""
for a in "$@"; do
  [ "$prev" = "--jq" ] && jqexpr=$a
  prev=$a
done
exec jq -r "$jqexpr" "$GH_FIXTURE"
EOF
  cat > "$bin/curl" <<'EOF'
#!/bin/sh
out=""; url=""; prev=""
for a in "$@"; do
  [ "$prev" = "-o" ] && out=$a
  url=$a
  prev=$a
done
printf '%s\n' "$url" >> "$CURL_LOG"
case "$url" in *fail*) exit 22 ;; esac
[ -n "$out" ] && printf 'content:%s' "$url" > "$out"
exit 0
EOF
  cat > "$bin/file" <<'EOF'
#!/bin/sh
for a in "$@"; do f=$a; done
c=$(cat "$f" 2>/dev/null)
case "$c" in
  *notimg*) echo text/plain ;;
  *pngimg*) echo image/png ;;
  *jpgimg*) echo image/jpeg ;;
  *) echo application/octet-stream ;;
esac
EOF
  chmod +x "$bin/gh" "$bin/curl" "$bin/file"
}

# Run fetch-issue.sh in a fresh temp dir. Echoes the dir path (so callers can inspect the artifacts)
# and returns the script's exit code. Extra KEY=VALUE args are layered onto the env (later wins), so
# GH_MODE=fail etc. can be injected.
run_fetch() { # fixture_json [KEY=VALUE ...]
  local fixture=$1; shift
  local d; d=$(mktemp -d)
  make_stub_bin "$d"
  printf '%s' "$fixture" > "$d/fixture.json"
  : > "$d/curl.log"
  ( cd "$d" && env PATH="$d/bin:$PATH" \
      ISSUE=42 GH_TOKEN=x REPO=owner/repo \
      GH_FIXTURE="$d/fixture.json" CURL_LOG="$d/curl.log" \
      "$@" bash "$STEPS/fetch-issue.sh" >"$d/stdout.log" 2>"$d/stderr.log" )
  local rc=$?
  printf '%s\n' "$d"
  return "$rc"
}

# ---------------------------------------------------------------------------------------------------
# Title / body / comment rendering + bot-comment exclusion (the embedded jq allowlist).
# ---------------------------------------------------------------------------------------------------
fix='{"title":"Checkout crashes","body":"Applying a promotion breaks it.","comments":[
  {"author":{"login":"alice"},"body":"I can reproduce on 6.7.2.0"},
  {"author":{"login":"github-actions"},"body":"automated bot chatter"},
  {"author":{"login":"botlike"},"body":"## AI Report (Reproduction) verdict here"},
  {"author":{"login":"botlike"},"body":"## Reproduction: incomplete missing steps"},
  {"author":{"login":"marker"},"body":"contains a gh-aw-comment-type marker"}]}'
d=$(run_fetch "$fix"); rc=$?
md=$(cat "$d/issue.md")
assert_eq "$rc" "0" "success path exits 0"
assert_contains "$md" "# Checkout crashes" "title is rendered with a leading '# '"
assert_contains "$md" "Applying a promotion breaks it." "body is included"
assert_contains "$md" "**@alice:** I can reproduce on 6.7.2.0" "human comment is kept with author"
assert_eq "$(printf '%s' "$md" | grep -c 'automated bot chatter')" "0" "github-actions comment excluded"
assert_eq "$(printf '%s' "$md" | grep -c 'AI Report')" "0" "prior AI Report comment excluded"
assert_eq "$(printf '%s' "$md" | grep -c 'Reproduction: incomplete')" "0" "incomplete-repro comment excluded"
assert_eq "$(printf '%s' "$md" | grep -c 'gh-aw-comment-type')" "0" "gh-aw-comment-type comment excluded"
assert_contains "$(cat "$d/stdout.log")" "prefetched issue.md" "reports the prefetched issue.md size"

# ---------------------------------------------------------------------------------------------------
# Attachment-host allowlist: only github.com/user-attachments/assets and
# user-images.githubusercontent.com are fetched; anything else (or non-https) is ignored.
# ---------------------------------------------------------------------------------------------------
fix='{"title":"T","body":"see https://github.com/user-attachments/assets/pngimg-aaa and https://user-images.githubusercontent.com/2/pngimg-bbb.png but not https://evil.com/user-attachments/assets/pngimg-xxx nor https://raw.githubusercontent.com/o/r/pngimg-yyy.png nor http://github.com/user-attachments/assets/pngimg-zzz","comments":[]}'
d=$(run_fetch "$fix"); rc=$?
log=$(cat "$d/curl.log")
assert_eq "$rc" "0" "allowlist path exits 0"
assert_contains "$log" "github.com/user-attachments/assets/pngimg-aaa" "user-attachments asset host is fetched"
assert_contains "$log" "user-images.githubusercontent.com/2/pngimg-bbb" "githubusercontent user-images host is fetched"
assert_eq "$(printf '%s' "$log" | grep -c 'evil.com')" "0" "arbitrary host is never fetched"
assert_eq "$(printf '%s' "$log" | grep -c 'raw.githubusercontent')" "0" "raw.githubusercontent host is rejected"
assert_eq "$(printf '%s' "$log" | grep -c 'pngimg-zzz')" "0" "non-https (http://) attachment is rejected"

# ---------------------------------------------------------------------------------------------------
# Count cap: at most 3 attachments are fetched even when more are present.
# ---------------------------------------------------------------------------------------------------
fix='{"title":"T","body":"a https://user-images.githubusercontent.com/1/pngimg-a.png b https://user-images.githubusercontent.com/1/pngimg-b.png c https://user-images.githubusercontent.com/1/pngimg-c.png d https://user-images.githubusercontent.com/1/pngimg-d.png e https://user-images.githubusercontent.com/1/pngimg-e.png","comments":[]}'
d=$(run_fetch "$fix"); rc=$?
assert_eq "$rc" "0" "count-cap path exits 0"
assert_eq "$(grep -c . "$d/curl.log")" "3" "no more than 3 attachments are fetched (head -3)"

# ---------------------------------------------------------------------------------------------------
# Magic-byte gate: images are kept (renamed by mime); non-image payloads are discarded.
# ---------------------------------------------------------------------------------------------------
fix='{"title":"T","body":"keep https://github.com/user-attachments/assets/pngimg-keep drop https://user-images.githubusercontent.com/1/notimg-drop.bin","comments":[]}'
d=$(run_fetch "$fix"); rc=$?
assert_eq "$rc" "0" "magic-byte path exits 0"
assert_eq "$(grep -c . "$d/curl.log")" "2" "both allowed URLs are fetched"
assert_eq "$(ls "$d/issue-assets" 2>/dev/null | grep -c .)" "1" "only the real image is kept on disk"
assert_eq "$(ls "$d/issue-assets" 2>/dev/null | grep -c '\.png$')" "1" "kept png is named by its mime type"
assert_contains "$(cat "$d/stdout.log")" "prefetched 1 screenshot(s)" "reports the single kept screenshot"

# ---------------------------------------------------------------------------------------------------
# A failed download leaves no artifact behind, and an all-empty issue-assets dir is removed.
# ---------------------------------------------------------------------------------------------------
fix='{"title":"T","body":"boom https://github.com/user-attachments/assets/pngimg-fail","comments":[]}'
d=$(run_fetch "$fix"); rc=$?
assert_eq "$rc" "0" "failed-download path exits 0"
assert_eq "$(grep -c . "$d/curl.log")" "1" "the failing URL was attempted"
assert_eq "$([ -d "$d/issue-assets" ] && echo present || echo gone)" "gone" "empty issue-assets dir is cleaned up"
assert_eq "$(grep -c 'screenshot' "$d/stdout.log")" "0" "no screenshot line when nothing was kept"

# ---------------------------------------------------------------------------------------------------
# No attachment URLs at all -> issue-assets is removed, only the issue.md line is printed.
# ---------------------------------------------------------------------------------------------------
d=$(run_fetch '{"title":"T","body":"plain text, no attachments","comments":[]}'); rc=$?
assert_eq "$rc" "0" "no-attachment path exits 0"
assert_eq "$(grep -c . "$d/curl.log")" "0" "curl is never invoked without attachments"
assert_eq "$([ -d "$d/issue-assets" ] && echo present || echo gone)" "gone" "issue-assets removed when unused"

# ---------------------------------------------------------------------------------------------------
# Context cap: issue.md is truncated to at most 60000 bytes.
# ---------------------------------------------------------------------------------------------------
big=$(printf 'A%.0s' $(seq 1 70000))
d=$(run_fetch "{\"title\":\"T\",\"body\":\"$big\",\"comments\":[]}"); rc=$?
assert_eq "$rc" "0" "oversized-body path exits 0"
assert_eq "$(wc -c < "$d/issue.md" | tr -d ' ')" "60000" "issue.md is capped at 60000 bytes"

# ---------------------------------------------------------------------------------------------------
# Missing issue: gh fails, issue.md falls back to the placeholder and the step still succeeds.
# ---------------------------------------------------------------------------------------------------
d=$(run_fetch '{"title":"unused","body":"unused","comments":[]}' GH_MODE=fail); rc=$?
assert_eq "$rc" "0" "gh failure does not abort the step"
assert_eq "$(cat "$d/issue.md")" "(issue unavailable)" "issue.md falls back to the placeholder"
assert_eq "$(grep -c . "$d/curl.log")" "0" "no attachment fetches on the unavailable path"

# ---------------------------------------------------------------------------------------------------
# Required-env validation: ISSUE and REPO/GITHUB_REPOSITORY are enforced.
# ---------------------------------------------------------------------------------------------------
d=$(mktemp -d); make_stub_bin "$d"
( cd "$d" && env -u ISSUE PATH="$d/bin:$PATH" REPO=owner/repo bash "$STEPS/fetch-issue.sh" >/dev/null 2>"$d/err" )
rc=$?
assert_eq "$(test "$rc" -ne 0 && echo nonzero)" "nonzero" "missing ISSUE aborts with a nonzero exit"
assert_contains "$(cat "$d/err")" "ISSUE is required" "missing ISSUE reports a helpful message"

d=$(mktemp -d); make_stub_bin "$d"
( cd "$d" && env -u REPO -u GITHUB_REPOSITORY PATH="$d/bin:$PATH" ISSUE=42 bash "$STEPS/fetch-issue.sh" >/dev/null 2>"$d/err" )
rc=$?
assert_eq "$(test "$rc" -ne 0 && echo nonzero)" "nonzero" "missing REPO and GITHUB_REPOSITORY aborts"

finish
