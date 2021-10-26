import { CookieStorage } from 'cookie-storage';

interface AuthObject {
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

/**
 * @module core/service/login
 */
export class LoginService {
    private httpClient;

    private context;

    private bearerAuth: AuthObject | null;

    private storageKey = 'bearerAuth';

    private onTokenChangedListener: ((auth: AuthObject) => void)[] = [];

    private onLogoutListener: (() => void)[] = [];

    private onLoginListener: (() => void)[] = [];

    private cookieStorage;

    constructor(
        httpClient: InitContainer['httpClient'],
        context: VuexRootState['context']['api'],
        bearerAuth: AuthObject | null = null,
    ) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        this.httpClient = httpClient;
        this.context = context;
        this.bearerAuth = bearerAuth;
        this.cookieStorage = this.cookieStorageFactory();
    }

    /**
     * Helper function to receive a logged in user token
     */
    verifyUserToken(password: string): Promise<string> {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
        return this.verifyUserByUsername(Shopware.State.get('session').currentUser.username as string, password)
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
    loginByUsername(user: string, pass: string): Promise<AuthObject> {
        return this.httpClient.post<TokenResponse>('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scopes: 'write',
            username: user,
            password: pass,
        }, {
            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            baseURL: this.context.apiPath!,
        }).then((response) => {
            const auth = this.setBearerAuthentication({
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
    refreshToken(): Promise<AuthObject['access']> {
        const token = this.getRefreshToken();

        if (!token || !token.length) {
            return Promise.reject(new Error('No refresh token found.'));
        }

        return this.httpClient.post<TokenResponse>('/oauth/token', {
            grant_type: 'refresh_token',
            client_id: 'administration',
            scopes: 'write',
            refresh_token: token,
        }, {
            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            baseURL: this.context.apiPath!,
        }).then((response) => {
            this.setBearerAuthentication({
                access: response.data.access_token,
                expiry: response.data.expires_in,
                refresh: response.data.refresh_token,
            });

            return response.data.access_token;
        });
    }

    verifyUserByUsername(user: string, pass: string): Promise<AuthObject> {
        return this.httpClient.post<TokenResponse>('/oauth/token', {
            grant_type: 'password',
            client_id: 'administration',
            scope: 'user-verified',
            username: user,
            password: pass,
        }, {
            // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
            baseURL: this.context.apiPath!,
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
    addOnTokenChangedListener(listener: () => void): void {
        this.onTokenChangedListener.push(listener);
    }

    /**
     * Adds an Listener for the onLogoutEvent
     */
    addOnLogoutListener(listener: () => void): void {
        this.onLogoutListener.push(listener);
    }

    /**
     * Adds an Listener for the onLoginEvent
     */
    addOnLoginListener(listener: () => void): void {
        this.onLoginListener.push(listener);
    }

    /**
     * notifies the listener for the onTokenChangedEvent
     */
    notifyOnTokenChangedListener(auth: AuthObject): void {
        this.onTokenChangedListener.forEach((callback) => {
            callback.call(null, auth);
        });
    }

    /**
     * notifies the listener for the onLogoutEvent
     */
    notifyOnLogoutListener():void {
        this.onLogoutListener.forEach((callback) => {
            callback.call(null);
        });
    }


    /**
     * notifies the listener for the onLoginEvent
     */
    notifyOnLoginListener(): void[] | null {
        if (!window.localStorage.getItem('redirectFromLogin')) {
            return null;
        }

        window.localStorage.removeItem('redirectFromLogin');

        return this.onLoginListener.map((callback) => {
            return callback.call(null);
        });
    }

    /**
     * Saves the bearer authentication object in the cookies using the {@link storageKey} as the
     * object identifier.
     */
    setBearerAuthentication({ access, refresh, expiry }: AuthObject): AuthObject {
        expiry = Math.round(+new Date() / 1000) + expiry;
        const authObject = { access, refresh, expiry };
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            this.cookieStorage.setItem(this.storageKey, JSON.stringify(authObject));
        } else {
            this.bearerAuth = authObject;
        }
        this.notifyOnTokenChangedListener(authObject);

        this.context.authToken = authObject;

        return authObject;
    }

    /**
     * Returns saved bearer authentication object. Either you're getting the full object or when you're specifying
     * the `section` argument and getting either the token or the expiry date.
     */
    getBearerAuthentication<K extends keyof AuthObject>(section: K): AuthObject[K]

    getBearerAuthentication<K extends keyof AuthObject>(section: K | null = null): false | AuthObject | AuthObject[K] {
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            try {
                this.bearerAuth = JSON.parse(this.cookieStorage.getItem(this.storageKey) as string) as AuthObject;
            } catch {
                this.bearerAuth = null;
            }
        }

        this.context.authToken = this.bearerAuth;

        if (!this.bearerAuth) {
            return false;
        }

        if (!section) {
            return this.bearerAuth;
        }

        return (this.bearerAuth[section] ? this.bearerAuth[section] : false);
    }

    /**
     * Clears the cookie stored bearer authentication object.
     */
    logout(): boolean {
        if (typeof document !== 'undefined' && typeof document.cookie !== 'undefined') {
            this.cookieStorage.removeItem(this.storageKey);

            // @deprecated tag:v6.5.0 - Was needed for old cookies set without domain
            // eslint-disable-next-line max-len,@typescript-eslint/no-non-null-assertion
            document.cookie = `bearerAuth=deleted; expires=Thu, 18 Dec 2013 12:00:00 UTC;path=${this.context.basePath! + this.context.pathInfo!}`;
        }

        this.context.authToken = null;
        this.bearerAuth = null;

        this.notifyOnLogoutListener();

        return true;
    }

    /**
     * Returns the bearer token
     */
    getToken(): string {
        return this.getBearerAuthentication('access');
    }

    /**
     * Returns the refresh token
     */
    getRefreshToken(): string {
        return this.getBearerAuthentication('refresh');
    }

    /**
     * Checks if the user is logged in by checking if the bearer token exists
     * in the cookies.
     *
     * A check for expiration is not possible because the refresh token is longer
     * valid then the normal token.
     */
    isLoggedIn(): boolean {
        return !!this.getToken();
    }

    /**
     * Returns the storage key.
     */
    getStorageKey(): string {
        return this.storageKey;
    }

    /**
     * Returns a CookieStorage instance with the right domain and path from the context.
     */
    cookieStorageFactory(): CookieStorage {
        let domain;

        if (typeof window === 'object') {
            domain = window.location.hostname;
        } else {
            // eslint-disable-next-line no-restricted-globals
            const url = new URL(self.location.origin);
            domain = url.hostname;
        }

        // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
        const path = this.context.basePath! + this.context.pathInfo!;

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

export default function createLoginService(
    httpClient: InitContainer['httpClient'],
    context: VuexRootState['context']['api'],
    bearerAuth: AuthObject | null = null,
): LoginService {
    return new LoginService(httpClient, context, bearerAuth);
}
