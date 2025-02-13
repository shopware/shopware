import { test } from '@fixtures/AcceptanceTest';

test('Same product should merge in wishlist after adding as registered and guest customer.',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    StorefrontWishlist,
    Login,
    Logout,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });
    await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });

    const product1 = await TestDataService.createBasicProduct();
    const product1Locators = await StorefrontHome.getListingItemByProductName(product1.name);

    await test.step('Log in as a registered user and add a product to the wishlist.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
    });

    await test.step('Log out and add the same product to the wishlist as a guest user.', async () => {
        await ShopCustomer.attemptsTo(Logout());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await StorefrontHome.consentAcceptAllCookiesButton.click();
        await StorefrontHome.page.reload();
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
    });

    await test.step('Log in again and verify that only one product in wishlist.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontWishlist.url());     
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('1');
        await ShopCustomer.expects(StorefrontWishlist.wishListHeader).toBeVisible();
        const firstProductInListing = StorefrontWishlist.productListItems.first();
        await ShopCustomer.expects(firstProductInListing.locator('.product-name')).toContainText(product1.name);
    });
});

test('Different products should merge and in correct order in wishlist after adding as registered and guest customer.',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    StorefrontWishlist,
    Login,
    Logout,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });

    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();

    const product1Locators = await StorefrontHome.getListingItemByProductName(product1.name);
    const product2Locators = await StorefrontHome.getListingItemByProductName(product2.name);

    await test.step('Log in as a registered user and add a product to the wishlist.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
    });

    await test.step('Log out and accept all cookies.', async () => {
        await ShopCustomer.attemptsTo(Logout());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await StorefrontHome.consentAcceptAllCookiesButton.click();
        await StorefrontHome.page.reload();
    });

    await test.step('As a guest user add product2 to wislist', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));
        await ShopCustomer.expects(product2Locators.wishlistAddedIcon).toBeVisible();
    });

    await test.step('Log in again and verify that both products are in correct order in the wishlist.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontWishlist.url());     
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('2');
        await ShopCustomer.expects(StorefrontWishlist.wishListHeader).toBeVisible();
        const firstProductInListing = StorefrontWishlist.productListItems.first();
        await ShopCustomer.expects(firstProductInListing.locator('.product-name')).toContainText(product2.name);
    });
});