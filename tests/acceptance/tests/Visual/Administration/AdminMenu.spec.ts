import { test, setViewport, hideElements, replaceElements, assertScreenshot } from '@fixtures/AcceptanceTest';

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

        await setViewport(AdminDashboard.page, {
            contentHeight: 1796,
        });
        await hideElements(AdminDashboard.page, [
            AdminDashboard.adminMenuUserIcon,
        ]);
        await replaceElements(AdminDashboard.page, [
            AdminDashboard.adminMenuUserName,
        ]);
        await assertScreenshot(AdminDashboard.page, 'AdminMenu-Expanded.png', AdminDashboard.adminMenuView);
    });
});
