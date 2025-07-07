import { test, expect } from '@fixtures/AcceptanceTest';

test('Visual: Administration dashboard', { tag: '@Visual' }, async ({ 
    ShopAdmin, 
    AdminDashboard,
    ReplaceElementsForScreenshot,
    HideElementsForScreenshot,
 }) => {
    await test.step('Creates a screenshot of the Administration dashboard.', async () => {

        const response = await AdminDashboard.page.waitForResponse(response => response.url().includes('/api/search/order') && response.status() === 200);
        await ShopAdmin.goesTo(AdminDashboard.url());
        await ShopAdmin.expects(response).toBeTruthy()  ;
        await AdminDashboard.page.setViewportSize({ width: 1440, height: 2300 });
        
        await ReplaceElementsForScreenshot(AdminDashboard.page, [
            '.sw-dashboard-index__welcome-text',
            '.mt-card__subtitle',
        ]);

        await HideElementsForScreenshot(AdminDashboard.page, [
            '.apexcharts-xaxis-texts-g',
        ]);

        await expect(AdminDashboard.page.locator('.sw-desktop__content')).toHaveScreenshot();
    });
});
