import { test } from '@fixtures/AcceptanceTest';
import type { ConsoleMessage, Locator, Page } from '@playwright/test';

interface CancelOrderModalState {
    modalEvents: string[];
    modalSelector: string | null;
    triggerEvents: string[];
}

/**
 * Captures the modal data API state only when the known flaky dialog activation fails.
 */
async function openCancelOrderModal(
    page: Page,
    trigger: Locator,
    dialog: Locator,
    triggerModal: () => Promise<void>,
    assertDialogFocused: () => Promise<void>,
): Promise<void> {
    const pageErrors: string[] = [];
    const consoleErrors: string[] = [];
    const pageErrorListener = (error: Error) => pageErrors.push(error.message);
    const consoleListener = (message: ConsoleMessage) => {
        if (message.type() === 'error') {
            consoleErrors.push(message.text());
        }
    };

    page.on('pageerror', pageErrorListener);
    page.on('console', consoleListener);

    await trigger.evaluate((button) => {
        const modalSelector = button.getAttribute('data-bs-target');
        const modal = modalSelector ? document.querySelector(modalSelector) : null;
        const state: CancelOrderModalState = {
            modalEvents: [],
            modalSelector,
            triggerEvents: [],
        };

        for (const eventName of ['keydown', 'keyup', 'click']) {
            button.addEventListener(eventName, () => state.triggerEvents.push(eventName), { once: true });
        }

        if (modal) {
            for (const eventName of ['show.bs.modal', 'shown.bs.modal']) {
                modal.addEventListener(eventName, () => state.modalEvents.push(eventName), { once: true });
            }
        }

        (window as unknown as { cancelOrderModalState?: CancelOrderModalState }).cancelOrderModalState = state;
    });

    try {
        await triggerModal();
        await assertDialogFocused();
    } catch (error) {
        const state = await page.evaluate(({ consoleErrors, pageErrors }) => {
            const windowWithState = window as unknown as { cancelOrderModalState?: CancelOrderModalState };
            const modal = windowWithState.cancelOrderModalState?.modalSelector
                ? document.querySelector(windowWithState.cancelOrderModalState.modalSelector)
                : null;

            return {
                activeElement: document.activeElement?.outerHTML,
                bootstrapLoaded: 'bootstrap' in window,
                consoleErrors,
                modal: modal instanceof HTMLElement ? {
                    ariaHidden: modal.getAttribute('aria-hidden'),
                    className: modal.className,
                    connected: modal.isConnected,
                } : null,
                pageErrors,
                readyState: document.readyState,
                state: windowWithState.cancelOrderModalState ?? null,
            };
        }, { consoleErrors, pageErrors });

        const message = error instanceof Error ? error.message : String(error);

        throw new Error(`${message}\nCancel order modal diagnostics:\n${JSON.stringify(state, null, 2)}`);
    } finally {
        page.off('pageerror', pageErrorListener);
        page.off('console', consoleListener);
        await page.evaluate(() => {
            delete (window as unknown as { cancelOrderModalState?: CancelOrderModalState }).cancelOrderModalState;
        });
    }
}

test(
    'Customers are able to cancel orders in storefront account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, page }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        const untouchedOrder = await TestDataService.createOrder([{ product: product, quantity: 1 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());

        const untouchedOrderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(untouchedOrder.orderNumber);
        await ShopCustomer.expects(untouchedOrderItemLocators.orderStatus).toContainText('Open');

        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await openCancelOrderModal(
            page,
            orderItemLocators.orderCancelButton,
            StorefrontAccountOrder.dialogOrderCancel,
            () => ShopCustomer.presses(orderItemLocators.orderCancelButton),
            () => ShopCustomer.expects(StorefrontAccountOrder.dialogOrderCancel).toBeFocused(),
        );
        await ShopCustomer.presses(StorefrontAccountOrder.dialogOrderCancelButton);
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText('Standard');
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Cancelled');
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText('Open');
        // ensure other order is unaffected
        await ShopCustomer.expects(untouchedOrderItemLocators.orderStatus).toContainText('Open');
    },
);

test(
    'Customers are able to cancel orders on the final checkout page in storefront account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, StorefrontCheckoutOrderEdit, page }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': true });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);
        await openCancelOrderModal(
            page,
            StorefrontCheckoutOrderEdit.orderCancelButton,
            StorefrontCheckoutOrderEdit.dialogOrderCancel,
            () => ShopCustomer.presses(StorefrontCheckoutOrderEdit.orderCancelButton),
            () => ShopCustomer.expects(StorefrontCheckoutOrderEdit.dialogOrderCancel).toBeFocused(),
        );
        await ShopCustomer.presses(StorefrontCheckoutOrderEdit.dialogOrderCancelButton);
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(orderItemLocators.orderShippingStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentStatus).toContainText('Open');
        await ShopCustomer.expects(orderItemLocators.orderPaymentMethod).toContainText('Invoice');
        await ShopCustomer.expects(orderItemLocators.orderShippingMethod).toContainText('Standard');
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Cancelled');
        await ShopCustomer.expects(orderItemLocators.orderStatus).not.toContainText('Open');
    },
);

test(
    'Customers are not able to cancel orders on the final checkout page in storefront account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login, StorefrontCheckoutOrderEdit }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.presses(orderItemLocators.orderChangePaymentMethodButton);
        await ShopCustomer.expects(StorefrontCheckoutOrderEdit.orderCancelButton).not.toBeVisible();
    },
);

test(
    'Customers are not able to cancel orders in storefront account.',
    {
        tag: [
            '@Order',
            '@Account',
            '@Storefront',
        ],
    },
    async ({ ShopCustomer, StorefrontAccountOrder, TestDataService, Login }) => {
        const product = await TestDataService.createBasicProduct();
        const customer = await TestDataService.createCustomer();
        const order = await TestDataService.createOrder([{ product: product, quantity: 5 }], customer);

        await TestDataService.setSystemConfig({ 'core.cart.enableOrderRefunds': false });

        await ShopCustomer.attemptsTo(Login(customer));
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        const orderItemLocators = await StorefrontAccountOrder.getOrderByOrderNumber(order.orderNumber);
        await ShopCustomer.expects(orderItemLocators.orderStatus).toContainText('Open');
        await ShopCustomer.presses(orderItemLocators.orderActionsButton);
        await ShopCustomer.expects(orderItemLocators.orderCancelButton).not.toBeVisible();
    },
);
