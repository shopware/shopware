import { test, expect } from '@fixtures/AcceptanceTest';

test('coverage debug', { tag: '@coverage' }, async ({ page }) => {
    await page.goto('/');
    expect(await page.evaluate(() => typeof (globalThis as any).__coverage__)).toBe('object');
});
