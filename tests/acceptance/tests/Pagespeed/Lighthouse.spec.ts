import { test } from '@fixtures/AcceptanceTest';

/**
 * These tests should only run against APP_ENV=Prod
 */
test('Product Detail Lighthouse Report', async ({
    ShopCustomer,
    TestDataService,
    ValidateLighthouseScore,
    StorefrontProductDetail,
}) => {
    const product = await TestDataService.createProductWithImage();

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(ValidateLighthouseScore(StorefrontProductDetail.page, 'Storefront-Product-Detail'));
});

test('Category Lighthouse Report', async ({
    ShopCustomer,
    TestDataService,
    ValidateLighthouseScore,
    StorefrontCategory,
}) => {

    test.setTimeout(150_000);

    const productCount = 10;

    const category = await TestDataService.createCategory();

    for (let i = 0; i < productCount; i++) {
        const product = await TestDataService.createProductWithImage();
        await TestDataService.assignProductCategory(product.id, category.id);
    }

    await ShopCustomer.goesTo(StorefrontCategory.url(category.name));

    await ShopCustomer.expects(async () => {
        await TestDataService.clearCaches();
        await ShopCustomer.goesTo(`${StorefrontCategory.url(category.name)}?a=${Date.now()}`);
        await ShopCustomer.expects(StorefrontCategory.page.locator('.cms-listing-row').locator('.product-name')).toHaveCount(productCount);
    }).toPass({
        intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
    });

    await ShopCustomer.attemptsTo(ValidateLighthouseScore(StorefrontCategory.page, 'Storefront-Category'))
});

test('Cart Lighthouse Report', async ({
    ShopCustomer,
    TestDataService,
    ValidateLighthouseScore,
    Login,
    AddProductToCart,
    StorefrontProductDetail,
    StorefrontCheckoutCart,
}) => {
    const product = await TestDataService.createProductWithImage();

    await ShopCustomer.attemptsTo(Login());

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product, '5'));

    await ShopCustomer.goesTo(StorefrontCheckoutCart.url());
    await ShopCustomer.attemptsTo(ValidateLighthouseScore(StorefrontCheckoutCart.page, 'Storefront-Cart'));
});
