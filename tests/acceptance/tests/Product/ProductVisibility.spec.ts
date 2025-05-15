import { test } from '@fixtures/AcceptanceTest';
import { Product } from '@shopware-ag/acceptance-test-suite';

test('Product is visible in listing and storefront search when set to "Visible".', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    DefaultSalesChannel,
    SearchForTerm,
    StorefrontSearchSuggest,
    IdProvider,
}) => {
    let product: Product;
    await test.step('Create a product with "Visible" visibility in the default sales channel.', async () => {
        product = await TestDataService.createBasicProduct({
            name: 'Product-' + await IdProvider.getIdPair().id,
            visibilities: [
                {
                    salesChannelId: DefaultSalesChannel.salesChannel.id,
                    visibility: 30,
                },
            ],
        });
    });

    await test.step('Verify the product appears in the Home category listing.', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        const productLocators = await StorefrontHome.getListingItemByProductName(product.name);
        await ShopCustomer.expects(productLocators.productName).toBeVisible();
    });

    await test.step('Verify the product appears in storefront search results.', async () => {
        await ShopCustomer.attemptsTo(SearchForTerm(product.name));
        const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();
        await ShopCustomer.expects(totalCount1).toBe(1);
    });

});

test('Product is visible in storefront search but hidden from listing when set to "Hide in listings".', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    DefaultSalesChannel,
    SearchForTerm,
    StorefrontSearchSuggest,
    IdProvider,
}) => {
    let product: Product;
    await test.step('Create a product with "Hide in listings" visibility in the default sales channel.', async () => {
        product = await TestDataService.createBasicProduct({
            name: 'Product-' + await IdProvider.getIdPair().id,
            visibilities: [
                {
                    salesChannelId: DefaultSalesChannel.salesChannel.id,
                    visibility: 20,
                },
            ],
        });
    });

    await test.step('Verify the product does not appear in the Home category listing.', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        const productLocators = await StorefrontHome.getListingItemByProductName(product.name);
        await ShopCustomer.expects(productLocators.productName).not.toBeVisible();
    });

    await test.step('Verify the product appears in storefront search results.', async () => {
        await ShopCustomer.attemptsTo(SearchForTerm(product.name));
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemName).toContainText(product.name);
        const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();
        await ShopCustomer.expects(totalCount1).toBe(1);
    });

});

test('Product is hidden from both listing and storefront search when set to "Hide in listings and search".', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    DefaultSalesChannel,
    SearchForTerm,
    StorefrontSearchSuggest,
    IdProvider,
    StorefrontProductDetail,
}) => {
    let product: Product;
    await test.step('Create a product with "Hide in listings and search" visibility in the default sales channel.', async () => {
        product = await TestDataService.createBasicProduct({
            name: 'Product-' + await IdProvider.getIdPair().id,
            visibilities: [
                {
                    salesChannelId: DefaultSalesChannel.salesChannel.id,
                    visibility: 10,
                },
            ],
        });
    });

    await test.step('Verify the product does not appear in the Home category listing.', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        const productLocators = await StorefrontHome.getListingItemByProductName(product.name);
        await ShopCustomer.expects(productLocators.productName).not.toBeVisible();
    });

    await test.step('Verify the product does not appear in storefront search results.', async () => {
        await ShopCustomer.attemptsTo(SearchForTerm(product.name));
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();
    });

    await test.step('Verify the product can still be accessed directly via its URL.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.page.locator('h1')).toContainText(product.name);
    });
});
