import { test } from '@fixtures/AcceptanceTest';

test('Customer gets a special product price depending on the amount of products bought.', {
    tag: ['@Product', '@Checkout'],
}, async ({
    ShopCustomer,
    TestDataService,
    StorefrontCheckoutCart,
    StorefrontProductDetail,
    AddProductToCart,
}) => {
    const product = await TestDataService.createProductWithPriceRange();

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product, '12'));
    await ShopCustomer.goesTo(StorefrontCheckoutCart.url());
    await ShopCustomer.expects(StorefrontCheckoutCart.unitPriceInfo).toContainText('€90.00*');
});
