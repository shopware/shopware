import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to use an "Accept All Cookies" button in the basic cookie consent banner in the storefront.', { tag: ['@Settings', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
}) => {

    await test.step('Enable "Accept All Cookies" button in system configuration', async () => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
    });

    await test.step('Navigate to the homepage and verify cookie consent banner', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible({ timeout: 15_000 });
        await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible({ timeout: 15_000 });
    });

    await test.step('Click "Accept All Cookies" and verify cookies are accepted', async () => {
        await StorefrontHome.consentAcceptAllCookiesButton.click();

        // Wait for banner to disappear, which indicates cookies have been set
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible({ timeout: 15_000 });

        const allCookies = await StorefrontHome.page.context().cookies();
        // Verify essential cookies are set
        const cookiePreference = allCookies.find(c => c.name === 'cookie-preference');
        ShopCustomer.expects(cookiePreference).toBeDefined();
        ShopCustomer.expects(cookiePreference?.value).toEqual('1');
    });

    await test.step('Reload page and verify cookie persistence', async () => {
        await StorefrontHome.page.reload();
        const allCookies = await StorefrontHome.page.context().cookies();
        // Verify cookie preference persists
        const cookiePreference = allCookies.find(c => c.name === 'cookie-preference');
        ShopCustomer.expects(cookiePreference).toBeDefined();
        ShopCustomer.expects(cookiePreference?.value).toEqual('1');
    });

    await test.step('Verify cookie consent banner is no longer visible', async () => {
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible({ timeout: 15_000 });
    });
});
