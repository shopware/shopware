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
        await TestDataService.createBasicProduct({
            name: `Product1 - ${productNameSuffix1}`,
        });
        await TestDataService.createBasicProduct({
            name: `Product2 - ${productNameSuffix2}`,
        });

        await test.step('Customer searches with an invalid input and sees no results', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await ShopCustomer.attemptsTo(SearchForTerm('thisProductDoesNotExist'));
            await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();
        });

        await test.step('Customer searches term and sees a single matching product', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(productNameSuffix2));
            const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();
            await ShopCustomer.expects(totalCount1).toBe(1);
        });

        await test.step('Customer searches for a partial term and sees multiple matching products', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(productNameSuffix1 + ' ' + productNameSuffix2));
            // todo: The test needs to have something to wait for in order to get the correct totalCount2. Option 1 is 2 lines and needs a new locator. Option 2 is 1 line, uses an existing locator, but is similar to the actual assertion.
            // option1:
            // await ShopCustomer.expects(StorefrontSearchSuggest.page.locator('.header-search-btn')).toBeDisabled();
            // await ShopCustomer.expects(StorefrontSearchSuggest.page.locator('.header-search-btn')).not.toBeDisabled();
            // option2:
            // await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemName).toHaveCount(2);
            const totalCount2 = await StorefrontSearchSuggest.getTotalSearchResultCount();
            await ShopCustomer.expects(totalCount2).toBe(2);
        });

        await test.step('Customer navigates to the results page to view all matching products', async () => {
            await StorefrontSearchSuggest.searchSuggestTotalLink.click();
            await ShopCustomer.expects(StorefrontSearchSuggest.searchHeadline).toContainText(productNameSuffix1 + ' ' + productNameSuffix2);
            const listedItemsCount = await StorefrontSearchSuggest.productListItems.count();
            await ShopCustomer.expects(listedItemsCount).toBe(2);
        });
    }
);
