import { test } from '@fixtures/AcceptanceTest';

test('Customer is able to add and remove products to the wishlist',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    Login, RemoveProductFromWishlist,
    StorefrontWishlist,
    AddProductToCartFromWishlist,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });

    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product3 = await TestDataService.createBasicProduct();

    const product1Data = await StorefrontHome.getListingItemByProductId(product1.id);
    const product2Data = await StorefrontHome.getListingItemByProductId(product2.id);
    const product3Data = await StorefrontHome.getListingItemByProductId(product3.id);

    await test.step('Add to products to the wishlist and check the basket count', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Data.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));
        await ShopCustomer.expects(product2Data.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.attemptsTo(AddProductToWishlist(product3));
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toContainText('3');
    });

    await test.step('Remove a product from the wishlist and check the basket count', async () => {
        await ShopCustomer.attemptsTo(RemoveProductFromWishlist(product3));
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toContainText('2');
        await ShopCustomer.expects(product3Data.wishlistNotAddedIcon).toBeVisible();
    });

    await test.step('Navigate to wishlist and verify the last added product is the fist in listing', async () => {
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontWishlist.wishlistIcon).toBeVisible();
        const firstProductInListing = StorefrontWishlist.productListItems.first();
        const firstProductName = await firstProductInListing.locator('.product-name').innerText();
        const expectedProductName = await product2Data.productName.innerText();
        await ShopCustomer.expects(firstProductName).toBe(expectedProductName);
    });

    await test.step('Add product to cart from wishlist and verify it is added', async () => {
        await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1))
    });
});


