import { test, replaceElements, hideElements, setViewport, assertScreenshot } from '@fixtures/AcceptanceTest';

test('Administration dashboard', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminDashboard,
 }) => {
    await test.step('Creates a screenshot of the Administration dashboard.', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url());
        await setViewport(AdminDashboard.page, {
            contentHeight: 2646,
        });
        await replaceElements(AdminDashboard.page, [
            AdminDashboard.welcomeHeadline,
            AdminDashboard.welcomeMessage,
            AdminDashboard.statisticsDateRange,
        ]);
        await hideElements(AdminDashboard.page, [
            AdminDashboard.statisticsChart,
        ]);
        await assertScreenshot(AdminDashboard.page, 'Dashboard.png');
    });
});
