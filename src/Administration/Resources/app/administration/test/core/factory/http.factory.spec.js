import { cacheAdapterFactory } from 'src/core/factory/http.factory';

describe('core/factory/http.factory.js', () => {
    let requestCaches = {};
    let mockAdapter;

    beforeAll(() => {
        jest.spyOn(console, 'warn').mockImplementation();
    });

    beforeEach(() => {
        // use fake timers to simulate timeouts
        jest.useFakeTimers();

        requestCaches = {};
        mockAdapter = jest.fn((config) => Promise.resolve({
            request: config,
            response: 'success'
        }));
        console.warn.mockClear();
    });

    it('should cache the second request when its identical and happen shortly afterwards', async () => {
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
                'sw-api-compatibility': true
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1
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
                'sw-api-compatibility': true
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1
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
                'sw-api-compatibility': true
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1
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
                'sw-api-compatibility': true
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1
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
                'sw-api-compatibility': true
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1
        };

        const productSearchRequestWithNewToken = {
            ...productSearchRequest,
            headers: {
                ...productSearchRequest.headers,
                Authorization: 'Bearer NeWlOnGtOkEn'
            }
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
                'sw-api-compatibility': true
            },
            baseURL: '/api',
            timeout: 0,
            xsrfCookieName: 'XSRF-TOKEN',
            xsrfHeaderName: 'X-XSRF-TOKEN',
            maxContentLength: -1,
            maxBodyLength: -1
        };

        // do first request
        const firstRequest = await cacheAdapter(productSearchRequest);
        expect(firstRequest.response).toEqual('success');

        // mutate value from first request
        firstRequest.response = 'Very dangerous';
        expect(firstRequest.response).toEqual('Very dangerous');

        // expect that the original adapter was called only once
        expect(mockAdapter).toHaveBeenCalledTimes(1);
        // expect no warning in the console
        expect(console.warn).not.toHaveBeenCalled();

        // set timer 1 second forward so caching should be used
        jest.advanceTimersByTime(1000);

        // do second request
        const secondRequest = await cacheAdapter(productSearchRequest);
        expect(secondRequest.response).toEqual('success');

        // expect that the original adapter was called only once
        // because the second request should be cached when it is identical
        expect(mockAdapter).toHaveBeenCalledTimes(1);

        // expect a warning in the console
        expect(console.warn).toHaveBeenCalledTimes(1);
        expect(console.warn.mock.calls[0][1]).toContain('Duplicated requests happening in short amount of time');
    });
});
