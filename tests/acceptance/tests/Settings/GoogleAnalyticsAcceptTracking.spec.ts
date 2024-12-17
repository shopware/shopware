import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to accept the Google Analytics tracking within basic cookie consent banner in storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    await test.step('Set up sales channel analytics and navigate to homepage', async () => {
        const salesChannelAnalytics = await TestDataService.createSalesChannelAnalytics();
        await TestDataService.assignSalesChannelAnalytics(DefaultSalesChannel.salesChannel.id, salesChannelAnalytics.id);

        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    });

    await test.step('Configure cookie consent settings', async () => {
        await StorefrontHome.consentConfigureButton.click();
        await StorefrontHome.consentDialogStatisticsCheckbox.click();
        await StorefrontHome.consentDialogMarketingdCheckbox.click();
        await StorefrontHome.consentDialogSaveButton.click();
    });

    await test.step('Verify cookies are set correctly after consent', async () => {
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.find(c => c.name == 'google-analytics-enabled').value).toEqual('1');
        ShopCustomer.expects(allCookies.find(c => c.name == 'google-ads-enabled').value).toEqual('1');
        ShopCustomer.expects(allCookies.length).toEqual(5);
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });

    await test.step('Reload page and verify cookies persist', async () => {
        await StorefrontHome.page.reload();
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.find(c => c.name == 'google-analytics-enabled').value).toEqual('1');
        ShopCustomer.expects(allCookies.find(c => c.name == 'google-ads-enabled').value).toEqual('1');
        ShopCustomer.expects(allCookies.length).toEqual(5);
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });
});
