import { test } from '@fixtures/AcceptanceTest';
import { expect } from '@shopware-ag/acceptance-test-suite';

test('As a customer, I want to repeat a previous order via the storefront account.', { tag: '@Order @Account' }, async ({
    ShopCustomer,
    StorefrontAccountOrder, 
    StorefrontOffCanvasCart,
    TestDataService,
    Login,
 
}) => {
    const product = await TestDataService.createBasicProduct();
    const customer = await TestDataService.createCustomer();
    const productQuantity = 5;
    const order = await TestDataService.createOrder(
        [{ product: product, quantity: productQuantity }],
        customer
    );

    await ShopCustomer.attemptsTo(Login(customer));
    await ShopCustomer.goesTo(StorefrontAccountOrder.url());
    const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
    await orderItemLocators.orderActionsButton.click();
    await orderItemLocators.orderRepeatButton.click();

    await expect(StorefrontOffCanvasCart.itemCount).toBeVisible();
    await expect(StorefrontOffCanvasCart.itemCount).toContainText('1 item');
    const cartProduct = await StorefrontOffCanvasCart.getLineItemByProductNumber(product.productNumber);
    await expect(cartProduct.productQuantityInput).toHaveValue(productQuantity.toString());
}); 