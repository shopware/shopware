import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { preparePlaywrightSpec } from './spec-preparer.ts';
import { FILES } from '../../bundle.ts';

// The spec is always read as the cwd-relative default (FILES.specTs); plan.script_path is ignored.
// Each test runs inside a throwaway cwd and restores it afterwards.
function withSandbox<T>(fn: (dir: string) => T): T {
  const cwd = process.cwd();
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'spec-preparer-'));
  process.chdir(dir);
  try {
    return fn(dir);
  } finally {
    process.chdir(cwd);
    fs.rmSync(dir, { recursive: true, force: true });
  }
}

/** Writes the canonical spec file into the (already-current) sandbox cwd. */
function writeDefaultSpec(dir: string, contents: string) {
  fs.writeFileSync(path.join(dir, FILES.specTs), contents);
}

test('returns a blocked reason and ts evidence when the default spec file is missing', () => {
  withSandbox(() => {
    const result = preparePlaywrightSpec({ plan: {} });

    assert.equal(result.blockedReason, `generated spec '${FILES.specTs}' not found`);
    assert.deepEqual(result.evidence, { script_lang: 'ts' });
    // A blocked result carries no authored/spec/viewport payload.
    assert.equal(result.authored, undefined);
    assert.equal(result.spec, undefined);
  });
});

test('loads the authored spec and derives a narration-stripped verdict spec', () => {
  withSandbox((dir) => {
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
    writeDefaultSpec(dir, authored);

    const result = preparePlaywrightSpec({ plan: {} });

    // `authored` is the raw, byte-identical source (narration preserved for the video run).
    assert.equal(result.authored, authored);
    assert.match(result.authored!, /video-helpers/);
    assert.match(result.authored!, /await narrate\('open the page'\)/);

    // `spec` is the machine-verdict version with narration and the helper import removed.
    assert.doesNotMatch(result.spec!, /video-helpers/);
    assert.doesNotMatch(result.spec!, /await\s+(?:narrate|mark)\(/);
    // The real Playwright actions and the deciding assertion survive untouched.
    assert.match(result.spec!, /page\.goto\('\/account'\)/);
    assert.match(result.spec!, /getByRole\('button'\)\.click\(\)/);
    assert.match(result.spec!, /await expect\(page\.getByText\('done'\)\)\.toBeVisible\(\)/);
  });
});

test('an agent-supplied script_path is ignored (pinned to the default, no arbitrary read)', () => {
  withSandbox((dir) => {
    const authored = "await page.goto('/');\nawait expect(page).toHaveTitle(/shop/);\n";
    writeDefaultSpec(dir, authored);
    // A file the agent tries to redirect the read to; must never be loaded.
    const injected = path.join(dir, 'secret.spec.ts');
    fs.writeFileSync(injected, "await page.goto('/');\n// SECRET must never be read\n");

    const result = preparePlaywrightSpec({ plan: { script_path: injected } });

    assert.equal(result.blockedReason, undefined);
    assert.equal(result.authored, authored, 'must read the default spec, never the injected path');
  });
});

test('serializes a valid viewport into the Playwright config env shape', () => {
  withSandbox((dir) => {
    writeDefaultSpec(dir, "await page.goto('/');\n");
    const result = preparePlaywrightSpec({ plan: { viewport: { width: 1280, height: 720 } } });

    assert.equal(result.viewport, JSON.stringify({ width: 1280, height: 720 }));
  });
});

test('rounds fractional viewport dimensions before serializing', () => {
  withSandbox((dir) => {
    writeDefaultSpec(dir, "await page.goto('/');\n");
    const result = preparePlaywrightSpec({ plan: { viewport: { width: 1279.4, height: 720.6 } } });

    assert.equal(result.viewport, JSON.stringify({ width: 1279, height: 721 }));
  });
});

test('returns a null viewport when the plan omits one', () => {
  withSandbox((dir) => {
    writeDefaultSpec(dir, "await page.goto('/');\n");
    const result = preparePlaywrightSpec({ plan: {} });

    assert.equal(result.viewport, null);
  });
});

test('rejects non-positive and non-finite viewport dimensions', () => {
  withSandbox((dir) => {
    writeDefaultSpec(dir, "await page.goto('/');\n");
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
      const result = preparePlaywrightSpec({ plan: { viewport } });
      assert.equal(result.viewport, null, `expected null for viewport ${JSON.stringify(viewport)}`);
    }
  });
});
