# has_word: whether w occurs in t with no word character [A-Za-z0-9_] directly before or after it,
# the same boundary rule git grep -w applies.
function has_word(t, w,   lw, pos, i, before, after) {
    if (w == "" || index(t, w) == 0) return 0
    lw = length(w)
    pos = 0
    while ((i = index(substr(t, pos + 1), w)) > 0) {
        i += pos
        before = (i == 1) ? "" : substr(t, i - 1, 1)
        after = substr(t, i + lw, 1)
        if (before !~ /[A-Za-z0-9_]/ && after !~ /[A-Za-z0-9_]/) return 1
        pos = i
    }
    return 0
}
