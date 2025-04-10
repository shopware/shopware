import { test } from '@fixtures/AcceptanceTest';

test('Customer is able to search products in shop', { tag: '@Search' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    StorefrontSearchSuggest,
    SearchForTerm,
    IdProvider
}) => {
        const productNameSuffix1 = IdProvider.getIdPair().uuid;
        const productNameSuffix2 = IdProvider.getIdPair().uuid;
        const productNameSuffix3 = IdProvider.getIdPair().uuid;
        await TestDataService.createBasicProduct({
            name: `Bottle - ${productNameSuffix1}`,
        });
        await TestDataService.createBasicProduct({
            name: `Bowl - ${productNameSuffix2}`,
        });

        await test.step('Customer searches with an invalid input and sees no results', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await ShopCustomer.attemptsTo(SearchForTerm(productNameSuffix3));
            await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();
        });

        await test.step('Customer searches term and sees a single matching product', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(`Bowl - ${productNameSuffix2}`));
            const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();
            await ShopCustomer.expects(totalCount1).toBe(1);
        });

        await test.step('Customer searches for a partial term and sees multiple matching products', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(`Bottle - ${productNameSuffix1}` + ' ' + `Bowl - ${productNameSuffix2}`));
            const totalCount2 = await StorefrontSearchSuggest.getTotalSearchResultCount();
            await ShopCustomer.expects(totalCount2).toBeGreaterThanOrEqual(2);
        });

        await test.step('Customer navigates to the results page to view all matching products', async () => {
            await StorefrontSearchSuggest.searchSuggestTotalLink.click();
            await ShopCustomer.expects(StorefrontSearchSuggest.searchHeadline).toContainText(`Bottle - ${productNameSuffix1}` + ' ' + `Bowl - ${productNameSuffix2}`);
            const listedItemsCount = await StorefrontSearchSuggest.productListItems.count();
            await ShopCustomer.expects(listedItemsCount).toBeGreaterThanOrEqual(2);
        });
    }
);
