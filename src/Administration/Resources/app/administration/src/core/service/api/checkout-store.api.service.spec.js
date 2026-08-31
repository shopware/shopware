import CheckoutStoreService from 'src/core/service/api/checkout-store.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createCheckoutStoreService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
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
    it('is registered correctly', async () => {
        const { checkoutStoreService } = createCheckoutStoreService();

        expect(checkoutStoreService).toBeInstanceOf(CheckoutStoreService);
    });

    it('sends checkout payload to the proxy order endpoint', async () => {
        const { checkoutStoreService, clientMock } = createCheckoutStoreService();
        const salesChannelId = '28abf61c7e3d4011aec0e0a7bcfa4265';
        const contextToken = 'is-exactly-32-chars-as-required-';

        await checkoutStoreService.checkout(salesChannelId, contextToken, {}, {}, { sendOrderConfirmationMail: false });

        expect(clientMock.history.post[0].url).toBe(`_proxy-order/${salesChannelId}`);
        expect(JSON.parse(clientMock.history.post[0].data)).toEqual({ sendOrderConfirmationMail: false });
        expect(clientMock.history.post[0].headers['sw-context-token']).toBe(contextToken);
    });
});
