/**
 * @sw-package framework
 */

import SsoReauthService from './sso-reauth.service';
import type { LoginService } from './login.service';

function jwtWithScopes(scopes: unknown): string {
    const encode = (value: unknown) =>
        window.btoa(JSON.stringify(value)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

    return `${encode({ alg: 'none' })}.${encode({ scopes })}.signature`;
}

describe('src/core/service/sso-reauth.service.ts', () => {
    const navigate = jest.fn();
    let accessToken: string | false = false;
    const loginService = {
        getBearerAuthentication: jest.fn(() => accessToken),
        getLoginTemplateConfig: jest.fn(() =>
            Promise.resolve({ useDefault: false, url: 'https://shop.test/api/oauth/sso/auth?rdm=abc' }),
        ),
    } as unknown as LoginService;

    const createService = () => new SsoReauthService(loginService, navigate);

    beforeEach(() => {
        sessionStorage.clear();
        accessToken = false;
        jest.clearAllMocks();
    });

    it('is verified when the access token carries the user-verified scope', () => {
        accessToken = jwtWithScopes(['write', 'user-verified']);

        expect(createService().isVerified()).toBe(true);
    });

    it('is not verified without the scope on the access token', () => {
        accessToken = jwtWithScopes(['write']);

        expect(createService().isVerified()).toBe(false);
    });

    it('is not verified without an access token or with one that cannot be read', () => {
        expect(createService().isVerified()).toBe(false);

        accessToken = 'not-a-jwt';
        expect(createService().isVerified()).toBe(false);

        accessToken = jwtWithScopes('user-verified');
        expect(createService().isVerified()).toBe(false);
    });

    it('sends the browser to the identity provider with a forced login and remembers the route', async () => {
        await createService().start('/sw/settings/language-pack');

        expect(navigate).toHaveBeenCalledWith('https://shop.test/api/oauth/sso/auth?rdm=abc&usePromptLogin=1');
        expect(sessionStorage.getItem('sw-sso-reauth-return-route')).toBe('/sw/settings/language-pack');
        expect(sessionStorage.getItem('redirectFromLogin')).toBe('true');
        expect(sessionStorage.getItem('sw-sso-session')).toBe('true');
    });

    it('refuses to start when the SSO login is not configured', async () => {
        (loginService.getLoginTemplateConfig as jest.Mock).mockResolvedValueOnce({ useDefault: true, url: '' });

        await expect(createService().start('/')).rejects.toThrow('The SSO login is not configured');
        expect(navigate).not.toHaveBeenCalled();
        expect(sessionStorage.getItem('sw-sso-reauth-return-route')).toBeNull();
    });

    it('returns the remembered route once on completion', async () => {
        const service = createService();
        await service.start('/sw/settings/language-pack');

        expect(service.complete()).toBe('/sw/settings/language-pack');
        expect(service.complete()).toBeNull();
    });
});
