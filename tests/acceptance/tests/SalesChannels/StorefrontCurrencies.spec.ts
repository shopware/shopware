import { getCurrencySymbolFromLocale, test } from '@fixtures/AcceptanceTest';

test(
    'Shop customers should be able to view products in different currencies.',
    { tag: ['@Currencies', '@Storefront'] },
    async ({ ShopCustomer, TestDataService, StorefrontHome }) => {
        const salesChannelId = TestDataService.defaultSalesChannel.id;
        const currency = await TestDataService.createCurrency();
        await TestDataService.assignSalesChannelCurrency(salesChannelId, currency.id);
        const product = await TestDataService.createBasicProduct();
        const productListing = await StorefrontHome.getListingItemByProductName(product.name);

        await ShopCustomer.expects(async () => {
            await test.step('Customer can view currencies menu', async () => {
                await ShopCustomer.goesTo(StorefrontHome.url());
                const currencySymbol = getCurrencySymbolFromLocale();
                await ShopCustomer.expects(StorefrontHome.currenciesDropdown).toContainText(currencySymbol);
                await ShopCustomer.expects(productListing.productPrice).toContainText(currencySymbol);
            });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });

        await test.step('Customer can select a different currency', async () => {
            await StorefrontHome.currenciesDropdown.click();
            await StorefrontHome.currenciesMenuOptions.getByText(currency.symbol).click();
            await ShopCustomer.expects(StorefrontHome.currenciesDropdown).toContainText(currency.name);
            await ShopCustomer.expects(productListing.productPrice).toContainText(currency.isoCode);
        });
    }
);
