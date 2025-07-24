import { test } from '@fixtures/AcceptanceTest';
import { VisualTestHelpers } from '@shopware-ag/acceptance-test-suite';
test('Visual: Administration menu', { tag: '@Visual' }, async ({
ShopAdmin,
AdminDashboard,
}) => {

    await test.step('Creates a screenshot of the fully expanded admin menu.', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url());
        await AdminDashboard.adminMenuCatalog.click();
        await AdminDashboard.adminMenuOrder.click();
        await AdminDashboard.adminMenuCustomer.click();
        await AdminDashboard.adminMenuContent.click();
        await AdminDashboard.adminMenuMarketing.click();
        await AdminDashboard.adminMenuExtension.click();
        await AdminDashboard.adminMenuUserChevron.click();

        await VisualTestHelpers.setViewport(AdminDashboard.page, {
            //waitForSelector: AdminDashboard.statisticsChart,
            waitForSelector: AdminDashboard.page.locator('.sw-dashboard-statistics__statistics-sum'),
            additionalHeight: -850,
        });
        await VisualTestHelpers.hideElements(AdminDashboard.page, [
            AdminDashboard.adminMenuUserIcon,
        ]);
        await VisualTestHelpers.replaceElements(AdminDashboard.page, [
            AdminDashboard.adminMenuUserName,
        ]);
        await ShopAdmin.expects(AdminDashboard.adminMenuView).toHaveScreenshot('AdminMenu-Expanded.png');
    });
});
