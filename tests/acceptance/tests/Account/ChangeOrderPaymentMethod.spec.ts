import { test } from '@fixtures/AcceptanceTest';

test('Customers can update the payment method for an existing order in the storefront account.', { tag: '@Order @Account' }, async ({
    ShopCustomer,
    StorefrontAccountOrder,
    StorefrontCheckoutOrderEdit,
    TestDataService,
    Login,
}) => {
    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: 5 }],
        customer
    );

    const newPaymentMethod = await TestDataService.createBasicPaymentMethod({ afterOrderEnabled: true });
    await TestDataService.assignSalesChannelPaymentMethod(TestDataService.defaultSalesChannel.id, newPaymentMethod.id);

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');

    await orderItemLocators.orderActionsButton.click();
    await orderItemLocators.orderChangePaymentMethodButton.click();

    const invoiceInputValue = await StorefrontCheckoutOrderEdit.getPaymentMethodButton('Invoice').getAttribute('value');
    await StorefrontCheckoutOrderEdit.getPaymentMethodButton(newPaymentMethod.name).click();

    const paymentMethodInput = ShopCustomer.page.locator('input[type=hidden][name="paymentMethodId"]');
    await ShopCustomer.expects(paymentMethodInput).not.toHaveValue(invoiceInputValue, { timeout: 15_000 });

    // for some reason checking the state of the inputs actually changes the state of the inputs
    await ShopCustomer.expects(StorefrontCheckoutOrderEdit.getPaymentMethodButton('Invoice')).not.toBeChecked();
    await ShopCustomer.expects(StorefrontCheckoutOrderEdit.getPaymentMethodButton(newPaymentMethod.name)).toBeChecked();

    await StorefrontCheckoutOrderEdit.completePaymentButton.click();

    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText(newPaymentMethod.name);
});