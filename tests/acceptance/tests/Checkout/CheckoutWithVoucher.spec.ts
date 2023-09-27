import { test, expect } from "@fixtures/AcceptanceTest";

test('Registered shop customer uses a voucher during checkout. @checkout', async ({
    storeApiContext,
    salesChannelProduct
}) => {
    const productResponse = await storeApiContext.post(`product/${salesChannelProduct.id}`);

    console.log(productResponse);
});
