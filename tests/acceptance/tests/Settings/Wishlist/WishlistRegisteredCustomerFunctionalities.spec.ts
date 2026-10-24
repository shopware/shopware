import { test } from '@fixtures/AcceptanceTest';

test(
    'Customers can add or remove products from their wishlist.',
    {
        tag: [
            '@Wishlist',
            '@Storefront',
        ],
    },
    async ({
        TestDataService,
        ShopCustomer,
        StorefrontHeader,
        StorefrontHome,
        AddProductToWishlist,
        Login,
        Logout,
        RemoveProductFromWishlist,
        StorefrontWishlist,
        AddProductToCartFromWishlist,
        StorefrontOffCanvasCart,
        HomeProducts,
    }) => {
        await TestDataService.setSystemConfig({ 'core.cart.wishlistEnabled': true });

        const [
            product1,
            product2,
            product3,
        ] = HomeProducts;

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
            await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toContainText('3');
        });

        await test.step('Remove a product from the wishlist and verify that the basket updates to 2', async () => {
            await ShopCustomer.attemptsTo(RemoveProductFromWishlist(product3));
            await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toContainText('2');
            await ShopCustomer.expects(product3Locators.wishlistNotAddedIcon).toBeVisible();
        });

        await test.step('Navigate to the wishlist and verify that the products are visible', async () => {
            await ShopCustomer.presses(StorefrontHeader.wishlistIcon);
            await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toHaveText('2');
            await ShopCustomer.expects(StorefrontWishlist.wishListHeader).toBeVisible();
            await ShopCustomer.expects(product1Locators.productName).toBeVisible();
            await ShopCustomer.expects(product2Locators.productName).toBeVisible();
        });

        await test.step('Remove product2 from the wishlist page and verify that the basket updates to 1', async () => {
            const listedItemInWishlist = await StorefrontWishlist.getListingItemByProductName(product2.name);
            await ShopCustomer.presses(listedItemInWishlist.removeFromWishlistButton);
            await ShopCustomer.expects(StorefrontWishlist.removeAlert).toBeVisible();
            await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toContainText('1');
        });

        await test.step('Add product to cart from wishlist and verify it is added and wishlist icon is visible on offcanvas', async () => {
            const expectedPrice = await product1Locators.productPrice.innerText();
            await ShopCustomer.attemptsTo(AddProductToCartFromWishlist(product1));
            const offcanvasItem = await StorefrontOffCanvasCart.getLineItemByProductNumber(product1.productNumber);
            const itemPrice = await offcanvasItem.productTotalPriceValue.innerText();
            await ShopCustomer.expects(offcanvasItem.wishlistAddedButton).toBeVisible();
            await ShopCustomer.expects(itemPrice).toBe(expectedPrice);
        });

        await test.step('Merge anonymous wishlist items after login and verify wishlist order', async () => {
            await ShopCustomer.attemptsTo(Logout());
            await ShopCustomer.goesTo(StorefrontHome.url());

            //Ensure wishlist cookie isn't already set
            await StorefrontHome.page.context().clearCookies();
            await ShopCustomer.presses(product1Locators.wishlistNotAddedIcon);
            await ShopCustomer.expects(StorefrontHome.wishlistCookieAcceptButton).toBeVisible();

            //Accept wishlist cookie 
            await ShopCustomer.presses(StorefrontHome.wishlistCookieAcceptButton);
            await ShopCustomer.expects(StorefrontHome.wishlistCookieAcceptButton).not.toBeVisible();

            //Check wishlist cookie was successfully set (critical for wishlist functionality)
            await ShopCustomer.expects(async () => {
                const cookies = await StorefrontHome.page.context().cookies();
                const wishlistCookie = cookies.find((c) => c.name === 'wishlist-enabled')?.value;
                await ShopCustomer.expects(wishlistCookie).toBe('1');
            }).toPass({
                intervals: [
                    1_000,
                    2_500,
                ],
            });

            await ShopCustomer.attemptsTo(AddProductToWishlist(product1));
            await ShopCustomer.attemptsTo(AddProductToWishlist(product3));
            await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toHaveText('2');
            
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.expects(StorefrontHeader.wishlistBasket).toHaveText('2');
            
            await ShopCustomer.presses(StorefrontHeader.wishlistIcon);
            await ShopCustomer.expects(StorefrontWishlist.wishlistItems.nth(0)).toContainText(product3.name);
            await ShopCustomer.expects(StorefrontWishlist.wishlistItems.nth(1)).toContainText(product1.name);
        });
    },
);
