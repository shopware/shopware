/**
 * @sw-package framework
 */

import SsoReauthService from './sso-reauth.service';
import type { LoginService } from './login.service';

describe('src/core/service/sso-reauth.service.ts', () => {
    const navigate = jest.fn();
    let now = 1_000_000;
    const loginService = {
        getLoginTemplateConfig: jest.fn(() =>
            Promise.resolve({ useDefault: false, url: 'https://shop.test/api/oauth/sso/auth?rdm=abc' }),
        ),
    } as unknown as LoginService;

    const createService = () => new SsoReauthService(loginService, navigate, () => now);

    beforeEach(() => {
        sessionStorage.clear();
        now = 1_000_000;
        jest.clearAllMocks();
    });

    it('is not verified without a completed SSO login', () => {
        expect(createService().isRecentlyVerified()).toBe(false);
    });

    it('counts a completed SSO login as verified inside the window', () => {
        const service = createService();

        expect(service.complete()).toBeNull();
        now += SsoReauthService.VERIFICATION_TTL - 1;

        expect(service.isRecentlyVerified()).toBe(true);
    });

    it('forgets the verification once the window has passed', () => {
        const service = createService();
        service.complete();
        now += SsoReauthService.VERIFICATION_TTL;

        expect(service.isRecentlyVerified()).toBe(false);
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

    it('returns the remembered route once and starts the window on completion', async () => {
        const service = createService();
        await service.start('/sw/settings/language-pack');

        expect(service.complete()).toBe('/sw/settings/language-pack');
        expect(service.isRecentlyVerified()).toBe(true);
        expect(service.complete()).toBeNull();
    });
});
