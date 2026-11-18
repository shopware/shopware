/**
 * @sw-package framework
 */

import type { AxiosInstance, AxiosRequestConfig, AxiosResponse } from 'axios';
import MockAdapter from 'axios-mock-adapter';
import type { HttpClient } from './http-client.types';

describe('core/factory/http-client.types.ts', () => {
    it('keeps common extension TypeScript usage compatible', () => {
        function assertCompatibility(httpClient: HttpClient): void {
            const axiosClient: AxiosInstance = httpClient;
            const requestConfig: AxiosRequestConfig = {
                signal: new AbortController().signal,
                auth: { username: 'admin', password: 'password' },
                onUploadProgress: () => {},
                validateStatus: (status) => status < 500,
            };
            const interceptorId = httpClient.interceptors.response.use((response) => response);
            const responsePromise: Promise<AxiosResponse> = httpClient.get('/test', requestConfig);
            // @deprecated tag:v6.8.0 - CancelToken stays typed until the shim is removed.
            const cancelToken = new httpClient.CancelToken(() => {});

            httpClient.defaults.headers.common['x-extension-header'] = 'value';
            httpClient.interceptors.response.eject(interceptorId);
            new MockAdapter(httpClient);
            void cancelToken;
            void responsePromise;

            expect(axiosClient).toBe(httpClient);
        }

        expect(assertCompatibility).toBeDefined();
    });

    it('types a derived client as an Axios instance without the Shopware additions', () => {
        function assertDerivedClient(httpClient: HttpClient): void {
            const derivedClient = httpClient.create({ timeout: 0 });
            const axiosClient: AxiosInstance = derivedClient;

            // @ts-expect-error - `create()` does not copy `isCancel` to the derived client
            void derivedClient.isCancel;
            // @ts-expect-error - `create()` does not copy `CancelToken` to the derived client
            void derivedClient.CancelToken;
            new MockAdapter(derivedClient);

            expect(axiosClient).toBe(derivedClient);
        }

        expect(assertDerivedClient).toBeDefined();
    });
});
