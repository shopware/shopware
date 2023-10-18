import { test as base } from '@playwright/test';

export const ConfirmTermsAndConditions = base.extend({
    ConfirmTermsAndConditions: async ({ shopCustomer, checkoutConfirmPage }, use)=> {
        const task = () => {
            return async function ConfirmTermsAndConditions() {
                await checkoutConfirmPage.termsAndConditionsCheckbox.check();
                await shopCustomer.expects(checkoutConfirmPage.termsAndConditionsCheckbox).toBeChecked();
            }
        };

        use(task);
    },
});
