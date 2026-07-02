import { test, expect, createNewAdminPageContext } from '@fixtures/AcceptanceTest';
import {
    createAdminAuthUser,
    deleteAdminAuthUser,
    enrollTotp,
    expectLoggedIn,
    fetchUserVerifiedToken,
    mfaHeadline,
    submitPasswordLogin,
    totpCode,
    wrongTotpCode,
} from '@helpers/admin-auth-helpers';

/**
 * Requires the ADMIN_AUTH feature flag to be active on the system under test (ADMIN_AUTH=1).
 */
test(
    'As an admin user with an enrolled authenticator app, I must confirm a TOTP code to log in.',
    { tag: '@AdminAuth' },
    async ({ request, browser, IdProvider, InstanceMeta, SalesChannelBaseConfig }) => {
        test.skip(!InstanceMeta.features['ADMIN_AUTH'], 'ADMIN_AUTH feature flag is not active on the system under test.');

        const user = await createAdminAuthUser(request, IdProvider.getIdPair(), SalesChannelBaseConfig.currentLocaleId);
        let secret = '';

        try {
            await test.step('Enroll a TOTP authenticator via the MFA self-service API.', async () => {
                const userVerifiedToken = await fetchUserVerifiedToken(request, user.username, user.password);
                secret = await enrollTotp(request, userVerifiedToken);
            });

            const adminPage = await createNewAdminPageContext(browser, SalesChannelBaseConfig);

            await test.step('Log in with username and password.', async () => {
                await submitPasswordLogin(adminPage, user.username, user.password);
            });

            await test.step('The two-factor step is shown instead of the dashboard.', async () => {
                await expect(mfaHeadline(adminPage)).toBeVisible();
                await expect(adminPage.getByLabel('Authentication code')).toBeVisible();
            });

            await test.step('A wrong code shows an error and keeps the two-factor step open.', async () => {
                await adminPage.getByLabel('Authentication code').fill(wrongTotpCode(secret));
                await adminPage.getByRole('button', { name: 'Verify', exact: true }).click();

                await expect(
                    adminPage.getByText('The code is invalid or has expired. Please try again.')
                ).toBeVisible();
                await expect(mfaHeadline(adminPage)).toBeVisible();
            });

            await test.step('The correct authenticator code completes the login.', async () => {
                await adminPage.getByLabel('Authentication code').fill(totpCode(secret));
                await adminPage.getByRole('button', { name: 'Verify', exact: true }).click();

                await expectLoggedIn(adminPage, user);
            });

            await adminPage.context().close();
        } finally {
            await deleteAdminAuthUser(request, user);
        }
    }
);
