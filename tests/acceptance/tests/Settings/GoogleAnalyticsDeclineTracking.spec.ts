import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to accept the technical required cookies without activating Google Analytics tracking within basic cookie consent banner in storefront.', { tag: '@Settings' }, async ({
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
    await ShopCustomer.expects(StorefrontHome.consentDialogTechnicallyRequiredCheckbox).toBeChecked();
    await ShopCustomer.expects(StorefrontHome.consentDialogStatisticsCheckbox).not.toBeChecked();
    await ShopCustomer.expects(StorefrontHome.consentDialogMarketingdCheckbox).not.toBeChecked();
    await ShopCustomer.expects(StorefrontHome.consentDialog.getByRole('checkbox')).toHaveCount(4);

    await StorefrontHome.consentDialogSaveButton.click();

    let allCookies = await StorefrontHome.page.context().cookies();
    ShopCustomer.expects(allCookies.find(c => c.name == 'google-analytics-enabled')).not.toBeDefined();
    ShopCustomer.expects(allCookies.find(c => c.name == 'google-ads-enabled')).not.toBeDefined();
    ShopCustomer.expects(allCookies.length).toEqual(3);

    await StorefrontHome.page.reload();
    allCookies = await StorefrontHome.page.context().cookies();

    ShopCustomer.expects(allCookies.find(c => c.name == 'google-analytics-enabled')).not.toBeDefined();
    ShopCustomer.expects(allCookies.find(c => c.name == 'google-ads-enabled')).not.toBeDefined();
    ShopCustomer.expects(allCookies.length).toEqual(3);
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
});
