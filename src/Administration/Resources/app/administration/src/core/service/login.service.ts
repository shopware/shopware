/**
 * @sw-package framework
 */

import { CookieStorage } from 'cookie-storage';
import type { CookieOptions } from 'cookie-storage/lib/cookie-options';
import type { Router } from 'vue-router';
import type { ContextStore } from '../../app/store/context.store';
import { getAssertion } from '../helper/webauthn.helper';
import type { PublicKeyCredentialRequestOptionsJson } from '../helper/webauthn.helper';

/** @private */
export interface AuthObject {
    access: string;
    refresh: string;
    expiry: number;
}

interface TokenResponse {
    access_token: string;
    refresh_token: string;
    expires_in: number;
}

/**
 * A primary login method advertised by `/_action/admin-auth/methods` (feature flag ADMIN_AUTH).
 *
 * @private
 */
export interface AdminAuthMethod {
    id: string;
    type: 'password' | 'webauthn' | 'oidc';
    label: string | null;
    startUrl: string | null;
}

interface AdminAuthMethodsResponse {
    methods: AdminAuthMethod[];
    managedByConfig: boolean;
    adminUiEnabled: boolean;
}

/**
 * Result of a primary login (`admin_primary` grant). Either the login completed (`auth` is set) or
 * a second factor is required (`mfaRequired`) and the pending token is held inside the service.
 *
 * @private
 */
export interface PrimaryLoginResult {
    mfaRequired: boolean;
    methods: string[];
    auth: AuthObject | null;
}

interface WebAuthnLoginOptionsResponse {
    options: PublicKeyCredentialRequestOptionsJson;
    challengeToken: string;
}

/**
 * Scope identifier carried by a "MFA pending" access token issued between the first and second
 * factor (see `Shopware\Core\Framework\AdminAuth\OAuth\Scope\MfaPendingScope`).
 */
const MFA_PENDING_SCOPE = 'admin-mfa-pending';

/**
 * Marker scope prefix advertising the allowed second-factor methods, e.g.
 * `admin-mfa-methods:totp,webauthn` (see `AdminPrimaryGrant::METHODS_SCOPE_PREFIX`).
 */
const MFA_METHODS_SCOPE_PREFIX = 'admin-mfa-methods:';

/**
 * Decode the payload (middle segment) of a JWT without any dependency.
 * Returns an empty object when the token cannot be decoded.
 */
function decodeJwtPayload(token: string): Record<string, unknown> {
    if (typeof token !== 'string') {
        return {};
    }

    const parts = token.split('.');
    if (parts.length < 2) {
        return {};
    }

    try {
        let base64 = parts[1].replace(/-/g, '+').replace(/_/g, '/');
        while (base64.length % 4 !== 0) {
            base64 += '=';
        }

        const json = decodeURIComponent(
            window
                .atob(base64)
                .split('')
                .map((char) => `%${`00${char.charCodeAt(0).toString(16)}`.slice(-2)}`)
                .join(''),
        );

        return JSON.parse(json) as Record<string, unknown>;
    } catch {
        return {};
    }
}

/**
 * Read the scopes from a decoded JWT payload, tolerant to both `scopes` (array) and `scope`
 * (space/comma separated string).
 */
function extractScopes(payload: Record<string, unknown>): string[] {
    const scopes = payload.scopes ?? payload.scope;

    if (Array.isArray(scopes)) {
        return scopes.map((scope) => String(scope));
    }

    if (typeof scopes === 'string') {
        return scopes.split(/[ ,]+/).filter((scope) => scope.length > 0);
    }

    return [];
}

/**
 * Whether the access token is a powerless "MFA pending" token that must never be persisted.
 */
function isPendingToken(accessToken: string): boolean {
    return extractScopes(decodeJwtPayload(accessToken)).includes(MFA_PENDING_SCOPE);
}

/**
 * Extract the available second-factor method types from a pending token, e.g. `['totp']`.
 */
function extractMfaMethods(accessToken: string): string[] {
    const scopes = extractScopes(decodeJwtPayload(accessToken));
    const marker = scopes.find((scope) => scope.startsWith(MFA_METHODS_SCOPE_PREFIX));

    if (!marker) {
        return [];
    }

    return marker
        .slice(MFA_METHODS_SCOPE_PREFIX.length)
        .split(',')
        .map((method) => method.trim())
        .filter((method) => method.length > 0);
}

interface RetryBackoffOptions {
    maxRetries?: number;
    initialDelay?: number;
    factor?: number;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export interface LoginService {
    loginByUsername: (user: string, pass: string) => Promise<AuthObject>;
    getAvailableAuthMethods: () => Promise<AdminAuthMethod[]>;
    loginPrimary: (user: string, pass: string) => Promise<PrimaryLoginResult>;
    loginWithPasskey: () => Promise<PrimaryLoginResult>;
    verifySecondFactor: (method: 'totp' | 'recovery_codes', code: string) => Promise<AuthObject>;
    verifySecondFactorWebauthn: () => Promise<AuthObject>;
    clearPendingMfa: () => void;
    hasPendingMfa: () => boolean;
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
    subscribeToTokenRefresh: (successCallback: (token: string) => void, errorCallback: (error: Error) => void) => void;
    isRefreshing: () => Promise<boolean>;
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

    /**
     * Tracks an in-flight token refresh request so that concurrent calls
     * to the refresh logic can share the same promise and avoid duplicate
     * network requests.
     */
    let refreshPromise: Promise<string> | null = null;

    // Subscriber pattern for token refresh events
    const refreshSubscribers: Array<(token: string) => void> = [];
    const refreshErrorSubscribers: Array<(error: Error) => void> = [];

    /**
     * Pending MFA token issued by the `admin_primary` grant, kept IN MEMORY ONLY. It must never be
     * persisted (no cookie / storage) and never be handed out to callers.
     */
    let pendingMfaToken: string | null = null;

    return {
        loginByUsername,
        getAvailableAuthMethods,
        loginPrimary,
        loginWithPasskey,
        verifySecondFactor,
        verifySecondFactorWebauthn,
        clearPendingMfa,
        hasPendingMfa,
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
        subscribeToTokenRefresh,
        isRefreshing,
    };

    /**
     * Helper function to receive a logged in user token
     */
    function verifyUserToken(password: string): Promise<string> {
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
                    baseURL: context.apiPath!,
                },
            )
            .then((response) => applyFullToken(response.data));
    }

    /**
     * Persist a full token response exactly like a classic password login and signal a successful
     * login to the app.
     */
    function applyFullToken(data: TokenResponse): AuthObject {
        Shopware.Service('userActivityService').updateLastUserActivity();

        const auth = setBearerAuthentication({
            access: data.access_token,
            refresh: data.refresh_token,
            expiry: data.expires_in,
        });

        sessionStorage.setItem('redirectFromLogin', 'true');

        return auth;
    }

    /**
     * Lists the available primary login methods for the (unauthenticated) admin login screen.
     * Only meaningful when the ADMIN_AUTH feature is active.
     */
    function getAvailableAuthMethods(): Promise<AdminAuthMethod[]> {
        return httpClient
            .get<AdminAuthMethodsResponse>('/_action/admin-auth/methods', {
                baseURL: context.apiPath!,
            })
            .then((response) => (Array.isArray(response.data.methods) ? response.data.methods : []));
    }

    /**
     * Turn an `admin_primary` token response into a {@link PrimaryLoginResult}: a full token is
     * persisted like a classic login, a pending token is kept in memory only and the caller is
     * asked for a second factor.
     */
    function handlePrimaryTokenResponse(data: TokenResponse): PrimaryLoginResult {
        if (isPendingToken(data.access_token)) {
            // Keep the pending token in memory only - it must never reach the auth cookie.
            pendingMfaToken = data.access_token;

            return {
                mfaRequired: true,
                methods: extractMfaMethods(data.access_token),
                auth: null,
            };
        }

        return {
            mfaRequired: false,
            methods: [],
            auth: applyFullToken(data),
        };
    }

    /**
     * First login leg via the `admin_primary` grant with the classic username/password method.
     * Only meaningful when the ADMIN_AUTH feature is active.
     */
    function loginPrimary(user: string, pass: string): Promise<PrimaryLoginResult> {
        pendingMfaToken = null;

        return httpClient
            .post<TokenResponse>(
                '/oauth/token',
                {
                    grant_type: 'admin_primary',
                    client_id: 'administration',
                    scope: 'write',
                    method: 'password',
                    username: user,
                    password: pass,
                },
                {
                    baseURL: context.apiPath!,
                },
            )
            .then((response) => handlePrimaryTokenResponse(response.data));
    }

    /**
     * Fetch WebAuthn request options from the backend, run the assertion ceremony and return the
     * serialized assertion together with the signed challenge token that must be echoed back.
     */
    function fetchLoginAssertion(): Promise<{ assertion: string; challengeToken: string }> {
        return httpClient
            .post<WebAuthnLoginOptionsResponse>(
                '/_action/admin-auth/webauthn/login-options',
                {},
                {
                    baseURL: context.apiPath!,
                },
            )
            .then(async (response) => {
                const assertion = await getAssertion(response.data.options);

                return {
                    assertion: JSON.stringify(assertion),
                    challengeToken: response.data.challengeToken,
                };
            });
    }

    /**
     * Passwordless primary login with a passkey via the `admin_primary` grant (`webauthn` method).
     * Behaves exactly like {@link loginPrimary}: returns either the full auth object or the
     * pending-MFA descriptor so the login screen's MFA step can take over.
     */
    function loginWithPasskey(): Promise<PrimaryLoginResult> {
        pendingMfaToken = null;

        return fetchLoginAssertion()
            .then(({ assertion, challengeToken }) =>
                httpClient.post<TokenResponse>(
                    '/oauth/token',
                    {
                        grant_type: 'admin_primary',
                        client_id: 'administration',
                        scope: 'write',
                        method: 'webauthn',
                        assertion,
                        challengeToken,
                    },
                    {
                        baseURL: context.apiPath!,
                    },
                ),
            )
            .then((response) => handlePrimaryTokenResponse(response.data));
    }

    /**
     * Complete an MFA token response: persist the full token and drop the pending token.
     */
    function completeSecondFactor(data: TokenResponse): AuthObject {
        const auth = applyFullToken(data);
        pendingMfaToken = null;

        return auth;
    }

    /**
     * Second login leg: verify a TOTP or recovery code via the `admin_second_factor` grant with the
     * in-memory pending token as Bearer. On success the full token is persisted exactly like a
     * classic login.
     */
    function verifySecondFactor(method: 'totp' | 'recovery_codes', code: string): Promise<AuthObject> {
        if (!pendingMfaToken) {
            return Promise.reject(new Error('No pending MFA login available.'));
        }

        return httpClient
            .post<TokenResponse>(
                '/oauth/token',
                {
                    grant_type: 'admin_second_factor',
                    client_id: 'administration',
                    method,
                    code,
                },
                {
                    baseURL: context.apiPath!,
                    headers: {
                        Authorization: `Bearer ${pendingMfaToken}`,
                    },
                },
            )
            .then((response) => completeSecondFactor(response.data));
    }

    /**
     * Second login leg with a passkey: runs the WebAuthn assertion ceremony and verifies it via the
     * `admin_second_factor` grant with the in-memory pending token as Bearer.
     */
    function verifySecondFactorWebauthn(): Promise<AuthObject> {
        const pendingToken = pendingMfaToken;

        if (!pendingToken) {
            return Promise.reject(new Error('No pending MFA login available.'));
        }

        return fetchLoginAssertion()
            .then(({ assertion, challengeToken }) =>
                httpClient.post<TokenResponse>(
                    '/oauth/token',
                    {
                        grant_type: 'admin_second_factor',
                        client_id: 'administration',
                        method: 'webauthn',
                        assertion,
                        challengeToken,
                    },
                    {
                        baseURL: context.apiPath!,
                        headers: {
                            Authorization: `Bearer ${pendingToken}`,
                        },
                    },
                ),
            )
            .then((response) => completeSecondFactor(response.data));
    }

    /**
     * Drop the in-memory pending MFA token (e.g. when the user cancels the second-factor step).
     */
    function clearPendingMfa(): void {
        pendingMfaToken = null;
    }

    /**
     * Whether a second factor is currently pending.
     */
    function hasPendingMfa(): boolean {
        return pendingMfaToken !== null;
    }

    /**
     * Refreshes the access token with retry/backoff and cross-tab synchronization.
     *
     * Uses the Web Locks API to coordinate token refresh across browser tabs.
     * Only one tab at a time will perform the actual HTTP request; other tabs
     * wait for the lock and then re-check whether the token was already refreshed.
     */
    function refreshToken(): Promise<AuthObject['access']> {
        // Avoid parallel refresh requests within the same tab by reusing the in-flight promise.
        if (refreshPromise) {
            return refreshPromise;
        }

        const refreshTokenValue = getRefreshToken();
        if (!refreshTokenValue || !refreshTokenValue.length) {
            return Promise.reject(new Error('No refresh token found.'));
        }

        // Capture the current access token before requesting the lock,
        // so we can detect whether another tab refreshed it while we were waiting.
        const accessTokenBeforeLock = getToken();

        refreshPromise = synchronizedTokenRefresh(async () => {
            // Another tab may already have refreshed the token while we were waiting for the lock.
            const currentAccessToken = getToken();
            if (currentAccessToken && currentAccessToken !== accessTokenBeforeLock) {
                notifyRefreshSubscribers(currentAccessToken);
                return currentAccessToken;
            }

            return retryRefreshWithBackoff(refreshTokenValue);
        })
            .catch((error) => {
                throw error instanceof Error ? error : new Error(String(error));
            })
            .finally(() => {
                refreshPromise = null;
            });

        return refreshPromise;
    }

    /**
     * Executes refresh logic under a cross-tab lock when the Web Locks API is available.
     */
    async function synchronizedTokenRefresh<T>(fn: () => Promise<T>): Promise<T> {
        if (typeof navigator === 'undefined' || typeof navigator.locks?.request !== 'function') {
            return fn();
        }

        const result = await (navigator.locks.request('sw-admin-token-refresh', fn) as Promise<T>);

        return result;
    }

    /**
     * Performs the token refresh HTTP request with exponential backoff retry logic.
     *
     * On success: updates authentication and notifies subscribers.
     * On failure after all retries: triggers an inactivity logout and notifies error subscribers.
     *
     * @private
     */
    function retryRefreshWithBackoff(token: string): Promise<string> {
        return retryPromiseWithBackoff(
            () => {
                return httpClient.post<TokenResponse>(
                    '/oauth/token',
                    {
                        grant_type: 'refresh_token',
                        client_id: 'administration',
                        scope: 'write',
                        refresh_token: token,
                    },
                    {
                        baseURL: context.apiPath!,
                    },
                );
            },
            {
                maxRetries: 2,
                initialDelay: 500,
                factor: 2,
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

                notifyRefreshSubscribers(newToken);

                return newToken;
            })
            .catch((error) => {
                logout(true);

                // Notify all error subscribers
                const errorObj = error instanceof Error ? error : new Error(String(error));
                refreshErrorSubscribers.forEach((callback) => {
                    callback(errorObj);
                });
                refreshSubscribers.length = 0;
                refreshErrorSubscribers.length = 0;

                return Promise.reject(errorObj);
            });
    }

    /**
     * Retries a promise-returning function with exponential backoff.
     *
     * @param fn - Function that returns the promise to execute
     * @param options - Retry and backoff configuration
     */
    function retryPromiseWithBackoff<T>(fn: () => Promise<T>, options: RetryBackoffOptions = {}): Promise<T> {
        const { maxRetries = 3, initialDelay = 1000, factor = 2 } = options;

        return new Promise<T>((resolve, reject) => {
            let attempt = 0;

            const execute = (): void => {
                Promise.resolve()
                    .then(fn)
                    .then(resolve)
                    .catch((error) => {
                        if (attempt >= maxRetries) {
                            const errorObj = error instanceof Error ? error : new Error(String(error));
                            reject(errorObj);
                            return;
                        }

                        const delay = initialDelay * factor ** attempt;
                        attempt += 1;

                        setTimeout(execute, delay);
                    });
            };

            execute();
        });
    }

    /**
     * Notifies all refresh subscribers with the latest token and clears all refresh subscriber queues.
     *
     * @param token - The refreshed access token
     */
    function notifyRefreshSubscribers(token: string): void {
        refreshSubscribers.forEach((callback) => {
            callback(token);
        });
        refreshSubscribers.length = 0;
        refreshErrorSubscribers.length = 0;
    }

    /**
     * Subscribe to token refresh events. Callbacks will be called when token refresh succeeds or fails.
     *
     * @param successCallback - Called with the new token when refresh succeeds
     * @param errorCallback - Called with the error when refresh fails
     */
    function subscribeToTokenRefresh(successCallback: (token: string) => void, errorCallback: (error: Error) => void): void {
        refreshSubscribers.push(successCallback);
        refreshErrorSubscribers.push(errorCallback);
    }

    /**
     * Returns whether a token refresh is currently in progress.
     *
     * Checks both this tab's in-flight refresh promise and, where supported,
     * the shared Web Lock used for cross-tab refresh synchronization.
     */
    async function isRefreshing(): Promise<boolean> {
        if (refreshPromise !== null) {
            return true;
        }

        if (typeof navigator === 'undefined' || typeof navigator.locks?.query !== 'function') {
            return false;
        }

        try {
            const lockState = await navigator.locks.query();
            const heldLocks = lockState.held ?? [];
            const pendingLocks = lockState.pending ?? [];

            return (
                heldLocks.some((lock) => lock.name === 'sw-admin-token-refresh') ||
                pendingLocks.some((lock) => lock.name === 'sw-admin-token-refresh')
            );
        } catch {
            return false;
        }
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
     * Clears local authentication state: cookies, context token, bearer cache,
     * remember-me flag, and auto-refresh timer.
     */
    function clearAuthState(): void {
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
    }

    /**
     * Clears the cookie stored bearer authentication object.
     */
    function logout(isInactivityLogout = false, shouldRedirect = true): boolean {
        clearAuthState();
        forwardLogout(isInactivityLogout, shouldRedirect);

        return true;
    }

    /**
     * @private
     */
    function forwardLogout(isInactivityLogout: boolean, shouldRedirect: boolean): void {
        notifyOnLogoutListener();

        // @ts-expect-error
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
                        } catch (_e) {
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
}
