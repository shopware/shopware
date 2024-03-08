import { test } from '@fixtures/AcceptanceTest';

test('Shop administrator should be able to create product variants. @product', async ({
    shopAdmin,
    propertiesData,
    productData,
    adminProductDetailPage,
    GenerateVariants,
}) => {
    await shopAdmin.goesTo(adminProductDetailPage);
    await shopAdmin.page.waitForLoadState('domcontentloaded');

    await shopAdmin.attemptsTo(GenerateVariants());

    /**
     * The test has to handle random behaviour.
     * Variants displayed in the admin grid can have different order and naming combinations.
     */
    const variantLocators = adminProductDetailPage.page.locator('.sw-product-variants-overview__variation-link');
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

    await shopAdmin.expects(validateVariants).toBeTruthy();
});
