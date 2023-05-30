/**
 * @package admin
 *
 * @module core/factory/http
 */
import Axios from 'axios';
import getRefreshTokenHelper from 'src/core/helper/refresh-token.helper';
import cacheAdapterFactory from 'src/core/factory/cache-adapter.factory';

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
    const client = Axios.create({
        baseURL: Shopware.Context.api.apiPath,
    });

    refreshTokenInterceptor(client);
    globalErrorHandlingInterceptor(client);
    storeSessionExpiredInterceptor(client);
    client.CancelToken = CancelToken;

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

    return client;
}

/**
 * Sets up an interceptor to handle automatic cache of same requests in short time amount
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
 * Sets up an interceptor to process global request errors
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function globalErrorHandlingInterceptor(client) {
    client.interceptors.response.use(response => response, error => {
        const { hasOwnProperty } = Shopware.Utils.object;

        if (hasOwnProperty(error?.config?.headers ?? {}, 'sw-app-integration-id')) {
            return Promise.reject(error);
        }

        if (!error) {
            return Promise.reject(error);
        }

        const { status } = error.response ?? { status: undefined };
        const { errors, data } = error.response?.data ?? { errors: undefined, data: undefined };

        try {
            handleErrorStates({ status, errors, error, data });
        } catch (e) {
            Shopware.Utils.debug.error(e);

            if (errors) {
                errors.forEach(singleError => {
                    Shopware.State.dispatch('notification/createNotification', {
                        variant: 'error',
                        title: singleError.title,
                        message: singleError.detail,
                    });
                });
            }
        }

        return Promise.reject(error);
    });

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
    const $tc = viewRoot.$tc.bind(viewRoot);

    // Handle sync-api errors
    if (status === 400 &&
        (error?.response?.config?.url ?? '').includes('_action/sync')) {
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
                handleErrorStates({ status: statusCode, errors: resultItem.errors, data });
            });
        });
    }

    if (status === 403) {
        const missingPrivilegeErrors = errors.filter(e => e.code === 'FRAMEWORK__MISSING_PRIVILEGE_ERROR');
        missingPrivilegeErrors.forEach(missingPrivilegeError => {
            const detail = JSON.parse(missingPrivilegeError.detail);
            let missingPrivileges = detail.missingPrivileges;

            // check if response is an object and not an array. If yes, then convert it
            if (!Array.isArray(missingPrivileges) && typeof missingPrivileges === 'object') {
                missingPrivileges = Object.values(missingPrivileges);
            }

            const missingPrivilegesMessage = missingPrivileges.reduce((message, privilege) => {
                return `${message}<br>"${privilege}"`;
            }, '');

            Shopware.State.dispatch('notification/createNotification', {
                variant: 'error',
                system: true,
                autoClose: false,
                growl: true,
                title: $tc('global.error-codes.FRAMEWORK__MISSING_PRIVILEGE_ERROR'),
                message: `${$tc('sw-privileges.error.description')} <br> ${missingPrivilegesMessage}`,
            });
        });
    }

    if (status === 403
        && ['FRAMEWORK__STORE_SESSION_EXPIRED', 'FRAMEWORK__STORE_SHOP_SECRET_INVALID'].includes(errors[0]?.code)
    ) {
        Shopware.State.dispatch('notification/createNotification', {
            variant: 'warning',
            system: true,
            autoClose: false,
            growl: true,
            title: $tc('sw-extension.errors.storeSessionExpired.title'),
            message: $tc('sw-extension.errors.storeSessionExpired.message'),
            actions: [{
                label: $tc('sw-extension.errors.storeSessionExpired.actionLabel'),
                method: () => {
                    viewRoot.$router.push({
                        name: 'sw.extension.my-extensions.account',
                    });
                },
            }],
        });
    }

    if (status === 409) {
        if (errors[0].code === 'FRAMEWORK__DELETE_RESTRICTED') {
            const parameters = errors[0].meta.parameters;

            const entityName = parameters.entity;
            let blockingEntities = '';

            blockingEntities = parameters.usages.reduce((message, usageObject) => {
                const times = usageObject.count;
                const timesSnippet = $tc('global.default.xTimesIn', times);
                const blockingEntitiesSnippet = $tc(`global.entities.${usageObject.entityName}`, times[1]);
                return `${message}<br>${timesSnippet} <b>${blockingEntitiesSnippet}</b>`;
            }, '');

            Shopware.State.dispatch('notification/createNotification', {
                variant: 'error',
                title: $tc('global.default.error'),
                message: `${$tc(
                    'global.notification.messageDeleteFailed',
                    3,
                    { entityName: $tc(`global.entities.${entityName}`) },
                )
                }${blockingEntities}`,
            });
        }
    }

    if (status === 412) {
        const frameworkLanguageNotFound = errors.find((e) => e.code === 'FRAMEWORK__LANGUAGE_NOT_FOUND');

        if (frameworkLanguageNotFound) {
            localStorage.removeItem('sw-admin-current-language');

            Shopware.State.dispatch('notification/createNotification', {
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

    client.interceptors.response.use((response) => {
        return response;
    }, (error) => {
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
                        resolve(Axios(originalRequest));
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
    });

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

    client.interceptors.response.use((response) => {
        return response;
    }, (error) => {
        const { config, response } = error;
        const code = response?.data?.errors?.[0]?.code;

        if (config?.storeSessionRequestRetries >= maxRetryLimit) {
            return Promise.reject(error);
        }

        const errorCodes = [
            'FRAMEWORK__STORE_SESSION_EXPIRED',
            'FRAMEWORK__STORE_SHOP_SECRET_INVALID',
        ];

        if (response.status === 403 && errorCodes.includes(code)) {
            if (typeof config.storeSessionRequestRetries === 'number') {
                config.storeSessionRequestRetries += 1;
            } else {
                config.storeSessionRequestRetries = 1;
            }

            return client.request(config);
        }

        return Promise.reject(error);
    });

    return client;
}
