import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

// full-run.ts exports fullRun() and has no CLI entry of its own (verify/try drive it). To test the
// orchestration itself in a real subprocess, we import the source by file URL inside `node -e` and
// call fullRun() with the cwd + env each case needs. Every path exercised here is reachable offline:
// setup failures (missing/invalid plan, failed reset, failed seed) block BEFORE any executor touches
// the network, and the one executor-dispatch case uses an unknown executor so no live shop is needed.
const HERE = path.dirname(fileURLToPath(import.meta.url));
const SRC = pathToFileURL(path.join(HERE, 'full-run.ts')).href;

const tmp = () => fs.mkdtempSync(path.join(os.tmpdir(), 'full-run-'));

// Minimal env: only PATH/HOME are inherited so a stray APP_URL / DATABASE_URL / REPRO_* in the test
// runner's own environment can never leak into a case that asserts on their absence.
const baseEnv = () => ({ PATH: process.env.PATH ?? '', HOME: process.env.HOME ?? '' });

/**
 * Runs fullRun({ target, out, reset }) in a subprocess rooted at `cwd`.
 * Returns { status, stdout, stderr, result } — `result` is the value fullRun resolved to.
 */
function runFull(cwd: string, { target = 'reported', out = 'result.json', reset = false, env = {} }: {
  target?: string;
  out?: string;
  reset?: boolean;
  env?: Record<string, string>;
} = {}) {
  const code = [
    `const { fullRun } = await import(${JSON.stringify(SRC)});`,
    `const r = await fullRun({ target: ${JSON.stringify(target)}, out: ${JSON.stringify(out)}, reset: ${reset ? 'true' : 'false'} });`,
    `process.stdout.write('__R__' + JSON.stringify(r ?? null));`,
  ].join('\n');
  const proc = spawnSync('node', ['--input-type=module', '-e', code], {
    cwd,
    encoding: 'utf8',
    env: { ...baseEnv(), ...env },
  });
  const marker = (proc.stdout || '').indexOf('__R__');
  return {
    status: proc.status,
    stdout: proc.stdout || '',
    stderr: proc.stderr || '',
    result: marker >= 0 ? JSON.parse(proc.stdout.slice(marker + 5)) : undefined,
  };
}

const readResult = (cwd: string, name = 'result.json') => JSON.parse(fs.readFileSync(path.join(cwd, name), 'utf8'));
const writePlan = (cwd: string, plan: Record<string, unknown>) => fs.writeFileSync(path.join(cwd, 'reproduction-plan.json'), JSON.stringify(plan));
const httpPlan = { executor: 'http', layer: 'store-api', issue: 42, version: '6.7.0.0' };

// Builds a PATH-shadowing dir whose `mysql` always fails, so reset()'s restore pipe exits non-zero
// deterministically without a real MySQL client or server.
function stubMysqlPath(cwd: string) {
  const dir = path.join(cwd, 'stub-bin');
  fs.mkdirSync(dir);
  fs.writeFileSync(path.join(dir, 'mysql'), '#!/bin/sh\nexit 1\n', { mode: 0o755 });
  return `${dir}:${process.env.PATH ?? ''}`;
}

test('no APP_URL blocks the leg with a coordinates reason and no executor runs', () => {
  const dir = tmp();
  writePlan(dir, httpPlan);
  const r = runFull(dir, { env: {} }); // APP_URL deliberately absent

  assert.equal(r.status, 0, r.stderr);
  assert.deepEqual(r.result, { status: 'blocked' });
  const result = readResult(dir);
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /APP_URL is not set/);
  assert.match(r.stderr, /::error::/);
});

test('a missing reproduction-plan.json blocks the leg', () => {
  const dir = tmp();
  const r = runFull(dir, { env: { APP_URL: 'http://127.0.0.1:9' } });

  assert.equal(r.status, 0, r.stderr);
  const result = readResult(dir);
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /reproduction-plan\.json not found/);
});

test('a malformed plan blocks the leg (never an unhandled rejection) and still writes result.json', () => {
  const dir = tmp();
  fs.writeFileSync(path.join(dir, 'reproduction-plan.json'), '{ this is : not json');
  const r = runFull(dir, { env: { APP_URL: 'http://127.0.0.1:9' } });

  assert.equal(r.status, 0, r.stderr);
  assert.deepEqual(r.result, { status: 'blocked' });
  const result = readResult(dir);
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /reproduction-plan\.json is not valid JSON/);
  // fail() reads the plan with a {} fallback, so metadata degrades gracefully instead of throwing.
  assert.equal(result.executor, 'unknown');
  assert.equal(result.issue, 0);
});

test('a failed DB reset blocks the leg BEFORE seeding (reset runs first and short-circuits)', () => {
  const dir = tmp();
  writePlan(dir, httpPlan);
  // A snapshot EXISTS but the restore fails → fail closed, never judge on un-restored state.
  fs.writeFileSync(path.join(dir, 'repro-clean-db.sql.gz'), 'not-a-real-gzip');
  // fixtures present too: if reset did NOT short-circuit, seed would run and drop seed-error.txt.
  fs.writeFileSync(path.join(dir, 'fixtures.json'), '{ invalid json');

  const r = runFull(dir, {
    reset: true,
    env: {
      APP_URL: 'http://127.0.0.1:9',
      DATABASE_URL: 'mysql://root:root@127.0.0.1:3306/testdb',
      PATH: stubMysqlPath(dir),
    },
  });

  assert.equal(r.status, 0, r.stderr);
  const result = readResult(dir);
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /DB reset failed/);
  // Ordering proof: seeding never ran, so its error file was never written.
  assert.equal(fs.existsSync(path.join(dir, 'seed-error.txt')), false);
});

test('reset passes with no snapshot, then a seed failure blocks the leg (reset -> seed ordering)', () => {
  const dir = tmp();
  writePlan(dir, httpPlan);
  // No snapshot → reset() returns ok and the sequence proceeds to seeding.
  fs.writeFileSync(path.join(dir, 'fixtures.json'), '{ invalid json'); // seed fails before any network

  const r = runFull(dir, { reset: true, env: { APP_URL: 'http://127.0.0.1:9' } });

  assert.equal(r.status, 0, r.stderr);
  const result = readResult(dir);
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /seeding fixtures\.json failed/);
  // Ordering proof: seeding WAS reached this time (its error file exists), unlike the reset-fail case.
  assert.equal(fs.existsSync(path.join(dir, 'seed-error.txt')), true);
});

test('readiness runs only AFTER a successful seed — a seed failure blocks before any readiness check', () => {
  const dir = tmp();
  writePlan(dir, { ...httpPlan, executor: 'playwright', layer: 'storefront-ui' });
  fs.writeFileSync(path.join(dir, 'fixtures.json'), '{ invalid json'); // seed fails first

  const r = runFull(dir, { reset: true, env: { APP_URL: 'http://127.0.0.1:9' } });

  assert.equal(r.status, 0, r.stderr);
  const result = readResult(dir);
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /seeding fixtures\.json failed/);
  // Ordering proof: the playwright readiness module was never imported/executed, so its report is absent.
  assert.equal(fs.existsSync(path.join(dir, 'seeded-readiness.json')), false);
});

test('with reset clean and no fixtures, the sequence reaches executor dispatch and writes its result', () => {
  const dir = tmp();
  // Unknown executor keeps executeBundle offline: it classifies as inconclusive without a live shop.
  writePlan(dir, { executor: 'telepathy', layer: 'store-api', issue: 7, version: '6.7.0.0' });

  const r = runFull(dir, { reset: true, env: { APP_URL: 'http://127.0.0.1:9' } });

  assert.equal(r.status, 0, r.stderr);
  // fullRun returns the executor's own result object here (not the bare { status: 'blocked' } of fail()).
  assert.equal(r.result.status, 'inconclusive');
  const result = readResult(dir);
  assert.equal(result.status, 'inconclusive');
  assert.equal(result.executor, 'telepathy');
  assert.match(result.blocked_reason, /not one of playwright\/http\/direct/);
});

test('reset:false skips the DB reset entirely and writes to the given out path', () => {
  const dir = tmp();
  writePlan(dir, httpPlan);
  // A snapshot + stubbed failing mysql are present; reset:false must NOT invoke them at all, so the
  // leg proceeds to seeding and blocks there — proving the reset flag gates the DB restore.
  fs.writeFileSync(path.join(dir, 'repro-clean-db.sql.gz'), 'not-a-real-gzip');
  fs.writeFileSync(path.join(dir, 'fixtures.json'), '{ invalid json');

  const r = runFull(dir, {
    reset: false,
    out: 'builder-result.json',
    env: {
      APP_URL: 'http://127.0.0.1:9',
      DATABASE_URL: 'mysql://root:root@127.0.0.1:3306/testdb',
      PATH: stubMysqlPath(dir),
    },
  });

  assert.equal(r.status, 0, r.stderr);
  // Blocked at seeding (a DB-reset failure would have said "DB reset failed" instead).
  const result = readResult(dir, 'builder-result.json');
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /seeding fixtures\.json failed/);
  // The custom out path was honored; the official result.json was left untouched.
  assert.equal(fs.existsSync(path.join(dir, 'result.json')), false);
});
