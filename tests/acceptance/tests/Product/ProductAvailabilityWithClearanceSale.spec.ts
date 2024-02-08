import { test, expect } from '@fixtures/AcceptanceTest';

test('Product should be added to the cart if stock:1 and clearance-sale:true. @product @priority1', async ({
    shopCustomer,
    productData,
    adminApiContext,
    productDetailPage,
    AddProductToCart,
}) => {

    await test.step('Set the stock of the product to 1 and the clearance sale to true.', async () => {
        const productId = productData.id;
        const editProductResponse = await adminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 1,
                isCloseout: true,
            },
        });

        await expect(editProductResponse.ok()).toBeTruthy();
    });

    await shopCustomer.goesTo(productDetailPage);
    await shopCustomer.attemptsTo(AddProductToCart(productData));

});

test('Product should not be added to the cart if stock:0 and clearance-sale:true. @product @priority1', async ({
    shopCustomer,
    productData,
    adminApiContext,
    productDetailPage,
}) => {

    const productId = productData.id;
    await test.step('Set the stock of the product to 0 and the clearance sale to true.', async () => {
        const editProductResponse = await adminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 0,
                isCloseout: true,
            },
        });

        await expect(editProductResponse.ok()).toBeTruthy();
    })

    await shopCustomer.goesTo(productDetailPage);
    await shopCustomer.expects(productDetailPage.addToCartButton).toBeHidden();

});

test('Product should be added to the cart if stock:0 and clearance-sale:false. @product @priority1', async ({
    shopCustomer,
    productData,
    adminApiContext,
    productDetailPage,
    AddProductToCart,
}) => {

    await test.step('Set the stock of the product to 0 and the clearance sale to false.', async () => {
        const productId = productData.id;
        const editProductResponse = await adminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 0,
                isCloseout: false,
            },
        });

        await expect(editProductResponse.ok()).toBeTruthy();
    });

    await shopCustomer.goesTo(productDetailPage);
    await shopCustomer.attemptsTo(AddProductToCart(productData));

});

test('Product should be removed from existing cart if stock:0 and cleareance-sale is changed to true. @product @priority1', async ({
    shopCustomer,
    productData,
    adminApiContext,
    productDetailPage,
    AddProductToCart,
    checkoutCartPage,
}) => {

    const productId = productData.id;
    await test.step('Set stock 0 and the clearance-sale to false.', async () => {
        const changeClearanceSaleResponse = await adminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 0,
                isCloseout: false,
            },
        });

        await expect(changeClearanceSaleResponse.ok()).toBeTruthy();
    });

    await shopCustomer.goesTo(productDetailPage);
    await shopCustomer.attemptsTo(AddProductToCart(productData));

    await test.step('Set the clearance sale to true.', async () => {
        const productId = productData.id;
        const editProductResponse = await adminApiContext.patch(`./product/${productId}`, {
            data: {
                isCloseout: true,
            },
        });

        await expect(editProductResponse.ok()).toBeTruthy();
    });

    await shopCustomer.goesTo(checkoutCartPage);
    await shopCustomer.expects(checkoutCartPage.emptyCartAlert).toBeVisible();

});

test('Stock reached message should be displayed if stock is changed to 1 and clearance-sale:active after adding 2 products to the cart. @product @priority1', async ({
    shopCustomer,
    productData,
    adminApiContext,
    productDetailPage,
    checkoutCartPage,
    AddProductToCart,
}) => {

    const productId = productData.id;
    await test.step('Set the stock of the product to 2 and the clearance sale to active.', async () => {
        const editProductResponse = await adminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 2,
                isCloseout: true,
            },
        });
        await expect(editProductResponse.ok()).toBeTruthy();
    });

    await shopCustomer.goesTo(productDetailPage);
    await shopCustomer.attemptsTo(AddProductToCart(productData, '2'));

    await test.step('Set the stock of the product to 1.', async () => {
        const changeStockResponse = await adminApiContext.patch(`./product/${productId}`, {
            data: {
                stock: 1,
            },
        });

        await expect(changeStockResponse.ok()).toBeTruthy();
    });

    await shopCustomer.goesTo(checkoutCartPage);

    await shopCustomer.expects(checkoutCartPage.stockReachedAlert).toContainText(productData.name);
    await shopCustomer.expects(checkoutCartPage.grandTotalPrice).toHaveText('€10.00*');
});
