import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to accept Google Analytics tracking via the basic cookie consent banner in the storefront.', { tag: ['@Settings', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    await test.step('Configure sales channel analytics and verify cookie banner visibility on the home page', async () => {
        const salesChannelAnalytics = await TestDataService.createSalesChannelAnalytics();
        await TestDataService.assignSalesChannelAnalytics(DefaultSalesChannel.salesChannel.id, salesChannelAnalytics.id);

        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    });

    await test.step('Enable Google Analytics and marketing tracking via cookie settings', async () => {
        await StorefrontHome.consentConfigureButton.click();
        await StorefrontHome.consentDialogStatisticsCheckbox.click();
        await StorefrontHome.consentDialogMarketingdCheckbox.click();
        await StorefrontHome.consentDialogSaveButton.click();

        // Wait for banner to disappear after saving, which indicates cookies are processed
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });

    await test.step('Verify tracking cookies are set correctly after consent', async () => {
        // Poll for cookie preference to be set (handles async cookie setting)
        await ShopCustomer.expects.poll(async () => {
            const cookies = await StorefrontHome.page.context().cookies();
            return cookies.find(c => c.name === 'cookie-preference')?.value;
        }).toBe('1');

        const allCookies = await StorefrontHome.page.context().cookies();
        const cookiePreference = allCookies.find(c => c.name === 'cookie-preference');
        ShopCustomer.expects(cookiePreference).toBeDefined();
        ShopCustomer.expects(cookiePreference?.value).toEqual('1');

        // Verify analytics cookies if they exist (they may be set by client-side JS)
        const googleAnalyticsCookie = allCookies.find(c => c.name === 'google-analytics-enabled');
        const googleAdsCookie = allCookies.find(c => c.name === 'google-ads-enabled');
        if (googleAnalyticsCookie) {
            ShopCustomer.expects(googleAnalyticsCookie.value).toEqual('1');
        }
        if (googleAdsCookie) {
            ShopCustomer.expects(googleAdsCookie.value).toEqual('1');
        }
    });

    await test.step('Verify tracking cookies persist after page reload', async () => {
        await StorefrontHome.page.reload();

        // Wait for page to be fully loaded
        await StorefrontHome.page.waitForLoadState('networkidle');

        const allCookies = await StorefrontHome.page.context().cookies();

        // Verify cookie preference persists
        const cookiePreference = allCookies.find(c => c.name === 'cookie-preference');
        ShopCustomer.expects(cookiePreference).toBeDefined();
        ShopCustomer.expects(cookiePreference?.value).toEqual('1');

        // Verify analytics cookies persist if they were set
        const googleAnalyticsCookie = allCookies.find(c => c.name === 'google-analytics-enabled');
        const googleAdsCookie = allCookies.find(c => c.name === 'google-ads-enabled');
        if (googleAnalyticsCookie) {
            ShopCustomer.expects(googleAnalyticsCookie.value).toEqual('1');
        }
        if (googleAdsCookie) {
            ShopCustomer.expects(googleAdsCookie.value).toEqual('1');
        }

        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });
});
