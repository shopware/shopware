import { test } from '@fixtures/AcceptanceTest';
import { Category } from '@shopware-ag/acceptance-test-suite';

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
    const productCount = 10;

    const category: Category = await TestDataService.createCategory();

    await Promise.all(
        Array.from({ length: productCount }, async () => {
            const product = await TestDataService.createProductWithImage();
            await TestDataService.assignProductCategory(product.id, category.id);
        })
    );

    await TestDataService.clearCaches();

    await ShopCustomer.goesTo(StorefrontCategory.url(category.name));
    await ShopCustomer.expects(StorefrontCategory.page.locator('.cms-listing-row').locator('.product-name')).toHaveCount(productCount);

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
