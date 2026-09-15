import CompanyAccountNameFieldsService from 'src/core/service/company-account-name-fields.service';

function createService(getValues: jest.Mock) {
    return new CompanyAccountNameFieldsService({ getValues });
}

/**
 * @sw-package checkout
 */
describe('src/core/service/company-account-name-fields.service', () => {
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
