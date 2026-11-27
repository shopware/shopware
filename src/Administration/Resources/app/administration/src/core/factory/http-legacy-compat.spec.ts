/**
 * @sw-package framework
 *
 * @deprecated tag:v6.8.0 - Will be removed together with the legacy compatibility mode.
 */

import Axios from 'axios';
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';
import type { HttpClient, HttpRequestConfig } from 'src/core/factory/http-client.types';
import { deprecations } from 'test/_helper_/deprecations';

describe('core/factory/http-legacy-compat.ts', () => {
    let httpClient: HttpClient;
    let mock: MockAdapter;

    const api = Shopware.Context.api.apiPath;
    const params = { ids: [1, 2], filter: { term: 'a,b' } };
    const legacyUrl = `${api}/q?ids[]=1&ids[]=2&filter[term]=a,b`;
    const modernUrl = `${api}/q?ids%5B%5D=1&ids%5B%5D=2&filter%5Bterm%5D=a,b`;

    beforeEach(() => {
        httpClient = createHTTPClient(Shopware.Context.api);
        mock = new MockAdapter(httpClient);
        mock.onAny().reply(200, {}, { 'x-custom': 'yes' });
    });

    // The URL the XHR adapter builds from the config that left the interceptors.
    async function sentUrl(config: HttpRequestConfig): Promise<string> {
        await httpClient.get('/q', config);

        return Axios.getUri(mock.history.get[0]);
    }

    describe('query encoding', () => {
        it.deprecated('v6.8.0.0')('should keep the Axios 0.x encoding by default before 6.8', async () => {
            expect(await sentUrl({ params })).toBe(legacyUrl);
            expect(httpClient.getUri({ url: '/q', params })).toBe(legacyUrl);
        });

        it.activeFeatureFlags(['v6.8.0.0'])('should use the Axios 1.x encoding by default from 6.8 on', async () => {
            expect(await sentUrl({ params })).toBe(modernUrl);
            expect(httpClient.getUri({ url: '/q', params })).toBe(modernUrl);
        });

        it.deprecated('v6.8.0.0').each([
            [false, legacyUrl],
            [true, modernUrl],
        ])('should let useAxiosV1: %s decide over the default', async (useAxiosV1, expected) => {
            expect(await sentUrl({ params, useAxiosV1 })).toBe(expected);
            expect(httpClient.getUri({ url: '/q', params, useAxiosV1 })).toBe(expected);
            expect(deprecations).toHaveTriggered('"useAxiosV1" request option is deprecated');
        });

        // Expected values are what Axios 0.31.1 produced.
        it.deprecated('v6.8.0.0').each([
            ['characters it left literal', { q: 'a,b:c$d[e]f' }, 'q=a,b:c$d[e]f'],
            ['characters it encoded', { q: 'a b&c=d/e' }, 'q=a+b%26c%3Dd%2Fe'],
            ['empty values', { a: null, b: undefined, c: '' }, 'c='],
            ['dates', { d: new Date(Date.UTC(2020, 0, 1)) }, 'd=2020-01-01T00:00:00.000Z'],
            ['objects in arrays', { f: [{ t: 'eq' }] }, 'f[0][t]=eq'],
        ])('should encode %s like Axios 0.x', (_name, query, expected) => {
            expect(httpClient.getUri({ url: '/q', params: query, useAxiosV1: false })).toBe(`${api}/q?${expected}`);
            expect(deprecations).toHaveTriggered('"useAxiosV1" request option is deprecated');
        });

        it.deprecated('v6.8.0.0')('should send URLSearchParams exactly as Axios 0.x did', async () => {
            const searchParams = new URLSearchParams({ 'a[b]': 'c,d' });

            expect(await sentUrl({ params: searchParams, useAxiosV1: false })).toBe(`${api}/q?a%5Bb%5D=c%2Cd`);
            expect(deprecations).toHaveTriggered('"useAxiosV1" request option is deprecated');
        });

        it.deprecated('v6.8.0.0')('should keep the legacy encoding when only serializer options are passed', async () => {
            const config = { params: { a: { b: [1] } }, paramsSerializer: { indexes: null }, useAxiosV1: false };

            expect(await sentUrl(config)).toBe(`${api}/q?a[b][0]=1`);
            expect(deprecations).toHaveTriggered('"useAxiosV1" request option is deprecated');
        });

        it.deprecated('v6.8.0.0')('should let an own serialize function win', async () => {
            expect(await sentUrl({ params, paramsSerializer: () => 'custom=1', useAxiosV1: false })).toBe(
                `${api}/q?custom=1`,
            );
            expect(deprecations).toHaveTriggered('"useAxiosV1" request option is deprecated');
        });
    });

    describe('response headers', () => {
        it.deprecated('v6.8.0.0')('should hand out plain headers in the legacy mode only', async () => {
            const { headers: legacy } = await httpClient.get<unknown, { headers: Record<string, string> }>('/headers', {
                useAxiosV1: false,
            });
            const { headers: modern } = await httpClient.get<unknown, { headers: Record<string, string> }>('/headers', {
                useAxiosV1: true,
            });

            expect(Object.getPrototypeOf(legacy)).toBe(Object.prototype);
            expect(legacy['x-custom']).toBe('yes');
            expect(Object.getPrototypeOf(modern)).not.toBe(Object.prototype);
            expect(modern['x-custom']).toBe('yes');
            expect(deprecations).toHaveTriggered('"useAxiosV1" request option is deprecated');
        });

        it.deprecated('v6.8.0.0')('should hand out plain headers on error responses in the legacy mode', async () => {
            mock.resetHandlers();
            mock.onGet('/missing').reply(404, {}, { 'x-custom': 'no' });

            const error = await httpClient.get('/missing', { useAxiosV1: false }).catch((e: unknown) => e);

            expect(Object.getPrototypeOf((error as { response: { headers: object } }).response.headers)).toBe(
                Object.prototype,
            );
            expect(deprecations).toHaveTriggered('"useAxiosV1" request option is deprecated');
        });
    });

    describe('deprecated members', () => {
        it.deprecated('v6.8.0.0')('should still cancel requests through the CancelToken shim', async () => {
            const source = httpClient.CancelToken.source();
            source.cancel('Operation cancelled');

            const error = await httpClient.get('/cancelled', { cancelToken: source.token }).catch((e: unknown) => e);

            expect(httpClient.isCancel(error)).toBe(true);
            expect(mock.history.get).toHaveLength(0);
            expect(deprecations).toHaveTriggered('"httpClient.CancelToken" and the "cancelToken" request option');
        });

        it.deprecated('v6.8.0.0').each([
            ['axiosV0', () => httpClient],
            ['axiosV1', () => httpClient],
            ['interceptorsV0', () => httpClient.interceptors],
            ['interceptorsV1', () => httpClient.interceptors],
            ['defaultsV0', () => httpClient.defaults],
            ['defaultsV1', () => httpClient.defaults],
        ])('should keep httpClient.%s as a deprecated alias', (alias, target) => {
            expect((httpClient as HttpClient & Record<string, unknown>)[alias]).toBe(target());
            expect(deprecations).toHaveTriggered(`"httpClient.${alias}" is deprecated`);
        });
    });
});
