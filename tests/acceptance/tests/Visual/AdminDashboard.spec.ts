import { test, expect } from '@fixtures/AcceptanceTest';

test('Visual: Administration dashboard', { tag: '@Visual' }, async ({ ShopAdmin, AdminDashboard }) => {
    await test.step('Creates a screenshot of the Administration dashboard.', async () => {
        await ShopAdmin.goesTo(AdminDashboard.url());
        await ShopAdmin.expects(AdminDashboard.welcomeHeadline).toBeVisible();
        await AdminDashboard.welcomeHeadline.hover();

        await AdminDashboard.page.setViewportSize({ width: 1440, height: 2240 });
        await AdminDashboard.page.addStyleTag({
            content: `
                .sw-admin-menu__user-name,
                .sw-avatar,
                .sw-dashboard-index__welcome-text 
                {display: none !important;}`,
        });
        await expect(AdminDashboard.page.locator('.sw-desktop__content')).toHaveScreenshot();
    });
});
