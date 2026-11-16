#!/bin/bash
#
# Archive the server's files (sites, configs...) into one tar archive.
#
# Usage:
#   files-backup.sh [--force] [--list] [--debug-run]
#
#   --force   back up now even if the last backup is younger than FilesPeriod
#   --list    print the final include and exclude lists and exit
#   --debug-run  dry run: test that every target is writable (writes and removes a
#             tiny file), measure the files that would be archived and print the
#             maximum archive size (uncompressed) next to the free space. No backup
#             is made and nothing is pruned.
#
# What is archived comes from /etc/emps-factory/backup.d/ (BackupDir):
#   *.include   one absolute path per line; "- /path" removes an earlier entry
#   *.exclude   tar wildcard patterns
# Files are read in name order. 00-default.* ships with the tools and is
# overwritten on upgrade; add your own as 50-name.include / 50-name.exclude.
#
# The archive is <BackupRoot>/files/YYYY-MM-DD_HHMM/files.tar.zst (or .tar.gz /
# .tar, see BackupCompression), copied to every enabled Target and pruned to
# FilesKeepPrevious backups. With FilesLocalCopy no it is streamed straight to a
# single rclone target instead. Restore with: tar -xf files.tar.zst -C /
#
# All settings: /etc/emps-factory/factory.conf (see conf/factory.conf).

set -uo pipefail

for _d in "${EF_LIB:-/usr/local/lib/emps-factory}" "$(dirname "$(readlink -f "$0")")/lib"; do
  [ -r "$_d/common.sh" ] && { . "$_d/common.sh"; break; }
done
type ef_conf_load >/dev/null 2>&1 || { echo "ERROR: common.sh not found" >&2; exit 1; }

FORCE=0; LIST_ONLY=0; DEBUG_RUN=0
for a in "$@"; do
  case "$a" in
    --force) FORCE=1 ;;
    --list)  LIST_ONLY=1 ;;
    --debug-run) DEBUG_RUN=1 ;;
    -h|--help) sed -n '3,27p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "usage: $0 [--force] [--list] [--debug-run]" >&2; exit 1 ;;
  esac
done

umask 077
ef_conf_load

BACKUP_ROOT="$(ef_get backuproot /srv/backups)"
BACKUP_DIR="$BACKUP_ROOT/files"
LOG_FILE="$(ef_get fileslogfile /var/log/files-backup.log)"
ef_log_to "$LOG_FILE"

PERIOD="$(ef_get filesperiod 1d)"
PERIOD_S="$(ef_period_seconds "$PERIOD")" || ef_die "bad FilesPeriod '$PERIOD' (use 12h, 1d, 1w ...)"
KEEP="$(ef_get fileskeepprevious 7)"
[[ "$KEEP" =~ ^[0-9]+$ ]] || ef_die "bad FilesKeepPrevious '$KEEP' (expected a number)"

LISTS_DIR="$(ef_get backupdir /etc/emps-factory/backup.d)"
USE_DEFAULTS=1; ef_bool "$(ef_get backupdefaults yes)" || USE_DEFAULTS=0
EXCLUDE_TAG="$(ef_get backupexcludetag .nobackup)"
ONE_FS=0; ef_bool "$(ef_get backuponefilesystem no)" && ONE_FS=1
LOCAL_COPY=1; ef_bool "$(ef_get fileslocalcopy yes)" || LOCAL_COPY=0

# Compression: zstd | gzip | none
COMP="$(ef_get backupcompression zstd)"
case "${COMP,,}" in
  zstd) EXT=tar.zst; LEVEL="$(ef_get backupcompressionlevel 3)"
        command -v zstd >/dev/null 2>&1 || ef_die "zstd is not installed (apt install zstd) or set BackupCompression gzip|none"
        COMPRESS=(zstd -q -T0 "-$LEVEL"); TESTC=(zstd -tq) ;;
  gzip) EXT=tar.gz;  LEVEL="$(ef_get backupcompressionlevel 6)"
        if command -v pigz >/dev/null 2>&1; then COMPRESS=(pigz "-$LEVEL"); else COMPRESS=(gzip "-$LEVEL"); fi
        TESTC=(gzip -t) ;;
  none) EXT=tar;     COMPRESS=(cat); TESTC=() ;;
  *) ef_die "bad BackupCompression '$COMP' (use zstd, gzip or none)" ;;
esac
[[ "${LEVEL:-1}" =~ ^[0-9]+$ ]] || ef_die "bad BackupCompressionLevel '$LEVEL'"

###############################################################################
# Include / exclude lists
###############################################################################

INC=()        # final include paths, in order
EXC=()        # final exclude patterns

_trim() { local s="$1"; s="${s#"${s%%[![:space:]]*}"}"; printf '%s' "${s%"${s##*[![:space:]]}"}"; }

list_files() {   # list_files EXT -> list files sorted by name, honoring BackupDefaults
  local f
  [ -d "$LISTS_DIR" ] || return 0
  while IFS= read -r f; do
    if [ "$USE_DEFAULTS" -eq 0 ] && [[ "$(basename "$f")" == 00-default.* ]]; then continue; fi
    echo "$f"
  done < <(compgen -G "$LISTS_DIR/*.$1" | LC_ALL=C sort)
}

inc_has() { local x; for x in "${INC[@]}"; do [ "$x" = "$1" ] && return 0; done; return 1; }

inc_remove() {
  local out=() x
  for x in "${INC[@]}"; do [ "$x" != "$1" ] && out+=("$x"); done
  INC=("${out[@]}")
}

# verify_archive FILE: is the finished archive readable?
verify_archive() {
  if [ "${#TESTC[@]}" -eq 0 ]; then tar -tf "$1" >/dev/null 2>&1; else "${TESTC[@]}" "$1" 2>/dev/null; fi
}

build_lists() {
  local f line p n
  INC=(); EXC=()
  while IFS= read -r f; do
    [ -n "$f" ] || continue
    n=0
    while IFS= read -r line || [ -n "$line" ]; do
      n=$((n + 1))
      line="${line%$'\r'}"; line="${line%%[[:space:]]#*}"; line="$(_trim "$line")"
      if [[ -z "$line" || "$line" == \#* ]]; then continue; fi
      if [[ "$line" =~ ^-[[:space:]]+(.+)$ ]]; then            # "- /path": remove
        p="$(_trim "${BASH_REMATCH[1]}")"; [ "$p" = / ] || p="${p%/}"
        if inc_has "$p"; then inc_remove "$p"; ef_log "DROP $p (by $(basename "$f"))"
        else ef_err "WARN: $f:$n: '- $p' matches no earlier include entry"; fi
        continue
      fi
      if [[ "$line" != /* ]]; then ef_err "WARN: $f:$n: not an absolute path, ignored: $line"; continue; fi
      [ "$line" = / ] || line="${line%/}"
      if [ ! -e "$line" ] && [ ! -L "$line" ]; then ef_err "WARN: $f:$n: $line does not exist, skipped"; continue; fi
      inc_has "$line" || INC+=("$line")
    done < "$f"
  done < <(list_files include)

  while IFS= read -r f; do
    [ -n "$f" ] || continue
    while IFS= read -r line || [ -n "$line" ]; do
      line="${line%$'\r'}"; line="$(_trim "$line")"
      if [[ -z "$line" || "$line" == \#* ]]; then continue; fi
      EXC+=("$line")
    done < "$f"
  done < <(list_files exclude)

  # Never archive the backups themselves: BackupRoot and local target folders
  EXC+=("$BACKUP_ROOT")
  local t base
  for t in $(ef_targets_for files); do
    if [ "$(ef_tget "$t" type)" = local ]; then
      base="$(ef_expand "$(ef_tget "$t" path)")"; [ -n "$base" ] && EXC+=("${base%/}")
    fi
  done
}

if [ "$LIST_ONLY" -eq 1 ]; then
  build_lists
  echo "# include"; [ "${#INC[@]}" -gt 0 ] && printf '%s\n' "${INC[@]}"
  echo "# exclude"; printf '%s\n' "${EXC[@]}"
  exit 0
fi

###############################################################################
# --debug-run: targets writable? how big can the archive get?
###############################################################################

if [ "$DEBUG_RUN" -eq 1 ]; then
  human() { numfmt --to=iec --suffix=B "$1" 2>/dev/null || echo "$1 B"; }
  free_of() { df -PB1 "$1" 2>/dev/null | awk 'NR==2{print $4}'; }
  bad=0
  TEST="ef-debug-run-$$.tmp"

  echo "== Lists"
  build_lists
  echo "include: ${#INC[@]} paths, exclude: ${#EXC[@]} patterns, compression: $COMP"

  echo "== Targets"
  if [ "$LOCAL_COPY" -eq 1 ]; then
    mkdir -p "$BACKUP_DIR" 2>/dev/null
    if echo test > "$BACKUP_DIR/$TEST" 2>/dev/null; then
      rm -f "$BACKUP_DIR/$TEST"
      fb="$(free_of "$BACKUP_DIR")"
      echo "OK   local copy $BACKUP_DIR: writable, free $(human "${fb:-0}")"
    else
      echo "FAIL local copy $BACKUP_DIR: not writable"; bad=1
    fi
  else
    echo "     FilesLocalCopy no: nothing is kept in $BACKUP_ROOT"
  fi
  TFREE=()
  for t in $(ef_targets_for files); do
    case "$(ef_tget "$t" type)" in
      local)
        base="$(ef_expand "$(ef_tget "$t" path)")"
        if [ -z "$base" ]; then echo "FAIL $t: Path is not set"; bad=1; continue; fi
        ef_target_ready "$t" "$base" 2>&1 | sed 's/^[0-9-]* [0-9:]* /     /' ; [ "${PIPESTATUS[0]}" -eq 0 ] || { bad=1; continue; }
        if mkdir -p "$base/files" 2>/dev/null && echo test > "$base/files/$TEST" 2>/dev/null; then
          rm -f "$base/files/$TEST"
          fb="$(free_of "$base/files")"
          echo "OK   $t (local $base): writable, free $(human "${fb:-0}")"
        else
          echo "FAIL $t (local $base): not writable"; bad=1
        fi ;;
      rclone)
        if ! command -v rclone >/dev/null 2>&1; then echo "FAIL $t: rclone is not installed"; bad=1; continue; fi
        base="$(ef_expand "$(ef_tget "$t" remote)")"
        read -r -a flags <<< "$(ef_tget "$t" rcloneflags)"
        if echo test | _ef_rclone rcat "${flags[@]}" "$base/files/$TEST" 2>/dev/null \
           && _ef_rclone deletefile "${flags[@]}" "$base/files/$TEST" 2>/dev/null; then
          echo "OK   $t (rclone $base): wrote and removed a test file (free space: check on the storage side)"
        else
          echo "FAIL $t (rclone $base): cannot write a test file"; bad=1
        fi ;;
      *) echo "FAIL $t: unknown Type"; bad=1 ;;
    esac
  done

  echo "== Size (reading every file once, this can take a while)"
  [ "${#INC[@]}" -gt 0 ] || ef_die "the include list is empty (check $LISTS_DIR)"
  WORK="$(mktemp -d /tmp/files-backup.XXXXXX)" || ef_die "cannot create a temp dir"
  trap 'rm -rf "$WORK"' EXIT
  printf '%s\n' "${INC[@]}" > "$WORK/include.list"
  printf '%s\n' "${EXC[@]}" > "$WORK/exclude.list"
  DOPTS=(--numeric-owner)
  [ -n "$EXCLUDE_TAG" ] && DOPTS+=("--exclude-tag-all=$EXCLUDE_TAG")
  [ "$ONE_FS" -eq 1 ] && DOPTS+=(--one-file-system)
  # a pipe, not /dev/null: GNU tar skips reading file contents when writing to /dev/null
  bytes="$(tar -cf - "${DOPTS[@]}" -X "$WORK/exclude.list" -T "$WORK/include.list" 2>"$WORK/tar.err" | wc -c)"
  trc="${PIPESTATUS[0]}"
  [ "$trc" -le 1 ] || { echo "FAIL tar exited with $trc:"; head -n 5 "$WORK/tar.err"; bad=1; }
  echo "Maximum archive size: $(human "$bytes") ($bytes bytes, uncompressed tar)"
  [ "${COMP,,}" = none ] || echo "With $COMP the real archive will be smaller, so this is the upper limit"
  echo "Keeping $KEEP backups needs up to $(human $(( bytes * KEEP ))) on each target"
  [ "$bad" -eq 0 ] && echo "RESULT: OK" || echo "RESULT: PROBLEMS FOUND"
  exit "$bad"
fi

###############################################################################
# Run
###############################################################################

ef_lock files-backup

mkdir -p "$BACKUP_DIR" || ef_die "cannot create $BACKUP_DIR"
chmod 700 "$BACKUP_ROOT" "$BACKUP_DIR"

# Local history is what the skip-guard looks at; without local copies ask the
# target (a directory or an rclone remote) instead.
if [ "$LOCAL_COPY" -eq 1 ]; then
  LATEST="$(ef_list_dumps "$BACKUP_DIR" | tail -n 1)"
else
  LATEST=""
  STREAM_T="$(ef_targets_for files | head -n 1)"
  if [ -n "$STREAM_T" ]; then
    case "$(ef_tget "$STREAM_T" type)" in
      local)
        LATEST="$(ef_list_dumps "$(ef_expand "$(ef_tget "$STREAM_T" path)")/files" | tail -n 1)" ;;
      rclone)
        LATEST="$(_ef_rclone lsf --dirs-only "$(ef_expand "$(ef_tget "$STREAM_T" remote)")/files" 2>/dev/null \
                  | sed 's#/$##' | grep -E "$EF_DUMP_NAME_RE" | LC_ALL=C sort | tail -n 1)" ;;
    esac
  fi
fi
if [ "$FORCE" -ne 1 ] && [ -n "$LATEST" ]; then
  AGE=$(( $(date +%s) - $(ef_dump_epoch "$LATEST") ))
  if [ "$AGE" -lt $(( PERIOD_S * 9 / 10 )) ]; then
    ef_log "SKIP last backup $LATEST is only $((AGE / 60)) min old (FilesPeriod $PERIOD)"
    exit 0
  fi
fi

# Build the lists only now, so that skipped runs do not repeat their warnings
build_lists
[ "${#INC[@]}" -gt 0 ] || ef_die "the include list is empty (check $LISTS_DIR)"

STAMP="$(date +%F_%H%M)"
WORK="$(mktemp -d /tmp/files-backup.XXXXXX)" || ef_die "cannot create a temp dir"
trap 'rm -rf "$WORK"' EXIT
printf '%s\n' "${INC[@]}" > "$WORK/include.list"
printf '%s\n' "${EXC[@]}" > "$WORK/exclude.list"

# -X must come before -T: tar ignores exclude options given after the file list.
TAR_OPTS=(--numeric-owner)
[ -n "$EXCLUDE_TAG" ] && TAR_OPTS+=("--exclude-tag-all=$EXCLUDE_TAG")
[ "$ONE_FS" -eq 1 ] && TAR_OPTS+=(--one-file-system)
TAR_CMD=(tar -cf - "${TAR_OPTS[@]}" -X "$WORK/exclude.list" -T "$WORK/include.list")

# tar exits 1 when files changed while being read (normal on live sites): a
# warning; 2 or more is a failure.
tar_ok() { [ "$1" -le 1 ]; }

fail=0

if [ "$LOCAL_COPY" -eq 1 ]; then
  DEST="$BACKUP_DIR/$STAMP"
  [ -e "$DEST" ] && ef_die "$DEST already exists"
  rm -rf -- "$DEST.partial"
  mkdir -p "$DEST.partial" || ef_die "cannot create $DEST.partial"
  chmod 700 "$DEST.partial"
  OUT="$DEST.partial/files.$EXT"

  "${TAR_CMD[@]}" 2>"$WORK/tar.err" | "${COMPRESS[@]}" > "$OUT.tmp"
  st=("${PIPESTATUS[@]}")
  if tar_ok "${st[0]}" && [ "${st[1]}" -eq 0 ] \
     && verify_archive "$OUT.tmp"; then
    mv -f "$OUT.tmp" "$OUT"
    [ "${st[0]}" -eq 1 ] && ef_log "WARN tar: some files changed while being read"
    ef_log "OK   files.$EXT ($(du -h "$OUT" | cut -f1), ${#INC[@]} paths)"
    cp "$WORK/include.list" "$WORK/exclude.list" "$DEST.partial/"
    mv -- "$DEST.partial" "$DEST" || ef_die "cannot rename $DEST.partial"
    ef_prune_local "$BACKUP_DIR" "$KEEP"
    ef_push_all files "$DEST" "$STAMP" "$KEEP" fileskeepprevious || fail=1
  else
    ef_err "FAIL tar/compression (tar status ${st[0]}, $COMP status ${st[1]})"
    head -n 5 "$WORK/tar.err" >&2
    rm -rf -- "$DEST.partial"
    fail=1
  fi
else
  # Streaming, no local copy: tar | compressor straight into the target
  mapfile -t TS < <(ef_targets_for files)
  [ "${#TS[@]}" -eq 1 ] || ef_die "FilesLocalCopy no needs exactly one enabled target for files (found ${#TS[@]})"
  t="${TS[0]}"
  ttype="$(ef_tget "$t" type)"
  tkeep="$(ef_tget "$t" fileskeepprevious "$KEEP")"
  [[ "$tkeep" =~ ^[0-9]+$ ]] || ef_die "bad FilesKeepPrevious '$tkeep' for target $t"

  case "$ttype" in
    local)
      # Into a directory, e.g. a mounted NAS or SSHFS folder (see RequireMount)
      base="$(ef_expand "$(ef_tget "$t" path)")"
      [ -n "$base" ] || ef_die "target $t: Path is not set"
      ef_target_ready "$t" "$base" || exit 1
      tdir="$base/files"; dest="$tdir/$STAMP"
      [ -e "$dest" ] && ef_die "$dest already exists"
      rm -rf -- "$dest.partial"
      mkdir -p "$dest.partial" || ef_die "cannot create $dest.partial"
      out="$dest.partial/files.$EXT"

      "${TAR_CMD[@]}" 2>"$WORK/tar.err" | "${COMPRESS[@]}" > "$out.tmp"
      st=("${PIPESTATUS[@]}")
      if tar_ok "${st[0]}" && [ "${st[1]}" -eq 0 ] && verify_archive "$out.tmp"; then
        mv -f "$out.tmp" "$out"
        [ "${st[0]}" -eq 1 ] && ef_log "WARN tar: some files changed while being read"
        ef_log "OK   files.$EXT written to $t: $dest ($(du -h "$out" | cut -f1), ${#INC[@]} paths)"
        cp "$WORK/include.list" "$WORK/exclude.list" "$dest.partial/"
        mv -- "$dest.partial" "$dest" || ef_die "cannot rename $dest.partial"
        ef_prune_local "$tdir" "$tkeep"
      else
        ef_err "FAIL writing to $t (tar ${st[0]}, $COMP ${st[1]})"
        head -n 5 "$WORK/tar.err" >&2
        rm -rf -- "$dest.partial"
        fail=1
      fi
      ;;
    rclone)
      # tar | compressor | rclone rcat
      command -v rclone >/dev/null 2>&1 || ef_die "rclone is not installed"
      base="$(ef_expand "$(ef_tget "$t" remote)")"
      read -r -a flags <<< "$(ef_tget "$t" rcloneflags)"
      dst="$base/files/$STAMP/files.$EXT"

      "${TAR_CMD[@]}" 2>"$WORK/tar.err" | "${COMPRESS[@]}" | _ef_rclone rcat "${flags[@]}" "$dst"
      st=("${PIPESTATUS[@]}")
      bytes="$(_ef_rclone size --json "${flags[@]}" "$base/files/$STAMP" 2>/dev/null | sed -n 's/.*"bytes": *\([0-9]*\).*/\1/p')"
      if tar_ok "${st[0]}" && [ "${st[1]}" -eq 0 ] && [ "${st[2]}" -eq 0 ] && [ "${bytes:-0}" -gt 0 ]; then
        [ "${st[0]}" -eq 1 ] && ef_log "WARN tar: some files changed while being read"
        ef_log "OK   streamed to $t: $dst ($bytes bytes, ${#INC[@]} paths)"
        _ef_prune_rclone "$base" files "$tkeep" "${flags[@]}"
      else
        ef_err "FAIL streaming to $t (tar ${st[0]}, $COMP ${st[1]}, rcat ${st[2]}, $bytes bytes at target)"
        head -n 5 "$WORK/tar.err" >&2
        _ef_rclone purge "${flags[@]}" "$base/files/$STAMP" >/dev/null 2>&1
        fail=1
      fi
      ;;
    *)
      ef_die "target $t has unknown Type '$ttype' (expected local or rclone)"
      ;;
  esac
fi

exit $fail
