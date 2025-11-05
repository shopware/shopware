import { getLanguageData, getSnippetSetId, test } from '@fixtures/AcceptanceTest';

test(
    'Shop customers should be able to view products in different languages.',
    { tag: ['@Languages', '@Storefront'] },
    async ({ ShopCustomer, TestDataService, StorefrontHeader, StorefrontHome }) => {
        const product = await TestDataService.createBasicProduct();

        const salesChannelId = TestDataService.defaultSalesChannel.id;
        const language = await getLanguageData(TestDataService.AdminApiClient, 'de-DE');
        const snippetSetId = await getSnippetSetId(TestDataService.AdminApiClient, 'de-DE');
        const languageDropdownDE = StorefrontHeader.page.locator('.top-bar-language').getByRole('button', { name: 'Deutsch' });
        const addToShoppingCartButtonDE = StorefrontHome.page.getByRole('listitem').filter({ hasText: product.name }).getByRole('button', { name: 'In den Warenkorb' });

        await TestDataService.assignSalesChannelLanguage(salesChannelId, language.id);
        await TestDataService.createSalesChannelDomain({ languageId: language.id, snippetSetId: snippetSetId });

        await TestDataService.clearCaches();

        const productListing = await StorefrontHome.getListingItemByProductName(product.name);

        await ShopCustomer.expects(async () => {
            await test.step('Customer can view languages menu', async () => {
                await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
                await ShopCustomer.expects(StorefrontHeader.languagesDropdown).toContainText('English');
                await ShopCustomer.expects(productListing.productAddToShoppingCart).toContainText('Add to shopping cart');
            });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });

        await test.step('Customer can select a different language', async () => {
            await ShopCustomer.presses(StorefrontHeader.languagesDropdown);
            await ShopCustomer.presses(StorefrontHeader.languagesMenuOptions.getByText('Deutsch'));
            await ShopCustomer.expects(languageDropdownDE).toBeVisible();
            await ShopCustomer.expects(addToShoppingCartButtonDE).toBeVisible();
    });
})
