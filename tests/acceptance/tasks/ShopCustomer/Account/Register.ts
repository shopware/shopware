import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const Register = base.extend<{ Register: Task }, FixtureTypes>({
    Register: async ({ shopCustomer, accountLoginPage, accountPage, idProvider }, use) => {
        const task = () => {
            return async function Register() {

                await accountLoginPage.firstNameInput.fill('Jeff');
                await accountLoginPage.lastNameInput.fill('Goldblum');

                const email = idProvider.getIdPair().uuid + '@test.com';
                await accountLoginPage.registerEmailInput.fill(email);

                await accountLoginPage.registerPasswordInput.fill('testtest');
                await accountLoginPage.streetAddressInput.fill('Ebbinghof 10');
                await accountLoginPage.cityInput.fill('Schöppingen');
                await accountLoginPage.countryInput.selectOption('Germany');

                await accountLoginPage.registerButton.click();
                await shopCustomer.expects(accountPage.page.getByText( email, { exact: true })).toBeVisible();
            }
        };

        await use(task);
    },
});
