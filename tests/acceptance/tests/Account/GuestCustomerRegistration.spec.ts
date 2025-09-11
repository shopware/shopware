import { test } from '@fixtures/AcceptanceTest';

test.describe('@Storefront', {
  tag: '@Registration', 
}, () => {

    test('Guest customer must be able to register in the Storefront.', async ({
        ShopCustomer,
        StorefrontCheckoutCart,
        StorefrontProductDetail,
        StorefrontHome,
        StorefrontAccountLogin,
        AddProductToCart,
        TestDataService,
        Register,
    }) => {
        const product = await TestDataService.createBasicProduct();

        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(`${product.translated.name} | ${product.productNumber}`);
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.presses(StorefrontCheckoutCart.goToCheckoutButton, 'Enter');

        await ShopCustomer.attemptsTo(Register({ isGuest: true }));
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.presses(StorefrontHome.accountMenuButton, 'Enter');
        await ShopCustomer.presses(StorefrontHome.closeGuestSessionButton, 'Enter');
        await ShopCustomer.expects(StorefrontAccountLogin.successAlert).toBeVisible();

    });

    test('Guest commercial customer must be able to register in the Storefront.', async ({
        ShopCustomer,
        StorefrontHome,
        StorefrontCheckoutCart,
        StorefrontProductDetail,
        StorefrontAccountLogin,
        AddProductToCart,
        TestDataService,
        Register,
    }) => {
        await TestDataService.setSystemConfig({ 'core.loginRegistration.showAccountTypeSelection': true });
        const product = await TestDataService.createBasicProduct();

        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.page).toHaveTitle(`${product.translated.name} | ${product.productNumber}`);
        await ShopCustomer.attemptsTo(AddProductToCart(product));
        await ShopCustomer.presses(StorefrontCheckoutCart.goToCheckoutButton, 'Enter');
        await ShopCustomer.presses(StorefrontAccountLogin.accountTypeSelect, 'Space');
        await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');
        await ShopCustomer.attemptsTo(Register({ isCommercial: true, isGuest: true }));
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.presses(StorefrontHome.accountMenuButton, 'Enter');
        await ShopCustomer.presses(StorefrontHome.closeGuestSessionButton, 'Enter');
        await ShopCustomer.expects(StorefrontAccountLogin.successAlert).toBeVisible();
    });
});