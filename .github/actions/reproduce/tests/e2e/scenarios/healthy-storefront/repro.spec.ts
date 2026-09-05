import { test, expect } from '@playwright/test';

// E2E harness scenario: a minimal healthy Playwright leg — the storefront home renders. Passes on any
// live shop, so both legs are not_reproduced → not_reproducible. Exercises the playwright executor +
// PW sandbox arming + screenshot evidence. baseURL is injected by the harness; navigate relative.
test('storefront home renders', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('body')).toBeVisible();
});
