import { test } from '@fixtures/AcceptanceTest';

test('Customer is able to add and remove products to the wishlist',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    StorefrontWishlist,
    AddProductToCartFromWishlist,
    StorefrontOffCanvasCart,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });
    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product1Data = await StorefrontHome.getListingItemByProductId(product1.id);
    const product2Data = await StorefrontHome.getListingItemByProductId(product2.id);

    await test.step('Add to products to the wishlist and check the basket count', async () => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
        await ShopCustomer.goesTo(StorefrontHome.url());
        await StorefrontHome.consentAcceptAllCookiesButton.click();
        await StorefrontHome.page.reload();
    });

    await test.step('Add two products to the wishlist', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));
        await ShopCustomer.expects(product1Data.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.expects(product2Data.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('2');
    });

    await test.step('Check the wishlist count and products', async () => {
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontWishlist.wishListHeader).toBeVisible();
        const firstProductInListing = StorefrontWishlist.productListItems.first();
        const firstProductName = await firstProductInListing.locator('.product-name').innerText();
        const expectedProductName = await product2Data.productName.innerText();
        await ShopCustomer.expects(firstProductName).toBe(expectedProductName);
    });

    await test.step('Add product to cart from wishlist and verify it is added', async () => {
        await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1));
    });
});


