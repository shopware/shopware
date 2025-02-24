import { test } from '@fixtures/AcceptanceTest';

test('Guest customer is able to add and remove products to the wishlist',{ tag: '@Wishlist' }, async ({ 
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    StorefrontWishlist,
    AddProductToCartFromWishlist,
    Login,
    StorefrontOffCanvasCart,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });
    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product1Locators = await StorefrontHome.getListingItemByProductName(product1.name);
    const product2Locators = await StorefrontHome.getListingItemByProductName(product2.name);

    await test.step('Accept all cookies and reload page', async () => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
        await ShopCustomer.goesTo(StorefrontHome.url());
        await StorefrontHome.consentAcceptAllCookiesButton.click();
        await StorefrontHome.page.reload();
    });

    await test.step('Add product1 to the wishlist and verify wishlist count updates to 1', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('1');
    });

    await test.step('Add product1 to the cart from wishlist and verify cart total is same with product price', async () => {
        await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1));
        const productPrice = await product1Locators.productPrice.innerText();
        const offCanvasSubtotal = await StorefrontOffCanvasCart.subTotalPrice.innerText();
        ShopCustomer.expects(offCanvasSubtotal).toBe(productPrice);
        const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product1.productNumber);
        await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).toBeVisible();
    });

    await test.step('Login as customer and verify product1 is still in wishlist', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('1');
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();       
    });

    await test.step('Add product2 to the wishlist and verify', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));
        await ShopCustomer.expects(product2Locators.wishlistAddedIcon).toBeVisible();   
    });

    await test.step('Navigate to the wishlist and verify that the products are visible', async () => {
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('2');
        await ShopCustomer.expects(StorefrontWishlist.wishListHeader).toBeVisible();
        await ShopCustomer.expects(product1Locators.productName).toBeVisible();
        await ShopCustomer.expects(product2Locators.productName).toBeVisible();
    });
});