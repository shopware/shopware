import type { Page } from '@playwright/test';

const ADMIN_MENU_EXPANDED_STORAGE_KEY = 'sw-admin-menu-expanded';

/**
 * Forces the admin navigation sidebar into its collapsed state before a visual test screenshot.
 *
 * The sidebar's expanded/collapsed state is read from localStorage on app boot
 * (`isExpanded: localStorage.getItem('sw-admin-menu-expanded') !== 'false'`), which defaults to
 * *expanded* whenever the key is absent - e.g. on a fresh browser context/storage state. Visual
 * tests that don't pin this explicitly end up with a non-deterministic sidebar width (300px
 * expanded vs. 80px collapsed), which shows up as a 220px-wide diff in `.sw-desktop__content`
 * screenshots depending on whatever the shared session happened to leave behind.
 *
 * Call this right after the initial page navigation, before the first `setViewport`/
 * `assertScreenshot` call - it reloads the page so the admin app boots with the flag already set.
 */
export async function collapseAdminMenu(page: Page): Promise<void> {
    await page.evaluate((key: string) => {
        // eslint-disable-next-line @typescript-eslint/ban-ts-comment
        // @ts-expect-error - runs in the browser context, where `localStorage` is defined
        localStorage.setItem(key, 'false');
    }, ADMIN_MENU_EXPANDED_STORAGE_KEY);
    await page.reload();
}
