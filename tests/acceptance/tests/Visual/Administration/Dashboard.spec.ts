import { test, expect } from '@fixtures/AcceptanceTest';

test('Administration dashboard', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminDashboard,
    ReplaceElementsForScreenshot,
    HideElementsForScreenshot,
 }) => {
    await test.step('Creates a screenshot of the Administration dashboard.', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url());
        const viewportSize = await AdminDashboard.getViewportDimensions({
            requestURL: 'api/search/sales-channel',
        });
        await AdminDashboard.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminDashboard.page, [
            '.sw-dashboard-index__welcome-text',
            '.mt-card__subtitle',
        ]);
        await HideElementsForScreenshot(AdminDashboard.page, [
            '.apexcharts-xaxis-texts-g',
        ]);
        await ShopAdmin.expects(AdminDashboard.page.locator('.sw-desktop__content')).toHaveScreenshot('Dashboard.png');
    });
});
