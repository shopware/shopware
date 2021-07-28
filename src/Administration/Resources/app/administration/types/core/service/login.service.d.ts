import { AxiosInstance, AxiosPromise } from 'axios';

export interface Token {
    access: string;
    refresh: string;
    expiry: string;
}

export interface Context {
    authToken?: string;
    basePath: string;
    pathInfo: string;
    apiPath: string;
    [other: string]: any;
}
export function createLoginService(
    httpClient: AxiosInstance,
    context: Context,
    bearerAuth?: string
): LoginService;

export interface LoginService {
    loginByUsername(user: string, pass: string): AxiosPromise;

    verifyUserByUsername(user: string, pass: string): AxiosPromise;

    refreshToken(): AxiosPromise;

    getToken(): boolean | string | number;

    getBearerAuthentication(section: string | null): boolean | string | number;

    setBearerAuthentication(token: Token): Token;

    logout(): boolean;

    isLoggedIn(): boolean;

    addOnTokenChangedListener(listener: (token: Token) => void): void;

    addOnLogoutListener(listener: (token: Token) => void): void;

    addOnLoginListener(listener: (token: Token) => void): void;

    getStorageKey(): string;

    notifyOnLoginListener(): null | void;

    verifyUserToken(password: string): AxiosPromise;
}
