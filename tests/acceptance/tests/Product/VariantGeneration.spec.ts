import { test } from '@fixtures/AcceptanceTest';
import { PropertyGroup } from '@shopware-ag/acceptance-test-suite';

test('Shop administrator should be able to create product variants.', { tag: '@Product' }, async ({
    ShopAdmin,
    TestDataService,
    AdminProductDetail,
    GenerateVariants,
}) => {
    const product = await TestDataService.createBasicProduct();
    const color = await TestDataService.createColorPropertyGroup();
    const size = await TestDataService.createTextPropertyGroup();

    await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
    await ShopAdmin.page.waitForLoadState('domcontentloaded');

    await ShopAdmin.attemptsTo(GenerateVariants(color.name, size.name));

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
    StorefrontProductDetail,
    CheckVisibilityInHome,
    InstanceMeta,
}) => {

    test.slow(InstanceMeta.isSaaS);
    await TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true });
    const color = await TestDataService.createColorPropertyGroup(
        {
            name: 'Color',
            description: 'Color Description',
            options: [
                { name: 'Red', colorHexCode: '#bf0f2a', },
            ],
        }
    );
    const propertyGroupsColor: PropertyGroup[] = [color];
    const colorManufacturer = await TestDataService.createBasicManufacturer({
        name: 'Color Manufacturer',
        description: 'Color Description Manufacturer',
    });
    const parentProductColor = await TestDataService.createBasicProduct({ manufacturerId: colorManufacturer.id });
    await test.step('Verify property display on the product detail page', async () => {
        const variantProductColor = await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
            description: 'Variant description',
        });
        await CheckVisibilityInHome(variantProductColor.at(0).name)();
        await ShopCustomer.goesTo(StorefrontProductDetail.url(variantProductColor.at(0)));
        await ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeVisible();
        await ShopCustomer.expects(StorefrontProductDetail.productDetailConfiguratorGroupTitle).toHaveText(
            `Select ${color.name}`
        );
        await ShopCustomer.expects(StorefrontProductDetail.productDetailConfiguratorOptionInputs).toHaveCount(
            variantProductColor.length
        );
    });
});
