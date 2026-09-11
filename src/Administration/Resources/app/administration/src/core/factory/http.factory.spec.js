/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
 */

import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

Shopware.Application.view.deleteReactive = () => {};

describe('core/factory/http.factory.js', () => {
    let httpClient;
    let mock;

    beforeEach(async () => {
        /**
         * The tracing and cache interceptors are disabled in the test environment. Enable them here
         * so the interceptor chain under test matches production.
         */
        process.env.NODE_ENV = 'prod';
        httpClient = createHTTPClient();
        mock = new MockAdapter(httpClient);
        process.env.NODE_ENV = 'test';
    });

    it('should create a single axios instance with the Shopware base configuration', () => {
        expect(httpClient.defaults.baseURL).toBe(Shopware.Context.api.apiPath);
        expect(httpClient.defaults.timeout).toBe(30000);
        expect(httpClient.defaults.maxContentLength).toBe(50 * 1024 * 1024);
        expect(httpClient.defaults.maxBodyLength).toBe(50 * 1024 * 1024);
        expect(httpClient.interceptors.response.handlers.length).toBeGreaterThan(0);
    });

    it('should not intercept if store session has not expired', async () => {
        mock.onGet('/store-session-expired').replyOnce(200, {});

        expect(mock.history.get).toHaveLength(0);

        await httpClient.get('/store-session-expired');

        expect(mock.history.get).toHaveLength(1);
    });

    it.each([
        ['FRAMEWORK__STORE_SESSION_EXPIRED'],
        ['FRAMEWORK__STORE_SHOP_SECRET_INVALID'],
    ])('should intercept and retry if error code matches', async (errorCode) => {
        mock.onGet('/store-route-requiring-auth')
            .replyOnce(403, {
                errors: [
                    {
                        code: errorCode,
                    },
                ],
            })
            .onGet('/store-route-requiring-auth')
            .replyOnce(200, {});

        expect(mock.history.get).toHaveLength(0);

        await httpClient.get('/store-route-requiring-auth');

        expect(mock.history.get).toHaveLength(2);
    });

    it.each([
        ['FRAMEWORK__STORE_SESSION_EXPIRED'],
        ['FRAMEWORK__STORE_SHOP_SECRET_INVALID'],
    ])('should reject the request and reset the counter once the retry limit is hit', async (errorCode) => {
        mock.onGet('/store-route-requiring-auth').reply(403, {
            errors: [
                {
                    code: errorCode,
                },
            ],
        });

        const getError = async () => {
            try {
                await httpClient.get('/store-route-requiring-auth');

                throw new Error('Expected error to be thrown');
            } catch (error) {
                return error;
            }
        };

        const error = await getError();
        expect(error.response.status).toBe(403);
        expect(error.response.data).toEqual({
            errors: [
                {
                    code: errorCode,
                },
            ],
        });

        expect(mock.history.get).toHaveLength(2);
    });

    it.each([
        ['FRAMEWORK__STORE_SESSION_EXPIRED'],
        ['FRAMEWORK__STORE_SHOP_SECRET_INVALID'],
    ])('should treat each request separately', async (errorCode) => {
        mock.onGet('/store-route-requiring-auth').reply(403, {
            errors: [
                {
                    code: errorCode,
                },
            ],
        });

        const getError = async () => {
            try {
                await Promise.all([
                    httpClient.get('/store-route-requiring-auth'),
                    httpClient.get('/store-route-requiring-auth'),
                ]);

                throw new Error('Expected error to be thrown');
            } catch (error) {
                return error;
            }
        };

        const error = await getError();
        expect(error.response.status).toBe(403);
        expect(error.response.data).toEqual({
            errors: [
                {
                    code: errorCode,
                },
            ],
        });

        expect(mock.history.get).toHaveLength(4);
    });

    it('should add current vue route, as http header to trace', async () => {
        Shopware.Application.view = {
            router: {
                currentRoute: {
                    value: {
                        name: 'sw-dashboard-index',
                    },
                },
            },
        };

        mock.onGet('/test').reply((request) => {
            expect(request.headers['shopware-admin-active-route']).toBe('sw-dashboard-index');

            return [
                200,
                {},
            ];
        });

        await httpClient.get('/test');
    });

    it('should pass snippet params for delete restricted notifications', async () => {
        const notificationStore = Shopware.Store.get('notification');
        const notificationSpy = jest.spyOn(notificationStore, 'createNotification').mockImplementation(() => {});
        const snippetSpy = jest.fn((key) => key);
        const originalView = Shopware.Application.view;

        Shopware.Application.view = {
            ...originalView,
            i18n: {
                ...(originalView?.i18n ?? {}),
                global: {
                    ...(originalView?.i18n?.global ?? {}),
                    t: snippetSpy,
                },
            },
        };

        mock.onDelete('/restricted-delete').reply(409, {
            errors: [
                {
                    code: 'FRAMEWORK__DELETE_RESTRICTED',
                    meta: {
                        parameters: {
                            entity: 'product',
                            usages: [
                                {
                                    count: [
                                        2,
                                        2,
                                    ],
                                    entityName: 'category',
                                },
                            ],
                        },
                    },
                },
            ],
        });

        await httpClient.delete('/restricted-delete').catch(() => {});

        expect(notificationSpy).toHaveBeenCalledTimes(1);
        expect(snippetSpy).toHaveBeenCalledWith(
            'global.notification.messageDeleteFailed',
            { entityName: 'global.entities.product' },
            0,
        );

        Shopware.Application.view = originalView;
        notificationSpy.mockRestore();
    });

    it('should have standard axios methods (get, post, etc.)', () => {
        expect(typeof httpClient.get).toBe('function');
        expect(typeof httpClient.post).toBe('function');
        expect(typeof httpClient.put).toBe('function');
        expect(typeof httpClient.patch).toBe('function');
        expect(typeof httpClient.delete).toBe('function');
        expect(typeof httpClient.head).toBe('function');
        expect(typeof httpClient.options).toBe('function');
        expect(typeof httpClient.request).toBe('function');
        expect(typeof httpClient.getUri).toBe('function');
    });

    it('should support the axios URL and config call form', async () => {
        mock.onPost('/test-callable').reply(200, { success: true });

        const response = await httpClient('/test-callable', {
            method: 'post',
            headers: { 'x-shopware-test': 'value' },
            data: { id: 'test-id' },
        });

        expect(response.data).toEqual({ success: true });
        expect(mock.history.post).toHaveLength(1);
        expect(mock.history.post[0].url).toBe('/test-callable');
        expect(mock.history.post[0].headers['x-shopware-test']).toBe('value');
        expect(JSON.parse(mock.history.post[0].data)).toEqual({ id: 'test-id' });
    });

    it('should keep the axios form helpers compatible', async () => {
        mock.onPost('/test-form').reply((config) => {
            expect(config.headers['Content-Type']).toContain('multipart/form-data');
            return [
                200,
                {},
            ];
        });

        await httpClient.postForm('/test-form', { name: 'shopware' });

        expect(mock.history.post).toHaveLength(1);
    });

    it('should register public interceptors and defaults on the client', async () => {
        const requestInterceptor = jest.fn((config) => config);
        const responseInterceptor = jest.fn((response) => response);

        httpClient.defaults.headers.common['x-shopware-test'] = 'default-header';
        const requestInterceptorId = httpClient.interceptors.request.use(requestInterceptor);
        const responseInterceptorId = httpClient.interceptors.response.use(responseInterceptor);

        expect(httpClient.interceptors.request.handlers[requestInterceptorId]).toMatchObject({
            fulfilled: requestInterceptor,
        });
        expect(httpClient.interceptors.response.handlers[responseInterceptorId]).toMatchObject({
            fulfilled: responseInterceptor,
        });
        mock.onGet('/test-defaults').reply((config) => {
            expect(config.headers['x-shopware-test']).toBe('default-header');
            return [
                200,
                {},
            ];
        });

        await httpClient.get('/test-defaults');

        expect(requestInterceptor).toHaveBeenCalledTimes(1);
        expect(responseInterceptor).toHaveBeenCalledTimes(1);

        httpClient.interceptors.request.eject(requestInterceptorId);
        httpClient.interceptors.response.eject(responseInterceptorId);

        expect(httpClient.interceptors.request.handlers[requestInterceptorId]).toBeFalsy();
        expect(httpClient.interceptors.response.handlers[responseInterceptorId]).toBeFalsy();

        await httpClient.get('/test-defaults');

        expect(requestInterceptor).toHaveBeenCalledTimes(1);
        expect(responseInterceptor).toHaveBeenCalledTimes(1);
        expect(mock.history.get).toHaveLength(2);
    });

    it('should detect AbortController cancellations with isCancel', async () => {
        mock.onGet('/abort-me').reply(200, {});
        const controller = new AbortController();
        controller.abort();

        const getError = async () => {
            try {
                await httpClient.get('/abort-me', { signal: controller.signal });

                throw new Error('Expected error to be thrown');
            } catch (error) {
                return error;
            }
        };

        const error = await getError();

        expect(httpClient.isCancel(error)).toBe(true);
        expect(mock.history.get).toHaveLength(0);
    });

    it('should have an isCancel method that detects cancellations', () => {
        // Errors produced by the deprecated CancelToken carry the __CANCEL__ marker
        const cancelTokenError = { __CANCEL__: true };
        expect(httpClient.isCancel(cancelTokenError)).toBe(true);

        // Errors produced by AbortController cancellations
        const canceledError = { name: 'CanceledError', code: 'ERR_CANCELED' };
        expect(httpClient.isCancel(canceledError)).toBe(true);

        const regularError = new Error('Regular error');
        expect(httpClient.isCancel(regularError)).toBe(false);
        expect(httpClient.isCancel(null)).toBe(false);
        expect(httpClient.isCancel('string')).toBe(false);
    });

    // @deprecated tag:v6.8.0 - The useAxiosV1 request option will be removed.
    it.deprecated('v6.8.0.0')('should accept the useAxiosV1 request option as a no-op and warn once', async () => {
        const warnSpy = jest.spyOn(global.console, 'warn').mockImplementation(() => {});
        mock.onGet('/legacy-flag').reply(200, { ok: true });

        const optIn = await httpClient.get('/legacy-flag', { useAxiosV1: true });
        const optOut = await httpClient.get('/legacy-flag', { useAxiosV1: false });

        expect(optIn.data).toEqual({ ok: true });
        expect(optOut.data).toEqual({ ok: true });
        expect(mock.history.get).toHaveLength(2);
        expect(warnSpy).toHaveBeenCalledTimes(1);
        expect(warnSpy).toHaveBeenCalledWith('[http.factory]', expect.stringContaining('useAxiosV1'));

        warnSpy.mockRestore();
    });

    // @deprecated tag:v6.8.0 - CancelToken will be removed in favour of AbortController.
    it.deprecated('v6.8.0.0')('should keep CancelToken working and warn once', async () => {
        const warnSpy = jest.spyOn(global.console, 'warn').mockImplementation(() => {});
        mock.onGet('/cancel-token').reply(200, {});

        expect(typeof httpClient.CancelToken.source).toBe('function');

        const source = httpClient.CancelToken.source();
        source.cancel('Operation cancelled');

        const getError = async () => {
            try {
                await httpClient.get('/cancel-token', { cancelToken: source.token });

                throw new Error('Expected error to be thrown');
            } catch (error) {
                return error;
            }
        };

        const error = await getError();

        expect(httpClient.isCancel(error)).toBe(true);
        expect(mock.history.get).toHaveLength(0);
        expect(warnSpy).toHaveBeenCalledTimes(1);
        expect(warnSpy).toHaveBeenCalledWith('[http.factory]', expect.stringContaining('cancelToken'));

        warnSpy.mockRestore();
    });

    // @deprecated tag:v6.8.0 - The version-specific runtime escape hatches will be removed.
    it.deprecated('v6.8.0.0')('should keep the v1 runtime escape hatches as aliases and drop the v0 ones', () => {
        expect(httpClient.axiosV1).toBe(httpClient);
        expect(httpClient.interceptorsV1).toBe(httpClient.interceptors);
        expect(httpClient.defaultsV1).toBe(httpClient.defaults);

        expect(httpClient).not.toHaveProperty('axiosV0');
        expect(httpClient).not.toHaveProperty('interceptorsV0');
        expect(httpClient).not.toHaveProperty('defaultsV0');
    });

    describe('Cache Interceptor', () => {
        beforeEach(() => {
            jest.useFakeTimers();
            jest.spyOn(global.console, 'warn').mockImplementation();
        });

        afterEach(() => {
            jest.useRealTimers();
            jest.restoreAllMocks();
        });

        it('should cache identical requests', async () => {
            // Enable cache interceptor by setting NODE_ENV to prod
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            clientMock.onGet('/search/product').reply(200, { data: 'product' });

            // First request
            await client.get('/search/product');
            expect(clientMock.history.get).toHaveLength(1);

            // Second identical request within cache timeout
            jest.advanceTimersByTime(1000);
            await client.get('/search/product');

            // Should still be only 1 actual request due to caching
            expect(clientMock.history.get).toHaveLength(1);
            expect(console.warn).toHaveBeenCalledWith(
                expect.anything(),
                expect.stringContaining('Duplicated requests'),
                expect.anything(),
                expect.anything(),
            );
        });

        it('should not cache requests after timeout expires', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            clientMock.onGet('/search/product').reply(200, { data: 'product' });

            // First request
            await client.get('/search/product');
            expect(clientMock.history.get).toHaveLength(1);

            // Wait for cache to expire (1500ms timeout)
            jest.advanceTimersByTime(2000);

            // Second request after cache timeout
            await client.get('/search/product');

            // Should be 2 actual requests since cache expired
            expect(clientMock.history.get).toHaveLength(2);
            expect(console.warn).not.toHaveBeenCalled();
        });

        it('should flush cache on DELETE requests', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            clientMock.onGet('/search/product').reply(200, { data: 'product' });
            clientMock.onDelete('/product/123').reply(204);

            // First GET request
            await client.get('/search/product');
            expect(clientMock.history.get).toHaveLength(1);

            // DELETE request should flush cache
            await client.delete('/product/123');

            // Second GET request should not use cache (cache was flushed)
            await client.get('/search/product');
            expect(clientMock.history.get).toHaveLength(2);
        });

        it('should flush cache on PATCH requests', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            clientMock.onGet('/search/product').reply(200, { data: 'product' });
            clientMock.onPatch('/product/123').reply(200, { data: 'updated' });

            // First GET request
            await client.get('/search/product');
            expect(clientMock.history.get).toHaveLength(1);

            // PATCH request should flush cache
            await client.patch('/product/123', { name: 'Updated' });

            // Second GET request should not use cache (cache was flushed)
            await client.get('/search/product');
            expect(clientMock.history.get).toHaveLength(2);
        });

        it('should only cache allowed URLs', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            // URL not in allow list
            clientMock.onGet('/some/random/endpoint').reply(200, { data: 'test' });

            // First request
            await client.get('/some/random/endpoint');
            expect(clientMock.history.get).toHaveLength(1);

            // Second identical request
            jest.advanceTimersByTime(1000);
            await client.get('/some/random/endpoint');

            // Should be 2 requests since URL is not in allow list
            expect(clientMock.history.get).toHaveLength(2);
            expect(console.warn).not.toHaveBeenCalled();
        });

        it('should cache config endpoints indefinitely', async () => {
            process.env.NODE_ENV = 'prod';
            const client = createHTTPClient();
            const clientMock = new MockAdapter(client);
            process.env.NODE_ENV = 'test';

            // Use _info/me which is in the allow list
            clientMock.onGet('/_info/me').reply(200, { data: 'config' });

            // First request
            await client.get('/_info/me');
            expect(clientMock.history.get).toHaveLength(1);

            // Wait longer than normal cache timeout (1500ms)
            jest.advanceTimersByTime(5000);

            // Second request should still use cache (config endpoints cached indefinitely)
            await client.get('/_info/me');
            expect(clientMock.history.get).toHaveLength(1);
            expect(console.warn).toHaveBeenCalled();
        });
    });

    describe('refreshTokenInterceptor', () => {
        let loginService;
        let originalShopwareService;

        beforeEach(() => {
            originalShopwareService = Shopware.Service;

            loginService = {
                refreshToken: jest.fn().mockResolvedValue('new-token'),
                subscribeToTokenRefresh: jest.fn((successCb) => {
                    successCb('new-token');
                }),
                logout: jest.fn(),
            };

            Shopware.Service = jest.fn(() => loginService);
        });

        afterEach(() => {
            Shopware.Service = originalShopwareService;
        });

        it('should not retry 401 responses with SSO_LOGIN__TOKEN_NOT_FOUND error code', async () => {
            mock.onGet('/api/sbp/shop-info').reply(401, {
                errors: [
                    {
                        code: 'SSO_LOGIN__TOKEN_NOT_FOUND',
                        detail: 'Cannot get token from user.',
                    },
                ],
            });

            const getError = async () => {
                try {
                    await httpClient.get('/api/sbp/shop-info');
                    throw new Error('Expected error to be thrown');
                } catch (error) {
                    return error;
                }
            };

            const error = await getError();
            expect(error.response.status).toBe(401);
            expect(error.response.data.errors[0].code).toBe('SSO_LOGIN__TOKEN_NOT_FOUND');

            expect(mock.history.get).toHaveLength(1);
            expect(loginService.refreshToken).not.toHaveBeenCalled();
        });

        it('should not retry a 401 request more than once after token refresh', async () => {
            mock.onGet('/api/some-endpoint').reply(401, {});

            const getError = async () => {
                try {
                    await httpClient.get('/api/some-endpoint');
                    throw new Error('Expected error to be thrown');
                } catch (error) {
                    return error;
                }
            };

            const error = await getError();
            expect(error.response.status).toBe(401);
            expect(mock.history.get).toHaveLength(2);
            expect(loginService.refreshToken).toHaveBeenCalledTimes(1);
            expect(loginService.subscribeToTokenRefresh).toHaveBeenCalledTimes(1);
        });
    });
});
