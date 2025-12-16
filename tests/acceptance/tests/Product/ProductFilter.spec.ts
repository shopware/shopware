import { test } from '@fixtures/AcceptanceTest';
import { Manufacturer, Product, PropertyGroup } from '@shopware-ag/acceptance-test-suite';

const TIMEOUT = 15_000;

test('Customer should see unavailable filter disabled based on selected filter', { tag: ['@Product', '@Storefront'] }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    SelectProductFilterOption,
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
                { name: 'Red', colorHexCode: '#bf0f2a' },
            ],
        }
    );
    const size = await TestDataService.createTextPropertyGroup(
        {
            name: 'Size',
            description: 'Size Description',
            options: [
                { name: 'Medium' },
            ],
        }
    );
    const propertyGroupsColor: PropertyGroup[] = [color];
    const propertyGroupsText: PropertyGroup[] = [size];
    const sizeOptions = await TestDataService.getPropertyGroupOptions(size.id);
    let colorManufacturer: Manufacturer;
    let parentProductColor: Product;
    let variantProductColor: Product[];
    let sizeManufacturer: Manufacturer;
    let parentProductSize: Product;
    let variantProductSize: Product[];
    let freeShipProduct: Product;
    let basicProduct: Product;

    await test.step('Create manufacturer and products then verify products created', async () => {
        sizeManufacturer = await TestDataService.createBasicManufacturer({
            name: 'Size Manufacturer',
            description: 'Size Description Manufacturer',
        });
        colorManufacturer = await TestDataService.createBasicManufacturer({
            name: 'Color Manufacturer',
            description: 'Color Description Manufacturer',
        });
        parentProductColor = await TestDataService.createBasicProduct({ manufacturerId: colorManufacturer.id });
        parentProductSize = await TestDataService.createBasicProduct({ manufacturerId: sizeManufacturer.id });
        const freeShipManufacturer = await TestDataService.createBasicManufacturer({
            name: 'Free-shipping Manufacturer',
            description: 'Free ship Description Manufacturer',
        });

        freeShipProduct = await TestDataService.createBasicProduct({ shippingFree: true, manufacturerId: freeShipManufacturer.id });
        basicProduct = await TestDataService.createBasicProduct({ name: 'Product without filters' });
        variantProductColor = await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
            description: 'Variant description',
        });
        variantProductSize = await TestDataService.createVariantProducts(parentProductSize, propertyGroupsText, {
            description: 'Variant description',
        });

        await CheckVisibilityInHome(variantProductSize.at(0).name)();
        await CheckVisibilityInHome(variantProductColor.at(0).name)();
        await CheckVisibilityInHome(freeShipProduct.name)();
        await CheckVisibilityInHome(basicProduct.name)();
    });

    await test.step('Verify setup filters display & enabled', async () => {

        await ShopCustomer.expects(async () => {
            await TestDataService.clearCaches();
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeVisible();
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeEnabled();
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeVisible();
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled();
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeVisible();
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled();
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeVisible();
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeEnabled();
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeVisible();
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeEnabled();
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });
    });

    await test.step('Select a manufacturer and verify that unavailable filter is disabled and products are filtered', async () => {
        const manufacturerLocator = await StorefrontHome.getFilterItemByFilterName(colorManufacturer.name);
        await ShopCustomer.attemptsTo(SelectProductFilterOption(StorefrontHome.manufacturerFilter, colorManufacturer.name));
        await ShopCustomer.expects(StorefrontHome.loader).not.toBeAttached();

        await ShopCustomer.expects(manufacturerLocator).toBeChecked();
        await ShopCustomer.expects(StorefrontHome.productItemNames).toHaveCount(1);
        await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: variantProductColor.at(0).name })).toHaveCount(1);

        await ShopCustomer.expects(async () => {
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeDisabled({ timeout: TIMEOUT });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });
    });

    await test.step('Reset all filters and verify that all filters are enabled', async () => {
        await ShopCustomer.presses(StorefrontHome.manufacturerFilter);
        await ShopCustomer.expects(StorefrontHome.resetAllButton).toBeVisible();
        await ShopCustomer.presses(StorefrontHome.resetAllButton);
        await ShopCustomer.expects(StorefrontHome.loader).not.toBeAttached();

        await ShopCustomer.expects(async () => {
            await TestDataService.clearCaches();
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });

        await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: variantProductSize.at(0).name })).toHaveCount(1);
        await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: variantProductColor.at(0).name })).toHaveCount(1);
        await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: freeShipProduct.name })).toHaveCount(1);
        await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: basicProduct.name })).toHaveCount(1);
    });

    await test.step('Select another manufacturer and verify that a different filter is disabled', async () => {
        await ShopCustomer.attemptsTo(SelectProductFilterOption(StorefrontHome.manufacturerFilter, sizeManufacturer.name));
        await ShopCustomer.expects(StorefrontHome.loader).not.toBeAttached();
        await ShopCustomer.expects(StorefrontHome.productItemNames).toHaveCount(1);
        await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: variantProductSize.at(0).name })).toHaveCount(1);

        await ShopCustomer.expects(async () => {
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeDisabled({ timeout: TIMEOUT });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });
    });

    await test.step('Filter only by size and verify color and freeshipping filters are disabled', async () => {
        const sizeFilter = await StorefrontHome.getFilterButtonByFilterName(size.name);
        await ShopCustomer.attemptsTo(SelectProductFilterOption(sizeFilter, sizeOptions[0].name));
        await ShopCustomer.expects(StorefrontHome.loader).not.toBeAttached();
        await ShopCustomer.expects(StorefrontHome.productItemNames).toHaveCount(1);
        await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: variantProductSize.at(0).name })).toHaveCount(1);

        await ShopCustomer.expects(async () => {
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled({ timeout: TIMEOUT });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });

        await ShopCustomer.presses(sizeFilter);
        await ShopCustomer.expects(StorefrontHome.resetAllButton).toBeVisible();
        await ShopCustomer.presses(StorefrontHome.resetAllButton);
        await ShopCustomer.expects(StorefrontHome.loader).not.toBeAttached();

        await ShopCustomer.expects(async () => {
            await TestDataService.clearCaches();
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeVisible();
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeEnabled();
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeVisible();
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled();
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeVisible();
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled();
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeVisible();
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeEnabled();
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeVisible();
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeEnabled();
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });
    });

    await test.step('Select filter by free shipping, verify that all filters are disabled', async () => {
        await ShopCustomer.presses(StorefrontHome.freeShippingFilter);
        await ShopCustomer.expects(StorefrontHome.loader).not.toBeAttached();

        await ShopCustomer.expects(async () => {
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled({ timeout: TIMEOUT });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });
    });
});

test('Customer should see unavailable filter options disabled when filtering by rating', { tag: ['@Product', '@Storefront'] }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    CheckVisibilityInHome,
    InstanceMeta,
}) => {
    test.slow(InstanceMeta.isSaaS);
    await TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true });
    const color = await TestDataService.createColorPropertyGroup();
    const propertyGroupsColor: PropertyGroup[] = [color];
    const colorManufacturer = await TestDataService.createBasicManufacturer({
        name: 'Color Manufacturer',
        description: 'Color Description Manufacturer',
    });
    const parentProductColor = await TestDataService.createBasicProduct({ manufacturerId: colorManufacturer.id, variantListingConfig: { displayParent: true } });
    await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
        description: 'Variant description',
    });
    const freeShipManufacturer = await TestDataService.createBasicManufacturer({
        name: 'Free-shipping Manufacturer',
        description: 'Free ship Description Manufacturer',
    });
    const productWithShippingAndManufacturer = await TestDataService.createBasicProduct({ shippingFree: true, manufacturerId: freeShipManufacturer.id });
    const productWithRating1 = await TestDataService.createBasicProduct();
    const productWithRating2 = await TestDataService.createBasicProduct();
    const productWithoutFilter = await TestDataService.createBasicProduct({ name: 'Product without filters' });

    await TestDataService.createProductReview(productWithRating1.id, { points: 3 });
    await TestDataService.createProductReview(productWithRating2.id, { points: 5 });
    const products = [productWithRating1, productWithRating2];

    await CheckVisibilityInHome(productWithRating2.name)();
    await CheckVisibilityInHome(productWithRating1.name)();
    await CheckVisibilityInHome(productWithoutFilter.name)();
    await CheckVisibilityInHome(productWithShippingAndManufacturer.name)();
    await CheckVisibilityInHome(parentProductColor.name)();

    await test.step('Verify setup filters display', async () => {

        await ShopCustomer.expects(async () => {
            await TestDataService.clearCaches();
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            await ShopCustomer.expects(StorefrontHome.productRatingButton).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.productRatingButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeVisible({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeEnabled({ timeout: TIMEOUT });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });
    });

    await test.step('When a rating is selected, verifies that any unavailable filter is disabled and that the products are filtered accordingly.', async () => {
        await ShopCustomer.presses(StorefrontHome.productRatingButton);
        const ratingLocator = await StorefrontHome.getRatingItemLocatorByRating(3);
        /**
         * Cannot use presses() as this opens a list of radio buttons but the inputs are lacking
         *     a checked attribute so ShopCustomer.selectsRadioButton() cannot be used either.
         */
        await ratingLocator.click();
        await ShopCustomer.expects(StorefrontHome.loader).not.toBeAttached();

        await ShopCustomer.expects(async () => {
            await TestDataService.clearCaches();
            await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeDisabled({ timeout: TIMEOUT });
            await ShopCustomer.expects(StorefrontHome.productItemNames).toHaveCount(products.length);
            await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: productWithRating1.name })).toHaveCount(1);
            await ShopCustomer.expects(StorefrontHome.productItemNames.filter({ hasText: productWithRating2.name })).toHaveCount(1);
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });
    });
});
