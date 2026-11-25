# Classifies the name-status stream of the range commits with path-classes.tsv and gates.tsv.
# Input files: path-classes.tsv, gates.tsv, then the stream ("\001commit <sha>" marker lines followed by name-status lines).
# Output rows (tab separated):
#   F commit status path            every changed path
#   I id class commit path detail 0 commits
#                                   one item per class and path for the classes that need no patch; the stream
#                                   is newest first, so commit is the newest commit and commits lists all, newest first
#   R commit id path symbol         a reusable-surface target, ranked from the patch later
#   D commit path                   a dependency manifest, compared between the range ends later
function symbol_of(path,   n, parts, base, sym) {
    n = split(path, parts, "/")
    base = parts[n]
    if ((base == "index.ts" || base == "index.js") && n > 1) return parts[n - 1]
    sym = base
    sub(/\.[^.]*$/, "", sym)
    return sym
}
BEGIN { FS = "\t" }
FILENAME == classes_file {
    if (FNR == 1) next
    if (NF != 4) { print "error: path-classes.tsv line " FNR " does not have 4 columns" > "/dev/stderr"; failed = 1; exit 1 }
    nc++; c_class[nc] = $1; c_status[nc] = $2; c_re[nc] = $3
    next
}
FILENAME == gates_file {
    if (FNR == 1) next
    if (NF != 4) { print "error: gates.tsv line " FNR " does not have 4 columns" > "/dev/stderr"; failed = 1; exit 1 }
    ng++; g_re[ng] = $1; g_id[ng] = $2
    next
}
substr($0, 1, 8) == "\001commit " { commit = substr($0, 9); short = substr(commit, 1, 12); next }
NF == 0 { next }
{
    letter = substr($1, 1, 1)
    path = (letter == "R" || letter == "C") ? $3 : $2
    print "F\t" commit "\t" letter "\t" path
    cls = ""
    for (i = 1; i <= nc; i++) {
        if (c_status[i] != "*" && index(c_status[i], letter) == 0) continue
        if (path ~ c_re[i]) { cls = c_class[i]; break }
    }
    if (cls == "" || cls == "none") next
    if (cls == "dependency") { print "D\t" commit "\t" path; next }
    if (cls == "reusable-surface") { print "R\t" commit "\t" cls ":" short ":" path "\t" path "\t" symbol_of(path); next }
    # One migration tool is one item: its path is the tool's directory below codemods/.
    if (cls == "migration-tooling" && match(path, /(^|\/)codemods\/[^\/]+\//)) path = substr(path, 1, RSTART + RLENGTH - 2)
    key = cls SUBSEP path
    if (key in agg_commits) { agg_commits[key] = agg_commits[key] "," commit; next }
    detail = letter
    if (cls == "gate") {
        detail = "unmapped"
        for (i = 1; i <= ng; i++) if (path ~ g_re[i]) { detail = g_id[i]; break }
    }
    agg_commits[key] = commit
    agg_row[key] = "I\t" cls ":" path "\t" cls "\t" commit "\t" path "\t" detail "\t0"
}
END {
    if (failed) exit 1
    for (key in agg_row) print agg_row[key] "\t" agg_commits[key]
}
