/**
 * @sw-package framework
 *
 * @module core/factory/http
 */
import Axios from 'axios';
import AxiosV1 from 'axios-v1';
import getRefreshTokenHelper from 'src/core/helper/refresh-token.helper';
import cacheAdapterFactory from 'src/core/factory/cache-adapter.factory';
import { createAxiosV0Adapter, createAxiosV1Adapter } from 'src/core/factory/http-client-adapter';

/**
 * Initializes the HTTP client with the provided context. The context provides the API end point and will be used as
 * the base url for the HTTP client.
 *
 * @method createHTTPClient
 * @memberOf module:core/factory/http
 * @param {Context} context Information about the environment
 * @returns {AxiosInstance}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createHTTPClient(context) {
    return createClient(context);
}

/**
 * Provides CancelToken so a request's promise from Http Client could be canceled.
 *
 * @returns { CancelToken, isCancel, Cancel}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const { CancelToken, isCancel, Cancel } = Axios;

/**
 * Creates the HTTP client with the provided context.
 *
 * @param {Context} context Information about the environment
 * @returns {AxiosInstance}
 */
function createClient() {
    const isV68 = Shopware?.Feature?.isActive('V6_8_0_0');
    const baseConfig = {
        baseURL: Shopware.Context.api.apiPath,
        // Add request/response size limits to mitigate DoS vulnerability
        maxContentLength: 50 * 1024 * 1024, // 50MB limit
        maxBodyLength: 50 * 1024 * 1024, // 50MB limit
        timeout: 30000, // 30 second timeout
    };

    // Create both axios v0 and v1 instances
    const axiosV0 = Axios.create(baseConfig);
    const axiosV1 = AxiosV1.create(baseConfig);

    // Apply all interceptors to both clients
    refreshTokenInterceptor(axiosV0);
    refreshTokenInterceptor(axiosV1);

    globalErrorHandlingInterceptor(axiosV0);
    globalErrorHandlingInterceptor(axiosV1);

    storeSessionExpiredInterceptor(axiosV0);
    storeSessionExpiredInterceptor(axiosV1);

    tracingInterceptor(axiosV0);
    tracingInterceptor(axiosV1);

    /**
     * Don´t use cache in unit tests because it is possible
     * that the test uses the same route with different responses
     * (e.g. error, success) in a short amount of time.
     * So in test cases we are using the originalAdapter directly
     * and skipping the caching mechanism.
     *
     * Note: Axios v1 uses a different adapter architecture (array of adapter names)
     * that requires resolving to a function before wrapping with the cache adapter.
     * The requestCacheAdapterInterceptorV1 function handles this resolution.
     */
    if (process?.env?.NODE_ENV !== 'test') {
        requestCacheAdapterInterceptor(axiosV0);
        requestCacheAdapterInterceptorV1(axiosV1);
    }

    // Create adapters for both versions
    const adapterV0 = createAxiosV0Adapter(axiosV0);
    const adapterV1 = createAxiosV1Adapter(axiosV1);

    /**
     * Dispatcher function that routes requests to the appropriate axios version
     * based on the useAxiosV1 flag in the request config
     *
     * @param {Object} config - Axios request config
     * @returns {Promise} - Promise that resolves with the response
     */
    const dispatcher = (config) => {
        // Determine which axios version to use:
        // 1. If useAxiosV1 is explicitly set (true/false), use that
        // 2. Otherwise, check V6_8_0_0 feature flag (defaults to v1 when active)
        // 3. Fall back to v0 for backward compatibility
        const shouldUseV1 = config?.useAxiosV1 ?? isV68 ?? false;
        const targetAdapter = shouldUseV1 ? adapterV1 : adapterV0;

        return targetAdapter.runRequest(config);
    };

    // Add standard axios methods to the dispatcher
    dispatcher.request = (config) => dispatcher(config);
    dispatcher.get = (url, config = {}) => dispatcher({ ...config, method: 'get', url });
    dispatcher.delete = (url, config = {}) => dispatcher({ ...config, method: 'delete', url });
    dispatcher.head = (url, config = {}) => dispatcher({ ...config, method: 'head', url });
    dispatcher.options = (url, config = {}) => dispatcher({ ...config, method: 'options', url });
    dispatcher.post = (url, data, config = {}) => dispatcher({ ...config, method: 'post', url, data });
    dispatcher.put = (url, data, config = {}) => dispatcher({ ...config, method: 'put', url, data });
    dispatcher.patch = (url, data, config = {}) => dispatcher({ ...config, method: 'patch', url, data });
    dispatcher.getUri = (config = {}) => {
        const shouldUseV1 = config?.useAxiosV1 ?? isV68 ?? false;
        return shouldUseV1 ? axiosV1.getUri(config) : axiosV0.getUri(config);
    };

    // Add isCancel method that checks both adapters
    dispatcher.isCancel = (value) => {
        return adapterV0.isCancel(value) || adapterV1.isCancel(value);
    };

    // Keep CancelToken for backward compatibility with axios v0
    dispatcher.CancelToken = CancelToken;

    // Add interceptors property to maintain compatibility
    dispatcher.interceptors = isV68 ? axiosV1.interceptors : axiosV0.interceptors;
    dispatcher.interceptorsV0 = axiosV0.interceptors;
    dispatcher.interceptorsV1 = axiosV1.interceptors;

    // Add defaults property to maintain compatibility
    dispatcher.defaults = isV68 ? axiosV1.defaults : axiosV0.defaults;
    dispatcher.defaultsV0 = axiosV0.defaults;
    dispatcher.defaultsV1 = axiosV1.defaults;

    // Expose underlying axios instances for testing/mocking purposes
    dispatcher.axiosV0 = axiosV0;
    dispatcher.axiosV1 = axiosV1;

    return dispatcher;
}

/**
 * Sets up an interceptor to handle automatic cache of same requests in short time amount
 * for Axios v0.x
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function requestCacheAdapterInterceptor(client) {
    const requestCaches = {};
    client.interceptors.request.use((config) => {
        const originalAdapter = config.adapter;

        config.adapter = cacheAdapterFactory(originalAdapter, requestCaches);

        return config;
    });
}

/**
 * Sets up an interceptor to handle automatic cache of same requests in short time amount
 * for Axios v1.x
 *
 * In Axios v1, the adapter is an array of adapter names (e.g., ['xhr', 'http', 'fetch'])
 * that need to be resolved to an actual adapter function before wrapping.
 *
 * @param {AxiosInstance} client - The Axios v1 instance
 * @returns {AxiosInstance}
 */
function requestCacheAdapterInterceptorV1(client) {
    const requestCaches = {};
    client.interceptors.request.use((config) => {
        const originalAdapter = config.adapter;

        // In Axios v1, config.adapter is an array of adapter names
        // We need to resolve it to an actual adapter function
        const resolvedAdapter = AxiosV1.getAdapter(originalAdapter);

        // Now wrap the resolved adapter with the cache adapter
        config.adapter = cacheAdapterFactory(resolvedAdapter, requestCaches);

        return config;
    });
}

/**
 * Sets up an interceptor to process global request errors
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function globalErrorHandlingInterceptor(client) {
    client.interceptors.response.use(
        (response) => response,
        (error) => {
            const { hasOwnProperty } = Shopware.Utils.object;

            if (hasOwnProperty(error?.config?.headers ?? {}, 'sw-app-integration-id')) {
                return Promise.reject(error);
            }

            if (!error) {
                return Promise.reject(error);
            }

            const { status } = error.response ?? { status: undefined };
            const { errors, data } = error.response?.data ?? {
                errors: undefined,
                data: undefined,
            };

            try {
                handleErrorStates({ status, errors, error, data });
            } catch (e) {
                Shopware.Utils.debug.error(e);

                if (errors) {
                    errors.forEach((singleError) => {
                        Shopware.Store.get('notification').createNotification({
                            variant: 'error',
                            title: singleError.title,
                            message: singleError.detail,
                        });
                    });
                }
            }

            return Promise.reject(error);
        },
    );

    return client;
}

/**
 * Determines the different status codes and creates a matching error via Shopware.State
 * @param {Number} status
 * @param {Array} errors
 * @param {Object} error
 * @param {Object} data
 */
function handleErrorStates({ status, errors, error = null, data }) {
    // Get $tc for translations and bind the Vue component scope to make it working
    const viewRoot = Shopware.Application.view.root;

    // Handle sync-api errors
    if (status === 400 && (error?.response?.config?.url ?? '').includes('_action/sync')) {
        if (!data) {
            return;
        }

        // Get data for each entity
        Object.values(data).forEach((item) => {
            // Get error for each result
            item.result.forEach((resultItem) => {
                if (!resultItem.errors.length) {
                    return;
                }

                const statusCode = parseInt(resultItem.errors[0].status, 10);
                handleErrorStates({
                    status: statusCode,
                    errors: resultItem.errors,
                    data,
                });
            });
        });
    }

    if (status === 403) {
        const missingPrivilegeErrors = errors.filter((e) => e.code === 'FRAMEWORK__MISSING_PRIVILEGE_ERROR');
        missingPrivilegeErrors.forEach((missingPrivilegeError) => {
            const detail = JSON.parse(missingPrivilegeError.detail);
            let missingPrivileges = detail.missingPrivileges;

            // check if response is an object and not an array. If yes, then convert it
            if (!Array.isArray(missingPrivileges) && typeof missingPrivileges === 'object') {
                missingPrivileges = Object.values(missingPrivileges);
            }

            const missingPrivilegesMessage = missingPrivileges.reduce((message, privilege) => {
                return `${message}<br>"${privilege}"`;
            }, '');

            Shopware.Store.get('notification').createNotification({
                variant: 'error',
                system: true,
                autoClose: false,
                growl: true,
                title: Shopware.Snippet.tc('global.error-codes.FRAMEWORK__MISSING_PRIVILEGE_ERROR'),
                message: `${Shopware.Snippet.tc('sw-privileges.error.description')} <br> ${missingPrivilegesMessage}`,
            });
        });
    }

    if (
        status === 403 &&
        [
            'FRAMEWORK__STORE_SESSION_EXPIRED',
            'FRAMEWORK__STORE_SHOP_SECRET_INVALID',
        ].includes(errors[0]?.code)
    ) {
        Shopware.Store.get('notification').createNotification({
            variant: 'warning',
            system: true,
            autoClose: false,
            growl: true,
            title: Shopware.Snippet.tc('sw-extension.errors.storeSessionExpired.title'),
            message: Shopware.Snippet.tc('sw-extension.errors.storeSessionExpired.message'),
            actions: [
                {
                    label: Shopware.Snippet.tc('sw-extension.errors.storeSessionExpired.actionLabel'),
                    method: () => {
                        viewRoot.$router.push({
                            name: 'sw.extension.my-extensions.account',
                        });
                    },
                },
            ],
        });
    }

    if (status === 409) {
        if (errors[0].code === 'FRAMEWORK__DELETE_RESTRICTED') {
            const parameters = errors[0].meta.parameters;

            const entityName = parameters.entity;
            let blockingEntities = '';

            blockingEntities = parameters.usages.reduce((message, usageObject) => {
                const times = usageObject.count;
                const timesSnippet = Shopware.Snippet.tc('global.default.xTimesIn', times);
                const blockingEntitiesSnippet = Shopware.Snippet.tc(`global.entities.${usageObject.entityName}`, times[1]);
                return `${message}<br>${timesSnippet} <b>${blockingEntitiesSnippet}</b>`;
            }, '');

            Shopware.Store.get('notification').createNotification({
                variant: 'error',
                title: Shopware.Snippet.tc('global.default.error'),
                message: `${Shopware.Snippet.tc(
                    'global.notification.messageDeleteFailed',
                    { entityName: Shopware.Snippet.tc(`global.entities.${entityName}`) },
                    0,
                )}${blockingEntities}`,
            });
        }
    }

    if (status === 412) {
        const frameworkLanguageNotFound = errors.find((e) => e.code === 'FRAMEWORK__LANGUAGE_NOT_FOUND');

        if (frameworkLanguageNotFound) {
            localStorage.removeItem('sw-admin-current-language');

            Shopware.Store.get('notification').createNotification({
                variant: 'error',
                system: true,
                autoClose: false,
                growl: true,
                title: frameworkLanguageNotFound.title,
                message: `${frameworkLanguageNotFound.detail} Please reload the administration.`,
                actions: [
                    {
                        label: 'Reload administration',
                        method: () => window.location.reload(),
                    },
                ],
            });
        }
    }
}

/**
 * Sets up an interceptor to refresh the token, cache the requests and retry them after the token got refreshed.
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function refreshTokenInterceptor(client) {
    const tokenHandler = getRefreshTokenHelper();

    client.interceptors.response.use(
        (response) => {
            return response;
        },
        (error) => {
            const config = error.config || {};
            const status = error.response?.status;
            const originalRequest = config;
            const resource = originalRequest.url?.replace(originalRequest.baseURL, '');

            // eslint-disable-next-line inclusive-language/use-inclusive-words
            if (tokenHandler.whitelist.includes(resource)) {
                return Promise.reject(error);
            }

            if (status === 401) {
                if (!tokenHandler.isRefreshing) {
                    tokenHandler.fireRefreshTokenRequest().catch(() => {
                        return Promise.reject(error);
                    });
                }

                return new Promise((resolve, reject) => {
                    tokenHandler.subscribe(
                        (newToken) => {
                            // replace the expired token and retry
                            originalRequest.headers.Authorization = `Bearer ${newToken}`;
                            originalRequest.url = originalRequest.url.replace(originalRequest.baseURL, '');
                            resolve(client.request(originalRequest));
                        },
                        (err) => {
                            if (!Shopware.Application.getApplicationRoot()) {
                                reject(err);
                                window.location.reload();
                                return;
                            }

                            reject(err);
                        },
                    );
                });
            }

            return Promise.reject(error);
        },
    );

    return client;
}

/**
 * Sets up an interceptor to retry store requests that previously failed because the store session has expired.
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function storeSessionExpiredInterceptor(client) {
    const maxRetryLimit = 1;

    client.interceptors.response.use(
        (response) => {
            return response;
        },
        (error) => {
            const { config, response } = error;
            const code = response?.data?.errors?.[0]?.code;

            if (config?.storeSessionRequestRetries >= maxRetryLimit) {
                return Promise.reject(error);
            }

            const errorCodes = [
                'FRAMEWORK__STORE_SESSION_EXPIRED',
                'FRAMEWORK__STORE_SHOP_SECRET_INVALID',
            ];

            if (response?.status === 403 && errorCodes.includes(code)) {
                if (typeof config.storeSessionRequestRetries === 'number') {
                    config.storeSessionRequestRetries += 1;
                } else {
                    config.storeSessionRequestRetries = 1;
                }

                return client.request(config);
            }

            return Promise.reject(error);
        },
    );

    return client;
}

/**
 * Sets up an interceptor to add tracing information to the request headers on which admin page this request has been fired
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function tracingInterceptor(client) {
    /**
     * axios-client-mock does not work with request interceptors. So we have to disable it for tests.
     */
    if (process.env.NODE_ENV !== 'test') {
        client.interceptors.request.use((config) => {
            const currentRoute = Shopware?.Application?.view?.router?.currentRoute?.value?.name;

            if (currentRoute) {
                config.headers['shopware-admin-active-route'] = currentRoute;
            }

            return config;
        });
    }

    return client;
}
