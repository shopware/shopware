import { test, expect } from '@fixtures/AcceptanceTest';

test('Product should be added to the cart if stock:1 and clearance-sale:true. @product @priority1', async ({
    ShopCustomer,
    ProductData,
    AdminApiContext,
    StorefrontProductDetail,
    AddProductToCart,
}) => {

    await test.step('Set the stock of the product to 1 and the clearance sale to true.', async () => {
        const productId = ProductData.id;
        const editProductResponse = await AdminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 1,
                isCloseout: true,
            },
        });

        expect(editProductResponse.ok()).toBeTruthy();
    });

    await ShopCustomer.goesTo(StorefrontProductDetail);
    await ShopCustomer.attemptsTo(AddProductToCart(ProductData));

});

test('Product should not be added to the cart if stock:0 and clearance-sale:true. @product @priority1', async ({
    ShopCustomer,
    ProductData,
    AdminApiContext,
    StorefrontProductDetail,
}) => {

    const productId = ProductData.id;
    await test.step('Set the stock of the product to 0 and the clearance sale to true.', async () => {
        const editProductResponse = await AdminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 0,
                isCloseout: true,
            },
        });

        expect(editProductResponse.ok()).toBeTruthy();
    })

    await ShopCustomer.goesTo(StorefrontProductDetail);
    await ShopCustomer.expects(StorefrontProductDetail.addToCartButton).toBeHidden();

});

test('Product should be added to the cart if stock:0 and clearance-sale:false. @product @priority1', async ({
    ShopCustomer,
    ProductData,
    AdminApiContext,
    StorefrontProductDetail,
    AddProductToCart,
}) => {

    await test.step('Set the stock of the product to 0 and the clearance sale to false.', async () => {
        const productId = ProductData.id;
        const editProductResponse = await AdminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 0,
                isCloseout: false,
            },
        });

        expect(editProductResponse.ok()).toBeTruthy();
    });

    await ShopCustomer.goesTo(StorefrontProductDetail);
    await ShopCustomer.attemptsTo(AddProductToCart(ProductData));

});

test('Product should be removed from existing cart if stock:0 and cleareance-sale is changed to true. @product @priority1', async ({
    ShopCustomer,
    ProductData,
    AdminApiContext,
    StorefrontProductDetail,
    AddProductToCart,
    StorefrontCheckoutCart,
}) => {

    const productId = ProductData.id;
    await test.step('Set stock 0 and the clearance-sale to false.', async () => {
        const changeClearanceSaleResponse = await AdminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 0,
                isCloseout: false,
            },
        });

        expect(changeClearanceSaleResponse.ok()).toBeTruthy();
    });

    await ShopCustomer.goesTo(StorefrontProductDetail);
    await ShopCustomer.attemptsTo(AddProductToCart(ProductData));

    await test.step('Set the clearance sale to true.', async () => {
        const productId = ProductData.id;
        const editProductResponse = await AdminApiContext.patch(`./product/${productId}`, {
            data: {
                isCloseout: true,
            },
        });

        expect(editProductResponse.ok()).toBeTruthy();
    });

    await ShopCustomer.goesTo(StorefrontCheckoutCart);
    await ShopCustomer.expects(StorefrontCheckoutCart.emptyCartAlert).toBeVisible();

});

test('Stock reached message should be displayed if stock is changed to 1 and clearance-sale:active after adding 2 products to the cart. @product @priority1', async ({
    ShopCustomer,
    ProductData,
    AdminApiContext,
    StorefrontProductDetail,
    StorefrontCheckoutCart,
    AddProductToCart,
}) => {

    const productId = ProductData.id;
    await test.step('Set the stock of the product to 2 and the clearance sale to active.', async () => {
        const editProductResponse = await AdminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 2,
                isCloseout: true,
            },
        });

        expect(editProductResponse.ok()).toBeTruthy();
    });

    await ShopCustomer.goesTo(StorefrontProductDetail);
    await ShopCustomer.attemptsTo(AddProductToCart(ProductData, '2'));

    await test.step('Set the stock of the product to 1.', async () => {
        const changeStockResponse = await AdminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 1,
            },
        });

        expect(changeStockResponse.ok()).toBeTruthy();
    });

    await ShopCustomer.goesTo(StorefrontCheckoutCart);

    await ShopCustomer.expects(StorefrontCheckoutCart.stockReachedAlert).toContainText(ProductData.name);
    await ShopCustomer.expects(StorefrontCheckoutCart.grandTotalPrice).toHaveText('€10.00*');
});
