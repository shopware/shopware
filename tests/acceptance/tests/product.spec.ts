import { test, expect } from '../fixtures/acceptance-test';

test('Open admin settings', async ({ adminPage }) => {
    await adminPage.getByText('Settings').click();
});

// run multiple times to test stability
for (let i = 0; i < 10; ++i) {
    test(`Open product ${i}`, async ({product, adminPage}) => {
        await adminPage.getByText('Catalogues').click();
        await adminPage.getByRole('link', {name: 'Products'}).first().click();

        await adminPage.getByPlaceholder('Search products...').click();
        await expect(adminPage.locator('.sw-search-bar__footer')).toBeVisible();
        await adminPage.getByPlaceholder('Search products...').fill(product.name);
        await expect(adminPage.locator('.sw-search-bar__footer')).toBeHidden();
        await adminPage.getByRole('link', {name: product.name, exact: true}).click();
        await expect(adminPage.locator('h2').getByText(product.name, {exact: true})).toBeVisible();
    });
}

// run multiple times to test stability
for (let i = 0; i < 10; ++i) {
    test(`Open storefront user account ${i}`, async ({ storefrontPage }) => {
        await storefrontPage.getByLabel('Your account').click();

        await storefrontPage.getByRole('link', { name: 'Overview' }).click();

        await expect(storefrontPage.getByRole('heading', { name: 'Overview' })).toBeVisible();
        await expect(storefrontPage.getByRole('heading', { name: 'Overview' })).toHaveText('Overview');
    });
}
