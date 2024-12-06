import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to continue shopping without accepting the cookies in storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    DefaultSalesChannel,
}) => {

    await TestDataService.createSystemConfigEntry('core.basicInformation.acceptAllCookies', true, DefaultSalesChannel.salesChannel.id);
    const product = await TestDataService.createBasicProduct();
    const category = await TestDataService.createCategory();
    await TestDataService.assignProductCategory(product.id, category.id);
    const productListItemLocators = await StorefrontHome.getListingItemByProductId(product.id);

    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible();
    await StorefrontHome.consentConfigureButton.click();
    await StorefrontHome.offcanvasBackdrop.click();
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();

    const allCookies = await StorefrontHome.page.context().cookies();
    ShopCustomer.expects(allCookies.length).toEqual(2);

    await productListItemLocators.productImage.click();
    await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
});
