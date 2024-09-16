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
    // eslint-disable-next-line playwright/no-skipped-test
    test.skip("not supported in 6.5");

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
    // eslint-disable-next-line playwright/no-skipped-test
    test.skip("not supported in 6.5");

    const productCount = 10;
    const promises = [];

    const category = await TestDataService.createCategory();

    const createProductAndAssign = async () => {
        const product = await TestDataService.createProductWithImage();
        return await TestDataService.assignProductCategory(product.id, category.id);
    }

    for (let i = 0; i < productCount; i++) {
        promises.push(createProductAndAssign());
    }

    await Promise.all(promises);

    await ShopCustomer.goesTo(StorefrontCategory.url(category.name));
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
    // eslint-disable-next-line playwright/no-skipped-test
    test.skip("not supported in 6.5");

    const product = await TestDataService.createProductWithImage();

    await ShopCustomer.attemptsTo(Login());

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product, '5'));

    await ShopCustomer.goesTo(StorefrontCheckoutCart.url());
    await ShopCustomer.attemptsTo(ValidateLighthouseScore(StorefrontCheckoutCart.page, 'Storefront-Cart'));
});
