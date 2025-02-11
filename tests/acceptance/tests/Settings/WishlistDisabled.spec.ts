import { test } from '@fixtures/AcceptanceTest';

test('Wishlist state is not set when disabled', { tag: '@Wishlist' }, async ({
    TestDataService,
    ShopCustomer,
    StorefrontHome,
    StorefrontProductDetail,
    AddProductToCart,
    StorefrontOffCanvasCart,
}) => {
    const product1 = await TestDataService.createBasicProduct();
    const product1Locators = await StorefrontHome.getListingItemByProductId(product1.id);

    await test.step('Disable wishlist in system settings', async () => {
        await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': false });
    });

    await test.step('Wishlist Icon is Not Displayed in the Header', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).not.toBeVisible();
    });

    await test.step('Wishlist Icon is Not Displayed on Product Listings', async () => {
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).not.toBeVisible();
        await ShopCustomer.expects(product1Locators.wishlistNotAddedIcon).not.toBeVisible();

    });

    await test.step('Wishlist Icon is Not Displayed on Product Detail', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product1));
        await ShopCustomer.expects(StorefrontProductDetail.wishlistAddedButton).not.toBeVisible();
        await ShopCustomer.expects(StorefrontProductDetail.wishlistNotAddedButton).not.toBeVisible();
    })

    await test.step('Wishlist Icon is Not Displayed on Product Detail', async () => {
        await ShopCustomer.attemptsTo(AddProductToCart(product1));
        const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product1.productNumber);
        await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).not.toBeVisible();
        await ShopCustomer.expects(offcanvasItem.wishlistNotAddedButton).not.toBeVisible();
    })
});