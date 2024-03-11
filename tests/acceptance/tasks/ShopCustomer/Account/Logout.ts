import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const Logout = base.extend<{ Logout: Task }, FixtureTypes>({
    Logout: async ({ shopCustomer, accountLoginPage }, use)=> {
        const task = () => {
            return async function Logout() {

                await shopCustomer.goesTo(accountLoginPage);
                await shopCustomer.expects(accountLoginPage.loginButton).not.toBeVisible();

                await accountLoginPage.logoutLink.click();
                await shopCustomer.expects(accountLoginPage.successAlert).toBeVisible();
            }
        };

        await use(task);
    },
});
