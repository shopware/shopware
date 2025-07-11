import { test } from '@fixtures/AcceptanceTest';
import { Manufacturer, Product, PropertyGroup } from '@shopware-ag/acceptance-test-suite';
import { CheckVisibilityInHome } from '@tasks/ShopCustomer/Listing/CheckVisibilityInHome';

test('Customer should see unavailable filter disabled based on selected filter', async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    SelectProductFilterOption,
    CheckVisibilityInHome,
}) => {
    await TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true });
    const color = await TestDataService.createColorPropertyGroup();
    const size = await TestDataService.createTextPropertyGroup();
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
        const basicProduct = await TestDataService.createBasicProduct({ name: 'Product without filters' });

        await CheckVisibilityInHome(freeShipProduct.name)();
        await CheckVisibilityInHome(parentProductColor.name)();
        await CheckVisibilityInHome(parentProductSize.name)();

        variantProductColor = await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
            description: 'Variant description',
        });
        variantProductSize = await TestDataService.createVariantProducts(parentProductSize, propertyGroupsText, {
            description: 'Variant description',
        });
    });

    await test.step('Verify setup filters display', async () => {
        await ShopCustomer.page.goto(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeVisible();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeVisible();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeVisible();
    });

    await test.step('Select a manufacturer and verify that unavailable filter is disabled and products are filtered', async () => {
        const manufacturerLocator = await StorefrontHome.getFilterItemByFilterName(colorManufacturer.name);
        await ShopCustomer.attemptsTo(SelectProductFilterOption(StorefrontHome.manufacturerFilter, colorManufacturer.name));
        await ShopCustomer.expects(manufacturerLocator).toBeChecked();
        await ShopCustomer.expects(StorefrontHome.productItemNames).toHaveCount(1);
        await ShopCustomer.expects(StorefrontHome.productVariantCharacteristicsOptions).toHaveText(/Red|Green|Blue/);
        const expectedNames = variantProductColor.map((product) => product.name);
        const actualNames = (await StorefrontHome.productItemNames.allTextContents());
        for (const name of actualNames) {
            ShopCustomer.expects(expectedNames).toContain(name.trim());
        }
        await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeEnabled();
        await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeDisabled();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeDisabled();
    });

    await test.step('Reset all filters and verify that all filters are enabled', async () => {
        await StorefrontHome.manufacturerFilter.click();
        await ShopCustomer.expects(StorefrontHome.resetAllButton).toBeVisible();
        await StorefrontHome.resetAllButton.click(); 
        await StorefrontHome.loader.waitFor({ state: 'hidden' });
        await CheckVisibilityInHome(freeShipProduct.name)();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeEnabled();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeEnabled();
        await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled();
        await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeEnabled();
        await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled();
        
        const actualNames = await StorefrontHome.productItemNames.allTextContents();
        const expectedNames = [
            ...variantProductColor.map((product) => product.name),
            ...variantProductSize.map((product) => product.name),
            freeShipProduct.name
        ];
        const matchingCount = actualNames.filter(name => expectedNames.includes(name.trim())).length;
        ShopCustomer.expects(matchingCount).toEqual(3);
    });

    await test.step('Select another manufacturer and verify that a different filter is disabled', async () => {
        await ShopCustomer.attemptsTo(SelectProductFilterOption(StorefrontHome.manufacturerFilter, sizeManufacturer.name));
        const actualNames = await StorefrontHome.productItemNames.allTextContents();
        const expectedNames = variantProductSize.map((product) => product.name);
        const matchingCount = actualNames.filter(name => expectedNames.includes(name.trim())).length;
        ShopCustomer.expects(matchingCount).toBeGreaterThan(0);
        await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeDisabled();
        await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeEnabled();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeDisabled();
    });

    await test.step('Filter only by size and verify color and manufacturer filters are disabled', async () => {
        await StorefrontHome.manufacturerFilter.click();
        await StorefrontHome.resetAllButton.click();
        const sizeFilter = await StorefrontHome.getFilterButtonByFilterName(size.name);
        const colorFilter = await StorefrontHome.getFilterButtonByFilterName(color.name);
        await ShopCustomer.expects(sizeFilter).toBeEnabled();
        await ShopCustomer.expects(colorFilter).toBeEnabled();
        await ShopCustomer.attemptsTo(SelectProductFilterOption(sizeFilter, sizeOptions[0].name));

        const actualNames = await StorefrontHome.productItemNames.allTextContents();
        const expectedNames = variantProductSize.map((product) => product.name);
        const matchingCount = actualNames.filter(name => expectedNames.includes(name.trim())).length;
        ShopCustomer.expects(matchingCount).toBeGreaterThan(0);

        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeDisabled();
        await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeDisabled();
        await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled();
        await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled();
        await StorefrontHome.resetAllButton.click();
    });

    await test.step('Select filter by free shipping, verify that all filters are disabled', async () => {
        await StorefrontHome.freeShippingFilter.click();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(size.name)).toBeDisabled();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeDisabled();
        await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled();
        await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeEnabled();
    });
});

test('Customer should see unavailable filter options disabled when filtering by rating', async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    CheckVisibilityInHome,
}) => {
    await TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true });
    const color = await TestDataService.createColorPropertyGroup();
    const propertyGroupsColor: PropertyGroup[] = [ color ];
    const colorManufacturer = await TestDataService.createBasicManufacturer({
        name: 'Color Manufacturer',
        description: 'Color Description Manufacturer',
    });
    const parentProductColor = await TestDataService.createBasicProduct({ manufacturerId: colorManufacturer.id });
    await TestDataService.createVariantProducts(parentProductColor, propertyGroupsColor, {
        description: 'Variant description',
    });
    const freeShipManufacturer = await TestDataService.createBasicManufacturer({
        name: 'Free-shipping Manufacturer',
        description: 'Free ship Description Manufacturer',
    });
    await TestDataService.createBasicProduct({ shippingFree: true, manufacturerId: freeShipManufacturer.id });
    const productWithRating1 = await TestDataService.createBasicProduct();
    const productWithRating2 = await TestDataService.createBasicProduct();
    await TestDataService.createProductReview(productWithRating1.id, { points: 5 });
    await TestDataService.createProductReview(productWithRating2.id, { points: 5 });
    const products = [productWithRating1, productWithRating2];
    await TestDataService.createBasicProduct({ name: 'Product without filters' });
    await CheckVisibilityInHome(productWithRating1.name)();
    await CheckVisibilityInHome(productWithRating2.name)();

    await test.step('Verify setup filters display', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.productRatingButton).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeVisible();
        await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeVisible();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeVisible();
    });

    await test.step('When a rating is selected, verifies that any unavailable filter is disabled and that the products are filtered accordingly.', async () => {
        await StorefrontHome.productRatingButton.click();
        const ratingLocator = await StorefrontHome.getRatingItemLocatorByRating(5);
        await ratingLocator.click();
        await StorefrontHome.loader.waitFor({ state: 'hidden' });
        await ShopCustomer.expects(StorefrontHome.freeShippingFilter).toBeDisabled();
        await ShopCustomer.expects(StorefrontHome.priceFilterButton).toBeEnabled();
        await ShopCustomer.expects(StorefrontHome.manufacturerFilter).toBeDisabled();
        await ShopCustomer.expects(await StorefrontHome.getFilterButtonByFilterName(color.name)).toBeDisabled();
        const actualNames = await StorefrontHome.productItemNames.allTextContents();
        ShopCustomer.expects(actualNames.length).toEqual(products.length);
        const expectedNames = products.map((product) => product.name);
        for (const name of actualNames) {
            ShopCustomer.expects(expectedNames).toContain(name.trim());
        }
    });
});
