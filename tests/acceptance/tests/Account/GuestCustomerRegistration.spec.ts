import { test } from '@fixtures/AcceptanceTest';

test('Guest customer must be able to register in the Storefront.', { tag: ['@Registration', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontProductDetail,
    StorefrontHome,
    StorefrontAccountLogin,
    StorefrontCheckoutRegister,
    AddProductToCart,
    TestDataService,
    Register,
}) => {
    const product = await TestDataService.createBasicProduct();

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(`${product.translated.name} | ${product.productNumber}`);
    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
    await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });

    await ShopCustomer.attemptsTo(Register({ isGuest: true }));
    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.presses(StorefrontHome.accountMenuButton);
    await ShopCustomer.presses(StorefrontHome.closeGuestSessionButton);
    await ShopCustomer.expects(StorefrontAccountLogin.successAlert).toBeVisible();

});

test('Guest commercial customer must be able to register in the Storefront.', { tag: ['@Registration', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontHome,
    StorefrontProductDetail,
    StorefrontAccountLogin,
    StorefrontCheckoutRegister,
    AddProductToCart,
    TestDataService,
    Register,
}) => {
    await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
    const product = await TestDataService.createBasicProduct();

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(`${product.translated.name} | ${product.productNumber}`);
    await ShopCustomer.attemptsTo(AddProductToCart(product));
    await ShopCustomer.presses(StorefrontProductDetail.offCanvasCartGoToCheckoutButton);
    await StorefrontCheckoutRegister.page.waitForURL('**/checkout/register', { waitUntil: 'commit' });

    await ShopCustomer.presses(StorefrontAccountLogin.accountTypeSelect);
    await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
    await ShopCustomer.attemptsTo(Register({ isCommercial: true, isGuest: true }));
    await ShopCustomer.goesTo(StorefrontHome.url());
    await ShopCustomer.presses(StorefrontHome.accountMenuButton);
    await ShopCustomer.presses(StorefrontHome.closeGuestSessionButton);
    await ShopCustomer.expects(StorefrontAccountLogin.successAlert).toBeVisible();
});