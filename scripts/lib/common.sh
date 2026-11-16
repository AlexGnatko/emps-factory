# shellcheck shell=bash
#
# EMPS Factory shell library: configuration parser, logging, locking,
# retention and backup-target helpers. Sourced by the scripts in this folder:
#
#   . /usr/local/lib/emps-factory/common.sh
#   ef_conf_load
#
# Configuration: /etc/emps-factory/factory.conf (override with EF_CONF_FILE),
# sshd-style syntax. See conf/factory.conf for the full description.
#
# Requires bash 4+ (associative arrays).

[ -n "${EF_COMMON_LOADED:-}" ] && return 0
EF_COMMON_LOADED=1

EF_CONF_FILE="${EF_CONF_FILE:-/etc/emps-factory/factory.conf}"

# Names of dump directories: YYYY-MM-DD (legacy, daily) or YYYY-MM-DD_HHMM
EF_DUMP_NAME_RE='^[0-9]{4}-[0-9]{2}-[0-9]{2}(_[0-9]{4})?$'

# Recognized keywords (lower case, no underscores)
EF_KNOWN_GLOBAL=" backuproot rcloneconfig dumpperiod keepprevious mysqluser
  mysqldefaultsfile mysqlexcludedb mysqlonlydb mysqlincludegrants
  mysqllegacycollation mysqllogfile
  filesperiod fileskeepprevious fileslogfile fileslocalcopy
  backupdir backupdefaults backupcompression backupcompressionlevel
  backupexcludetag backuponefilesystem
  cronmysql cronfiles cronoffsetminute cronstarthour
  cronfilesoffsetminute cronfilesstarthour cronmailto "
EF_KNOWN_TARGET=" type path requiremount remote keepprevious fileskeepprevious kinds enabled rcloneflags "

declare -A EF_S=()    # "scope|key" -> last value (scope is empty for globals)
declare -A EF_L=()    # "scope|key" -> all values, newline separated
EF_TARGETS=()         # target names in order of first appearance
EF_LOG_FILE=""

###############################################################################
# Logging
###############################################################################

# ef_log_to FILE: also append every ef_log message to FILE
ef_log_to() { EF_LOG_FILE="$1"; }

ef_log() {
  local msg
  msg="$(date '+%F %T') $*"
  echo "$msg"
  if [ -n "$EF_LOG_FILE" ]; then echo "$msg" >> "$EF_LOG_FILE"; fi
  return 0
}

ef_err() {
  local msg
  msg="$(date '+%F %T') $*"
  echo "$msg" >&2
  if [ -n "$EF_LOG_FILE" ]; then echo "$msg" >> "$EF_LOG_FILE"; fi
  return 0
}

ef_die() { ef_err "ERROR: $*"; exit 1; }

###############################################################################
# Configuration parser
###############################################################################

_ef_norm() { local k="${1,,}"; printf '%s' "${k//_/}"; }

_ef_check_file() {
  local file="$1" owner mode
  read -r owner mode < <(stat -c '%u %a' "$file") || ef_die "cannot stat $file"
  if [ "$owner" != 0 ] && [ "$owner" != "$EUID" ]; then
    ef_die "$file must be owned by root"
  fi
  if (( (8#$mode & 8#022) != 0 )); then
    ef_die "$file must not be writable by group or others (mode $mode)"
  fi
}

_ef_include() {
  local pat="$1" from="$2" depth="$3" f
  [[ "$pat" == /* ]] || pat="$(dirname "$from")/$pat"
  while IFS= read -r f; do
    [ -f "$f" ] || continue
    _ef_load_file "$f" $((depth + 1))
  done < <(compgen -G "$pat" | LC_ALL=C sort)
}

_ef_load_file() {
  local file="$1" depth="$2" scope="" line key val k n=0 known

  (( depth <= 5 )) || ef_die "$file: Include nesting too deep"
  _ef_check_file "$file"

  while IFS= read -r line || [ -n "$line" ]; do
    n=$((n + 1))
    line="${line%$'\r'}"
    line="${line#"${line%%[![:space:]]*}"}"            # trim left
    if [[ -z "$line" || "$line" == \#* ]]; then continue; fi
    line="${line%%[[:space:]]#*}"                       # drop trailing " # comment"
    line="${line%"${line##*[![:space:]]}"}"             # trim right

    if [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)([[:space:]]*=[[:space:]]*|[[:space:]]+)(.*)$ ]]; then
      key="${BASH_REMATCH[1]}"; val="${BASH_REMATCH[3]}"
    elif [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)$ ]]; then
      key="${BASH_REMATCH[1]}"; val=""
    else
      ef_die "$file:$n: syntax error: $line"
    fi
    key="$(_ef_norm "$key")"

    # Strip one pair of surrounding quotes
    if [[ "$val" =~ ^\"(.*)\"$ || "$val" =~ ^\'(.*)\'$ ]]; then val="${BASH_REMATCH[1]}"; fi

    case "$key" in
      include)
        _ef_include "$val" "$file" "$depth"
        scope=""    # every file (and what follows an Include) starts in global scope
        ;;
      target)
        [[ "$val" =~ ^[A-Za-z0-9_-]+$ ]] || ef_die "$file:$n: invalid target name: '$val'"
        scope="$val"
        [[ " ${EF_TARGETS[*]-} " == *" $val "* ]] || EF_TARGETS+=("$val")
        ;;
      reset)
        k="$(_ef_norm "$val")"
        unset "EF_S[$scope|$k]" "EF_L[$scope|$k]"
        ;;
      *)
        if [ -z "$scope" ]; then known="$EF_KNOWN_GLOBAL"; else known="$EF_KNOWN_TARGET"; fi
        [[ "$known" == *[[:space:]]"$key"[[:space:]]* ]] \
          || ef_err "WARN: $file:$n: unknown option '$key'${scope:+ in Target $scope} (ignored)"
        k="$scope|$key"
        EF_S["$k"]="$val"
        if [ -n "${EF_L["$k"]+x}" ]; then EF_L["$k"]+=$'\n'"$val"; else EF_L["$k"]="$val"; fi
        ;;
    esac
  done < "$file"
}

# ef_conf_load: read EF_CONF_FILE (and everything it includes)
ef_conf_load() {
  EF_S=(); EF_L=(); EF_TARGETS=()
  if [ -e "$EF_CONF_FILE" ]; then
    _ef_load_file "$EF_CONF_FILE" 0
  else
    ef_err "WARN: $EF_CONF_FILE not found, using built-in defaults"
  fi
}

# ef_get KEY [DEFAULT]: last value of a global option
ef_get() {
  local k v; k="$(_ef_norm "$1")"
  v="${EF_S["|$k"]:-}"
  printf '%s\n' "${v:-${2:-}}"
}

# ef_list KEY: all values of a global option, one per line
ef_list() {
  local k; k="$(_ef_norm "$1")"
  if [ -n "${EF_L["|$k"]+x}" ]; then printf '%s\n' "${EF_L["|$k"]}" | sed '/^$/d'; fi
  return 0
}

# ef_tget TARGET KEY [DEFAULT]: target option, falling back to the global one
ef_tget() {
  local t="$1" k v; k="$(_ef_norm "$2")"
  v="${EF_S["$t|$k"]:-}"
  if [ -z "$v" ]; then v="${EF_S["|$k"]:-}"; fi
  printf '%s\n' "${v:-${3:-}}"
}

# ef_bool VALUE: true for yes/true/on/1
ef_bool() { case "${1,,}" in yes|true|on|1) return 0 ;; *) return 1 ;; esac; }

# ef_expand VALUE: substitute %h with the host name
ef_expand() { local v="$1"; printf '%s\n' "${v//%h/$(hostname)}"; }

###############################################################################
# Periods
###############################################################################

# ef_period_seconds 12h|1d|2w -> seconds (returns 1 on bad input)
ef_period_seconds() {
  local n u
  [[ "$1" =~ ^([0-9]+)([hdw])$ ]] || return 1
  n=$((10#${BASH_REMATCH[1]})); u="${BASH_REMATCH[2]}"
  (( n >= 1 )) || return 1
  case "$u" in
    h) echo $((n * 3600)) ;;
    d) echo $((n * 86400)) ;;
    w) echo $((n * 604800)) ;;
  esac
}

###############################################################################
# Locking
###############################################################################

# ef_lock NAME: take /var/lock/NAME.lock or exit (fd 9 is kept for the script's life)
ef_lock() {
  exec 9>"/var/lock/$1.lock" || ef_die "cannot open lock file /var/lock/$1.lock"
  flock -n 9 || { ef_log "Already running, exiting"; exit 1; }
}

###############################################################################
# Dump directories: listing, age, retention
###############################################################################

# ef_list_dumps DIR: dump directory names, oldest first
ef_list_dumps() {
  [ -d "$1" ] || return 0
  find "$1" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' \
    | grep -E "$EF_DUMP_NAME_RE" | LC_ALL=C sort
}

# ef_dump_epoch NAME: unix time encoded in a dump directory name
ef_dump_epoch() {
  local d="${1%%_*}" t=""
  [[ "$1" == *_* ]] && t="${1#*_}" && t="${t:0:2}:${t:2:2}"
  date -d "$d${t:+ $t}" +%s
}

# ef_prune_local DIR KEEP: keep the newest KEEP dumps (0 = keep everything),
# also remove abandoned *.partial directories older than a day
ef_prune_local() {
  local dir="$1" keep="$2" name total i=0
  [ -d "$dir" ] || return 0
  find "$dir" -mindepth 1 -maxdepth 1 -type d -name '*.partial' -mtime +1 \
    -exec rm -rf {} + 2>/dev/null
  (( keep > 0 )) || return 0
  mapfile -t _all < <(ef_list_dumps "$dir")
  total=${#_all[@]}
  for name in "${_all[@]}"; do
    (( total - i > keep )) || break
    [[ "$name" =~ $EF_DUMP_NAME_RE ]] || continue     # never rm -rf anything else
    rm -rf -- "${dir:?}/$name" && ef_log "PRUNE $dir/$name"
    i=$((i + 1))
  done
}

###############################################################################
# Backup targets (Target blocks in the config)
###############################################################################

# Uses rclone's own default config (/root/.config/rclone/rclone.conf) unless
# RcloneConfig names another file.
_ef_rclone() {
  local cfg; cfg="$(ef_get rcloneconfig "")"
  if [ -n "$cfg" ]; then rclone --config "$cfg" "$@"; else rclone "$@"; fi
}

# ef_targets_for KIND: enabled targets that take this kind of backup (mysql, files)
ef_targets_for() {
  local kind="$1" t k
  for t in "${EF_TARGETS[@]-}"; do
    [ -n "$t" ] || continue
    ef_bool "$(ef_tget "$t" enabled yes)" || continue
    k="$(ef_tget "$t" kinds)"; k="${k//,/ }"
    if [ -n "$k" ] && [[ " $k " != *" $kind "* ]]; then continue; fi
    echo "$t"
  done
}

# _ef_prune_rclone BASE KIND KEEP [rclone flags...]: keep the newest KEEP dumps
_ef_prune_rclone() {
  local base="$1" kind="$2" keep="$3" name total i=0
  shift 3
  (( keep > 0 )) || return 0
  mapfile -t _all < <(_ef_rclone lsf --dirs-only "$@" "$base/$kind" 2>/dev/null \
                      | sed 's#/$##' | grep -E "$EF_DUMP_NAME_RE" | LC_ALL=C sort)
  total=${#_all[@]}
  for name in "${_all[@]}"; do
    (( total - i > keep )) || break
    if _ef_rclone purge "$@" "$base/$kind/$name" >/dev/null 2>&1; then
      ef_log "PRUNE $base/$kind/$name"
    fi
    i=$((i + 1))
  done
}

# ef_target_ready TARGET DIR: a local target is usable only if its RequireMount
# path (if set) is a mount point and DIR exists. This keeps an unmounted NAS or
# SSHFS folder from being filled silently on the local disk.
ef_target_ready() {
  local t="$1" dir="$2" m
  m="$(ef_expand "$(ef_tget "$t" requiremount)")"
  if [ -n "$m" ] && ! mountpoint -q "$m"; then
    ef_err "FAIL target $t: $m is not mounted"; return 1
  fi
  if [ ! -d "$dir" ]; then
    ef_err "FAIL target $t: $dir does not exist (is it mounted?)"; return 1
  fi
  return 0
}

# ef_push_all KIND SRC_DIR STAMP GLOBAL_KEEP KEEP_KEY
#   Copy SRC_DIR to <target>/KIND/STAMP on every enabled target that takes KIND
#   and prune each target to its KEEP_KEY (KeepPrevious / FilesKeepPrevious)
#   dumps. Returns 1 if any target failed.
ef_push_all() {
  local kind="$1" src="$2" stamp="$3" gkeep="$4" keepkey="$5"
  local t type keep rc=0 flags dst base
  for t in $(ef_targets_for "$kind"); do
    type="$(ef_tget "$t" type)"
    keep="$(ef_tget "$t" "$keepkey" "$gkeep")"
    [[ "$keep" =~ ^[0-9]+$ ]] || { ef_err "FAIL target $t: bad $keepkey '$keep'"; rc=1; continue; }

    case "$type" in
      local)
        base="$(ef_expand "$(ef_tget "$t" path)")"
        if [ -z "$base" ]; then ef_err "FAIL target $t: Path is not set"; rc=1; continue; fi
        ef_target_ready "$t" "$base" || { rc=1; continue; }
        dst="$base/$kind/$stamp"
        # cp -R, not -a: network file systems such as SSHFS refuse to keep the owner
        if mkdir -p "$base/$kind" && cp -R -- "$src" "$dst.partial" && mv -- "$dst.partial" "$dst"; then
          ef_log "PUSH $t (local): $dst"
          ef_prune_local "$base/$kind" "$keep"
        else
          rm -rf -- "$dst.partial"
          ef_err "FAIL target $t: cannot copy to $dst"; rc=1
        fi
        ;;
      rclone)
        base="$(ef_expand "$(ef_tget "$t" remote)")"
        if [ -z "$base" ]; then ef_err "FAIL target $t: Remote is not set"; rc=1; continue; fi
        command -v rclone >/dev/null 2>&1 || { ef_err "FAIL target $t: rclone is not installed"; rc=1; continue; }
        read -r -a flags <<< "$(ef_tget "$t" rcloneflags)"
        dst="$base/$kind/$stamp"
        if _ef_rclone copy "${flags[@]}" "$src" "$dst" \
           && _ef_rclone check --one-way --size-only "${flags[@]}" "$src" "$dst" >/dev/null 2>&1; then
          ef_log "PUSH $t (rclone): $dst"
          _ef_prune_rclone "$base" "$kind" "$keep" "${flags[@]}"
        else
          ef_err "FAIL target $t: rclone copy/check to $dst failed"; rc=1
        fi
        ;;
      *)
        ef_err "FAIL target $t: unknown Type '$type' (expected local or rclone)"; rc=1
        ;;
    esac
  done
  return $rc
}
