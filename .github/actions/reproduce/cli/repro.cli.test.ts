import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const CLI = path.join(path.dirname(fileURLToPath(import.meta.url)), 'repro.ts');
const run = (args: string[], cwd?: string) => spawnSync('node', [CLI, ...args], { cwd, encoding: 'utf8' });
const tmp = () => fs.mkdtempSync(path.join(os.tmpdir(), 'repro-cli-'));

test('an unknown command exits 2 and lists the commands', () => {
  const r = run(['frobnicate']);
  assert.equal(r.status, 2);
  assert.match(r.stderr, /unknown command 'frobnicate'/);
  assert.match(r.stderr, /validate \| reset \| seed/);
});

test('no command exits 2', () => {
  assert.equal(run([]).status, 2);
});

test('blocked-result without a target exits 2', () => {
  assert.equal(run(['blocked-result']).status, 2);
});

test('blocked-result writes a canonical blocked result.json', () => {
  const dir = tmp();
  const r = run(['blocked-result', 'trunk', 'env did not come up', '6.7.0.0'], dir);
  assert.equal(r.status, 0);
  const result = JSON.parse(fs.readFileSync(path.join(dir, 'result.json'), 'utf8'));
  assert.equal(result.status, 'blocked');
  assert.equal(result.target, 'trunk');
  assert.equal(result.version, '6.7.0.0');
  assert.equal(result.blocked_reason, 'env did not come up');
});

test('validate refuses a plan with an unknown executor', () => {
  const dir = tmp();
  fs.writeFileSync(path.join(dir, 'reproduction-plan.json'), JSON.stringify({ executor: 'telepathy', layer: 'store-api', issue: 1, version: '6.7.0.0' }));
  const r = run(['validate'], dir);
  assert.equal(r.status, 1);
  assert.match(r.stderr, /executor must be one of/);
});

test('validate refuses an http plan missing requests/assertions', () => {
  const dir = tmp();
  fs.writeFileSync(path.join(dir, 'reproduction-plan.json'), JSON.stringify({ executor: 'http', layer: 'store-api', issue: 1, version: '6.7.0.0' }));
  const r = run(['validate'], dir);
  assert.equal(r.status, 1);
  assert.match(r.stderr, /needs a `request`|needs an `assertion`/);
});

test('validate accepts a well-formed http plan', () => {
  const dir = tmp();
  fs.writeFileSync(path.join(dir, 'reproduction-plan.json'), JSON.stringify({
    executor: 'http', layer: 'store-api', issue: 1, version: '6.7.0.0',
    request: { method: 'GET', path: '/store-api/x' },
    assertion: { field: '.active', op: 'equals', expect: 'true' },
  }));
  const r = run(['validate'], dir);
  assert.equal(r.status, 0, r.stderr);
  assert.match(r.stdout, /ok/);
});
