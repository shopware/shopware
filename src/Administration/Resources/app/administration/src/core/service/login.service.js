import { CookieStorage } from 'cookie-storage';

/**
 * @module core/service/login
 */

/**
 *
 * @memberOf module:core/service/login
 * @constructor
 * @method createLoginService
 * @param httpClient
 * @param context
 * @param bearerAuth
 * @returns {Object}
 */
export default function createLoginService(httpClient, context, bearerAuth = null) {
    /** @var {String} storageKey token */
    const storageKey = 'bearerAuth';
    const onTokenChangedListener = [];
    const onLogoutListener = [];
    const onLoginListener = [];
    const cookieStorage = cookieStorageFactory();

    return {
        loginByUsername,
        verifyUserByUsername,
        refreshToken,
        getToken,
        getBearerAuthentication,
        setBearerAuthentication,
        logout,
        isLoggedIn,
        addOnTokenChangedListener,
        addOnLogoutListener,
        addOnLoginListener,
        getStorageKey,
        notifyOnLoginListener,
        verifyUserToken,
    };

    /**
     * Helper function to receive a logged in user token
     * @returns {response.data.access_token} returns an OAuth token
     * @param password
     */
    function verifyUserToken(password) {
        return this.verifyUserByUsername(Shopware.State.get('session').currentUser.username, password).then(({ access }) => {
            if (Shopware.Utils.types.isString(access)) {
                return access;
            }
            throw new Error('access Token should be of type String');
        }).catch((e) => {
            throw e;
        });
    }

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
        return httpClient.post('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username: user,
            password: pass,
        }, {
            baseURL: context.apiPath,
        }).then((response) => {
            const auth = setBearerAuthentication({
                access: response.data.access_token,
                refresh: response.data.refresh_token,
                expiry: response.data.expires_in,
            });

            window.localStorage.setItem('redirectFromLogin', 'true');

            return auth;
        });
    }

    /**
     * Sends an AJAX request to the authentication end point and retries to refresh the token.
     *
     * @memberOf module:core/service/login
     * @returns {Observable<AjaxResponse>|AxiosPromise}
     */
    function refreshToken() {
        const token = getRefreshToken();

        if (!token || !token.length) {
            return Promise.reject(new Error('No refresh token found.'));
        }

        return httpClient.post('/oauth/token', {
            grant_type: 'refresh_token',
            client_id: 'administration',
            scopes: 'write',
            refresh_token: token,
        }, {
            baseURL: context.apiPath,
        }).then((response) => {
            setBearerAuthentication({
                access: response.data.access_token,
                expiry: response.data.expires_in,
                refresh: response.data.refresh_token,
            });

            return response.data.access_token;
        });
    }

    function verifyUserByUsername(user, pass) {
        return httpClient.post('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scope: 'user-verified',
            username: user,
            password: pass,
        }, {
            baseURL: context.apiPath,
        }).then((response) => {
            return {
                access: response.data.access_token,
                expiry: response.data.expires_in,
                refresh: response.data.refresh_token,
            };
        });
    }

    /**
     * Adds an Listener for the onTokenChangedEvent
     * @param {Function} listener
     */
    function addOnTokenChangedListener(listener) {
        onTokenChangedListener.push(listener);
    }

    /**
     * Adds an Listener for the onLogoutEvent
     * @param {Function} listener
     */
    function addOnLogoutListener(listener) {
        onLogoutListener.push(listener);
    }

    /**
     * Adds an Listener for the onLoginEvent
     * @param {Function} listener
     */
    function addOnLoginListener(listener) {
        onLoginListener.push(listener);
    }

    /**
     * notifies the listener for the onTokenChangedEvent
     */
    function notifyOnTokenChangedListener(auth) {
        onTokenChangedListener.forEach((callback) => {
            callback.call(null, auth);
        });
    }

    /**
     * notifies the listener for the onLogoutEvent
     */
    function notifyOnLogoutListener() {
        onLogoutListener.forEach((callback) => {
            callback.call(null);
        });
    }


    /**
     * notifies the listener for the onLoginEvent
     */
    function notifyOnLoginListener() {
        if (!window.localStorage.getItem('redirectFromLogin')) {
            return null;
        }

        window.localStorage.removeItem('redirectFromLogin');

        return onLoginListener.map((callback) => {
            return callback.call(null);
        });
    }

    /**
     * Saves the bearer authentication object in the cookies using the {@link storageKey} as the
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
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            cookieStorage.setItem(storageKey, JSON.stringify(authObject));
        } else {
            bearerAuth = authObject;
        }
        notifyOnTokenChangedListener(authObject);

        context.authToken = authObject;

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
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            try {
                bearerAuth = JSON.parse(cookieStorage.getItem(storageKey));
            } catch {
                bearerAuth = null;
            }
        }

        context.authToken = bearerAuth;

        if (!bearerAuth) {
            return false;
        }

        if (!section) {
            return bearerAuth;
        }

        return (bearerAuth[section] ? bearerAuth[section] : false);
    }

    /**
     * Clears the cookie stored bearer authentication object.
     *
     * @memberOf module:core/service/login
     * @returns {Boolean}
     */
    function logout() {
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            cookieStorage.removeItem(storageKey);

            // @deprecated tag:v6.5.0 - Was needed for old cookies set without domain
            // eslint-disable-next-line max-len
            document.cookie = `bearerAuth=deleted; expires=Thu, 18 Dec 2013 12:00:00 UTC;path=${context.basePath + context.pathInfo}`;
        }

        context.authToken = null;
        bearerAuth = null;

        notifyOnLogoutListener();

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
     * Checks if the user is logged in by checking if the bearer token exists
     * in the cookies.
     *
     * A check for expiration is not possible because the refresh token is longer
     * valid then the normal token.
     *
     * @memberOf module:core/service/login
     * @returns {Boolean}
     */
    function isLoggedIn() {
        return !!getToken();
    }

    /**
     * Returns the storage key.
     *
     * @returns {String}
     */
    function getStorageKey() {
        return storageKey;
    }

    /**
     * Returns a CookieStorage instance with the right domain and path from the context.
     *
     * @returns {CookieStorage}
     */
    function cookieStorageFactory() {
        let domain;

        if (typeof window === 'object') {
            domain = window.location.hostname;
        } else {
            // eslint-disable-next-line no-restricted-globals
            const url = new URL(self.location.origin);
            domain = url.hostname;
        }

        const path = context.basePath + context.pathInfo;

        // Set default cookie values
        return new CookieStorage(
            {
                path: path,
                domain: domain,
                secure: false, // only allow HTTPs
                sameSite: 'Strict', // Should be Strict
            },
        );
    }
}
