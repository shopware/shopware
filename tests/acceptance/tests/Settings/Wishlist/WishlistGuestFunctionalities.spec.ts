import { test } from '@fixtures/AcceptanceTest';

test('Guest customer is able to add and remove products to the wishlist',{ tag: ['@Wishlist', '@Storefront'] }, async ({
    TestDataService,
    ShopCustomer,
    StorefrontHeader,
    StorefrontHome,
    StorefrontOffCanvasCart,
    StorefrontWishlist,
    AddProductToCartFromWishlist,
    AddProductToWishlist,
    Login,
}) => {
    await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });
    const product1 = await TestDataService.createBasicProduct();
    const product2 = await TestDataService.createBasicProduct();
    const product1Locators = await StorefrontHome.getListingItemByProductName(product1.name);
    const product2Locators = await StorefrontHome.getListingItemByProductName(product2.name);

    await test.step('Accept all cookies and reload page', async () => {
        await TestDataService.setSystemConfig({ 'core.basicInformation.acceptAllCookies': true });
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.presses(StorefrontHome.consentAcceptAllCookiesButton);
        await ShopCustomer.expects(StorefrontHome.consentAcceptAllCookiesButton).not.toBeVisible();
    });

    await test.step('Add product1 to the wishlist and verify wishlist count updates to 1', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
        await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toHaveText('1');
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
        await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toHaveText('1');
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
    });

    await test.step('Add product2 to the wishlist and verify', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));
        await ShopCustomer.expects(product2Locators.wishlistAddedIcon).toBeVisible();
    });

                // Wait for cookies to be set (critical for wishlist functionality)
                await ShopCustomer.expects(async () => {
                    const cookies = await StorefrontHome.page.context().cookies();
                    const cookiePreference = cookies.find((c) => c.name === 'cookie-preference')?.value;
                    const wishlistCookie = cookies.find((c) => c.name === 'wishlist-enabled')?.value;
                    await ShopCustomer.expects(cookiePreference).toBe('1');
                    await ShopCustomer.expects(wishlistCookie).toBe('1');
                }).toPass({
                    intervals: [
                        1_000,
                        2_500,
                    ],
                });
            });

            const product1Locators = await StorefrontHome.getListingItemByProductName(product1.name);
            const product2Locators = await StorefrontHome.getListingItemByProductName(product2.name);

            await test.step('Add product1 to the wishlist and verify wishlist count updates to 1', async () => {
                await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
                await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible();
                await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toHaveText('1');
            });

            await test.step('Add product1 to the cart from wishlist and verify cart total is same with product price', async () => {
                const productPrice = await product1Locators.productPrice.innerText();
                await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1));
                const offCanvasSubtotal = await StorefrontOffCanvasCart.subTotalPrice.innerText();
                ShopCustomer.expects(offCanvasSubtotal).toBe(productPrice);
                const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product1.productNumber);
                await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).toBeVisible();
            });

            await test.step('Login as customer and verify product1 is still in wishlist', async () => {
                await ShopCustomer.attemptsTo(Login());
                await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toHaveText('1', { timeout: 15_000 });
                await ShopCustomer.goesTo(StorefrontHome.url());
                await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible({ timeout: 15_000 });
            });

            await test.step('Add product2 to the wishlist and verify', async () => {
                await ShopCustomer.attemptsTo(AddProductToWishlist(product2));

                await ShopCustomer.expects(product2Locators.wishlistAddedIcon).toBeVisible({ timeout: 15_000 });
            });

            await test.step('Navigate to the wishlist and verify that the products are visible', async () => {
                await ShopCustomer.presses(StorefrontHeader.wishlistIcon);
                await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toHaveText('2', { timeout: 15_000 });
                await ShopCustomer.expects(StorefrontWishlist.wishListHeader.first()).toBeVisible();
                await ShopCustomer.expects(product1Locators.productName).toBeVisible();
                await ShopCustomer.expects(product2Locators.productName).toBeVisible();
            });
        },
    );
});
