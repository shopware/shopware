import CompanyAccountNameFieldsApiService from 'src/core/service/api/company-account-name-fields.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

function createService() {
    const context = Shopware.Context?.api || {};
    const client = createHTTPClient(context);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, context);
    const service = new CompanyAccountNameFieldsApiService(client, loginService);

    return { service, clientMock };
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

    it('reads the inherited login registration settings of the sales channel', async () => {
        const { service, clientMock } = createService();
        clientMock.onGet('_action/system-config').reply(200, {
            'core.loginRegistration.showNameFieldsForCompanyAccounts': true,
            'core.loginRegistration.nameFieldsRequiredForCompanyAccounts': false,
        });

        await expect(service.isContactPersonRequired('sales-channel-id')).resolves.toBe(false);

        const request = clientMock.history.get[0];

        expect(request?.params).toEqual({
            domain: 'core.loginRegistration',
            salesChannelId: 'sales-channel-id',
            inherit: true,
        });
    });

    it.each([
        [
            'both on',
            { showNameFieldsForCompanyAccounts: true, nameFieldsRequiredForCompanyAccounts: true },
            true,
        ],
        [
            'hidden cannot be required',
            { showNameFieldsForCompanyAccounts: false, nameFieldsRequiredForCompanyAccounts: true },
            false,
        ],
        [
            'never saved keeps the names required',
            {},
            true,
        ],
    ])('resolves the requirement: %s', async (_name, values, expected) => {
        const { service, clientMock } = createService();
        const response = Object.fromEntries(
            Object.entries(values).map(
                ([
                    key,
                    value,
                ]) => [
                    `core.loginRegistration.${key}`,
                    value,
                ],
            ),
        );
        clientMock.onGet('_action/system-config').reply(200, response);

        await expect(service.isContactPersonRequired(null)).resolves.toBe(expected);
    });

    it('treats an empty configuration as required', async () => {
        const { service, clientMock } = createService();
        clientMock.onGet('_action/system-config').reply(200, []);

        await expect(service.isContactPersonRequired(null)).resolves.toBe(true);
    });
});
