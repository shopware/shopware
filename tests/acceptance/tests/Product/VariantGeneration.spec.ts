import { test } from '@fixtures/AcceptanceTest';
import { PropertyGroup } from '@shopware-ag/acceptance-test-suite';
import { CheckVisibilityInHome } from '@tasks/ShopCustomer/Listing/CheckVisibilityInHome';

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

test('Customer should be able to see a new property displayed on the product detail page', async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    StorefrontProductDetail,
    CheckVisibilityInHome,
}) => {
    await TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true });
    const color = await TestDataService.createColorPropertyGroup();
    const propertyGroupsColor: PropertyGroup[] = [color];
    const colorManufacturer = await TestDataService.createBasicManufacturer({
        name: 'Color Manufacturer',
        description: 'Color Description Manufacturer',
    });
    const parentProductColor = await TestDataService.createBasicProduct({ manufacturerId: colorManufacturer.id });
    await CheckVisibilityInHome(parentProductColor.name)();

    await test.step('Verify property display on the product detail page', async () => {
        const variantProductColor = await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
            description: 'Variant description',
        });
        await ShopCustomer.goesTo(StorefrontHome.url());
        // Find and click the product with any of the expected color variant names
        const variantNames = variantProductColor.map(product => product.name);
        const productNames = await StorefrontHome.productItemNames.allTextContents();
        const productIndex = productNames.findIndex(
            name => variantNames.includes(name.trim())
        );
        ShopCustomer.expects(productIndex).not.toEqual(-1);
        await StorefrontHome.productItemNames.nth(productIndex).click();
        await ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeVisible();
        await ShopCustomer.expects(StorefrontProductDetail.productDetailConfiguratorGroupTitle).toHaveText(
            `Select ${color.name}`
        );
        await ShopCustomer.expects(StorefrontProductDetail.productDetailConfiguratorOptionInputs).toHaveCount(
            variantProductColor.length
        );
    });
});
