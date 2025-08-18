import { test } from '@fixtures/AcceptanceTest';
import { PropertyGroup } from '@shopware-ag/acceptance-test-suite';

test('Customer should be able to see a new property displayed on the product detail page', async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    CheckVisibilityInHome,
}) => {

    await TestDataService.setSystemConfig({ 'core.listing.disableEmptyFilterOptions': true });
    const color = await TestDataService.createColorPropertyGroup(); //by default 3 colors
    const size = await TestDataService.createTextPropertyGroup(); //by default 3 sizes
    const propertyGroups: PropertyGroup[] = [];
    propertyGroups.push(color);
    propertyGroups.push(size);
    const colorManufacturer = await TestDataService.createBasicManufacturer({
        name: 'Color Manufacturer',
        description: 'Color Description Manufacturer',
    });
    const parentProduct = await TestDataService.createBasicProduct({ manufacturerId: colorManufacturer.id });

    await test.step('Verify property display on the product detail page', async () => {
        //creates 9 variants (3 colors ^ 3 sizes == 9 variants)
        const variantProducts = await TestDataService.createVariantProducts(parentProduct, propertyGroups, {
            description: 'Variant description',
        });
        await CheckVisibilityInHome(variantProducts.at(0).name);
        await ShopCustomer.goesTo(StorefrontProductDetail.url(variantProducts.at(0)));
        await ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeVisible();
        
        await ShopCustomer.expects(StorefrontProductDetail.page.getByText(color.name)).toBeVisible(); 
        
        //filter out which property radio buttons we need as there are 6 total
        const colorOptions = StorefrontProductDetail.propertyName(color.name);
        await ShopCustomer.expects(colorOptions.getByRole('radio')).toHaveCount(3);
    
        //selects Green from colorOptions group via keyboard and checks for visible focus
        await ShopCustomer.selectsValue(colorOptions, 'Green');
    });
});