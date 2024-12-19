import { test } from '@fixtures/AcceptanceTest';

test ('As a customer, I can request a new password with existing customer email address.', { tag: '@Account @Password' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccountRecover,
    TestDataService,
}) => {
    const customer = await TestDataService.createCustomer();
    await test.step('Navigate to the login page and click on forgot password', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await StorefrontAccountLogin.forgotPasswordLink.click();
    });

    await test.step('Fill in the customer email and request a password reset', async () => {
        await StorefrontAccountRecover.emailInput.fill(customer.email);
        await StorefrontAccountRecover.requestEmailButton.click();
    });

    await test.step('Verify the password reset email sent message and navigate back to login', async () => {
        await ShopCustomer.expects(StorefrontAccountRecover.passwordResetEmailSentMessage).toBeVisible();
        await StorefrontAccountRecover.backButton.click();
        await ShopCustomer.expects(StorefrontAccountLogin.loginButton).toBeVisible();
    });
});

test ('As a customer, I can request a new password without existing customer email address.', { tag: '@Account @Password' }, async ({
   ShopCustomer,
   StorefrontAccountLogin,
   StorefrontAccountRecover,
}) => {
    await test.step('Navigate to login page and initiate password recovery', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await StorefrontAccountLogin.forgotPasswordLink.click();
    });

    await test.step('Attempt to request password reset without entering an email', async () => {
        await StorefrontAccountRecover.requestEmailButton.click();
        await ShopCustomer.expects(StorefrontAccountRecover.passwordResetEmailSentMessage).not.toBeVisible();
    });

    await test.step('Request password reset with a non-existing email', async () => {
        await StorefrontAccountRecover.emailInput.fill('test-forgot-password-non-existing@email.net');
        await StorefrontAccountRecover.requestEmailButton.click();
        // Verify that the success message is shown for security reasons
        await ShopCustomer.expects(StorefrontAccountRecover.passwordResetEmailSentMessage).toBeVisible();
    });
});
