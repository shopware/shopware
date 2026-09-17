import { test, translate } from '@fixtures/AcceptanceTest';
import type { ProductCreationType, ProductFormData } from '@shopware-ag/acceptance-test-suite';

function buildProductData(uniqueId: string, creationType: ProductCreationType = 'physical'): ProductFormData {
    return {
        name: `Test product - ${uniqueId}`,
        productNumber: `SWATS-${uniqueId}`,
        grossPrice: '99.99',
        // A digital product has no stock field, it manages stock through a checkbox instead.
        stock: creationType === 'physical' ? '25' : undefined,
    };
}

test(
    'Shop administrator should be able to create a product via the administration.',
    { tag: '@Product' },
    async ({
        ShopAdmin,
        IdProvider,
        TestDataService,
        AdminProductListing,
        AdminProductCreate,
        AdminProductDetail,
        FillProductBaseData,
        SaveProduct,
    }) => {
        const { id: uniqueId } = IdProvider.getIdPair();
        const productData = buildProductData(uniqueId);

        await test.step('Open the product creation form from the product listing', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url());
            await AdminProductListing.addProductButton.click();

            await ShopAdmin.expects(AdminProductCreate.page).toHaveURL(/#\/sw\/product\/create\/base/);
        });

        await test.step('Create the product and validate its saved data', async () => {
            await ShopAdmin.attemptsTo(FillProductBaseData(productData));
            await ShopAdmin.attemptsTo(SaveProduct());

            await AdminProductDetail.page.waitForURL(/#\/sw\/product\/detail\/[0-9a-f]{32}\//);
            TestDataService.addCreatedRecord('product', AdminProductDetail.page.url().split('/detail/')[1].split('/')[0]);

            await ShopAdmin.expects(AdminProductDetail.productHeadline).toHaveText(productData.name);
            await ShopAdmin.expects(AdminProductDetail.priceGrossInput).toHaveValue(productData.grossPrice);
            await ShopAdmin.expects(AdminProductDetail.stockInput).toHaveValue(productData.stock);
        });

        await test.step('Validate that the new product is listed', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([productData.productNumber]));
            const productRow = await AdminProductListing.getProductRow(productData.productNumber);

            await ShopAdmin.expects(productRow.productName).toHaveText(productData.name);
        });
    },
);

test(
    'Shop administrator should be able to create a digital product via the administration.',
    { tag: '@Product' },
    async ({
        ShopAdmin,
        IdProvider,
        TestDataService,
        AdminProductListing,
        AdminProductCreate,
        AdminProductDetail,
        FillProductBaseData,
        UploadProductDownloadFile,
        SaveProduct,
    }) => {
        const { id: uniqueId } = IdProvider.getIdPair();
        const productData = buildProductData(uniqueId, 'digital');
        const fileName = `ats-download-${uniqueId}`;

        await test.step('Open the digital product creation form from the product listing', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url());
            await AdminProductListing.addProductContextMenuButton.click();
            await AdminProductListing.addDigitalProductLink.click();

            await ShopAdmin.expects(AdminProductCreate.page).toHaveURL(/#\/sw\/product\/create\/base\?creationType=digital/);
        });

        await test.step('Create the digital product and validate its file', async () => {
            await ShopAdmin.attemptsTo(FillProductBaseData(productData));
            // A digital product cannot be saved without at least one file.
            await ShopAdmin.attemptsTo(UploadProductDownloadFile(fileName));
            await ShopAdmin.attemptsTo(SaveProduct());

            await AdminProductDetail.page.waitForURL(/#\/sw\/product\/detail\/[0-9a-f]{32}\//);
            TestDataService.addCreatedRecord('product', AdminProductDetail.page.url().split('/detail/')[1].split('/')[0]);

            await ShopAdmin.expects(AdminProductDetail.productHeadline).toHaveText(productData.name);
            await ShopAdmin.expects(AdminProductDetail.downloadFileName).toHaveText(`${fileName}.txt`);
        });

        await test.step('Validate that the new product is listed as digital', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([productData.productNumber]));
            const productRow = await AdminProductListing.getProductRow(productData.productNumber);

            // The row is already identified by its unique product number and the name is validated
            // on the detail page, so the listing only has to prove the product is marked as digital.
            await ShopAdmin.expects(productRow.productDigitalIndicator).toHaveText(
                translate('administration:product:listing.digitalIndicator'),
            );
        });
    },
);

test(
    'Shop administrator should be able to duplicate a product while creating it.',
    { tag: '@Product' },
    async ({
        ShopAdmin,
        IdProvider,
        AdminProductListing,
        AdminProductCreate,
        AdminProductDetail,
        FillProductBaseData,
        SaveAndDuplicateProduct,
    }) => {
        const { id: uniqueId } = IdProvider.getIdPair();
        const productData = buildProductData(uniqueId);
        const duplicateName = `${productData.name} ${translate('administration:product:detail.copySuffix')}`;

        await test.step('Create the product via "Save and duplicate"', async () => {
            await ShopAdmin.goesTo(AdminProductCreate.url());
            await ShopAdmin.attemptsTo(FillProductBaseData(productData));
            await ShopAdmin.attemptsTo(SaveAndDuplicateProduct());
        });

        await test.step('Validate that the duplicate was opened', async () => {
            await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(duplicateName);
            await ShopAdmin.expects(AdminProductDetail.productNumberInput).not.toHaveValue(productData.productNumber);
            await ShopAdmin.expects(AdminProductDetail.priceGrossInput).toHaveValue(productData.grossPrice);
        });

        await test.step('Validate that the original product was saved as well', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([productData.productNumber]));
            const productRow = await AdminProductListing.getProductRow(productData.productNumber);

            await ShopAdmin.expects(productRow.productName).toHaveText(productData.name);
        });
    },
);

test(
    'Shop administrator should be able to duplicate an existing product.',
    {
        tag: '@Product',
        annotation: {
            type: 'issue',
            description: 'https://github.com/shopware/shopware/issues/20528',
        },
    },
    async ({ ShopAdmin, TestDataService, AdminProductDetail, SaveAndDuplicateProduct }) => {
        test.fixme(
            true,
            'Duplicating an existing product fails with a write constraint violation on "childCount", see https://github.com/shopware/shopware/issues/20528. Fixed by https://github.com/shopware/shopware/pull/20575.',
        );

        const product = await TestDataService.createBasicProduct();
        const duplicateName = `${product.name} ${translate('administration:product:detail.copySuffix')}`;

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        await ShopAdmin.attemptsTo(SaveAndDuplicateProduct());

        await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(duplicateName);
        await ShopAdmin.expects(AdminProductDetail.productNumberInput).not.toHaveValue(product.productNumber);
    },
);
