import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { preparePlaywrightAuth } from './auth-preparer.ts';

/**
 * Runs `fn` inside a throwaway cwd with a controlled APP_URL.
 *
 * `preparePlaywrightAuth` reuses/writes relative state files (`admin-state.json`,
 * `.repro-storefront-state.json`) in `process.cwd()` and reads the app URL from the
 * environment, so each case gets an isolated directory and the original cwd/env are
 * always restored. Top-level node:test cases run sequentially, so the in-process
 * chdir is safe.
 */
function inCase<T>({ appUrl }: { appUrl: string | undefined }, fn: (dir: string) => T): T {
  const prevCwd = process.cwd();
  const prevAppUrl = process.env.APP_URL;
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'auth-preparer-'));
  if (appUrl === undefined) {
    delete process.env.APP_URL;
  } else {
    process.env.APP_URL = appUrl;
  }
  process.chdir(dir);
  try {
    return fn(dir);
  } finally {
    process.chdir(prevCwd);
    if (prevAppUrl === undefined) {
      delete process.env.APP_URL;
    } else {
      process.env.APP_URL = prevAppUrl;
    }
    fs.rmSync(dir, { recursive: true, force: true });
  }
}

test('admin-ui reuses an existing admin-state.json on a builder run (no fresh login)', () => {
  inCase({ appUrl: 'http://localhost:8000' }, () => {
    // A builder run passes force=false, so an existing state file is reused rather
    // than triggering a browser login — that keeps this a pure, offline assertion.
    fs.writeFileSync('admin-state.json', '{"cookies":[],"origins":[]}');
    const res = preparePlaywrightAuth({ layer: 'admin-ui' }, 'builder');
    assert.deepEqual(res, { storageState: 'admin-state.json' });
  });
});

test('admin-ui is blocked (harness env problem) when there is no app URL', () => {
  inCase({ appUrl: undefined }, () => {
    // An empty APP_URL short-circuits ensureAdminState before any login spawn, even
    // though a state file happens to be present.
    fs.writeFileSync('admin-state.json', '{}');
    const res = preparePlaywrightAuth({ layer: 'admin-ui' }, 'builder');
    assert.deepEqual(res, {
      blockedReason:
        'the harness could not log in to the admin (env problem, not a reproduction result)',
      evidence: { script_lang: 'ts', reporter_output: 'harness admin login failed' },
    });
    assert.equal((res as { storageState?: string }).storageState, undefined);
  });
});

test('a forced verify run is also blocked (not a repro result) when there is no app URL', () => {
  inCase({ appUrl: undefined }, () => {
    // target !== 'builder' forces a fresh login, but an empty URL still short-circuits
    // to the blocked verdict without launching a browser.
    const res = preparePlaywrightAuth({ layer: 'admin-ui' }, 'verify');
    assert.match(res.blockedReason!, /could not log in to the admin/);
    assert.equal(res.storageState, undefined);
  });
});

test('storefront-ui pre-accepts cookie consent by writing a storefront state file', () => {
  inCase({ appUrl: 'http://127.0.0.1:1' }, () => {
    // 127.0.0.1:1 refuses immediately; the consent helper catches the failed
    // /cookie/groups fetch and still writes a valid storageState (empty hash).
    const res = preparePlaywrightAuth({ layer: 'storefront-ui' }, 'builder');
    assert.deepEqual(res, { storageState: '.repro-storefront-state.json' });
    assert.equal(fs.existsSync('.repro-storefront-state.json'), true);
    const state = JSON.parse(fs.readFileSync('.repro-storefront-state.json', 'utf8')) as {
      cookies: Array<{ name: string }>;
    };
    assert.ok(state.cookies.some((c) => c.name === 'cookie-preference'));
    assert.ok(state.cookies.some((c) => c.name === 'cookie-config-hash'));
  });
});

test('storefront-ui with auto_cookie_consent=false skips consent and returns no state', () => {
  inCase({ appUrl: 'http://localhost:8000' }, () => {
    const res = preparePlaywrightAuth(
      { layer: 'storefront-ui', browser_state: { auto_cookie_consent: false } },
      'builder',
    );
    assert.deepEqual(res, { storageState: '' });
    // The consent helper must not have run.
    assert.equal(fs.existsSync('.repro-storefront-state.json'), false);
  });
});

test('storefront-ui with auto_cookie_consent=true still writes the consent state', () => {
  inCase({ appUrl: 'http://127.0.0.1:1' }, () => {
    const res = preparePlaywrightAuth(
      { layer: 'storefront-ui', browser_state: { auto_cookie_consent: true } },
      'builder',
    );
    assert.deepEqual(res, { storageState: '.repro-storefront-state.json' });
    assert.equal(fs.existsSync('.repro-storefront-state.json'), true);
  });
});

test('storefront-ui falls back to empty state when the consent helper fails', () => {
  inCase({ appUrl: undefined }, () => {
    // An empty URL makes consent-state.mjs exit non-zero and write nothing, so the
    // preparer degrades to an empty storageState instead of a phantom file path.
    const res = preparePlaywrightAuth({ layer: 'storefront-ui', browser_state: {} }, 'builder');
    assert.deepEqual(res, { storageState: '' });
    assert.equal(fs.existsSync('.repro-storefront-state.json'), false);
  });
});

test('non-UI layers get an empty storage state and never touch auth files', () => {
  for (const layer of ['store-api', 'admin-api', 'service'] as const) {
    inCase({ appUrl: 'http://localhost:8000' }, () => {
      const res = preparePlaywrightAuth({ layer }, 'builder');
      assert.deepEqual(res, { storageState: '' }, `layer=${layer}`);
      assert.equal(fs.existsSync('admin-state.json'), false, `layer=${layer}`);
      assert.equal(fs.existsSync('.repro-storefront-state.json'), false, `layer=${layer}`);
    });
  }
});

test('admin vs storefront select distinct storage states from the same app URL', () => {
  inCase({ appUrl: 'http://127.0.0.1:1' }, () => {
    fs.writeFileSync('admin-state.json', '{"cookies":[],"origins":[]}');
    const admin = preparePlaywrightAuth({ layer: 'admin-ui' }, 'builder');
    const storefront = preparePlaywrightAuth({ layer: 'storefront-ui' }, 'builder');
    assert.equal(admin.storageState, 'admin-state.json');
    assert.equal(storefront.storageState, '.repro-storefront-state.json');
    assert.notEqual(admin.storageState, storefront.storageState);
  });
});
