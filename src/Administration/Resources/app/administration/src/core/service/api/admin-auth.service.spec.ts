/**
 * @sw-package framework
 */
import AdminAuthApiService from 'src/core/service/api/admin-auth.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';
import type { AxiosInstance } from 'axios';

function createAdminAuthService() {
    const client = createHTTPClient(Shopware.Context.api) as AxiosInstance;
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const adminAuthService = new AdminAuthApiService(client, loginService);

    return { adminAuthService, clientMock };
}

describe('AdminAuthApiService', () => {
    it('has the correct service name', () => {
        const { adminAuthService } = createAdminAuthService();

        expect(adminAuthService.name).toBe('adminAuthService');
    });

    it('discovers the OIDC endpoints of a provider', async () => {
        const { adminAuthService, clientMock } = createAdminAuthService();

        const discovered = {
            issuer: 'https://idp.example.com',
            authorizationEndpoint: 'https://idp.example.com/authorize',
            tokenEndpoint: 'https://idp.example.com/token',
            jwksUri: 'https://idp.example.com/jwks',
            scopes: [
                'openid',
                'email',
            ],
        };

        clientMock.onPost('/_action/admin-auth/oidc/discover').reply(200, discovered);

        const result = await adminAuthService.discoverOidc({
            discoveryUrl: 'https://idp.example.com/.well-known/openid-configuration',
        });

        expect(result).toEqual(discovered);
        expect(clientMock.history.post).toHaveLength(1);
        expect(JSON.parse(clientMock.history.post[0].data as string)).toEqual({
            discoveryUrl: 'https://idp.example.com/.well-known/openid-configuration',
        });
    });

    it('lists the enrolled MFA methods of the current user', async () => {
        const { adminAuthService, clientMock } = createAdminAuthService();

        const methods = [
            {
                id: 'method-id',
                type: 'totp',
                active: true,
                label: 'My phone',
                lastUsedAt: null,
            },
        ];

        clientMock.onGet('/_action/admin-auth/mfa/methods').reply(200, { methods });

        await expect(adminAuthService.getMfaMethods()).resolves.toEqual(methods);
        expect(clientMock.history.get[0].url).toBe('/_action/admin-auth/mfa/methods');
    });

    it('starts and verifies a TOTP enrollment', async () => {
        const { adminAuthService, clientMock } = createAdminAuthService();

        clientMock.onPost('/_action/admin-auth/mfa/totp/register/options').reply(200, {
            id: 'enrollment-id',
            secret: 'SECRET',
            otpauthUri: 'otpauth://totp/…',
        });
        clientMock.onPost('/_action/admin-auth/mfa/totp/register/verify').reply(200, {
            success: true,
        });

        const options = await adminAuthService.totpRegisterOptions('My phone');
        expect(options.id).toBe('enrollment-id');
        expect(JSON.parse(clientMock.history.post[0].data as string)).toEqual({ label: 'My phone' });

        const result = await adminAuthService.totpRegisterVerify('enrollment-id', '123456');
        expect(result).toEqual({ success: true });
        expect(JSON.parse(clientMock.history.post[1].data as string)).toEqual({
            id: 'enrollment-id',
            code: '123456',
        });
    });

    it('starts and verifies a passkey enrollment', async () => {
        const { adminAuthService, clientMock } = createAdminAuthService();

        clientMock.onPost('/_action/admin-auth/mfa/webauthn/register/options').reply(200, {
            options: { challenge: 'Y2hhbGxlbmdl' },
            challengeToken: 'signed-challenge-token',
        });
        clientMock.onPost('/_action/admin-auth/mfa/webauthn/register/verify').reply(200, {
            id: 'method-id',
            success: true,
        });

        const options = await adminAuthService.webauthnRegisterOptions();
        expect(options.challengeToken).toBe('signed-challenge-token');

        const result = await adminAuthService.webauthnRegisterVerify({
            credential: '{"id":"credential-id"}',
            challengeToken: 'signed-challenge-token',
            label: 'My passkey',
        });

        expect(result).toEqual({ id: 'method-id', success: true });
        expect(JSON.parse(clientMock.history.post[1].data as string)).toEqual({
            credential: '{"id":"credential-id"}',
            challengeToken: 'signed-challenge-token',
            label: 'My passkey',
        });
    });

    it('generates recovery codes and unwraps the code list', async () => {
        const { adminAuthService, clientMock } = createAdminAuthService();

        clientMock.onPost('/_action/admin-auth/mfa/recovery-codes').reply(200, {
            codes: [
                'AAAA-BBBB',
                'CCCC-DDDD',
            ],
        });

        await expect(adminAuthService.generateRecoveryCodes()).resolves.toEqual([
            'AAAA-BBBB',
            'CCCC-DDDD',
        ]);
    });

    it('deletes an enrolled method by id', async () => {
        const { adminAuthService, clientMock } = createAdminAuthService();

        clientMock.onDelete('/_action/admin-auth/mfa/methods/method-id').reply(204);

        await adminAuthService.deleteMfaMethod('method-id');

        expect(clientMock.history.delete).toHaveLength(1);
        expect(clientMock.history.delete[0].url).toBe('/_action/admin-auth/mfa/methods/method-id');
    });
});
