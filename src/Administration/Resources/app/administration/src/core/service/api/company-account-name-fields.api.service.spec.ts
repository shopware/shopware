import CompanyAccountNameFieldsApiService from 'src/core/service/api/company-account-name-fields.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';

function createService(getValues: jest.Mock) {
    const context = Shopware.Context?.api || {};
    const client = createHTTPClient(context);
    const loginService = createLoginService(client, context);

    return new CompanyAccountNameFieldsApiService(client, loginService, 'system-config', { getValues });
}

/**
 * @sw-package checkout
 */
describe('companyAccountNameFieldsService', () => {
    it('is registered correctly', () => {
        const service = createService(jest.fn());

        expect(service).toBeInstanceOf(CompanyAccountNameFieldsApiService);
        expect(service.name).toBe('companyAccountNameFieldsService');
    });

    it('reads the inherited login registration settings of the sales channel', async () => {
        const getValues = jest.fn().mockResolvedValue({
            'core.loginRegistration.showNameFieldsForCompanyAccounts': true,
            'core.loginRegistration.nameFieldsRequiredForCompanyAccounts': false,
        });

        await expect(createService(getValues).isContactPersonRequired('sales-channel-id')).resolves.toBe(false);

        expect(getValues).toHaveBeenCalledWith('core.loginRegistration', 'sales-channel-id', { inherit: true });
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

        await expect(createService(jest.fn().mockResolvedValue(response)).isContactPersonRequired(null)).resolves.toBe(
            expected,
        );
    });

    it('keeps the names required when the settings cannot be read', async () => {
        const getValues = jest.fn().mockRejectedValue(new Error('forbidden'));

        await expect(createService(getValues).isContactPersonRequired(null)).resolves.toBe(true);
    });
});
