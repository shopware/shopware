import { test } from '@fixtures/AcceptanceTest';

test ('Customer is able to search products in shop', {tag: '@Search'}, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    StorefrontSearchSuggest,
    SalesChannelBaseConfig,
}) => {
    const product1 = await TestDataService.createBasicProduct({
        name: 'Bottle',
        price: [{
            currencyId: SalesChannelBaseConfig.defaultCurrencyId,
            gross: 10.00,
            linked: false,
            net: 7.55,
        }],
    });
    const product2 = await TestDataService.createBasicProduct({
        name: 'Bowl',
        price: [{
            currencyId: SalesChannelBaseConfig.defaultCurrencyId,
            gross: 10.00,
            linked: false,
            net: 7.55,
        }],
    });

    await ShopCustomer.goesTo(StorefrontHome.url());

    await test.step('Customer tries search with invalid input', async () => {
        await StorefrontSearchSuggest.searchInput.fill('Be');
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();
    });

    await test.step('Customer tries search with valid input', async () => {
        await StorefrontSearchSuggest.searchInput.fill('Bo');

        const totalCount = await StorefrontSearchSuggest.getTotalSearchResultCount();
        await ShopCustomer.expects(totalCount).toBe(2);

        await StorefrontSearchSuggest.searchInput.fill('Bow');
        await ShopCustomer.expects(StorefrontSearchSuggest.searchResultTotal).toBeVisible();
        await ShopCustomer.expects(totalCount).toBe(1);

    
    });

    await test.step('Customer navigates to result page and wants to see all result', async () => {
        await StorefrontSearchSuggest.searchSuggestTotalLink.click();
        await ShopCustomer.expects(StorefrontSearchSuggest.searchHeadline).toContainText('Bow');

        const productListing2 = await StorefrontSearchSuggest.getListingItemByProductId(product2.id);
        await ShopCustomer.expects(productListing2.productName).toHaveText(product2.name);
        await ShopCustomer.expects(productListing2.productPrice).toContainText('€10.00*');     
    });
});