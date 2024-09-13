import { test } from '@fixtures/AcceptanceTest';

test('Customer gets a special product price depending on the amount of products bought.', {
    tag: ['@Product', '@Checkout'],
}, async ({
    ShopCustomer,
    TestDataService,
    StorefrontCheckoutCart,
    StorefrontProductDetail,
    StorefrontHome,
    AddProductToCart,
    ChangeProductQuantity,
}) => {
    const product = await TestDataService.createProductWithPriceRange();

    await test.step('Testing price ranges are available on product detail page @product', async () => {
        await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('th').nth(0)).toContainText('Quantity');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('th').nth(1)).toContainText('Unit price');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('th').nth(2)).toContainText('To 10');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('td').nth(0)).toContainText('€100.00*');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('th').nth(3)).toContainText('To 20');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('td').nth(1)).toContainText('€90.00*');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('th').nth(4)).toContainText('To 50');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('td').nth(2)).toContainText('€80.00*');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('th').nth(5)).toContainText('From 51');
        await ShopCustomer.expects(StorefrontProductDetail.productPriceRangesRow.locator('td').nth(3)).toContainText('€70.00*');
    });

    await test.step('Testing product with price range (1-10) @product', async () => {
        await ShopCustomer.attemptsTo(AddProductToCart(product, '10'));
        await ShopCustomer.goesTo(StorefrontCheckoutCart.url());
        await ShopCustomer.expects(StorefrontCheckoutCart.unitPriceInfo).toContainText('€100.00*');
    });

    await test.step('Testing product with price range (11-20) @product', async () => {
        await ShopCustomer.attemptsTo(ChangeProductQuantity('11'));
        await ShopCustomer.expects(StorefrontCheckoutCart.unitPriceInfo).toContainText('€90.00*');
    });

    await test.step('Testing product with price range (21-50) @product', async () => {
        await ShopCustomer.attemptsTo(ChangeProductQuantity('50'));
        await ShopCustomer.expects(StorefrontCheckoutCart.unitPriceInfo).toContainText('€80.00*');
    });

    await test.step('Testing product last price range (51-infinity) @product', async () => {
        await ShopCustomer.attemptsTo(ChangeProductQuantity('51'));
        await ShopCustomer.expects(StorefrontCheckoutCart.unitPriceInfo).toContainText('€70.00*');
    });

    await test.step('Testing product listing contains cheapest price @product', async () => {
        await ShopCustomer.goesTo(StorefrontHome.url());
        await ShopCustomer.expects(StorefrontHome.productListItems
            .filter({ hasText: product.name })
            .locator('.product-price-wrapper')
        ).toHaveText('From €70.00*');
        await ShopCustomer.expects(StorefrontHome.productListItems
            .filter({ hasText: product.name })
            .getByTitle('Details')
        ).toBeVisible();
    });
});
