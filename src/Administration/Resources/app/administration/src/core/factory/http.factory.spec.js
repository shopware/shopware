/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
 */

import axios from 'axios';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

Shopware.Application.view.deleteReactive = () => {};

describe('core/factory/http.factory.js', () => {
    let httpClient;
    let mock;

    beforeEach(async () => {
        /**
         * axios-client-mock does not work with request interceptors. So we enable our interceptor here
         */
        process.env.NODE_ENV = 'prod';
        httpClient = createHTTPClient();
        mock = new MockAdapter(httpClient);
        process.env.NODE_ENV = 'test';
    });

    it('should create a HTTP client with response interceptors', async () => {
        expect(Object.getPrototypeOf(httpClient).isPrototypeOf(axios)).toBeTruthy();
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
        expect(typeof httpClient.request).toBe('function');
    });

    it('should support the axios URL and config call form', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        clientMock.onPost('/test-callable').reply(200, { success: true });

        const response = await client('/test-callable', {
            method: 'post',
            headers: { 'x-shopware-test': 'value' },
            data: { id: 'test-id' },
        });

        expect(response.data).toEqual({ success: true });
        expect(clientMock.history.post).toHaveLength(1);
        expect(clientMock.history.post[0].headers['x-shopware-test']).toBe('value');
    });

    it('should keep the axios form helpers compatible', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        clientMock.onPost('/test-form').reply((config) => {
            expect(config.headers['Content-Type']).toContain('multipart/form-data');
            return [
                200,
                {},
            ];
        });

        await client.postForm('/test-form', { name: 'first' });
        await client.postForm('/test-form', { name: 'second' });

        expect(clientMock.history.post).toHaveLength(2);
    });

    it('should apply public interceptors and defaults', async () => {
        const client = createHTTPClient();
        const clientMock = new MockAdapter(client);
        const requestInterceptor = jest.fn((config) => config);
        const responseInterceptor = jest.fn((response) => response);

        client.defaults.headers.common['x-shopware-test'] = 'mirrored';
        const requestInterceptorId = client.interceptors.request.use(requestInterceptor);
        const responseInterceptorId = client.interceptors.response.use(responseInterceptor);

        expect(client.interceptors.request.handlers[requestInterceptorId]).toMatchObject({
            fulfilled: requestInterceptor,
            synchronous: false,
            runWhen: null,
        });
        expect(client.interceptors.response.handlers[responseInterceptorId]).toMatchObject({
            fulfilled: responseInterceptor,
            synchronous: false,
            runWhen: null,
        });
        clientMock.onGet('/test-mirrored').reply((config) => {
            expect(config.headers['x-shopware-test']).toBe('mirrored');
            return [
                200,
                {},
            ];
        });

        await client.get('/test-mirrored');
        await client.get('/test-mirrored');

        expect(requestInterceptor).toHaveBeenCalledTimes(2);
        expect(responseInterceptor).toHaveBeenCalledTimes(2);
        expect(clientMock.history.get).toHaveLength(2);

        client.interceptors.request.eject(requestInterceptorId);
        client.interceptors.response.eject(responseInterceptorId);

        expect(client.interceptors.request.handlers[requestInterceptorId]).toBeNull();
        expect(client.interceptors.response.handlers[responseInterceptorId]).toBeNull();
        await client.get('/test-mirrored');
        await client.get('/test-mirrored');

        expect(requestInterceptor).toHaveBeenCalledTimes(2);
        expect(responseInterceptor).toHaveBeenCalledTimes(2);
    });

    it('should clear public interceptor handlers', () => {
        const client = createHTTPClient();

        client.interceptors.response.use((response) => response);
        client.interceptors.response.clear();

        expect(client.interceptors.response.handlers).toHaveLength(0);
    });

    it('should register public interceptors after handlers are replaced', () => {
        const client = createHTTPClient();
        client.interceptors.response.handlers = [];

        const interceptorId = client.interceptors.response.use((response) => response);

        expect(client.interceptors.response.handlers).toHaveLength(1);
        expect(interceptorId).toBe(0);
    });

    it('should have an isCancel method that detects cancellations', () => {
        expect(httpClient.isCancel(new axios.CanceledError('cancelled'))).toBe(true);
        expect(httpClient.isCancel(new Error('Regular error'))).toBe(false);
    });

    it('should have CancelToken for backward compatibility', () => {
        expect(httpClient.CancelToken).toBeDefined();
        expect(typeof httpClient.CancelToken.source).toBe('function');
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
