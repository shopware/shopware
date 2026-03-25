import { test, expect } from '@fixtures/AcceptanceTest';
import type { Page, Response } from '@playwright/test';
import { setProductAnalyticsConsentState } from '../../helpers/product-analytics-consent';

const SETTINGS_PRIVACY_ROUTE = '#/sw/settings/usage/data/index/general';
const PROFILE_PRIVACY_ROUTE = '#/sw/profile/index/privacy-preferences';
const STORE_DATA_CHECKBOX = /Share store data \(anonymous\)|Shopdaten teilen \(anonym\)/;
const USER_DATA_CHECKBOX = /Share personal data|Persönliche Daten teilen/;

function waitForConsentResponse(
    page: Page,
    action: 'accept' | 'revoke',
    consent: 'backend_data' | 'product_analytics',
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

test('Merchant is able accept or decline the data sharing consent.', {
    tag: ['@DataSharing'],
}, async ({
    AdminDashboard,
    AdminApiContext,
}) => {
    const page = AdminDashboard.page;

    await test.step('Start from both accepted consents', async () => {
        await setProductAnalyticsConsentState(page, AdminApiContext, true);
    });

    await test.step('Validate declining and accepting store data consent on privacy settings', async () => {
        await page.goto(SETTINGS_PRIVACY_ROUTE);

        const storeDataConsentCheckbox = page.getByLabel(STORE_DATA_CHECKBOX);

        await expect(storeDataConsentCheckbox).toBeChecked();

        let consentResponsePromise = waitForConsentResponse(page, 'revoke', 'backend_data');
        await storeDataConsentCheckbox.click();
        let response = await consentResponsePromise;
        expect(response.ok()).toBeTruthy();
        await expect(storeDataConsentCheckbox).not.toBeChecked();

        consentResponsePromise = waitForConsentResponse(page, 'accept', 'backend_data');
        await storeDataConsentCheckbox.click();
        response = await consentResponsePromise;
        expect(response.ok()).toBeTruthy();
        await expect(storeDataConsentCheckbox).toBeChecked();

        consentResponsePromise = waitForConsentResponse(page, 'revoke', 'backend_data');
        await storeDataConsentCheckbox.click();
        response = await consentResponsePromise;
        expect(response.ok()).toBeTruthy();
        await expect(storeDataConsentCheckbox).not.toBeChecked();
    });

    await test.step('Validate declining and accepting personal data consent on profile privacy preferences', async () => {
        await page.goto(PROFILE_PRIVACY_ROUTE);

        const personalDataConsentCheckbox = page.getByLabel(USER_DATA_CHECKBOX);

        await expect(personalDataConsentCheckbox).toBeChecked();

        let consentResponsePromise = waitForConsentResponse(page, 'revoke', 'product_analytics');
        await personalDataConsentCheckbox.click();
        let response = await consentResponsePromise;
        expect(response.ok()).toBeTruthy();
        await expect(personalDataConsentCheckbox).not.toBeChecked();

        consentResponsePromise = waitForConsentResponse(page, 'accept', 'product_analytics');
        await personalDataConsentCheckbox.click();
        response = await consentResponsePromise;
        expect(response.ok()).toBeTruthy();
        await expect(personalDataConsentCheckbox).toBeChecked();

        consentResponsePromise = waitForConsentResponse(page, 'revoke', 'product_analytics');
        await personalDataConsentCheckbox.click();
        response = await consentResponsePromise;
        expect(response.ok()).toBeTruthy();
        await expect(personalDataConsentCheckbox).not.toBeChecked();
    });

    await page.close();
});
