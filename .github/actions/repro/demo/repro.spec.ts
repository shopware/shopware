import { test, expect } from '@playwright/test';

// DEMO wiring smoke for the `playwright` executor — used ONLY by the workflow's dry-run
// demo (dry_run=true, demo_layer=playwright). Not a real reproduction.
test('admin entry point renders', async ({ page }) => {
  // PRECONDITION + SYMPTOM (healthy): the admin entry point loads and the page renders.
  // Passing => not_reproduced, which proves the whole playwright path runs: provision ->
  // copy spec under the config's testDir -> playwright run -> parse stats -> report.
  await page.goto('/admin');
  await expect(page.locator('body')).toBeVisible();
});
