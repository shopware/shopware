import type { APIResponse, Page, Response } from '@playwright/test';
import type { FixtureTypes } from '@shopware-ag/acceptance-test-suite';

type AdminApiContextLike = Pick<FixtureTypes['AdminApiContext'], 'post' | 'delete'>;
type ConsentAction = 'accept' | 'revoke';
type ConsentName = 'backend_data' | 'product_analytics';

const SETTINGS_PRIVACY_ROUTE = '#/sw/settings/usage/data/index/general';
const PROFILE_PRIVACY_ROUTE = '#/sw/profile/index/privacy-preferences';
const DASHBOARD_ROUTE = '#/sw/dashboard/index';
const STORE_DATA_CHECKBOX = /Share store data \(anonymous\)|Shopdaten teilen \(anonym\)/;
const USER_DATA_CHECKBOX = /Share personal data|Persönliche Daten teilen/;

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

async function waitForConsentResponse(
    page: Page,
    action: ConsentAction,
    consent: ConsentName,
): Promise<Response> {
    return page.waitForResponse((response: Response) => {
        if (!response.url().includes(`/api/consents/${action}`)) {
            return false;
        }

        if (response.request().method() !== 'POST') {
            return false;
        }

        try {
            return response.request().postDataJSON()?.consent === consent;
        } catch {
            return false;
        }
    });
}

async function navigateToAdministrationRoute(page: Page, route: string): Promise<void> {
    await page.goto(route);
    await page.waitForURL((url) => url.hash.startsWith(route.slice(1)) || url.hash.startsWith(route));
    await page.locator('main').waitFor({ state: 'visible' });
}

async function ensureConsentCheckboxState(
    page: Page,
    route: string,
    label: RegExp,
    consent: ConsentName,
    desiredState: boolean,
): Promise<void> {
    await navigateToAdministrationRoute(page, route);

    const checkbox = page.getByLabel(label);
    const currentState = await checkbox.isChecked();

    if (currentState === desiredState) {
        return;
    }

    const responsePromise = waitForConsentResponse(page, desiredState ? 'accept' : 'revoke', consent);

    await checkbox.click();

    await expectApiSuccess(await responsePromise, `${desiredState ? 'accept' : 'revoke'} ${consent}`);
    await checkbox.waitFor({ state: 'visible' });
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

export async function setProductAnalyticsConsentState(
    page: Page,
    adminApiContext: AdminApiContextLike,
    desiredState: boolean,
): Promise<void> {
    await finishFirstRunWizard(adminApiContext);
    await ensureConsentCheckboxState(page, SETTINGS_PRIVACY_ROUTE, STORE_DATA_CHECKBOX, 'backend_data', desiredState);
    await ensureConsentCheckboxState(page, PROFILE_PRIVACY_ROUTE, USER_DATA_CHECKBOX, 'product_analytics', desiredState);
    await navigateToAdministrationRoute(page, DASHBOARD_ROUTE);
}
