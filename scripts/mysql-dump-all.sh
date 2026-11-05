#!/bin/bash
#
# Daily per-database MySQL dumps, one .sql.gz file per database
# (equivalent of Webmin's "Backup databases"), importable via phpMyAdmin.
#
# Run as root: the connection uses the unix socket (auth_socket),
# so no password is needed.
#
# Installation:
#   install -m 700 mysql-dump-all.sh /usr/local/sbin/mysql-dump-all.sh
#   echo '30 3 * * * root /usr/local/sbin/mysql-dump-all.sh' > /etc/cron.d/mysql-dump-all

set -uo pipefail

BACKUP_ROOT="/srv/backups/mysql"   # where dumps are stored
KEEP_DAYS=14                       # how many days to keep dumps
LOG_FILE="/var/log/mysql-dump-all.log"

# Databases to skip (system schemas). Remove 'mysql' from the list
# if you also want to back up MySQL accounts and grants.
EXCLUDE_REGEX='^(information_schema|performance_schema|sys|mysql)$'

# Connection: root over the unix socket, no password.
MYSQL_CONN=(--protocol=socket -uroot)

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

# Set to 1 if dumps must be importable into older MySQL 5.7 / MariaDB:
# MySQL 8 dumps may contain the utf8mb4_0900_ai_ci collation, which those
# servers do not know. The script replaces it with utf8mb4_general_ci,
# which exists everywhere.
LEGACY_COLLATION_FIX=0

###############################################################################

umask 077
log() { echo "$(date '+%F %T') $*" | tee -a "$LOG_FILE"; }

# Prevent concurrent runs
exec 9>/var/lock/mysql-dump-all.lock
flock -n 9 || { log "Already running, exiting"; exit 1; }

DEST="$BACKUP_ROOT/$(date +%F)"
mkdir -p "$DEST" || { log "ERROR: cannot create $DEST"; exit 1; }
chmod 700 "$BACKUP_ROOT" "$DEST"

DBS=$(mysql "${MYSQL_CONN[@]}" -N -B -e 'SHOW DATABASES') || {
  log "ERROR: failed to get the database list"; exit 1; }

fail=0
for db in $DBS; do
  [[ "$db" =~ $EXCLUDE_REGEX ]] && continue

  out="$DEST/$db.sql.gz"
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
  # and the archive is intact, so a broken dump never replaces a good one.
  if [ "${PIPESTATUS[0]}" -eq 0 ] && gzip -t "$tmp" 2>/dev/null; then
    mv -f "$tmp" "$out"
    log "OK   $db ($(du -h "$out" | cut -f1))"
  else
    rm -f "$tmp"
    log "FAIL $db"
    fail=1
  fi
done

# Remove old dump directories
find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" \
  -exec rm -rf {} + 2>/dev/null

exit $fail
