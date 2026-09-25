/**
 * Reset command for restoring the clean post-install database snapshot and clearing shop cache.
 *
 * The reset is best-effort: freshly provisioned legs may not have a snapshot yet, and a failed
 * restore should leave the leg running on current state rather than aborting before seeding can
 * report an actionable blocker.
 */
import fs from 'node:fs';
import { spawnSync } from 'node:child_process';
import { FILES, shopDir } from '../../bundle.ts';

/**
 * Extracts MySQL connection parts from Shopware's `DATABASE_URL`.
 */
function parseDatabaseUrl(url: string) {
  const u = new URL(url);
  return {
    host: u.hostname || '127.0.0.1',
    port: u.port || '3306',
    user: decodeURIComponent(u.username) || 'root',
    pass: decodeURIComponent(u.password),
    name: u.pathname.replace(/^\//, ''),
  };
}

/**
 * Restores the clean post-install database snapshot before a trusted leg runs.
 *
 * Reset is best-effort so freshly provisioned legs without a snapshot can still continue, while
 * stale rows and cache are cleared whenever the snapshot is available.
 */
export function reset() {
  if (!fs.existsSync(FILES.snapshot)) {
    console.log('no DB snapshot — running on the current state');
    return { ok: true };
  }
  if (!process.env.DATABASE_URL) {
    console.warn('::warning::DATABASE_URL not set — skipping DB reset');
    return { ok: true };
  }

  const db = parseDatabaseUrl(process.env.DATABASE_URL);
  console.log('resetting DB to the clean snapshot…');
  const restore = spawnSync(
    'bash',
    ['-c', `gunzip -c "${FILES.snapshot}" | mysql -h"${db.host}" -P"${db.port}" -u"${db.user}" "${db.name}"`],
    { stdio: 'inherit', env: { ...process.env, MYSQL_PWD: db.pass } },
  );
  if (restore.status !== 0) {
    // A snapshot EXISTS but couldn't be restored → the leg would run on dirty/agent-polluted state and
    // emit a real reproduced/not_reproduced into the verdict. Fail CLOSED: the caller turns this into a
    // blocked leg instead of a silently-wrong verdict. (Contrast the no-snapshot case, which is a
    // legitimate run-on-current-state for previews.)
    return { ok: false, reason: `DB reset failed (exit ${restore.status}) — refusing to judge a leg on un-restored state` };
  }

  const shop = shopDir();
  if (fs.existsSync(`${shop}/bin/console`)) {
    spawnSync('php', ['bin/console', 'cache:pool:clear', '--all'], {
      cwd: shop,
      stdio: 'ignore',
      env: { ...process.env, APP_ENV: 'prod' },
    });
  }
  return { ok: true };
}
