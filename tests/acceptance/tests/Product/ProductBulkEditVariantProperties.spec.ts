import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to add property options to variants in bulk without losing the properties they already inherit.',
    { tag: ['@Product', '@Variant'] },
    async ({ TestDataService, ShopAdmin, AdminProductDetail, AdminApiContext }) => {
        test.slow();

        const inheritedGroup = await TestDataService.createColorPropertyGroup();
        const addedGroup = await TestDataService.createTextPropertyGroup();
        const inheritedOptions = await TestDataService.getPropertyGroupOptions(inheritedGroup.id);
        const addedOptions = await TestDataService.getPropertyGroupOptions(addedGroup.id);
        const optionToAdd = addedOptions[0];

        const parentProduct = await TestDataService.createBasicProduct({
            properties: inheritedOptions.slice(0, 2).map((option) => ({ id: option.id })),
        });
        const variantGroup = await TestDataService.createTextPropertyGroup();
        const variants = await TestDataService.createVariantProducts(parentProduct, [variantGroup]);

        await ShopAdmin.goesTo(AdminProductDetail.url(parentProduct.id));
        await ShopAdmin.page.getByRole('tab', { name: 'Variants' }).click();
        await ShopAdmin.page
            .locator('.sw-product-variants-overview .sw-data-grid__header .sw-field__checkbox input')
            .check();
        await ShopAdmin.page.locator('.sw-data-grid__bulk-selected .link', { hasText: 'Bulk edit' }).click();
        await ShopAdmin.page.locator('.sw-bulk-edit-modal .mt-button--primary').click();

        const propertiesField = ShopAdmin.page.locator('.sw-bulk-edit-change-field-properties');
        await propertiesField.locator('.sw-inheritance-switch').click();
        await propertiesField.locator('.sw-bulk-edit-change-field__change .mt-select').click();
        await ShopAdmin.page.getByRole('option', { name: 'Add' }).click();
        await propertiesField.getByRole('button', { name: 'Add property' }).click();
        await ShopAdmin.page.getByText(optionToAdd.name, { exact: true }).click();
        await ShopAdmin.page.locator('.sw-bulk-edit-product__save-action').click();
        await ShopAdmin.page.getByRole('button', { name: 'Apply changes' }).click();
        await ShopAdmin.page.locator('.sw-bulk-edit-save-modal-success').waitFor();

        for (const variant of variants) {
            const response = await AdminApiContext.post('search/product', {
                data: {
                    ids: [variant.id],
                    associations: { properties: {} },
                },
            });
            const { data } = await response.json();
            const optionIds = (data[0].properties ?? []).map((option: { id: string }) => option.id);

            expect(optionIds).toContain(optionToAdd.id);
            for (const inherited of inheritedOptions.slice(0, 2)) {
                expect(optionIds).toContain(inherited.id);
            }
        }
    },
);
