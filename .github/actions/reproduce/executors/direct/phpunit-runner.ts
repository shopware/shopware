import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import type { SpawnSyncOptionsWithStringEncoding } from 'node:child_process';
import { FILES } from '../../bundle.ts';
import type { Plan } from '../../types.ts';

/** A fully-resolved spawnSync invocation for the PHPUnit run (host or sandboxed). */
interface PhpunitCommand {
  cmd: string;
  args: string[];
  opts: SpawnSyncOptionsWithStringEncoding;
}

/**
 * Adapts the generated direct test to Shopware's existing PHPUnit integration test layout.
 *
 * Owns filesystem placement and process invocation only; it deliberately returns raw output so
 * classification remains a separate policy decision.
 */
export function runPhpunit(specPath: string, shop: string, _plan: Partial<Plan>, _target: string): string | null {
  if (process.env.PHPUNIT_REPORT) {
    return fs.readFileSync(process.env.PHPUNIT_REPORT, 'utf8');
  }
  if (!fs.existsSync(specPath)) {
    return null;
  }

  const dest = path.join(shop, 'tests/integration/Repro');
  fs.mkdirSync(dest, { recursive: true });
  fs.copyFileSync(specPath, path.join(dest, FILES.testPhp));
  const phpunitArgs = ['vendor/bin/phpunit', '--colors=never', 'tests/integration/Repro/ReproTest.php'];
  const { cmd, args, opts } = phpunitCommand(shop, phpunitArgs);
  const res = spawnSync(cmd, args, opts);

  return `${res.stdout || ''}${res.stderr || ''}` || `PHP direct executor could not run phpunit in ${shop}. Install PHP or set PHPUNIT_REPORT.`;
}

/**
 * Builds the command that runs the (untrusted, agent-authored) PHPUnit test.
 *
 * Host-side (`php`) by default. When `REPRO_SANDBOX=1` the test runs inside a PHP container with no
 * internet (the job drops container egress): the agent-authored test can boot the kernel and hit the
 * DB, but a test that shells out or opens sockets cannot exfiltrate or abuse the network. The shop is
 * bind-mounted at the same path so `vendor/bin/phpunit` and the kernel resolve identically, and the
 * DB host in DATABASE_URL is rewritten to host.docker.internal (the container's localhost is itself).
 */
function phpunitCommand(shop: string, phpunitArgs: string[]): PhpunitCommand {
  const baseEnv = { ...process.env, APP_ENV: 'test' };
  if (process.env.REPRO_SANDBOX !== '1') {
    return { cmd: 'php', args: phpunitArgs, opts: { cwd: shop, encoding: 'utf8', env: baseEnv } };
  }
  const image = process.env.REPRO_SANDBOX_PHP_IMAGE || 'repro-php:local';
  const shopAbs = path.resolve(shop);
  const dbUrl = (process.env.DATABASE_URL || '').replace(/@(127\.0\.0\.1|localhost)(?=[:/])/, '@host.docker.internal');
  return {
    cmd: 'docker',
    args: [
      'run', '--rm',
      '--add-host=host.docker.internal:host-gateway',
      '--user', `${process.getuid!()}:${process.getgid!()}`,
      '-e', 'HOME=/tmp', '-e', 'APP_ENV=test', '-e', `DATABASE_URL=${dbUrl}`,
      '-v', `${shopAbs}:${shopAbs}`, '-w', shopAbs,
      image, 'php', ...phpunitArgs,
    ],
    opts: { encoding: 'utf8', env: process.env },
  };
}
