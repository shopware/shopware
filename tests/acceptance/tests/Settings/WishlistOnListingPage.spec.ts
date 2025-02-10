import { test } from '@fixtures/AcceptanceTest';

test('Customers can add or remove products from their wishlist.',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    Login,
    RemoveProductFromWishlist,
    StorefrontWishlist,
    AddProductToCartFromWishlist,
    StorefrontOffCanvasCart,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });

    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product3 = await TestDataService.createBasicProduct();

    const product1Locators = await StorefrontHome.getListingItemByProductId(product1.id);
    const product2Locators = await StorefrontHome.getListingItemByProductId(product2.id);
    const product3Locators = await StorefrontHome.getListingItemByProductId(product3.id);

    await test.step('Add three products to the wishlist and verify the basket count updates to 3', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));
        await ShopCustomer.expects(product2Locators.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.attemptsTo(AddProductToWishlist(product3));
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toContainText('3');
    });

    await test.step('Remove a product from the wishlist and verify that the basket updates to 2', async () => {
        await ShopCustomer.attemptsTo(RemoveProductFromWishlist(product3));
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toContainText('2');
        await ShopCustomer.expects(product3Locators.wishlistNotAddedIcon).toBeVisible();
    });

    await test.step('Navigate to the wishlist and verify the lastly added product appears the first in the listing', async () => {
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontWishlist.wishlistIcon).toBeVisible();
        const firstProductInListing = StorefrontWishlist.productListItems.first();
        const firstProductName = await firstProductInListing.locator('.product-name').innerText();
        const expectedProductName = await product2Locators.productName.innerText();
        ShopCustomer.expects(firstProductName).toBe(expectedProductName);
    });

    await test.step('Remove product2 from the wishlist page and verify that the basket updates to 1', async () => {
        const listedItemInWishlist = await StorefrontWishlist.getListingItemByProductId(product2.id);
        await listedItemInWishlist.removeFromWishlistButton.click();
        await ShopCustomer.expects(StorefrontWishlist.removeAlert).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toContainText('1');
    });

    await test.step('Add product to cart from wishlist and verify it is added and wishlist icon is visible on offcanvas', async () => {
        await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1));
        const offCanvasSubtotal = await StorefrontOffCanvasCart.subTotalPrice.innerText();
        const expectedPrice = await product1Locators.productPrice.innerText();
        ShopCustomer.expects(offCanvasSubtotal).toBe(expectedPrice);
        const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product1.productNumber);
        await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).toBeVisible();
    }); 
});