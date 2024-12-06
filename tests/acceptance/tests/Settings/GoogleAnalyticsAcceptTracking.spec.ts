import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to accept the Google Analytics tracking within basic cookie consent banner in storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    const salesChannelAnalytics = await TestDataService.createSalesChannelAnalytics();
    await TestDataService.assignSalesChannelAnalytics(DefaultSalesChannel.salesChannel.id, salesChannelAnalytics.id);

    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();

    await StorefrontHome.consentConfigureButton.click();
    await StorefrontHome.consentDialogStatisticsCheckbox.click();
    await StorefrontHome.consentDialogMarketingdCheckbox.click();
    await StorefrontHome.consentDialogSaveButton.click();

    let allCookies = await StorefrontHome.page.context().cookies();
    ShopCustomer.expects(allCookies.find(c => c.name == 'google-analytics-enabled').value).toEqual('1');
    ShopCustomer.expects(allCookies.find(c => c.name == 'google-ads-enabled').value).toEqual('1');
    ShopCustomer.expects(allCookies.length).toEqual(5);
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();

    await StorefrontHome.page.reload();
    allCookies = await StorefrontHome.page.context().cookies();
    ShopCustomer.expects(allCookies.find(c => c.name == 'google-analytics-enabled').value).toEqual('1');
    ShopCustomer.expects(allCookies.find(c => c.name == 'google-ads-enabled').value).toEqual('1');
    ShopCustomer.expects(allCookies.length).toEqual(5);
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
});
