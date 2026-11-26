import CompanyAccountNameFieldsApiService from 'src/core/service/api/company-account-name-fields.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createService() {
    const client = createHTTPClient(Shopware.Context.api);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);

    return { service: new CompanyAccountNameFieldsApiService(client, loginService), clientMock };
}

/**
 * @sw-package checkout
 */
describe('companyAccountNameFieldsService', () => {
    it('is registered correctly', () => {
        const { service } = createService();

        expect(service).toBeInstanceOf(CompanyAccountNameFieldsApiService);
        expect(service.name).toBe('companyAccountNameFieldsService');
    });

    it('asks the endpoint for the sales channel', async () => {
        const { service, clientMock } = createService();

        clientMock
            .onGet('/_action/customer/company-account-name-fields', { params: { salesChannelId: 'sales-channel-id' } })
            .reply(200, { contactPersonRequired: false });

        await expect(service.isContactPersonRequired('sales-channel-id')).resolves.toBe(false);

        expect(clientMock.history.get).toHaveLength(1);
        expect(clientMock.history.get[0].params).toEqual({ salesChannelId: 'sales-channel-id' });
    });

    it('asks the endpoint without a sales channel', async () => {
        const { service, clientMock } = createService();

        clientMock.onGet('/_action/customer/company-account-name-fields').reply(200, { contactPersonRequired: true });

        await expect(service.isContactPersonRequired(null)).resolves.toBe(true);

        expect(clientMock.history.get[0].params).toEqual({});
    });

    it('keeps the names required when the endpoint cannot be read', async () => {
        const { service, clientMock } = createService();

        clientMock.onGet('/_action/customer/company-account-name-fields').reply(403, { errors: [] });

        await expect(service.isContactPersonRequired(null)).resolves.toBe(true);
    });
});
