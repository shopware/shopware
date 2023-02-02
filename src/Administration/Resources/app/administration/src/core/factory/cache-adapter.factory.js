/**
 * @package admin
 */

// this list contains all URLs which should be cached
const allowUrlList = [
    '/search/user-config',
    '/search/product',
    '/search/product-review',
    '/search/property-group',
    '/search/newsletter-recipient',
    '/search/salutation',
    '/search/product-search-config',
    '/search/product-search-config-field',
    '/app-system/action-button/product/list',
    'app-system/action-button/product/list',
    '/search/currency',
    '/search/order',
    '/search/customer',
    '/_info/me',
];

/**
 * When one of these urls get requested
 * the whole cache gets flushed
 * @type {string[]}
 */
const flushCacheUrls = [
    '/user-config',
    'user-config',
    '/_action/sync',
    '_action/sync',
    '/product-visibility',
    'product-visibility',
];

// the timeout at which the response in the cache gets cleared
const requestCacheTimeout = 1500;

/**
 * @deprecated tag:v6.6.0 - Will be private
 *
 * This cacheAdapterFactory creates an adapter for the axios
 * library. The created adapter do short time caching for
 * identical requests.
 *
 * @param originalAdapter
 * @param requestCaches
 * @returns {(function(*=): (*))|*}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function cacheAdapterFactory(originalAdapter, requestCaches = {}) {
    return (config) => {
        const requestChangesData = ['delete', 'patch'].includes(config?.method);
        const shouldFlushCache = flushCacheUrls.includes(config?.url);

        // remove all caches when something gets changed
        if (requestChangesData || shouldFlushCache) {
            Object.keys(requestCaches).forEach((key) => {
                delete requestCaches[key];
            });

            return originalAdapter(config);
        }

        // ignore requests which are not in the allowedUrlList
        const isNotInAllowList = !allowUrlList.includes(config?.url);
        if (isNotInAllowList) {
            return originalAdapter(config);
        }

        // use the stringified configuration as hashValue
        const requestHash = JSON.stringify(config);

        // check if identical requests already exists
        const identicalRequest = requestCaches[requestHash];
        if (identicalRequest) {
            Shopware.Utils.debug.warn(
                'http.factory',
                'Duplicated requests happening in short amount of time: ',
                config,
                'This duplicated request should be fixed.',
            );

            // when identical requests exists then return the previous value as a clone
            return cloneResponse(identicalRequest);
        }

        // when no identical request exists then
        // create a new one with the original adapter
        requestCaches[requestHash] = originalAdapter(config);

        // remove the request cache entry after 1.5 seconds
        setTimeout(() => {
            if (requestCaches[requestHash]) {
                delete requestCaches[requestHash];
            }
        }, requestCacheTimeout);

        // return a clone of the created request from the request cache
        return cloneResponse(requestCaches[requestHash]);
    };
}

/**
 * This function returns a clone of the original axios response object.
 * This guarantees that the response can be mutated and following requests are returning the
 * original, initial response values.
 * @param request
 * @returns {Promise<{finishedRequest: *, response: any}>}
 */
function cloneResponse(request) {
    return request.then((response) => {
        // response is in JSON format therefore JSON stringify is safe
        return JSON.parse(JSON.stringify(response));
    });
}
