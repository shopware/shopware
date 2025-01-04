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

test ('As a customer, I can reset my password using the password recovery process for an existing account and successfully log in with the new password.', { tag: '@Account @Password' }, async ({
    ShopCustomer,
    StorefrontAccountLogin,
    StorefrontAccountRecover,
    TestDataService,
    MailpitApiContext,
    InstanceMeta,
    Login,
    DefaultSalesChannel,
}) => {
    test.skip(InstanceMeta.isSaaS, 'Skipping test because it requires a local mailpit instance.');

    let passwordResetLink = '';
    const newPassword = 'new-password';
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

    await test.step('Verify email headers and content', async () => {
        const emailHeaders = await MailpitApiContext.getEmailHeaders(customer.email);
        const emailBody = await MailpitApiContext.getEmailBody(customer.email);
        ShopCustomer.expects(emailHeaders.subject).toContain('Password recovery');
        ShopCustomer.expects(emailHeaders.toName).toContain(`${customer.firstName} ${customer.lastName}`);
        ShopCustomer.expects(emailHeaders.toAddress).toContain(customer.email);
        ShopCustomer.expects(emailBody).toContain(`Hello ${customer.firstName} ${customer.lastName},`);
        ShopCustomer.expects(emailBody).toContain(`You have requested a new password for your ${DefaultSalesChannel.salesChannel.name} account.`);
        ShopCustomer.expects(emailBody).toContain('Click on the following link to reset your password:');
        ShopCustomer.expects(emailBody).toContain('This link is valid for the next 2 hours.');
        ShopCustomer.expects(emailBody).toContain('If you don\'t want to reset your password, ignore this email and no changes will be made.');
        ShopCustomer.expects(emailBody).toContain('Yours sincerely');
        ShopCustomer.expects(emailBody).toContain(`Your ${DefaultSalesChannel.salesChannel.name} team`);
    });

    await test.step('Update password using link in the email and login with new password successfully', async () => {
        passwordResetLink = await MailpitApiContext.getLinkFromMail(customer.email);
        await ShopCustomer.goesTo(StorefrontAccountRecover.url(passwordResetLink));
        await StorefrontAccountRecover.newPasswordInput.fill(newPassword);
        await StorefrontAccountRecover.newPasswordConfirmInput.fill(newPassword);
        await StorefrontAccountRecover.changePasswordButton.click();
        await ShopCustomer.expects(StorefrontAccountLogin.passwordUpdatedAlert).toBeVisible();
        customer.password = newPassword;
        await ShopCustomer.expects(StorefrontAccountLogin.loginButton).toBeVisible();
        await ShopCustomer.attemptsTo(Login(customer));
    });

    await test.step('Verify that the recovery link is invalid after used. Request form displays so user can request again.', async () => {
        await ShopCustomer.goesTo(StorefrontAccountRecover.url(passwordResetLink));
        await ShopCustomer.expects(StorefrontAccountRecover.invalidLinkMessage).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountRecover.emailInput).toBeVisible();
        await ShopCustomer.expects(StorefrontAccountRecover.requestEmailButton).toBeVisible();
    });
});
