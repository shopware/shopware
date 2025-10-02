import { test } from '@fixtures/AcceptanceTest';
import { satisfies } from 'compare-versions';
import { expect } from '@playwright/test';

test(
    'As a merchant, I want to see an advertisement banner for Shopware Services on the dashboard.', { tag: '@Settings' }, async ({
        ShopAdmin,
        AdminDashboard,
        AdminShopwareServices,
        InstanceMeta,
        }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');

        await ShopAdmin.goesTo(AdminDashboard.url());
        await ShopAdmin.expects(AdminDashboard.shopwareServicesAdvertisementBanner).toBeVisible();
        await ShopAdmin.expects(AdminDashboard.shopwareServicesAdvertisementBanner).toContainText('Introducing Shopware Services');
        await ShopAdmin.expects(AdminDashboard.shopwareServicesExploreNowButton).toBeVisible();
        await AdminDashboard.shopwareServicesExploreNowButton.click();
        await ShopAdmin.expects(AdminShopwareServices.header).toBeVisible();
    });

test(
    'As a merchant, I want to hide the advertisement banner for Shopware Services on the dashboard.', { tag: '@Settings' }, async ({
        ShopAdmin,
        AdminDashboard,
        AdminSettingsListing,
        CheckVisibilityOfServicesBanner,
        InstanceMeta,
        }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');

        await ShopAdmin.goesTo(AdminDashboard.url());
        await ShopAdmin.expects(AdminDashboard.shopwareServicesAdvertisementBanner).toBeVisible();
        await AdminDashboard.shopwareServicesAdvertisementBannerCloseButton.click();
        await ShopAdmin.expects(AdminDashboard.shopwareServicesAdvertisementBanner).not.toBeVisible();
        await ShopAdmin.goesTo(AdminSettingsListing.url());
        await ShopAdmin.expects(AdminSettingsListing.shopwareServicesLink).toBeVisible();
        await ShopAdmin.goesTo(AdminDashboard.url());
        await ShopAdmin.expects(AdminDashboard.shopwareServicesAdvertisementBanner).not.toBeVisible();

        await test.step('Verify the visibility of the services banner for another admin user', async () => {
            await ShopAdmin.attemptsTo(CheckVisibilityOfServicesBanner());
        });
    });

test(
    'As a merchant, I want to fully deactivate the Shopware Services feature.', { tag: '@Settings' }, async ({
        ShopAdmin,
        AdminShopwareServices,
        DeactivateShopwareServices,
        InstanceMeta,
        }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');
        test.skip(InstanceMeta.isSaaS, 'Shopware Services deactivation could run into race conditions on SaaS instances.');

        await ShopAdmin.goesTo(AdminShopwareServices.url());
        await ShopAdmin.expects(AdminShopwareServices.header).toHaveText('Future proof your store with Shopware Services');
        const disableResponsePromise = AdminShopwareServices.page.waitForResponse(`${ process.env['APP_URL'] }api/services/disable`);
        await AdminShopwareServices.deactivateServicesButton.click();
        await ShopAdmin.expects(AdminShopwareServices.deactivateServicesModal).toBeVisible();
        await AdminShopwareServices.deactivateServicesConfirmButton.click();
        await ShopAdmin.expects(AdminShopwareServices.deactivatedBanner).toBeVisible({ timeout: 15000 });

        const disableResponse = await disableResponsePromise;
        expect(disableResponse.ok()).toBeTruthy();

        await ShopAdmin.expects(AdminShopwareServices.activateServicesButton).toBeVisible();
        await ShopAdmin.expects(AdminShopwareServices.permissionBanner).not.toBeVisible();
        await ShopAdmin.expects(AdminShopwareServices.serviceCards).not.toBeVisible();
        // enable the services again for further tests
        const enableResponsePromise = AdminShopwareServices.page.waitForResponse(`${ process.env['APP_URL'] }api/services/enable`);
        await AdminShopwareServices.activateServicesButton.click();
        const enableResponse = await enableResponsePromise;
        expect(enableResponse.ok()).toBeTruthy();
        await AdminShopwareServices.page.reload();
        await ShopAdmin.expects(AdminShopwareServices.deactivateServicesButton).toBeVisible({ timeout: 15000 });
    });

test(
    'As a merchant, I can manage Shopware Services only if the necessary permissions are granted.', { tag: '@Settings' }, async ({
        ShopAdmin,
        TestDataService,
        CheckAccessToShopwareServices,
        InstanceMeta,
    }) => {
        test.skip(satisfies(InstanceMeta.version, '<6.7.1'), 'Feature not available until version 6.7.1.0');

        await test.step('Verify insufficient permissions prevent access to services.', async () => {
            let aclRole;
            // Create a role with insufficient permissions
            // eslint-disable-next-line playwright/no-conditional-in-test
            if (InstanceMeta.isSaaS) {
                const privileges = [
                    'cms_page:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                    'language:read',
                    'locale:read',
                    'log_entry:create',
                    'message_queue_stats:read',
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
                    'language:read',
                    'locale:read',
                    'log_entry:create',
                    'message_queue_stats:read',
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
                    'language:read',
                    'locale:read',
                    'log_entry:create',
                    'message_queue_stats:read',
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
    });
