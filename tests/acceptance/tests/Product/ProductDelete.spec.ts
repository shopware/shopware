import { test } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should be able to delete a product in the administration.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, AdminProductListing, DeleteProduct }) => {
        const product = await TestDataService.createBasicProduct();

        await ShopAdmin.goesTo(AdminProductListing.url([product.productNumber]));
        const productRow = await AdminProductListing.getProductRow(product.productNumber);
        await ShopAdmin.expects(productRow.productName).toHaveText(product.name);

        await ShopAdmin.attemptsTo(DeleteProduct(product.productNumber));

        await ShopAdmin.expects(productRow.productName).toBeHidden();
    },
);

test(
    'Shop administrator should be able to delete a digital product in the administration.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, AdminProductListing, DeleteProduct }) => {
        const product = await TestDataService.createDigitalProduct();

        await ShopAdmin.goesTo(AdminProductListing.url([product.productNumber]));
        const productRow = await AdminProductListing.getProductRow(product.productNumber);
        await ShopAdmin.expects(productRow.productDigitalIndicator).toBeVisible();

        await ShopAdmin.attemptsTo(DeleteProduct(product.productNumber));

        await ShopAdmin.expects(productRow.productName).toBeHidden();
    },
);

test(
    'Shop administrator should be able to delete a duplicated product in the administration.',
    { tag: '@Product' },
    async ({
        ShopAdmin,
        IdProvider,
        AdminProductCreate,
        AdminProductDetail,
        AdminProductListing,
        FillProductBaseData,
        SaveAndDuplicateProduct,
        DeleteProduct,
    }) => {
        const { id: uniqueId } = IdProvider.getIdPair();
        const productData = {
            name: `Test product - ${uniqueId}`,
            productNumber: `SWATS-${uniqueId}`,
            grossPrice: '99.99',
            stock: '25',
        };

        await test.step('Create a product and duplicate it', async () => {
            await ShopAdmin.goesTo(AdminProductCreate.url());
            await ShopAdmin.attemptsTo(FillProductBaseData(productData));
            await ShopAdmin.attemptsTo(SaveAndDuplicateProduct());
        });

        // The duplicate is the product that is currently open, its number was taken from the number range.
        const duplicateNumber = await AdminProductDetail.productNumberInput.inputValue();

        await test.step('Delete the duplicate and validate that the original is kept', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([duplicateNumber]));
            await ShopAdmin.attemptsTo(DeleteProduct(duplicateNumber));

            await ShopAdmin.goesTo(AdminProductListing.url([productData.productNumber]));
            const originalRow = await AdminProductListing.getProductRow(productData.productNumber);

            await ShopAdmin.expects(originalRow.productName).toHaveText(productData.name);
        });
    },
);
