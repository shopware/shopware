/**
 * @sw-package framework
 */

import OAuthAuthorizeApiService from 'src/core/service/api/oauth-authorize.api.service';
import type { OAuthAuthorizationParams } from 'src/core/service/api/oauth-authorize.api.service';
import createLoginService from 'src/core/service/login.service';
import createHTTPClient from 'src/core/factory/http.factory';
import MockAdapter from 'axios-mock-adapter';

const params: OAuthAuthorizationParams = {
    response_type: 'code',
    client_id: 'shopware-cli',
    redirect_uri: 'http://127.0.0.1:53421/callback',
    state: 'xyz',
    code_challenge: 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
    code_challenge_method: 'S256',
    scope: 'write',
};

function createOAuthAuthorizeApiService() {
    const client = createHTTPClient(Shopware.Context.api);
    const clientMock = new MockAdapter(client);
    const loginService = createLoginService(client, Shopware.Context.api);
    const oauthAuthorizeApiService = new OAuthAuthorizeApiService(client, loginService);

    return { oauthAuthorizeApiService, clientMock };
}

describe('src/core/service/api/oauth-authorize.api.service', () => {
    it('is registered correctly', () => {
        const { oauthAuthorizeApiService } = createOAuthAuthorizeApiService();

        expect(oauthAuthorizeApiService).toBeInstanceOf(OAuthAuthorizeApiService);
        expect(oauthAuthorizeApiService.name).toBe('oauthAuthorizeApiService');
        expect(oauthAuthorizeApiService.getApiBasePath()).toBe('oauth/authorize');
    });

    it('requests the authorization info with the authorization params as query', async () => {
        const { oauthAuthorizeApiService, clientMock } = createOAuthAuthorizeApiService();
        const info = {
            client: { id: 'shopware-cli', name: 'Shopware CLI' },
            redirectUri: 'http://127.0.0.1:53421/callback',
            scopes: ['write'],
        };

        clientMock.onGet('oauth/authorize/info').reply(200, info);

        const result = await oauthAuthorizeApiService.getInfo(params);

        expect(result).toEqual(info);

        const request = clientMock.history.get[0];
        expect(request).toBeDefined();
        expect(request?.params).toEqual(params);
        expect(request?.headers?.Authorization).toMatch(/^Bearer /);
    });

    it('rejects with the OAuth error of the backend when the request is invalid', async () => {
        const { oauthAuthorizeApiService, clientMock } = createOAuthAuthorizeApiService();

        clientMock.onGet('oauth/authorize/info').reply(400, {
            errors: [
                {
                    status: '400',
                    code: 'FRAMEWORK__OAUTH_INVALID_CLIENT',
                    title: 'Invalid client',
                    detail: 'Client authentication failed',
                },
            ],
        });

        await expect(oauthAuthorizeApiService.getInfo(params)).rejects.toMatchObject({
            response: {
                status: 400,
                data: {
                    errors: [{ detail: 'Client authentication failed' }],
                },
            },
        });
    });

    it.each([
        true,
        false,
    ])('posts the decision approved=%s together with the authorization params', async (approved) => {
        const { oauthAuthorizeApiService, clientMock } = createOAuthAuthorizeApiService();
        const redirectUri = approved
            ? 'http://127.0.0.1:53421/callback?code=abc&state=xyz'
            : 'http://127.0.0.1:53421/callback?error=access_denied&state=xyz';

        clientMock.onPost('oauth/authorize').reply(200, { redirectUri });

        const result = await oauthAuthorizeApiService.decide(params, approved);

        expect(result).toEqual({ redirectUri });

        const request = clientMock.history.post[0];
        expect(request).toBeDefined();
        expect(JSON.parse(request?.data as string)).toEqual({ ...params, approved });
        expect(request?.headers?.Authorization).toMatch(/^Bearer /);
    });
});
