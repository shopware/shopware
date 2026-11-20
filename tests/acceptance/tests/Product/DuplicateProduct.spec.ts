import { test, translate } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to duplicate a product from the product listing.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, AdminApiContext, AdminProductListing, AdminProductDetail, DuplicateProduct }) => {
        const product = await TestDataService.createBasicProduct();

        await ShopAdmin.goesTo(AdminProductListing.url([product.productNumber]));
        await ShopAdmin.attemptsTo(DuplicateProduct(product.productNumber));

        // The clone opens on its own detail page, so the id in the URL must differ from the source.
        const duplicateId = ShopAdmin.page.url().split('/sw/product/detail/')[1]?.split('/')[0];
        ShopAdmin.expects(duplicateId).not.toBe(product.id);

        const duplicateName = `${product.name} ${translate('administration:product:detail.copySuffix')}`;
        await ShopAdmin.expects(AdminProductDetail.nameInput).toHaveValue(duplicateName);
        await ShopAdmin.expects(AdminProductDetail.activeForAllSalesChannelsToggle).not.toBeChecked();
        await ShopAdmin.expects(AdminProductDetail.productNumberInput).not.toHaveValue(product.productNumber);

        // The source product is left untouched by the clone.
        const originalResponse = await AdminApiContext.get(`./product/${product.id}?_response=detail`);
        const { data: original } = (await originalResponse.json()) as {
            data: { name: string; productNumber: string; active: boolean };
        };
        ShopAdmin.expects(original.name).toBe(product.name);
        ShopAdmin.expects(original.productNumber).toBe(product.productNumber);
        ShopAdmin.expects(original.active).toBe(true);

        // ...and it is still listed. The exact name matches the original, not the "<name> Copy" duplicate.
        await ShopAdmin.goesTo(AdminProductListing.url([product.productNumber]));
        await ShopAdmin.expects(ShopAdmin.page.getByRole('gridcell', { name: product.name, exact: true })).toBeVisible();
    },
);
