import type { Route, Page } from '@playwright/test';
import { expect } from '@playwright/test';

export interface CapturedRequest {
    postData: string | null;
}

export interface ProductAnalyticsContext {
    sw_version: string;
    sw_app_url: string;
    sw_browser_url: string;
    sw_user_agent: string;
    sw_default_language: string;
    sw_default_currency: string;
    sw_screen_width: number;
    sw_screen_height: number;
    sw_screen_orientation: string;
}

export interface ProductAnalyticsUser {
    shop_id: string;
    id: string;
}

export interface ProductAnalyticsEvent {
    name: string;
    properties: Record<string, string | number | null>;
    timestamp: number;
    insert_id: string;
    device_id: string;
    session_id: number;
}

export interface ProductAnalyticsRequestPayload {
    context: ProductAnalyticsContext;
    events: ProductAnalyticsEvent[];
    user: ProductAnalyticsUser;
}

type ConsentStatus = 'accepted' | 'declined' | 'unset';

type ConsentName = 'backend_data' | 'product_analytics';

type ConsentStatusOverride = Partial<Record<ConsentName, ConsentStatus>>;

interface ConsentEntry {
    acceptedUntil: string | null;
    acceptedRevision: string | null;
    name: ConsentName;
    scopeName: string;
    identifier: string;
    status: ConsentStatus;
    actor: string | null;
    updatedAt: string | null;
    latestRevision: string | null;
}

type ConsentResponse = Record<ConsentName, ConsentEntry>;

const JSON_HEADERS = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Credentials': 'true',
    'Content-Type': 'application/json',
};

const CONSENT_NAMES: ConsentName[] = ['backend_data', 'product_analytics'];

export function parseCapturedRequests(captured: CapturedRequest[]): ProductAnalyticsRequestPayload[] {
    const requests: ProductAnalyticsRequestPayload[] = [];

    for (const c of captured) {
        if (!c.postData) continue;
        try {
            const parsed = JSON.parse(c.postData) as unknown;
            if (
                parsed &&
                typeof parsed === 'object' &&
                'context' in parsed &&
                'events' in parsed &&
                Array.isArray((parsed as ProductAnalyticsRequestPayload).events)
            ) {
                requests.push(parsed as ProductAnalyticsRequestPayload);
            }
        } catch {
            // If not JSON, ignore for now
        }
    }
    return requests;
}

export function setupProductAnalyticsInterceptor() {
    const capturedTrackingEventRequests: CapturedRequest[] = [];
    const trackingEventHandler = async (route: Route) => {
        const req = route.request();
        const postData = req.postData();

        capturedTrackingEventRequests.push({
            postData: postData,
        });

        if (!postData) {
            await fulfillError(route, 'Missing request body');
            return;
        }

        await route.fulfill({
            status: 200,
            headers: JSON_HEADERS,
            body: JSON.stringify({ code: 200 }),
        });
    };

    return {
        capturedTrackingEventRequests,
        trackingEventHandler,
    };
}

export function setupConsentInterceptor(
    statusOverrides: ConsentStatusOverride = {}
) {
    const consentStatuses: Record<ConsentName, ConsentStatus> = {
        backend_data: statusOverrides.backend_data ?? 'unset',
        product_analytics: statusOverrides.product_analytics ?? 'unset',
    };

    const capturedConsentRequests: CapturedRequest[] = [];

    const consentHandler = async (route: Route) => {
        const req = route.request();
        const pathName = new URL(req.url()).pathname;

        capturedConsentRequests.push({
            postData: req.postData(),
        });

        if (pathName.endsWith('/consents')) {
            await route.fulfill({
                status: 200,
                headers: JSON_HEADERS,
                body: JSON.stringify(createConsentListResponse(consentStatuses)),
            });
            return;
        }

        const requestedStatus = getConsentStatusFromPath(pathName);
        if (!requestedStatus) {
            await fulfillError(route, 'Unknown endpoint');
            return;
        }

        const postData = req.postData();
        if (!postData) {
            await fulfillError(route, 'Missing request body');
            return;
        }

        let requestBody: { consent?: unknown };
        try {
            requestBody = JSON.parse(postData) as { consent?: unknown };
        } catch {
            await fulfillError(route, 'Invalid JSON body');
            return;
        }

        if (!isConsentName(requestBody.consent)) {
            await fulfillError(route, 'Unknown consent');
            return;
        }

        consentStatuses[requestBody.consent] = requestedStatus;

        await route.fulfill({
            status: 200,
            headers: JSON_HEADERS,
            body: JSON.stringify(createConsentStatusResponse(requestBody.consent, requestedStatus)),
        });
    };

    return {
        capturedConsentRequests,
        consentHandler,
    };
}

export async function removeSymfonyToolbar(page: Page): Promise<void> {

    await page.addStyleTag({
        content: `
                .sf-toolbar {
                    width: 0 !important;
                    height: 0 !important;
                    display: none !important;
                    pointer-events: none !important;
                }
                `.trim(),
    });
}

function isConsentName(value: unknown): value is ConsentName {
    return typeof value === 'string' && CONSENT_NAMES.includes(value as ConsentName);
}

function createConsentListResponse(statuses: Record<ConsentName, ConsentStatus>): ConsentResponse {
    return {
        backend_data: {
            acceptedUntil: null,
            acceptedRevision: null,
            name: 'backend_data',
            scopeName: 'system',
            identifier: 'system',
            status: statuses.backend_data,
            actor: null,
            updatedAt: null,
            latestRevision: null,
        },
        product_analytics: {
            acceptedUntil: null,
            acceptedRevision: null,
            name: 'product_analytics',
            scopeName: 'admin_user',
            identifier: 'random_identifier',
            status: statuses.product_analytics,
            actor: null,
            updatedAt: null,
            latestRevision: null,
        },
    };
}

function createConsentStatusResponse(consent: ConsentName, status: ConsentStatus): ConsentEntry {
    return {
        acceptedUntil: null,
        acceptedRevision: null,
        name: consent,
        scopeName: 'admin_user',
        identifier: 'random_static_identifier',
        status,
        actor: null,
        updatedAt: null,
        latestRevision: null,
    };
}

function getConsentStatusFromPath(pathName: string): ConsentStatus | null {
    if (pathName.endsWith('/consents/accept')) {
        return 'accepted';
    }

    if (pathName.endsWith('/consents/revoke')) {
        return 'declined';
    }

    return null;
}

async function fulfillError(route: Route, detail: string): Promise<void> {
    await route.fulfill({
        status: 400,
        headers: JSON_HEADERS,
        body: JSON.stringify({ errors: [{ detail }] }),
    });
}

export async function waitForEventCount(
    getEvents: () => unknown[],
    expectedCount: number,
    options?: {
        timeout?: number;
        intervals?: number[];
    }
) {
    await expect
        .poll(
            () => getEvents().length,
            {
                timeout: options?.timeout ?? 10_000,
                intervals: options?.intervals ?? [1000, 2000, 3000],
            }
        )
        .toBe(expectedCount);
}
