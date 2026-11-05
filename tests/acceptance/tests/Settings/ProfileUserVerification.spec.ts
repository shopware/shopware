import { test } from '@fixtures/AcceptanceTest';

test(
    'As an admin, I confirm my password in the verify user modal before my profile is saved.',
    { tag: '@Profile' },
    async ({ ShopAdmin, AdminPage, AdminSession, IdProvider }) => {
        const email = `verified-${IdProvider.getIdPair().id}@example.com`;
        const emailField = AdminPage.getByLabel('Email address', { exact: true });
        const modal = AdminPage.getByRole('dialog', { name: 'Confirm password' });
        // the profile page renders its form only after the user, locales and timezones are loaded
        const pageLoad = { timeout: 30_000 };

        await test.step('Saving the profile asks for the password', async () => {
            await ShopAdmin.goesTo('#/sw/profile/index/general');
            // the profile loads asynchronously and would overwrite a value typed before it arrived
            await ShopAdmin.expects(emailField).toHaveValue(AdminSession.user.email, pageLoad);

            await emailField.fill(email);
            await AdminPage.getByRole('button', { name: 'Save', exact: true }).click();

            await ShopAdmin.expects(modal).toBeVisible();
        });

        await test.step('The confirmed password lets the save go through', async () => {
            const userSaved = AdminPage.waitForResponse(
                (response) =>
                    response.request().method() === 'PATCH' && response.url().endsWith(`/api/user/${AdminSession.user.id}`),
            );

            await modal.getByLabel('Password', { exact: true }).fill(AdminSession.user.password);
            await modal.getByRole('button', { name: 'Confirm', exact: true }).click();

            ShopAdmin.expects((await userSaved).ok()).toBe(true);
            await ShopAdmin.expects(modal).toBeHidden();
        });

        await test.step('The profile keeps the change', async () => {
            await AdminPage.reload();

            await ShopAdmin.expects(emailField).toHaveValue(email, pageLoad);
        });
    },
);
