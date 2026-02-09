import { test } from '@fixtures/AcceptanceTest';

test(
    'As a shop customer, I want to continue shopping without accepting the cookies in the storefront.', { tag: ['@Settings', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontHome,
    TestDataService,
    CheckVisibilityInHome,
}) => {

    const COOKIE_BANNER_VISIBILITY_TIMEOUT = 15_000;

    await TestDataService.setSystemConfig({'core.basicInformation.acceptAllCookies': true});
    const product = await TestDataService.createBasicProduct();
    const category = await TestDataService.createCategory();
    await TestDataService.assignProductCategory(product.id, category.id);

    await test.step('Navigate to homepage and verify cookie banner', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await CheckVisibilityInHome(product.name)();
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible({ timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT });
        await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).toBeVisible({ timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT });
    });

    await test.step('Dismiss cookie banner using the configure option without choosing a preference, cookie banner should be displayed again', async () => {
        await ShopCustomer.presses(StorefrontHome.consentConfigureButton);
        await ShopCustomer.presses(StorefrontHome.consentDialogCloseButton);
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible({ timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT });
    });

    await test.step('Verify cookies after dismissing the cookie banner', async () => {
        const allCookies = await StorefrontHome.page.context().cookies();
        ShopCustomer.expects(allCookies.length).toEqual(2);
    });

    await test.step('Navigate to the product page and verify the cookie banner', async () => {
        const productListItemLocators = await StorefrontHome.getListingItemByProductName(product.name);
        await ShopCustomer.presses(productListItemLocators.productName);
        await StorefrontHome.page.reload();
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).toBeVisible({ timeout: COOKIE_BANNER_VISIBILITY_TIMEOUT });
    });
});