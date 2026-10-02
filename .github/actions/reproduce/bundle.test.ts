import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {
  readJson, writeJson, makeResult, blockedResult,
  fillPlaceholders, unresolvedPlaceholders, referencedPlaceholders, appUrl,
} from './bundle.ts';
import type { LegStatus } from './types.ts';

const tmp = () => fs.mkdtempSync(path.join(os.tmpdir(), 'repro-bundle-'));

test('readJson: parses, honours a fallback, and rethrows without one', () => {
  const dir = tmp();
  const good = path.join(dir, 'good.json');
  writeJson(good, { a: 1 });
  assert.deepEqual(readJson(good), { a: 1 });
  assert.match(fs.readFileSync(good, 'utf8'), /\n$/); // writeJson newline-terminates
  assert.equal(readJson(path.join(dir, 'missing.json'), null), null);
  fs.writeFileSync(path.join(dir, 'bad.json'), '{not json');
  assert.equal(readJson(path.join(dir, 'bad.json'), 'FB'), 'FB');
  assert.throws(() => readJson(path.join(dir, 'bad.json')));
});

test('fillPlaceholders: longest key first so SALUTATION2 is not eaten by SALUTATION', () => {
  const out = fillPlaceholders('{{SALUTATION}}|{{SALUTATION2}}', { SALUTATION: 'A', SALUTATION2: 'B' });
  assert.equal(out, 'A|B');
});

test('fillPlaceholders: an empty/absent resolution leaves the token intact', () => {
  assert.equal(fillPlaceholders('{{COUNTRY}}', { COUNTRY: '' }), '{{COUNTRY}}');
  assert.equal(fillPlaceholders('{{COUNTRY}}', {}), '{{COUNTRY}}');
});

test('fillPlaceholders: serialises objects before substituting', () => {
  assert.equal(fillPlaceholders({ id: '{{TAX}}' }, { TAX: 't1' }), '{"id":"t1"}');
});

test('unresolvedPlaceholders / referencedPlaceholders', () => {
  assert.deepEqual(unresolvedPlaceholders('{{A}} then {{B}} and {{A}}'), ['{{A}}', '{{B}}']);
  assert.deepEqual(referencedPlaceholders({ x: '{{COUNTRY}}' }, '{{TAX}}').sort(), ['COUNTRY', 'TAX']);
});

test('appUrl strips a trailing slash', () => {
  const prev = process.env.APP_URL;
  process.env.APP_URL = 'http://host:8000/';
  assert.equal(appUrl(), 'http://host:8000');
  if (prev === undefined) delete process.env.APP_URL; else process.env.APP_URL = prev;
});

test('makeResult: resolved version env wins over plan.version', () => {
  const prev = process.env.REPRO_RESOLVED_VERSION;
  process.env.REPRO_RESOLVED_VERSION = 'v6.7.1.0';
  const r = makeResult({ plan: { issue: 5, version: '6.0.0.0', executor: 'http' }, target: 'reported', status: 'reproduced', assertion: {} });
  assert.equal(r.version, 'v6.7.1.0');
  assert.equal(r.issue, 5);
  assert.equal(r.schema_version, '1');
  assert.equal(r.evidence.script_lang, 'sh'); // default evidence shape present
  delete process.env.REPRO_RESOLVED_VERSION;
  // The bogus status here is intentional test data — the assertion below only checks the version
  // fallback, so the value is asserted through `unknown` to keep this a pure runtime no-op.
  const r2 = makeResult({ plan: { version: '6.0.0.0' }, target: 't', status: 'x' as unknown as LegStatus, assertion: {} });
  assert.equal(r2.version, '6.0.0.0');
  if (prev !== undefined) process.env.REPRO_RESOLVED_VERSION = prev;
});

test('blockedResult: status blocked with the reason surfaced', () => {
  const r = blockedResult({ issue: 1, executor: 'http' }, 'trunk', 'db down');
  assert.equal(r.status, 'blocked');
  assert.equal(r.blocked_reason, 'db down');
  assert.equal(r.evidence.reporter_output, 'db down');
  assert.equal(r.assertion.matched, null);
});
