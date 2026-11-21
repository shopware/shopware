import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to find a custom field by its label when I add it to an essential characteristics template.',
    { tag: '@Settings' },
    async ({ ShopAdmin, TestDataService, AdminApiContext, IdProvider }) => {
        const customFieldSet = await TestDataService.createCustomFieldSet({
            relations: [{ id: IdProvider.getIdPair().uuid, entityName: 'product' }],
        });
        const label = `Shown label ${IdProvider.getIdPair().id}`;
        await TestDataService.createCustomField(customFieldSet.id, {
            name: `cf_tech_${IdProvider.getIdPair().id}`,
            type: 'text',
            config: { label: { 'en-GB': label } },
        });
        await TestDataService.createCustomField(customFieldSet.id, {
            name: `cf_other_${IdProvider.getIdPair().id}`,
            type: 'text',
            config: { label: { 'en-GB': `Other label ${IdProvider.getIdPair().id}` } },
        });

        const featureSetId = IdProvider.getIdPair().uuid;
        const featureSetResponse = await AdminApiContext.post('./product-feature-set', {
            data: { id: featureSetId, name: `Template ${IdProvider.getIdPair().id}`, features: [] },
        });
        expect(featureSetResponse.ok()).toBeTruthy();
        TestDataService.addCreatedRecord('product_feature_set', featureSetId);

        await ShopAdmin.goesTo(`#/sw/settings/product/feature/sets/detail/${featureSetId}`);
        await ShopAdmin.page.getByRole('button', { name: 'Add field' }).click();

        const modal = ShopAdmin.page.locator('.sw-settings-product-feature-sets__modal');
        await modal.getByText('Custom field', { exact: true }).click();
        await modal.getByRole('button', { name: 'Next' }).click();

        const searchResponse = ShopAdmin.page.waitForResponse('**/api/search/custom-field');
        await modal.locator('.sw-simple-search-field input').fill(label);
        await searchResponse;

        const rows = modal.locator('tbody.sw-data-grid__body:not(.sw-data-grid-skeleton) > tr.sw-data-grid__row');
        await expect(rows).toHaveCount(1);
        await expect(rows.first()).toContainText(label);

        await rows.first().getByRole('checkbox').check();
        const saveResponse = ShopAdmin.page.waitForResponse(`**/api/product-feature-set/${featureSetId}`);
        await modal.getByRole('button', { name: 'Add', exact: true }).click();
        expect((await saveResponse).ok()).toBeTruthy();

        await expect(ShopAdmin.page.locator('.sw-settings-product-feature-sets-values-card')).toContainText(label);
    },
);
