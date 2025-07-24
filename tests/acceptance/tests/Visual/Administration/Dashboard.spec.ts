import { test } from '@fixtures/AcceptanceTest';
import { VisualTestHelpers } from '@shopware-ag/acceptance-test-suite';

test('Administration dashboard', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminDashboard,
 }) => {
    await test.step('Creates a screenshot of the Administration dashboard.', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url());
        await VisualTestHelpers.setViewport(AdminDashboard.page, {
            waitForSelector: AdminDashboard.statisticsChart,
        });
        await VisualTestHelpers.replaceElements(AdminDashboard.page, [
            AdminDashboard.welcomeHeadline,
            AdminDashboard.welcomeMessage,
            AdminDashboard.statisticsDateRange,
        ]);
        await VisualTestHelpers.hideElements(AdminDashboard.page, [
            AdminDashboard.statisticsChart,
        ]);
        await ShopAdmin.expects(AdminDashboard.contentView).toHaveScreenshot('Dashboard.png');
    });
});
