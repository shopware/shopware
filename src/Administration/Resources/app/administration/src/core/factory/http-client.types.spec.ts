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
            const requestConfig: AxiosRequestConfig & { useAxiosV1: boolean } = {
                useAxiosV1: true,
                auth: { username: 'admin', password: 'password' },
                onUploadProgress: () => {},
                validateStatus: (status) => status < 500,
            };
            const interceptorId = httpClient.interceptors.response.use((response) => response);
            const responsePromise: Promise<AxiosResponse> = httpClient.get('/test', requestConfig);
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
});
