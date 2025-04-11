import { test } from '@fixtures/AcceptanceTest';

test('Product should be visible in listing and storefront search if assigned to a sales channel.', { tag: '@ProductVisibility' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    DefaultSalesChannel,
    SearchForTerm,
    StorefrontSearchSuggest,
    IdProvider,
}) => {
    const product = await TestDataService.createBasicProduct({
        name: 'Product' + await IdProvider.getIdPair().uuid,
        visibilities: [
            {
                salesChannelId: DefaultSalesChannel.salesChannel.id,
                visibility: 30,
            },
        ],
    });

    await ShopCustomer.goesTo(StorefrontHome.url());
    const productLocators = await StorefrontHome.getListingItemByProductName(product.name);
    await ShopCustomer.expects(productLocators.productName).toBeVisible();

    await ShopCustomer.attemptsTo(SearchForTerm(product.name));
    const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();
    await ShopCustomer.expects(totalCount1).toBe(1);

});

test('Product should be visible in storefront search but not in listing if assigned to a sales channel.', { tag: '@ProductVisibility' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    DefaultSalesChannel,
    SearchForTerm,
    StorefrontSearchSuggest,
    IdProvider,
}) => {
    const product = await TestDataService.createBasicProduct({
        name: 'Product' + await IdProvider.getIdPair().uuid,
        visibilities: [
            {
                salesChannelId: DefaultSalesChannel.salesChannel.id,
                visibility: 20,
            },
        ],
    });

    await ShopCustomer.goesTo(StorefrontHome.url());
    const productLocators = await StorefrontHome.getListingItemByProductName(product.name);
    await ShopCustomer.expects(productLocators.productName).not.toBeVisible();

    await ShopCustomer.attemptsTo(SearchForTerm(product.name));
    const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();
    await ShopCustomer.expects(totalCount1).toBe(1);

});

test('Product should not be visible in listing and storefront search if assigned to a sales channel.', { tag: '@ProductVisibility' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    DefaultSalesChannel,
    SearchForTerm,
    StorefrontSearchSuggest,
    IdProvider,
}) => {
    const product = await TestDataService.createBasicProduct({
        name: 'Product' + await IdProvider.getIdPair().uuid,
        visibilities: [
            {
                salesChannelId: DefaultSalesChannel.salesChannel.id,
                visibility: 10,
            },
        ],
    });

    await ShopCustomer.goesTo(StorefrontHome.url());
    const productLocators = await StorefrontHome.getListingItemByProductName(product.name);
    await ShopCustomer.expects(productLocators.productName).not.toBeVisible();

    await ShopCustomer.attemptsTo(SearchForTerm(product.name));
    await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();

});
