import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to use a accept all cookies button in the basic cookie consent banner in storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    await TestDataService.createSystemConfigEntry('core.basicInformation.acceptAllCookies', true, DefaultSalesChannel.salesChannel.id);

    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible();
    await StorefrontHome.consentAcceptAllCookiesButton.click();

    let allCookies = await StorefrontHome.page.context().cookies();
    ShopCustomer.expects(allCookies.length).toEqual(2);

    await StorefrontHome.page.reload();

    allCookies = await StorefrontHome.page.context().cookies();
    ShopCustomer.expects(allCookies.length).toEqual(4);
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
});
