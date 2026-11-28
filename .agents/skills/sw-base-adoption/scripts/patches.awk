# Ranks reusable-surface items and derives their before tokens from patches.
# Input files: the R target rows of classify.awk, stop-tokens.txt, then the -U0 patch stream
# of the target commits ("\001commit <sha>" marker lines followed by the patch).
# Output rows (tab separated):
#   I id class commit path detail rank commits
#   K id token sites
function header_path(line,   p) {
    p = substr(line, 5)
    sub(/\t$/, "", p)
    if (p == "/dev/null") return ""
    return substr(p, 3)
}
function file_has_word(f, w,   k, n) {
    n = N["+", f]
    for (k = 1; k <= n; k++) if (has_word(L["+", f, k], w)) return 1
    return 0
}
# Identifiers of four or more characters, the leftmost-longest matches of [A-Za-z_][A-Za-z0-9_]{3,}.
function collect_tokens(kind, f, set,   k, n, m, parts, j, p) {
    n = N[kind, f]
    for (k = 1; k <= n; k++) {
        m = split(L[kind, f, k], parts, /[^A-Za-z0-9_]+/)
        for (j = 1; j <= m; j++) {
            p = parts[j]
            sub(/^[0-9]+/, "", p)
            if (length(p) >= 4) set[p] = 1
        }
    }
}
function emit_reusable(i,   item, sym, own, nf, f, ncs, cs, c, added, removed, t, cnt, need) {
    item = r_item[commit, i]; sym = r_sym[commit, i]; own = r_path[commit, i]
    ncs = 0
    for (nf = 1; nf <= file_count; nf++) {
        f = file_list[nf]
        if (f != own && file_has_word(f, sym)) cs[++ncs] = f
    }
    split("", cnt)
    for (c = 1; c <= ncs; c++) {
        split("", added); split("", removed)
        collect_tokens("+", cs[c], added)
        collect_tokens("-", cs[c], removed)
        for (t in removed) if (!(t in added)) cnt[t]++
    }
    need = (ncs == 1) ? 1 : 2
    for (t in cnt) if (cnt[t] >= need && !(t in stop)) print "K\t" item "\t" t "\t" cnt[t]
    print "I\t" item "\treusable-surface\t" commit "\t" own "\t" sym "\t" ncs "\t" commit
}
function finish(   i) {
    if (commit == "") return
    for (i = 1; i <= r_count[commit]; i++) emit_reusable(i)
}
BEGIN { FS = "\t" }
FILENAME == targets_file {
    n = ++r_count[$2]; r_item[$2, n] = $3; r_path[$2, n] = $4; r_sym[$2, n] = $5
    next
}
FILENAME == stop_file { if ($0 != "") stop[$0] = 1; next }
substr($0, 1, 8) == "\001commit " {
    finish()
    commit = substr($0, 9); state = "none"; file_count = 0
    split("", L); split("", N); split("", seen); split("", file_list)
    next
}
/^diff --git / { state = "header"; minus = ""; next }
state == "header" {
    if (substr($0, 1, 4) == "--- ") { minus = header_path($0); next }
    if (substr($0, 1, 4) == "+++ ") {
        file = header_path($0)
        if (file == "") file = minus
        if (!(file in seen)) { seen[file] = 1; file_list[++file_count] = file }
        next
    }
    if (substr($0, 1, 2) == "@@") state = "hunk"
    next
}
state == "hunk" {
    c = substr($0, 1, 1)
    if (c == "+") { k = ++N["+", file]; L["+", file, k] = substr($0, 2) }
    if (c == "-") { k = ++N["-", file]; L["-", file, k] = substr($0, 2) }
}
END { finish() }
