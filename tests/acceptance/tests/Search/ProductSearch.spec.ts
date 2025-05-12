import { test } from '@fixtures/AcceptanceTest';

test('Customer is able to search products in shop', { tag: '@Search' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    StorefrontSearchSuggest,
    SearchForTerm,
    IdProvider,
    InstanceMeta,
}) => {
        const productNameSuffix1 = IdProvider.getIdPair().uuid;
        await TestDataService.createBasicProduct({
            name: `Bottle${productNameSuffix1}`,
        });
        await TestDataService.createBasicProduct({
            name: `Bowl${productNameSuffix1}`,
        });

        await test.step('Customer searches with an invalid input and sees no results', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await ShopCustomer.attemptsTo(SearchForTerm('thisShouldNotFindAnything'));
            await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();
        });

        await test.step('Customer searches term and sees a single matching product', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(`Bottle${productNameSuffix1}`));
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (InstanceMeta.isSaaS) {
                let productFound = false;
                for (const lineItem of await StorefrontSearchSuggest.searchSuggestLineItemName.all()) {
                    const lineItemText = await lineItem.textContent();
                    // eslint-disable-next-line playwright/no-conditional-in-test
                    if (lineItemText.includes(`Bottle${productNameSuffix1}`)) {
                        productFound = true;
                        break;
                    }
                }
                ShopCustomer.expects(productFound).toBe(true);
            } else {
                const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();
                await ShopCustomer.expects(totalCount1).toBe(1);
            }
        });

        await test.step('Customer searches for a partial term and sees multiple matching products', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(productNameSuffix1));
            const totalCount2 = await StorefrontSearchSuggest.getTotalSearchResultCount();
            await ShopCustomer.expects(totalCount2).toBe(2);
        });

        await test.step('Customer navigates to the results page to view all matching products', async () => {
            await StorefrontSearchSuggest.searchSuggestTotalLink.click();
            await ShopCustomer.expects(StorefrontSearchSuggest.searchHeadline).toContainText(productNameSuffix1);
            const listedItemsCount = await StorefrontSearchSuggest.productListItems.count();
            await ShopCustomer.expects(listedItemsCount).toBe(2);
        });
    }
);
