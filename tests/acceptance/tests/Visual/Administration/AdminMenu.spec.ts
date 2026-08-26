import { test, expect, setViewport, hideElements, replaceElements, assertScreenshot } from '@fixtures/AcceptanceTest';

interface NavigationEntry {
    parent?: string;
}

interface AdminMenuStore {
    expandSidebar(): void;
    clearExpandedMenuEntries(): void;
    adminModuleNavigation: NavigationEntry[];
    // eslint-disable-next-line no-unused-vars
    expandMenuEntry(entry: NavigationEntry): void;
}

interface ShopwareGlobal {
    Shopware?: {
        Store?: {
            // eslint-disable-next-line no-unused-vars
            get(storeId: string): AdminMenuStore | undefined;
        };
    };
}

// Sidebar state persists in localStorage and leaks between tests sharing a worker.
test.afterEach(async ({ AdminDashboard }) => {
    await AdminDashboard.page
        .evaluate(() => {
            const store = (globalThis as ShopwareGlobal).Shopware?.Store?.get('adminMenu');
            store?.expandSidebar();
            store?.clearExpandedMenuEntries();
        })
        .catch(() => undefined);
});

test('Visual: Administration menu', { tag: '@Visual' }, async ({ ShopAdmin, AdminDashboard }) => {
    const page = AdminDashboard.page;

    await test.step('Creates a screenshot of the expanded admin menu with an open Catalogues section.', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url());
        // Pin the height so the menu screenshots do not depend on the dashboard's content height.
        await setViewport(page, {
            contentHeight: 1080,
        });
        await page.evaluate(() => {
            (globalThis as ShopwareGlobal).Shopware?.Store?.get('adminMenu')?.expandSidebar();
        });

        // The Symfony debug toolbar of dev instances overlaps the menu footer.
        await hideElements(page, ['.sf-toolbar']);

        // Only one menu branch can be open at a time, so one section stands in for all of them.
        await AdminDashboard.adminMenuCatalog.click();
        await expect(AdminDashboard.adminMenuCatalog.locator('.sw-admin-menu__sub-navigation-list')).toBeVisible();

        // Park the pointer over the content area so no menu row is hovered.
        await page.mouse.move(1000, 400);
        await hideElements(page, ['.sw-avatar']);
        await replaceElements(page, [AdminDashboard.adminMenuUserName]);
        await assertScreenshot(page, 'AdminMenu-Expanded.png', AdminDashboard.adminMenuView);
    });

    await test.step('Creates a screenshot with every menu branch open to cover all sub-entries.', async () => {
        // Interactively only one branch can be open, so this unreachable state is set via the store.
        await page.setViewportSize({ width: 1440, height: 1600 });
        await page.evaluate(() => {
            const store = (globalThis as ShopwareGlobal).Shopware?.Store?.get('adminMenu');
            store?.adminModuleNavigation.filter((entry) => !entry.parent).forEach((entry) => store.expandMenuEntry(entry));
        });
        await expect(AdminDashboard.adminMenuExtension.locator('.sw-admin-menu__sub-navigation-list')).toBeVisible();

        await assertScreenshot(page, 'AdminMenu-All-Branches.png', AdminDashboard.adminMenuView);

        await page.evaluate(() => {
            (globalThis as ShopwareGlobal).Shopware?.Store?.get('adminMenu')?.clearExpandedMenuEntries();
        });
        await page.setViewportSize({ width: 1440, height: 1080 });
    });

    await test.step('Creates a screenshot of the opened user actions menu.', async () => {
        await AdminDashboard.adminMenuUserActions.click();
        const userActionsMenu = page.locator('.sw-admin-menu__user-actions-menu');
        await expect(userActionsMenu).toBeVisible();

        await replaceElements(page, ['.sw-admin-menu__version-footer']);
        await assertScreenshot(page, 'AdminMenu-User-Actions.png', userActionsMenu);

        await page.keyboard.press('Escape');
        await expect(userActionsMenu).toBeHidden();
    });

    await test.step('Creates a screenshot of the opened sales channel context menu.', async () => {
        await page.locator('.sw-admin-menu__headline-action').click();
        const salesChannelMenu = page.locator('.mt-action-menu', {
            has: page.locator('.sw-admin-menu__headline-context-menu-manage-sales-channels'),
        });
        await expect(salesChannelMenu).toBeVisible();

        await assertScreenshot(page, 'AdminMenu-Sales-Channel-Actions.png', salesChannelMenu);

        await page.keyboard.press('Escape');
        await expect(salesChannelMenu).toBeHidden();
    });

    await test.step('Creates a screenshot of the collapsed admin menu.', async () => {
        await page.locator('.sw-admin-menu__collapse-button').click();
        await expect(AdminDashboard.adminMenuView).toHaveClass(/is--collapsed/);

        await page.mouse.move(1000, 400);
        await assertScreenshot(page, 'AdminMenu-Collapsed.png', AdminDashboard.adminMenuView);
    });

    await test.step('Creates a screenshot of the flyout of the collapsed admin menu.', async () => {
        await AdminDashboard.adminMenuCatalog.hover();
        const flyout = page.locator('.sw-admin-menu__flyout-content');
        await expect(flyout).toBeVisible();

        await assertScreenshot(page, 'AdminMenu-Flyout.png', flyout);

        await page.mouse.move(1000, 400);
        await expect(flyout).toBeHidden();
    });

    await test.step('Creates a screenshot of the off-canvas admin menu on a small viewport.', async () => {
        await page.evaluate(() => {
            (globalThis as ShopwareGlobal).Shopware?.Store?.get('adminMenu')?.expandSidebar();
        });
        await page.setViewportSize({ width: 1024, height: 800 });

        await page.locator('.sw-search-bar__off-canvas-toggle').click();
        await expect(AdminDashboard.adminMenuView).toHaveClass(/is--off-canvas-shown/);

        await replaceElements(page, [AdminDashboard.adminMenuUserName]);
        await assertScreenshot(page, 'AdminMenu-Off-Canvas.png', AdminDashboard.adminMenuView);
    });
});
