import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';
test('Visual: Administration dashboard', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminDashboard,
}) => {

    await test.step('Creates a screenshot of the Administration dashboard.', async () => {

        await ShopAdmin.goesTo(AdminDashboard.url());
        await ShopAdmin.expects(AdminDashboard.welcomeHeadline).toBeVisible();
        await AdminDashboard.welcomeHeadline.hover();

        await AdminDashboard.page.setViewportSize({ width: 1440, height: 2240});

        await expect(AdminDashboard.page.locator('.sw-desktop__content')).toHaveScreenshot({
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
        });
    });
});
