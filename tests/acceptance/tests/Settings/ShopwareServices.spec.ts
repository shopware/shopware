import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';
import { expect } from '@playwright/test';

test.describe('Shopware Services', () => {
    test.describe.configure({ mode: 'serial' });

    let initialServicesState: boolean;

    test.beforeAll(async ({ ShopAdmin, AdminShopwareServices, InstanceMeta }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');

        await ShopAdmin.goesTo(AdminShopwareServices.url());
        initialServicesState = await AdminShopwareServices.deactivateServicesButton
            .isVisible({ timeout: 5000 })
            .catch(() => false);
    });

    test.afterAll(async ({ ShopAdmin, AdminShopwareServices, InstanceMeta }) => {
        if (satisfies(InstanceMeta.version, '>=6.7.1')) {
            try {
                await ShopAdmin.goesTo(AdminShopwareServices.url());
                const isCurrentlyActive = await AdminShopwareServices.deactivateServicesButton
                    .isVisible()
                    .catch(() => false);

                if (initialServicesState && !isCurrentlyActive) {
                    const enableResponsePromise = AdminShopwareServices.page.waitForResponse(
                        (response) =>
                            response.url().includes('/api/services/enable') && response.request().method() === 'POST',
                        { timeout: 20000 },
                    );
                    await AdminShopwareServices.activateServicesButton.click();
                    await enableResponsePromise;
                    await AdminShopwareServices.page.reload();
                    await ShopAdmin.expects(AdminShopwareServices.deactivateServicesButton).toBeVisible({
                        timeout: 15000,
                    });
                }
            } catch (error) {
                console.error('Failed to restore Shopware Services state:', error);
            }
        }
    });

    // eslint-disable-next-line playwright/no-skipped-test
    test.skip(
        'As a merchant, I want to fully deactivate the Shopware Services feature.',
        {
            tag: '@Settings',
            annotation: {
                type: 'issue',
                description: 'https://github.com/shopware/shopware/issues/17082',
            },
        },
        async ({ ShopAdmin, AdminShopwareServices, InstanceMeta }) => {
            test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');

            await ShopAdmin.goesTo(AdminShopwareServices.url());

            await ShopAdmin.expects(AdminShopwareServices.header).toBeVisible({ timeout: 10000 });
            await ShopAdmin.expects(AdminShopwareServices.header).toHaveText(
                'Future proof your store with Shopware Services',
            );

            await ShopAdmin.expects(AdminShopwareServices.deactivateServicesButton).toBeVisible();
            await ShopAdmin.expects(AdminShopwareServices.deactivateServicesButton).toBeEnabled();

            const disableResponsePromise = AdminShopwareServices.page.waitForResponse(
                (response) => response.url().includes('/api/services/disable') && response.request().method() === 'POST',
                { timeout: 20000 },
            );

            await AdminShopwareServices.deactivateServicesButton.click();
            await ShopAdmin.expects(AdminShopwareServices.deactivateServicesModal).toBeVisible();
            await AdminShopwareServices.deactivateServicesConfirmButton.click();

            const disableResponse = await disableResponsePromise;
            expect(disableResponse.ok()).toBeTruthy();

            await ShopAdmin.expects(AdminShopwareServices.deactivatedBanner).toBeVisible({ timeout: 15000 });
            await ShopAdmin.expects(AdminShopwareServices.activateServicesButton).toBeVisible();
            await ShopAdmin.expects(AdminShopwareServices.permissionBanner).not.toBeVisible();
            await ShopAdmin.expects(AdminShopwareServices.serviceCards).not.toBeVisible();

            const enableResponsePromise = AdminShopwareServices.page.waitForResponse(
                (response) => response.url().includes('/api/services/enable') && response.request().method() === 'POST',
                { timeout: 20000 },
            );

            await AdminShopwareServices.activateServicesButton.click();
            const enableResponse = await enableResponsePromise;
            expect(enableResponse.ok()).toBeTruthy();

            await AdminShopwareServices.page.reload();
            await ShopAdmin.expects(AdminShopwareServices.deactivateServicesButton).toBeVisible({ timeout: 15000 });
            await ShopAdmin.expects(AdminShopwareServices.header).toBeVisible();
        },
    );

    test(
        'As a merchant, I can manage Shopware Services only if the necessary permissions are granted.',
        { tag: '@Settings' },
        async ({ ShopAdmin, TestDataService, CheckAccessToShopwareServices, InstanceMeta }) => {
            test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');
            // The `CheckAccessToShopwareServices` task enters the services page through the dashboard
            // advertisement banner, which was removed from the dashboard. Re-enable once the task in
            // @shopware-ag/acceptance-test-suite navigates to `sw.settings.services.index` directly.
            test.skip(true, 'Task depends on the removed services dashboard banner.');

            await test.step('Verify insufficient permissions prevent access to services.', async () => {
                let aclRole;
                // Create a role with insufficient permissions
                // eslint-disable-next-line playwright/no-conditional-in-test
                if (InstanceMeta.isSaaS) {
                    const privileges = [
                        'cms_page:read',
                        'custom_field:read',
                        'custom_field_set_relation:read',
                        'product_sorting:create',
                        'product_sorting:delete',
                        'product_sorting:read',
                        'product_sorting:update',
                        'sales_channel:read',
                        'seo_url_template:create',
                        'seo_url_template:read',
                        'seo_url_template:update',
                        'system.system_config',
                        'system_config:create',
                        'system_config:delete',
                        'system_config:read',
                        'system_config:update',
                        'sales_channel_domain:read',
                        'swag_language_pack_language:read',
                    ];
                    aclRole = await TestDataService.createAclRole({ privileges: privileges });
                } else {
                    aclRole = await TestDataService.createAclRole();
                }
                const user = await TestDataService.createUser();
                await TestDataService.assignAclRoleUser(aclRole.id, user.id);

                await ShopAdmin.attemptsTo(CheckAccessToShopwareServices(user, aclRole));
            });

            await test.step('Verify minimum permissions are enough to manage Shopware Services.', async () => {
                let aclRole;
                // Basic permissions to access the services
                // eslint-disable-next-line playwright/no-conditional-in-test
                if (InstanceMeta.isSaaS) {
                    const privileges = [
                        'cms_page:read',
                        'custom_field:read',
                        'custom_field_set_relation:read',
                        'plugin:update',
                        'product_sorting:create',
                        'product_sorting:delete',
                        'product_sorting:read',
                        'product_sorting:update',
                        'sales_channel:read',
                        'seo_url_template:create',
                        'seo_url_template:read',
                        'seo_url_template:update',
                        'system.plugin_maintain',
                        'system.system_config',
                        'system:clear:cache',
                        'system:plugin:maintain',
                        'system_config:create',
                        'system_config:delete',
                        'system_config:read',
                        'system_config:update',
                        'sales_channel_domain:read',
                        'swag_language_pack_language:read',
                    ];
                    aclRole = await TestDataService.createAclRole({ privileges: privileges });
                } else {
                    const privileges = [
                        'cms_page:read',
                        'custom_field:read',
                        'custom_field_set_relation:read',
                        'plugin:update',
                        'product_sorting:create',
                        'product_sorting:delete',
                        'product_sorting:read',
                        'product_sorting:update',
                        'sales_channel:read',
                        'seo_url_template:create',
                        'seo_url_template:read',
                        'seo_url_template:update',
                        'system.plugin_maintain',
                        'system.system_config',
                        'system:clear:cache',
                        'system:plugin:maintain',
                        'system_config:create',
                        'system_config:delete',
                        'system_config:read',
                        'system_config:update',
                    ];
                    aclRole = await TestDataService.createAclRole({ privileges: privileges });
                }
                const user = await TestDataService.createUser();
                await TestDataService.assignAclRoleUser(aclRole.id, user.id);

                await ShopAdmin.attemptsTo(CheckAccessToShopwareServices(user, aclRole));
            });
        },
    );
});
