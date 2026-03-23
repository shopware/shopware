import { test, expect } from '@fixtures/AcceptanceTest';
import type { Page, Response } from '@playwright/test';
import { prepareProductAnalyticsConsentModal } from '../../helpers/product-analytics-consent';
import { createStableAdminPage } from '../../helpers/stable-admin-page';

const SETTINGS_PRIVACY_ROUTE = '#/sw/settings/usage/data/index/general';
const PROFILE_PRIVACY_ROUTE = '#/sw/profile/index/privacy-preferences';
const ALLOW_ALL_BUTTON = /Allow All|Share all data|Alle akzeptieren|Alle Daten teilen/;
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

async function removeSymfonyDebugToolbar(page: Page): Promise<void> {
    await page.evaluate(() => {
        document.querySelectorAll('.sf-toolbar').forEach((element) => {
            element.remove();
        });
    });
}

test('Merchant is able accept or decline the data sharing consent.', {
    tag: ['@DataSharing', '@ProductAnalyticsConsentModal', '@ProductAnalyticsConsentModalSettings'],
}, async ({
    browser,
    SalesChannelBaseConfig,
    AdminApiContext,
}) => {
    const { page } = await createStableAdminPage(browser, SalesChannelBaseConfig, AdminApiContext);

    await test.step('Accept both data sharing consents from the dashboard modal', async () => {
        await prepareProductAnalyticsConsentModal(page, AdminApiContext);
        await expect(page.locator('.sw-settings-usage-data-consent-modal__content')).toBeVisible();

        const backendDataConsentPromise = waitForConsentResponse(page, 'accept', 'backend_data');
        const productAnalyticsConsentPromise = waitForConsentResponse(page, 'accept', 'product_analytics');

        await removeSymfonyDebugToolbar(page);
        await page.getByRole('button', { name: ALLOW_ALL_BUTTON }).click();

        const backendDataResponse = await backendDataConsentPromise;
        const productAnalyticsResponse = await productAnalyticsConsentPromise;

        expect(backendDataResponse.ok()).toBeTruthy();
        expect(productAnalyticsResponse.ok()).toBeTruthy();
        await expect(page.locator('.sw-settings-usage-data-consent-modal__content')).not.toBeVisible();
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
