import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { preparePlaywrightSpec } from './spec-preparer.ts';
import { FILES } from '../../bundle.ts';

/** Writes a spec into a fresh temp dir and returns its absolute path. */
function writeSpec(contents: string) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'spec-preparer-'));
  const file = path.join(dir, 'repro.spec.ts');
  fs.writeFileSync(file, contents);
  return { dir, file };
}

test('returns a blocked reason and ts evidence when the spec file is missing', () => {
  const missing = path.join(os.tmpdir(), 'definitely-does-not-exist-1234', 'repro.spec.ts');
  const result = preparePlaywrightSpec({ plan: { script_path: missing } });

  assert.equal(result.blockedReason, `generated spec '${missing}' not found`);
  assert.deepEqual(result.evidence, { script_lang: 'ts' });
  // A blocked result carries no authored/spec/viewport payload.
  assert.equal(result.authored, undefined);
  assert.equal(result.spec, undefined);
});

test('loads the authored spec and derives a narration-stripped verdict spec', () => {
  const authored = [
    "import { narrate, mark } from './video-helpers.js';",
    "import { test, expect } from '@playwright/test';",
    '',
    "await narrate('open the page');",
    "await page.goto('/account');",
    "await mark('submit');",
    "await page.getByRole('button').click();",
    "await expect(page.getByText('done')).toBeVisible();",
  ].join('\n');
  const { file } = writeSpec(authored);

  const result = preparePlaywrightSpec({ plan: { script_path: file } });

  // `authored` is the raw, byte-identical source (narration preserved for the video run).
  assert.equal(result.authored, authored);
  assert.match(result.authored, /video-helpers/);
  assert.match(result.authored, /await narrate\('open the page'\)/);

  // `spec` is the machine-verdict version with narration and the helper import removed.
  assert.doesNotMatch(result.spec!, /video-helpers/);
  assert.doesNotMatch(result.spec!, /await\s+(?:narrate|mark)\(/);
  // The real Playwright actions and the deciding assertion survive untouched.
  assert.match(result.spec!, /page\.goto\('\/account'\)/);
  assert.match(result.spec!, /getByRole\('button'\)\.click\(\)/);
  assert.match(result.spec!, /await expect\(page\.getByText\('done'\)\)\.toBeVisible\(\)/);
});

test('falls back to FILES.specTs relative to cwd when no script_path is set', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'spec-preparer-cwd-'));
  const authored = "await page.goto('/');\nawait expect(page).toHaveTitle(/shop/);\n";
  fs.writeFileSync(path.join(dir, FILES.specTs), authored);

  const prevCwd = process.cwd();
  try {
    process.chdir(dir);
    const result = preparePlaywrightSpec({ plan: {} });
    assert.equal(result.blockedReason, undefined);
    assert.equal(result.authored, authored);
    assert.match(result.spec!, /page\.goto\('\/'\)/);
  } finally {
    process.chdir(prevCwd);
  }
});

test('serializes a valid viewport into the Playwright config env shape', () => {
  const { file } = writeSpec("await page.goto('/');\n");
  const result = preparePlaywrightSpec({ plan: { script_path: file, viewport: { width: 1280, height: 720 } } });

  assert.equal(result.viewport, JSON.stringify({ width: 1280, height: 720 }));
});

test('rounds fractional viewport dimensions before serializing', () => {
  const { file } = writeSpec("await page.goto('/');\n");
  const result = preparePlaywrightSpec({ plan: { script_path: file, viewport: { width: 1279.4, height: 720.6 } } });

  assert.equal(result.viewport, JSON.stringify({ width: 1279, height: 721 }));
});

test('returns a null viewport when the plan omits one', () => {
  const { file } = writeSpec("await page.goto('/');\n");
  const result = preparePlaywrightSpec({ plan: { script_path: file } });

  assert.equal(result.viewport, null);
});

test('rejects non-positive and non-finite viewport dimensions', () => {
  const { file } = writeSpec("await page.goto('/');\n");
  const cases = [
    { width: 0, height: 720 },
    { width: 1280, height: 0 },
    { width: -1280, height: 720 },
    { width: 1280, height: -720 },
    { width: Number.NaN, height: 720 },
    { width: 1280, height: Number.POSITIVE_INFINITY },
    { width: 'wide', height: 720 },
    {},
  ];

  for (const viewport of cases) {
    const result = preparePlaywrightSpec({ plan: { script_path: file, viewport } });
    assert.equal(result.viewport, null, `expected null for viewport ${JSON.stringify(viewport)}`);
  }
});
