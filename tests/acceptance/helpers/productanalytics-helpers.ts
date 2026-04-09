import type { Route } from '@playwright/test';
import { expect } from '@playwright/test';

export interface CapturedRequest {
    postData: string;
}

export interface AmplitudeEvent {
    event_type: string;
    event_properties: Record<string, string | number>;
    event_id?: number;
}

export interface AmplitudeRequestPayload {
    events: AmplitudeEvent[];
}

export function parseCapturedEvents(captured: CapturedRequest[]): AmplitudeEvent[] {
    const events: AmplitudeEvent[] = [];

    for (const c of captured) {
        if (!c.postData) continue;
        try {
            const parsed: AmplitudeRequestPayload = JSON.parse(c.postData);
            if (Array.isArray(parsed.events)) {
                events.push(...parsed.events);
            }
        } catch {
            // If not JSON, ignore for now
        }
    }

    return events;
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
