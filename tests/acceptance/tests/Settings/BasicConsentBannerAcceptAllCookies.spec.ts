import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to use an "Accept All Cookies" button in the basic cookie consent banner in the storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    await test.step('Set system configuration to enable accept all cookies button', async () => {
        await TestDataService.createSystemConfigEntry('core.basicInformation.acceptAllCookies', true, DefaultSalesChannel.salesChannel.id);
    });

    await test.step('Navigate to the homepage and verify cookie consent banner', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible();
    });

    await test.step('Accept all cookies', async () => {
        await StorefrontHome.consentAcceptAllCookiesButton.click();
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(2);  // Verify initial cookies
    });

    await test.step('Reload page and verify cookies', async () => {
        await StorefrontHome.page.reload();
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(4);  // Verify cookies after reload
    });

    await test.step('Verify cookie consent banner is no longer visible', async () => {
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });
});
