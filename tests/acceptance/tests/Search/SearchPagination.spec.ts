import { test } from '@fixtures/AcceptanceTest';

test(
    'Search result pagination keeps the search term in its links',
    {
        tag: [
            '@Search',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, TestDataService, StorefrontSearch, IdProvider }) => {
        const productNameSuffix = IdProvider.getIdPair().uuid;

        await Promise.all([
            TestDataService.createBasicProduct({ name: `Bottle ${productNameSuffix} & Co` }),
            TestDataService.createBasicProduct({ name: `Bowl ${productNameSuffix} & Co` }),
        ]);

        await TestDataService.clearCaches();

        // The term contains characters that have a meaning in a query string
        const searchTerm = `${productNameSuffix} & Co`;

        await test.step('Customer sees the search term encoded in the link to the next page', async () => {
            // One product per page, so the two products need two pages
            await ShopCustomer.goesTo(`${StorefrontSearch.url(encodeURIComponent(searchTerm))}&limit=1`);

            const secondPageLink = StorefrontSearch.page.locator('.pagination .page-link[data-page="2"]').first();

            await ShopCustomer.expects(secondPageLink).toHaveAttribute(
                'href',
                `?p=2&search=${encodeURIComponent(searchTerm)}`,
            );
        });
    },
);
