import { test, translate } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to duplicate a product from the product listing.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, AdminProductListing, AdminProductDetail, DuplicateProduct }) => {
        const product = await TestDataService.createBasicProduct();

        await ShopAdmin.goesTo(AdminProductListing.url([product.productNumber]));
        await ShopAdmin.attemptsTo(DuplicateProduct(product.productNumber));

        const duplicateName = `${product.name} ${translate('administration:product:detail.copySuffix')}`;
        await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(duplicateName);
        await ShopAdmin.expects(AdminProductDetail.activeForAllSalesChannelsToggle).not.toBeChecked();
        await ShopAdmin.expects(AdminProductDetail.productNumberInput).not.toHaveValue(product.productNumber);
    },
);
