import { test } from '@fixtures/AcceptanceTest';

test('As a shop customer, I want to continue shopping without accepting the cookies in the storefront.', { tag: '@Settings' }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    InstanceMeta,
}) => {
    test.skip(InstanceMeta.isSaaS, 'Cache invalidation does not happen immediately on SaaS');

    await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
    const product = await TestDataService.createBasicProduct();
    const category = await TestDataService.createCategory();
    await TestDataService.assignProductCategory(product.id, category.id);

    await test.step('Navigate to homepage and verify cookie banner', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible();
    });

    await test.step('Dismiss cookie banner using the configure option', async () => {
        await StorefrontHome.consentConfigureButton.click();
        await StorefrontHome.offcanvasBackdrop.click();
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();
    });

    await test.step('Verify cookies after dismissing the cookie banner', async () => {
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(2);
    });

    await test.step('Navigate to the product page and verify the cookie banner', async () => {
        const productListItemLocators = await StorefrontHome.getListingItemByProductId(product.id);
        await productListItemLocators.productImage.click();
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible();
    });
});
