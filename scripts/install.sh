#!/bin/bash
#
# Install the EMPS Factory shell tools. Run as root from anywhere:
#   ./install.sh
#
# Scripts go to /usr/local/sbin, the library to /usr/local/lib/emps-factory and
# the configuration to /etc/emps-factory:
#   factory.conf       created once; an existing one is never overwritten (the new
#                      default is saved next to it as factory.conf.dist)
#   conf.d/            your overrides (left alone)
#   backup.d/00-default.{include,exclude}
#                      shipped file lists, OVERWRITTEN on every run; put your own
#                      lists in backup.d/NN-name.include / .exclude
# Cron is NOT touched: run "emps-factory-cron.sh --install" when you want it.

set -eu
cd "$(dirname "$(readlink -f "$0")")"

install -m 700 mysql-dump-all.sh    /usr/local/sbin/mysql-dump-all.sh
install -m 700 mysql-load-dump.sh   /usr/local/sbin/mysql-load-dump.sh
install -m 700 files-backup.sh      /usr/local/sbin/files-backup.sh
install -m 700 emps-factory-cron.sh /usr/local/sbin/emps-factory-cron.sh

install -d -m 755 /usr/local/lib/emps-factory
install -m 644 lib/common.sh /usr/local/lib/emps-factory/common.sh

install -d -m 755 /etc/emps-factory /etc/emps-factory/conf.d /etc/emps-factory/backup.d
install -m 644 backup.d/00-default.include backup.d/00-default.exclude /etc/emps-factory/backup.d/

if [ -e /etc/emps-factory/factory.conf ]; then
  install -m 600 conf/factory.conf /etc/emps-factory/factory.conf.dist
  echo "Kept your /etc/emps-factory/factory.conf (new defaults: factory.conf.dist)"
else
  install -m 600 conf/factory.conf /etc/emps-factory/factory.conf
  echo "Created /etc/emps-factory/factory.conf"
fi

echo "Done. Edit /etc/emps-factory/factory.conf (or add files to conf.d/ and backup.d/), then run:"
echo "  emps-factory-cron.sh --install"
