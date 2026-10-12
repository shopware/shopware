import GuestCustomerConvertService from 'src/core/service/api/guest-customer-convert.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

/**
 * @sw-package checkout
 */

function getGuestCustomerConvertService() {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    const guestCustomerConvertService = new GuestCustomerConvertService(client, loginService);

    return {
        guestCustomerConvertService,
        clientMock,
    };
}

describe('GuestCustomerConvertService', () => {
    it('is registered correctly', () => {
        const { guestCustomerConvertService } = getGuestCustomerConvertService();

        expect(guestCustomerConvertService).toBeInstanceOf(GuestCustomerConvertService);
    });

    it('calls convert with correct endpoint and payload', async () => {
        const { guestCustomerConvertService, clientMock } = getGuestCustomerConvertService();

        const customerId = 'test-customer-id';

        const payload = {
            password: 'test-password',
        };

        let requestSent = false;

        clientMock.onPost(`/_action/customer-convert/${customerId}`, payload).reply((config) => {
            requestSent = true;

            expect(config.url).toBe(`/_action/customer-convert/${customerId}`);
            expect(JSON.parse(config.data)).toEqual(payload);

            return [
                200,
                {
                    success: true,
                },
            ];
        });

        const response = await guestCustomerConvertService.convert(customerId, payload);

        await flushPromises();

        expect(requestSent).toBeTruthy();
        expect(response.success).toBe(true);
    });

    it('calls convert with correct endpoint without payload', async () => {
        const { guestCustomerConvertService, clientMock } = getGuestCustomerConvertService();

        const customerId = 'test-customer-id';

        let requestSent = false;

        clientMock.onPost(`/_action/customer-convert/${customerId}`).reply((config) => {
            requestSent = true;

            expect(config.url).toBe(`/_action/customer-convert/${customerId}`);

            return [
                200,
                {
                    success: true,
                },
            ];
        });

        const response = await guestCustomerConvertService.convert(customerId);

        await flushPromises();

        expect(requestSent).toBeTruthy();
        expect(response.success).toBe(true);
    });

    it('throws error on convert request failure', async () => {
        const { guestCustomerConvertService, clientMock } = getGuestCustomerConvertService();

        const customerId = 'test-customer-id';

        clientMock.onPost(`/_action/customer-convert/${customerId}`).reply(400, {
            errors: [
                {
                    detail: 'Conversion failed',
                },
            ],
        });

        await expect(guestCustomerConvertService.convert(customerId, {})).rejects.toThrow();
    });
});
