/**
 * @sw-package framework
 *
 * @module core/factory/http
 */
import Axios from 'axios';
import cacheAdapterFactory from 'src/core/factory/cache-adapter.factory';

/**
 * Initializes the HTTP client with the provided context. The context provides the API end point and will be used as
 * the base url for the HTTP client.
 *
 * @method createHTTPClient
 * @memberOf module:core/factory/http
 * @param {Context} context Information about the environment
 * @returns {import('./http-client.types').HttpClient}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createHTTPClient(context) {
    return createClient(context);
}

/**
 * Provides CancelToken so a request's promise from Http Client could be canceled.
 *
 * @deprecated tag:v6.8.0 - `CancelToken` and `Cancel` will be removed. Create an `AbortController`, pass its
 * `signal` in the request config and detect cancellations with `httpClient.isCancel(error)`.
 * @returns { CancelToken, isCancel, Cancel}
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const { CancelToken, isCancel, Cancel } = Axios;

/**
 * Creates the HTTP client with the provided context.
 *
 * The client is a single Axios 1.x instance. Axios picks the XHR adapter in the browser, which is what makes
 * `onUploadProgress` work for media uploads. Do not force the fetch adapter here: fetch cannot report upload progress.
 *
 * @param {Context} context Information about the environment
 * @returns {import('./http-client.types').HttpClient}
 */
function createClient() {
    const client = Axios.create({
        baseURL: Shopware.Context.api.apiPath,
        // Add request/response size limits to mitigate DoS vulnerability
        maxContentLength: 50 * 1024 * 1024, // 50MB limit
        maxBodyLength: 50 * 1024 * 1024, // 50MB limit
        timeout: 30000, // 30 second timeout
    });

    refreshTokenInterceptor(client);
    globalErrorHandlingInterceptor(client);
    storeSessionExpiredInterceptor(client);
    tracingInterceptor(client);
    legacyRequestOptionsInterceptor(client);

    /**
     * Don´t use cache in unit tests because it is possible
     * that the test uses the same route with different responses
     * (e.g. error, success) in a short amount of time.
     * So in test cases we are using the originalAdapter directly
     * and skipping the caching mechanism.
     */
    if (process?.env?.NODE_ENV !== 'test') {
        requestCacheAdapterInterceptor(client);
    }

    client.isCancel = isCancelledRequest;

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use `AbortController` and the `signal` request option instead.
     */
    client.CancelToken = Axios.CancelToken;

    /**
     * @deprecated tag:v6.8.0 - The version-specific runtime escape hatches will be removed. The client itself is the
     * Axios instance; use `httpClient`, `httpClient.interceptors` and `httpClient.defaults` directly.
     */
    client.axiosV1 = client;
    client.interceptorsV1 = client.interceptors;
    client.defaultsV1 = client.defaults;

    return client;
}

/**
 * Checks whether the given error was caused by a cancelled request. Detects the `CanceledError` thrown by
 * `AbortController` cancellations as well as errors produced by the deprecated `CancelToken`.
 *
 * @param {unknown} value
 * @returns {boolean}
 */
function isCancelledRequest(value) {
    if (Axios.isCancel(value)) {
        return true;
    }

    if (!value || typeof value !== 'object') {
        return false;
    }

    return value.name === 'CanceledError' || value.code === 'ERR_CANCELED';
}

/**
 * Sets up an interceptor to handle automatic cache of same requests in short time amount.
 *
 * `config.adapter` is Axios' adapter preference list (e.g. `['xhr', 'http', 'fetch']`), so it has to be resolved
 * to the actual adapter function before it can be wrapped by the cache adapter.
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function requestCacheAdapterInterceptor(client) {
    const requestCaches = {};
    client.interceptors.request.use((config) => {
        const originalAdapter = Axios.getAdapter(config.adapter);

        config.adapter = cacheAdapterFactory(originalAdapter, requestCaches);

        return config;
    });

    return client;
}

/**
 * Sets up an interceptor that accepts the request options of the removed dual-transport facade and warns once
 * per client so extensions get migration feedback.
 *
 * The interceptor runs synchronously so the request pipeline keeps its synchronous fast path.
 *
 * @deprecated tag:v6.8.0 - Will be removed together with the `useAxiosV1` and `cancelToken` request options.
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function legacyRequestOptionsInterceptor(client) {
    let warnedTransportFlag = false;
    let warnedCancelToken = false;

    client.interceptors.request.use(
        (config) => {
            if (config.useAxiosV1 !== undefined && !warnedTransportFlag) {
                warnedTransportFlag = true;
                Shopware.Utils.debug.warn(
                    'http.factory',
                    'The "useAxiosV1" request option is deprecated and has no effect anymore. ' +
                        'The Administration HTTP client only ships Axios 1.x. Remove the option from your requests.',
                );
            }

            if (config.cancelToken && !warnedCancelToken) {
                warnedCancelToken = true;
                Shopware.Utils.debug.warn(
                    'http.factory',
                    'The "cancelToken" request option and "httpClient.CancelToken" are deprecated. ' +
                        'Use an AbortController and pass its "signal" in the request config instead.',
                );
            }

            return config;
        },
        null,
        { synchronous: true },
    );

    return client;
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
    const skipList = ['/oauth/token'];

    client.interceptors.response.use(
        (response) => {
            return response;
        },
        (error) => {
            const config = error.config || {};
            const status = error.response?.status;
            const originalRequest = config;
            const resource = originalRequest.url?.replace(originalRequest.baseURL, '');

            if (skipList.includes(resource)) {
                // For /oauth/token endpoint, reject immediately to avoid infinite loops
                // This endpoint returns 400 when token is revoked (invalid_grant error)
                return Promise.reject(error);
            }

            if (status === 401) {
                const errorCode = error.response?.data?.errors?.[0]?.code;

                // Do not retry on SSO_LOGIN__TOKEN_NOT_FOUND 401 error that is not related to an expired admin token
                if (errorCode === 'SSO_LOGIN__TOKEN_NOT_FOUND') {
                    return Promise.reject(error);
                }

                // Prevent infinite retry loops - only allow one token refresh retry per request
                if (originalRequest._tokenRefreshRetry) {
                    return Promise.reject(error);
                }

                const loginService = Shopware.Service('loginService');

                // Intentionally ignore refresh token errors here; they are handled via subscribeToTokenRefresh.
                loginService.refreshToken().catch(() => undefined);

                return new Promise((resolve, reject) => {
                    loginService.subscribeToTokenRefresh(
                        (newToken) => {
                            // replace the expired token and retry
                            originalRequest.headers.Authorization = `Bearer ${newToken}`;
                            originalRequest.url = originalRequest.url.replace(originalRequest.baseURL, '');
                            originalRequest._tokenRefreshRetry = true;
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
