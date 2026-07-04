import { test, expect, createNewAdminPageContext } from '@fixtures/AcceptanceTest';

/**
 * Requires the ADMIN_AUTH feature flag to be active on the system under test (ADMIN_AUTH=1).
 */
test(
    'As a merchant, I can configure an OIDC provider with a group to role mapping for the admin login.',
    { tag: '@AdminAuth' },
    async ({
        ShopAdmin,
        AdminPage,
        AdminApiContext,
        TestDataService,
        IdProvider,
        InstanceMeta,
        SalesChannelBaseConfig,
        browser,
    }) => {
        test.skip(!InstanceMeta.features['ADMIN_AUTH'], 'ADMIN_AUTH feature flag is not active on the system under test.');

        const methodsProbe = await AdminApiContext.get('./_action/admin-auth/methods');
        expect(methodsProbe.ok()).toBeTruthy();
        const methodsProbeBody = (await methodsProbe.json()) as { managedByConfig: boolean };
        test.skip(
            methodsProbeBody.managedByConfig,
            'Providers are managed via the shopware.admin_auth.providers configuration file; the admin UI is read-only.'
        );

        const providerName = `ATS OIDC ${IdProvider.getIdPair().id}`;
        const idpGroup = 'idp-catalog';
        const adminRoleLabel = 'Administrator (admin flag)';
        let providerId = '';

        await test.step('Create an OIDC provider via the settings module.', async () => {
            await ShopAdmin.goesTo('#/sw/settings/admin/auth/provider/create');

            await AdminPage.getByLabel('Name', { exact: true }).fill(providerName);
            await AdminPage.getByLabel('Client ID', { exact: true }).fill('acceptance-test-client');
            await AdminPage.getByLabel('Client secret', { exact: true }).fill('acceptance-test-secret');
            await AdminPage.getByLabel('Issuer', { exact: true }).fill('https://idp.example.com');
            await AdminPage.getByLabel('Authorization endpoint', { exact: true }).fill('https://idp.example.com/oauth2/authorize');
            await AdminPage.getByLabel('Token endpoint', { exact: true }).fill('https://idp.example.com/oauth2/token');
            await AdminPage.getByLabel('JWKS URI', { exact: true }).fill('https://idp.example.com/oauth2/jwks');

            await AdminPage.locator('.sw-settings-admin-auth-provider-detail__save-action').click();

            await AdminPage.waitForURL('**/sw/settings/admin/auth/provider/detail/**');
            providerId = AdminPage.url().split('/').pop() as string;
            expect(providerId).toMatch(/^[0-9a-f]{32}$/);
            TestDataService.addCreatedRecord('admin_auth_provider', providerId);
        });

        await test.step('The provider appears in the provider list.', async () => {
            await ShopAdmin.goesTo('#/sw/settings/admin/auth/providers');
            await ShopAdmin.expects(
                AdminPage.locator('.sw-settings-admin-auth-provider-list__grid').getByText(providerName)
            ).toBeVisible();
        });

        await test.step('The login methods endpoint offers the provider to the login screen.', async () => {
            const response = await AdminApiContext.get('./_action/admin-auth/methods');
            expect(response.ok()).toBeTruthy();

            const body = (await response.json()) as {
                methods: { type: string; label: string | null; startUrl: string | null }[];
            };
            const providerMethod = body.methods.find(
                (method) => method.type === 'oidc' && method.label === providerName
            );

            expect(providerMethod, 'Provider should be offered as a login method').toBeTruthy();
            expect(providerMethod?.startUrl).toContain('/_action/admin-auth/oidc/');
        });

        await test.step('A provider button is shown on the admin login screen.', async () => {
            const loginPage = await createNewAdminPageContext(browser, SalesChannelBaseConfig);
            await expect(loginPage.getByRole('button', { name: providerName })).toBeVisible();
            await loginPage.context().close();
        });

        await test.step('Add a group to role mapping row and save it.', async () => {
            await ShopAdmin.goesTo(`#/sw/settings/admin/auth/provider/detail/${providerId}`);

            await AdminPage.locator('.sw-settings-admin-auth-role-mapping__add-action').click();
            await AdminPage.locator('.sw-settings-admin-auth-role-mapping__group input').fill(idpGroup);

            await AdminPage.locator('.sw-settings-admin-auth-role-mapping__roles').click();
            await AdminPage.locator('.sw-select-result-list__content')
                .getByRole('listitem')
                .filter({ hasText: adminRoleLabel })
                .click();
            await AdminPage.keyboard.press('Escape');

            const saveResponse = AdminPage.waitForResponse(
                (response) => response.url().includes('/admin-auth-provider') && response.ok()
            );
            await AdminPage.locator('.sw-settings-admin-auth-provider-detail__save-action').click();
            await saveResponse;
        });

        await test.step('The role mapping is still there after a reload.', async () => {
            await AdminPage.reload();

            await ShopAdmin.expects(AdminPage.locator('.sw-settings-admin-auth-role-mapping__group input')).toHaveValue(
                idpGroup
            );
            await ShopAdmin.expects(
                AdminPage.locator('.sw-settings-admin-auth-role-mapping__roles').getByText(adminRoleLabel)
            ).toBeVisible();
        });
    }
);
