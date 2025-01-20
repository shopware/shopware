import { test } from '@fixtures/AcceptanceTest';

test ('Customer is able to search products in shop', {tag: '@Search'}, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    StorefrontSearchSuggest,
    SalesChannelBaseConfig,
}) => {
    const product = await TestDataService.createBasicProduct({
        name: 'Shopware',
        price: [{
            currencyId: SalesChannelBaseConfig.defaultCurrencyId,
            gross: 10.00,
            linked: false,
            net: 7.55,
        }],
    });
    const productListing = await StorefrontHome.getListingItemByProductId(product.id);


    await ShopCustomer.goesTo(StorefrontHome.url());

    await test.step('Customer tries search with invalid input', async () => {
        await StorefrontSearchSuggest.searchInput.fill('Sf');
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();
    });

    await test.step('Customer tries search with valid input', async () => {
        await StorefrontSearchSuggest.searchInput.fill('Sh');
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemName).toHaveText(product.name);
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemPrice).toContainText('€10.00*');
    
    });

    await test.step('Customer navigates to result page and wants to see all result', async () => {
        await StorefrontSearchSuggest.searchSuggestTotalLink.click();
        await ShopCustomer.expects(StorefrontSearchSuggest.searchHeadline).toContainText('Sh');
        await ShopCustomer.expects(productListing.productName).toHaveText(product.name);
        await ShopCustomer.expects(productListing.productPrice).toContainText('€10.00*');     
    });
});