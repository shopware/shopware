import { test } from 'node:test';
import assert from 'node:assert/strict';
import { classifyHttpAssertions } from './assertion-classifier.ts';
import type { HttpAssertion } from '../../types.ts';

// These tests shell out to the host `jq` (present on CI ubuntu runners); keep REPRO_SANDBOX unset so
// the scrubbed-host path is exercised rather than the docker path.
delete process.env.REPRO_SANDBOX;

const run = (assertions: HttpAssertion[], resp: { code: string; bodyText: string; blocked?: string }) =>
  classifyHttpAssertions(assertions, { blocked: '', ...resp });

test('a readable wrong value fails the assert -> reproduced', () => {
  const r = run([{ field: '.active', op: 'equals', expect: 'true' }], { code: '200', bodyText: '{"active":false}' });
  assert.equal(r.status, 'reproduced');
  assert.equal(r.checks[0].actual, 'false');
});

test('all assertions pass -> not_reproduced', () => {
  const r = run([{ field: '.active', op: 'equals', expect: 'true' }], { code: '200', bodyText: '{"active":true}' });
  assert.equal(r.status, 'not_reproduced');
});

test('$ENV field cannot read the runner environment (scrubbed jq)', () => {
  process.env.REPRO_TEST_SECRET = 'super-secret';
  try {
    const r = run([{ field: '$ENV.REPRO_TEST_SECRET', op: 'equals', expect: 'x' }], { code: '200', bodyText: '{}' });
    assert.doesNotMatch(String(r.checks[0].actual), /super-secret/);
  } finally {
    delete process.env.REPRO_TEST_SECRET;
  }
});

test('an empty request list captured no response -> inconclusive, never reproduced', () => {
  const r = run([{ kind: 'http_status', op: 'equals', expect: '200' }], { code: '', bodyText: '' });
  assert.equal(r.status, 'inconclusive');
});

test('a blocked sequence is passed through as blocked', () => {
  const r = run([{ field: '.a', op: 'present' }], { code: '', bodyText: '', blocked: 'setup request failed' });
  assert.equal(r.status, 'blocked');
});

test('a failed precondition -> inconclusive', () => {
  const r = run([{ field: '.ready', op: 'equals', expect: 'true', role: 'precondition' }], { code: '200', bodyText: '{"ready":false}' });
  assert.equal(r.status, 'inconclusive');
});

test('a genuinely empty field is readable "" (not <unparseable>) so absent passes', () => {
  const r = run([{ field: '.note', op: 'absent' }], { code: '200', bodyText: '{"note":""}' });
  assert.equal(r.checks[0].actual, '');
  assert.equal(r.status, 'not_reproduced');
});

// The three jq-failure classes must never masquerade as a reproduction on any status; each surfaces
// jq's own message so the agent can fix the filter (or re-express a shape symptom readably).
test('invalid jq filter on a 200 -> inconclusive with the jq error surfaced', () => {
  const r = run([{ field: '.data[[bad', op: 'equals', expect: 'x' }], { code: '200', bodyText: '{"data":1}' });
  assert.equal(r.status, 'inconclusive');
  assert.match(r.reporter, /jq:/);
  assert.equal(r.checks[0].actual, '<unparseable>');
});

test('wrong-shape access on a 200 -> inconclusive', () => {
  const r = run([{ field: '.data.name', op: 'equals', expect: 'x' }], { code: '200', bodyText: '{"data":[1,2,3]}' });
  assert.equal(r.status, 'inconclusive');
});

test('non-JSON body on a 200 -> inconclusive', () => {
  const r = run([{ field: '.data', op: 'contains', expect: 'x' }], { code: '200', bodyText: '<html>oops</html>' });
  assert.equal(r.status, 'inconclusive');
});

test('shape asserted readably via `.data | type` still reproduces', () => {
  const r = run([{ field: '.data | type', op: 'equals', expect: 'object' }], { code: '200', bodyText: '{"data":[1,2,3]}' });
  assert.equal(r.status, 'reproduced');
  assert.equal(r.checks[0].actual, 'array');
});

test('a 401 with no auth-status assertion is a harness-credential failure -> inconclusive', () => {
  const r = run([{ field: '.data', op: 'present' }], { code: '401', bodyText: '{}' });
  assert.equal(r.status, 'inconclusive');
});
