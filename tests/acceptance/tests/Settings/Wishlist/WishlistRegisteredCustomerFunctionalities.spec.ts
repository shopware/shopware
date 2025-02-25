import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

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
    InstanceMeta,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });

    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product3 = await TestDataService.createBasicProduct();

    const product1Locators = await StorefrontHome.getListingItemByProductName(product1.name);
    const product2Locators = await StorefrontHome.getListingItemByProductName(product2.name);
    const product3Locators = await StorefrontHome.getListingItemByProductName(product3.name);

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

    await test.step('Navigate to the wishlist and verify that the products are visible', async () => {
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('2');
        await ShopCustomer.expects(StorefrontWishlist.wishListHeader).toBeVisible();
        await ShopCustomer.expects(product1Locators.productName).toBeVisible();
        await ShopCustomer.expects(product2Locators.productName).toBeVisible();
    });

    await test.step('Remove product2 from the wishlist page and verify that the basket updates to 1', async () => {
        const listedItemInWishlist = await StorefrontWishlist.getListingItemByProductName(product2.name);
        await listedItemInWishlist.removeFromWishlistButton.click();
        await ShopCustomer.expects(StorefrontWishlist.removeAlert).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toContainText('1');
    });

    // TO-DO: This step is skipped, please check the details from ticket : https://shopware.atlassian.net/browse/NEXT-40639
    // eslint-disable-next-line playwright/no-conditional-in-test
    if (!InstanceMeta.features['ACCESSIBILITY_TWEAKS'] && satisfies(InstanceMeta.version, '<6.7')) {
        await test.step('Add product to cart from wishlist and verify it is added and wishlist icon is visible on offcanvas', async () => {       
            await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1));
            const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product1.productNumber);
            const expectedPrice = await product1Locators.productPrice.innerText();
            const itemPrice = await offcanvasItem.productTotalPriceValue.innerText();
            await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).toBeVisible(); 
            await ShopCustomer.expects(itemPrice).toBe(expectedPrice);       
    });
    }
});