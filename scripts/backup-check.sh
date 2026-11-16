#!/bin/bash
#
# Health check of the backup system. Prints machine-readable results and sets
# the exit code, so that EMPS Factory (or cron, or Nagios-style monitoring) can
# run it from time to time and report problems.
#
# Usage:
#   backup-check.sh [--json] [--no-remote] [--output FILE]
#
#   --json       print one JSON object instead of "name: value" lines
#   --no-remote  skip the targets (no rclone calls, no network); local checks only
#   --output FILE  write the result to FILE (atomically, mode 644) instead of the
#                  screen, so that a reader never sees a half-written file
#
# Exit code: 0 = ok, 1 = warnings, 2 = critical problems, 3 = the check itself
# could not run (bad config). The "status:" line says the same in words:
# ok, warn, crit or error.
#
# What is checked
#   * MySQL dumps in <BackupRoot>/mysql: newest one is not older than the
#     schedule allows, no FAILED marker, no empty or corrupt (gzip -t) file, no
#     database missing compared with the previous dump, size not suddenly much
#     smaller than the previous dump, no abandoned .partial directory.
#   * File archives in <BackupRoot>/files (only with FilesLocalCopy yes).
#   * Every enabled Target (local directory or rclone remote), for mysql and
#     files: reachable, newest backup fresh as per the schedule, size sane, a
#     MySQL backup holds as many files as the local dump of the same moment.
#   * Free space on the backup disk and on local targets.
#   * The cron file exists (when CronMysql / CronFiles are enabled).
#
# Settings (factory.conf), all optional:
#   CheckAgePercent      150  a backup may be this % of its period old (DumpPeriod,
#                             FilesPeriod) before it is critical
#   CheckSizeDropPercent 50   warn if a backup is smaller than this % of the previous
#   CheckMinFreePercent  10   warn below this % free disk (critical below half)
#   CheckIntegrity       yes  run gzip -t over the newest local MySQL dump
#   CheckRemote          yes  check the targets (same as not giving --no-remote)
#
# Output example ("name: value", one per line):
#   status: warn
#   problems: 1
#   problem.1: warn: target adman mysql: newest backup 2026-11-15_0317 is 1d 4h old (allowed 18h)
#   mysql.local.latest: 2026-11-16_0317
#   mysql.local.age_seconds: 3600
#   files.target.adman.bytes: 16456095038

set -uo pipefail

for _d in "${EF_LIB:-/usr/local/lib/emps-factory}" "$(dirname "$(readlink -f "$0")")/lib"; do
  [ -r "$_d/common.sh" ] && { . "$_d/common.sh"; break; }
done
type ef_conf_load >/dev/null 2>&1 || { echo "status: error"; echo "error: common.sh not found"; exit 3; }

JSON=0; REMOTE=1; OUT=""
while [ $# -gt 0 ]; do
  a="$1"; shift
  case "$a" in
    --json) JSON=1 ;;
    --no-remote) REMOTE=0 ;;
    --output) [ $# -gt 0 ] || { echo "usage: --output FILE" >&2; exit 3; }; OUT="$1"; shift ;;
    -h|--help) sed -n '3,39p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "usage: $0 [--json] [--no-remote] [--output FILE]" >&2; exit 3 ;;
  esac
done

if [ -n "$OUT" ]; then
  exec 3>&1                                  # keep the real stdout for messages
  exec >"$OUT.tmp" || { echo "cannot write $OUT.tmp" >&2; exit 3; }
  trap 'chmod 644 "$OUT.tmp"; mv -f "$OUT.tmp" "$OUT"' EXIT
fi

###############################################################################
# Output
###############################################################################

K=(); V=()          # key/value pairs, in order
PL=(); PM=()        # problems: level, message
LEVEL=0             # 0 ok, 1 warn, 2 crit

kv() { K+=("$1"); V+=("$2"); }

problem() {   # problem warn|crit MESSAGE...
  local lvl="$1"; shift
  PL+=("$lvl"); PM+=("$*")
  if [ "$lvl" = crit ]; then LEVEL=2; elif [ "$LEVEL" -lt 1 ]; then LEVEL=1; fi
}

jesc() {
  local s="$1"
  s="${s//\\/\\\\}"; s="${s//\"/\\\"}"
  s="${s//$'\n'/ }"; s="${s//$'\t'/ }"; s="${s//$'\r'/ }"
  printf '%s' "$s"
}

# An error inside the check itself (bad config): not a backup problem
ef_die() {
  if [ "$JSON" -eq 1 ]; then printf '{"status":"error","error":"%s"}\n' "$(jesc "$*")"
  else echo "status: error"; echo "error: $*"; fi
  exit 3
}

emit() {
  local status=ok i n=${#K[@]} sep
  [ "$LEVEL" -eq 1 ] && status=warn
  [ "$LEVEL" -eq 2 ] && status=crit
  if [ "$JSON" -eq 0 ]; then
    echo "status: $status"
    echo "host: $(hostname)"
    echo "checked_at: $(date -u +%FT%TZ)"
    echo "checked_at_epoch: $NOW"
    echo "problems: ${#PL[@]}"
    for ((i = 0; i < ${#PM[@]}; i++)); do echo "problem.$((i + 1)): ${PL[i]}: ${PM[i]}"; done
    for ((i = 0; i < n; i++)); do echo "${K[i]}: ${V[i]}"; done
  else
    printf '{"status":"%s","host":"%s","checked_at":"%s","checked_at_epoch":%s' \
      "$status" "$(jesc "$(hostname)")" "$(date -u +%FT%TZ)" "$NOW"
    printf ',"problems":['
    sep=""
    for ((i = 0; i < ${#PL[@]}; i++)); do
      printf '%s{"level":"%s","message":"%s"}' "$sep" "${PL[i]}" "$(jesc "${PM[i]}")"; sep=","
    done
    printf ']'
    for ((i = 0; i < n; i++)); do
      if [[ "${V[i]}" =~ ^[0-9]+$ ]]; then printf ',"%s":%s' "$(jesc "${K[i]}")" "${V[i]}"
      else printf ',"%s":"%s"' "$(jesc "${K[i]}")" "$(jesc "${V[i]}")"; fi
    done
    printf '}\n'
  fi
  exit "$LEVEL"
}

###############################################################################
# Helpers
###############################################################################

human() { numfmt --to=iec --suffix=B "$1" 2>/dev/null || echo "$1 B"; }

dur() {   # seconds -> "1d 4h", "3h 20m", "12m"
  local s="$1" d h m
  d=$((s / 86400)); h=$(( (s % 86400) / 3600 )); m=$(( (s % 3600) / 60 ))
  if [ "$d" -gt 0 ]; then echo "${d}d ${h}h"
  elif [ "$h" -gt 0 ]; then echo "${h}h ${m}m"
  else echo "${m}m"; fi
}

dir_bytes() { find "$1" -type f -printf '%s\n' 2>/dev/null | awk '{s+=$1} END{print s+0}'; }

num() { [[ "$1" =~ ^[0-9]+$ ]] || ef_die "bad $2 '$1' (expected a number)"; }

ef_conf_load
NOW="$(date +%s)"

ROOT="$(ef_get backuproot /srv/backups)"
AGE_PCT="$(ef_get checkagepercent 150)";        num "$AGE_PCT" CheckAgePercent
DROP_PCT="$(ef_get checksizedroppercent 50)";   num "$DROP_PCT" CheckSizeDropPercent
FREE_PCT="$(ef_get checkminfreepercent 10)";    num "$FREE_PCT" CheckMinFreePercent
ef_bool "$(ef_get checkintegrity yes)" || INTEGRITY=0; INTEGRITY="${INTEGRITY:-1}"
ef_bool "$(ef_get checkremote yes)" || REMOTE=0

MYSQL_PERIOD="$(ef_get dumpperiod 1d)"
FILES_PERIOD="$(ef_get filesperiod 1d)"
MYSQL_PERIOD_S="$(ef_period_seconds "$MYSQL_PERIOD")" || ef_die "bad DumpPeriod '$MYSQL_PERIOD'"
FILES_PERIOD_S="$(ef_period_seconds "$FILES_PERIOD")" || ef_die "bad FilesPeriod '$FILES_PERIOD'"
LOCAL_COPY=1; ef_bool "$(ef_get fileslocalcopy yes)" || LOCAL_COPY=0

# Age allowed for a backup of this kind: period * CheckAgePercent / 100, plus a
# little for a run that takes long and for cron jitter.
max_age() { local p="$1"; echo $(( p * AGE_PCT / 100 + 1800 )); }

# check_series PREFIX LABEL MAXAGE COUNT LATEST BYTES PREVIOUS_BYTES
check_series() {
  local p="$1" label="$2" max="$3" count="$4" latest="$5" bytes="$6" prev="$7" age
  kv "$p.count" "$count"
  if [ -z "$latest" ]; then
    kv "$p.latest" ""
    problem crit "$label: no backups found"
    return 1
  fi
  age=$(( NOW - $(ef_dump_epoch "$latest") ))
  kv "$p.latest" "$latest"
  kv "$p.age_seconds" "$age"
  kv "$p.max_age_seconds" "$max"
  kv "$p.bytes" "$bytes"
  [ "$age" -gt "$max" ] && problem crit "$label: newest backup $latest is $(dur "$age") old (allowed $(dur "$max"))"
  if [ "${bytes:-0}" -le 0 ]; then
    problem crit "$label: newest backup $latest is empty"
  elif [ -n "$prev" ] && [ "$prev" -gt 0 ] && [ $(( bytes * 100 )) -lt $(( prev * DROP_PCT )) ]; then
    problem warn "$label: newest backup $latest is $(human "$bytes"), much smaller than the previous one ($(human "$prev"))"
  fi
  return 0
}

# check_free PREFIX LABEL PATH
check_free() {
  local p="$1" label="$2" path="$3" line total free pct
  line="$(df -PB1 "$path" 2>/dev/null | awk 'NR==2{print $2, $4}')"
  [ -n "$line" ] || return 0
  read -r total free <<< "$line"
  [ "${total:-0}" -gt 0 ] || return 0
  pct=$(( free * 100 / total ))
  kv "$p.free_bytes" "$free"
  kv "$p.free_percent" "$pct"
  if [ "$pct" -lt $(( FREE_PCT / 2 )) ]; then problem crit "$label: only $pct% disk space free ($(human "$free"))"
  elif [ "$pct" -lt "$FREE_PCT" ]; then problem warn "$label: only $pct% disk space free ($(human "$free"))"; fi
}

###############################################################################
# Local MySQL dumps
###############################################################################

check_mysql_local() {
  local dir="$ROOT/mysql" p=mysql.local S=() n latest prev="" bytes pbytes=0 f
  mapfile -t S < <(ef_list_dumps "$dir")
  n=${#S[@]}; latest=""
  [ "$n" -gt 0 ] && latest="${S[n-1]}"
  [ "$n" -gt 1 ] && prev="${S[n-2]}"

  bytes=0; [ -n "$latest" ] && bytes="$(dir_bytes "$dir/$latest")"
  [ -n "$prev" ] && pbytes="$(dir_bytes "$dir/$prev")"
  check_series "$p" "local MySQL dumps" "$(max_age "$MYSQL_PERIOD_S")" "$n" "$latest" "$bytes" "$pbytes" || return 0

  # Databases: how many, which are missing against the previous dump
  local dbs=() pdbs=() missing
  mapfile -t dbs < <(cd "$dir/$latest" && ls -1 ./*.sql.gz 2>/dev/null | sed 's#^\./##; s#\.sql\.gz$##' | LC_ALL=C sort)
  kv "$p.databases" "${#dbs[@]}"
  [ "${#dbs[@]}" -eq 0 ] && problem crit "local MySQL dumps: $latest has no database files"
  if [ -n "$prev" ]; then
    mapfile -t pdbs < <(cd "$dir/$prev" && ls -1 ./*.sql.gz 2>/dev/null | sed 's#^\./##; s#\.sql\.gz$##' | LC_ALL=C sort)
    missing="$(comm -13 <(printf '%s\n' "${dbs[@]}") <(printf '%s\n' "${pdbs[@]}") | tr '\n' ' ')"
    [ -n "${missing// /}" ] && problem warn "local MySQL dumps: $latest lacks databases present in $prev: ${missing% }"
  fi

  # A dump run that failed for some databases leaves a FAILED file
  if [ -s "$dir/$latest/FAILED" ]; then
    problem crit "local MySQL dumps: $latest has failed databases: $(tr '\n' ' ' < "$dir/$latest/FAILED" | sed 's/ $//')"
    kv "$p.failed" "$(tr '\n' ' ' < "$dir/$latest/FAILED" | sed 's/ $//')"
  else
    kv "$p.failed" ""
  fi

  # Empty or damaged files
  local empty=() bad=()
  for f in "$dir/$latest"/*.sql.gz; do
    [ -e "$f" ] || continue
    if [ ! -s "$f" ]; then empty+=("$(basename "$f" .sql.gz)"); continue; fi
    if [ "$INTEGRITY" -eq 1 ] && ! gzip -t "$f" 2>/dev/null; then bad+=("$(basename "$f" .sql.gz)"); fi
  done
  [ "${#empty[@]}" -gt 0 ] && problem crit "local MySQL dumps: empty files in $latest: ${empty[*]}"
  [ "${#bad[@]}" -gt 0 ] && problem crit "local MySQL dumps: corrupt (gzip -t) files in $latest: ${bad[*]}"
  [ "$INTEGRITY" -eq 1 ] && kv "$p.integrity_checked" yes

  # A dump that has been running or has hung for hours
  local stale
  stale="$(find "$dir" -mindepth 1 -maxdepth 1 -name '*.partial' -mmin +180 -printf '%f ' 2>/dev/null)"
  [ -n "$stale" ] && problem warn "local MySQL dumps: abandoned unfinished dump(s): ${stale% }"
}

###############################################################################
# Local file archives (only when FilesLocalCopy yes)
###############################################################################

check_files_local() {
  local dir="$ROOT/files" p=files.local S=() n latest="" prev="" bytes=0 pbytes=0
  mapfile -t S < <(ef_list_dumps "$dir")
  n=${#S[@]}
  [ "$n" -gt 0 ] && latest="${S[n-1]}"
  [ "$n" -gt 1 ] && prev="${S[n-2]}"
  [ -n "$latest" ] && bytes="$(dir_bytes "$dir/$latest")"
  [ -n "$prev" ] && pbytes="$(dir_bytes "$dir/$prev")"
  check_series "$p" "local file archives" "$(max_age "$FILES_PERIOD_S")" "$n" "$latest" "$bytes" "$pbytes" || return 0
  local stale
  stale="$(find "$dir" -mindepth 1 -maxdepth 1 -name '*.partial' -mmin +720 -printf '%f ' 2>/dev/null)"
  [ -n "$stale" ] && problem warn "local file archives: abandoned unfinished archive(s): ${stale% }"
}

###############################################################################
# Targets
###############################################################################

RC=()
setup_rclone() {
  local cfg; cfg="$(ef_get rcloneconfig "")"
  RC=(rclone); [ -n "$cfg" ] && RC+=(--config "$cfg")
}

json_field() { sed -n "s/.*\"$2\": *\\([0-9]*\\).*/\\1/p" <<< "$1" | head -n 1; }

# rclone_series BASE KIND FLAGS... -> sets R_STAMPS (array), R_OK
rclone_stamps() {
  local base="$1" kind="$2"; shift 2
  local out
  R_STAMPS=()
  if ! out="$(timeout 180 "${RC[@]}" lsf --dirs-only "$@" "$base/$kind" 2>&1)"; then
    # a missing directory is "no backups yet" for rclone's s3 backend: empty output
    R_ERR="$(head -n 1 <<< "$out")"; return 1
  fi
  mapfile -t R_STAMPS < <(sed 's#/$##' <<< "$out" | grep -E "$EF_DUMP_NAME_RE" | LC_ALL=C sort)
  return 0
}

check_target() {   # check_target TARGET KIND
  local t="$1" kind="$2" label p type base dir S=() n latest="" prev="" bytes=0 pbytes=0 period_s
  label="target $t $kind"; p="$kind.target.$t"
  type="$(ef_tget "$t" type)"
  [ "$kind" = mysql ] && period_s="$MYSQL_PERIOD_S" || period_s="$FILES_PERIOD_S"
  kv "$p.type" "$type"
  TFILES=""

  case "$type" in
    local)
      base="$(ef_expand "$(ef_tget "$t" path)")"
      local m; m="$(ef_expand "$(ef_tget "$t" requiremount)")"
      if [ -n "$m" ] && ! mountpoint -q "$m"; then
        kv "$p.reachable" no; problem crit "$label: $m is not mounted"; return
      fi
      if [ ! -d "$base" ]; then
        kv "$p.reachable" no; problem crit "$label: $base does not exist (is it mounted?)"; return
      fi
      kv "$p.reachable" yes
      dir="$base/$kind"
      mapfile -t S < <(ef_list_dumps "$dir")
      n=${#S[@]}
      [ "$n" -gt 0 ] && latest="${S[n-1]}"
      [ "$n" -gt 1 ] && prev="${S[n-2]}"
      [ -n "$latest" ] && bytes="$(dir_bytes "$dir/$latest")"
      [ -n "$prev" ] && pbytes="$(dir_bytes "$dir/$prev")"
      if [ "$kind" = mysql ] && [ -n "$latest" ]; then
        TFILES="$(find "$dir/$latest" -maxdepth 1 -name '*.sql.gz' | wc -l)"
        kv "$p.files" "$TFILES"
      fi
      [ -d "$dir" ] && check_free "$p" "$label" "$dir" || check_free "$p" "$label" "$base"
      ;;
    rclone)
      if ! command -v rclone >/dev/null 2>&1; then
        kv "$p.reachable" no; problem crit "$label: rclone is not installed"; return
      fi
      base="$(ef_expand "$(ef_tget "$t" remote)")"
      local flags=(); read -r -a flags <<< "$(ef_tget "$t" rcloneflags)"
      # Only connection-related flags matter for listing; chunk-size flags are harmless
      if ! rclone_stamps "$base" "$kind" "${flags[@]}"; then
        kv "$p.reachable" no; problem crit "$label: cannot list $base/$kind: ${R_ERR:-unknown error}"; return
      fi
      kv "$p.reachable" yes
      S=("${R_STAMPS[@]}"); n=${#S[@]}
      [ "$n" -gt 0 ] && latest="${S[n-1]}"
      [ "$n" -gt 1 ] && prev="${S[n-2]}"
      local js
      if [ -n "$latest" ]; then
        js="$(timeout 300 "${RC[@]}" size --json "${flags[@]}" "$base/$kind/$latest" 2>/dev/null)"
        bytes="$(json_field "$js" bytes)"; bytes="${bytes:-0}"
        TFILES="$(json_field "$js" count)"
        kv "$p.files" "$TFILES"
      fi
      if [ -n "$prev" ]; then
        js="$(timeout 300 "${RC[@]}" size --json "${flags[@]}" "$base/$kind/$prev" 2>/dev/null)"
        pbytes="$(json_field "$js" bytes)"; pbytes="${pbytes:-0}"
      fi
      ;;
    *) problem warn "$label: unknown Type '$type'"; return ;;
  esac

  check_series "$p" "$label" "$(max_age "$period_s")" "$n" "$latest" "$bytes" "$pbytes" || return

  # MySQL: the target must hold every database file of the local dump of the same moment
  if [ "$kind" = mysql ] && [ -d "$ROOT/mysql/$latest" ]; then
    local lcount
    lcount="$(find "$ROOT/mysql/$latest" -maxdepth 1 -name '*.sql.gz' | wc -l)"
    if [[ "$TFILES" =~ ^[0-9]+$ ]] && [ "$TFILES" -lt "$lcount" ]; then
      problem crit "$label: $latest has $TFILES file(s), the local dump has $lcount (incomplete upload?)"
    fi
  fi
}

###############################################################################
# Run the checks
###############################################################################

check_mysql_local
check_free mysql.local.disk "backup disk $ROOT" "$ROOT"
[ "$LOCAL_COPY" -eq 1 ] && check_files_local

if [ "$REMOTE" -eq 1 ]; then
  setup_rclone
  for kind in mysql files; do
    for t in $(ef_targets_for "$kind"); do check_target "$t" "$kind"; done
  done
else
  kv remote_checked no
fi

# Is the schedule installed?
CRON_FILE="${EF_CRON_FILE:-/etc/cron.d/emps-factory}"
if ef_bool "$(ef_get cronmysql yes)" || ef_bool "$(ef_get cronfiles yes)"; then
  if [ -r "$CRON_FILE" ]; then
    kv cron.installed yes
    ef_bool "$(ef_get cronmysql yes)" && ! grep -q 'mysql-dump-all' "$CRON_FILE" \
      && problem warn "cron: no MySQL dump job in $CRON_FILE"
    ef_bool "$(ef_get cronfiles yes)" && ! grep -q 'files-backup' "$CRON_FILE" \
      && problem warn "cron: no files backup job in $CRON_FILE"
  else
    kv cron.installed no
    problem warn "cron: $CRON_FILE does not exist (run emps-factory-cron.sh --install)"
  fi
fi

emit
