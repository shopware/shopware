import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { createSeedContext } from '../lib/data-service.ts';

/** The foreign keys `TestDataService` needs; the values are opaque to the lookups under test. */
const SALES_CHANNEL = {
    id: 'sc',
    currencyId: 'cur',
    languageId: 'lang',
    countryId: 'country',
    customerGroupId: 'group',
    paymentMethodId: 'pay',
    navigationCategoryId: 'nav',
};

const realFetch = globalThis.fetch;

/**
 * Queue one canned response per call `createSeedContext` makes, in order: login, sales channel, tax.
 *
 * Position is the only routing, so a case lists every response up to the one it is about.
 */
function mockFetch(responses: { status: number; body?: unknown }[]): void {
    globalThis.fetch = (async () => {
        const next = responses.shift() ?? { status: 200, body: {} };

        return {
            ok: next.status >= 200 && next.status < 300,
            status: next.status,
            json: async () => next.body ?? {},
            text: async () => JSON.stringify(next.body ?? {}),
        };
    }) as unknown as typeof fetch;
}

afterEach(() => {
    globalThis.fetch = realFetch;
});

describe('createSeedContext', () => {
    it('reports the status when the tax lookup fails', async () => {
        // Without the status check the run dies inside `response.json()` on the API's error page, or
        // reports "no tax rate found" — sending the operator after missing shop data, not a 403.
        mockFetch([
            { status: 200, body: { access_token: 't' } },
            { status: 200, body: { data: [SALES_CHANNEL] } },
            { status: 403, body: { errors: [{ detail: 'no scope' }] } },
        ]);

        await assert.rejects(createSeedContext, /tax lookup failed \(HTTP 403\)/);
    });

    it('reports an empty tax table separately from a failed lookup', async () => {
        mockFetch([
            { status: 200, body: { access_token: 't' } },
            { status: 200, body: { data: [SALES_CHANNEL] } },
            { status: 200, body: { data: [] } },
        ]);

        await assert.rejects(createSeedContext, /no tax rate found/);
    });

    it('reports the status when the sales-channel lookup fails', async () => {
        mockFetch([
            { status: 200, body: { access_token: 't' } },
            { status: 500, body: { errors: [{ detail: 'boom' }] } },
        ]);

        await assert.rejects(createSeedContext, /sales-channel lookup failed \(HTTP 500\)/);
    });
});
