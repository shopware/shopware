import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to use an "Accept All Cookies" button in the basic cookie consent banner in the storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    InstanceMeta,
}) => {
    test.skip(InstanceMeta.isSaaS, 'Cache invalidation does not happen immediately on SaaS');

    await test.step('Enable "Accept All Cookies" button in system configuration', async () => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
    });

    await test.step('Navigate to the homepage and verify cookie consent banner', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible();
    });

    await test.step('Click "Accept All Cookies" and verify initial cookies', async () => {
        await StorefrontHome.consentAcceptAllCookiesButton.click();
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(2);
    });

    await test.step('Reload page and verify additional cookies are set', async () => {
        await StorefrontHome.page.reload();
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(4);
    });

    await test.step('Verify cookie consent banner is no longer visible', async () => {
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });
});
