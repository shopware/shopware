import { test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';
import type { Request } from '@playwright/test';
import { prepareProductAnalyticsConsentModal } from '../../helpers/product-analytics-consent';
import { createStableAdminPage } from '../../helpers/stable-admin-page';

interface ConsentModalViewedPayload {
    events?: {
        event_type?: string;
        event_properties?: {
            consents_shown?: string[];
        };
    }[];
}

test(
    'As a merchant, opening the Product Analytics consent modal should send an anonymous modal-viewed event.',
    { tag: ['@ProductAnalytics', '@ProductAnalyticsConsentModal', '@ProductAnalyticsConsentModalViewed'] },
    async ({
        browser,
        SalesChannelBaseConfig,
        AdminApiContext,
    }) => {
        const { page } = await createStableAdminPage(browser, SalesChannelBaseConfig, AdminApiContext);

        const requestPromise = page.waitForRequest((request: Request) => {
            if (request.method() !== 'POST') {
                return false;
            }

            if (!request.url().includes('/event/anonymous')) {
                return false;
            }

            const payload = request.postDataJSON() as ConsentModalViewedPayload;
            const firstEvent = payload.events?.[0];

            return firstEvent?.event_type === 'consent_modal_viewed';
        });

        await prepareProductAnalyticsConsentModal(page, AdminApiContext);
        await expect(page.locator('.sw-settings-usage-data-consent-modal__content')).toBeVisible();

        const request = await requestPromise;
        const payload = request.postDataJSON() as ConsentModalViewedPayload;
        expect(payload.events).toBeDefined();

        const [firstEvent] = payload.events as NonNullable<ConsentModalViewedPayload['events']>;

        expect(firstEvent).toBeDefined();
        expect(firstEvent.event_properties).toBeDefined();
        expect(firstEvent.event_properties!.consents_shown).toBeDefined();

        const consentsShown = firstEvent.event_properties!.consents_shown as string[];

        expect(firstEvent.event_type).toBe('consent_modal_viewed');
        expect(consentsShown).toContain('product_analytics');
        expect(consentsShown.every((consent) => ['backend_data', 'product_analytics'].includes(consent))).toBeTruthy();

        await page.close();
    },
);
