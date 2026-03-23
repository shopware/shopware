import type { APIResponse, Page, Response } from '@playwright/test';
import type { FixtureTypes } from '@shopware-ag/acceptance-test-suite';

type AdminApiContextLike = Pick<FixtureTypes['AdminApiContext'], 'post' | 'delete'>;

async function expectApiSuccess(response: APIResponse | Response, action: string): Promise<void> {
    if (response.ok()) {
        return;
    }

    throw new Error(`Failed to ${action}: ${response.status()} ${response.statusText()}`);
}

async function finishFirstRunWizard(adminApiContext: AdminApiContextLike): Promise<void> {
    const response = await adminApiContext.post('./_action/store/frw/finish', {
        data: {
            failed: false,
        },
    });

    await expectApiSuccess(response, 'finish first run wizard');
}

async function clearDelayedCache(adminApiContext: AdminApiContextLike): Promise<void> {
    const response = await adminApiContext.delete('./_action/cache-delayed');

    await expectApiSuccess(response, 'clear delayed cache');
}

async function refreshAdministration(page: Page): Promise<void> {
    const { origin, pathname } = new URL(page.url());

    await page.goto(`${origin}${pathname}`);
}

async function waitForAdministrationReady(page: Page): Promise<void> {
    const currentUserResponsePromise = page.waitForResponse((response) => {
        return response.request().method() === 'GET' && response.url().includes('/api/_info/me');
    });

    const consentsResponsePromise = page.waitForResponse((response) => {
        return response.request().method() === 'GET' && response.url().includes('/api/consents');
    });

    await refreshAdministration(page);

    const [currentUserResponse, consentsResponse] = await Promise.all([
        currentUserResponsePromise,
        consentsResponsePromise,
    ]);

    await expectApiSuccess(currentUserResponse, 'load current admin user');
    await expectApiSuccess(consentsResponse, 'load consent states');

    await page.waitForURL((url) => {
        return url.hash.startsWith('/sw/dashboard/index') || url.hash.startsWith('#/sw/dashboard/index');
    });

    await page.locator('main').waitFor({ state: 'visible' });

    const loadingSkeleton = page.locator('.sw-skeleton');

    if (await loadingSkeleton.count() > 0) {
        await loadingSkeleton.first().waitFor({ state: 'hidden' });
    }
}

export async function prepareProductAnalyticsConsentModal(
    page: Page,
    adminApiContext: AdminApiContextLike,
): Promise<void> {
    await finishFirstRunWizard(adminApiContext);
    await clearDelayedCache(adminApiContext);
    await waitForAdministrationReady(page);
}
