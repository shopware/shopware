import { test } from '@fixtures/AcceptanceTest';

test('Customer is able to add and remove products to the wishlist',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    StorefrontWishlist,
    AddProductToCartFromWishlist,
    Login
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });
    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product1Locators = await StorefrontHome.getListingItemByProductId(product1.id);
    const product2Locators = await StorefrontHome.getListingItemByProductId(product2.id);

    await test.step('Add to products to the wishlist and check the basket count', async () => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
        await ShopCustomer.goesTo(StorefrontHome.url());
        await StorefrontHome.consentAcceptAllCookiesButton.click();
        await StorefrontHome.page.reload();
    });

    await test.step('Add two products to the wishlist', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('1');
    });

    await test.step('Add product to cart from wishlist and verify it is added', async () => {
        await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1));
        const productPrice = await product1Locators.productPrice.innerText();
        const offCanvasSubtotal = await StorefrontWishlist.offCanvasSummaryTotalPrice.innerText();
        ShopCustomer.expects(offCanvasSubtotal).toBe(productPrice);
    });

    await test.step('Login as customer and verify the product1 is already added to wishlist', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('1');
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();       
    });

    await test.step('Add product2 to the wishlist', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));
        await ShopCustomer.expects(product2Locators.wishlistAddedIcon).toBeVisible();   
    });

    await test.step('Navigate to wishlist and verify that the products are in correct order', async () => {
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('2');
        await ShopCustomer.expects(StorefrontWishlist.wishListHeader).toBeVisible();
        const firstProductInListing = StorefrontWishlist.productListItems.first();
        const firstProductName = await firstProductInListing.locator('.product-name').innerText();
        const expectedProductName = await product2Locators.productName.innerText();
        ShopCustomer.expects(firstProductName).toBe(expectedProductName);
    });
});


