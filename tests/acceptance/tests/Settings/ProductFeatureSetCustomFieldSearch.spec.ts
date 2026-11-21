import { test, expect } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want to find a custom field by its label when I add it to an essential characteristics template.',
    { tag: '@Settings' },
    async ({ ShopAdmin, TestDataService, IdProvider }) => {
        const customFieldSet = await TestDataService.createCustomFieldSet({
            relations: [{ id: IdProvider.getIdPair().uuid, entityName: 'product' }],
        });
        const technicalName = `cf_tech_${IdProvider.getIdPair().id}`;
        const label = `Shown label ${IdProvider.getIdPair().id}`;
        await TestDataService.createCustomField(customFieldSet.id, {
            name: technicalName,
            type: 'text',
            config: { label: { 'en-GB': label }, customFieldType: 'text', customFieldPosition: 1 },
        });
        const templateName = `Template ${IdProvider.getIdPair().id}`;

        await ShopAdmin.goesTo('#/sw/settings/product/feature/sets/create');
        await ShopAdmin.page.getByLabel('Name').fill(templateName);
        await ShopAdmin.page.getByRole('button', { name: 'Add values' }).click();

        const modal = ShopAdmin.page.locator('.sw-settings-product-feature-sets__modal');
        await modal.getByText('Custom field', { exact: true }).click();
        await modal.getByRole('button', { name: 'Next' }).click();

        const searchResponse = ShopAdmin.page.waitForResponse('**/api/search/custom-field');
        await modal.locator('.sw-simple-search-field input').fill(label);
        await searchResponse;

        await expect(modal.getByText(label)).toBeVisible();

        await modal.locator('.sw-data-grid__row').filter({ hasText: label }).getByRole('checkbox').check();
        await modal.getByRole('button', { name: 'Add', exact: true }).click();
        await expect(ShopAdmin.page.getByText(label)).toBeVisible();

        const saveResponse = ShopAdmin.page.waitForResponse('**/api/product-feature-set');
        await ShopAdmin.page.getByRole('button', { name: 'Save' }).click();
        expect((await saveResponse).ok()).toBeTruthy();
    },
);
