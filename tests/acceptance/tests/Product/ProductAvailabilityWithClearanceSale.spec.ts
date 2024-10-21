import { test, expect } from '@fixtures/AcceptanceTest';

test('Product should be added to the cart if stock:1 and clearance-sale:true.', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    AddProductToCart,
}) => {
    const product = await TestDataService.createBasicProduct({
        stock: 1,
        isCloseout: true,
    });

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product));
});

test('Product should not be added to the cart if stock:0 and clearance-sale:true.', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
}) => {
    const product = await TestDataService.createBasicProduct({
        stock: 0,
        isCloseout: true,
    });

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeHidden();
});

test('Product should be added to the cart if stock:0 and clearance-sale:false.', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    StorefrontProductDetail,
    AddProductToCart,
}) => {
    const product = await TestDataService.createBasicProduct({
        stock: 0,
        isCloseout: false,
    });

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product));
});

test('Product should be removed from existing cart if stock:0 and cleareance-sale is changed to true.', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    AdminApiContext,
    StorefrontProductDetail,
    AddProductToCart,
    StorefrontCheckoutCart,
}) => {
    const product = await TestDataService.createBasicProduct({
        stock: 0,
        isCloseout: false,
    });

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product));

    await test.step('Set the clearance sale to true.', async () => {
        const editProductResponse = await AdminApiContext.patch(`product/${product.id}`, {
            data: {
                isCloseout: true,
            },
        });

        expect(editProductResponse.ok()).toBeTruthy();
    });

    await ShopCustomer.goesTo(StorefrontCheckoutCart.url());
    await ShopCustomer.expects(StorefrontCheckoutCart.emptyCartAlert).toBeVisible();

});

test('Stock reached message should be displayed if stock is changed to 1 and clearance-sale:active after adding 2 products to the cart.', { tag: '@Product' }, async ({
    ShopCustomer,
    TestDataService,
    AdminApiContext,
    StorefrontProductDetail,
    StorefrontCheckoutCart,
    AddProductToCart,
}) => {
    const product = await TestDataService.createBasicProduct({
        stock: 2,
        isCloseout: true,
    });

    await ShopCustomer.goesTo(StorefrontProductDetail.url(product));
    await ShopCustomer.attemptsTo(AddProductToCart(product, '2'));

    await test.step('Set the stock of the product to 1.', async () => {
        const changeStockResponse = await AdminApiContext.patch(`product/${product.id}`, {
            data: {
                stock: 1,
            },
        });

        expect(changeStockResponse.ok()).toBeTruthy();
    });

    await ShopCustomer.goesTo(StorefrontCheckoutCart.url());

    await ShopCustomer.expects(StorefrontCheckoutCart.stockReachedAlert).toContainText(product.name);
    await ShopCustomer.expects(StorefrontCheckoutCart.grandTotalPrice).toHaveText('€10.00*');
});
