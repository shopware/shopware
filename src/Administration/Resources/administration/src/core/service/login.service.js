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
    /** @var {String} localStorage token */
    const localStorageKey = 'bearerAuth';
    const onTokenChangedListener = [];
    const onLogoutListener = [];
    const onLoginListener = [];

    return {
        loginByUsername,
        refreshToken,
        getToken,
        getBearerAuthentication,
        setBearerAuthentication,
        logout,
        isLoggedIn,
        addOnTokenChangedListener,
        addOnLogoutListener,
        addOnLoginListener,
        getLocalStorageKey
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
        return httpClient.post('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username: user,
            password: pass
        }, {
            baseURL: context.apiPath
        }).then((response) => {
            const auth = setBearerAuthentication({
                access: response.data.access_token,
                refresh: response.data.refresh_token,
                expiry: response.data.expires_in
            });

            notifyOnLoginListener();

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
            refresh_token: token
        }, {
            baseURL: context.apiPath
        }).then((response) => {
            setBearerAuthentication({
                access: response.data.access_token,
                expiry: response.data.expires_in,
                refresh: token
            });

            return response.data.access_token;
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
        return onLoginListener.map((callback) => {
            return callback.call(null);
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
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(localStorageKey, JSON.stringify(authObject));
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
        if (typeof localStorage !== 'undefined') {
            try {
                bearerAuth = JSON.parse(localStorage.getItem(localStorageKey));
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
     * Clears the local stored bearer authentication object.
     *
     * @memberOf module:core/service/login
     * @returns {Boolean}
     */
    function logout() {
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem(localStorageKey);
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
     * Checks if the user is logged in using the expiry time provided by the authentication end point.
     *
     * @memberOf module:core/service/login
     * @returns {Boolean}
     */
    function isLoggedIn() {
        const bearerAuthExpiry = getExpiry();
        return validateExpiry(bearerAuthExpiry);
    }

    function getLocalStorageKey() {
        return localStorageKey;
    }
}
