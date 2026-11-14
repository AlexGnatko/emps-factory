#!/bin/bash
#
# Periodic per-database MySQL dumps, one .sql.gz file per database
# (equivalent of Webmin's "Backup databases"), importable via phpMyAdmin.
#
# Usage:
#   mysql-dump-all.sh [--force]
#
#   --force   dump now even if the last dump is younger than DumpPeriod
#
# Dumps go to <BackupRoot>/mysql/YYYY-MM-DD_HHMM/<database>.sql.gz, are
# copied to every enabled Target and pruned to KeepPrevious dumps.
# All settings live in /etc/emps-factory/factory.conf (see conf/factory.conf):
# DumpPeriod, KeepPrevious, MysqlExcludeDb, MysqlOnlyDb, Target blocks, ...
#
# Run as root: the connection uses the unix socket (auth_socket), so no
# password is needed.
#
# Installation (scripts/install.sh does this) and scheduling:
#   install -m 700 mysql-dump-all.sh /usr/local/sbin/mysql-dump-all.sh
#   emps-factory-cron.sh --install     # writes /etc/cron.d/emps-factory

set -uo pipefail

for _d in "${EF_LIB:-/usr/local/lib/emps-factory}" "$(dirname "$(readlink -f "$0")")/lib"; do
  [ -r "$_d/common.sh" ] && { . "$_d/common.sh"; break; }
done
type ef_conf_load >/dev/null 2>&1 || { echo "ERROR: common.sh not found" >&2; exit 1; }

FORCE=0
case "${1:-}" in
  "") ;;
  --force) FORCE=1 ;;
  -h|--help) sed -n '3,19p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
  *) echo "usage: $0 [--force]" >&2; exit 1 ;;
esac

umask 077
ef_conf_load

BACKUP_ROOT="$(ef_get backuproot /srv/backups)"
BACKUP_DIR="$BACKUP_ROOT/mysql"
LOG_FILE="$(ef_get mysqllogfile /var/log/mysql-dump-all.log)"
ef_log_to "$LOG_FILE"

PERIOD="$(ef_get dumpperiod 1d)"
PERIOD_S="$(ef_period_seconds "$PERIOD")" || ef_die "bad DumpPeriod '$PERIOD' (use 8h, 12h, 1d, 1w ...)"
KEEP="$(ef_get keepprevious 14)"
[[ "$KEEP" =~ ^[0-9]+$ ]] || ef_die "bad KeepPrevious '$KEEP' (expected a number)"

# Connection: root over the unix socket unless a defaults file is given.
if [ -n "$(ef_get mysqldefaultsfile)" ]; then
  MYSQL_CONN=(--defaults-extra-file="$(ef_get mysqldefaultsfile)")
else
  MYSQL_CONN=(--protocol=socket -u"$(ef_get mysqluser root)")
fi

# Dump options:
#  --single-transaction   consistent InnoDB dump without locking tables
#  --quick                stream rows instead of buffering whole tables in memory
#  --routines/--triggers/--events  include stored routines, triggers and events
#  --add-drop-table       DROP TABLE IF EXISTS before CREATE (convenient for restores)
#  --create-options       full CREATE TABLE options
#  --complete-insert      INSERT with column names (robust against column order changes)
#  --extended-insert      multi-row INSERT statements (faster import; the default)
#  --hex-blob             binary data as hex so it survives import intact
#  --set-gtid-purged=OFF  no GTID statements, which break imports on other servers
#  --no-tablespaces       does not require the PROCESS privilege
#  --default-character-set=utf8mb4  correct character encoding
DUMP_OPTS=(
  --single-transaction
  --quick
  --routines
  --triggers
  --events
  --add-drop-table
  --create-options
  --complete-insert
  --extended-insert
  --hex-blob
  --set-gtid-purged=OFF
  --no-tablespaces
  --default-character-set=utf8mb4
)

# MySQL 8 dumps may contain the utf8mb4_0900_ai_ci collation, which MySQL 5.7
# and MariaDB do not know. With MysqlLegacyCollation yes it is replaced by
# utf8mb4_general_ci, which exists everywhere.
LEGACY_COLLATION_FIX=0
ef_bool "$(ef_get mysqllegacycollation no)" && LEGACY_COLLATION_FIX=1

###############################################################################

# Does this database need a dump?
db_wanted() {
  local db="$1" p only=0
  case "$db" in information_schema|performance_schema|sys) return 1 ;; esac
  if [ "$db" = mysql ] && ! ef_bool "$(ef_get mysqlincludegrants no)"; then return 1; fi
  while IFS= read -r p; do
    # shellcheck disable=SC2053   # the pattern is meant to be a glob
    [[ "$db" == $p ]] && return 1
  done < <(ef_list mysqlexcludedb)
  while IFS= read -r p; do
    only=1
    # shellcheck disable=SC2053
    [[ "$db" == $p ]] && return 0
  done < <(ef_list mysqlonlydb)
  [ "$only" -eq 0 ]
}

# Prevent concurrent runs
ef_lock mysql-dump-all

mkdir -p "$BACKUP_DIR" || ef_die "cannot create $BACKUP_DIR"
chmod 700 "$BACKUP_ROOT" "$BACKUP_DIR"

# Skip if the newest dump is younger than 90% of DumpPeriod, so that a cron
# that fires more often than DumpPeriod (or a double run) does no harm.
LATEST="$(ef_list_dumps "$BACKUP_DIR" | tail -n 1)"
if [ "$FORCE" -ne 1 ] && [ -n "$LATEST" ]; then
  AGE=$(( $(date +%s) - $(ef_dump_epoch "$LATEST") ))
  if [ "$AGE" -lt $(( PERIOD_S * 9 / 10 )) ]; then
    ef_log "SKIP last dump $LATEST is only $((AGE / 60)) min old (DumpPeriod $PERIOD)"
    exit 0
  fi
fi

STAMP="$(date +%F_%H%M)"
DEST="$BACKUP_DIR/$STAMP"
[ -e "$DEST" ] && ef_die "$DEST already exists"
rm -rf -- "$DEST.partial"
mkdir -p "$DEST.partial" || ef_die "cannot create $DEST.partial"
chmod 700 "$DEST.partial"

DB_LIST=$(mysql "${MYSQL_CONN[@]}" -N -B -e 'SHOW DATABASES') || {
  rm -rf -- "$DEST.partial"; ef_die "failed to get the database list"; }
mapfile -t DBS <<< "$DB_LIST"

fail=0
failed_dbs=()
for db in "${DBS[@]}"; do
  [ -n "$db" ] || continue
  db_wanted "$db" || continue

  out="$DEST.partial/$db.sql.gz"
  tmp="$out.tmp"

  if [ "$LEGACY_COLLATION_FIX" = 1 ]; then
    mysqldump "${MYSQL_CONN[@]}" "${DUMP_OPTS[@]}" -- "$db" \
      | sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_general_ci/g' \
            -e 's/utf8mb4_0900_as_cs/utf8mb4_general_ci/g' \
      | gzip -9 > "$tmp"
  else
    mysqldump "${MYSQL_CONN[@]}" "${DUMP_OPTS[@]}" -- "$db" | gzip -9 > "$tmp"
  fi

  # Dump into a temp file first and only rename it if mysqldump succeeded
  # and the archive is intact, so a broken dump never ends up under its real name.
  if [ "${PIPESTATUS[0]}" -eq 0 ] && gzip -t "$tmp" 2>/dev/null; then
    mv -f "$tmp" "$out"
    ef_log "OK   $db ($(du -h "$out" | cut -f1))"
  else
    rm -f "$tmp"
    ef_err "FAIL $db"
    failed_dbs+=("$db")
    fail=1
  fi
done

# The dump directory gets its final name only when the run is over; a FAILED
# file lists the databases that could not be dumped.
if [ "${#failed_dbs[@]}" -gt 0 ]; then printf '%s\n' "${failed_dbs[@]}" > "$DEST.partial/FAILED"; fi
mv -- "$DEST.partial" "$DEST" || ef_die "cannot rename $DEST.partial"

ef_prune_local "$BACKUP_DIR" "$KEEP"
ef_push_all mysql "$DEST" "$STAMP" "$KEEP" keepprevious || fail=1

exit $fail
