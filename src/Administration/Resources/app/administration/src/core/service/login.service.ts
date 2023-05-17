/**
 * @package admin
 */

import { CookieStorage } from 'cookie-storage';
import html2canvas from 'html2canvas';
import type VueRouter from 'vue-router';

/** @private */
export interface AuthObject {
    access: string,
    refresh: string,
    expiry: number
}

interface TokenResponse {
    /* eslint-disable camelcase */
    access_token: string,
    refresh_token: string,
    expires_in: number,
    /* eslint-enable camelcase */
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface LoginService {
    loginByUsername: (user: string, pass: string) => Promise<AuthObject>,
    verifyUserByUsername: (user: string, pass: string) => Promise<AuthObject>,
    refreshToken: () => Promise<AuthObject['access']>,
    getToken: () => string,
    getBearerAuthentication: <K extends keyof AuthObject>(section?: K) => AuthObject[K],
    setBearerAuthentication: ({ access, refresh, expiry }: AuthObject) => AuthObject,
    logout: (isInactivityLogout?: boolean, shouldRedirect?: boolean) => boolean,
    forwardLogout(isInactivityLogout: boolean, shouldRedirect: boolean): void,
    isLoggedIn: () => boolean,
    addOnTokenChangedListener: (listener: (auth?: AuthObject) => void) => void,
    addOnLogoutListener: (listener: () => void) => void,
    addOnLoginListener: (listener: () => unknown) => void,
    getStorageKey: () => string,
    notifyOnLoginListener: () => (void[] | null),
    verifyUserToken: (password: string) => Promise<string>,
    getStorage: () => CookieStorage,
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createLoginService(
    httpClient: InitContainer['httpClient'],
    context: VuexRootState['context']['api'],
    bearerAuth: AuthObject | null = null,
): LoginService {
    /** @var {String} storageKey token */
    const storageKey = 'bearerAuth';
    const onTokenChangedListener: ((auth: AuthObject) => void)[] = [];
    const onLogoutListener: (() => void)[] = [];
    const onLoginListener: (() => void)[] = [];
    const cookieStorage = cookieStorageFactory();

    return {
        loginByUsername,
        verifyUserByUsername,
        refreshToken,
        getToken,
        getBearerAuthentication,
        setBearerAuthentication,
        logout,
        forwardLogout,
        isLoggedIn,
        addOnTokenChangedListener,
        addOnLogoutListener,
        addOnLoginListener,
        getStorageKey,
        notifyOnLoginListener,
        verifyUserToken,
        getStorage,
    };

    /**
     * Helper function to receive a logged in user token
     */
    function verifyUserToken(password: string): Promise<string> {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return verifyUserByUsername(Shopware.State.get('session').currentUser.username, password)
            .then(({ access }) => {
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
     */
    function loginByUsername(user: string, pass: string): Promise<AuthObject> {
        return httpClient.post<TokenResponse>('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username: user,
            password: pass,
        }, {
            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            baseURL: context.apiPath!,
        }).then((response) => {
            if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
                cookieStorage.setItem('lastActivity', `${Math.round(+new Date() / 1000)}`);
            }

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
     */
    function refreshToken(): Promise<AuthObject['access']> {
        const token = getRefreshToken();

        if (!token || !token.length) {
            return Promise.reject(new Error('No refresh token found.'));
        }

        return httpClient.post<TokenResponse>('/oauth/token', {
            grant_type: 'refresh_token',
            client_id: 'administration',
            scopes: 'write',
            refresh_token: token,
        }, {
        // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            baseURL: context.apiPath!,
        }).then((response) => {
            const expiry = response.data.expires_in;

            setBearerAuthentication({
                access: response.data.access_token,
                expiry: expiry,
                refresh: response.data.refresh_token,
            });

            return response.data.access_token;
        });
    }

    function verifyUserByUsername(user: string, pass: string): Promise<AuthObject> {
        return httpClient.post<TokenResponse>('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scope: 'user-verified',
            username: user,
            password: pass,
        }, {
            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            baseURL: context.apiPath!,
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
     */
    function addOnTokenChangedListener(listener: (auth?: AuthObject) => void): void {
        onTokenChangedListener.push(listener);
    }

    /**
     * Adds an Listener for the onLogoutEvent
     */
    function addOnLogoutListener(listener: () => void): void {
        onLogoutListener.push(listener);
    }

    /**
     * Adds an Listener for the onLoginEvent
     */
    function addOnLoginListener(listener: () => void): void {
        onLoginListener.push(listener);
    }

    /**
     * notifies the listener for the onTokenChangedEvent
     */
    function notifyOnTokenChangedListener(auth: AuthObject): void {
        onTokenChangedListener.forEach((callback) => {
            callback.call(null, auth);
        });
    }

    /**
     * notifies the listener for the onLogoutEvent
     */
    function notifyOnLogoutListener():void {
        onLogoutListener.forEach((callback) => {
            callback.call(null);
        });
    }


    /**
     * notifies the listener for the onLoginEvent
     */
    function notifyOnLoginListener(): void[] | null {
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
     */
    function setBearerAuthentication({ access, refresh, expiry }: AuthObject): AuthObject {
        expiry = Math.round(+new Date() / 1000) + expiry;
        const authObject = { access, refresh, expiry };
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            cookieStorage.setItem(storageKey, JSON.stringify(authObject));
        } else {
            bearerAuth = authObject;
        }

        if (isLoggedIn()) {
            notifyOnTokenChangedListener(authObject);
        }

        context.authToken = authObject;

        autoRefreshToken(expiry);

        return authObject;
    }

    /**
     * Refresh token in half of expiry time
     */
    let autoRefreshTokenTimeoutId: ReturnType<typeof setTimeout> | undefined;
    function autoRefreshToken(expiryTimestamp: number):void {
        if (autoRefreshTokenTimeoutId) {
            clearTimeout(autoRefreshTokenTimeoutId);
        }

        if (shouldConsiderUserActivity() && lastActivityOverThreshold()) {
            logout(true);

            return;
        }

        const timeUntilExpiry = expiryTimestamp * 1000 - Date.now();

        autoRefreshTokenTimeoutId = setTimeout(() => {
            void refreshToken();
        }, timeUntilExpiry / 2);
    }

    /**
     * Returns true if the last user activity is over the 30-minute threshold
     *
     * @private
     */
    function lastActivityOverThreshold(): boolean {
        const lastActivity = Number(cookieStorage.getItem('lastActivity'));

        // (Current time in seconds) - 25 minutes
        // 25 minutes + half the 10-minute expiry = 30 minute threshold
        const threshold = Math.round(+new Date() / 1000) - 1500;

        return lastActivity <= threshold;
    }

    function shouldConsiderUserActivity(): boolean {
        const devEnv = Shopware.Context.app.environment === 'development';

        return !devEnv;
    }

    /**
     * Returns saved bearer authentication object. Either you're getting the full object or when you're specifying
     * the `section` argument and getting either the token or the expiry date.
     */
    function getBearerAuthentication<K extends keyof AuthObject>(section?: K): AuthObject[K]

    // eslint-disable-next-line max-len
    function getBearerAuthentication<K extends keyof AuthObject>(section: K | null = null): false | AuthObject | AuthObject[K] {
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            try {
                bearerAuth = JSON.parse(cookieStorage.getItem(storageKey) as string) as AuthObject;
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
     */
    function logout(isInactivityLogout = false, shouldRedirect = true): boolean {
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            cookieStorage.removeItem(storageKey);
        }

        context.authToken = null;
        bearerAuth = null;
        localStorage.removeItem('rememberMe');

        forwardLogout(isInactivityLogout, shouldRedirect);

        return true;
    }

    /**
     * @private
     */
    function forwardLogout(isInactivityLogout: boolean, shouldRedirect: boolean): void {
        notifyOnLogoutListener();

        // @ts-expect-error
        const $router = Shopware.Application.getApplicationRoot().$router as null | VueRouter;
        if ($router) {
            const id = Shopware.Utils.createId();

            sessionStorage.setItem(`sw-admin-previous-route_${id}`, JSON.stringify({
                fullPath: $router.currentRoute.fullPath,
                name: $router.currentRoute.name,
            }));

            if (isInactivityLogout && shouldRedirect) {
                // @ts-expect-error - The app element exists
                void html2canvas(document.querySelector('#app'), { scale: 0.1 }).then(canvas => {
                    window.localStorage.setItem(`inactivityBackground_${id}`, canvas.toDataURL('image/jpeg'));

                    window.sessionStorage.setItem('lastKnownUser', Shopware.State.get('session').currentUser.username);

                    void $router.push({ name: 'sw.inactivity.login.index', params: { id } });
                });
            } else {
                void $router.push({ name: 'sw.login.index' });
            }
        }
    }

    /**
     * Returns the bearer token
     */
    function getToken(): string {
        return getBearerAuthentication('access');
    }

    /**
     * Returns the refresh token
     */
    function getRefreshToken(): string {
        return getBearerAuthentication('refresh');
    }

    /**
     * Checks if the user is logged in by checking if the bearer token exists
     * in the cookies.
     *
     * A check for expiration is not possible because the refresh token is longer
     * valid then the normal token.
     */
    function isLoggedIn(): boolean {
        const tokenExists = !!getToken();

        if (tokenExists) {
            autoRefreshToken(getBearerAuthentication('expiry'));
        }

        return tokenExists;
    }

    /**
     * Returns the storage key.
     */
    function getStorageKey(): string {
        return storageKey;
    }

    /**
     * Returns a CookieStorage instance with the right domain and path from the context.
     */
    function cookieStorageFactory(): CookieStorage {
        // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
        const path = context.basePath! + context.pathInfo!;

        // Set default cookie values
        return new CookieStorage(
            {
                path: path,
                domain: null,
                secure: false, // only allow HTTPs
                sameSite: 'Strict', // Should be Strict
            },
        );
    }

    /**
     * Returns the current cookie storage
     */
    function getStorage(): CookieStorage {
        return cookieStorage;
    }
}
