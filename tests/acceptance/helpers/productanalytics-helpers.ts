import type { Route, Page } from '@playwright/test';
import { expect } from '@playwright/test';

export interface CapturedRequest {
    postData: string;
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

export function parseCapturedRequests(captured: CapturedRequest[]): ProductAnalyticsRequestPayload[] {
    const requests: ProductAnalyticsRequestPayload[] = [];

    for (const c of captured) {
        if (!c.postData) continue;
        try {
            const parsed: ProductAnalyticsRequestPayload = JSON.parse(c.postData);
            if (parsed && typeof parsed.context === 'object' && Array.isArray(parsed.events)) {
                requests.push(parsed);
            }
        } catch {
            // If not JSON, ignore for now
        }
    }

    return requests;
}

export function setupProductAnalyticsInterceptor(){
    const capturedRequests: CapturedRequest[] = [];
    const handler = async (route: Route) => {
        const req = route.request();

        capturedRequests.push({
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
        capturedRequests,
        handler,
    };
}

export async function waitForCapturedRequests(
    capturedRequests: CapturedRequest[],
    expectedCount: number
) {
    await expect.poll(() => capturedRequests.length, { timeout: 10_000 }).toBe(expectedCount);
}

export async function removeSymfonyToolbar(page: Page): Promise<boolean>{

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
    return true;
}
