import { test } from '@fixtures/AcceptanceTest';

let originalConfig: Record<string, unknown> = {};

test.describe('Wishlist Guest Functionalities', () => {
    test.beforeEach(async ({ TestDataService }) => {
        // Get and store current config
        const getCurrentConfig = await TestDataService.AdminApiClient.get('_action/system-config?domain=core');
        if (!getCurrentConfig.ok()) {
            throw new Error(`Failed to get system config: ${getCurrentConfig.status()} ${getCurrentConfig.statusText()}`);
        }
        originalConfig = await getCurrentConfig.json();

        const updatedConfig = {
            null: {
                ...(originalConfig as Record<string, unknown>),
                'core.cart.wishlistEnabled': true,
                'core.basicInformation.acceptAllCookies': true,
            },
        };

        const updateResponse = await TestDataService.AdminApiClient.post('_action/system-config/batch', {
            data: updatedConfig
        });
        if (!updateResponse.ok()) {
            throw new Error(`Failed to update system config: ${updateResponse.status()} ${updateResponse.statusText()}`);
        }

        const cacheResponse = await TestDataService.AdminApiClient.delete('_action/cache');
        if (!cacheResponse.ok()) {
            throw new Error(`Failed to clear cache: ${cacheResponse.status()} ${cacheResponse.statusText()}`);
        }
    });

    test.afterEach(async ({ TestDataService }) => {
        if (Object.keys(originalConfig).length > 0) {
            await TestDataService.AdminApiClient.post('_action/system-config/batch', {
                data: { null: originalConfig }
            });

            await TestDataService.AdminApiClient.delete('_action/cache');
        }
    });

test('Guest customer is able to add and remove products to the wishlist', { tag: ['@Wishlist', '@Storefront'] }, async ({
    ShopCustomer,
    StorefrontHome,
    AddProductToWishlist,
    StorefrontWishlist,
    AddProductToCartFromWishlist,
    Login,
    StorefrontOffCanvasCart,
    HomeProducts,
}) => {
    const [product1, product2] = HomeProducts;

    await test.step('Navigate to home and accept cookies', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await StorefrontHome.consentAcceptAllCookiesButton.click();

        // Wait for banner to disappear
        await ShopCustomer.expects(StorefrontHome.consentCookieBannerContainer).not.toBeVisible();

        // Wait for cookie to actually be set (critical for wishlist functionality)
        await ShopCustomer.expects.poll(async () => {
            const cookies = await StorefrontHome.page.context().cookies();
            return cookies.find(c => c.name === 'cookie-preference')?.value;
        }, { timeout: 10000 }).toBe('1');
    });

    const product1Locators = await StorefrontHome.getListingItemByProductName(product1.name);
    const product2Locators = await StorefrontHome.getListingItemByProductName(product2.name);

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
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('1', { timeout: 15_000 });
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(product1Locators.wishlistAddedIcon).toBeVisible({ timeout: 15_000 });
    });

    await test.step('Add product2 to the wishlist and verify', async () => {
        await ShopCustomer.attemptsTo(AddProductToWishlist(product2));

        await ShopCustomer.expects(product2Locators.wishlistAddedIcon).toBeVisible({ timeout: 15_000 });
    });

    await test.step('Navigate to the wishlist and verify that the products are visible', async () => {
        await StorefrontHome.wishlistIcon.click();
        await ShopCustomer.expects(StorefrontHome.wishlistBasket).toHaveText('2', { timeout: 15_000 });
        await ShopCustomer.expects(StorefrontWishlist.wishListHeader.first()).toBeVisible();
        await ShopCustomer.expects(product1Locators.productName).toBeVisible();
        await ShopCustomer.expects(product2Locators.productName).toBeVisible();
    });
});
});
