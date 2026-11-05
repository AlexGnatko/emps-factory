#!/bin/bash
#
# Load a dump archive into a MySQL database.
#
# Usage:
#   mysql-load-dump.sh [-y] <archive> [database]
#
#   <archive>   .tar.gz / .tgz (containing exactly one .sql file),
#               .sql.gz or plain .sql
#   [database]  target database name; if omitted, it is derived from the
#               archive file name (e.g. mydb.sql.gz -> mydb, mydb.tar.gz -> mydb)
#   -y          do not ask for confirmation when the database already
#               contains tables
#
# Run as root: the connection uses the unix socket (auth_socket),
# so no password is needed.
#
# Dumps created by mysql-dump-all.sh contain DROP TABLE IF EXISTS, so
# loading one into an existing database replaces the tables it contains.

set -uo pipefail

MYSQL_CONN=(--protocol=socket -uroot)
PROTECTED_REGEX='^(information_schema|performance_schema|sys|mysql)$'

die() { echo "ERROR: $*" >&2; exit 1; }

ASSUME_YES=0
if [ "${1:-}" = "-y" ]; then ASSUME_YES=1; shift; fi

[ $# -ge 1 ] && [ $# -le 2 ] || die "usage: $0 [-y] <archive> [database]"

ARCHIVE="$1"
[ -f "$ARCHIVE" ] || die "file not found: $ARCHIVE"

# Derive the database name from the file name if not given
if [ $# -eq 2 ]; then
  DB="$2"
else
  DB="$(basename "$ARCHIVE")"
  DB="${DB%.tar.gz}"; DB="${DB%.tgz}"; DB="${DB%.sql.gz}"; DB="${DB%.sql}"
fi

# Allow only safe database names
[[ "$DB" =~ ^[A-Za-z0-9_.-]+$ ]] || die "invalid database name: $DB"
[[ "$DB" =~ $PROTECTED_REGEX ]] && die "refusing to load into system database: $DB"

TMPDIR_LOAD="$(mktemp -d /tmp/mysql-load.XXXXXX)" || die "cannot create temp dir"
trap 'rm -rf "$TMPDIR_LOAD"' EXIT

# Prepare the SQL stream
case "$ARCHIVE" in
  *.tar.gz|*.tgz)
    tar -xzf "$ARCHIVE" -C "$TMPDIR_LOAD" --no-same-owner --no-same-permissions \
      || die "failed to extract $ARCHIVE"
    mapfile -t SQL_FILES < <(find "$TMPDIR_LOAD" -type f -name '*.sql' | sort)
    [ "${#SQL_FILES[@]}" -eq 1 ] \
      || die "expected exactly one .sql file in the archive, found ${#SQL_FILES[@]}"
    SQL_FILE="${SQL_FILES[0]}"
    ;;
  *.sql.gz)
    gzip -t "$ARCHIVE" || die "corrupted gzip file: $ARCHIVE"
    SQL_FILE="$TMPDIR_LOAD/dump.sql"
    gzip -dc "$ARCHIVE" > "$SQL_FILE" || die "failed to decompress $ARCHIVE"
    ;;
  *.sql)
    SQL_FILE="$ARCHIVE"
    ;;
  *)
    die "unsupported file type (expected .tar.gz, .tgz, .sql.gz or .sql)"
    ;;
esac

# Check that the server is reachable
mysql "${MYSQL_CONN[@]}" -e 'SELECT 1' >/dev/null 2>&1 \
  || die "cannot connect to MySQL as root via socket"

# If the database exists and is not empty, ask before overwriting
EXISTS=$(mysql "${MYSQL_CONN[@]}" -N -B \
  -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$DB'")
if [ "$EXISTS" = "1" ]; then
  TABLES=$(mysql "${MYSQL_CONN[@]}" -N -B \
    -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB'")
  if [ "$TABLES" -gt 0 ] && [ "$ASSUME_YES" -ne 1 ]; then
    echo "Database '$DB' already exists and contains $TABLES table(s)."
    echo "Tables present in the dump will be dropped and recreated."
    if [ -t 0 ]; then
      read -r -p "Continue? [y/N] " ans
      [[ "$ans" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 1; }
    else
      die "non-interactive run: use -y to confirm"
    fi
  fi
else
  echo "Creating database '$DB'"
  mysql "${MYSQL_CONN[@]}" \
    -e "CREATE DATABASE \`$DB\` CHARACTER SET utf8mb4" \
    || die "failed to create database $DB"
fi

echo "Loading $(basename "$SQL_FILE") into '$DB' ..."
mysql "${MYSQL_CONN[@]}" --default-character-set=utf8mb4 "$DB" < "$SQL_FILE" \
  || die "import failed"

echo "Done: '$DB' loaded from $ARCHIVE"
