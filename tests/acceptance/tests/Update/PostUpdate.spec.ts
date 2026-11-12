import { test, expect } from '@fixtures/AcceptanceTest';

// Runs after Update.spec.ts on the updated shop. In the major run the workflow has enabled the
// upcoming major flag and run its migrations before, so the shop has to work with the flag active.
// The entity and smoke checks are added in https://github.com/shopware/shopware/issues/22863.
test(
    `Check the Shopware instance updated from ${process.env.SHOPWARE_UPDATE_FROM}.`,
    { tag: '@PostUpdate' },
    async ({ page, AdminApiContext, InstanceMeta }) => {
        const expectMajor = process.env.SHOPWARE_UPDATE_MAJOR === '1';

        expect(InstanceMeta.features['V6_8_0_0'] ?? false, 'state of the V6_8_0_0 flag').toBe(expectMajor);

        const storefrontResponse = await page.request.get(process.env.APP_URL);
        expect(storefrontResponse.status(), 'Storefront home page status').toBe(200);

        const firstRunWizardResponse = await AdminApiContext.post('./_action/store/frw/finish');
        expect(firstRunWizardResponse.ok(), '/_action/store/frw/finish request failed').toBeTruthy();

        await page.goto(process.env.ADMIN_URL);
        await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
            timeout: 60000,
        });

        await page.locator('css=.sw-admin-menu__user-actions-toggle').click();
        await expect(page.locator('css=.sw-version__info').first()).toContainText(InstanceMeta.version, {
            timeout: 60000,
        });
    },
);
