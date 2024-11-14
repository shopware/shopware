/**
 * @package admin
 */

import cacheAdapterFactory from 'src/core/factory/cache-adapter.factory';

describe('core/factory/cache-adapter.factory.js', () => {
    let requestCaches = {};
    let mockAdapter;

    beforeEach(async () => {
        // use fake timers to simulate timeouts
        jest.useFakeTimers();

        requestCaches = {};
        mockAdapter = jest.fn((config) =>
            Promise.resolve({
                request: config,
                response: 'success',
            }),
        );
    });

    it('should cache the second request when its identical and happen shortly afterwards', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const productSearchRequest = {
            url: '/search/product',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        // do first request
        cacheAdapter(productSearchRequest);

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 1 second forward so caching should be used
        jest.advanceTimersByTime(1000);

        // do second request
        cacheAdapter(productSearchRequest);

        // expect that the original adapter was called only once
        // because the second request should be cached when it is identical
        expect(mockAdapter).toHaveBeenCalledTimes(1);

        // expect a warning in the console
        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.warn.mock.calls[0][1]).toContain('Duplicated requests happening in short amount of time');
    });

    it('should not cache the second request when its identical and happen a bit later', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const productSearchRequest = {
            url: '/search/product',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        // do first request
        cacheAdapter(productSearchRequest);

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 2 seconds forward so no caching should be used
        jest.advanceTimersByTime(2000);

        // do second request
        cacheAdapter(productSearchRequest);

        // expect that the original adapter was called twice
        // because the second request was happening after the cache timer
        expect(mockAdapter).toHaveBeenCalledTimes(2);

        // expect no warning in the console because the identical
        // request does not happen in a short time amount
        expect(console.warn).toHaveBeenCalledTimes(0);
    });

    it('should not cache the second request when its different and happen shortly afterwards', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const productSearchRequest = {
            url: '/search/product',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        const productManufacturerRequest = {
            url: '/search/manufacturer',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        // do first request
        cacheAdapter(productSearchRequest);

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 1 second forward
        jest.advanceTimersByTime(1000);

        // do second request
        cacheAdapter(productManufacturerRequest);

        // expect that the original adapter was called twice
        // because the second request is different
        expect(mockAdapter).toHaveBeenCalledTimes(2);

        // expect no warning in the console
        expect(console.warn).toHaveBeenCalledTimes(0);
    });

    it('should also send a second request when only the token changes (for token refreshing)', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const productSearchRequest = {
            url: '/search/product',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        const productSearchRequestWithNewToken = {
            ...productSearchRequest,
            headers: {
                ...productSearchRequest.headers,
                Authorization: 'Bearer NeWlOnGtOkEn',
            },
        };

        // do first request
        cacheAdapter(productSearchRequest);

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 1 second forward
        jest.advanceTimersByTime(1000);

        // do second request
        cacheAdapter(productSearchRequestWithNewToken);

        // expect that the original adapter was called twice
        // because the second request is different
        expect(mockAdapter).toHaveBeenCalledTimes(2);

        // expect no warning in the console
        expect(console.warn).toHaveBeenCalledTimes(0);
    });

    it('should cache the second request when its identical and happen shortly afterwards. Mutating value in the first response should not be happening on the second request', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const productSearchRequest = {
            url: '/search/product',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        // do first request
        const firstRequest = await cacheAdapter(productSearchRequest);
        expect(firstRequest.response).toBe('success');

        // mutate value from first request
        firstRequest.response = 'Very dangerous';
        expect(firstRequest.response).toBe('Very dangerous');

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 1 second forward so caching should be used
        jest.advanceTimersByTime(1000);

        // do second request
        const secondRequest = await cacheAdapter(productSearchRequest);
        expect(secondRequest.response).toBe('success');

        // expect that the original adapter was called only once
        // because the second request should be cached when it is identical
        expect(mockAdapter).toHaveBeenCalledTimes(1);

        // expect a warning in the console
        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.warn.mock.calls[0][1]).toContain('Duplicated requests happening in short amount of time');
    });

    it('should clear the requestCaches when delete request is happening', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const productSearchRequest = {
            url: '/search/product',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        const productDeleteRequest = {
            url: '/product/1a2b3cd4',
            method: 'delete',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        // do post request
        await cacheAdapter(productSearchRequest);
        // expect caching the request
        expect(Object.values(requestCaches)).toHaveLength(1);

        // do delete request
        await cacheAdapter(productDeleteRequest);
        // expect removal off all cached requests
        expect(Object.values(requestCaches)).toHaveLength(0);
    });

    it('should clear the requestCaches when patch request is happening', async () => {
        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const productSearchRequest = {
            url: '/search/product',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        const productPatchRequest = {
            url: '/product/1a2b3cd4',
            method: 'patch',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        // do post request
        await cacheAdapter(productSearchRequest);
        // expect caching the request
        expect(Object.values(requestCaches)).toHaveLength(1);

        // do delete request
        await cacheAdapter(productPatchRequest);
        // expect removal off all cached requests
        expect(Object.values(requestCaches)).toHaveLength(0);
    });

    it('should clear the requestCaches when specifc data is created', async () => {
        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const productSearchRequest = {
            url: '/search/product',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        const userConfigCreateRequest = {
            url: '/user-config',
            method: 'post',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        // do post request
        await cacheAdapter(productSearchRequest);
        // expect caching the request
        expect(Object.values(requestCaches)).toHaveLength(1);

        // create new user config
        await cacheAdapter(userConfigCreateRequest);
        // expect removal off all cached requests
        expect(Object.values(requestCaches)).toHaveLength(0);
    });

    it('should not cache requests which are not in the allowUrlList', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const nonCachedSearchRequest = {
            url: '/search/do-not-cache',
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            headers: {
                Accept: 'application/vnd.api+json',
                'Content-Type': 'application/json',
                'sw-language-id': '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                Authorization: 'Bearer lOnGtOkEn',
                'sw-api-compatibility': true,
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1,
        };

        // do first request
        cacheAdapter(nonCachedSearchRequest);

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 1 second forward so caching should be used
        jest.advanceTimersByTime(1000);

        // do second request
        cacheAdapter(nonCachedSearchRequest);

        // expect that the original adapter was called twice
        // because the second request should not be cached because it is
        // not in the allowUrList
        expect(mockAdapter).toHaveBeenCalledTimes(2);

        // expect no warning in the console
        expect(console.warn).toHaveBeenCalledTimes(0);
    });
});
