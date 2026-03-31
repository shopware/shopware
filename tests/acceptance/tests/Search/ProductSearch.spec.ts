import { test } from '@fixtures/AcceptanceTest';

test('Customer is able to search products in shop', { tag: ['@Search', '@Storefront'] }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    StorefrontSearchSuggest,
    SearchForTerm,
    IdProvider,
}) => {
    const productNameSuffix1 = IdProvider.getIdPair().uuid;
    const createBottle = TestDataService.createBasicProduct({
        name: `Bottle ${productNameSuffix1}`,
    });
    const createBowl = TestDataService.createBasicProduct({
        name: `Bowl ${productNameSuffix1}`,
    });

    await Promise.all([createBottle, createBowl]);

    await TestDataService.clearCaches();

    await test.step('Wait for products to be visible.', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        const productLocator1 = await StorefrontHome.getListingItemByProductName(`Bottle ${productNameSuffix1}`);
        await ShopCustomer.expects(productLocator1.productName).toBeVisible();
        const productLocator2 = await StorefrontHome.getListingItemByProductName(`Bowl ${productNameSuffix1}`);
        await ShopCustomer.expects(productLocator2.productName).toBeVisible();
    });

    await test.step('Customer searches with an invalid input and sees no results', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.attemptsTo(SearchForTerm('thisShouldNotFindAnything'));
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();
    });

    await test.step('Customer searches term and sees a single matching product', async () => {
        await ShopCustomer.attemptsTo(SearchForTerm(`Bottle`));

        const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();
        await ShopCustomer.expects(totalCount1).toBe(1);

        const lineItemText = await StorefrontSearchSuggest.searchSuggestLineItemName.first().textContent();
        ShopCustomer.expects(lineItemText).toContain(`Bottle ${productNameSuffix1}`);
    });

    await test.step('Customer searches for a partial term and sees multiple matching products', async () => {
        await ShopCustomer.attemptsTo(SearchForTerm(productNameSuffix1));
        const totalCount2 = await StorefrontSearchSuggest.getTotalSearchResultCount();
        await ShopCustomer.expects(totalCount2).toBe(2);
    });

    await test.step('Customer navigates to the results page to view all matching products', async () => {
        await ShopCustomer.presses(StorefrontSearchSuggest.searchSuggestTotalLink);
        await ShopCustomer.expects(StorefrontSearchSuggest.searchHeadline).toContainText(productNameSuffix1);
        const listedItemsCount = await StorefrontSearchSuggest.productListItems.count();
        await ShopCustomer.expects(listedItemsCount).toBe(2);
    });
}
);
