import { test } from '@fixtures/AcceptanceTest';
import { expect } from '@playwright/test';
import type { Request } from '@playwright/test';

interface ConsentModalViewedPayload {
    event: string;
    properties?: {
        option?: string[];
    };
}

test(
    'As a merchant, opening the Product Analytics consent modal should send an anonymous modal-viewed event.',
    { tag: '@ProductAnalytics' },
    async ({
        ShopAdmin,
        FeatureService,
        AdminDashboard,
    }) => {
        test.skip(!(await FeatureService.isEnabled('PRODUCT_ANALYTICS')), 'Product Analytics feature flag is not enabled.');

        const requestPromise = AdminDashboard.page.waitForRequest((request: Request) => {
            if (request.method() !== 'POST') {
                return false;
            }

            if (!request.url().includes('/event/anonymous')) {
                return false;
            }

            const payload = request.postDataJSON() as ConsentModalViewedPayload;

            return payload.event === 'consent_modal_viewed';
        });

        await ShopAdmin.goesTo(AdminDashboard.url());

        await ShopAdmin.expects(AdminDashboard.page.locator('.sw-settings-usage-data-consent-modal__content')).toBeVisible();

        const request = await requestPromise;
        const payload = request.postDataJSON() as ConsentModalViewedPayload;

        expect(payload.event).toBe('consent_modal_viewed');
        expect(Array.isArray(payload.properties?.option)).toBeTruthy();
        expect(payload.properties?.option).toContain('user_tracking');
    },
);
