/**
 * @sw-package framework
 *
 * Covers the ADMIN_AUTH login flows of the login service: method discovery, the `admin_primary`
 * grant (password + passkey) and the `admin_second_factor` grant, with special focus on the
 * in-memory-only handling of the pending MFA token.
 */

import LoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import * as webauthnHelper from 'src/core/helper/webauthn.helper';
import type { AxiosInstance } from 'axios';

jest.mock('src/core/helper/webauthn.helper', () => ({
    ...jest.requireActual<Record<string, unknown>>('src/core/helper/webauthn.helper'),
    getAssertion: jest.fn(),
}));

const getAssertionMock = webauthnHelper.getAssertion as jest.Mock;

const loginServiceFactory = () => {
    const client = createHTTPClient(Shopware.Context.api) as AxiosInstance;
    const clientMock = new MockAdapter(client);
    const contextMock = {} as Parameters<typeof LoginService>[1];

    return {
        loginService: LoginService(client, contextMock),
        clientMock,
    };
};

function base64UrlEncode(value: string): string {
    return window.btoa(value).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

/**
 * Build an unsigned JWT with the given payload, mirroring the token shape issued by the backend.
 */
function buildJwt(payload: Record<string, unknown>): string {
    const header = base64UrlEncode(JSON.stringify({ alg: 'RS256', typ: 'JWT' }));
    const body = base64UrlEncode(JSON.stringify(payload));

    return `${header}.${body}.signature`;
}

const pendingToken = buildJwt({
    sub: 'user-id',
    jti: 'challenge-jti',
    scopes: [
        'admin-mfa-pending',
        'admin-mfa-challenge:0123456789abcdef',
        'admin-mfa-methods:totp,recovery_codes,webauthn',
    ],
});

const fullToken = buildJwt({
    sub: 'user-id',
    jti: 'full-jti',
    scopes: [
        'write',
        'admin',
    ],
});

let cookieStorageMock = '';

describe('core/service/login.service.spec/admin-auth', () => {
    beforeAll(() => {
        Object.defineProperty(document, 'cookie', {
            set: (value) => {
                cookieStorageMock = `${cookieStorageMock}${value};`;
            },
            get: () => cookieStorageMock,
            configurable: true,
        });

        // eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
        Shopware.Service().register('userActivityService', () => {
            return {
                getLastUserActivity: () => new Date(),
                updateLastUserActivity: () => {},
            };
        });
    });

    beforeEach(() => {
        cookieStorageMock = '';
        getAssertionMock.mockReset();
    });

    afterEach(() => {
        localStorage.removeItem('rememberMe');
        sessionStorage.removeItem('redirectFromLogin');
    });

    describe('getAvailableAuthMethods', () => {
        it('should fetch the methods from the discovery endpoint', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            const methods = [
                { id: 'password', type: 'password', label: null, startUrl: null },
                { id: 'webauthn', type: 'webauthn', label: null, startUrl: null },
                {
                    id: 'okta',
                    type: 'oidc',
                    label: 'Okta',
                    startUrl: 'https://shop.example/api/_action/admin-auth/oidc/okta/start',
                },
            ];

            clientMock.onGet('/_action/admin-auth/methods').reply(200, {
                methods,
                managedByConfig: false,
                adminUiEnabled: true,
            });

            await expect(loginService.getAvailableAuthMethods()).resolves.toEqual(methods);

            expect(clientMock.history.get).toHaveLength(1);
            expect(clientMock.history.get[0].url).toBe('/_action/admin-auth/methods');
        });

        it('should return an empty list for a malformed response', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onGet('/_action/admin-auth/methods').reply(200, {});

            await expect(loginService.getAvailableAuthMethods()).resolves.toEqual([]);
        });
    });

    describe('loginPrimary', () => {
        it('should send the admin_primary password payload', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: fullToken,
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.loginPrimary('admin', 'shopware');

            expect(JSON.parse(clientMock.history.post[0].data as string)).toEqual({
                grant_type: 'admin_primary',
                client_id: 'administration',
                scope: 'write',
                method: 'password',
                username: 'admin',
                password: 'shopware',
            });
        });

        it('should complete the login like a classic password login when no second factor is required', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: fullToken,
                refresh_token: 'rEfReSh_ToKeN',
            });

            const result = await loginService.loginPrimary('admin', 'shopware');

            expect(result.mfaRequired).toBe(false);
            expect(result.methods).toEqual([]);
            expect(result.auth).toEqual({
                access: fullToken,
                refresh: 'rEfReSh_ToKeN',
                expiry: expect.any(Number) as number,
            });

            expect(loginService.hasPendingMfa()).toBe(false);
            expect(loginService.getToken()).toBe(fullToken);
            expect(sessionStorage.getItem('redirectFromLogin')).toBe('true');
        });

        it('should treat a malformed access token as a full token', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: 'not-a-jwt',
                refresh_token: 'rEfReSh_ToKeN',
            });

            const result = await loginService.loginPrimary('admin', 'shopware');

            expect(result.mfaRequired).toBe(false);
            expect(result.auth).not.toBeNull();
            expect(loginService.hasPendingMfa()).toBe(false);
        });

        it('should ask for a second factor when a pending token is issued', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 300,
                access_token: pendingToken,
            });

            const result = await loginService.loginPrimary('admin', 'shopware');

            expect(result).toEqual({
                mfaRequired: true,
                methods: [
                    'totp',
                    'recovery_codes',
                    'webauthn',
                ],
                auth: null,
            });
            expect(loginService.hasPendingMfa()).toBe(true);
        });

        it('should never persist or expose the pending token', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 300,
                access_token: pendingToken,
            });

            const result = await loginService.loginPrimary('admin', 'shopware');

            // Never handed out to callers ...
            expect(JSON.stringify(result)).not.toContain(pendingToken);
            expect(result).not.toHaveProperty('pendingToken');

            // ... never written to the auth cookie or any storage ...
            expect(cookieStorageMock).not.toContain(pendingToken);
            expect(document.cookie).not.toContain('bearerAuth');
            expect(loginService.getBearerAuthentication()).toBeFalsy();
            expect(sessionStorage.getItem('redirectFromLogin')).toBeNull();

            // ... and the user is not logged in yet.
            expect(loginService.isLoggedIn()).toBe(false);
        });

        it('should return an empty method list when the methods marker scope is missing', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 300,
                access_token: buildJwt({ scopes: ['admin-mfa-pending'] }),
            });

            const result = await loginService.loginPrimary('admin', 'shopware');

            expect(result.mfaRequired).toBe(true);
            expect(result.methods).toEqual([]);
        });
    });

    describe('loginWithPasskey', () => {
        it('should echo the challenge token and serialized assertion into the token request', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            const requestOptions = { challenge: 'Y2hhbGxlbmdl', allowCredentials: [] };
            const serializedAssertion = {
                id: 'credential-id',
                rawId: 'credential-id',
                type: 'public-key',
                response: {
                    authenticatorData: 'YXV0aERhdGE',
                    clientDataJSON: 'Y2xpZW50RGF0YQ',
                    signature: 'c2lnbmF0dXJl',
                    userHandle: null,
                },
            };

            clientMock.onPost('/_action/admin-auth/webauthn/login-options').reply(200, {
                options: requestOptions,
                challengeToken: 'signed-challenge-token',
            });
            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: fullToken,
                refresh_token: 'rEfReSh_ToKeN',
            });

            getAssertionMock.mockResolvedValue(serializedAssertion);

            const result = await loginService.loginWithPasskey();

            expect(getAssertionMock).toHaveBeenCalledWith(requestOptions);

            const tokenRequest = clientMock.history.post.find((request) => request.url === '/oauth/token');
            expect(JSON.parse(tokenRequest?.data as string)).toEqual({
                grant_type: 'admin_primary',
                client_id: 'administration',
                scope: 'write',
                method: 'webauthn',
                assertion: JSON.stringify(serializedAssertion),
                challengeToken: 'signed-challenge-token',
            });

            expect(result.mfaRequired).toBe(false);
            expect(loginService.getToken()).toBe(fullToken);
        });

        it('should switch to the MFA step when the passkey login yields a pending token', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/_action/admin-auth/webauthn/login-options').reply(200, {
                options: { challenge: 'Y2hhbGxlbmdl' },
                challengeToken: 'signed-challenge-token',
            });
            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 300,
                access_token: pendingToken,
            });

            getAssertionMock.mockResolvedValue({ id: 'credential-id' });

            const result = await loginService.loginWithPasskey();

            expect(result.mfaRequired).toBe(true);
            expect(result.auth).toBeNull();
            expect(loginService.hasPendingMfa()).toBe(true);
            expect(loginService.getBearerAuthentication()).toBeFalsy();
        });
    });

    describe('verifySecondFactor', () => {
        async function loginUntilPending() {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 300,
                access_token: pendingToken,
            });

            await loginService.loginPrimary('admin', 'shopware');

            return { loginService, clientMock };
        }

        it('should send the code with the pending token as Bearer authorization', async () => {
            const { loginService, clientMock } = await loginUntilPending();

            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: fullToken,
                refresh_token: 'rEfReSh_ToKeN',
            });

            const auth = await loginService.verifySecondFactor('totp', '123456');

            const secondFactorRequest = clientMock.history.post[1];
            expect(secondFactorRequest.headers?.Authorization).toBe(`Bearer ${pendingToken}`);
            expect(JSON.parse(secondFactorRequest.data as string)).toEqual({
                grant_type: 'admin_second_factor',
                client_id: 'administration',
                method: 'totp',
                code: '123456',
            });

            expect(auth).toEqual({
                access: fullToken,
                refresh: 'rEfReSh_ToKeN',
                expiry: expect.any(Number) as number,
            });

            expect(loginService.hasPendingMfa()).toBe(false);
            expect(loginService.getToken()).toBe(fullToken);
            expect(sessionStorage.getItem('redirectFromLogin')).toBe('true');
        });

        it('should send recovery codes with the recovery_codes method', async () => {
            const { loginService, clientMock } = await loginUntilPending();

            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: fullToken,
                refresh_token: 'rEfReSh_ToKeN',
            });

            await loginService.verifySecondFactor('recovery_codes', 'ABCD-EFGH');

            expect(JSON.parse(clientMock.history.post[1].data as string)).toEqual({
                grant_type: 'admin_second_factor',
                client_id: 'administration',
                method: 'recovery_codes',
                code: 'ABCD-EFGH',
            });
        });

        it('should keep the pending token on verification failure', async () => {
            const { loginService, clientMock } = await loginUntilPending();

            clientMock.onPost('/oauth/token').replyOnce(400, {
                errors: [{ detail: 'Invalid code' }],
            });

            await expect(loginService.verifySecondFactor('totp', '000000')).rejects.toBeDefined();

            expect(loginService.hasPendingMfa()).toBe(true);
            expect(loginService.getBearerAuthentication()).toBeFalsy();
        });

        it('should reject without a pending token and not send a request', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            await expect(loginService.verifySecondFactor('totp', '123456')).rejects.toThrow(
                'No pending MFA login available.',
            );

            expect(clientMock.history.post).toHaveLength(0);
        });

        it('should reject after the pending state was cleared', async () => {
            const { loginService, clientMock } = await loginUntilPending();

            loginService.clearPendingMfa();

            expect(loginService.hasPendingMfa()).toBe(false);
            await expect(loginService.verifySecondFactor('totp', '123456')).rejects.toThrow(
                'No pending MFA login available.',
            );

            // Only the initial primary login request was sent.
            expect(clientMock.history.post).toHaveLength(1);
        });
    });

    describe('verifySecondFactorWebauthn', () => {
        it('should verify a passkey second factor with the pending token as Bearer authorization', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 300,
                access_token: pendingToken,
            });

            await loginService.loginPrimary('admin', 'shopware');

            clientMock.onPost('/_action/admin-auth/webauthn/login-options').reply(200, {
                options: { challenge: 'Y2hhbGxlbmdl' },
                challengeToken: 'second-factor-challenge-token',
            });
            clientMock.onPost('/oauth/token').replyOnce(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: fullToken,
                refresh_token: 'rEfReSh_ToKeN',
            });

            const serializedAssertion = { id: 'credential-id', rawId: 'credential-id' };
            getAssertionMock.mockResolvedValue(serializedAssertion);

            const auth = await loginService.verifySecondFactorWebauthn();

            const tokenRequests = clientMock.history.post.filter((request) => request.url === '/oauth/token');
            const secondFactorRequest = tokenRequests[1];

            expect(secondFactorRequest.headers?.Authorization).toBe(`Bearer ${pendingToken}`);
            expect(JSON.parse(secondFactorRequest.data as string)).toEqual({
                grant_type: 'admin_second_factor',
                client_id: 'administration',
                method: 'webauthn',
                assertion: JSON.stringify(serializedAssertion),
                challengeToken: 'second-factor-challenge-token',
            });

            expect(auth.access).toBe(fullToken);
            expect(loginService.hasPendingMfa()).toBe(false);
        });

        it('should reject without a pending token', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            await expect(loginService.verifySecondFactorWebauthn()).rejects.toThrow('No pending MFA login available.');
            expect(clientMock.history.post).toHaveLength(0);
        });
    });

    describe('loginByUsername (flag-off path)', () => {
        it('should still use the classic password grant', async () => {
            const { loginService, clientMock } = loginServiceFactory();

            clientMock.onPost('/oauth/token').reply(200, {
                token_type: 'Bearer',
                expires_in: 600,
                access_token: fullToken,
                refresh_token: 'rEfReSh_ToKeN',
            });

            const auth = await loginService.loginByUsername('admin', 'shopware');

            expect((JSON.parse(clientMock.history.post[0].data as string) as { grant_type: string }).grant_type).toBe(
                'password',
            );
            expect(auth.access).toBe(fullToken);
            expect(sessionStorage.getItem('redirectFromLogin')).toBe('true');
        });
    });
});
