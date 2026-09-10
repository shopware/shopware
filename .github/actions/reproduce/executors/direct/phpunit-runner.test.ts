import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { runPhpunit } from './phpunit-runner.ts';
import { FILES } from '../../bundle.ts';

// runPhpunit shells out via spawnSync('php'|'docker', …). Instead of a real PHP/Docker toolchain we
// put a stub `php` and `docker` on PATH that echoes back the argv (and cwd) it was invoked with, so
// every assertion is offline, deterministic, and exercises the real command-building code path.

const ENV_KEYS = ['PHPUNIT_REPORT', 'REPRO_SANDBOX', 'REPRO_SANDBOX_PHP_IMAGE', 'DATABASE_URL', 'PATH', 'APP_ENV'];
let saved: Record<string, string | undefined>;
let workdir: string;
let bindir: string;

// Writes an executable stub that prints one recognisable line describing its invocation.
function stub(name: string, body: string): void {
  const file = path.join(bindir, name);
  fs.writeFileSync(file, `#!/bin/sh\n${body}\n`, { mode: 0o755 });
  fs.chmodSync(file, 0o755);
}

// A stub that echoes a tagged, space-joined view of $PWD and every argument.
const echoArgv = (tag: string): string => `printf '${tag} cwd=%s' "$PWD"; for a in "$@"; do printf ' |%s' "$a"; done`;

beforeEach(() => {
  saved = Object.fromEntries(ENV_KEYS.map((k) => [k, process.env[k]]));
  workdir = fs.mkdtempSync(path.join(os.tmpdir(), 'phpunit-runner-'));
  bindir = path.join(workdir, 'bin');
  fs.mkdirSync(bindir, { recursive: true });
  // Isolate the run: only our stubs are resolvable, plus the real node/sh location if needed.
  process.env.PATH = `${bindir}:${saved.PATH}`;
  delete process.env.PHPUNIT_REPORT;
  delete process.env.REPRO_SANDBOX;
  delete process.env.REPRO_SANDBOX_PHP_IMAGE;
  delete process.env.DATABASE_URL;
});

afterEach(() => {
  for (const k of ENV_KEYS) {
    if (saved[k] === undefined) {
      delete process.env[k];
    } else {
      process.env[k] = saved[k];
    }
  }
  fs.rmSync(workdir, { recursive: true, force: true });
});

// Creates a shop checkout dir and an authored spec file, returning both paths.
function scaffold() {
  const shop = path.join(workdir, 'shop');
  fs.mkdirSync(shop, { recursive: true });
  const specPath = path.join(workdir, 'ReproTest.php');
  fs.writeFileSync(specPath, '<?php // authored repro test\n');
  return { shop, specPath };
}

test('PHPUNIT_REPORT short-circuits: returns the report file verbatim without spawning', () => {
  const report = path.join(workdir, 'canned.txt');
  fs.writeFileSync(report, 'OK (1 test, 2 assertions)\n');
  process.env.PHPUNIT_REPORT = report;
  // A stub that would explode if it were ever executed — proves no spawn happens.
  stub('php', 'echo SHOULD_NOT_RUN; exit 3');
  const { shop } = scaffold();

  // specPath deliberately does NOT exist: the report short-circuit is checked before existsSync.
  const out = runPhpunit(path.join(workdir, 'missing.php'), shop, {}, 'reported');

  assert.equal(out, 'OK (1 test, 2 assertions)\n');
  // The spec was never copied into the shop.
  assert.equal(fs.existsSync(path.join(shop, 'tests/integration/Repro', FILES.testPhp)), false);
});

test('missing spec (no PHPUNIT_REPORT) returns null', () => {
  const { shop } = scaffold();
  const out = runPhpunit(path.join(workdir, 'does-not-exist.php'), shop, {}, 'reported');
  assert.equal(out, null);
});

test('host command: copies spec into the shop and runs php with phpunit args from the shop cwd', () => {
  stub('php', echoArgv('STUB_PHP'));
  const { shop, specPath } = scaffold();

  const out = runPhpunit(specPath, shop, {}, 'reported')!;

  // The authored spec is placed at the fixed integration-test location under the shop.
  const copied = path.join(shop, 'tests/integration/Repro', FILES.testPhp);
  assert.equal(fs.existsSync(copied), true);
  assert.equal(fs.readFileSync(copied, 'utf8'), '<?php // authored repro test\n');

  // The command is the host `php` invocation, run from the shop directory.
  assert.match(out, /^STUB_PHP cwd=/);
  assert.ok(out.includes(`cwd=${fs.realpathSync(shop)}`) || out.includes(`cwd=${shop}`), `cwd not the shop dir: ${out}`);
  assert.ok(out.includes('|vendor/bin/phpunit'), out);
  assert.ok(out.includes('|--colors=never'), out);
  assert.ok(out.includes('|tests/integration/Repro/ReproTest.php'), out);
  // No container flags leaked into the host path.
  assert.equal(out.includes('|run'), false);
});

test('empty toolchain output falls back to the install-hint message', () => {
  // A php stub that succeeds but prints nothing → stdout+stderr is '' (falsy) → fallback message.
  stub('php', 'exit 0');
  const { shop, specPath } = scaffold();

  const out = runPhpunit(specPath, shop, {}, 'reported')!;

  assert.match(out, /PHP direct executor could not run phpunit in/);
  assert.ok(out.includes(shop), out);
  assert.ok(out.includes('set PHPUNIT_REPORT'), out);
});

test('sandbox command: builds a docker run with the bind-mount, egress-free flags and image', () => {
  process.env.REPRO_SANDBOX = '1';
  process.env.REPRO_SANDBOX_PHP_IMAGE = 'my-php:test';
  stub('docker', echoArgv('STUB_DOCKER'));
  const { shop, specPath } = scaffold();
  const shopAbs = path.resolve(shop);

  const out = runPhpunit(specPath, shop, {}, 'reported')!;

  // Docker, not php, is invoked.
  assert.match(out, /^STUB_DOCKER cwd=/);
  assert.ok(out.includes('|run'), out);
  assert.ok(out.includes('|--rm'), out);
  assert.ok(out.includes('|--add-host=host.docker.internal:host-gateway'), out);
  // Shop is bind-mounted at the same absolute path and used as the working dir.
  assert.ok(out.includes(`|-v |${shopAbs}:${shopAbs}`), out);
  assert.ok(out.includes(`|-w |${shopAbs}`), out);
  // Configured image, then the phpunit invocation inside the container.
  assert.ok(out.includes('|my-php:test'), out);
  assert.ok(out.includes('|php |vendor/bin/phpunit'), out);
  assert.ok(out.includes('|tests/integration/Repro/ReproTest.php'), out);
  // The spec is still copied into the shop for the bind mount to expose.
  assert.equal(fs.existsSync(path.join(shop, 'tests/integration/Repro', FILES.testPhp)), true);
});

test('sandbox command: defaults the image and rewrites a localhost DATABASE_URL to host.docker.internal', () => {
  process.env.REPRO_SANDBOX = '1';
  process.env.DATABASE_URL = 'mysql://root:root@127.0.0.1:3306/shopware';
  stub('docker', echoArgv('STUB_DOCKER'));
  const { shop, specPath } = scaffold();

  const out = runPhpunit(specPath, shop, {}, 'reported')!;

  // Default image when REPRO_SANDBOX_PHP_IMAGE is unset.
  assert.ok(out.includes('|repro-php:local'), out);
  // The container's own localhost is itself, so the DB host is rewritten.
  assert.ok(out.includes('|DATABASE_URL=mysql://root:root@host.docker.internal:3306/shopware'), out);
  assert.equal(out.includes('@127.0.0.1'), false);
});

test('sandbox command: leaves a non-localhost DATABASE_URL untouched', () => {
  process.env.REPRO_SANDBOX = '1';
  process.env.DATABASE_URL = 'mysql://root:root@db.internal:3306/shopware';
  stub('docker', echoArgv('STUB_DOCKER'));
  const { shop, specPath } = scaffold();

  const out = runPhpunit(specPath, shop, {}, 'reported')!;

  assert.ok(out.includes('|DATABASE_URL=mysql://root:root@db.internal:3306/shopware'), out);
  assert.equal(out.includes('host.docker.internal'), true); // only from --add-host, not the DB url
  assert.equal((out.match(/host\.docker\.internal/g) || []).length, 1, out);
});
