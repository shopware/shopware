import { test, expect } from '@fixtures/AcceptanceTest';

test('Administration dashboard', { tag: '@Visual' }, async ({ 
    ShopAdmin, 
    AdminDashboard,
    ReplaceElementsForScreenshot,
    HideElementsForScreenshot,
 }) => {
    await test.step('Creates a screenshot of the Administration dashboard.', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url()),
        await AdminDashboard.page.waitForLoadState('load');
        await AdminDashboard.page.setViewportSize({ width: 1440, height: 2300 });

        await ReplaceElementsForScreenshot(AdminDashboard.page, [
            '.sw-dashboard-index__welcome-text',
            '.mt-card__subtitle',
        ]);

        await HideElementsForScreenshot(AdminDashboard.page, [
            '.apexcharts-xaxis-texts-g',
        ]);

        await expect(AdminDashboard.page.locator('.sw-desktop__content')).toHaveScreenshot('Dashboard.png');
    }); 
});
