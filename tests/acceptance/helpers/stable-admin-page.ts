import type { APIResponse } from '@playwright/test';
import type { FixtureTypes } from '@shopware-ag/acceptance-test-suite';
import { AdminPageObjects, createNewAdminPageContext } from '@shopware-ag/acceptance-test-suite';

type AdminApiContextLike = Pick<FixtureTypes['AdminApiContext'], 'get'>;
type BrowserLike = FixtureTypes['browser'];
type SalesChannelBaseConfigLike = Pick<FixtureTypes['SalesChannelBaseConfig'], 'adminUrl'>;

interface ConfigResponse {
    bundles?: Record<string, { js?: string | string[] }>;
}

function normalizeToArray(value?: string | string[]): string[] {
    if (!value) {
        return [];
    }

    return Array.isArray(value) ? value : [value];
}

function expectApiSuccess(response: APIResponse, action: string): void {
    if (response.ok()) {
        return;
    }

    throw new Error(`Failed to ${action}: ${response.status()} ${response.statusText()}`);
}

async function loginWithDefaultAdmin(
    page: FixtureTypes['AdminPage'],
    adminApiContext: AdminApiContextLike,
): Promise<void> {
    const usernamePattern = /Benutzername|E-Mail-Adresse|Username|Email address/;
    const passwordPattern = /Passwort|Password/;
    const loginButtonPattern = /Anmelden|Log in|Login/;

    await page.getByLabel(usernamePattern).fill(process.env['SHOPWARE_ADMIN_USERNAME'] || 'admin');
    await page.getByLabel(passwordPattern, { exact: true }).fill(process.env['SHOPWARE_ADMIN_PASSWORD'] || 'shopware');

    const configResponse = await adminApiContext.get('./_info/config');
    expectApiSuccess(configResponse, 'load admin configuration');

    const config = await configResponse.json() as ConfigResponse;
    const jsLoadingPromises: Promise<unknown>[] = [];

    for (const bundle of Object.values(config.bundles || {})) {
        for (const url of normalizeToArray(bundle.js)) {
            jsLoadingPromises.push(page.waitForResponse(url));
        }
    }

    await page.getByRole('button', { name: loginButtonPattern }).click();
    await Promise.all(jsLoadingPromises);
    await page.waitForURL((url) => url.hash !== '#login' && url.hash !== '#/login');
    await page.locator('main').waitFor({ state: 'visible' });

    const loadingSkeleton = page.locator('.sw-skeleton');

    if (await loadingSkeleton.count() > 0) {
        await loadingSkeleton.first().waitFor({ state: 'hidden' });
    }
}

export async function createStableAdminPage(
    browser: BrowserLike,
    salesChannelBaseConfig: SalesChannelBaseConfigLike,
    adminApiContext: AdminApiContextLike,
): Promise<{
    page: FixtureTypes['AdminPage'];
    adminDashboard: InstanceType<typeof AdminPageObjects.Dashboard>;
}> {
    const page = await createNewAdminPageContext(browser, salesChannelBaseConfig);

    await loginWithDefaultAdmin(page, adminApiContext);

    return {
        page,
        adminDashboard: new AdminPageObjects.Dashboard(page),
    };
}
