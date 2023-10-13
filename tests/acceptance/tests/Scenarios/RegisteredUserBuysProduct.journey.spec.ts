import { test, expect } from '@fixtures/AcceptanceTest';
import { ProductDetailPage, CheckoutConfirmPage, CheckoutFinishPage } from '@page-objects/StorefrontPages';

test('Journey: Registered shop customer buys a product. @journey @checkout', async ({
    salesChannelProduct,
    storefrontPage,
    defaultStorefront,
    adminApiContext,
}) => {
    test.info().annotations.push({
        type: 'Description',
        description:
            'This scenario tests a full shop customer journey from selecting a product, adding it to the cart and performing a checkout.',
    });

    const detailPage = new ProductDetailPage(storefrontPage, salesChannelProduct);
    const checkoutConfirmPage = new CheckoutConfirmPage(storefrontPage);
    const checkoutFinishPage = new CheckoutFinishPage(storefrontPage);

    await test.step('Shop customer navigates to product detail page.', async () => {
        await detailPage.goto();
        await expect(detailPage.page).toHaveTitle(
            `${salesChannelProduct.translated.name} | ${salesChannelProduct.productNumber}`
        );
    });

    await test.step('Shop customer adds product to cart.', async () => {
        await detailPage.addToCartButton.click();

        await expect(detailPage.offCanvasCartTitle).toBeVisible();
        await expect(detailPage.offCanvasCart.getByText(salesChannelProduct.name)).toBeVisible();
    });

    await test.step('Shop customer proceeds to checkout.', async () => {
        await detailPage.page.getByRole('link', { name: 'Go to checkout' }).click();

        await expect(checkoutConfirmPage.headline).toBeVisible();
    });

    await test.step('Shop customer confirms terms and conditions.', async () => {
        await checkoutConfirmPage.termsAndConditionsCheckbox.check();
        await expect(checkoutConfirmPage.termsAndConditionsCheckbox).toBeChecked();
    });

    await test.step('Shop customer selects payment method.', async () => {
        await checkoutConfirmPage.selectInvoicePaymentOption();
    });

    await test.step('Shop customer selects shipping method', async () => {
        await checkoutConfirmPage.selectStandardShippingOption();
    });

    await test.step('Shop customer validates price sum.', async () => {
        await expect(checkoutConfirmPage.grandTotalPrice).toHaveText('€10.00*');
    });

    await test.step('Shop customer submits order.', async () => {
        await checkoutConfirmPage.submitOrderButton.click();
        await expect(checkoutFinishPage.headline).toBeVisible();
    });

    await test.step('Validate that the order was submitted successfully.', async () => {
        const orderId = checkoutFinishPage.getOrderId();
        const orderResponse = await adminApiContext.get(`order/${orderId}`);

        expect(orderResponse.ok()).toBeTruthy();

        const order = await orderResponse.json();

        expect(order.data).toEqual(
            expect.objectContaining({
                price: expect.objectContaining({
                    totalPrice: 10,
                }),
                orderCustomer: expect.objectContaining({
                    email: defaultStorefront.customer.email,
                }),
            })
        );
    });
});
