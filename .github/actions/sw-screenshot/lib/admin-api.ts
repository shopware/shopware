import { resolveShop, type ShopCoordinates } from './shop.ts';

/**
 * The subset of Playwright's `APIResponse` that `TestDataService` actually consumes.
 *
 * Note that `ok` and `status` are *methods*, not properties — `TestDataService` calls
 * `expect(response.ok()).toBeTruthy()`, so a plain `fetch` Response is not a drop-in substitute.
 */
export interface AdminApiResponse {
    ok(): boolean;
    status(): number;
    json(): Promise<unknown>;
    text(): Promise<string>;
}

/** Per-request options, mirroring the Playwright `APIRequestContext` options we rely on. */
export interface AdminApiRequestOptions {
    /** Request body. An object is JSON-encoded; a Buffer/string is sent verbatim (media uploads). */
    data?: unknown;
    /** Extra headers, merged over the defaults (auth + content type). */
    headers?: Record<string, string>;
    /** Query parameters appended to the URL. */
    params?: Record<string, string | number | boolean>;
}

/**
 * Minimal Admin API client, shaped like Playwright's `APIRequestContext`.
 *
 * This exists because the acceptance test suite does not export its own `AdminApiContext` from the
 * package root, while `TestDataService` — which we do want — needs one. `TestDataService` only ever
 * calls four methods on its client, so a duck-typed stand-in is enough and keeps us off a deep
 * import into the package's `dist/`, which its `exports` map forbids.
 *
 * Prefer {@link createAdminApi} over constructing this directly; it performs the initial login.
 *
 * @example
 * const api = await createAdminApi();
 * const res = await api.post('search/product', { data: { limit: 1 } });
 * if (!res.ok()) throw new Error(await res.text());
 */
export class AdminApi {
    private token: string | null = null;
    private readonly shop: ShopCoordinates;

    constructor(shop: ShopCoordinates) {
        this.shop = shop;
    }

    /**
     * Obtain an admin bearer token via the password grant.
     *
     * Called once by {@link createAdminApi} and again automatically whenever a request comes back
     * 401 — Shopware's admin tokens are short-lived and a long seeding script outlives one.
     */
    async authenticate(): Promise<void> {
        const response = await fetch(new URL('api/oauth/token', this.shop.appUrl), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                client_id: 'administration',
                grant_type: 'password',
                scopes: 'write',
                username: this.shop.username,
                password: this.shop.password,
            }),
        });

        if (!response.ok) {
            throw new Error(`admin login failed (HTTP ${response.status}): ${await response.text()}`);
        }

        const body = (await response.json()) as { access_token?: string };
        if (!body.access_token) {
            throw new Error('admin login returned no access_token');
        }

        this.token = body.access_token;
    }

    get(path: string, options: AdminApiRequestOptions = {}): Promise<AdminApiResponse> {
        return this.request('GET', path, options);
    }

    post(path: string, options: AdminApiRequestOptions = {}): Promise<AdminApiResponse> {
        return this.request('POST', path, options);
    }

    patch(path: string, options: AdminApiRequestOptions = {}): Promise<AdminApiResponse> {
        return this.request('PATCH', path, options);
    }

    delete(path: string, options: AdminApiRequestOptions = {}): Promise<AdminApiResponse> {
        return this.request('DELETE', path, options);
    }

    private async request(
        method: string,
        path: string,
        options: AdminApiRequestOptions,
    ): Promise<AdminApiResponse> {
        const response = await this.send(method, path, options);

        // A seeding script routinely outlives the token's lifetime; re-auth once and replay.
        if (response.status() !== 401) {
            return response;
        }

        await this.authenticate();

        return this.send(method, path, options);
    }

    private async send(
        method: string,
        path: string,
        options: AdminApiRequestOptions,
    ): Promise<AdminApiResponse> {
        const url = this.resolve(path, options.params);
        const binary = options.data instanceof Uint8Array || typeof options.data === 'string';

        const headers: Record<string, string> = {
            Accept: 'application/json',
            ...(this.token ? { Authorization: `Bearer ${this.token}` } : {}),
            ...(options.data !== undefined && !binary ? { 'Content-Type': 'application/json' } : {}),
            ...options.headers,
        };

        let body: BodyInit | undefined;
        if (options.data !== undefined) {
            body = binary ? (options.data as BodyInit) : JSON.stringify(options.data);
        }

        const response = await fetch(url, { method, headers, body });

        return {
            ok: () => response.ok,
            status: () => response.status,
            json: () => response.json(),
            text: () => response.text(),
        };
    }

    /**
     * Build the absolute URL for an Admin API path.
     *
     * Callers inside `TestDataService` mix two spellings — `search/product` and
     * `./_action/cache-delayed` — so resolution goes through `new URL`, which normalises both
     * against the `api/` base.
     */
    private resolve(path: string, params?: AdminApiRequestOptions['params']): URL {
        const url = new URL(path, new URL('api/', this.shop.appUrl));

        for (const [key, value] of Object.entries(params ?? {})) {
            url.searchParams.set(key, String(value));
        }

        return url;
    }
}

/**
 * Create an authenticated {@link AdminApi} for the shop this run provisioned.
 *
 * This is the entry point for anything that needs the Admin API — seeding scripts get one handed to
 * them, so they never deal with tokens at all.
 *
 * @example
 * const api = await createAdminApi();
 */
export async function createAdminApi(shop: ShopCoordinates = resolveShop()): Promise<AdminApi> {
    const api = new AdminApi(shop);
    await api.authenticate();

    return api;
}
