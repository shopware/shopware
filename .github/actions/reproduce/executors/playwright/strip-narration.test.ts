import { test } from 'node:test';
import assert from 'node:assert/strict';
import { stripNarration, hasLeftoverNarration } from './strip-narration.ts';

test('removes the video-helpers import and standalone narration statements', () => {
  const spec = [
    "import { narrate, mark } from './video-helpers.js';",
    "import { test, expect } from '@playwright/test';",
    '',
    "await narrate('open the page');",
    "await page.goto('/');",
    "await mark('click');",
    "await page.getByRole('button').click();",
    "await expect(page.getByText('ok')).toBeVisible();",
  ].join('\n');
  const out = stripNarration(spec);
  assert.doesNotMatch(out, /video-helpers/);
  assert.doesNotMatch(out, /await\s+(narrate|mark)\(/);
  assert.match(out, /page\.goto\('\/'\)/);
  assert.match(out, /getByRole\('button'\)\.click\(\)/);
  assert.match(out, /await expect\(/);
  assert.equal(hasLeftoverNarration(out), false);
});

test('does not swallow adjacent code when the argument spans delimiters', () => {
  const spec = "await narrate(`a ) not the end`);\nawait page.goto('/next');";
  const out = stripNarration(spec);
  assert.doesNotMatch(out, /narrate/);
  assert.match(out, /page\.goto\('\/next'\)/);
});

test('refuses to strip a narration call with trailing code on the line (flagged as leftover)', () => {
  const spec = "await narrate('x'); doSomethingElse();\n";
  const out = stripNarration(spec);
  assert.match(out, /narrate/); // left in place rather than over-stripping
  assert.equal(hasLeftoverNarration(out), true);
});

test('hasLeftoverNarration detects a surviving import', () => {
  assert.equal(hasLeftoverNarration("import {narrate} from './video-helpers.js';"), true);
  assert.equal(hasLeftoverNarration("await page.goto('/');"), false);
});
