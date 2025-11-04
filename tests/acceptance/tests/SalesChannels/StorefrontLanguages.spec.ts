import { getLanguageData, getSnippetSetId, test } from '@fixtures/AcceptanceTest';

test('Shop customers should be able to view products in different languages.', { tag: ['@Languages', '@Storefront'] }, async ({
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
            await ShopCustomer.expects(StorefrontHome.languagesDropdown).toContainText(/Englisch|English/);
            await ShopCustomer.expects(addToCartButton).toContainText('Add to shopping cart');
        });
    }).toPass({
        intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
    });

    await test.step('Customer can select a different language', async () => {
        await StorefrontHome.languagesDropdown.click();
        // if you run it with feature flag v6.8.0 enabled, the test instance is in english
        // @ToDo: Why is the test instance in english when the feature flag is enabled via .env file? Should also be in german.
        await StorefrontHome.languagesMenuOptions.getByText(/Deutsch|German/).click();
        await ShopCustomer.expects(StorefrontHome.languagesDropdown).toContainText(/Deutsch|German/);
        await ShopCustomer.expects(addToCartButton).toContainText('In den Warenkorb');
    });
});
