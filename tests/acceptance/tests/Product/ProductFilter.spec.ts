import { test } from '@fixtures/AcceptanceTest';
import { Manufacturer, Product, PropertyGroup } from '@shopware-ag/acceptance-test-suite';

const TIMEOUT = 15_000;

test('Customer should see unavailable filter disabled based on selected filter', { tag: ['@Product', '@Storefront'] }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    SelectProductFilterOption,
    CheckVisibilityInHome,
}) => {
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
    let variantProductColor: Product[];
    let sizeManufacturer: Manufacturer;
    let variantProductSize: Product[];
    let freeShipProduct: Product;
    let basicProduct: Product;

    await test.step('Create manufacturer and products then verify products created', async () => {
        const setupPromises: Promise<void>[] = [];

        setupPromises.push(TestDataService.createBasicManufacturer({
            name: 'Size Manufacturer',
            description: 'Size Description Manufacturer',
        }).then(manufacturer => {
            sizeManufacturer = manufacturer;

            return TestDataService.createBasicProduct({ manufacturerId: manufacturer.id });
        }).then(parentProduct => {
            return TestDataService.createVariantProducts(parentProduct, propertyGroupsText, {
                description: 'Variant description',
            });
        }).then(variantProduct => {variantProductSize = variantProduct;}));

        setupPromises.push(TestDataService.createBasicManufacturer({
            name: 'Color Manufacturer',
            description: 'Color Description Manufacturer',
        }).then(manufacturer => {
            colorManufacturer = manufacturer;

            return TestDataService.createBasicProduct({ manufacturerId: manufacturer.id })
        }).then(parentProduct => {
            return TestDataService.createVariantProducts(parentProduct, propertyGroupsColor, {
                description: 'Variant description',
            });
         }).then(variantProduct => {variantProductColor = variantProduct;}));

        setupPromises.push(TestDataService.createBasicManufacturer({
            name: 'Free-shipping Manufacturer',
            description: 'Free ship Description Manufacturer',
        }).then(manufacturer => {
            return TestDataService.createBasicProduct({ shippingFree: true, manufacturerId: manufacturer.id });
        }).then(product => {freeShipProduct = product}));

        setupPromises.push(TestDataService.createBasicProduct({ name: 'Product without filters' })
        .then(product => {basicProduct = product}));

        // await all setup promises to complete before proceeding, this allows the product creation to happen in parallel which should speed up the setup significantly
        await Promise.all(setupPromises);
        // currently CheckVisibilityInHome clears caches multiple times, it should only be needed once here
        await TestDataService.clearCaches();

        await CheckVisibilityInHome(variantProductSize.at(0).name)();
        await CheckVisibilityInHome(variantProductColor.at(0).name)();
        await CheckVisibilityInHome(freeShipProduct.name)();
        await CheckVisibilityInHome(basicProduct.name)();
    });

    await test.step('Verify setup filters display & enabled', async () => {
        await ShopCustomer.expects(async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
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
            await ShopCustomer.goesTo(StorefrontHome.url());
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
            await ShopCustomer.goesTo(StorefrontHome.url());
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
}) => {
    const setupPromises: Promise<unknown>[] = [];

    setupPromises.push(TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true }));

    let color: PropertyGroup;
    let propertyGroupsColor: PropertyGroup[];
    let parentProductColor: Product;
    let productWithShippingAndManufacturer: Product;
    let productWithRating1: Product;
    let productWithRating2: Product;
    let productWithoutFilter: Product;

    setupPromises.push(TestDataService.createColorPropertyGroup()
        .then(createdColor => {
            color = createdColor;
            propertyGroupsColor = [color];
                return TestDataService.createBasicManufacturer({
                    name: 'Color Manufacturer',
                    description: 'Color Description Manufacturer',
                });
        }).then(manufacturer => {
            return TestDataService.createBasicProduct({ manufacturerId: manufacturer.id, variantListingConfig: { displayParent: true } });
        }).then(parentProduct => {
            parentProductColor = parentProduct;

            return TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
                description: 'Variant description',
            });
        }));

    setupPromises.push(TestDataService.createBasicManufacturer({
        name: 'Free-shipping Manufacturer',
        description: 'Free ship Description Manufacturer',
        }).then(manufacturer => {
            return TestDataService.createBasicProduct({ shippingFree: true, manufacturerId: manufacturer.id });
        }).then(product => {productWithShippingAndManufacturer = product}));

    setupPromises.push(TestDataService.createBasicProduct()
        .then(product => {
            productWithRating1 = product;

            return TestDataService.createProductReview(product.id, { points: 3 });
        }));

    setupPromises.push(TestDataService.createBasicProduct()
        .then(product => {
            productWithRating2 = product;

            return TestDataService.createProductReview(product.id, { points: 5 });
        }));

    setupPromises.push(TestDataService.createBasicProduct({ name: 'Product without filters' })
        .then(product => {productWithoutFilter = product}));


    // await all setup promises to complete before proceeding, this allows the product creation to happen in parallel which should speed up the setup significantly
    await Promise.all(setupPromises);
    // currently CheckVisibilityInHome clears caches multiple times, it should only be needed once here
    await TestDataService.clearCaches();

    const products = [productWithRating1, productWithRating2];

    await CheckVisibilityInHome(productWithRating2.name)();
    await CheckVisibilityInHome(productWithRating1.name)();
    await CheckVisibilityInHome(productWithoutFilter.name)();
    await CheckVisibilityInHome(productWithShippingAndManufacturer.name)();
    await CheckVisibilityInHome(parentProductColor.name)();

    await test.step('Verify setup filters display', async () => {
        await ShopCustomer.expects(async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
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
