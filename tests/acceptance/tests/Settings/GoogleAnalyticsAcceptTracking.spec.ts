import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to accept Google Analytics tracking via the basic cookie consent banner in the storefront.', { tag: '@Settings' }, async ({
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
    });

    await test.step('Verify tracking cookies are set correctly after consent', async () => {
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.find(c => c.name == 'google-analytics-enabled').value).toEqual('1');
        ShopCustomer.expects(allCookies.find(c => c.name == 'google-ads-enabled').value).toEqual('1');
        ShopCustomer.expects(allCookies.length).toEqual(5);
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });

    await test.step('Verify tracking cookies persist after page reload', async () => {
        await StorefrontHome.page.reload();
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.find(c => c.name == 'google-analytics-enabled').value).toEqual('1');
        ShopCustomer.expects(allCookies.find(c => c.name == 'google-ads-enabled').value).toEqual('1');
        ShopCustomer.expects(allCookies.length).toEqual(5);
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });
});
