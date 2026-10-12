import CheckoutStoreService from 'src/core/service/api/checkout-store.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createCheckoutStoreService() {
    const context = Shopware.Context?.api || {};
    const client = createHTTPClient(context);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);
    const checkoutStoreService = new CheckoutStoreService(client, loginService);

    clientMock.onAny().reply(200, {
        data: null,
    });

    return { checkoutStoreService, clientMock };
}

/**
 * @sw-package checkout
 */
describe('checkoutStoreService', () => {
    it('is registered correctly', () => {
        const { checkoutStoreService } = createCheckoutStoreService();

        expect(checkoutStoreService).toBeInstanceOf(CheckoutStoreService);
    });

    it('sends checkout payload to the proxy order endpoint', async () => {
        const { checkoutStoreService, clientMock } = createCheckoutStoreService();
        const salesChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265' as EntityKey<'sales_channel'>;
        const contextToken = 'is-exactly-32-chars-as-required-';

        await checkoutStoreService.checkout(salesChannelId, contextToken, {}, {}, { sendOrderConfirmationMail: false });

        const request = clientMock.history.post[0];

        if (!request) {
            throw new Error('Expected checkout request to be sent');
        }

        expect(request.url).toBe(`_proxy-order/${salesChannelId}`);
        const requestData = request.data as string;

        expect(JSON.parse(requestData)).toEqual({ sendOrderConfirmationMail: false });
        expect(request.headers?.['sw-context-token']).toBe(contextToken);
    });
});
