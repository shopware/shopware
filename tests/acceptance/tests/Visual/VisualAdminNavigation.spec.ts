import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';

test('Visual: Admin menu in the administration.', { tag: '@Visual' }, async ({
ShopAdmin,
AdminDashboard,
}) => {

    await test.step('Creates a screenshot of the fully expanded admin menu.', async () => {

        await ShopAdmin.goesTo(AdminDashboard.url());
        await AdminDashboard.page.locator('.sw-catalogue').click();
        await AdminDashboard.page.locator('.sw-order').click();
        await AdminDashboard.page.locator('.sw-customer').click();
        await AdminDashboard.page.locator('.sw-content').click();
        await AdminDashboard.page.locator('.sw-marketing').click();
        await AdminDashboard.page.locator('.sw-extension').click();
        await AdminDashboard.page.locator('.sw-admin-menu__user-actions-indicator').click();

        await AdminDashboard.page.setViewportSize({ width: 1280, height: 2048});

        await expect(AdminDashboard.page.locator('.sw-admin-menu')).toHaveScreenshot({
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
            mask: [
                AdminDashboard.page.locator('.sw-admin-menu__user-name'),
                AdminDashboard.page.locator('.sw-avatar'),
            ],
        });
    });
});
