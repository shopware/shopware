import { getLanguageData, getSnippetSetId, test } from '@fixtures/AcceptanceTest';

test('Shop customers should be able to view products in different languages.', { tag: '@Languages' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
}) => {
    const product = await TestDataService.createBasicProduct();

    const salesChannelId = TestDataService.defaultSalesChannel.id;
    const language = await getLanguageData('de-DE', TestDataService.AdminApiClient);
    const snippetSetId = await getSnippetSetId('de-DE', TestDataService.AdminApiClient);

    await TestDataService.assignSalesChannelLanguage(salesChannelId, language.id);
    await TestDataService.createSalesChannelDomain({ languageId: language.id, snippetSetId: snippetSetId });

    await TestDataService.clearCaches();

    const productListing = StorefrontHome.productListItems.filter({ has: StorefrontHome.page.getByRole('link', { name: product.name }) });
    const addToCartButton = productListing.filter({ has: StorefrontHome.page.getByRole('button') });

    await ShopCustomer.expects(async () => {
        await test.step('Customer can view languages menu', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            await ShopCustomer.expects(StorefrontHome.languagesDropdown).toContainText('English');
            await ShopCustomer.expects(addToCartButton).toContainText('Add to shopping cart');
        });
    }).toPass({
        intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
    });

    await test.step('Customer can select a different language', async () => {
        await StorefrontHome.languagesDropdown.click();
        await StorefrontHome.languagesMenuOptions.getByText('Deutsch').click();
        await ShopCustomer.expects(StorefrontHome.languagesDropdown).toContainText('Deutsch');
        await ShopCustomer.expects(addToCartButton).toContainText('In den Warenkorb');
    });
})
