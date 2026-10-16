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

interface ProductNumberParts {
    prefix: string;
    counter: number;
}

// Splits a number range value like "SW10013" into its prefix and counter.
function splitProductNumber(productNumber: string): ProductNumberParts {
    const [, prefix, counter] = /^(\D*)(\d+)$/.exec(productNumber)!;

    return { prefix, counter: Number(counter) };
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
            if (productData.stock !== undefined) {
                await ShopAdmin.expects(AdminProductDetail.stockInput).toHaveValue(productData.stock);
            }
        });

        await test.step('Validate that the new product is listed', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([productData.productNumber!]));
            const productRow = await AdminProductListing.getProductRow(productData.productNumber!);

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

            // Before 6.7.7 the product type is passed as creation state.
            await ShopAdmin.expects(AdminProductCreate.page).toHaveURL(
                /#\/sw\/product\/create\/base\?(creationType=digital|creationStates=is-download)/,
            );
        });

        await test.step('Create the digital product and validate its file', async () => {
            await ShopAdmin.attemptsTo(FillProductBaseData(productData));
            // A digital product cannot be saved without at least one file.
            await ShopAdmin.attemptsTo(UploadProductDownloadFile(fileName));

            await ShopAdmin.expects(AdminProductDetail.downloadFileName).toHaveText(`${fileName}.txt`);
            await ShopAdmin.expects(AdminProductDetail.savePhysicalProductButton).toBeEnabled();
            await ShopAdmin.attemptsTo(SaveProduct());

            await AdminProductDetail.page.waitForURL(/#\/sw\/product\/detail\/[0-9a-f]{32}\//);
            TestDataService.addCreatedRecord('product', AdminProductDetail.page.url().split('/detail/')[1].split('/')[0]);

            await ShopAdmin.expects(AdminProductDetail.productHeadline).toHaveText(productData.name);
            await ShopAdmin.expects(AdminProductDetail.downloadFileName).toHaveText(`${fileName}.txt`);
        });

        await test.step('Validate that the new product is listed as digital', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([productData.productNumber!]));
            const productRow = await AdminProductListing.getProductRow(productData.productNumber!);

            await ShopAdmin.expects(productRow.productDigitalIndicator).toHaveText(
                translate('administration:product:listing.digitalIndicator'),
            );
        });
    },
);

test(
    'Shop administrator should be able to duplicate an existing product.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, AdminProductDetail, SaveAndDuplicateProduct }) => {
        const product = await TestDataService.createBasicProduct();
        const duplicateName = `${product.name} ${translate('administration:product:detail.copySuffix')}`;

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        await ShopAdmin.attemptsTo(SaveAndDuplicateProduct());

        await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(duplicateName);
        await ShopAdmin.expects(AdminProductDetail.productNumberInput).not.toHaveValue(product.productNumber);
    },
);

test(
    'Shop administrator should be able to duplicate a new created product.',
    { tag: '@Product' },
    async ({
        ShopAdmin,
        IdProvider,
        TestDataService,
        AdminProductListing,
        AdminProductCreate,
        AdminProductDetail,
        FillProductBaseData,
        SaveAndDuplicateProduct,
    }) => {
        const { id: uniqueId } = IdProvider.getIdPair();
        const productData: ProductFormData = { ...buildProductData(uniqueId), productNumber: undefined };
        const duplicateName = `${productData.name} ${translate('administration:product:detail.copySuffix')}`;
        let originalProductId: string;
        let previewProductNumber: ProductNumberParts;
        let originalProductNumber: string;
        let duplicateProductNumber: string;

        await test.step('Open the product creation form from the product listing', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url());
            await AdminProductListing.addProductButton.click();

            await ShopAdmin.expects(AdminProductCreate.page).toHaveURL(/#\/sw\/product\/create\/base/);
        });

        await test.step('Validate that a product number is suggested', async () => {
            await ShopAdmin.expects(AdminProductCreate.productNumberInput).toHaveValue(/^\D*\d+$/);
            previewProductNumber = splitProductNumber(await AdminProductCreate.productNumberInput.inputValue());
        });

        await test.step('Save the new product and duplicate it', async () => {
            await ShopAdmin.attemptsTo(FillProductBaseData(productData));

            // The original is saved first and then cloned, so the clone request carries its id.
            const cloneResponse = AdminProductDetail.page.waitForResponse((response) =>
                response.url().includes('/api/_action/clone/product/'),
            );
            await ShopAdmin.attemptsTo(SaveAndDuplicateProduct());
            originalProductId = (await cloneResponse).url().split('/').pop()!;
            TestDataService.addCreatedRecord('product', originalProductId);
        });

        await test.step('Validate that the duplicate is opened for editing', async () => {
            // Wait for the duplicate's detail page, not the original's.
            await ShopAdmin.expects(AdminProductDetail.page).toHaveURL(
                new RegExp(`#/sw/product/detail/(?!${originalProductId})[0-9a-f]{32}/`),
            );
            TestDataService.addCreatedRecord('product', AdminProductDetail.page.url().split('/detail/')[1].split('/')[0]);

            await ShopAdmin.expects(AdminProductDetail.productHeadline).toHaveText(duplicateName);
            await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(duplicateName);
            await ShopAdmin.expects(AdminProductDetail.nameInput).toBeEditable();
            await ShopAdmin.expects(AdminProductDetail.priceGrossInput).toHaveValue(productData.grossPrice);
            await ShopAdmin.expects(AdminProductDetail.stockInput).toHaveValue(productData.stock!);
            await ShopAdmin.expects(AdminProductDetail.productNumberInput).toHaveValue(/^\D*\d+$/);
            duplicateProductNumber = await AdminProductDetail.productNumberInput.inputValue();
        });

        await test.step('Validate that the original product is saved with a number from the number range', async () => {
            await ShopAdmin.goesTo(AdminProductDetail.url(originalProductId));
            await ShopAdmin.expects(AdminProductDetail.productHeadline).toHaveText(productData.name);

            originalProductNumber = await AdminProductDetail.productNumberInput.inputValue();
            const original = splitProductNumber(originalProductNumber);
            ShopAdmin.expects(original.prefix).toBe(previewProductNumber.prefix);
            ShopAdmin.expects(original.counter).toBeGreaterThanOrEqual(previewProductNumber.counter);
        });

        await test.step('Validate that the duplicate gets a later number from the number range', async () => {
            const original = splitProductNumber(originalProductNumber);
            const duplicate = splitProductNumber(duplicateProductNumber);

            ShopAdmin.expects(duplicate.prefix).toBe(original.prefix);
            ShopAdmin.expects(duplicate.counter).toBeGreaterThan(original.counter);
        });

        await test.step('Validate that the original product is listed with its number', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url([originalProductNumber]));
            const productRow = await AdminProductListing.getProductRow(originalProductNumber);

            await ShopAdmin.expects(productRow.productName).toHaveText(productData.name);
        });
    },
);
