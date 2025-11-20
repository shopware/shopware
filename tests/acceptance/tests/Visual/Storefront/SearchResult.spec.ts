import { test, expect, getCurrencyCodeFromLocale, getLocale } from '@fixtures/AcceptanceTest';

test(
    'Creates a screenshot of the Storefront Search Result Page.',
    { tag: '@Visual' },
    async ({
        ShopCustomer,
        TestDataService,
        StorefrontHome,
        SearchForTerm,
        StorefrontSearchSuggest,
        CheckVisibilityInHome,
        SalesChannelBaseConfig,
    }) => {
        const currency = await TestDataService.getCurrency(getCurrencyCodeFromLocale());
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
                {
                    currencyId: SalesChannelBaseConfig.defaultCurrencyId,
                    gross: 10,
                    linked: false,
                    net: 8.4,
                },
            ],
        });
        await TestDataService.setSystemConfig({ 'core.basicInformation.useDefaultCookieConsent': false });

        await test.step('Search with valid input and sees results and take a screenshot.', async () => {
            await ShopCustomer.goesTo(StorefrontHome.url());
            await CheckVisibilityInHome(product.name);
            await ShopCustomer.attemptsTo(SearchForTerm(product.name));
            await ShopCustomer.expects(StorefrontSearchSuggest.searchSuggestTotalLink).toBeVisible();
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
    }
);
