import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';

test('As a customer, I must be able to change my email via account.', { tag: ['@Account', '@Storefront'] }, async ({
    IdProvider,
    ShopCustomer,
    StorefrontAccount,
    StorefrontAccountLogin,
    StorefrontAccountProfile,
    Register,
}) => {

    const customer = { email: IdProvider.getIdPair().uuid + '@test.com' , password: IdProvider.getIdPair().uuid };
    const invalidEmail = 'invalidEmailWithoutAtSymbol';
    const newEmail = IdProvider.getIdPair().uuid + '@test.com' ;

    await test.step('Register a valid account', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await ShopCustomer.attemptsTo(Register(customer));
        await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
    });

    await test.step('Attempt to change email to an invalid address', async () => {
        await ShopCustomer.goesTo(StorefrontAccountProfile.url());
        await ShopCustomer.presses(StorefrontAccountProfile.changeEmailButton);
        await ShopCustomer.expects(StorefrontAccountProfile.emailAddressInput).toBeVisible();
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailAddressInput, invalidEmail);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailAddressConfirmInput, invalidEmail);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailConfirmPasswordInput, customer.password);
        await ShopCustomer.presses(StorefrontAccountProfile.saveEmailAddressButton);
        await ShopCustomer.expects(StorefrontAccountProfile.emailValidationAlert).toBeVisible();
    });

    await test.step('Attempt to change email to the same address', async () => {
        await ShopCustomer.goesTo(StorefrontAccountProfile.url());
        await ShopCustomer.presses(StorefrontAccountProfile.changeEmailButton);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailAddressInput, customer.email);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailAddressConfirmInput, customer.email);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailConfirmPasswordInput, customer.password);
        await ShopCustomer.presses(StorefrontAccountProfile.saveEmailAddressButton);
        await ShopCustomer.expects(StorefrontAccountProfile.emailUpdateFailureAlert).toBeVisible();
    });

    await test.step('Change email to a new valid address', async () => {
        await ShopCustomer.goesTo(StorefrontAccountProfile.url());
        await ShopCustomer.presses(StorefrontAccountProfile.changeEmailButton);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailAddressInput, newEmail);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailAddressConfirmInput, newEmail);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.emailConfirmPasswordInput, customer.password);
        await ShopCustomer.presses(StorefrontAccountProfile.saveEmailAddressButton);
        await ShopCustomer.expects(StorefrontAccountProfile.emailUpdateMessage).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountProfile.loginDataEmailAddress).toContainText(newEmail);
    });

    await test.step('Verify login with old email fails', async () => {
        await ShopCustomer.presses(StorefrontAccountLogin.logoutLink);
        await ShopCustomer.expects(StorefrontAccountLogin.successAlert).toBeVisible();
        await ShopCustomer.fillsIn(StorefrontAccountLogin.emailInput, customer.email);
        await ShopCustomer.fillsIn(StorefrontAccountLogin.passwordInput, customer.password);
        await ShopCustomer.presses(StorefrontAccountLogin.loginButton);
        await ShopCustomer.expects(StorefrontAccountLogin.invalidCredentialsAlert).toBeVisible();
    });

    await test.step('Verify login with new email', async () => {
        await ShopCustomer.fillsIn(StorefrontAccountLogin.emailInput, newEmail);
        await ShopCustomer.fillsIn(StorefrontAccountLogin.passwordInput, customer.password);
        await ShopCustomer.presses(StorefrontAccountLogin.loginButton);
        await StorefrontAccount.page.waitForURL('**/account', { waitUntil: 'commit' });
        await ShopCustomer.expects(StorefrontAccount.personalDataCardTitle).toBeVisible();
    });
});

test('As a customer, I must be able to change my password via account.', { tag: ['@Account', '@Storefront'] }, async ({
    IdProvider,
    InstanceMeta,
    ShopCustomer,
    StorefrontAccount,
    StorefrontAccountLogin,
    StorefrontAccountProfile,
    Register,
}) => {

    const customer = { email: IdProvider.getIdPair().uuid + '@test.com' , password: IdProvider.getIdPair().uuid };
    const invalidPassword = { password: 'short' }; // Invalid: less than 8 characters
    const newPassword = IdProvider.getIdPair().uuid ;

    await test.step('Register a new account', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await ShopCustomer.attemptsTo(Register(customer));
        await ShopCustomer.expects(StorefrontAccount.page.getByText(customer.email, { exact: true })).toBeVisible();
    });

    await test.step('Attempt to change password to an invalid (short) password', async () => {
        await ShopCustomer.goesTo(StorefrontAccountProfile.url());
        await ShopCustomer.presses(StorefrontAccountProfile.changePasswordButton);
        await ShopCustomer.expects(StorefrontAccountProfile.newPasswordInput).toBeVisible();
        await ShopCustomer.fillsIn(StorefrontAccountProfile.newPasswordInput, invalidPassword.password);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.newPasswordConfirmInput, invalidPassword.password);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.currentPasswordInput, customer.password);
        await ShopCustomer.presses(StorefrontAccountProfile.saveNewPasswordButton);
        
        // eslint-disable-next-line playwright/no-conditional-in-test
        if (satisfies(InstanceMeta.version, '<6.7') && !InstanceMeta.features['ACCESSIBILITY_TWEAKS']) {
            await StorefrontAccountProfile.saveNewPasswordButton.click();
        }   
        else {
            await ShopCustomer.presses(StorefrontAccountProfile.saveNewPasswordButton);
        }

        await ShopCustomer.expects(StorefrontAccountProfile.passwordUpdateFailureAlert).toBeVisible();
    });

    await test.step('Successfully change password to a valid password', async () => {
        await ShopCustomer.goesTo(StorefrontAccountProfile.url());
        await ShopCustomer.presses(StorefrontAccountProfile.changePasswordButton);
        await ShopCustomer.expects(StorefrontAccountProfile.newPasswordInput).toBeVisible();
        await ShopCustomer.fillsIn(StorefrontAccountProfile.newPasswordInput, newPassword);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.newPasswordConfirmInput, newPassword);
        await ShopCustomer.fillsIn(StorefrontAccountProfile.currentPasswordInput, customer.password);
        await ShopCustomer.presses(StorefrontAccountProfile.saveNewPasswordButton);
        await ShopCustomer.expects(StorefrontAccountProfile.passwordUpdateMessage).toBeVisible();
    });

    await test.step('Verify login with old password fails', async () => {
        await ShopCustomer.presses(StorefrontAccountLogin.logoutLink);
        await ShopCustomer.expects(StorefrontAccountLogin.successAlert).toBeVisible();
        await ShopCustomer.fillsIn(StorefrontAccountLogin.emailInput, customer.email);
        await ShopCustomer.fillsIn(StorefrontAccountLogin.passwordInput, customer.password);
        await ShopCustomer.presses(StorefrontAccountLogin.loginButton);
        await ShopCustomer.expects(StorefrontAccountLogin.invalidCredentialsAlert).toBeVisible();
    });

    await test.step('Verify login with new password', async () => {
        await ShopCustomer.fillsIn(StorefrontAccountLogin.emailInput, customer.email);
        await ShopCustomer.fillsIn(StorefrontAccountLogin.passwordInput, newPassword);
        await ShopCustomer.presses(StorefrontAccountLogin.loginButton);
        await StorefrontAccount.page.waitForURL('**/account', { waitUntil: 'commit' });
        await ShopCustomer.expects(StorefrontAccount.personalDataCardTitle).toBeVisible();
    });
});