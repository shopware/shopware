import LoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

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
        clientMock: clientMock
    };
};

describe('core/service/login.service.js', () => {
    beforeEach(() => {
        const mockDate = new Date(1577881800000);
        jest.spyOn(global, 'Date').mockImplementation(() => mockDate);

        const name = 'bearerAuth';
        document.cookie = `${name}=1; expires=1 Jan 1970 00:00:00 GMT;`;
        window.localStorage.removeItem('redirectFromLogin');
    });

    it('should contain all public functions', () => {
        const { loginService } = loginServiceFactory();

        expect(loginService).toHaveProperty('loginByUsername');
        expect(loginService).toHaveProperty('refreshToken');
        expect(loginService).toHaveProperty('getToken');
        expect(loginService).toHaveProperty('getBearerAuthentication');
        expect(loginService).toHaveProperty('setBearerAuthentication');
        expect(loginService).toHaveProperty('logout');
        expect(loginService).toHaveProperty('isLoggedIn');
        expect(loginService).toHaveProperty('addOnTokenChangedListener');
        expect(loginService).toHaveProperty('addOnLogoutListener');
        expect(loginService).toHaveProperty('addOnLoginListener');
        expect(loginService).toHaveProperty('notifyOnLoginListener');
    });

    it('should set the bearer authentication with the right expiry', () => {
        const { loginService } = loginServiceFactory();

        const auth = loginService.setBearerAuthentication({
            expiry: 300,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN'
        });

        expect(auth).toEqual({
            expiry: 1577882100,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN'
        });
    });

    it('should login and return the bearer token', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN'
            });

        const auth = await loginService.loginByUsername('admin', 'shopware');

        expect(auth).toEqual({
            expiry: 1577882400,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN'
        });
    });

    it('should clear the cookie succesfully after each test', async () => {
        const { loginService } = loginServiceFactory();

        const auth = loginService.getBearerAuthentication();

        expect(auth).toBeFalsy();
    });

    it('should get a new token', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN'
            });

        await loginService.loginByUsername('admin', 'shopware');

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn_TwO',
                refresh_token: 'rEfReSh_ToKeN_tWo'
            });

        const refreshToken = await loginService.refreshToken();
        expect(refreshToken).toEqual('aCcEsS_tOkEn_TwO');
    });

    it('should refresh the actual bearer auth', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN'
            });

        await loginService.loginByUsername('admin', 'shopware');

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 400,
                access_token: 'aCcEsS_tOkEn_TwO',
                refresh_token: 'rEfReSh_ToKeN_tWo'
            });

        await loginService.refreshToken();

        const bearerAuthentication = loginService.getBearerAuthentication();
        expect(bearerAuthentication).toEqual({
            access: 'aCcEsS_tOkEn_TwO',
            expiry: 1577882200,
            refresh: 'rEfReSh_ToKeN_tWo'
        });
    });

    it('should login and logout successfully', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN'
            });

        await loginService.loginByUsername('admin', 'shopware');

        const authLoggedIn = loginService.getBearerAuthentication();
        expect(authLoggedIn).toEqual({
            expiry: 1577882400,
            access: 'aCcEsS_tOkEn',
            refresh: 'rEfReSh_ToKeN'
        });

        loginService.logout();

        const newAuth = loginService.getBearerAuthentication();
        expect(newAuth).toBeFalsy();
    });

    it('should return the storage key', async () => {
        const { loginService } = loginServiceFactory();

        expect(loginService.getStorageKey()).toEqual('bearerAuth');
    });

    it('should check if user is logged in', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        expect(loginService.isLoggedIn()).toBeFalsy();

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN'
            });

        await loginService.loginByUsername('admin', 'shopware');

        expect(loginService.isLoggedIn()).toBeTruthy();
    });

    it('should return only the token', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN'
            });

        await loginService.loginByUsername('admin', 'shopware');

        expect(loginService.getToken()).toEqual('aCcEsS_tOkEn');
    });

    it('should return false when token is unparsable', async () => {
        const { loginService } = loginServiceFactory();

        document.cookie = 'bearerAuth=%7B%22acce{{"ss%%3A1577882400%7D';

        expect(loginService.getBearerAuthentication()).toBeFalsy();
    });

    it('should set the bearer auth also in non document environments', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        const documentClone = global.document;
        delete global.document;
        global.document = undefined;

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN'
            });

        await loginService.loginByUsername('admin', 'shopware');

        global.document = documentClone;
    });

    it('should call the listener', async () => {
        const { loginService, clientMock } = loginServiceFactory();

        const logoutListener = jest.fn();
        const tokenChangedListener = jest.fn();

        loginService.addOnLogoutListener(logoutListener);
        loginService.addOnTokenChangedListener(tokenChangedListener);

        clientMock.onPost('/oauth/token')
            .reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'aCcEsS_tOkEn',
                refresh_token: 'rEfReSh_ToKeN'
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
        window.localStorage.setItem('redirectFromLogin', true);

        const loginListener = jest.fn();

        loginService.addOnLoginListener(loginListener);
        expect(loginListener).not.toHaveBeenCalled();

        loginService.notifyOnLoginListener();
        expect(loginListener).toHaveBeenCalled();
    });

    it('should reject when no refresh token was found', async () => {
        const { loginService } = loginServiceFactory();

        await expect(loginService.refreshToken()).rejects.toThrowError();
    });
});
