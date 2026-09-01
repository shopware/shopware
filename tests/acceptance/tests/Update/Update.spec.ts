import { test, expect } from '@fixtures/AcceptanceTest';

test(
    `Update an existing Shopware ${process.env.SHOPWARE_UPDATE_FROM} instance.`,
    { tag: '@Update' },
    async ({ page, AdminApiContext }) => {
        test.slow();

        await page.goto(process.env.ADMIN_URL);

        await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
            timeout: 20000,
        });

        (await (await AdminApiContext.get(`./_info/config`)).json()) as {
            version: string;
        };

        await page.goto('#/sw/settings/shopware/updates/wizard');

        await page.getByRole('button', { name: 'Start update' }).click();

        await page.getByLabel("Yes, I've created a backup.").check();
        await page.getByRole('button', { name: 'Continue' }).click();
        await page.waitForLoadState('domcontentloaded');

        await page.getByRole('link', { name: 'Continue' }).click();
        await page.waitForLoadState('domcontentloaded');

        await page.getByRole('button', { name: 'Continue' }).click();
        await page.waitForLoadState('domcontentloaded');

        await page.getByRole('button', { name: 'Update Shopware' }).click();

        const response = await page.waitForResponse((response) => response.url().includes('/update/_finish'), {
            timeout: 120000,
        });
        expect(response.status()).toBe(200);

        await page.screenshot();

        await expect(page.getByRole('heading', { name: 'Finish' })).toBeVisible({ timeout: 120000 });

        await page.getByRole('button', { name: 'Open Administration' }).click();

        const versionResponse = await AdminApiContext.get('./_info/config');
        expect(versionResponse.ok(), '/_info/config request failed').toBeTruthy();
        const config = (await versionResponse.json()) as { version: string };

        // The updated instance re-enables the first run wizard, whose modal cannot be closed from
        // the UI and covers the admin menu. Mark it as finished before interacting with the menu.
        const firstRunWizardResponse = await AdminApiContext.post('./_action/store/frw/finish');
        expect(firstRunWizardResponse.ok(), '/_action/store/frw/finish request failed').toBeTruthy();
        await page.reload();

        // test admin login
        // Wait until the page is loaded
        await expect(page.locator('css=.sw-admin-menu__header-logo').first()).toBeVisible({
            timeout: 60000,
        });

        // The version is shown inside the user actions menu in the admin menu footer.
        await page.locator('css=.sw-admin-menu__user-actions-toggle').click();
        await expect(page.locator('css=.sw-version__info').first()).toContainText(`${config.version}`, {
            timeout: 60000,
        });
    },
);
