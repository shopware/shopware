import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';
import { getCountryAddressData, getCountryCodeFromLocale, getLocale } from '@shopware-ag/acceptance-test-suite';

export interface CompanyAccountData {
    email: string;
    company: string;
    password?: string;
    firstName?: string;
    lastName?: string;
    vatRegNo?: string;
}

export const RegisterCompanyAccount = base.extend<{ RegisterCompanyAccount: Task }, FixtureTypes>({
    RegisterCompanyAccount: async ({ ShopCustomer, StorefrontAccountLogin, TestDataService }, use) => {
        const address = getCountryAddressData(getCountryCodeFromLocale(getLocale()));

        const task = (data: CompanyAccountData) => {
            return async function RegisterCompanyAccount() {
                await ShopCustomer.goesTo(StorefrontAccountLogin.url());
                await ShopCustomer.presses(StorefrontAccountLogin.accountTypeSelect);
                await StorefrontAccountLogin.accountTypeSelect.selectOption('Commercial');

                await ShopCustomer.presses(StorefrontAccountLogin.salutationSelect);
                await StorefrontAccountLogin.salutationSelect.selectOption('Mr.');

                if (await StorefrontAccountLogin.firstNameInput.isVisible()) {
                    await ShopCustomer.fillsIn(StorefrontAccountLogin.firstNameInput, data.firstName ?? '');
                    await ShopCustomer.fillsIn(StorefrontAccountLogin.lastNameInput, data.lastName ?? '');
                }

                await ShopCustomer.fillsIn(StorefrontAccountLogin.companyInput, data.company);
                await ShopCustomer.fillsIn(StorefrontAccountLogin.vatRegNoInput, data.vatRegNo ?? '');
                await ShopCustomer.fillsIn(StorefrontAccountLogin.registerEmailInput, data.email);
                await ShopCustomer.fillsIn(StorefrontAccountLogin.registerPasswordInput, data.password ?? 'shopware');
                await ShopCustomer.fillsIn(StorefrontAccountLogin.streetAddressInput, address.street);
                await ShopCustomer.fillsIn(StorefrontAccountLogin.postalCodeInput, address.postalCode);
                await ShopCustomer.fillsIn(StorefrontAccountLogin.cityInput, address.city);
                await ShopCustomer.presses(StorefrontAccountLogin.countryInput);
                await StorefrontAccountLogin.countryInput.selectOption({ label: address.country });
                await ShopCustomer.presses(StorefrontAccountLogin.registerButton);

                const customer = await TestDataService.getCustomerByEmail(data.email);
                if (customer) {
                    TestDataService.addCreatedRecord('customer', customer.id);
                }
            };
        };

        await use(task);
    },
});
