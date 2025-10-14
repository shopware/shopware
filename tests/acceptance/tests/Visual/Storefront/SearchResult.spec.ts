import { test, expect } from '@fixtures/AcceptanceTest';
import { replaceElements } from '@shopware-ag/acceptance-test-suite';

test('Creates a screenshot of the Storefront Homepage.', { tag: '@Visual' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
    SearchForTerm,
    StorefrontSearchSuggest,

}) => {
    const currency = await TestDataService.getCurrency('EUR');
    const product = await TestDataService.createBasicProduct({
        name: 'Test Product1',
        productNumber: 'TEST-123',
        description: null,
        stock: 10,
        price: [
            {
                currencyId: currency.id,
                gross: 10,
                linked: false,
                net: 8.4,
            },
        ],
    });
    await TestDataService.clearCaches();
    await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });
    
    await ShopCustomer.expects(async () => {
        await test.step('Wait for products to be visible.', async () => {
            await ShopCustomer.goesTo(`${StorefrontHome.url()}?a=${Date.now()}`);
            const productLocator1 = await StorefrontHome.getListingItemByProductName(product.name);
            await ShopCustomer.expects(productLocator1.productName).toBeVisible();
        });
    }).toPass({
        intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
    });

    await test.step('Search with valid input and sees results', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.attemptsTo(SearchForTerm(product.name));
        await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestNoResult).toBeVisible();
        await expect(StorefrontHome.page).toHaveScreenshot('Search-Result-Dropdown.png', {
            fullPage: true,
        });
    });

    await test.step('Navigate to the results page to and take a screenshot.', async () => {
        await StorefrontSearchSuggest.searchSuggestTotalLink.click();
        await ShopCustomer.expects(StorefrontSearchSuggest.searchHeadline).toContainText(product.name);
        await expect(StorefrontSearchSuggest.page).toHaveScreenshot('Search-Result-Page.png', {
            fullPage: true,
        });
    });

});
