import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { prepareDirectSpec } from './spec-preparer.ts';
import { FILES } from '../../bundle.ts';

// The helper reads cwd-relative paths (the fallback spec name and the `vendor/bin/phpunit`
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

test('specPath uses the plan script_path when provided', () => {
  withSandbox(() => {
    const r = prepareDirectSpec({ plan: { script_path: 'tests/integration/Repro/ReproTest.php' } });
    assert.equal(r.specPath, 'tests/integration/Repro/ReproTest.php');
  });
});

test('specPath falls back to the canonical bundle test file name', () => {
  withSandbox(() => {
    const r = prepareDirectSpec({ plan: {} });
    assert.equal(r.specPath, FILES.testPhp);
    assert.equal(r.specPath, 'ReproTest.php');
  });
});

test('an empty-string script_path falls back to the default (not a blank path)', () => {
  withSandbox(() => {
    const r = prepareDirectSpec({ plan: { script_path: '' } });
    assert.equal(r.specPath, FILES.testPhp);
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

test('spec contains the authored source when the file exists at specPath', () => {
  withSandbox((dir) => {
    const rel = 'ReproTest.php';
    const body = '<?php\nnamespace Shopware\\Tests\\Integration\\Repro;\n// authored\n';
    fs.writeFileSync(path.join(dir, rel), body);
    const r = prepareDirectSpec({ plan: { script_path: rel } });
    assert.equal(r.spec, body);
    assert.equal(r.specPath, rel);
  });
});

test('spec reads through an absolute script_path regardless of cwd', () => {
  withSandbox((dir) => {
    const abs = path.join(dir, 'nested', 'AbsRepro.php');
    fs.mkdirSync(path.dirname(abs), { recursive: true });
    const body = '<?php\n// absolute placement\n';
    fs.writeFileSync(abs, body);
    const r = prepareDirectSpec({ plan: { script_path: abs } });
    assert.equal(r.specPath, abs);
    assert.equal(r.spec, body);
  });
});

test('spec is empty string when the resolved file is missing (evidence still recorded)', () => {
  withSandbox(() => {
    const r = prepareDirectSpec({ plan: { script_path: 'does/not/exist/ReproTest.php' } });
    assert.equal(r.spec, '');
    assert.equal(r.specPath, 'does/not/exist/ReproTest.php');
  });
});

test('returns exactly the three documented keys', () => {
  withSandbox(() => {
    const r = prepareDirectSpec({ plan: {} });
    assert.deepEqual(Object.keys(r).sort(), ['shop', 'spec', 'specPath']);
  });
});
