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
    StorefrontProductDetail,
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

    await ShopCustomer.expects(async () => {
        await test.step('Verify the product appears in the Home category listing.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productLocators = await StorefrontHome.getListingItemByProductName(product.name);
            await ShopCustomer.expects(productLocators.productName).toBeVisible();
        });
    }).toPass({
        intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
    });

    await test.step('Verify the product appears in storefront search results.', async () => {
        await ShopCustomer.attemptsTo(SearchForTerm(product.name));
        const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();

        // if we create other products in parallel - for example by using workers - we might find multiple results
        await ShopCustomer.expects(totalCount1).toBeGreaterThanOrEqual(1);
    });

    await test.step('Verify the product can be accessed directly via its URL.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.page.locator('h1')).toContainText(product.name);
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
    StorefrontProductDetail,
}) => {
    let product: Product;
    await test.step('Create a product with "Hide in listings" visibility in the default sales channel.', async () => {
        product = await TestDataService.createBasicProduct({
            name: 'Product-' + IdProvider.getIdPair().id,
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

    await ShopCustomer.expects(async () => {
        await test.step('Verify the product appears in storefront search results.', async () => {
            await ShopCustomer.attemptsTo(SearchForTerm(product.name));
            await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemName.getByText(product.name)).toBeVisible();
            const totalCount1 = await StorefrontSearchSuggest.getTotalSearchResultCount();

            // if we create other products in parallel - for example by using workers - we might find multiple results
            await ShopCustomer.expects(totalCount1).toBeGreaterThanOrEqual(1);
            await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemName.getByText(product.name)).toBeVisible();
        });
    }).toPass({
        intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
    });

    await test.step('Verify the product can be accessed directly via its URL.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.page.locator('h1')).toContainText(product.name);
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
            name: 'Product-' + IdProvider.getIdPair().id,
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
        // if we have other test products the result might not be empty but our product should not be in the list
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemName.getByText(product.name)).not.toBeVisible();
    });

    await test.step('Verify the product can still be accessed directly via its URL.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.page.locator('h1')).toContainText(product.name);
    });
});

test('Product is not visible without adding it to the sales channel.', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    SearchForTerm,
    StorefrontSearchSuggest,
    IdProvider,
    StorefrontProductDetail,
}) => {
    let product: Product;
    await test.step('Create a product without visibility in the default sales channel.', async () => {
        product = await TestDataService.createBasicProduct({
            name: 'Product-' + await IdProvider.getIdPair().id,
            visibilities: [],
        });
    });

    await test.step('Verify the product does not appear in the Home category listing.', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        const productLocators = await StorefrontHome.getListingItemByProductName(product.name);
        await ShopCustomer.expects(productLocators.productName).not.toBeVisible();
    });

    await test.step('Verify the product does not appear in storefront search results.', async () => {
        await ShopCustomer.attemptsTo(SearchForTerm(product.name));
        // if we have other test products the result might not be empty but our product should not be in the list
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestLineItemName.getByText(product.name)).not.toBeVisible();
    });

    await test.step('Verify the product can still be accessed directly via its URL.', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.page.locator('h1', { hasText: product.name })).not.toBeVisible();
    });
});