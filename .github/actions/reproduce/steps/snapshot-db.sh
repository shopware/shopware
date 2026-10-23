#!/usr/bin/env bash
# Snapshot the clean post-install DB once (after provision, before the agent). `repro reset` restores
# it before each verified run, so every attempt starts fresh — re-seeds never collide with prior rows.
#
# Env: DATABASE_URL (job env), OUT (default repro-clean-db.sql.gz).
set -euo pipefail

OUT=${OUT:-repro-clean-db.sql.gz}
: "${DATABASE_URL:?DATABASE_URL is required}"
# rawurldecode user/pass so a percent-encoded userinfo (e.g. p%40ss -> p@ss) authenticates — matches
# reset.ts, which decodeURIComponent()s the same components.
eval "$(php -r '$u=parse_url(getenv("DATABASE_URL")); printf("DBH=%s DBP=%s DBU=%s DBPW=%s DBN=%s", $u["host"]??"127.0.0.1", $u["port"]??3306, rawurldecode($u["user"]??"root"), rawurldecode($u["pass"]??""), ltrim($u["path"]??"/","/"));')"

mysqldump --no-tablespaces --single-transaction --skip-lock-tables \
  -h"$DBH" -P"$DBP" -u"$DBU" ${DBPW:+-p"$DBPW"} "$DBN" | gzip > "$OUT"
echo "DB snapshot → $OUT ($(du -h "$OUT" | cut -f1))"
