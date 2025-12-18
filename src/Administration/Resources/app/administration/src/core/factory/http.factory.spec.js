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

    it('should have standard axios methods (get, post, etc.)', () => {
        expect(typeof httpClient.get).toBe('function');
        expect(typeof httpClient.post).toBe('function');
        expect(typeof httpClient.put).toBe('function');
        expect(typeof httpClient.patch).toBe('function');
        expect(typeof httpClient.delete).toBe('function');
        expect(typeof httpClient.request).toBe('function');
    });

    it('should use axios v0 by default (without useAxiosV1 flag)', async () => {
        mock.onGet('/test-v0-default').reply(200, { version: 'v0' });

        const response = await httpClient.get('/test-v0-default');

        expect(response.data).toEqual({ version: 'v0' });
        expect(mock.history.get).toHaveLength(1);
    });

    it('should support requests with useAxiosV1 flag in config', async () => {
        // This tests that the useAxiosV1 flag is accepted without errors
        // Full integration testing of v1 routing requires more complex mock setup
        mock.onPost('/test-with-flag').reply(200, { success: true });

        const response = await httpClient.post(
            '/test-with-flag',
            { data: 'test' },
            {
                useAxiosV1: false, // Explicitly use v0 to ensure mock works
            },
        );

        expect(response.data).toEqual({ success: true });
    });

    it('should have an isCancel method that detects cancellations', () => {
        // Test axios v0 style cancellation - axios.isCancel checks for __CANCEL__ property
        const v0CancelError = { __CANCEL__: true };
        expect(httpClient.isCancel(v0CancelError)).toBe(true);

        // Test axios v1 style cancellation
        const v1CancelError = { name: 'CanceledError', code: 'ERR_CANCELED' };
        expect(httpClient.isCancel(v1CancelError)).toBe(true);

        // Test non-cancellation error
        const regularError = new Error('Regular error');
        expect(httpClient.isCancel(regularError)).toBe(false);
    });

    it('should have CancelToken for backward compatibility', () => {
        expect(httpClient.CancelToken).toBeDefined();
        expect(typeof httpClient.CancelToken.source).toBe('function');
    });
});
