/**
 * @sw-package checkout
 */

import companyNamesRequired from './company-name-fields.helper';

function service(values: Record<string, unknown>) {
    return { getValues: () => Promise.resolve(values) };
}

describe('module/sw-customer/helper/company-name-fields.helper', () => {
    it('follows both settings while the account type selection is on', async () => {
        await expect(
            companyNamesRequired(
                service({
                    'core.loginRegistration.showAccountTypeSelection': true,
                    'core.loginRegistration.showNameFieldsForCompanyAccounts': true,
                    'core.loginRegistration.nameFieldsRequiredForCompanyAccounts': false,
                }),
            ),
        ).resolves.toBe(false);
    });

    it('cannot require a hidden field', async () => {
        await expect(
            companyNamesRequired(
                service({
                    'core.loginRegistration.showAccountTypeSelection': true,
                    'core.loginRegistration.showNameFieldsForCompanyAccounts': false,
                    'core.loginRegistration.nameFieldsRequiredForCompanyAccounts': true,
                }),
            ),
        ).resolves.toBe(false);
    });

    it('ignores both settings without the account type selection', async () => {
        await expect(
            companyNamesRequired(
                service({
                    'core.loginRegistration.showAccountTypeSelection': false,
                    'core.loginRegistration.showNameFieldsForCompanyAccounts': true,
                    'core.loginRegistration.nameFieldsRequiredForCompanyAccounts': false,
                }),
            ),
        ).resolves.toBe(true);
    });

    it('defaults the two settings to on', async () => {
        await expect(
            companyNamesRequired(service({ 'core.loginRegistration.showAccountTypeSelection': true })),
        ).resolves.toBe(true);
    });

    it('stays required without a service', async () => {
        await expect(companyNamesRequired(null)).resolves.toBe(true);
    });

    it('stays required when the request fails', async () => {
        await expect(companyNamesRequired({ getValues: () => Promise.reject(new Error('forbidden')) })).resolves.toBe(true);
    });
});
