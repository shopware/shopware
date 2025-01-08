import { test } from '@fixtures/AcceptanceTest';

test ('Shop customers should be able to view products in different currencies.', {tag: '@Currencies'}, async ({
    ShopCustomer,
    TestDataService,
    StorefrontHome,
}) => {

    const salesChannelId = TestDataService.defaultSalesChannel.id;
    const currency = await TestDataService.createCurrency();
    await TestDataService.assignSalesChannelCurrency(salesChannelId, currency.id);
    
    const product = await TestDataService.createBasicProduct();
    const productListing = await StorefrontHome.getListingItemByProductId(product.id);

    await test.step('Customer can view currencies menu', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.currenciesDropdown).toContainText('Euro');
        await ShopCustomer.expects(productListing.productPrice).toContainText('€');
    });

    await test.step('Customer can select a different currency', async () => {
        await StorefrontHome.currenciesDropdown.click();
        await StorefrontHome.currenciesMenuOptions.getByText(currency.symbol).click();
        await ShopCustomer.expects(StorefrontHome.currenciesDropdown).toContainText(currency.name);
        await ShopCustomer.expects(productListing.productPrice).toContainText(currency.isoCode);
    });
})