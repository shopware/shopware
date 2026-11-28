# Turns git grep lines of the owned files into candidates and symbol hits.
# Input files: tokens (id token sites), items (id class commit path detail rank), commit files (commit path),
# then git grep lines as "<prefix><path>\t<line>\t<text>", prefix being the "<tree-ish>:" that was searched.
# Output rows (tab separated):
#   C id token path line text   a candidate line, text cut to 200 characters with tabs as spaces
#   Y id symbol_hits
BEGIN { FS = "\t" }
FILENAME == tokens_file {
    if (!(($1, $2) in pair)) { pair[$1, $2] = 1; n = ++tok_n[$2]; tok_item[$2, n] = $1; has_tokens[$1] = 1 }
    next
}
FILENAME == items_file {
    item_commit[$1] = $3
    if (!($1 in has_tokens)) next
    # An identifier symbol is found through the word runs below; any other symbol needs has_word.
    if ($5 ~ /^[A-Za-z_][A-Za-z0-9_]*$/) { n = ++sym_n[$5]; sym_item[$5, n] = $1; next }
    other_n++; other_sym[other_n] = $5; other_item[other_n] = $1
    next
}
FILENAME == commit_files_file { in_commit[$1, $2] = 1; next }
{
    path = substr($1, length(prefix) + 1)
    line = $2
    text = substr($0, length($1) + length($2) + 3)
    shown = text
    gsub(/\t/, " ", shown)
    shown = substr(shown, 1, 200)
    m = split(text, runs, /[^A-Za-z0-9_]+/)
    split("", done)
    for (j = 1; j <= m; j++) {
        r = runs[j]
        if (r == "" || (r in done)) continue
        done[r] = 1
        for (k = 1; k <= tok_n[r]; k++) {
            item = tok_item[r, k]
            if (!((item_commit[item], path) in in_commit)) print "C\t" item "\t" r "\t" path "\t" line "\t" shown
        }
        for (k = 1; k <= sym_n[r]; k++) {
            item = sym_item[r, k]
            if (!((item_commit[item], path) in in_commit)) sym_hits[item]++
        }
    }
    for (k = 1; k <= other_n; k++) {
        item = other_item[k]
        if (!((item_commit[item], path) in in_commit) && has_word(text, other_sym[k])) sym_hits[item]++
    }
}
END { for (item in sym_hits) print "Y\t" item "\t" sym_hits[item] }
