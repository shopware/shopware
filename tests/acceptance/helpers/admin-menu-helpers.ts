import type { Page } from '@playwright/test';

export const ADMIN_MENU_EXPANDED_STORAGE_KEY = 'sw-admin-menu-expanded';

/**
 * Forces the admin navigation sidebar back into its expanded state for the given page.
 *
 * The sidebar's expanded/collapsed state is read from localStorage on app boot
 * (`isExpanded: localStorage.getItem('sw-admin-menu-expanded') !== 'false'`), which defaults to
 * *expanded* whenever the key is absent. Three CMS/layout-builder pages (`sw-cms-list`,
 * `sw-cms-create`, `sw-cms-detail`) call `collapseSidebar()` unconditionally on mount, and
 * nothing ever calls `expandSidebar()` afterwards - so once a spec visits one of those routes,
 * the collapsed flag persists in localStorage for the rest of the browser context's lifetime.
 *
 * That matters because the Docker CI job runs the `Visual` Playwright project with
 * `--workers=1`, and `AdminSession.context` (see the base suite's `PageContexts.ts`) is a single
 * `BrowserContext` shared across the whole worker (`scope: 'worker'`). localStorage is
 * per-origin and shared by every page opened in that context, so a collapsed flag left behind by
 * a CMS-visiting spec leaks into every other `@Visual` test that happens to run afterwards in
 * the same worker, making their `.sw-desktop__content` screenshots non-deterministic (300px
 * expanded vs. 80px collapsed sidebar = 220px width diff).
 *
 * Call this once each CMS-visiting spec is done (see `CreateProductListingLayout.spec.ts` and
 * `ProductListLayout.spec.ts`), so the sidebar is restored to expanded before any other `@Visual`
 * test can inherit the collapsed state.
 *
 * `page.reload()`, not `addInitScript`, is what actually applies this: the admin app has already
 * booted by the time these specs are done, and `ShopAdmin.goesTo()` navigates hash-based admin
 * URLs via `document.location = "#/..."` (see `Actor.ts`), which never re-triggers app boot - so
 * an `addInitScript` registered here would never fire again within the same test. A real reload
 * is the only thing that flips the already-booted app's live state immediately, rather than
 * leaving cleanup to whichever test opens the next fresh page.
 */
export async function expandAdminMenu(page: Page): Promise<void> {
    await page.evaluate((key: string) => {
        // eslint-disable-next-line @typescript-eslint/ban-ts-comment
        // @ts-expect-error - runs in the browser context, where `localStorage` is defined
        localStorage.setItem(key, 'true');
    }, ADMIN_MENU_EXPANDED_STORAGE_KEY);
    await page.reload();
}
