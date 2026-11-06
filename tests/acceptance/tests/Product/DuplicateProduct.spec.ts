import { test } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to duplicate a product from the product listing.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, AdminProductListing, AdminProductDetail, DuplicateProduct }) => {
        const product = await TestDataService.createBasicProduct();

        await ShopAdmin.goesTo(AdminProductListing.url([product.productNumber]));
        await ShopAdmin.attemptsTo(DuplicateProduct(product.productNumber));

        await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(`${product.name} Copy`);
        await ShopAdmin.expects(AdminProductDetail.activeForAllSalesChannelsToggle).not.toBeChecked();
        await ShopAdmin.expects(AdminProductDetail.productNumberInput).not.toHaveValue(product.productNumber);
    },
);

test(
    'As a merchant, I want to save and duplicate a product from the product detail page.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, AdminProductDetail, SaveAndDuplicateProduct }) => {
        const product = await TestDataService.createBasicProduct();

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        await ShopAdmin.attemptsTo(SaveAndDuplicateProduct());

        await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(`${product.name} Copy`);
        await ShopAdmin.expects(AdminProductDetail.activeForAllSalesChannelsToggle).not.toBeChecked();
        await ShopAdmin.expects(AdminProductDetail.productNumberInput).not.toHaveValue(product.productNumber);
    },
);
