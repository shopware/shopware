import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to find a custom field by its label when I add it to an essential characteristics template.',
    { tag: '@Settings' },
    async ({ ShopAdmin, TestDataService, AdminApiContext, IdProvider }) => {
        const customFieldSet = await TestDataService.createCustomFieldSet({
            name: `cf_set_${IdProvider.getIdPair().id}`,
            relations: [{ id: IdProvider.getIdPair().uuid, entityName: 'product' }],
        });
        const label = `Shown label ${IdProvider.getIdPair().id}`;
        await TestDataService.createCustomField(customFieldSet.id, {
            name: `cf_tech_${IdProvider.getIdPair().id}`,
            type: 'text',
            config: { label: { 'en-GB': label } },
        });
        const decoyLabel = `Other ${IdProvider.getIdPair().uuid}`;
        await TestDataService.createCustomField(customFieldSet.id, {
            name: `cf_other_${IdProvider.getIdPair().id}`,
            type: 'text',
            config: { label: { 'en-GB': decoyLabel } },
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
        const names = rows.locator('.sw-data-grid__cell--name');
        await expect(names.first()).toHaveText(label);
        await expect(names.filter({ hasText: decoyLabel })).toHaveCount(0);

        await rows.first().getByRole('checkbox').check();
        const saveResponse = ShopAdmin.page.waitForResponse(`**/api/product-feature-set/${featureSetId}`);
        await modal.getByRole('button', { name: 'Add', exact: true }).click();
        expect((await saveResponse).ok()).toBeTruthy();

        await expect(ShopAdmin.page.locator('.sw-settings-product-feature-sets-values-card')).toContainText(label);
    },
);

test(
    'As a merchant, I want the custom fields whose label matches my search best listed first in an essential characteristics template.',
    { tag: '@Settings' },
    async ({ ShopAdmin, TestDataService, AdminApiContext, IdProvider }) => {
        const customFieldSet = await TestDataService.createCustomFieldSet({
            name: `cf_set_${IdProvider.getIdPair().id}`,
            relations: [{ id: IdProvider.getIdPair().uuid, entityName: 'product' }],
        });
        const token = IdProvider.getIdPair().uuid;
        const term = `Thickness ${token}`;
        const fields = [
            { prefix: 'cf_wall', type: 'text', label: `Wall Thickness ${token}` },
            { prefix: 'cf_grade', type: 'int', label: `Grade ${token}` },
            { prefix: 'cf_exact', type: 'bool', label: term },
            { prefix: 'cf_other', type: 'text', label: `Other ${IdProvider.getIdPair().uuid}` },
        ];
        for (const field of fields) {
            await TestDataService.createCustomField(customFieldSet.id, {
                name: `${field.prefix}_${IdProvider.getIdPair().id}`,
                type: field.type,
                config: { label: { 'en-GB': field.label } },
            });
        }

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
        await modal.locator('.sw-simple-search-field input').fill(term);
        await searchResponse;

        const rows = modal.locator('tbody.sw-data-grid__body:not(.sw-data-grid-skeleton) > tr.sw-data-grid__row');
        const names = rows.locator('.sw-data-grid__cell--name');
        await expect(names.nth(0)).toHaveText(term);
        await expect(names.nth(1)).toHaveText(`Wall Thickness ${token}`);
        await expect(names.filter({ hasText: `Grade ${token}` })).toHaveCount(1);
        await expect(names.filter({ hasText: fields[3].label })).toHaveCount(0);
    },
);
