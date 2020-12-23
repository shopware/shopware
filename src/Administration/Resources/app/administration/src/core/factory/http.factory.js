/**
 * @module core/factory/http
 */
import Axios from 'axios';
import RefreshTokenHelper from 'src/core/helper/refresh-token.helper';

/**
 * Initializes the HTTP client with the provided context. The context provides the API end point and will be used as
 * the base url for the HTTP client.
 *
 * @method createHTTPClient
 * @memberOf module:core/factory/http
 * @param {Context} context Information about the environment
 * @returns {AxiosInstance}
 */
export default function createHTTPClient(context) {
    return createClient(context);
}

/**
 * Provides CancelToken so a request's promise from Http Client could be canceled.
 *
 * @returns { CancelToken, isCancel, Cancel}
 */
export const { CancelToken, isCancel, Cancel } = Axios;

/**
 * Creates the HTTP client with the provided context.
 *
 * @param {Context} context Information about the environment
 * @returns {AxiosInstance}
 */
function createClient() {
    const client = Axios.create({
        baseURL: Shopware.Context.api.apiPath
    });

    refreshTokenInterceptor(client);
    globalErrorHandlingInterceptor(client);

    return client;
}

/**
 * Sets up an interceptor to process global request errors
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function globalErrorHandlingInterceptor(client) {
    client.interceptors.response.use(response => response, error => {
        // Get $tc for translations and bind the Vue component scope to make it working
        const viewRoot = Shopware.Application.view.root;
        const $tc = viewRoot.$tc.bind(viewRoot);

        const { response: { status, data: { errors } } } = error;

        if (status === 403) {
            // create a fallback if the backend structure does not match the convention
            try {
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
                        message: `${$tc('sw-privileges.error.description')} <br> ${missingPrivilegesMessage}`
                    });
                });
            } catch (e) {
                Shopware.Utils.debug.error(e);

                errors.forEach(singleError => {
                    Shopware.State.dispatch('notification/createNotification', {
                        variant: 'error',
                        system: true,
                        autoClose: false,
                        growl: true,
                        title: singleError.title,
                        message: singleError.detail
                    });
                });
            }
        }

        if (status === 409) {
            try {
                if (errors[0].code === 'FRAMEWORK__DELETE_RESTRICTED') {
                    const parameters = errors[0].meta.parameters;

                    const entityName = parameters.entity;
                    let blockingEntities = '';
                    if (Shopware.Feature.isActive('FEATURE_NEXT_10539')) {
                        blockingEntities = parameters.usages.reduce((message, usageObject) => {
                            const times = usageObject.count;
                            const timesSnippet = $tc('global.default.xTimesIn', times);
                            const blockingEntitiesSnippet = $tc(`global.entities.${usageObject.entityName}`, times[1]);
                            return `${message}<br>${timesSnippet} <b>${blockingEntitiesSnippet}</b>`;
                        }, '');
                    } else {
                        blockingEntities = parameters.usages.reduce((message, entity) => {
                            const times = entity.match(/ \(([0-9]*)?\)/, '');
                            const timesSnippet = $tc('global.default.xTimesIn', times[1]);
                            const blockingEntitiesSnippet = $tc(`global.entities.${entity.replace(/ \([0-9]*?\)/, '')}`, times[1]);
                            return `${message}<br>${timesSnippet} <b>${blockingEntitiesSnippet}</b>`;
                        }, '');
                    }
                    Shopware.State.dispatch('notification/createNotification', {
                        variant: 'error',
                        title: $tc('global.default.error'),
                        message: `${$tc(
                            'global.notification.messageDeleteFailed',
                            3,
                            { entityName: $tc(`global.entities.${entityName}`) }
                        )
                        }${blockingEntities}`
                    });
                }
            } catch (e) {
                Shopware.Utils.debug.error(e);

                errors.forEach(singleError => {
                    Shopware.State.dispatch('notification/createNotification', {
                        variant: 'error',
                        title: singleError.title,
                        message: singleError.detail
                    });
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
                            method: () => window.location.reload()
                        }
                    ]
                });
            }
        }

        return Promise.reject(error);
    });

    return client;
}

/**
 * Sets up an interceptor to refresh the token, cache the requests and retry them after the token got refreshed.
 *
 * @param {AxiosInstance} client
 * @returns {AxiosInstance}
 */
function refreshTokenInterceptor(client) {
    const tokenHandler = new RefreshTokenHelper();

    client.interceptors.response.use((response) => {
        return response;
    }, (error) => {
        const { config, response: { status } } = error;
        const originalRequest = config;
        const resource = originalRequest.url.replace(originalRequest.baseURL, '');

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
                tokenHandler.subscribe((newToken) => {
                    // replace the expired token and retry
                    originalRequest.headers.Authorization = `Bearer ${newToken}`;
                    originalRequest.url = originalRequest.url.replace(originalRequest.baseURL, '');
                    resolve(Axios(originalRequest));
                }, (err) => {
                    if (!Shopware.Application.getApplicationRoot()) {
                        reject(err);
                        window.location.reload();
                        return;
                    }
                    Shopware.Application.getApplicationRoot().$router.push({ name: 'sw.login.index' });
                    reject(err);
                });
            });
        }

        return Promise.reject(error);
    });

    return client;
}
