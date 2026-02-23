/**
 * @sw-package framework
 */

import { CookieStorage } from 'cookie-storage';
import type { CookieOptions } from 'cookie-storage/lib/cookie-options';
import type { Router } from 'vue-router';
import type { ContextStore } from '../../app/store/context.store';

/** @private */
export interface AuthObject {
    access: string;
    refresh: string;
    expiry: number;
}

/** @private */
export interface LoginConfig {
    useDefault: boolean;
    url: string;
}

interface TokenResponse {
    /* eslint-disable camelcase */
    access_token: string;
    refresh_token: string;
    expires_in: number;
    /* eslint-enable camelcase */
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface LoginService {
    loginByUsername: (user: string, pass: string) => Promise<AuthObject>;
    verifyUserByUsername: (user: string, pass: string) => Promise<AuthObject>;
    refreshToken: () => Promise<AuthObject['access']>;
    getToken: () => string;
    getBearerAuthentication: <K extends keyof AuthObject>(section?: K) => AuthObject[K];
    setBearerAuthentication: ({ access, refresh, expiry }: AuthObject) => AuthObject;
    restartAutoTokenRefresh: (expiryTimestamp: number) => void;
    logout: (isInactivityLogout?: boolean, shouldRedirect?: boolean) => boolean;
    forwardLogout(isInactivityLogout: boolean, shouldRedirect: boolean): void;
    isLoggedIn: () => boolean;
    addOnTokenChangedListener: (listener: (auth?: AuthObject) => void) => void;
    addOnLogoutListener: (listener: () => void) => void;
    addOnLoginListener: (listener: () => unknown) => void;
    getStorageKey: () => string;
    notifyOnLoginListener: () => void[] | null;
    verifyUserToken: (password: string) => Promise<string>;
    getStorage: () => CookieStorage;
    setRememberMe: (active?: boolean) => void;
    getLoginTemplateConfig: () => Promise<LoginConfig>;
    subscribeToTokenRefresh: (successCallback: (token: string) => void, errorCallback: (error: Error) => void) => void;
    isRefreshing: () => boolean;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createLoginService(
    httpClient: InitContainer['httpClient'],
    context: ContextStore['api'],
    bearerAuth: AuthObject | null = null,
): LoginService {
    /** @var {String} storageKey token */
    const storageKey = 'bearerAuth';
    const onTokenChangedListener: ((auth: AuthObject) => void)[] = [];
    const onLogoutListener: (() => void)[] = [];
    const onLoginListener: (() => void)[] = [];
    const cookieStorage = cookieStorageFactory();
    let autoRefreshTokenTimeoutId: ReturnType<typeof setTimeout> | undefined;
    let logoutTimeoutId: ReturnType<typeof setTimeout> | undefined;

    /**
     * Tracks an in-flight token refresh request so that concurrent calls
     * to the refresh logic can share the same promise and avoid duplicate
     * network requests.
     */
    let refreshPromise: Promise<string> | null = null;
    /**
     * Counts the number of consecutive failed token refresh attempts for
     * the current token before giving up or triggering a logout.
     */
    let refreshRetryCount = 0;
    /**
     * Maximum number of consecutive token refresh retries allowed before
     * the refresh mechanism stops retrying.
     */
    const MAX_REFRESH_RETRIES = 5;

    // Subscriber pattern for token refresh events
    const refreshSubscribers: Array<(token: string) => void> = [];
    const refreshErrorSubscribers: Array<(error: Error) => void> = [];

    // Delay in milliseconds before logging out after a failed token refresh.
    // This gives other browser tabs time to potentially refresh the token successfully
    // and sync it via cookie storage, preventing unnecessary logouts in multi-tab scenarios.
    const TOKEN_SYNC_DELAY_MS = 1000;

    return {
        loginByUsername,
        verifyUserByUsername,
        refreshToken,
        getToken,
        getBearerAuthentication,
        setBearerAuthentication,
        restartAutoTokenRefresh,
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
        setRememberMe,
        getLoginTemplateConfig,
        subscribeToTokenRefresh,
        isRefreshing,
    };

    /**
     * Helper function to receive a logged in user token
     */
    function verifyUserToken(password: string): Promise<string> {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return verifyUserByUsername(Shopware.Store.get('session').currentUser?.username ?? '', password)
            .then(({ access }) => {
                if (Shopware.Utils.types.isString(access)) {
                    return access;
                }
                throw new Error('access Token should be of type String');
            })
            .catch((e) => {
                throw e;
            });
    }

    /**
     * Sends an AJAX request to the authentication end point and tries to log in the user with the provided
     * password.
     */
    function loginByUsername(user: string, pass: string): Promise<AuthObject> {
        return httpClient
            .post<TokenResponse>(
                '/oauth/token',
                {
                    grant_type: 'password',
                    client_id: 'administration',
                    scope: 'write',
                    username: user,
                    password: pass,
                },
                {
                    // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
                    baseURL: context.apiPath!,
                },
            )
            .then((response) => {
                Shopware.Service('userActivityService').updateLastUserActivity();

                const auth = setBearerAuthentication({
                    access: response.data.access_token,
                    refresh: response.data.refresh_token,
                    expiry: response.data.expires_in,
                });

                sessionStorage.setItem('redirectFromLogin', 'true');

                return auth;
            });
    }

    /**
     * Sends an AJAX request to the authentication end point and retries to refresh the token.
     */
    function refreshToken(): Promise<AuthObject['access']> {
        // No parallel requests - return existing promise if refresh is already in progress
        if (refreshPromise) {
            return refreshPromise;
        }

        const token = getRefreshToken();
        if (!token || !token.length) {
            return Promise.reject(new Error('No refresh token found.'));
        }

        refreshPromise = httpClient
            .post<TokenResponse>(
                '/oauth/token',
                {
                    grant_type: 'refresh_token',
                    client_id: 'administration',
                    scope: 'write',
                    refresh_token: token,
                },
                {
                    // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
                    baseURL: context.apiPath!,
                },
            )
            .then((response) => {
                const expiry = response.data.expires_in;
                const newToken = response.data.access_token;

                setBearerAuthentication({
                    access: newToken,
                    expiry: expiry,
                    refresh: response.data.refresh_token,
                });

                refreshRetryCount = 0;
                clearLogoutTimeout();

                // Notify all subscribers about successful token refresh
                refreshSubscribers.forEach((callback) => {
                    callback(newToken);
                });
                refreshSubscribers.length = 0;
                refreshErrorSubscribers.length = 0;

                return newToken;
            })
            .catch((error) => {
                refreshRetryCount += 1;

                if (refreshRetryCount >= MAX_REFRESH_RETRIES) {
                    refreshRetryCount = 0;
                    logout(true);
                } else {
                    const backoffMs = 2 ** refreshRetryCount * 1000;
                    scheduleRefreshRetry(backoffMs);
                }

                // Schedule logout if no token is present after delay
                scheduleLogoutIfNoToken();

                // Notify all error subscribers
                const errorObj = error instanceof Error ? error : new Error(String(error));
                refreshErrorSubscribers.forEach((callback) => {
                    callback(errorObj);
                });
                refreshSubscribers.length = 0;
                refreshErrorSubscribers.length = 0;

                return Promise.reject(error);
            })
            .finally(() => {
                refreshPromise = null;
            });

        return refreshPromise;
    }

    /**
     * Subscribe to token refresh events. Callbacks will be called when token refresh succeeds or fails.
     *
     * @param successCallback - Called with the new token when refresh succeeds
     * @param errorCallback - Called with the error when refresh fails
     */
    function subscribeToTokenRefresh(
        successCallback: (token: string) => void,
        errorCallback: (error: Error) => void,
    ): void {
        refreshSubscribers.push(successCallback);
        refreshErrorSubscribers.push(errorCallback);
    }

    /**
     * Returns whether a token refresh is currently in progress.
     */
    function isRefreshing(): boolean {
        return refreshPromise !== null;
    }

    /**
     * Clears any pending logout timeout.
     *
     * @private
     */
    function clearLogoutTimeout(): void {
        if (logoutTimeoutId !== undefined) {
            clearTimeout(logoutTimeoutId);
            logoutTimeoutId = undefined;
        }
    }

    /**
     * Schedules a logout if no token is present after a delay.
     * Only one timeout can be active at a time to prevent multiple queued callbacks.
     *
     * @private
     */
    function scheduleLogoutIfNoToken(): void {
        if (logoutTimeoutId !== undefined) {
            return;
        }

        if (!getToken()) {
            logoutTimeoutId = setTimeout(() => {
                logoutTimeoutId = undefined;
                if (!getToken()) {
                    logout();
                }
            }, TOKEN_SYNC_DELAY_MS);
        }
    }

    /**
     * Schedules a token refresh retry after a given delay.
     * Unlike {@link restartAutoTokenRefresh}, this uses the delay directly
     * without halving it, making it suitable for exponential backoff retry logic.
     *
     * @private
     */
    function scheduleRefreshRetry(delayMs: number): void {
        if (autoRefreshTokenTimeoutId) {
            clearTimeout(autoRefreshTokenTimeoutId);
            autoRefreshTokenTimeoutId = undefined;
        }
        
        const tokenBeforeRetry = getToken();

        autoRefreshTokenTimeoutId = setTimeout(() => {
            autoRefreshTokenTimeoutId = undefined;

            // Another tab may have already refreshed the token via cookie storage
            const currentToken = getToken();
            if (currentToken && currentToken !== tokenBeforeRetry) {
                refreshRetryCount = 0;
                return;
            }

            void refreshToken();
        }, delayMs);
    }

    function verifyUserByUsername(user: string, pass: string): Promise<AuthObject> {
        return httpClient
            .post<TokenResponse>(
                '/oauth/token',
                {
                    grant_type: 'password',
                    client_id: 'administration',
                    scope: 'user-verified',
                    username: user,
                    password: pass,
                },
                {
                    // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
                    baseURL: context.apiPath!,
                },
            )
            .then((response) => {
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
    function notifyOnLogoutListener(): void {
        onLogoutListener.forEach((callback) => {
            callback.call(null);
        });
    }

    /**
     * notifies the listener for the onLoginEvent
     */
    function notifyOnLoginListener(): void[] | null {
        if (!sessionStorage.getItem('redirectFromLogin')) {
            return null;
        }

        sessionStorage.removeItem('redirectFromLogin');

        return onLoginListener.map((callback) => {
            return callback.call(null);
        });
    }

    /**
     * Saves the bearer authentication object in the cookies using the {@link storageKey} as the
     * object identifier.
     */
    function setBearerAuthentication({ access, refresh, expiry }: AuthObject): AuthObject {
        expiry = Date.now() + expiry * 1000;

        const cookieOptions: CookieOptions = {
            expires: new Date(expiry),
        };

        if (!shouldConsiderUserActivity()) {
            const rememberMeDuration = context.refreshTokenTtl || 7 * 86400 * 1000;
            cookieOptions.expires = new Date(Date.now() + Number(rememberMeDuration));
        }

        const authObject = { access, refresh, expiry };
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            cookieStorage.setItem(storageKey, JSON.stringify(authObject), cookieOptions);
        } else {
            bearerAuth = authObject;
        }

        if (getToken()) {
            notifyOnTokenChangedListener(authObject);
        }

        context.authToken = authObject;

        restartAutoTokenRefresh(expiry);

        return authObject;
    }

    /**
     * Refresh token in half of expiry time
     */
    function restartAutoTokenRefresh(expiryTimestamp: number): void {
        if (autoRefreshTokenTimeoutId) {
            clearTimeout(autoRefreshTokenTimeoutId);
            autoRefreshTokenTimeoutId = undefined;
        }

        const timeUntilExpiry = (expiryTimestamp - Date.now()) / 2;

        autoRefreshTokenTimeoutId = setTimeout(() => {
            autoRefreshTokenTimeoutId = undefined;

            if (shouldConsiderUserActivity() && lastActivityOverThreshold()) {
                logout(true);
                return;
            }

            void refreshToken();
        }, timeUntilExpiry);
    }

    /**
     * Returns true if the last user activity is over the 30-minute threshold
     *
     * @private
     */
    function lastActivityOverThreshold(): boolean {
        const lastActivity = Shopware.Service('userActivityService').getLastUserActivity().getTime();

        // (Current time) - (30 minutes)
        const threshold = Date.now() - 30 * 60 * 1000;

        return lastActivity <= threshold;
    }

    function setRememberMe(active = true): void {
        if (!active) {
            localStorage.removeItem('rememberMe');
            return;
        }

        localStorage.setItem('rememberMe', 'true');
    }

    function shouldConsiderUserActivity(): boolean {
        const rememberMe = Boolean(localStorage.getItem('rememberMe'));
        const devEnv = Shopware.Context.app.environment === 'development';

        return !devEnv && !rememberMe;
    }

    /**
     * Returns saved bearer authentication object. Either you're getting the full object or when you're specifying
     * the `section` argument and getting either the token or the expiry date.
     */
    function getBearerAuthentication<K extends keyof AuthObject>(section?: K): AuthObject[K];

    // eslint-disable-next-line max-len
    function getBearerAuthentication<K extends keyof AuthObject>(
        section: K | null = null,
    ): false | AuthObject | AuthObject[K] {
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

        return bearerAuth[section] ? bearerAuth[section] : false;
    }

    /**
     * Clears the cookie stored bearer authentication object.
     */
    function logout(isInactivityLogout = false, shouldRedirect = true): boolean {
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            cookieStorage.removeItem(storageKey, { path: context.basePath });
            cookieStorage.removeItem(storageKey);
        }

        context.authToken = null;
        bearerAuth = null;
        setRememberMe(false);

        if (autoRefreshTokenTimeoutId) {
            clearTimeout(autoRefreshTokenTimeoutId);
            autoRefreshTokenTimeoutId = undefined;
        }

        clearLogoutTimeout();

        forwardLogout(isInactivityLogout, shouldRedirect);

        return true;
    }

    /**
     * @private
     */
    function forwardLogout(isInactivityLogout: boolean, shouldRedirect: boolean): void {
        notifyOnLogoutListener();

        // @ts-expect-error
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        const router = Shopware.Application.view.router as null | Router;
        if (router) {
            const id = Shopware.Utils.createId();

            sessionStorage.setItem(
                `sw-admin-previous-route_${id}`,
                JSON.stringify({
                    fullPath: router.currentRoute.value.fullPath,
                    name: router.currentRoute.value.name,
                }),
            );

            if (isInactivityLogout && shouldRedirect) {
                // Prevent multiple logout calls
                if (window.processingInactivityLogout) {
                    return;
                }

                // Dynamically import html2canvas only when needed to reduce initial bundle size
                void import('html2canvas')
                    .then((module) => {
                        const html2canvas = module.default;
                        const appElement = document.querySelector('#app') as HTMLElement;
                        if (!appElement) {
                            throw new Error('App element not found');
                        }
                        return html2canvas(appElement, {
                            scale: 0.1,
                        });
                    })
                    .then((canvas) => {
                        try {
                            sessionStorage.setItem(`inactivityBackground_${id}`, canvas.toDataURL('image/jpeg'));
                        } catch (e) {
                            // empty catch intended
                            // Calling toDataURL on a canvas with images from a different origin or css rules
                            // that contain urls to images from a different origin will throw a security error in Safari.
                        }
                    })
                    .catch((error) => {
                        // If html2canvas fails to load or execute, still proceed with logout
                        // in ".finally" block below
                        console.error('Failed to capture inactivity logout screenshot:', error);
                    })
                    .finally(() => {
                        sessionStorage.setItem('lastKnownUser', Shopware.Store.get('session').currentUser?.username ?? '');

                        window.processingInactivityLogout = true;

                        void router.push({
                            name: 'sw.inactivity.login.index',
                            params: { id },
                        });
                    });
            } else {
                sessionStorage.setItem('refresh-after-logout', 'true');

                void router.push({ name: 'sw.login.index' });
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
     * If the user was logged in but the last activity was over the threshold,
     * the user will be logged out and the function will return false.
     */
    function isLoggedIn(): boolean {
        const tokenExists = !!getToken();

        if (tokenExists && shouldConsiderUserActivity() && lastActivityOverThreshold()) {
            logout(true);
            return false;
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
        return new CookieStorage({
            path: path,
            domain: null,
            secure: false, // only allow HTTPs
            sameSite: 'Strict', // Should be Strict
        });
    }

    /**
     * Returns the current cookie storage
     */
    function getStorage(): CookieStorage {
        return cookieStorage;
    }

    function getLoginTemplateConfig(): Promise<LoginConfig> {
        return httpClient
            .get<LoginConfig>('/oauth/sso/config', {
                baseURL: context.apiPath!,
            })
            .then((response) => response.data);
    }
}
