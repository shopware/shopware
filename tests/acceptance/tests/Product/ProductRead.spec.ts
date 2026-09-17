import { test } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should be able to read the product data in the administration.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, DefaultSalesChannel, AdminProductListing, AdminProductDetail }) => {
        const category = await TestDataService.createCategory();
        const manufacturer = await TestDataService.createBasicManufacturer();
        const media = await TestDataService.createMediaPNG();

        const product = await TestDataService.createBasicProduct({
            manufacturerId: manufacturer.id,
            stock: 42,
            width: 60,
            height: 30,
            length: 120,
            weight: 5,
            categories: [{ id: category.id }],
        });
        await TestDataService.assignProductMedia(product.id, media.id);

        await test.step('Read the product data from the product listing', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([product.productNumber]));
            const productRow = await AdminProductListing.getProductRow(product.productNumber);

            await ShopAdmin.expects(productRow.productName).toHaveText(product.name);
            await ShopAdmin.expects(productRow.productNumber).toHaveText(product.productNumber);
            await ShopAdmin.expects(productRow.productManufacturer).toHaveText(manufacturer.name);
            await ShopAdmin.expects(productRow.productActive).toBeVisible();
            await ShopAdmin.expects(productRow.productStock).toHaveText(product.stock.toString());
        });

        await test.step('Read the general data from the product detail page', async () => {
            await ShopAdmin.goesTo(AdminProductDetail.url(product.id));

            await ShopAdmin.expects(AdminProductDetail.productHeadline).toHaveText(product.name);
            await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(product.name);
            await ShopAdmin.expects(AdminProductDetail.productNumberInput).toHaveValue(product.productNumber);
            await ShopAdmin.expects(AdminProductDetail.descriptionEditor).toContainText(product.description);
            await ShopAdmin.expects(AdminProductDetail.manufacturerDropdownText).toHaveText(manufacturer.name);
            await ShopAdmin.expects(AdminProductDetail.priceGrossInput).toHaveValue(product.price[0].gross.toString());
            await ShopAdmin.expects(AdminProductDetail.stockInput).toHaveValue(product.stock.toString());
        });

        await test.step('Read the assignments from the product detail page', async () => {
            await ShopAdmin.expects(AdminProductDetail.selectedSaleChannel).toHaveText(
                DefaultSalesChannel.salesChannel.name,
            );
            await ShopAdmin.expects(AdminProductDetail.selectedCategory).toContainText(category.name);
            // The uploaded image renders twice, as the cover preview and as a gallery item.
            await ShopAdmin.expects(AdminProductDetail.productImage.first()).toHaveAttribute('alt', media.alt);
        });

        await test.step('Read the measurements from the specifications tab', async () => {
            await AdminProductDetail.specificationsTabLink.click();

            await ShopAdmin.expects(AdminProductDetail.widthInput).toHaveValue('60');
            await ShopAdmin.expects(AdminProductDetail.heightInput).toHaveValue('30');
            await ShopAdmin.expects(AdminProductDetail.lengthInput).toHaveValue('120');
            await ShopAdmin.expects(AdminProductDetail.weightInput).toHaveValue('5');
        });
    },
);
