import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { prepareDirectSpec } from './spec-preparer.ts';
import { FILES } from '../../bundle.ts';

// The helper reads cwd-relative paths (the canonical spec name and the `vendor/bin/phpunit`
// probe) and honours the SHOP_DIR env var, so every test runs inside a throwaway cwd with a
// clean SHOP_DIR and restores both afterwards.
function withSandbox<T>(fn: (dir: string) => T): T {
  const cwd = process.cwd();
  const prevShop = process.env.SHOP_DIR;
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'spec-preparer-'));
  delete process.env.SHOP_DIR;
  process.chdir(dir);
  try {
    return fn(dir);
  } finally {
    process.chdir(cwd);
    if (prevShop === undefined) delete process.env.SHOP_DIR;
    else process.env.SHOP_DIR = prevShop;
    fs.rmSync(dir, { recursive: true, force: true });
  }
}

test('specPath is always the canonical bundle test file', () => {
  withSandbox(() => {
    const r = prepareDirectSpec({ plan: {} });
    assert.equal(r.specPath, FILES.testPhp);
    assert.equal(r.specPath, 'ReproTest.php');
  });
});

test('an agent-supplied script_path is ignored (pinned to the default, no arbitrary read)', () => {
  withSandbox((dir) => {
    // The default file the executor is allowed to read…
    const authored = '<?php\n// the real, allowed test\n';
    fs.writeFileSync(path.join(dir, FILES.testPhp), authored);
    // …and a file the agent tries to redirect the read to (simulating /etc/passwd, an env file, …).
    const injected = path.join(dir, 'secret.php');
    fs.writeFileSync(injected, '<?php\n// SECRET must never be read\n');

    const r = prepareDirectSpec({ plan: { script_path: injected } });

    assert.equal(r.specPath, FILES.testPhp, 'specPath must pin to the default, not the injected path');
    assert.equal(r.spec, authored, 'must read the default file, never the injected path');
  });
});

test('SHOP_DIR env wins over any filesystem probe', () => {
  withSandbox(() => {
    process.env.SHOP_DIR = '/custom/shop/location';
    const r = prepareDirectSpec({ plan: {} });
    assert.equal(r.shop, '/custom/shop/location');
  });
});

test('shop resolves to "." when vendor/bin/phpunit is present in cwd', () => {
  withSandbox((dir) => {
    fs.mkdirSync(path.join(dir, 'vendor', 'bin'), { recursive: true });
    fs.writeFileSync(path.join(dir, 'vendor', 'bin', 'phpunit'), '#!/bin/sh\n');
    const r = prepareDirectSpec({ plan: {} });
    assert.equal(r.shop, '.');
  });
});

test('shop resolves to "shop" when no local phpunit and no SHOP_DIR', () => {
  withSandbox(() => {
    const r = prepareDirectSpec({ plan: {} });
    assert.equal(r.shop, 'shop');
  });
});

test('spec contains the authored source when the default file exists', () => {
  withSandbox((dir) => {
    const body = '<?php\nnamespace Shopware\\Tests\\Integration\\Repro;\n// authored\n';
    fs.writeFileSync(path.join(dir, FILES.testPhp), body);
    const r = prepareDirectSpec({ plan: {} });
    assert.equal(r.spec, body);
    assert.equal(r.specPath, FILES.testPhp);
  });
});

test('spec is empty string when the default file is missing (evidence still recorded)', () => {
  withSandbox(() => {
    // Even with an agent script_path pointing at an existing file, nothing is read: the default is absent.
    const r = prepareDirectSpec({ plan: { script_path: 'does/not/matter.php' } });
    assert.equal(r.spec, '');
    assert.equal(r.specPath, FILES.testPhp);
  });
});

test('returns exactly the three documented keys', () => {
  withSandbox(() => {
    const r = prepareDirectSpec({ plan: {} });
    assert.deepEqual(Object.keys(r).sort(), ['shop', 'spec', 'specPath']);
  });
});
