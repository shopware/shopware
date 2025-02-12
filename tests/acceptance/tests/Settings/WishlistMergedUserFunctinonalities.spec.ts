import { test } from '@fixtures/AcceptanceTest';

test('Guest customer is able to add and remove products to the wishlist',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    StorefrontWishlist,
    Login,
    Logout,
}) => {
    /*
    - Kayıtlı kullanıcı olarak giriş yap ve bir ürünü dilek listesine ekle.
- Çıkış yap ve anonim olarak aynı ürünü tekrar ekle.
- Tekrar giriş yap ve birleşmeyi doğrula.
    */
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });
    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product1Locators = await StorefrontHome.getListingItemByProductName(product1.name);
    await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });

    await test.step('Log in as a registered user and add a product to the wishlist.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
    });

    await test.step('Log out and add the same product to the wishlist as an anonymous user.', async () => {
        await ShopCustomer.attemptsTo(Logout());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await StorefrontHome.consentAcceptAllCookiesButton.click();
        await StorefrontHome.page.reload();
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
    });

    await test.step('Log in again and verify the merge.', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontWishlist.url());     
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('1');
        await ShopCustomer.expects(StorefrontWishlist.wishListHeader).toBeVisible();
        const firstProductInListing = StorefrontWishlist.productListItems.first();
        const firstProductName = await firstProductInListing.locator('.product-name').innerText();
        ShopCustomer.expects(firstProductName).toBe(product1.name);
    });
});