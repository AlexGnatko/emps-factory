# EMPS Factory shell tools

Backup and maintenance scripts for a server managed with EMPS Factory.
They run as root and are configured through `/etc/emps-factory/`.

| File | What it does |
|---|---|
| `mysql-dump-all.sh` | Dumps every MySQL database into its own `.sql.gz`, prunes old dumps, copies them to the configured targets |
| `mysql-load-dump.sh` | Loads a `.tar.gz`, `.sql.gz` or `.sql` dump into a database |
| `files-backup.sh` | Archives `/srv`, `/etc` and other paths into one tar archive (zstd, gzip or plain), copies or streams it to the targets |
| `emps-factory-cron.sh` | Generates `/etc/cron.d/emps-factory` from the configuration |
| `tar-du.sh` | Shows how much space each directory takes inside a (huge) tar archive |
| `lib/common.sh` | Shared library: config parser, logging, locking, retention, targets |
| `conf/factory.conf` | Default configuration with every option described |
| `backup.d/00-default.*` | Shipped include/exclude lists for `files-backup.sh` |
| `install.sh` | Installs everything (does **not** touch cron) |

## Installation

```bash
cd scripts
sudo ./install.sh                     # scripts -> /usr/local/sbin, lib -> /usr/local/lib/emps-factory
sudo mcedit /etc/emps-factory/factory.conf
sudo emps-factory-cron.sh --install   # only when you want the schedule
```

`install.sh` never overwrites an existing `factory.conf` (the new defaults are
saved as `factory.conf.dist`) and never touches `conf.d/`. It **does**
overwrite `backup.d/00-default.*`, so keep your changes in your own files.
`zstd` is needed for the default archive format (`apt install zstd`), `rclone`
for rclone targets.

## Commands

```bash
mysql-dump-all.sh [--force]            # dump now; --force ignores DumpPeriod
mysql-load-dump.sh [-y] <archive> [database]
files-backup.sh [--force] [--list]     # --list prints the final include/exclude lists
emps-factory-cron.sh                   # print the crontab
emps-factory-cron.sh --install         # write /etc/cron.d/emps-factory
emps-factory-cron.sh --remove          # delete it
tar-du.sh archive.tar [levels] [top]
```

Local copies are stored under `BackupRoot` (default `/srv/backups`):

```
/srv/backups/mysql/2026-11-14_0317/<database>.sql.gz
/srv/backups/files/2026-11-14_0447/files.tar.zst   (+ include.list, exclude.list)
```

Logs: `/var/log/mysql-dump-all.log`, `/var/log/files-backup.log`. A `FAILED` file
in a dump folder lists databases that could not be dumped; scripts exit with
status 1 on any failure. Restore files with `tar -xf files.tar.zst -C /`.

## Configuration

Read from `/etc/emps-factory/factory.conf`. Its last line,
`Include /etc/emps-factory/conf.d/*.conf`, loads local overrides in file name
order (`00-base.conf`, `10-targets.conf`, `90-local.conf`); the last value wins.
Files that do not end in `.conf` are ignored. All files must be owned by root
and not group/world-writable.

Syntax is sshd-like:

```
Keyword value         # or  Keyword = value
```

* keywords ignore case and underscores: `KeepPrevious` = `KEEP_PREVIOUS`
* a repeated keyword builds a list; for single values the last one wins
* `Reset Keyword` empties a list collected so far
* `Target NAME` opens a backup target, ended by the next `Target`, an `Include`
  or end of file; put global options before targets

| Option | Meaning | Default |
|---|---|---|
| `DumpPeriod` | How often to dump MySQL: `8h`, `12h`, `1d`, `1w`... | `1d` |
| `KeepPrevious` | MySQL dumps to keep, newest included; one unit = one `DumpPeriod` (`12h` and `28` = 14 days). `0` = never delete | `14` |
| `FilesPeriod`, `FilesKeepPrevious` | The same for file backups | `1d`, `7` |
| `BackupRoot` | Local backup directory | `/srv/backups` |
| `MysqlExcludeDb`, `MysqlOnlyDb` | Database patterns to skip / to back up (repeatable) | all |
| `MysqlIncludeGrants` | Also dump the `mysql` system database | `no` |
| `MysqlLegacyCollation` | Rewrite `utf8mb4_0900_*` for MySQL 5.7 / MariaDB | `no` |
| `MysqlUser` / `MysqlDefaultsFile` | Connection (socket as `MysqlUser`, or a client `.cnf` file) | `root` |
| `BackupCompression` | `zstd` (`.tar.zst`), `gzip` (`.tar.gz`) or `none` (`.tar`) | `zstd` |
| `BackupCompressionLevel` | Compression level | `3` zstd, `6` gzip |
| `BackupDir` | Folder with the `*.include` / `*.exclude` lists | `/etc/emps-factory/backup.d` |
| `BackupDefaults` | Use the shipped `00-default.*` lists | `yes` |
| `BackupExcludeTag` | Skip any directory containing this file | `.nobackup` |
| `BackupOneFileSystem` | Do not cross into other mounted file systems | `no` |
| `FilesLocalCopy` | `no` = stream straight to the one files target (rclone or local/mounted directory), no archive in `BackupRoot` | `yes` |
| `RcloneConfig` | rclone config for all rclone targets | `/etc/emps-factory/rclone.conf` |
| `CronMysql`, `CronFiles` | Which jobs `emps-factory-cron.sh` writes | `yes` |
| `CronOffsetMinute`, `CronStartHour` | Minute / first hour of the MySQL job | `17`, `3` |
| `CronFilesOffsetMinute`, `CronFilesStartHour` | The same for the file backup job | `47`, `4` |
| `CronMailTo` | Where cron mails script errors | none |
| `MysqlLogFile`, `FilesLogFile` | Log files | see above |

Target options: `Type` (`local` or `rclone`), `Path` (local) or `Remote`
(rclone), `KeepPrevious`, `FilesKeepPrevious`, `Kinds` (`mysql`, `files`; default
both), `Enabled yes|no`, `RcloneFlags`, `RequireMount` (local only: refuse to write unless this path is a mount point, e.g. `/mnt/nas`). `%h` in a path or remote is replaced
with the host name. Targets get the same `mysql/<timestamp>/` and
`files/<timestamp>/` layout as the local copy.

## What goes into the file backup

`files-backup.sh` reads every `*.include` and `*.exclude` file in
`/etc/emps-factory/backup.d/` in name order:

```
backup.d/
  00-default.include    # shipped: /etc /srv /home /var/spool/cron
  00-default.exclude    # shipped: */vendor */node_modules */bower_components */cache */temp_c *.log *.tmp
  50-local.include      # yours
  50-local.exclude      # yours
  90-test.include.off   # disabled (does not end in .include)
```

* `*.include`: one absolute path per line. `- /path` removes an earlier entry
  (exact match). Missing paths are skipped with a warning.
* `*.exclude`: tar wildcard patterns, matched at any depth. Use these to cut a
  subfolder out of an included path.
* `BackupRoot` and local target folders are always excluded, so backups never
  contain older backups. A folder holding a `.nobackup` file is skipped.

## Examples

Dump MySQL every 12 hours (14 days locally), back up files every 3 days, and
keep copies on a NAS and on S3 (`/etc/emps-factory/conf.d/10-backup.conf`).
The cron job fires daily and `files-backup.sh` skips the days when nothing is
due, so `FilesPeriod 1w` or `2d` work the same way:

```
DumpPeriod         12h
KeepPrevious       28
FilesPeriod        3d
FilesKeepPrevious  5             # 15 days of file backups locally

Target nas
    Type          local
    Path          /mnt/nas/backups/%h
    KeepPrevious  120            # 60 days of dumps
    FilesKeepPrevious 14         # 42 days of file backups

Target adman
    Type          rclone
    Remote        adman:my-bucket/%h
    KeepPrevious  180            # 90 days of dumps
    FilesKeepPrevious 30         # 90 days of file backups
    RcloneFlags   --s3-chunk-size 32M
```

`/etc/emps-factory/rclone.conf` (`chmod 600`):

```ini
[adman]
type = s3
provider = Other
access_key_id = <access_key>
secret_access_key = <secret_key>
endpoint = https://s3.adman.com
region = default
force_path_style = true
```

Check the remote with `rclone --config /etc/emps-factory/rclone.conf lsd adman:`,
then apply the schedule: `emps-factory-cron.sh` (look first), then `--install`.

Do not back up the raw MySQL files, but do back up `/data` (`backup.d/50-local.include`):

```
- /var/lib/mysql
/data
```

Skip one old site (`backup.d/50-local.exclude`):

```
/srv/www/old-site
```

To change the shipped excludes themselves (for example to keep `*.log`), set
`BackupDefaults no` and write your own complete lists.

Plain tar instead of zstd, and no local copy on a small disk (stream to S3 or to a mounted NAS/SSHFS folder):

```
BackupCompression none
FilesLocalCopy    no
Target adman
    Kinds  files          # a streaming setup needs exactly one target for files
```

Skip test and scratch databases (`conf.d`):

```
MysqlExcludeDb test_*
MysqlExcludeDb scratch
```

Restore a dump into a database:

```bash
mysql-load-dump.sh /srv/backups/mysql/2026-11-14_0317/shop.sql.gz
mysql-load-dump.sh -y shop.sql.gz shop_copy
```

## Notes

* A run is skipped when the newest backup is younger than 90% of the period,
  so extra cron fires or double starts do no harm.
* Old `YYYY-MM-DD` dump folders from earlier versions still count as dumps and
  are pruned together with the new ones.
* tar warnings about files that changed while being read (normal on live
  sites) are logged but do not fail the backup; real read errors do.
* Only one run of each script at a time (`flock`).
* `vendor/` (Composer) is excluded by default; restore it with `composer install`.
