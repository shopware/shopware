import { getCurrencySymbolFromLocale, test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test(
    'Shop customers should be able to view products in different currencies.',
    { tag: ['@Currencies', '@Storefront'] },
    async ({ ShopCustomer, TestDataService, InstanceMeta, StorefrontHeader, StorefrontHome, ChangeStorefrontCurrency }) => {
        const salesChannelId = TestDataService.defaultSalesChannel.id;
        const currency = await TestDataService.createCurrency();
        await TestDataService.assignSalesChannelCurrency(salesChannelId, currency.id);
        const product = await TestDataService.createBasicProduct();
        const productListing = await StorefrontHome.getListingItemByProductName(product.name);

        await ShopCustomer.expects(async () => {
            await test.step('Customer can view currencies menu', async () => {
                await ShopCustomer.goesTo(StorefrontHome.url());
                const currencySymbol = getCurrencySymbolFromLocale();
                // eslint-disable-next-line playwright/no-conditional-in-test
                if (satisfies(InstanceMeta.version, '<6.7')) {
                    await ShopCustomer.expects(StorefrontHeader.currenciesDropdown).toContainText('Pound');
                }   
                else {
                    await ShopCustomer.expects(StorefrontHeader.currenciesDropdown).toContainText(currencySymbol);
                }
                await ShopCustomer.expects(productListing.productPrice).toContainText(currencySymbol);
            });
        }).toPass({
            intervals: [1_000, 2_500], // retry after 1 seconds, then every 2.5 seconds
        });

        await test.step('Customer can select a different currency', async () => {
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
                await StorefrontHeader.currenciesDropdown.click();
                await StorefrontHeader.currenciesMenuOptions.getByText(currency.symbol).click();
            }   
            else {
                await ShopCustomer.attemptsTo(ChangeStorefrontCurrency(currency.name));
            }
            await ShopCustomer.expects(StorefrontHeader.currenciesDropdown).toContainText(currency.name);
            await ShopCustomer.expects(productListing.productPrice).toContainText(currency.isoCode);
        });
    }
);