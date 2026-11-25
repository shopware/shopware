/**
 * @sw-package framework
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
    '_action/system-config',
    '/_action/system-config',
    '/search/currency',
    '/search/order',
    '/search/customer',
    '/_info/me',
    '/_info/config-me',
    '_action/product/types',
    '/_action/product/types',
];

/**
 * When one of these urls get requested
 * the whole cache gets flushed
 * @type {string[]}
 */
const flushCacheUrls = [
    '/user-config',
    'user-config',
    '_action/system-config/batch',
    '/_action/system-config/batch',
    '/_action/sync',
    '_action/sync',
    '/product-visibility',
    'product-visibility',
    '/_info/config-me',
];

const readOnlyPostPaths = [
    /^search(-ids)?\//,
    /^_admin\/(es-)?search$/,
    /^_admin\/product-stream-preview\//,
    /^_admin\/check-customer-email-valid$/,
    /^_admin\/sanitize-html$/,
    /^_admin\/tag-filter-ids$/,
    /^_action\/calculate-prices?$/,
    /^_action\/user\/check-(email|username)-unique$/,
    /^_action\/extension-sdk\/sign-uri$/,
    /^_action\/custom-snippet\/render$/,
    /^_action\/increment\//,
];

// the timeout at which the response in the cache gets cleared
const requestCacheTimeout = 1500;

/**
 *
 * This cacheAdapterFactory creates an adapter for the axios
 * library. The created adapter do short time caching for
 * identical requests.
 *
 * @private
 * @param originalAdapter
 * @param requestCaches
 * @returns {(function(*=): (*))|*}
 */
export default function cacheAdapterFactory(originalAdapter, requestCaches = {}) {
    return (config) => {
        const requestPath = getRequestPath(config);
        const requestChangesData = changesData(config?.method, requestPath);
        const shouldFlushCache = flushCacheUrls.includes(config?.url);

        // remove all caches when something gets changed
        if (requestChangesData || shouldFlushCache) {
            const flushRequestCaches = () => {
                Object.keys(requestCaches).forEach((key) => {
                    delete requestCaches[key];
                });
            };

            flushRequestCaches();
            const request = originalAdapter(config);
            request.then(flushRequestCaches, flushRequestCaches);

            return request;
        }

        // ignore requests which are not in the allowedUrlList
        const isNotInAllowList = !allowUrlList.some((url) => url.replace(/^\//, '') === requestPath);

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

        // Only set timeout for non-config endpoints (config endpoints cached indefinitely)
        if (!config?.url?.includes('_info/')) {
            // remove the request cache entry after 1.5 seconds
            setTimeout(() => {
                if (requestCaches[requestHash]) {
                    delete requestCaches[requestHash];
                }
            }, requestCacheTimeout);
        }

        // return a clone of the created request from the request cache
        return cloneResponse(requestCaches[requestHash]);
    };
}

function getRequestPath(config) {
    let url = config?.url ?? '';

    if (config?.baseURL && /^[a-z][a-z\d+.-]*:\/\//i.test(url) && url.startsWith(config.baseURL)) {
        url = url.slice(config.baseURL.length);
    }

    return url.replace(/^\//, '').split('?')[0];
}

function changesData(method, requestPath) {
    if (method === 'post') {
        return !readOnlyPostPaths.some((pattern) => pattern.test(requestPath));
    }

    return ['put', 'patch', 'delete'].includes(method);
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
