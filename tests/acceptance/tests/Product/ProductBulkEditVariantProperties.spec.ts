import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to add property options to variants in bulk without losing the properties they already inherit.',
    { tag: ['@Product', '@Variant'] },
    async ({ TestDataService, ShopAdmin, AdminProductDetail, AdminProductBulkEdit, AdminApiContext }) => {
        test.slow();

        const inheritedGroup = await TestDataService.createColorPropertyGroup();
        const addedGroup = await TestDataService.createTextPropertyGroup();
        const inheritedOptions = (await TestDataService.getPropertyGroupOptions(inheritedGroup.id)).slice(0, 2);
        const addedOptions = await TestDataService.getPropertyGroupOptions(addedGroup.id);
        const optionToAdd = addedOptions[0];

        const parentProduct = await TestDataService.createBasicProduct({
            properties: inheritedOptions.map((option) => ({ id: option.id })),
        });
        const variantGroup = await TestDataService.createTextPropertyGroup();
        const variants = await TestDataService.createVariantProducts(parentProduct, [variantGroup]);

        await test.step('Open the bulk edit for all variants.', async () => {
            await ShopAdmin.goesTo(AdminProductDetail.url(parentProduct.id));
            await AdminProductDetail.variantsTabLink.click();

            const variantsOverview = ShopAdmin.page.locator('.sw-product-variants-overview');
            await variantsOverview
                .locator('.sw-data-grid__cell--header.sw-data-grid__cell--selection')
                .getByRole('checkbox')
                .check();
            await variantsOverview.locator('.sw-data-grid__bulk').getByRole('link', { name: 'Bulk edit' }).click();
            await ShopAdmin.page
                .locator('.sw-product-variants-overview__bulk-edit-modal')
                .getByRole('button', { name: 'Start bulk edit' })
                .click();
        });

        await test.step('Add a property option to the variants.', async () => {
            const propertiesField = ShopAdmin.page.locator('.sw-bulk-edit-change-field-properties');
            await propertiesField.getByRole('checkbox').first().check();
            await propertiesField.locator('.sw-bulk-edit-change-type__selection .sw-single-select__selection-text').click();
            await ShopAdmin.page.locator('.sw-select-result', { hasText: 'Add' }).click();
            await propertiesField.locator('.sw-inheritance-switch--is-inherited .mt-icon').click();
            await propertiesField.getByRole('button', { name: 'Configure properties' }).click();

            const addPropertiesModal = ShopAdmin.page.locator('.sw-product-add-properties-modal');
            await addPropertiesModal.locator('.sw-product-add-properties-modal__search-field input').fill(addedGroup.name);
            await addPropertiesModal
                .locator('.sw-property-search__search-selection__option_grid')
                .getByRole('row', { name: `${addedGroup.name} / ${optionToAdd.name}` })
                .getByRole('checkbox')
                .check();
            await addPropertiesModal.locator('.sw-product-add-properties-modal__button-save').click();
            await ShopAdmin.expects(propertiesField.locator('.sw-product-properties__list')).toContainText(optionToAdd.name);
        });

        await test.step('Apply the bulk edit.', async () => {
            await AdminProductBulkEdit.applyChangesButton.click();
            await AdminProductBulkEdit.confirmModalApplyChangesButton.click();
            await ShopAdmin.expects(AdminProductBulkEdit.confirmModalSuccessHeader).toBeVisible();
        });

        await test.step('Confirm that every variant keeps its inherited properties and has the added one.', async () => {
            for (const variant of variants) {
                const response = await AdminApiContext.post('search/product', {
                    data: {
                        ids: [variant.id],
                        associations: { properties: {} },
                    },
                });
                expect(response.ok()).toBeTruthy();
                const { data } = await response.json();
                const optionIds = (data[0].properties ?? []).map((option: { id: string }) => option.id);

                expect(optionIds).toContain(optionToAdd.id);
                for (const inherited of inheritedOptions) {
                    expect(optionIds).toContain(inherited.id);
                }
            }
        });
    },
);
