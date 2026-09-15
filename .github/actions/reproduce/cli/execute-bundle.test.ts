import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

// execute-bundle.ts is not a CLI entrypoint; it exports `executeBundle({ target, out })` and reads
// `reproduction-plan.json` from the process cwd. We drive it in a subprocess (a tiny module-eval
// runner) so each case gets clean module state — the admin-api token / placeholder caches never leak
// between legs — and so the leg's cwd is an isolated mkdtemp dir exactly like the real pipeline.
const SRC = pathToFileURL(path.join(path.dirname(fileURLToPath(import.meta.url)), 'execute-bundle.ts')).href;
const RUNNER = `import { executeBundle } from ${JSON.stringify(SRC)};\n`
  + `await executeBundle({ target: process.env.REPRO_TEST_TARGET || 'reported', out: 'result.json' });\n`;

// Env keys that would otherwise perturb these offline cases: APP_URL must stay unset so any executor
// that reaches the network throws deterministically instead of hanging; the verify/sandbox sentinels
// and the resolved-version override are opt-in per test.
const VOLATILE = ['APP_URL', 'REPRO_ALLOW_VERIFY', 'REPRO_SANDBOX_ARMED', 'REPRO_RESOLVED_VERSION', 'SW_ACCESS_KEY'];

/**
 * Runs executeBundle in a subprocess against an authored plan and returns exit/stdio + result.json.
 *
 * Pass `plan` for a normal object plan, or `planRaw` to write bytes verbatim (malformed JSON); omit
 * both to exercise the missing-plan path.
 */
function runBundle({ plan, planRaw, target = 'reported', env = {} }: {
  plan?: Record<string, unknown>;
  planRaw?: string;
  target?: string;
  env?: Record<string, string>;
} = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'repro-exec-'));
  if (planRaw !== undefined) {
    fs.writeFileSync(path.join(dir, 'reproduction-plan.json'), planRaw);
  } else if (plan !== undefined) {
    fs.writeFileSync(path.join(dir, 'reproduction-plan.json'), JSON.stringify(plan));
  }

  const childEnv = { ...process.env };
  for (const key of VOLATILE) {
    delete childEnv[key];
  }
  childEnv.REPRO_TEST_TARGET = target;
  Object.assign(childEnv, env);

  const r = spawnSync('node', ['--input-type=module', '-e', RUNNER], { cwd: dir, env: childEnv, encoding: 'utf8' });
  const resultPath = path.join(dir, 'result.json');
  const result = fs.existsSync(resultPath) ? JSON.parse(fs.readFileSync(resultPath, 'utf8')) : null;
  return { r, dir, result };
}

test('an unknown executor writes an inconclusive result, never blocked or reproduced', () => {
  const { r, result } = runBundle({
    plan: { executor: 'telepathy', layer: 'store-api', issue: 42, version: '6.7.0.0' },
  });

  assert.equal(r.status, 0, r.stderr);
  assert.ok(result, 'result.json must be written');
  assert.equal(result.status, 'inconclusive');
  assert.equal(result.executor, 'telepathy');
  assert.equal(result.issue, 42);
  assert.equal(result.assertion.matched, null);
  assert.match(result.evidence.reporter_output, /unknown executor 'telepathy'/);
  assert.match(result.blocked_reason, /is not one of playwright\/http\/direct/);
  assert.match(r.stdout, /status=inconclusive/);
});

test('an executor that throws during run becomes a blocked leg with the reason', () => {
  // A well-formed http plan referencing an entity placeholder forces resolvePlaceholders(), which
  // calls the admin API; with APP_URL unset the base() lookup throws synchronously before any real
  // network — a deterministic, offline stand-in for the "classify/prepare threw" path.
  const { r, result } = runBundle({
    plan: {
      executor: 'http', layer: 'store-api', issue: 7, version: '6.7.0.0',
      request: { method: 'GET', path: '/store-api/country/{{COUNTRY}}' },
      assertion: { field: '.active', op: 'equals', expect: 'true' },
    },
  });

  assert.equal(r.status, 0, r.stderr);
  assert.ok(result, 'result.json must be written even when the executor throws');
  assert.equal(result.status, 'blocked');
  assert.equal(result.executor, 'http');
  assert.equal(result.assertion.matched, null);
  assert.match(result.evidence.reporter_output, /executor 'http' threw during run/);
  assert.match(result.blocked_reason, /executor 'http' threw during run: APP_URL is required/);
  assert.match(r.stdout, /status=blocked/);
});

test('a malformed plan blocks with a JSON parse reason and emits a ::error:: annotation', () => {
  const { r, result } = runBundle({ planRaw: '{ this is not: valid json' });

  assert.equal(r.status, 0, r.stderr);
  assert.ok(result, 'a malformed plan must still yield a result.json');
  assert.equal(result.status, 'blocked');
  // A malformed plan has no parsable executor, so the canonical shape falls back to 'unknown'.
  assert.equal(result.executor, 'unknown');
  assert.equal(result.assertion.matched, null);
  assert.match(result.blocked_reason, /reproduction-plan\.json is not valid JSON/);
  assert.match(r.stderr, /::error::reproduction-plan\.json is not valid JSON/);
  assert.match(r.stdout, /status=blocked/);
});

test('a missing plan file is treated as an unreadable plan and blocks', () => {
  const { r, result } = runBundle({});

  assert.equal(r.status, 0, r.stderr);
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /reproduction-plan\.json is not valid JSON/);
  assert.match(r.stderr, /::error::/);
});

for (const executor of ['playwright', 'direct']) {
  test(`a trusted ${executor} verify refuses to run when the sandbox is not armed`, () => {
    const { r, result } = runBundle({
      plan: { executor, layer: 'admin-ui', issue: 9, version: '6.7.0.0' },
      env: { REPRO_ALLOW_VERIFY: '1' }, // REPRO_SANDBOX_ARMED stays unset
    });

    assert.equal(r.status, 0, r.stderr);
    assert.equal(result.status, 'blocked');
    assert.equal(result.executor, executor);
    assert.equal(result.assertion.matched, null);
    assert.match(result.blocked_reason, new RegExp(`refusing to run the ${executor} verify unsandboxed`));
    assert.match(result.blocked_reason, /REPRO_SANDBOX_ARMED unset/);
    assert.match(r.stderr, /::error::/);
    assert.match(r.stdout, /status=blocked/);
  });
}

test('the sandbox gate does not apply to http (no agent code runs there)', () => {
  // With REPRO_ALLOW_VERIFY=1 and the sandbox unarmed, an http leg is NOT refused up front; it
  // proceeds to run and blocks only because the (offline) executor throws — proving the gate is
  // scoped to the playwright/direct specs that execute agent-authored code.
  const { r, result } = runBundle({
    plan: {
      executor: 'http', layer: 'store-api', issue: 11, version: '6.7.0.0',
      request: { method: 'GET', path: '/store-api/country/{{COUNTRY}}' },
      assertion: { field: '.active', op: 'equals', expect: 'true' },
    },
    env: { REPRO_ALLOW_VERIFY: '1' },
  });

  assert.equal(r.status, 0, r.stderr);
  assert.equal(result.status, 'blocked');
  assert.match(result.blocked_reason, /threw during run/);
  assert.doesNotMatch(result.blocked_reason, /refusing to run/);
});

test('REPRO_RESOLVED_VERSION overrides the agent-authored plan.version in the result', () => {
  const { result } = runBundle({
    plan: { executor: 'telepathy', layer: 'store-api', issue: 1, version: '6.6.0.0' },
    env: { REPRO_RESOLVED_VERSION: '6.7.1.0' },
  });

  assert.equal(result.version, '6.7.1.0');
});

test('the target argument is carried onto the leg result', () => {
  const { result } = runBundle({
    plan: { executor: 'telepathy', layer: 'store-api', issue: 1, version: '6.7.0.0' },
    target: 'trunk',
  });

  assert.equal(result.target, 'trunk');
});
