import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { FILES } from '../../bundle.mjs';

/**
 * Adapts the generated direct test to Shopware's existing PHPUnit integration test layout.
 *
 * The class owns filesystem placement and process invocation only; it deliberately returns raw
 * output so classification remains a separate policy decision.
 */
export class PhpunitRunner {
  runPhpunit(specPath, shop, plan, target) {
    if (process.env.PHPUNIT_REPORT) {
      return fs.readFileSync(process.env.PHPUNIT_REPORT, 'utf8');
    }
    if (!fs.existsSync(specPath)) {
      return null;
    }

    const dest = path.join(shop, 'tests/integration/Repro');
    fs.mkdirSync(dest, { recursive: true });
    fs.copyFileSync(specPath, path.join(dest, FILES.testPhp));
    const res = spawnSync('php', ['vendor/bin/phpunit', '--colors=never', 'tests/integration/Repro/ReproTest.php'], {
      cwd: shop,
      encoding: 'utf8',
      env: { ...process.env, APP_ENV: 'test' },
    });

    return `${res.stdout || ''}${res.stderr || ''}` || `PHP direct executor could not run phpunit in ${shop}. Install PHP or set PHPUNIT_REPORT.`;
  }
}
