import { test } from '@fixtures/AcceptanceTest';

test('Shop administrator should be able to create product variants.', { tag: '@Product' }, async ({
    ShopAdmin,
    TestDataService,
    AdminProductDetail,
    GenerateVariants,
}) => {
    const product = await TestDataService.createBasicProduct();
    await TestDataService.createColorPropertyGroup();
    await TestDataService.createTextPropertyGroup();

    await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
    await ShopAdmin.page.waitForLoadState('domcontentloaded');

    await test.slow();
    await ShopAdmin.attemptsTo(GenerateVariants());

    /**
     * The test has to handle random behaviour.
     * Variants displayed in the admin grid can have different order and naming combinations.
     */
    const variantLocators = AdminProductDetail.page.locator('.sw-product-variants-overview__variation-link');
    const variantTexts = await variantLocators.allInnerTexts();
    const allowedVariants = [
        'RedMedium',
        'RedLarge',
        'BlueMedium',
        'BlueLarge',
        'MediumRed',
        'MediumBlue',
        'LargeRed',
        'LargeBlue',
    ];

    const validateVariants = variantTexts.every(variant => allowedVariants.includes(variant.trim()));

    ShopAdmin.expects(validateVariants).toBeTruthy();
});
