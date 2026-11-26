/**
 * @sw-package framework
 */

import 'src/app/store/user-verification.store';
import UserVerificationService from './user-verification.service';
import UserVerificationError from './user-verification.error';
import type SsoReauthService from './sso-reauth.service';
import type { LoginService } from './login.service';

function createToken(scopes: string[], sub = 'user-a'): string {
    const payload = window.btoa(JSON.stringify({ scopes, sub })).replace(/\+/g, '-').replace(/\//g, '_');

    return `header.${payload}.signature`;
}

describe('src/core/service/user-verification.service.ts', () => {
    let accessScopes = ['write'];
    let accessSubject = 'user-a';
    const loginService = {
        logout: jest.fn(() => true),
        getLoginTemplateConfig: jest.fn(() => Promise.resolve({ useDefault: true, url: 'https://shop.test/sso' })),
        getBearerAuthentication: jest.fn((section?: string) => {
            const auth = { access: createToken(accessScopes, accessSubject), refresh: 'refresh', expiry: 1 };

            return section ? auth[section as keyof typeof auth] : auth;
        }),
    } as unknown as LoginService;
    // the server issues `user-verified` after a fresh identity provider login
    const reauthenticate = jest.fn(() => {
        accessScopes = ['write', 'user-verified'];

        return Promise.resolve();
    });
    const ssoReauthService = { reauthenticate } as unknown as SsoReauthService;
    const createNotification = jest.fn();

    const createService = () => new UserVerificationService(loginService, ssoReauthService);
    const store = () => Shopware.Store.get('userVerification');

    beforeEach(() => {
        accessScopes = ['write'];
        accessSubject = 'user-a';
        sessionStorage.clear();
        store().dismiss();
        jest.clearAllMocks();
        Shopware.Store.get('notification').createNotification = createNotification;
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockReturnValue({
            $t: (key: string) => key,
        } as unknown as ReturnType<typeof Shopware.Application.getApplicationRoot>);
    });

    it('asks through the modal and resolves with the verified context', async () => {
        const service = createService();
        const context = { ...Shopware.Context.api, authToken: { access: 'verified', refresh: 'r', expiry: 1 } };

        const result = service.verify();

        expect(store().requested).toBe(true);
        expect(loginService.getLoginTemplateConfig).not.toHaveBeenCalled();

        service.complete(context);

        await expect(result).resolves.toBe(context);
        expect(store().requested).toBe(false);
    });

    it('opens the modal for an SSO session as well', () => {
        sessionStorage.setItem('sw-sso-session', 'true');

        void createService()
            .verify()
            .catch(() => {});

        expect(store().requested).toBe(true);
        expect(reauthenticate).not.toHaveBeenCalled();
    });

    it('rejects as cancelled when the modal closes without a verification', async () => {
        const service = createService();

        const result = service.verify();
        service.cancel();

        await expect(result).rejects.toEqual(UserVerificationError.cancelled());
        expect(store().requested).toBe(false);
    });

    it('joins a running verification instead of asking twice', async () => {
        const service = createService();
        const context = { ...Shopware.Context.api };

        const first = service.verify();
        const second = service.verify();
        service.complete(context);

        await expect(first).resolves.toBe(context);
        await expect(second).resolves.toBe(context);
    });

    it('ignores a cancel without a running verification', () => {
        const service = createService();

        expect(() => service.cancel()).not.toThrow();
        expect(store().requested).toBe(false);
    });

    it.each([
        {
            name: 'a password session in a shop with an identity provider',
            marker: false,
            config: { useDefault: true, url: 'https://shop.test/sso' },
            expected: { ssoSession: false, loginUrl: 'https://shop.test/sso' },
        },
        {
            name: 'a session the login marked as SSO',
            marker: true,
            config: { useDefault: true, url: 'https://shop.test/sso' },
            expected: { ssoSession: true, loginUrl: 'https://shop.test/sso' },
        },
        {
            name: 'every session of a shop without password login',
            marker: false,
            config: { useDefault: false, url: 'https://shop.test/sso' },
            expected: { ssoSession: true, loginUrl: 'https://shop.test/sso' },
        },
        {
            name: 'an SSO marker in a shop without identity provider',
            marker: true,
            config: { useDefault: true, url: '' },
            expected: { ssoSession: false, loginUrl: '' },
        },
    ])('reports the SSO options for $name', async ({ marker, config, expected }) => {
        if (marker) {
            sessionStorage.setItem('sw-sso-session', 'true');
        }
        (loginService.getLoginTemplateConfig as jest.Mock).mockResolvedValueOnce(config);

        await expect(createService().getSsoOptions()).resolves.toEqual(expected);
    });

    it('offers only the password when the SSO configuration cannot be loaded', async () => {
        (loginService.getLoginTemplateConfig as jest.Mock).mockRejectedValueOnce(new Error('offline'));

        await expect(createService().getSsoOptions()).resolves.toEqual({ ssoSession: false, loginUrl: '' });
    });

    it('opens the identity provider window before the first await', () => {
        void createService()
            .verifyWithSso('https://shop.test/sso')
            .catch(() => {});

        expect(reauthenticate).toHaveBeenCalledWith('https://shop.test/sso');
    });

    it('resolves with the verified context after the identity provider login', async () => {
        const result = await createService().verifyWithSso('https://shop.test/sso');

        expect(result).toEqual({ ...Shopware.Context.api });
        expect(sessionStorage.getItem('sw-sso-session')).toBe('true');
        expect(createNotification).not.toHaveBeenCalled();
    });

    it('rejects an SSO re-authentication the user cancelled without a notification', async () => {
        reauthenticate.mockRejectedValueOnce(UserVerificationError.cancelled());

        await expect(createService().verifyWithSso('https://shop.test/sso')).rejects.toEqual(
            UserVerificationError.cancelled(),
        );
        expect(createNotification).not.toHaveBeenCalled();
    });

    it('tells the user when the SSO re-authentication cannot start', async () => {
        reauthenticate.mockRejectedValueOnce(UserVerificationError.ssoWindowBlocked());

        await expect(createService().verifyWithSso('https://shop.test/sso')).rejects.toEqual(
            UserVerificationError.ssoWindowBlocked(),
        );
        expect(createNotification).toHaveBeenCalledWith({
            variant: 'critical',
            title: 'global.default.error',
            message: 'global.sw-verify-user-modal.failure.sso-window-blocked',
        });
    });

    it('reports an unexpected SSO failure as a start failure', async () => {
        reauthenticate.mockRejectedValueOnce(new Error('network'));

        await expect(createService().verifyWithSso('https://shop.test/sso')).rejects.toEqual(
            UserVerificationError.ssoNotConfigured(),
        );
        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ message: 'global.sw-verify-user-modal.failure.sso-not-configured' }),
        );
    });

    it('falls back to the error message while the administration has no root', async () => {
        reauthenticate.mockRejectedValueOnce(UserVerificationError.ssoWindowBlocked());
        jest.spyOn(Shopware.Application, 'getApplicationRoot').mockReturnValue(false);

        await expect(createService().verifyWithSso('https://shop.test/sso')).rejects.toEqual(
            UserVerificationError.ssoWindowBlocked(),
        );
        expect(createNotification).toHaveBeenCalledWith({
            variant: 'critical',
            title: '',
            message: UserVerificationError.ssoWindowBlocked().message,
        });
    });

    it('rejects when the identity provider returned without a session', async () => {
        reauthenticate.mockImplementationOnce(() => {
            (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce(false);

            return Promise.resolve();
        });

        await expect(createService().verifyWithSso('https://shop.test/sso')).rejects.toEqual(
            UserVerificationError.noSession(),
        );
        expect(createNotification).toHaveBeenCalledTimes(1);
    });

    it('rejects when the window reported back without a verified token', async () => {
        reauthenticate.mockResolvedValueOnce(undefined);

        await expect(createService().verifyWithSso('https://shop.test/sso')).rejects.toEqual(
            UserVerificationError.noSession(),
        );
        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ message: 'global.sw-verify-user-modal.failure.no-session' }),
        );
    });

    it('rejects and logs out when the identity provider signed in another account', async () => {
        reauthenticate.mockImplementationOnce(() => {
            accessScopes = ['write', 'user-verified'];
            accessSubject = 'user-b';

            return Promise.resolve();
        });

        await expect(createService().verifyWithSso('https://shop.test/sso')).rejects.toEqual(
            UserVerificationError.otherUser(),
        );
        expect(loginService.logout).toHaveBeenCalledTimes(1);
        // the login page shows the reason after its reload; a notification would not survive it
        expect(sessionStorage.getItem('sw-login-alert')).toBe('global.sw-verify-user-modal.failure.other-user');
        expect(createNotification).not.toHaveBeenCalled();
    });

    it('reads the verified scope from the access token', () => {
        const service = createService();

        expect(service.isVerified()).toBe(false);

        (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce(createToken(['write', 'user-verified']));
        expect(service.isVerified()).toBe(true);

        (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce('not-a-jwt');
        expect(service.isVerified()).toBe(false);

        (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce(`header.${window.btoa('null')}.signature`);
        expect(service.isVerified()).toBe(false);

        (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce(false);
        expect(service.isVerified()).toBe(false);

        const withoutScopeList = `header.${window.btoa(JSON.stringify({ scopes: 'user-verified' }))}.signature`;
        (loginService.getBearerAuthentication as jest.Mock).mockReturnValueOnce(withoutScopeList);
        expect(service.isVerified()).toBe(false);
    });
});
