import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const Login = base.extend<{ Login: Task }, FixtureTypes>({
    Login: async ({ shopCustomer, defaultStorefront, accountLoginPage, accountPage }, use)=> {
        const task = () => {
            return async function Login() {
                const { customer } = defaultStorefront;

                await shopCustomer.goesTo(accountLoginPage);

                await accountLoginPage.emailInput.fill(customer.email);
                await accountLoginPage.passwordInput.fill(customer.password);
                await accountLoginPage.loginButton.click();

                await shopCustomer.expects(accountPage.personalDataCardTitle).toBeVisible();
            }
        };

        await use(task);
    },
});
