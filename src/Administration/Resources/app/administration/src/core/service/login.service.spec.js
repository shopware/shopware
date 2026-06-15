/* eslint-disable sw-test-rules/test-file-max-lines-warning, sw-test-rules/test-file-max-lines-error */

/**
 * @sw-package framework
 */

import LoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import { CookieStorage } from 'cookie-storage';

const getClientMock = () => {
    const client = createHTTPClient();
    const clientMock = new MockAdapter(client);

    return { client, clientMock };
};

const loginServiceFactory = () => {
    const { client, clientMock } = getClientMock();
    const contextMock = {};

    return {
        loginService: new LoginService(client, contextMock),
        contextMock: contextMock,
        clientMock: clientMock,
    };
};

let cookieStorageMock = '';
let lastUserActivity = null;

describe('core/service/login.service.js', () => {
    beforeAll(async () => {
        // JSDOM does not provide navigator.locks — mock it so that
        // the Web Locks based refresh logic works in unit tests.
        if (!navigator.locks) {
            Object.defineProperty(navigator, 'locks', {
                value: {
                    request: jest.fn((_name, callback) => callback()),
                },
                configurable: true,
            });
        }

        Object.defineProperty(document, 'cookie', {
            set: function (value) {
                cookieStorageMock = `${cookieStorageMock}${value};`;
            },
            get: function () {
                return cookieStorageMock;
            },
        });

        const mockDate = new Date(1577881800000);
        Date.now = jest.fn(() => +mockDate);

        Shopware.Service().register('userActivityService', () => {
            return {
                getLastUserActivity: () => {
                    return lastUserActivity ?? new Date();
                },
                updateLastUserActivity: () => {
                    lastUserActivity = new Date();
                },
            };
        });
    });

    beforeEach(() => {
        cookieStorageMock = '';
        lastUserActivity = null;
        Shopware.Application.view.router = undefined;
    });

    afterEach(() => {
        localStorage.removeItem('rememberMe');
        sessionStorage.removeItem('redirectFromLogin');
    });

    it('should contain all public functions', async () => {
        const { loginService } = loginServiceFactory();

        expect(loginService).toHaveProperty('loginByUsername');
        expect(loginService).toHaveProperty('refreshToken');
        expect(loginService).toHaveProperty('getToken');
        expect(loginService).toHaveProperty('getBearerAuthentication');
        expect(loginService).toHaveProperty('setBearerAuthentication');
        expect(loginService).toHaveProperty('restartAutoTokenRefresh');
        expect(loginService).toHaveProperty('logout');
        expect(loginService).toHaveProperty('logoutSso');
        expect(loginService).toHaveProperty('isLoggedIn');
        expect(loginService).toHaveProperty('addOnTokenChangedListener');
        expect(loginService).toHaveProperty('addOnLogoutListener');
        expect(loginService).toHaveProperty('addOnLoginListener');
        expect(loginService).toHaveProperty('notifyOnLoginListener');
    });

    it('should set the bearer authentication with the right expiry', async () => {
        const { loginService } = loginServiceFactory();

        const auth = loginService.setBearerAuthentication({
            expiry: 300,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN',
        });

        expect(auth).toEqual({
            expiry: Date.now() + 300 * 1000,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN',
        });
    });

    it('should set the bearer authentication with the right cookie expiry', async () => {
        const { loginService } = loginServiceFactory();

        loginService.setBearerAuthentication({
            expiry: 300,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN',
        });

        const expiresPart = cookieStorageMock.match(/expires=([^;]+)/);
        expect(expiresPart).not.toBeNull();
        expect(expiresPart).toHaveLength(2);
        const date = new Date(expiresPart[1]);
        expect(date.getTime()).toBe(Date.now() + 300 * 1000);
    });

    it('should set the bearer authentication with the right cookie expiry (remember me - default value)', async () => {
        const { loginService } = loginServiceFactory();

        loginService.setRememberMe(true);

        loginService.setBearerAuthentication({
            expiry: 300,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN',
        });

        expect(localStorage.getItem('rememberMe')).toBe('true');

        const expiresPart = cookieStorageMock.match(/expires=([^;]+)/);
        expect(expiresPart).not.toBeNull();
        expect(expiresPart).toHaveLength(2);
        const date = new Date(expiresPart[1]);
        expect(date.getTime()).toBe(Date.now() + 7 * 86400 * 1000);
    });

    it('should set the bearer authentication with the right cookie expiry (remember me - refreshTokenTtl)', async () => {
        const { loginService, contextMock } = loginServiceFactory();

        loginService.setRememberMe(true);

        const refreshTokenTtl = 14 * 86400 * 1000;
        contextMock.refreshTokenTtl = refreshTokenTtl;

        loginService.setBearerAuthentication({
            expiry: 300,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN',
        });

        expect(localStorage.getItem('rememberMe')).toBe('true');

        const expiresPart = cookieStorageMock.match(/expires=([^;]+)/);
        expect(expiresPart).not.toBeNull();
        expect(expiresPart).toHaveLength(2);
        const date = new Date(expiresPart[1]);
        expect(date.getTime()).toBe(Date.now() + refreshTokenTtl);
    });

    it('should set the remember me value', async () => {
        const { loginService } = loginServiceFactory();

        loginService.setRememberMe(true);
        expect(localStorage.getItem('rememberMe')).toBe('true');

        loginService.setRememberMe(false);
        expect(localStorage.getItem('rememberMe')).toBeNull();
    });

    it('should login and return the bearer token', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        const auth = await loginService.loginByUsername('admin', 'shopware');

        expect(auth).toEqual({
            expiry: Date.now() + 600 * 1000,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN',
        });
    });

    it('should clear the cookie successfully after each test', async () => {
        const { loginService } = loginServiceFactory();

        const auth = loginService.getBearerAuthentication();

        expect(auth).toBeFalsy();
    });

    it('should get a new token', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        await loginService.loginByUsername('admin', 'shopware');

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn_TwO',
            refresh_token: 'rEfReSh_ToKeN_tWo',
        });

        const refreshToken = await loginService.refreshToken();
        expect(refreshToken).toBe('aCcEsS_tOkEn_TwO');
    });

    it('should refresh the actual bearer auth', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        await loginService.loginByUsername('admin', 'shopware');

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 400,
            access_token: 'aCcEsS_tOkEn_TwO',
            refresh_token: 'rEfReSh_ToKeN_tWo',
        });

        await loginService.refreshToken();

        const bearerAuthentication = loginService.getBearerAuthentication();
        expect(bearerAuthentication).toEqual({
            access: 'aCcEsS_tOkEn_TwO',
            expiry: Date.now() + 400 * 1000,
            refresh: 'rEfReSh_ToKeN_tWo',
        });
    });

    it('should login and logout successfully', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        await loginService.loginByUsername('admin', 'shopware');

        const authLoggedIn = loginService.getBearerAuthentication();
        expect(authLoggedIn).toEqual({
            expiry: Date.now() + 600 * 1000,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN',
        });

        loginService.logout();

        const newAuth = loginService.getBearerAuthentication();
        expect(newAuth).toBeFalsy();
    });

    it('should return the storage key', async () => {
        const { loginService } = loginServiceFactory();

        expect(loginService.getStorageKey()).toBe('bearerAuth');
    });

    it('should check if user is logged in', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        expect(loginService.isLoggedIn()).toBeFalsy();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        await loginService.loginByUsername('admin', 'shopware');

        expect(loginService.isLoggedIn()).toBeTruthy();
    });

    it('should return only the token', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        await loginService.loginByUsername('admin', 'shopware');

        expect(loginService.getToken()).toBe('aCcEsS_tOkEn');
    });

    it('should return false when token is unparsable', async () => {
        const { loginService } = loginServiceFactory();

        document.cookie = 'bearerAuth=%7B%22acce{{"ss%%3A1577882400%7D';

        expect(loginService.getBearerAuthentication()).toBeFalsy();
    });

    it('should call the listener', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        const logoutListener = jest.fn();
        const tokenChangedListener = jest.fn();

        loginService.addOnLogoutListener(logoutListener);
        loginService.addOnTokenChangedListener(tokenChangedListener);

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        expect(tokenChangedListener).not.toHaveBeenCalled();

        await loginService.loginByUsername('admin', 'shopware');

        expect(tokenChangedListener).toHaveBeenCalled();

        expect(logoutListener).not.toHaveBeenCalled();
        loginService.logout();
        expect(logoutListener).toHaveBeenCalled();
    });

    it('should not call the login listener when you not redirecting from the login', async () => {
        const { loginService } = loginServiceFactory();

        const loginListener = jest.fn();

        loginService.addOnLoginListener(loginListener);
        expect(loginListener).not.toHaveBeenCalled();

        loginService.notifyOnLoginListener();
        expect(loginListener).not.toHaveBeenCalled();
    });

    it('should call the login listener when redirect from the login', async () => {
        const { loginService } = loginServiceFactory();
        sessionStorage.setItem('redirectFromLogin', true);

        const loginListener = jest.fn();

        loginService.addOnLoginListener(loginListener);
        expect(loginListener).not.toHaveBeenCalled();

        loginService.notifyOnLoginListener();
        expect(loginListener).toHaveBeenCalled();
    });

    it('should reject when no refresh token was found', async () => {
        const { loginService } = loginServiceFactory();

        await expect(loginService.refreshToken()).rejects.toThrow();
    });

    it('should be logged in when token exists and there is a valid last activity', async () => {
        document.cookie =
            'bearerAuth=%7B%22access%22%3A%22eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImU5Njk3NjdmMWQ0M2FhMzBiOGRjNDU3NDU0YWNjZWU4YjM3MzRjYTMyZDVlZDcwYTU4Yjg3ZWZjMWRkYzI5MjFhYTE1NzBjOWI4Zjk0NjZkIn0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6ImU5Njk3NjdmMWQ0M2FhMzBiOGRjNDU3NDU0YWNjZWU4YjM3MzRjYTMyZDVlZDcwYTU4Yjg3ZWZjMWRkYzI5MjFhYTE1NzBjOWI4Zjk0NjZkIiwiaWF0IjoxNjA2Mjk0MTM2LCJuYmYiOjE2MDYyOTQxMzYsImV4cCI6MTYwNjI5NDczNiwic3ViIjoiZTAzOWY0YzMyZjllNGMxZjgyMDNlMzVmZjdmZDQ1NzUiLCJzY29wZXMiOlsid3JpdGUiLCJhZG1pbiJdfQ.KNMWZqRJXM-lamNSuNvCsyZkR0zYkvS72DxjbJDAKqQex-PNUsDBDll9E4B7W5dLmIurTbxbzB4c8ztfPVkdXcZg5EORIIU8JRTjpbtwKhnXohEODsNqFPYGjFfhJnwcpt8tXvJ1BFXQdGR0UcHqPe-qLqWP9U1CZRht3A-9EvQFfzyqV9RJTs83tZ5MQI1LowjKIx1C6yxQ4CaQ-d-YUkerDguCukCg_z_Qkf2ME5tfdiiVp_uKCqknXNrNzs5y6LX0xnrLXBOGrcC3ZNF7RxmWxM-MzLaDa6kcYxc-k-QP3I89qDitZVU7LYTvK4WW_eH4qfOyVEzqSJuwtsoShA%22%2C%22refresh%22%3A%22def502006b139951ad0e625d58b94953b05b68ab5cd05abbc68b375ba21abf3e155a162020fd3175f2b057dc095c7ee53ac6686df506baba3053521be09354faa0142aee26a1548edf3f11fb724b1f0c60d044bc66c1c1304f59501a2f1b60378a5200e9254fcbde8c25fc9f745f31aacdaebbc77b3611226d22ee68128f28182a419ab2b04bfba9f240c4d743263dd8e798afccc7c0c2d2cc1c2df6ac6c097d17d9f991a408b5b6534a4a71fad3f7348139fa5b95b483fd2d3e206047fda7c60e099723dab5ff5197113faccd23a3aba8d8c948fd7e4d8da59dc74f9c160fd1de812900f51b5d06bd61dae754b87dc18efec9acdc82447042189871e69db6cbaaed1d82aef3cc8958c553cd5c75c98f0d174887c6a71a3f60aae584e2711198d3af88177f43bb630c6ee4e2453b11a6783953e1e6ef84ba2085f1414a4bf0638e65a047f1fb1b0b0dd59f4df68ef245d465c38dae2a7c887db636832b060c78e40b11667641653e5e4ec7a0eaacb1fdb1eef80e699d695183be585f4f3db16022e33f36ad300282487fcc17eee807085d079cdd2f129b30c5d5aea861d0%22%2C%22expiry%22%3A1606294737%7D';
        const { loginService } = loginServiceFactory();

        loginService.getStorage().setItem('lastActivity', `${Math.round(Date.now() / 1000)}`);

        await expect(loginService.isLoggedIn()).toBe(true);
    });

    it('should be logged out when token exists, but the last activity is too old', async () => {
        document.cookie =
            'bearerAuth=%7B%22access%22%3A%22eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6ImU5Njk3NjdmMWQ0M2FhMzBiOGRjNDU3NDU0YWNjZWU4YjM3MzRjYTMyZDVlZDcwYTU4Yjg3ZWZjMWRkYzI5MjFhYTE1NzBjOWI4Zjk0NjZkIn0.eyJhdWQiOiJhZG1pbmlzdHJhdGlvbiIsImp0aSI6ImU5Njk3NjdmMWQ0M2FhMzBiOGRjNDU3NDU0YWNjZWU4YjM3MzRjYTMyZDVlZDcwYTU4Yjg3ZWZjMWRkYzI5MjFhYTE1NzBjOWI4Zjk0NjZkIiwiaWF0IjoxNjA2Mjk0MTM2LCJuYmYiOjE2MDYyOTQxMzYsImV4cCI6MTYwNjI5NDczNiwic3ViIjoiZTAzOWY0YzMyZjllNGMxZjgyMDNlMzVmZjdmZDQ1NzUiLCJzY29wZXMiOlsid3JpdGUiLCJhZG1pbiJdfQ.KNMWZqRJXM-lamNSuNvCsyZkR0zYkvS72DxjbJDAKqQex-PNUsDBDll9E4B7W5dLmIurTbxbzB4c8ztfPVkdXcZg5EORIIU8JRTjpbtwKhnXohEODsNqFPYGjFfhJnwcpt8tXvJ1BFXQdGR0UcHqPe-qLqWP9U1CZRht3A-9EvQFfzyqV9RJTs83tZ5MQI1LowjKIx1C6yxQ4CaQ-d-YUkerDguCukCg_z_Qkf2ME5tfdiiVp_uKCqknXNrNzs5y6LX0xnrLXBOGrcC3ZNF7RxmWxM-MzLaDa6kcYxc-k-QP3I89qDitZVU7LYTvK4WW_eH4qfOyVEzqSJuwtsoShA%22%2C%22refresh%22%3A%22def502006b139951ad0e625d58b94953b05b68ab5cd05abbc68b375ba21abf3e155a162020fd3175f2b057dc095c7ee53ac6686df506baba3053521be09354faa0142aee26a1548edf3f11fb724b1f0c60d044bc66c1c1304f59501a2f1b60378a5200e9254fcbde8c25fc9f745f31aacdaebbc77b3611226d22ee68128f28182a419ab2b04bfba9f240c4d743263dd8e798afccc7c0c2d2cc1c2df6ac6c097d17d9f991a408b5b6534a4a71fad3f7348139fa5b95b483fd2d3e206047fda7c60e099723dab5ff5197113faccd23a3aba8d8c948fd7e4d8da59dc74f9c160fd1de812900f51b5d06bd61dae754b87dc18efec9acdc82447042189871e69db6cbaaed1d82aef3cc8958c553cd5c75c98f0d174887c6a71a3f60aae584e2711198d3af88177f43bb630c6ee4e2453b11a6783953e1e6ef84ba2085f1414a4bf0638e65a047f1fb1b0b0dd59f4df68ef245d465c38dae2a7c887db636832b060c78e40b11667641653e5e4ec7a0eaacb1fdb1eef80e699d695183be585f4f3db16022e33f36ad300282487fcc17eee807085d079cdd2f129b30c5d5aea861d0%22%2C%22expiry%22%3A1606294737%7D';
        const { loginService } = loginServiceFactory();

        // 01.07.1982
        lastUserActivity = new Date(394356370);

        await expect(loginService.isLoggedIn()).toBe(false);
    });

    it('should not be logged in when token does not exist', async () => {
        document.cookie = '';
        const { loginService } = loginServiceFactory();

        await expect(loginService.isLoggedIn()).toBe(false);
    });

    it('should start auto refresh the token after login', async () => {
        jest.useFakeTimers();

        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn_first',
            refresh_token: 'rEfReSh_ToKeN_first',
        });

        await loginService.loginByUsername('admin', 'shopware');

        expect(clientMock.history.post[0]).toBeDefined();
        expect(clientMock.history.post[1]).toBeUndefined();
        expect(JSON.parse(clientMock.history.post[0].data).grant_type).toBe('password');

        await jest.runAllTimers();

        expect(clientMock.history.post[1]).toBeDefined();
        expect(JSON.parse(clientMock.history.post[1].data).grant_type).toBe('refresh_token');
    });

    it('should start auto refresh the token after token refresh', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        await loginService.loginByUsername('admin', 'shopware');

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 400,
            access_token: 'aCcEsS_tOkEn_TwO',
            refresh_token: 'rEfReSh_ToKeN_tWo',
        });

        await loginService.refreshToken();

        expect(clientMock.history.post[1]).toBeDefined();
        expect(JSON.parse(clientMock.history.post[1].data).grant_type).toBe('refresh_token');
    });

    it('should return CookieStorage', async () => {
        const { loginService } = loginServiceFactory();

        expect(typeof loginService.getStorage).toBe('function');

        const storage = loginService.getStorage();
        expect(storage instanceof CookieStorage).toBe(true);
    });

    it('should logout inactive user', async () => {
        // Current time in Seconds - 1501 to be one 1-second over the threshold
        cookieStorageMock = Math.round(+new Date() / 1000) - 1501;

        const { loginService, clientMock } = loginServiceFactory();
        const logoutListener = jest.fn();
        loginService.addOnLogoutListener(logoutListener);

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn_first',
            refresh_token: 'rEfReSh_ToKeN_first',
        });

        await loginService.loginByUsername('admin', 'shopware');

        expect(clientMock.history.post[0]).toBeDefined();
        expect(clientMock.history.post[1]).toBeUndefined();
        expect(JSON.parse(clientMock.history.post[0].data).grant_type).toBe('password');

        expect(clientMock.history.post[1]).toBeUndefined();
    });

    it('should set logout refresh storage key correctly', async () => {
        // Mock Router
        Shopware.Application.view.router = {
            currentRoute: {
                value: {
                    fullPath: '/sw/dashboard/index',
                },
            },
            push: jest.fn(),
        };
        const { loginService } = loginServiceFactory();

        // Check if refresh-after-logout storage key is not set before logout
        expect(sessionStorage.getItem('refresh-after-logout')).toBeNull();

        loginService.logout();

        // Check if refresh-after-logout storage key is set after logout
        expect(sessionStorage.getItem('refresh-after-logout')).toBe('true');

        sessionStorage.removeItem('refresh-after-logout');
    });

    it('should logout inactive user when user activity is over the threshold', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token').reply(200, {
            token_type: 'Bearer',
            expires_in: 600,
            access_token: 'aCcEsS_tOkEn',
            refresh_token: 'rEfReSh_ToKeN',
        });

        await loginService.loginByUsername('admin', 'shopware');

        lastUserActivity = new Date(Date.now() - 30 * 60 * 1000 - 1);

        const logoutListener = jest.fn();
        loginService.addOnLogoutListener(logoutListener);

        loginService.isLoggedIn();

        expect(logoutListener).toHaveBeenCalled();
    });

    describe('token refresh behavior', () => {
        it('should refresh token without Web Locks API support', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            const originalLocks = navigator.locks;
            try {
                Object.defineProperty(navigator, 'locks', {
                    value: undefined,
                    configurable: true,
                });

                clientMock.onPost('/oauth/token').replyOnce(200, {
                    token_type: 'Bearer',
                    expires_in: 600,
                    access_token: 'aCcEsS_tOkEn',
                    refresh_token: 'rEfReSh_ToKeN',
                });

                await loginService.loginByUsername('admin', 'shopware');

                clientMock.onPost('/oauth/token').replyOnce(200, {
                    token_type: 'Bearer',
                    expires_in: 600,
                    access_token: 'fallback_token',
                    refresh_token: 'fallback_refresh',
                });

                await expect(loginService.refreshToken()).resolves.toBe('fallback_token');
            } finally {
                Object.defineProperty(navigator, 'locks', {
                    value: originalLocks,
                    configurable: true,
                });
            }
        });

        it('should clear token when refresh fails after all retries', async () => {
            jest.useFakeTimers();

            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');

            clientMock.onPost('/oauth/token').reply(400, {
                error: 'invalid_grant',
            });

            const refreshPromise = loginService.refreshToken();
            const advanceTimePromise = jest.advanceTimersByTimeAsync(2000);

            // two retries (500ms + 1000ms) + buffer for async scheduling
            await expect(refreshPromise).rejects.toThrow();
            await advanceTimePromise;

            expect(loginService.getToken()).toBe(false);
            expect(loginService.isLoggedIn()).toBe(false);

            jest.useRealTimers();
        });

        it('should retry with interval when refresh fails once', async () => {
            jest.useFakeTimers();

            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');
            clientMock.resetHistory();

            clientMock.onPost('/oauth/token').replyOnce(400, { error: 'invalid_grant' });
            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'new_token',
                refresh_token: 'new_refresh',
            });

            const refreshPromise = loginService.refreshToken();
            const advanceTimePromise = jest.advanceTimersByTimeAsync(1000);

            await expect(refreshPromise).resolves.toBe('new_token');
            await advanceTimePromise;

            expect(clientMock.history.post).toHaveLength(2);
            expect(loginService.getToken()).toBe('new_token');

            jest.useRealTimers();
        });

        it('should logout after max refresh retries are reached', async () => {
            jest.useFakeTimers();

            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').reply((config) => {
                const payload = JSON.parse(config.data);

                if (payload.grant_type === 'password') {
                    return [
                        200,
                        {
                            token_type: 'Bearer',
                            expires_in: 600,
                            access_token: 'aCcEsS_tOkEn',
                            refresh_token: 'rEfReSh_ToKeN',
                        },
                    ];
                }

                return [
                    400,
                    { error: 'invalid_grant' },
                ];
            });

            await loginService.loginByUsername('admin', 'shopware');

            const refreshPromise = loginService.refreshToken();
            const advanceTimePromise = jest.advanceTimersByTimeAsync(2000);

            // two retries (500ms + 1000ms) + buffer for async scheduling
            await expect(refreshPromise).rejects.toThrow();
            await advanceTimePromise;

            expect(loginService.isLoggedIn()).toBe(false);

            jest.useRealTimers();
        });

        it('should handle concurrent refresh calls in the same tab with singleton promise', async () => {
            jest.useFakeTimers();

            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'shared_token',
                refresh_token: 'shared_refresh',
            });

            await loginService.loginByUsername('admin', 'shopware');

            clientMock.reset();

            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'tab1_new_token',
                refresh_token: 'tab1_new_refresh',
            });

            const firstRefreshCallPromise = loginService.refreshToken();
            const secondRefreshCallPromise = loginService.refreshToken();

            await expect(firstRefreshCallPromise).resolves.toBe('tab1_new_token');
            await expect(secondRefreshCallPromise).resolves.toBe('tab1_new_token');

            const refreshRequests = clientMock.history.post.filter(
                (req) => JSON.parse(req.data).grant_type === 'refresh_token',
            );
            expect(refreshRequests).toHaveLength(1);

            expect(loginService.isLoggedIn()).toBe(true);
            expect(loginService.getToken()).toBe('tab1_new_token');

            jest.useRealTimers();
        });

        it('should notify token changed listeners when the token gets updated', () => {
            const { loginService } = loginServiceFactory();

            const tokenChangedListener = jest.fn();
            loginService.addOnTokenChangedListener(tokenChangedListener);

            loginService.setBearerAuthentication({
                access: 'initial_token',
                refresh: 'initial_refresh',
                expiry: 3600,
            });

            expect(tokenChangedListener).toHaveBeenCalledTimes(1);

            loginService.setBearerAuthentication({
                access: 'updated_token',
                refresh: 'updated_refresh',
                expiry: 3600,
            });

            expect(tokenChangedListener).toHaveBeenCalledTimes(2);
            expect(tokenChangedListener).toHaveBeenCalledWith(
                expect.objectContaining({
                    access: 'updated_token',
                }),
            );
        });
    });

    describe('logoutSso', () => {
        let originalFetch;

        beforeEach(() => {
            originalFetch = global.fetch;
            global.fetch = jest.fn(() => Promise.resolve({ ok: true }));
        });

        afterEach(() => {
            global.fetch = originalFetch;
            sessionStorage.removeItem('sw-sso-session');
        });

        it('should revoke server tokens, clear auth state, and redirect to SSO with prompt=login', async () => {
            const { loginService, clientMock } = loginServiceFactory();
            const navigateToSpy = jest.fn();
            loginService._navigateTo = navigateToSpy;

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');

            clientMock.onGet(/\/oauth\/sso\/config/).reply(200, {
                useDefault: false,
                url: 'https://idp.example.com/authorize?client_id=test',
            });

            await loginService.logoutSso();

            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/_action/user/logout'),
                expect.objectContaining({ method: 'POST' }),
            );
            expect(loginService.getBearerAuthentication()).toBeFalsy();
            expect(navigateToSpy).toHaveBeenCalledWith('https://idp.example.com/authorize?client_id=test&usePromptLogin=1');
        });

        it('should fall back to regular logout when SSO config fetch fails', async () => {
            Shopware.Application.view.router = {
                currentRoute: { value: { fullPath: '/sw/dashboard/index', name: 'sw.dashboard.index' } },
                push: jest.fn(),
            };

            const { loginService, clientMock } = loginServiceFactory();
            const navigateToSpy = jest.fn();
            loginService._navigateTo = navigateToSpy;

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');

            clientMock.onGet(/\/oauth\/sso\/config/).reply(500);

            await loginService.logoutSso();

            expect(loginService.getBearerAuthentication()).toBeFalsy();
            expect(navigateToSpy).not.toHaveBeenCalled();
        });

        it('should fall back to regular logout when SSO config has no url', async () => {
            Shopware.Application.view.router = {
                currentRoute: { value: { fullPath: '/sw/dashboard/index', name: 'sw.dashboard.index' } },
                push: jest.fn(),
            };

            const { loginService, clientMock } = loginServiceFactory();
            const navigateToSpy = jest.fn();
            loginService._navigateTo = navigateToSpy;

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');

            clientMock.onGet(/\/oauth\/sso\/config/).reply(200, {
                useDefault: true,
                url: '',
            });

            await loginService.logoutSso();

            expect(loginService.getBearerAuthentication()).toBeFalsy();
            expect(navigateToSpy).not.toHaveBeenCalled();
        });

        it('should continue even if server-side token revocation fails', async () => {
            global.fetch = jest.fn(() => Promise.reject(new TypeError('Network error')));

            const { loginService, clientMock } = loginServiceFactory();
            const navigateToSpy = jest.fn();
            loginService._navigateTo = navigateToSpy;

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');

            clientMock.onGet(/\/oauth\/sso\/config/).reply(200, {
                useDefault: false,
                url: 'https://idp.example.com/authorize?client_id=test',
            });

            await loginService.logoutSso();

            expect(loginService.getBearerAuthentication()).toBeFalsy();
            expect(navigateToSpy).toHaveBeenCalledWith('https://idp.example.com/authorize?client_id=test&usePromptLogin=1');
        });

        it('should not redirect to SSO when useDefault is true and session is not SSO', async () => {
            Shopware.Application.view.router = {
                currentRoute: { value: { fullPath: '/sw/dashboard/index', name: 'sw.dashboard.index' } },
                push: jest.fn(),
            };

            const { loginService, clientMock } = loginServiceFactory();
            const navigateToSpy = jest.fn();
            loginService._navigateTo = navigateToSpy;

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');

            sessionStorage.removeItem('sw-sso-session');

            clientMock.onGet(/\/oauth\/sso\/config/).reply(200, {
                useDefault: true,
                url: 'https://idp.example.com/authorize?client_id=test',
            });

            await loginService.logoutSso();

            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining('/_action/user/logout'),
                expect.objectContaining({ method: 'POST' }),
            );
            expect(loginService.getBearerAuthentication()).toBeFalsy();
            expect(navigateToSpy).not.toHaveBeenCalled();
        });

        it('should redirect to SSO when useDefault is true but session was SSO', async () => {
            const { loginService, clientMock } = loginServiceFactory();
            const navigateToSpy = jest.fn();
            loginService._navigateTo = navigateToSpy;

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');

            sessionStorage.setItem('sw-sso-session', 'true');

            clientMock.onGet(/\/oauth\/sso\/config/).reply(200, {
                useDefault: true,
                url: 'https://idp.example.com/authorize?client_id=test',
            });

            await loginService.logoutSso();

            expect(loginService.getBearerAuthentication()).toBeFalsy();
            expect(navigateToSpy).toHaveBeenCalledWith('https://idp.example.com/authorize?client_id=test&usePromptLogin=1');
            expect(sessionStorage.getItem('sw-sso-session')).toBe('true');
        });

        it('should notify logout listeners when switching account', async () => {
            const { loginService, clientMock } = loginServiceFactory();
            const navigateToSpy = jest.fn();
            loginService._navigateTo = navigateToSpy;
            const logoutListener = jest.fn();
            loginService.addOnLogoutListener(logoutListener);

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginByUsername('admin', 'shopware');

            clientMock.onGet(/\/oauth\/sso\/config/).reply(200, {
                useDefault: false,
                url: 'https://idp.example.com/authorize?client_id=test',
            });

            await loginService.logoutSso();

            expect(logoutListener).toHaveBeenCalled();
        });
    });

    describe('multi-tab token synchronization', () => {
        it('should synchronize token across tabs via cookie storage', () => {
            const { loginService } = loginServiceFactory();
            const { loginService: loginServiceTab2 } = loginServiceFactory();

            loginService.setBearerAuthentication({
                access: 'test_token',
                refresh: 'test_refresh',
                expiry: 3600,
            });

            expect(loginServiceTab2.getToken()).toBe('test_token');
            expect(loginServiceTab2.getBearerAuthentication('refresh')).toBe('test_refresh');
        });

        it('should share refreshed token when two tabs refresh concurrently', async () => {
            jest.useFakeTimers();

            const originalLocks = navigator.locks;
            let lockQueue = Promise.resolve();

            Object.defineProperty(navigator, 'locks', {
                value: {
                    request: jest.fn((_name, callback) => {
                        const run = lockQueue.then(() => callback());
                        lockQueue = run.catch(() => undefined);

                        return run;
                    }),
                },
                configurable: true,
            });

            const tab1 = loginServiceFactory();
            const tab2 = loginServiceFactory();

            try {
                tab1.clientMock.onPost('/oauth/token').replyOnce(200, {
                    token_type: 'Bearer',
                    expires_in: 600,
                    access_token: 'initial_access_token',
                    refresh_token: 'initial_refresh_token',
                });

                await tab1.loginService.loginByUsername('admin', 'shopware');

                tab1.clientMock.resetHistory();
                tab2.clientMock.resetHistory();

                tab1.clientMock.onPost('/oauth/token').replyOnce(200, {
                    token_type: 'Bearer',
                    expires_in: 600,
                    access_token: 'refreshed_access_token',
                    refresh_token: 'refreshed_refresh_token',
                });

                const tab1RefreshPromise = tab1.loginService.refreshToken();
                const tab2RefreshPromise = tab2.loginService.refreshToken();

                await Promise.all([
                    expect(tab1RefreshPromise).resolves.toBe('refreshed_access_token'),
                    expect(tab2RefreshPromise).resolves.toBe('refreshed_access_token'),
                ]);

                const tab1RefreshRequests = tab1.clientMock.history.post.filter(
                    (req) => JSON.parse(req.data).grant_type === 'refresh_token',
                );
                const tab2RefreshRequests = tab2.clientMock.history.post.filter(
                    (req) => JSON.parse(req.data).grant_type === 'refresh_token',
                );

                expect(tab1RefreshRequests).toHaveLength(1);
                expect(tab2RefreshRequests).toHaveLength(0);
                expect(tab2.loginService.getToken()).toBe('refreshed_access_token');
            } finally {
                Object.defineProperty(navigator, 'locks', {
                    value: originalLocks,
                    configurable: true,
                });

                jest.useRealTimers();
            }
        });
    });
});
