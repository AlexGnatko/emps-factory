#!/usr/bin/env bash
#
# tar-du.sh — show how much space each directory takes inside a tar archive.
#
#   tar-du.sh archive.tar              directories at levels 1, 2 and 3, top 25 per level
#   tar-du.sh archive.tar 4 40         up to level 4, top 40 per level
#   tar-du.sh f4.list                  same report from a saved listing (tar -tvf > f4.list), archive is not read
#   tar -tvf a.tar | tar-du.sh -       listing from standard input
#
#   LIST_OUT=f4.list tar-du.sh f4.tar  also save the listing to a file, so next time you can
#                                      feed it to the script instead of re-reading the archive
#
# Reads the archive once, sequentially (100+ GB takes a long time — run it in screen).
# A truncated or damaged archive is fine: the report covers whatever could be read.
# Only regular files are counted; sizes are uncompressed, as shown by tar -tv.
# Files sitting in a directory shallower than the current level are counted under their own
# directory, so the total is the same at every level (= the whole archive).

set -eu

usage() {
    sed -n '3,14p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
}

ARCH="${1:-}"
LEVELS="${2:-3}"
TOP="${3:-25}"
[[ -n "$ARCH" ]] || usage
[[ "$LEVELS" =~ ^[0-9]+$ && "$TOP" =~ ^[0-9]+$ ]] || usage

ERR="$(mktemp)"
trap 'rm -f "$ERR"' EXIT

# Source of the listing: "perms owner size date time path"
produce() {
    if [[ "$ARCH" == "-" ]]; then
        cat
    elif [[ "$ARCH" == *.list || "$ARCH" == *.txt ]]; then
        [[ -r "$ARCH" ]] || { echo "ERROR: cannot read $ARCH" >&2; exit 1; }
        cat -- "$ARCH"
    else
        [[ -r "$ARCH" ]] || { echo "ERROR: cannot read $ARCH" >&2; exit 1; }
        # a truncated archive makes tar exit non-zero — that is not a reason to stop
        tar --numeric-owner --quoting-style=literal -tvf "$ARCH" 2>"$ERR" || true
    fi
}

# Sums sizes per directory for levels 1..LEVELS, prints "level<TAB>bytes<TAB>files<TAB>directory".
# Level 0 is the grand total (4th field: number of lines that could not be parsed).
aggregate() {
    LC_ALL=C awk -v L="$LEVELS" '
    {
        if (substr($0, 1, 1) != "-") next                      # regular files only
        if (!match($0, /^[^ ]+ +[^ ]+ +[0-9]+ +[0-9][0-9-]+ +[0-9:]+ /)) { bad++; next }
        head = substr($0, 1, RLENGTH); path = substr($0, RLENGTH + 1)
        split(head, f, / +/); size = f[3] + 0
        sub(/^(\.\/)+/, "", path); sub(/^\/+/, "", path)
        n = split(path, p, "/")
        total += size; cnt++
        depth = n - 1                                          # number of directories in the path
        for (l = 1; l <= L; l++) {
            k = (depth < l) ? depth : l
            key = (k == 0) ? "(archive root)" : p[1]
            for (i = 2; i <= k; i++) key = key "/" p[i]
            B[l SUBSEP key] += size; C[l SUBSEP key]++
        }
    }
    END {
        printf "0\t%.0f\t%d\t%d\n", total, cnt, bad
        for (x in B) { split(x, a, SUBSEP); printf "%d\t%.0f\t%d\t%s\n", a[1], B[x], C[x], a[2] }
    }'
}

# Prints the report: the TOP largest directories at each level.
report() {
    LC_ALL=C awk -F'\t' -v TOP="$TOP" '
    function h(b) {
        if (b >= 1073741824) return sprintf("%.2f GiB", b / 1073741824)
        if (b >= 1048576)    return sprintf("%.1f MiB", b / 1048576)
        if (b >= 1024)       return sprintf("%.1f KiB", b / 1024)
        return sprintf("%d B", b)
    }
    function flush() {
        if (hidden > 0) printf "  %12s %8s  %6s  ... and %d more\n", h(hsize), "", "", hidden
        hidden = 0; hsize = 0
    }
    $1 == 0 {
        total = $2; tcount = $3; bad = $4
        printf "Files: %d, total: %s\n", tcount, h(total)
        if (bad > 0) printf "(lines that could not be parsed: %d)\n", bad
        next
    }
    $1 != cur {
        flush()
        cur = $1; shown = 0
        printf "\n=== Level %d ===\n", cur
        printf "  %12s %8s  %6s  %s\n", "size", "files", "share", "directory"
    }
    {
        if (shown < TOP) {
            pct = (total > 0) ? 100 * $2 / total : 0
            printf "  %12s %8d  %5.1f%%  %s\n", h($2), $3, pct, $4
            shown++
        } else { hidden++; hsize += $2 }
    }
    END { flush() }'
}

analyze() {
    aggregate | LC_ALL=C sort -t "$(printf '\t')" -k1,1n -k2,2nr | report
}

if [[ -n "${LIST_OUT:-}" ]]; then
    produce | tee "$LIST_OUT" | analyze
else
    produce | analyze
fi

if [[ -s "$ERR" ]]; then
    echo >&2
    echo "tar messages (if you see 'Unexpected EOF' the archive is truncated; the report covers the part that was read):" >&2
    head -n 5 "$ERR" >&2
fi
