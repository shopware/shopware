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
 * Creates the HTTP client with the provided context.
 *
 * @param {Context} context Information about the environment
 * @returns {AxiosInstance}
 */
function createClient(context) {
    const client = Axios.create({
        baseURL: context.apiResourcePath
    });

    return refreshTokenInterceptor(client);
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
