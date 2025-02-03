import { test } from '@fixtures/AcceptanceTest';

test('Customer is able to add and remove products to the wishlist',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    Login,
    RemoveProductFromWishlist,
    StorefrontWishlist,
    AddProductToCartFromWishlist,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });

    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product3 = await TestDataService.createBasicProduct();

    const product1Locators = await StorefrontHome.getListingItemByProductId(product1.id);
    const product2Locators = await StorefrontHome.getListingItemByProductId(product2.id);
    const product3Locators = await StorefrontHome.getListingItemByProductId(product3.id);

    await test.step('Add to products to the wishlist and check the basket count', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));
        await ShopCustomer.expects(product2Locators.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.attemptsTo(AddProductToWishlist(product3));
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toContainText('3');
    });

    await test.step('Remove a product from the wishlist and check the basket count', async () => {
        await ShopCustomer.attemptsTo(RemoveProductFromWishlist(product3));
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toContainText('2');
        await ShopCustomer.expects(product3Locators.wishlistNotAddedIcon).toBeVisible();
    });

    await test.step('Navigate to wishlist and verify the last added product is the fist in listing', async () => {
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontWishlist.wishlistIcon).toBeVisible();
        const firstProductInListing = StorefrontWishlist.productListItems.first();
        const firstProductName = await firstProductInListing.locator('.product-name').innerText();
        const expectedProductName = await product2Locators.productName.innerText();
        ShopCustomer.expects(firstProductName).toBe(expectedProductName);
    });

    await test.step('Add product to cart from wishlist and verify it is added', async () => {
        await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1));
        const productPrice = await product1Locators.productPrice.innerText();
        const offCanvasSubtotal = await StorefrontWishlist.offCanvasSummaryTotalPrice.innerText();
        ShopCustomer.expects(offCanvasSubtotal).toBe(productPrice);
    });
});


