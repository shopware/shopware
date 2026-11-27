/* eslint-disable sw-test-rules/test-file-max-lines-warning */

/**
 * @sw-package framework
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

    it('should cache config endpoints indefinitely without timeout', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const configMeRequest = {
            url: '_info/config-me',
            method: 'get',
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
        cacheAdapter(configMeRequest);

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 3 seconds forward (beyond normal timeout)
        jest.advanceTimersByTime(3000);

        // do second request
        cacheAdapter(configMeRequest);

        // expect that the original adapter was still called only once
        // because config endpoints are cached indefinitely
        expect(mockAdapter).toHaveBeenCalledTimes(1);

        // expect a warning in the console about duplicate requests
        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.warn.mock.calls[0][1]).toContain('Duplicated requests happening in short amount of time');
    });

    it('should cache config endpoints with query parameters', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const configMeRequestWithParams = {
            url: '_info/config-me?keys[]=search.preferences',
            method: 'get',
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
        cacheAdapter(configMeRequestWithParams);

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 3 seconds forward (beyond normal timeout)
        jest.advanceTimersByTime(3000);

        // do second request
        cacheAdapter(configMeRequestWithParams);

        // expect that the original adapter was still called only once
        // because config endpoints are cached indefinitely
        expect(mockAdapter).toHaveBeenCalledTimes(1);

        // expect a warning in the console about duplicate requests
        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.warn.mock.calls[0][1]).toContain('Duplicated requests happening in short amount of time');
    });

    it('should clear config endpoint cache when PATCH request is made to config endpoint', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);

        const configMeRequest = {
            url: '_info/config-me',
            method: 'get',
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

        const configMePostRequest = {
            url: '_info/config-me',
            method: 'patch',
            data: '{"search.preferences": {"enabled": true}}',
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

        // Cache a config request
        cacheAdapter(configMeRequest);
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        expect(Object.keys(requestCaches)).toHaveLength(1);

        // Make a POST request to modify config
        cacheAdapter(configMePostRequest);
        expect(mockAdapter).toHaveBeenCalledTimes(2);

        // Config cache should be cleared
        expect(Object.keys(requestCaches)).toHaveLength(0);

        // Next GET request should not use cache
        cacheAdapter(configMeRequest);
        expect(mockAdapter).toHaveBeenCalledTimes(3);
    });

    it.each([
        '/search/product-warehouse-group',
        '/search/product-manufacturer',
        '/search/property-group-option',
        '/search/order-line-item',
        '/search/customer-address',
        '/search/product/1a2b3cd4/categories',
    ])('should not serve a stale search for %s that only shares a prefix with an allowed url', async (url) => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        let total = 0;
        const searchAdapter = jest.fn((config) => Promise.resolve({ request: config, total }));
        const cacheAdapter = cacheAdapterFactory(searchAdapter, requestCaches);
        const searchRequest = {
            url,
            method: 'post',
            data: '{"page": 1, "limit": 25}',
            baseURL: '/api',
        };

        const before = await cacheAdapter(searchRequest);
        expect(before.total).toBe(0);

        await cacheAdapter({ url: '/product-warehouse-group', method: 'post', data: '{}', baseURL: '/api' });
        total = 1;

        jest.advanceTimersByTime(500);

        const after = await cacheAdapter(searchRequest);
        expect(after.total).toBe(1);
        expect(console.warn).not.toHaveBeenCalled();
    });

    it.each([
        '/product',
        'product',
        '/product/1a2b3cd4/categories',
        'app-system/action-button/run/1a2b3cd4',
    ])('should not serve a stale allowed search after an entity is created with POST %s', async (createUrl) => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        let total = 0;
        const searchAdapter = jest.fn((config) => Promise.resolve({ request: config, total }));
        const cacheAdapter = cacheAdapterFactory(searchAdapter, requestCaches);
        const searchRequest = { url: '/search/product', method: 'post', data: '{"page": 1}', baseURL: '/api' };

        expect((await cacheAdapter(searchRequest)).total).toBe(0);

        await cacheAdapter({ url: createUrl, method: 'post', data: '{}', baseURL: '/api' });
        total = 1;
        jest.advanceTimersByTime(500);

        expect((await cacheAdapter(searchRequest)).total).toBe(1);
        expect(console.warn).not.toHaveBeenCalled();
    });

    it.each([
        '/search/product-manufacturer',
        'search-ids/product',
        '_admin/search',
        '_admin/es-search',
        '_admin/product-stream-preview/1a2b3cd4',
        '/_admin/check-customer-email-valid',
        '/_action/calculate-price',
        '/_action/calculate-prices',
        '/_action/user/check-email-unique',
        '/_action/user/check-username-unique',
        '/_action/extension-sdk/sign-uri',
        '/_action/custom-snippet/render',
        '_admin/sanitize-html',
        '_admin/tag-filter-ids',
        '/_action/increment/user_activity',
    ])('should keep the cache when the POST %s is sent', async (url) => {
        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);
        const searchRequest = { url: '/search/product', method: 'post', data: '{"page": 1}', baseURL: '/api' };

        await cacheAdapter(searchRequest);
        await cacheAdapter({ url, method: 'post', data: '{}', baseURL: '/api' });

        expect(Object.keys(requestCaches)).toContain(JSON.stringify(searchRequest));
    });

    it.each([
        ['/search/product', 'post'],
        ['search/product', 'post'],
        ['/search/order', 'post'],
        ['_action/system-config', 'get'],
        ['/_action/product/types', 'get'],
    ])('should keep caching the allowed url %s', async (url, method) => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);
        const request = { url, method, data: '{"page": 1, "limit": 25}', baseURL: '/api' };

        await cacheAdapter(request);
        await cacheAdapter(request);

        expect(mockAdapter).toHaveBeenCalledTimes(1);
    });

    it.each([
        [
            { url: '_action/system-config', method: 'get', params: { domain: 'core.listing' } },
            'post',
            '_action/system-config',
        ],
        [
            { url: '/search/order', method: 'post', data: '{"ids": ["1a2b3cd4"]}' },
            'post',
            '/_action/order/1a2b3cd4/state/process',
        ],
        [{ url: '/search/product', method: 'post', data: '{"page": 1}' }, 'post', '/_action/clone/product/1a2b3cd4'],
        [{ url: '/search/product', method: 'post', data: '{"page": 1}' }, 'post', '_action/version/merge/product/1a2b3cd4'],
        [{ url: '/search/product', method: 'post', data: '{"page": 1}' }, 'put', '/_action/media/1a2b3cd4/upload'],
        [{ url: '_info/config-me', method: 'get' }, 'post', '_info/config-me'],
    ])('should not serve a stale %o after the write %s %s', async (readRequest, writeMethod, writeUrl) => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        let version = 0;
        const readAdapter = jest.fn((config) => Promise.resolve({ request: config, version }));
        const cacheAdapter = cacheAdapterFactory(readAdapter, requestCaches);
        const request = { ...readRequest, baseURL: '/api' };

        expect((await cacheAdapter(request)).version).toBe(0);

        await cacheAdapter({ url: writeUrl, method: writeMethod, data: '{}', baseURL: '/api' });
        version = 1;
        jest.advanceTimersByTime(500);

        expect((await cacheAdapter(request)).version).toBe(1);
        expect(console.warn).not.toHaveBeenCalled();
    });

    it('should refetch an indefinitely cached config endpoint after a write', async () => {
        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);
        const configMeRequest = { url: '_info/config-me', method: 'get', baseURL: '/api' };

        await cacheAdapter(configMeRequest);
        await cacheAdapter({ url: '/_action/order/1a2b3cd4/state/process', method: 'post', baseURL: '/api' });
        await cacheAdapter(configMeRequest);

        expect(mockAdapter).toHaveBeenCalledTimes(3);
    });

    it('should not serve a search that was sent while a write was still running', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        let version = 0;
        let finishWrite;
        const adapter = jest.fn((config) => {
            if (config.method === 'patch') {
                return new Promise((resolve) => {
                    finishWrite = () => {
                        version = 1;
                        resolve({ request: config });
                    };
                });
            }

            return Promise.resolve({ request: config, version });
        });
        const cacheAdapter = cacheAdapterFactory(adapter, requestCaches);
        const searchRequest = { url: '/search/product', method: 'post', data: '{"page": 1}', baseURL: '/api' };

        const write = cacheAdapter({ url: '/product/1a2b3cd4', method: 'patch', data: '{}', baseURL: '/api' });
        expect((await cacheAdapter(searchRequest)).version).toBe(0);

        finishWrite();
        await write;

        expect((await cacheAdapter(searchRequest)).version).toBe(1);
    });

    it.each(['head', 'options'])('should keep the cache when a %s request is sent', async (method) => {
        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);
        const searchRequest = { url: '/search/product', method: 'post', data: '{"page": 1}', baseURL: '/api' };

        await cacheAdapter(searchRequest);
        await cacheAdapter({ url: '/product/1a2b3cd4', method, baseURL: '/api' });

        expect(Object.keys(requestCaches)).toContain(JSON.stringify(searchRequest));
    });

    it('should treat a search with an absolute url like a relative one', async () => {
        jest.spyOn(global.console, 'warn').mockImplementation();

        const cacheAdapter = cacheAdapterFactory(mockAdapter, requestCaches);
        const baseURL = 'http://localhost:8000/api';
        const productSearch = { url: `${baseURL}/search/product`, method: 'post', data: '{"page": 1}', baseURL };
        const manufacturerSearch = { url: `${baseURL}/search/product-manufacturer`, method: 'post', baseURL };

        await cacheAdapter(productSearch);
        await cacheAdapter(manufacturerSearch);
        await cacheAdapter(productSearch);

        expect(mockAdapter).toHaveBeenCalledTimes(2);
    });
});
