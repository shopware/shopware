import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to accept only the technically required cookies without activating Google Analytics tracking via the basic cookie consent banner in the storefront.', { tag: ['@Settings', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    await test.step('Configure sales channel analytics and verify cookie banner visibility', async () => {
        const salesChannelAnalytics = await TestDataService.createSalesChannelAnalytics();
        await TestDataService.assignSalesChannelAnalytics(DefaultSalesChannel.salesChannel.id, salesChannelAnalytics.id);

        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    });

    await test.step('Verify default cookie consent settings', async () => {
        await StorefrontHome.consentConfigureButton.click();
        await ShopCustomer.expects(StorefrontHome.consentDialogTechnicallyRequiredCheckbox).toBeChecked();
        await ShopCustomer.expects(StorefrontHome.consentDialogStatisticsCheckbox).not.toBeChecked();
        await ShopCustomer.expects(StorefrontHome.consentDialogMarketingdCheckbox).not.toBeChecked();
        await ShopCustomer.expects(StorefrontHome.consentDialog.getByRole('checkbox')).toHaveCount(4);
        await StorefrontHome.consentDialogSaveButton.click();

        // Wait for banner to disappear after saving
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });

    await test.step('Verify cookies after saving default consent settings', async () => {
        // Poll for cookie preference to be set (handles async cookie setting)
        await ShopCustomer.expects.poll(async () => {
            const cookies = await StorefrontHome.page.context().cookies();
            return cookies.find(c => c.name === 'cookie-preference')?.value;
        }).toBe('1');

        const allCookies = await StorefrontHome.page.context().cookies();
        // Verify analytics cookies are NOT set (user declined)
        ShopCustomer.expects(allCookies.find(c => c.name === 'google-analytics-enabled')).not.toBeDefined();
        ShopCustomer.expects(allCookies.find(c => c.name === 'google-ads-enabled')).not.toBeDefined();

        // Verify cookie preference is set (user made a choice)
        const cookiePreference = allCookies.find(c => c.name === 'cookie-preference');
        ShopCustomer.expects(cookiePreference?.value).toEqual('1');
    });

    await test.step('Verify cookies persist after page reload', async () => {
        await StorefrontHome.page.reload();
        const allCookies = await StorefrontHome.page.context().cookies();
        // Verify analytics cookies remain NOT set after reload
        ShopCustomer.expects(allCookies.find(c => c.name === 'google-analytics-enabled')).not.toBeDefined();
        ShopCustomer.expects(allCookies.find(c => c.name === 'google-ads-enabled')).not.toBeDefined();

        // Verify cookie preference persists
        const cookiePreference = allCookies.find(c => c.name === 'cookie-preference');
        ShopCustomer.expects(cookiePreference?.value).toEqual('1');

        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });
});
