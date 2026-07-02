import { test, expect, createNewAdminPageContext } from '@fixtures/AcceptanceTest';
import {
    createAdminAuthUser,
    deleteAdminAuthUser,
    enrollTotp,
    expectLoggedIn,
    fetchUserVerifiedToken,
    generateRecoveryCodes,
    mfaHeadline,
    submitPasswordLogin,
} from '@helpers/admin-auth-helpers';

/**
 * Requires the ADMIN_AUTH feature flag to be active on the system under test (ADMIN_AUTH=1).
 *
 * Recovery codes are a fallback second factor: they only become usable once a non-fallback factor
 * (here: TOTP) is enrolled, and every code is valid exactly once.
 */
test(
    'As an admin user, I can complete the two-factor login with a recovery code, but only once per code.',
    { tag: '@AdminAuth' },
    async ({ request, browser, IdProvider, InstanceMeta, SalesChannelBaseConfig }) => {
        test.skip(!InstanceMeta.features['ADMIN_AUTH'], 'ADMIN_AUTH feature flag is not active on the system under test.');

        const user = await createAdminAuthUser(request, IdProvider.getIdPair(), SalesChannelBaseConfig.currentLocaleId);
        let recoveryCodes: string[] = [];

        try {
            await test.step('Enroll a TOTP authenticator and generate recovery codes via the MFA self-service API.', async () => {
                const userVerifiedToken = await fetchUserVerifiedToken(request, user.username, user.password);
                await enrollTotp(request, userVerifiedToken);
                recoveryCodes = await generateRecoveryCodes(request, userVerifiedToken);
            });

            const firstLoginPage = await createNewAdminPageContext(browser, SalesChannelBaseConfig);

            await test.step('Complete the two-factor login with a recovery code.', async () => {
                await submitPasswordLogin(firstLoginPage, user.username, user.password);
                await expect(mfaHeadline(firstLoginPage)).toBeVisible();

                await firstLoginPage.getByRole('button', { name: 'Use a recovery code instead' }).click();
                await firstLoginPage.getByLabel('Recovery code').fill(recoveryCodes[0]);
                await firstLoginPage.getByRole('button', { name: 'Verify', exact: true }).click();

                await expectLoggedIn(firstLoginPage, user);
            });

            await firstLoginPage.context().close();

            const secondLoginPage = await createNewAdminPageContext(browser, SalesChannelBaseConfig);

            await test.step('The same recovery code is rejected on a second login.', async () => {
                await submitPasswordLogin(secondLoginPage, user.username, user.password);
                await expect(mfaHeadline(secondLoginPage)).toBeVisible();

                await secondLoginPage.getByRole('button', { name: 'Use a recovery code instead' }).click();
                await secondLoginPage.getByLabel('Recovery code').fill(recoveryCodes[0]);
                await secondLoginPage.getByRole('button', { name: 'Verify', exact: true }).click();

                await expect(
                    secondLoginPage.getByText('The code is invalid or has expired. Please try again.')
                ).toBeVisible();
                await expect(mfaHeadline(secondLoginPage)).toBeVisible();
            });

            await secondLoginPage.context().close();
        } finally {
            await deleteAdminAuthUser(request, user);
        }
    }
);
