/**
 * @module core/service/login
 */
import { Application } from 'src/core/shopware';

/**
 *
 * @memberOf module:core/service/login
 * @constructor
 * @method createLoginService
 * @param httpClient
 * @returns {Object}
 */
export default function createLoginService(httpClient) {
    /** @var {String} localStorage token */
    let localStorageKey = 'bearerAuth';

    return {
        loginByUsername,
        refreshTokenUsingRefreshToken,
        getToken,
        getRefreshToken,
        setBearerAuthentication,
        getExpiry,
        validateExpiry,
        getBearerAuthentication,
        clearBearerAuthentication,
        getLocalStorageKey,
        setLocalStorageKey
    };

    /**
     * Sends an AJAX request to the authentication end point and tries to log in the user with the provided
     * password.
     *
     * @memberOf module:core/service/login
     * @param {String} user Username
     * @param {String} pass Password
     * @returns {Observable<AjaxResponse>|AxiosPromise}
     */
    function loginByUsername(user, pass) {
        const contextService = Application.getContainer('init').contextService;

        return httpClient.post('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username: user,
            password: pass
        }, {
            baseURL: contextService.apiPath
        });
    }

    /**
     * Sends an AJAX request to the authentication end point and retries to refresh the token.
     *
     * @memberOf module:core/service/login
     * @param {String} token
     * @returns {Observable<AjaxResponse>|AxiosPromise}
     */
    function refreshTokenUsingRefreshToken(token) {
        const contextService = Application.getContainer('init').contextService;

        return httpClient.post('/oauth/token', {
            grant_type: 'refresh_token',
            client_id: 'administration',
            scopes: 'write',
            refresh_token: token
        }, {
            baseURL: contextService.apiPath
        });
    }

    /**
     * Saves the bearer authentication object in the localStorage using the {@link localStorageKey} as the
     * object identifier.
     *
     * @memberOf module:core/service/login
     * @param {String} token - Bearer token from the API
     * @param {Number} expiry - Expiry date as an unix timestamp
     * @returns {Object} saved authentication object
     */
    function setBearerAuthentication({ access, refresh, expiry }) {
        expiry = Math.round(+new Date() / 1000) + expiry;
        const authObject = { access, refresh, expiry };
        localStorage.setItem(localStorageKey, JSON.stringify(authObject));

        return authObject;
    }

    /**
     * Returns saved bearer authentication object. Either you're getting the full object or when you're specifying
     * the `section` argument and getting either the token or the expiry date.
     *
     * @memberOf module:core/service/login
     * @param {null|String} [section=null]
     * @returns {Boolean|String|Number}
     */
    function getBearerAuthentication(section = null) {
        const bearerAuth = JSON.parse(localStorage.getItem(localStorageKey));

        if (!bearerAuth) {
            return false;
        }

        if (!section) {
            return bearerAuth;
        }

        return (bearerAuth[section] ? bearerAuth[section] : false);
    }

    /**
     * Clears the local stored bearer authentication object.
     *
     * @memberOf module:core/service/login
     * @returns {Boolean}
     */
    function clearBearerAuthentication() {
        localStorage.removeItem(localStorageKey);
        return true;
    }

    /**
     * Returns the bearer token
     *
     * @memberOf module:core/service/login
     * @returns {Boolean|String}
     */
    function getToken() {
        return getBearerAuthentication('access');
    }

    /**
     * Returns the refresh token
     *
     * @memberOf module:core/service/login
     * @returns {Boolean|String}
     */
    function getRefreshToken() {
        return getBearerAuthentication('refresh');
    }

    /**
     * Returns the expiry date of the token as an unix timestamp.
     *
     * @memberOf module:core/service/login
     * @returns {Boolean|String|Number}
     */
    function getExpiry() {
        return getBearerAuthentication('expiry');
    }

    /**
     * Validates the token using the current time (based on the OS system clock of the user) and the server time.
     *
     * @memberOf module:core/service/login
     * @param {Number} expiry - Expiry date as an unix timestamp
     * @returns {Boolean}
     */
    function validateExpiry(expiry) {
        const timestamp = Math.round(+new Date() / 1000);
        return (expiry - timestamp) > 0;
    }

    /**
     * Returns the localStorage key
     *
     * @memberOf module:core/service/login
     * @returns {String}
     */
    function getLocalStorageKey() {
        return localStorageKey;
    }

    /**
     * Sets the localStorage key
     *
     * @memberOf module:core/service/login
     * @param {String} storageKey
     * @param {Boolean} [clearKey=true] Should the localStorage be cleared before setting a new auth object
     * @returns {String}
     */
    function setLocalStorageKey(storageKey, clearKey = true) {
        if (clearKey) {
            clearBearerAuthentication();
        }
        localStorageKey = storageKey;

        return localStorageKey;
    }
}
