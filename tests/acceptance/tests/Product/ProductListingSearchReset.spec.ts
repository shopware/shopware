import { test } from '@fixtures/AcceptanceTest';

test(
    'As a merchant, I want the product search to reset when I open the Products menu item again.',
    { tag: '@Product' },
    async ({ ShopAdmin, TestDataService, AdminProductListing, IdProvider }) => {
        const product = await TestDataService.createBasicProduct({ name: `SearchReset${IdProvider.getIdPair().id}` });
        await TestDataService.createBasicProduct();
        const searchField = AdminProductListing.page.getByRole('searchbox', { name: 'Search products...' });
        const productRows = AdminProductListing.productsTable.locator(
            '.sw-data-grid__body:not(.sw-data-grid-skeleton) > .sw-data-grid__row',
        );
        const productsMenuItem = AdminProductListing.page.locator(
            '.sw-admin-menu__item--sw-product .sw-admin-menu__navigation-link',
        );

        await test.step('Search for a product in the product listing.', async () => {
            await ShopAdmin.goesTo(AdminProductListing.url());
            await searchField.fill(product.name);
            await ShopAdmin.expects(AdminProductListing.page).toHaveURL(/term=[^&]/);
            await ShopAdmin.expects(productRows).toHaveCount(1);
            await ShopAdmin.expects(productRows).toContainText(product.name);
        });

        await test.step('Go back in the browser and expect a reset search.', async () => {
            await AdminProductListing.page.goBack();
            await ShopAdmin.expects(AdminProductListing.page).not.toHaveURL(/term=[^&]/);
            await ShopAdmin.expects(searchField).toHaveValue('');
            await ShopAdmin.expects(productRows.nth(1)).toBeVisible();
        });

        await test.step('Search for the product again.', async () => {
            await searchField.fill(product.name);
            await ShopAdmin.expects(AdminProductListing.page).toHaveURL(/term=[^&]/);
            await ShopAdmin.expects(productRows).toHaveCount(1);
        });

        await test.step('Open the Products menu item again and expect a reset search.', async () => {
            await productsMenuItem.click();
            await ShopAdmin.expects(AdminProductListing.page).not.toHaveURL(/term=[^&]/);
            await ShopAdmin.expects(searchField).toHaveValue('');
        });
    },
);
