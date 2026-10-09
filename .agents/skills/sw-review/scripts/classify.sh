#!/usr/bin/env bash
# Deterministic change classification for sw-review.
#
# Reads the changed-file list and the unified diff of a pull request and emits one
# JSON "change profile": path classes, impact signals, size, and the review guides
# selected from guides/index.json. No LLM is involved, so the routing is
# reproducible and covered by tests/routing/.
#
#   classify.sh --files files.txt --diff diff.patch [--base trunk] [--meta meta.json] \
#               [--root <checkout root>] [--index guides/index.json]
#
# files.txt: one repo-relative path per line (post-change paths; deleted files too).
# meta.json: optional {"fork": bool, "author_association": "...", "labels": [...]}.
# Signals are computed on added/removed lines only (never on context lines) and
# never inside tests/**; the one exception is "public_surface", which needs the
# checkout to confirm that the touched class is not @internal.
set -euo pipefail

FILES=""; DIFF=""; BASE="trunk"; META=""; ROOT="."; INDEX=""
while [ $# -gt 0 ]; do
  case "$1" in
    --files) FILES="$2"; shift 2 ;;
    --diff) DIFF="$2"; shift 2 ;;
    --base) BASE="$2"; shift 2 ;;
    --meta) META="$2"; shift 2 ;;
    --root) ROOT="$2"; shift 2 ;;
    --index) INDEX="$2"; shift 2 ;;
    *) echo "unknown argument: $1" >&2; exit 2 ;;
  esac
done
[ -n "$FILES" ] && [ -n "$DIFF" ] || { echo "usage: classify.sh --files files.txt --diff diff.patch [--base trunk] [--meta meta.json] [--root dir] [--index guides/index.json]" >&2; exit 2; }
[ -f "$FILES" ] || { echo "files list not found: $FILES" >&2; exit 2; }
[ -f "$DIFF" ] || { echo "diff not found: $DIFF" >&2; exit 2; }
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
[ -n "$INDEX" ] || INDEX="$SCRIPT_DIR/../guides/index.json"
[ -f "$INDEX" ] || { echo "guide index not found: $INDEX" >&2; exit 2; }
GUIDES_DIR="$(dirname "$INDEX")"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# --- changed lines per file: "<path>\t<+|->\t<line>" ------------------------------
# Only the +/- lines of hunks; the +++/--- headers and context lines are skipped.
awk '
  /^diff --git / { file=""; next }
  /^\+\+\+ / { file=$2; sub(/^b\//, "", file); next }
  /^--- /    { next }
  /^@@ /     { next }
  file != "" && /^\+/ { print file "\t+\t" substr($0, 2); next }
  file != "" && /^-/  { print file "\t-\t" substr($0, 2); next }
' "$DIFF" > "$WORK/lines.tsv"
# Deleted files have no +++ b/ path; take them from "deleted file mode" blocks.
awk '/^diff --git / { f=$3; sub(/^a\//, "", f) } /^deleted file mode/ { print f }' "$DIFF" | sort -u > "$WORK/deleted.txt"

# class_is_internal <file>: true when the docblock directly above the first class,
# interface, trait or enum declaration carries @internal. An @internal on a constructor
# or a method (which InternalMethodRule requires for DI service constructors) does not
# make the class internal.
class_is_internal() {
  [ -f "$1" ] || return 1
  awk '
    /^[[:space:]]*\/\*\*/ { indoc=1; doc=""; }
    indoc { doc = doc "\n" $0 }
    /\*\// { if (indoc) { indoc=0; lastdoc=doc; doc="" } }
    /^[[:space:]]*(abstract[[:space:]]+|final[[:space:]]+|readonly[[:space:]]+)*(class|interface|trait|enum)[[:space:]]/ {
      found = (lastdoc ~ /@internal/) ? 1 : 0; exit
    }
    END { exit found ? 0 : 1 }   # exit inside a rule still runs END; decide the status here
  ' "$1"
}

is_test_path() { case "$1" in tests/*|*/tests/*|*/Test/*|*/test/*|*.spec.*|*.test.*) return 0 ;; esac; return 1; }
is_php_src() { case "$1" in src/*.php) is_test_path "$1" && return 1; return 0 ;; esac; return 1; }

# Non-test changed lines for anchor matching.
awk -F'\t' '{ print }' "$WORK/lines.tsv" | while IFS=$'\t' read -r path sign line; do
  is_test_path "$path" && continue
  printf '%s\t%s\t%s\n' "$path" "$sign" "$line"
done > "$WORK/src_lines.tsv"

hit() { # hit <regex> [sign]  -> 0 when any non-test changed line matches
  local re="$1" sign="${2:-}"
  if [ -n "$sign" ]; then
    awk -F'\t' -v s="$sign" '$2 == s { print $3 }' "$WORK/src_lines.tsv" | grep -Eq -- "$re"
  else
    cut -f3 "$WORK/src_lines.tsv" | grep -Eq -- "$re"
  fi
}
hit_in() { # hit_in <path-glob> <regex> [sign] -> 0 when a non-test changed line of a matching file matches
  local g="$1" re="$2" sign="${3:-}"
  while IFS=$'\t' read -r path s line; do
    case "$path" in $g) ;; *) continue ;; esac
    [ -n "$sign" ] && [ "$s" != "$sign" ] && continue
    echo "$line" | grep -Eq -- "$re" && return 0
  done < "$WORK/src_lines.tsv"
  return 1
}
path_hit() { # path_hit <glob>  -> 0 when any changed path matches (bash glob, * crosses /)
  local g="$1" p
  while IFS= read -r p; do [ -n "$p" ] || continue; case "$p" in $g) return 0 ;; esac; done < "$FILES"
  return 1
}

# --- path classes ----------------------------------------------------------------
declare -A CLASSES=()
php_src=0; php_src_tests=0
while IFS= read -r p; do
  [ -n "$p" ] || continue
  case "$p" in
    tests/*|*/tests/*) CLASSES[tests]=1; php_src_tests=1 ;;
    src/Administration/*) CLASSES[admin]=1 ;;
    src/Storefront/*) CLASSES[storefront]=1 ;;
    src/*) CLASSES[core]=1 ;;
    .github/*|composer.json|composer.lock|package.json|package-lock.json|*.yaml|*.yml) CLASSES[config-build]=1 ;;
    UPGRADE-*.md|RELEASE_INFO-*.md|changelog/*) CLASSES[release-docs]=1 ;;
    *.lock|*.lock.yml|*/dist/*|*.min.js|*.snap) CLASSES[generated]=1 ;;
    *.md|docs/*|coding-guidelines/*|adr/*) CLASSES[docs]=1 ;;
    *) CLASSES[other]=1 ;;
  esac
  is_php_src "$p" && php_src=1
done < "$FILES"

# --- signals ---------------------------------------------------------------------
declare -A SIG=()
[ "$php_src" = 1 ] && SIG[php_src]=1
if [ "$php_src" = 1 ] && [ "$php_src_tests" = 0 ]; then SIG[php_src_without_tests]=1; fi

# public_surface: a candidate line in a non-test PHP file, confirmed only when the
# file is not @internal (checkout lookup; a deleted file counts as candidate).
if hit '^[[:space:]]*(public|protected) function |__construct\(' ; then
  while IFS=$'\t' read -r path sign line; do
    case "$path" in src/*.php) ;; *) continue ;; esac
    is_test_path "$path" && continue
    echo "$line" | grep -Eq '^[[:space:]]*(public|protected) function |__construct\(' || continue
    if grep -qx "$path" "$WORK/deleted.txt"; then SIG[public_surface]=1; SIG[removal]=1; break; fi
    if class_is_internal "$ROOT/$path"; then continue; fi
    SIG[public_surface]=1; break
  done < "$WORK/src_lines.tsv"
fi

hit '@deprecated|triggerDeprecationOrThrow\(|BCChange\\|<deprecated|silentUntil' && SIG[deprecation]=1
( path_hit 'UPGRADE-*.md' || path_hit 'RELEASE_INFO-*.md' ) && SIG[release_docs]=1
( path_hit 'src/Core/Migration/*' || path_hit '*/Migration/Migration*.php' || hit 'updateDestructive\(|extends MigrationStep' ) && SIG[migration]=1
( path_hit '*/ScheduledTask/*' || hit 'ScheduledTask|AsMessageHandler|MessageHandler|DelayStamp' ) && SIG[scheduled_task]=1
( path_hit 'src/*/Command/*.php' || hit '#\[AsCommand\(|extends Command\b' ) && SIG[command]=1
hit 'Request \$request|->request->|->query->|\$request->get\(' && SIG[request_input]=1
if [ "${SIG[scheduled_task]:-0}" = 1 ] && { [ "${SIG[command]:-0}" = 1 ] || [ "${SIG[request_input]:-0}" = 1 ]; }; then SIG[sync_path_change]=1; fi
( hit 'deleteDirectory\(|FilesystemOperator|->deleteDir\(|unlink\(|rmdir\(' || path_hit 'src/Storefront/Theme/*' || path_hit 'src/Core/Content/Media/*' ) && SIG[filesystem]=1
hit 'HttpCache|CacheInvalidator|cache\.tag|Cache-Control|ETag|addCacheTag|CacheTagCollector|CacheStore' && SIG[cache]=1
hit '#\[Route\(|#\[Acl\(|_acl' && SIG[acl_route]=1
( path_hit 'src/Administration/Resources/app/administration/src/*' ) && SIG[admin_ui]=1
( path_hit 'src/Storefront/Resources/views/*' || path_hit 'src/Storefront/Resources/app/storefront/src/*' || path_hit 'src/Storefront/Resources/app/storefront/*.scss' || path_hit 'src/*/Resources/snippet/*' ) && SIG[storefront_ui]=1
( path_hit '*config.xml' || path_hit '*/Resources/config/packages/*' || path_hit '*shopware.yaml' || path_hit '*feature.yaml' || hit '%env\(|getenv\(|ini_get\(' ) && SIG[config]=1
# di: a service *contract* change, not any edit of a DI file (those happen in most PHP PRs):
# alias, decoration, visibility, deprecation or tag changes, or a removed service definition.
# A removed definition, alias, tag or visibility line is a contract change; a new service with a tag is not.
for di_glob in 'src/*/DependencyInjection/*' 'src/*/Resources/config/*.xml'; do
  if hit_in "$di_glob" '->set\(|->alias\(|->tag\(|->public\(|->private\(|->decorate\(|decorates=|<service |<tag |alias=' '-' \
     || hit_in "$di_glob" '->decorate\(|decorates=|->alias\(|->deprecate\(|<deprecated|#\[AsDecorator' '+'; then SIG[di]=1; fi
done
( path_hit 'src/*/SalesChannel/*Route.php' || path_hit 'src/Core/*/Api/*' || path_hit 'src/Core/Framework/Api/ApiDefinition/Generator/Schema/*' ) && SIG[api_contract]=1
( path_hit 'src/*Event.php' || path_hit 'src/*/Abstract*.php' || hit 'extends Event\b|AbstractExtension|getDecorated\(' ) && SIG[extension_point]=1
if [ -s "$WORK/deleted.txt" ]; then
  while IFS= read -r d; do case "$d" in src/*) is_test_path "$d" || { SIG[removal]=1; break; } ;; esac; done < "$WORK/deleted.txt"
fi
hit '^[[:space:]]*(public|protected) function ' '-' && { [ "${SIG[public_surface]:-0}" = 1 ] && SIG[removal]=1 || true; }
hit '\{% block |sw_include|sw_extends' && SIG[twig]=1

# metadata
fork=false; assoc=""; labels='[]'
if [ -n "$META" ] && [ -f "$META" ]; then
  fork="$(jq -r '.fork // false' "$META")"; assoc="$(jq -r '.author_association // ""' "$META")"; labels="$(jq -c '.labels // []' "$META")"
fi
[ "$fork" = "true" ] && SIG[fork_pr]=1
if echo "$labels" | jq -e 'index("external-contribution") != null' >/dev/null; then SIG[external]=1; fi
case "$assoc" in CONTRIBUTOR|FIRST_TIME_CONTRIBUTOR|FIRST_TIMER|NONE) [ -n "$assoc" ] && SIG[external]=1 ;; esac
# fix_without_test: a "fix" PR title or fixes-link is known only from metadata; callers may set it.
if [ -n "$META" ] && [ -f "$META" ] && jq -e '.fixes_issue == true' "$META" >/dev/null 2>&1 && [ "$php_src_tests" = 0 ] && [ "$php_src" = 1 ]; then SIG[fix_without_test]=1; fi

# --- size --------------------------------------------------------------------------
n_files="$(grep -c . "$FILES" || true)"
n_lines="$(wc -l < "$WORK/lines.tsv" | tr -d ' ')"
over_cap=false; { [ "$n_files" -gt 200 ] || [ "$n_lines" -gt 5000 ]; } && over_cap=true

# --- guide selection ---------------------------------------------------------------
signals_json="$(printf '%s\n' "${!SIG[@]}" | sort | jq -R . | jq -s .)"
classes_json="$(printf '%s\n' "${!CLASSES[@]}" | sort | jq -R . | jq -s .)"
selected='[]'
n_guides="$(jq '.guides | length' "$INDEX")"
for ((i=0; i<n_guides; i++)); do
  g="$(jq -r ".guides[$i].guide" "$INDEX")"
  matched=()
  while IFS= read -r s; do [ -n "$s" ] && [ "${SIG[$s]:-0}" = 1 ] && matched+=("signal:$s"); done < <(jq -r ".guides[$i].signals_any // [] | .[]" "$INDEX")
  while IFS= read -r s; do [ -n "$s" ] && [ "${SIG[$s]:-0}" = 1 ] && matched+=("always:$s"); done < <(jq -r ".guides[$i].always_for // [] | .[]" "$INDEX")
  while IFS= read -r glob; do [ -n "$glob" ] && path_hit "$glob" && matched+=("path:$glob"); done < <(jq -r ".guides[$i].paths_any // [] | .[]" "$INDEX")
  while IFS= read -r re; do [ -n "$re" ] && hit "$re" && matched+=("anchor:$re"); done < <(jq -r ".guides[$i].anchors_any // [] | .[]" "$INDEX")
  [ "${#matched[@]}" -gt 0 ] || continue
  exists=true; [ -f "$GUIDES_DIR/$g.md" ] || exists=false
  personas="$(jq -c ".guides[$i].personas" "$INDEX")"
  mj="$(printf '%s\n' "${matched[@]}" | jq -R . | jq -s .)"
  selected="$(jq -c --arg g "$g" --argjson p "$personas" --argjson m "$mj" --argjson e "$exists" '. + [{guide:$g, personas:$p, matched_by:$m, file_exists:$e}]' <<<"$selected")"
done

jq -n \
  --arg base "$BASE" \
  --argjson classes "$classes_json" \
  --argjson signals "$signals_json" \
  --argjson fork "$fork" --arg assoc "$assoc" --argjson labels "$labels" \
  --argjson files "$n_files" --argjson lines "$n_lines" --argjson over "$over_cap" \
  --argjson guides "$selected" \
  '{base_ref:$base, path_classes:$classes, signals:$signals, metadata:{fork:$fork, author_association:$assoc, labels:$labels}, size:{files:$files, changed_lines:$lines, over_cap:$over}, guides:$guides}'
