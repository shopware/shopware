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

type ConsentOverride = Record<string, Partial<ConsentEntry>>;

interface ConsentEntry {
    acceptedUntil: null;
    acceptedRevision: null;
    name: string;
    scopeName: string;
    identifier: string;
    status: ConsentStatus;
    actor: null;
    updatedAt: null;
    latestRevision: null;
}

type ConsentResponse = Record<string, ConsentEntry>;

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

export function setupProductAnalyticsInterceptor(){
    const capturedTrackingEventRequests: CapturedRequest[] = [];
    const trackingEventHandler = async (route: Route) => {
        const req = route.request();

        capturedTrackingEventRequests.push({
            postData: req.postData(),
        });

        await route.fulfill({
            status: 200,
            headers: {
                'Access-Control-Allow-Origin': '*',
                'Access-Control-Allow-Credentials': 'true',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ code: 200 }),
        });
    };

    return {
        capturedTrackingEventRequests,
        trackingEventHandler,
    };
}

export function setupConsentInterceptor(
    overrides: ConsentOverride = {}
) {
    const defaultResponse: ConsentResponse = {
        backend_data: {
            acceptedUntil: null,
            acceptedRevision: null,
            name: 'backend_data',
            scopeName: 'system',
            identifier: 'system',
            status: 'unset',
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
            status: 'unset',
            actor: null,
            updatedAt: null,
            latestRevision: null,
        },
    };

    const mergedResponse = mergeConsentResponse(defaultResponse, overrides);

    const capturedConsentRequests: CapturedRequest[] = [];

    const consentHandler = async (route: Route) => {
        const req = route.request();

        capturedConsentRequests.push({
            postData: req.postData(),
        });

        await route.fulfill({
            status: 200,
            headers: {
                'Access-Control-Allow-Origin': '*',
                'Access-Control-Allow-Credentials': 'true',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(mergedResponse),
        });
    };

    return {
        capturedConsentRequests,
        consentHandler,
    };
}

export function setupConsentRevokeInterceptor(){
    const capturedConsentRevokeRequests: CapturedRequest[] = [];
    const consentRevokeHandler = async (route: Route) => {
        const req = route.request();

        const requestBody = JSON.parse(req.postData());
        if (requestBody.consent === 'backend_data') {
            await route.fulfill({
                status: 200,
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Credentials': 'true',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    'acceptedUntil': null,
                    'acceptedRevision': null,
                    'name': 'backend_data',
                    'scopeName': 'admin_user',
                    'identifier': '019d75c08b6673fa90c44923e2254f0a',
                    'status': 'declined',
                    'actor': null,
                    'updatedAt': null,
                    'latestRevision': null,
                }),
            });
        } else if (requestBody.consent === 'product_analytics') {
            await route.fulfill({
                status: 200,
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Credentials': 'true',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    'acceptedUntil': null,
                    'acceptedRevision': null,
                    'name': 'product_analytics',
                    'scopeName': 'admin_user',
                    'identifier': '019d75c08b6673fa90c44923e2254f0a',
                    'status': 'declined',
                    'actor': null,
                    'updatedAt': null,
                    'latestRevision': null,
                }),
            });
        }

        capturedConsentRevokeRequests.push({
            postData: req.postData(),
        });
    };

    return {
        capturedConsentRevokeRequests,
        consentRevokeHandler,
    };
}

export function setupConsentAcceptInterceptor(){
    const capturedConsentRevokeRequests: CapturedRequest[] = [];
    const consentRevokeHandler = async (route: Route) => {
        const req = route.request();

        const requestBody = JSON.parse(req.postData());
        if (requestBody.consent === 'backend_data') {
            await route.fulfill({
                status: 200,
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Credentials': 'true',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    'acceptedUntil': null,
                    'acceptedRevision': null,
                    'name': 'backend_data',
                    'scopeName': 'admin_user',
                    'identifier': '019d75c08b6673fa90c44923e2254f0a',
                    'status': 'declined',
                    'actor': null,
                    'updatedAt': null,
                    'latestRevision': null,
                }),
            });
        } else if (requestBody.consent === 'product_analytics') {
            await route.fulfill({
                status: 200,
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Credentials': 'true',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    'acceptedUntil': null,
                    'acceptedRevision': null,
                    'name': 'product_analytics',
                    'scopeName': 'admin_user',
                    'identifier': '019d75c08b6673fa90c44923e2254f0a',
                    'status': 'declined',
                    'actor': null,
                    'updatedAt': null,
                    'latestRevision': null,
                }),
            });
        }

        capturedConsentRevokeRequests.push({
            postData: req.postData(),
        });
    };

    return {
        capturedConsentRevokeRequests,
        consentRevokeHandler,
    };
}

export async function removeSymfonyToolbar(page: Page): Promise<void>{

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

function mergeConsentResponse(
    defaults: ConsentResponse,
    overrides: Partial<ConsentOverride>
): ConsentResponse {
    return Object.fromEntries(
        Object.entries(defaults).map(([key, defaultValue]) => [
            key,
            {
                ...defaultValue,
                ...(overrides[key] || {}),
            },
        ])
    );
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
