// Restore the clean post-install DB snapshot and clear the shop cache, so a run starts fresh — a
// re-seed never collides with a prior attempt's rows and no stale cache entry lingers. Best-effort:
// with no snapshot (a freshly-provisioned leg) this is a no-op; a failed restore just runs on the
// current state rather than aborting the leg.
import fs from 'node:fs';
import { spawnSync } from 'node:child_process';
import { FILES, shopDir } from './lib.mjs';

/**
 * Extracts MySQL connection parts from Shopware's `DATABASE_URL`.
 */
function parseDatabaseUrl(url) {
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
    return;
  }
  if (!process.env.DATABASE_URL) {
    console.warn('::warning::DATABASE_URL not set — skipping DB reset');
    return;
  }

  const db = parseDatabaseUrl(process.env.DATABASE_URL);
  console.log('resetting DB to the clean snapshot…');
  const restore = spawnSync(
    'bash',
    ['-c', `gunzip -c "${FILES.snapshot}" | mysql -h"${db.host}" -P"${db.port}" -u"${db.user}" "${db.name}"`],
    { stdio: 'inherit', env: { ...process.env, MYSQL_PWD: db.pass } },
  );
  if (restore.status !== 0) {
    console.warn('::warning::DB reset failed — running on the current state');
    return;
  }

  const shop = shopDir();
  if (fs.existsSync(`${shop}/bin/console`)) {
    spawnSync('php', ['bin/console', 'cache:pool:clear', '--all'], {
      cwd: shop,
      stdio: 'ignore',
      env: { ...process.env, APP_ENV: 'prod' },
    });
  }
}
