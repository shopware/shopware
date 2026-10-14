import { test } from '@fixtures/AcceptanceTest';

test(
    'Shop administrator should be able to update a product in the administration.',
    { tag: '@Product' },
    async ({
        ShopAdmin,
        IdProvider,
        TestDataService,
        DefaultSalesChannel,
        AdminProductListing,
        AdminProductDetail,
        UploadImage,
        SaveProduct,
    }) => {
        const { id: uniqueId } = IdProvider.getIdPair();
        const initialDescription = `Description of ${uniqueId}`;
        const category = await TestDataService.createCategory();
        // The product starts without assignments so that both of them are made through the administration.
        const product = await TestDataService.createBasicProduct({
            // The Meteor text editor only edits markup it supports, a plain text description locks it.
            description: `<p>${initialDescription}</p>`,
            stock: 10,
            categories: [],
            visibilities: [],
        });

        const imageName = `ats-image-${uniqueId}`;
        const updatedDescription = `Updated description of ${product.name}`;
        const updatedGrossPrice = '149.99';
        const updatedStock = '77';

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));

        await test.step('Update description, price and stock', async () => {
            // The editor sets its content once the product is loaded, which would overwrite earlier input.
            await ShopAdmin.expects(AdminProductDetail.descriptionEditor).toHaveText(initialDescription);
            // Filled directly: the Meteor text editor has no visible focus indicator, which fails the a11y check of fillsIn.
            await AdminProductDetail.descriptionEditor.fill(updatedDescription);
            await ShopAdmin.expects(AdminProductDetail.descriptionEditor).toHaveText(updatedDescription);
            await ShopAdmin.fillsIn(AdminProductDetail.priceGrossInput, updatedGrossPrice);
            await ShopAdmin.fillsIn(AdminProductDetail.stockInput, updatedStock);
        });

        await test.step('Assign a category and a Sales Channel', async () => {
            await ShopAdmin.fillsIn(AdminProductDetail.categoriesSearchInput, category.name);
            await AdminProductDetail.categorySearchResult(category.name).click();
            // The category tree keeps its result popover open, which would cover the Sales Channel
            // select. Escape would cancel the whole edit, and a click can land on the popover itself
            // when the category list is long, so it is closed with Tab like an outside click.
            await AdminProductDetail.categoriesSearchInput.press('Tab');
            await ShopAdmin.expects(
                AdminProductDetail.page.locator('.sw-category-tree-field__results_popover'),
            ).toBeHidden();
            await AdminProductDetail.saleChannelsSearchInput.click();

            await ShopAdmin.fillsIn(AdminProductDetail.saleChannelsSearchInput, DefaultSalesChannel.salesChannel.name);
            await AdminProductDetail.saleChannelSearchResult(DefaultSalesChannel.salesChannel.name).click();
        });

        await test.step('Upload a product image', async () => {
            await ShopAdmin.attemptsTo(UploadImage(imageName));
        });

        await test.step('Save the product and validate the updated data', async () => {
            await ShopAdmin.attemptsTo(SaveProduct());
            await AdminProductDetail.page.reload();

            await ShopAdmin.expects(AdminProductDetail.descriptionEditor).toHaveText(updatedDescription);
            await ShopAdmin.expects(AdminProductDetail.priceGrossInput).toHaveValue(updatedGrossPrice);
            await ShopAdmin.expects(AdminProductDetail.stockInput).toHaveValue(updatedStock);
            await ShopAdmin.expects(AdminProductDetail.selectedCategory).toContainText(category.name);
            await ShopAdmin.expects(AdminProductDetail.selectedSaleChannel).toHaveText(
                DefaultSalesChannel.salesChannel.name,
            );
            await ShopAdmin.expects(AdminProductDetail.productImage.first()).toHaveAttribute('alt', imageName);
        });

        await test.step('Validate the updated stock in the product listing', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([product.productNumber]));
            const productRow = await AdminProductListing.getProductRow(product.productNumber);

            await ShopAdmin.expects(productRow.productStock).toHaveText(updatedStock);
        });
    },
);
