import type { FixtureTypes, Task } from '@fixtures/AcceptanceTest';
import { test as base } from '@playwright/test';

export interface CompanyAccountData {
    email: string;
    company: string;
    password?: string;
    firstName?: string;
    lastName?: string;
    vatRegNo?: string;
}

/**
 * Registers a commercial account in the Storefront. The name fields are only filled when they are
 * rendered, so the task serves both a shop that shows them and one that hides them for company accounts.
 */
export const RegisterCompanyAccount = base.extend<{ RegisterCompanyAccount: Task }, FixtureTypes>({
    RegisterCompanyAccount: async ({ ShopCustomer, StorefrontAccountLogin, TestDataService }, use) => {
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
                await ShopCustomer.fillsIn(StorefrontAccountLogin.streetAddressInput, 'Ebbinghoff 10');
                await ShopCustomer.fillsIn(StorefrontAccountLogin.postalCodeInput, '48624');
                await ShopCustomer.fillsIn(StorefrontAccountLogin.cityInput, 'Schöppingen');
                await ShopCustomer.presses(StorefrontAccountLogin.countryInput);
                await StorefrontAccountLogin.countryInput.selectOption({
                    label: 'Germany',
                });
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
