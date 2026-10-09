import assert from 'node:assert/strict';
import { afterEach, describe, it } from 'node:test';
import { AdminApi } from '../lib/admin-api.ts';

const SHOP = {
    appUrl: 'http://localhost:8000/',
    username: 'admin',
    password: 'shopware',
};

interface Call {
    url: string;
    method: string;
    headers: Record<string, string>;
    body: string | undefined;
}

const realFetch = globalThis.fetch;

/**
 * Replace `fetch` with a queue of canned responses and record what was sent.
 *
 * The recorded calls are what the assertions inspect — these tests are about the request the shim
 * builds, which is the part `TestDataService` depends on and cannot itself verify.
 */
function mockFetch(responses: { status: number; body?: unknown }[]): Call[] {
    const calls: Call[] = [];

    globalThis.fetch = (async (input: string | URL, init: RequestInit = {}) => {
        calls.push({
            url: String(input),
            method: init.method ?? 'GET',
            headers: (init.headers ?? {}) as Record<string, string>,
            body: init.body as string | undefined,
        });

        const next = responses.shift() ?? { status: 200, body: {} };

        return {
            ok: next.status >= 200 && next.status < 300,
            status: next.status,
            json: async () => next.body ?? {},
            text: async () => JSON.stringify(next.body ?? {}),
        };
    }) as typeof fetch;

    return calls;
}

afterEach(() => {
    globalThis.fetch = realFetch;
});

describe('AdminApi url resolution', () => {
    it('resolves a bare path against the api base', async () => {
        const calls = mockFetch([{ status: 200, body: { access_token: 't' } }, { status: 200 }]);
        const api = new AdminApi(SHOP);
        await api.authenticate();
        await api.post('search/product', { data: { limit: 1 } });

        assert.equal(calls[1]?.url, 'http://localhost:8000/api/search/product');
    });

    it('resolves a ./-prefixed path the same way', async () => {
        // TestDataService spells one call `./_action/cache-delayed`; naive concatenation would
        // produce a 404 here while every other call kept working.
        const calls = mockFetch([{ status: 200, body: { access_token: 't' } }, { status: 200 }]);
        const api = new AdminApi(SHOP);
        await api.authenticate();
        await api.delete('./_action/cache-delayed?refreshOpenSearch=true');

        assert.equal(
            calls[1]?.url,
            'http://localhost:8000/api/_action/cache-delayed?refreshOpenSearch=true',
        );
    });

    it('appends params without dropping an existing query string', async () => {
        const calls = mockFetch([{ status: 200, body: { access_token: 't' } }, { status: 200 }]);
        const api = new AdminApi(SHOP);
        await api.authenticate();
        await api.get('search/product?_response=detail', { params: { limit: 5 } });

        assert.equal(calls[1]?.url, 'http://localhost:8000/api/search/product?_response=detail&limit=5');
    });
});

describe('AdminApi request bodies', () => {
    it('json-encodes an object body and sets the content type', async () => {
        const calls = mockFetch([{ status: 200, body: { access_token: 't' } }, { status: 200 }]);
        const api = new AdminApi(SHOP);
        await api.authenticate();
        await api.post('product', { data: { name: 'x' } });

        assert.equal(calls[1]?.body, '{"name":"x"}');
        assert.equal(calls[1]?.headers['Content-Type'], 'application/json');
    });

    it('passes binary bodies through untouched', async () => {
        // Media uploads send raw bytes; JSON-encoding them would corrupt the file.
        const bytes = new Uint8Array([1, 2, 3]);
        const calls = mockFetch([{ status: 200, body: { access_token: 't' } }, { status: 200 }]);
        const api = new AdminApi(SHOP);
        await api.authenticate();
        await api.post('_action/media/abc/upload', { data: bytes, headers: { 'Content-Type': 'image/png' } });

        assert.equal(calls[1]?.body, bytes);
        assert.equal(calls[1]?.headers['Content-Type'], 'image/png');
    });
});

describe('AdminApi authentication', () => {
    it('re-authenticates once and replays after a 401', async () => {
        // A seeding script routinely outlives the token; without the replay the run would fail on an
        // expiry that has nothing to do with the payload.
        const calls = mockFetch([
            { status: 200, body: { access_token: 'first' } },
            { status: 401 },
            { status: 200, body: { access_token: 'second' } },
            { status: 200, body: { data: [] } },
        ]);

        const api = new AdminApi(SHOP);
        await api.authenticate();
        const response = await api.post('search/product', { data: {} });

        assert.equal(response.ok(), true);
        assert.equal(calls.length, 4);
        assert.equal(calls[3]?.headers['Authorization'], 'Bearer second');
    });

    it('does not retry a non-401 failure', async () => {
        const calls = mockFetch([{ status: 200, body: { access_token: 't' } }, { status: 400 }]);
        const api = new AdminApi(SHOP);
        await api.authenticate();
        const response = await api.post('product', { data: {} });

        assert.equal(response.status(), 400);
        assert.equal(calls.length, 2);
    });

    it('surfaces the response body when login fails', async () => {
        mockFetch([{ status: 401, body: { errors: [{ detail: 'bad credentials' }] } }]);
        const api = new AdminApi(SHOP);

        await assert.rejects(() => api.authenticate(), /admin login failed \(HTTP 401\)/);
    });

    it('rejects a login response with no token', async () => {
        mockFetch([{ status: 200, body: {} }]);
        const api = new AdminApi(SHOP);

        await assert.rejects(() => api.authenticate(), /no access_token/);
    });
});

describe('AdminApi response shape', () => {
    it('exposes ok and status as methods', async () => {
        // TestDataService calls `expect(response.ok()).toBeTruthy()`, so a plain fetch Response —
        // where `ok` is a property — would silently always be truthy.
        mockFetch([{ status: 200, body: { access_token: 't' } }, { status: 204 }]);
        const api = new AdminApi(SHOP);
        await api.authenticate();
        const response = await api.get('product');

        assert.equal(typeof response.ok, 'function');
        assert.equal(typeof response.status, 'function');
        assert.equal(response.status(), 204);
    });
});
