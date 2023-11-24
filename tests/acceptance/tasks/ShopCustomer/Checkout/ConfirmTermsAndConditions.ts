import { test as base } from '@playwright/test';
import { FixtureTypes } from '@fixtures/FixtureTypes';
import type { Task } from '@fixtures/Task';

export const ConfirmTermsAndConditions = base.extend<{ ConfirmTermsAndConditions: Task }, FixtureTypes>({
    ConfirmTermsAndConditions: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function ConfirmTermsAndConditions() {
                await checkoutConfirmPage.termsAndConditionsCheckbox.check();
                await shopCustomer.expects(checkoutConfirmPage.termsAndConditionsCheckbox).toBeChecked();
            }
        };

        await use(task);
    },
});
