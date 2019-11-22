/**
 * Refresh token helper which manages a cache of requests to retry them after the token got refreshed.
 * @class
 */
export default class RefreshTokenHelper {
    constructor() {
        this._isRefreshing = false;
        this._subscribers = [];
        this._errorSubscribers = [];
        this._whitelist = [
            '/oauth/token'
        ];
    }

    /**
     * Subscribe a new callback to the cache queue
     *
     * @param {Function} [callback = () => {}]
     * @param {Function} [errorCallback = () => {}]
     */
    subscribe(callback = () => {}, errorCallback = () => {}) {
        this._subscribers.push(callback);
        this._errorSubscribers.push(errorCallback);
    }

    /**
     * Event handler which will be fired when the token got refresh. It iterates over the registered
     * subscribers and fires the callbacks with the new token.
     *
     * @param {String} token - Renewed access token
     */
    onRefreshToken(token) {
        this._subscribers = this._subscribers.reduce((accumulator, callback) => {
            callback.call(null, token);
            return accumulator;
        }, []);
        this._errorSubscribers = [];
    }

    /**
     * Event handler which will be fired when the refresh token couldn't be fetched from the API
     *
     * @param {Error} err
     */
    onRefreshTokenFailed(err) {
        this._errorSubscribers = this._errorSubscribers.reduce((accumulator, callback) => {
            callback.call(null, err);
            return accumulator;
        }, []);
        this._subscribers = [];
    }

    /**
     * Fires the refresh token request and renews the bearer authentication in the login service.
     *
     * @returns {Promise<String>}
     */
    fireRefreshTokenRequest() {
        const loginService = Shopware.Service('loginService');
        this.isRefreshing = true;

        return loginService.refreshToken().then((newToken) => {
            this.onRefreshToken(newToken);
        }).finally(() => {
            this.isRefreshing = false;
        }).catch(() => {
            loginService.logout();
            this.onRefreshTokenFailed();
            return Promise.reject();
        });
    }

    get whitelist() {
        return this._whitelist;
    }

    set whitelist(urls) {
        this._whitelists = urls;
    }

    get isRefreshing() {
        return this._isRefreshing;
    }

    set isRefreshing(value) {
        this._isRefreshing = value;
    }
}
